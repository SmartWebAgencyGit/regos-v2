<?php

namespace App\Portal\AddPlayers;

use App\Exceptions\Hapi\HapiException;
use App\Payments\PaymentGateway;
use App\Payments\PaymentRequest;
use App\Payments\PaymentResult;
use App\Portal\Booking;
use App\Portal\PortalRepository;
use App\Portal\Registration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Takes payment for extra players, then tells the booking office.
 *
 * After a successful charge the booking itself is not changed: an
 * interaction tagged for the booking office is added (with the legacy
 * "ADD_PAX::" / "|COSTOMER|" markers their tooling parses) and the
 * confirmation email is sent. Staff then update the booking.
 */
class AddPlayersCheckout
{
    /**
     * @param  array<string, mixed>  $config  the "regos" configuration
     */
    public function __construct(
        private readonly PortalRepository $repository,
        private readonly PaymentGateway $gateway,
        private readonly array $config,
    ) {}

    public function isAvailableFor(Booking $booking): bool
    {
        return ! in_array($booking->siteName(), (array) $this->config['restricted_sites'], true)
            && ! in_array($booking->countryId(), (array) $this->config['add_players']['hidden_countries'], true);
    }

    /**
     * @param  array{card_holder_name: string, card_number: string, expiry_date: string, security_code: string, billing_address_street: string, billing_address_locality?: ?string, billing_address_town: string, billing_address_postcode: string}  $details
     */
    public function pay(Booking $booking, ?Registration $registration, AddPlayersQuote $quote, array $details, ?string $ip, ?string $userAgent): PaymentResult
    {
        $country = (array) ($this->config['countries'][$booking->countryId()] ?? []);

        if (empty($country['merchant_code']) || $quote->currency === null || $quote->isEmpty()) {
            return PaymentResult::failed('Online payments are not available for this booking. Please contact us to add players.');
        }

        $lock = Cache::lock('regos:payment:'.$booking->id(), 120);

        if (! $lock->get()) {
            return PaymentResult::failed('A payment for this booking is already being processed. Please wait a moment before trying again.');
        }

        try {
            [$expiryMonth, $expiryYear] = explode('/', $details['expiry_date']);

            $request = new PaymentRequest(
                merchantCode: $country['merchant_code'],
                orderCode: $this->orderCode($booking, $registration),
                description: 'Regos Portal',
                amount: $quote->total(),
                currency: $quote->currency,
                cardNumber: $details['card_number'],
                cardHolderName: $details['card_holder_name'],
                expiryMonth: $expiryMonth,
                expiryYear: '20'.$expiryYear,
                securityCode: $details['security_code'],
                address1: $details['billing_address_street'],
                address2: (string) ($details['billing_address_locality'] ?? ''),
                city: $details['billing_address_town'],
                postcode: $details['billing_address_postcode'],
                countryCode: (string) ($country['code'] ?? ''),
                merchantPassword: $country['merchant_password'] ?? null,
            );

            $result = $this->gateway->charge($request);

            Log::channel('payments')->info('Add players payment', [
                'booking_id' => $booking->id(),
                'order_code' => $request->orderCode,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'players' => $quote->playerCount(),
                'successful' => $result->successful,
                'simulated' => $result->simulated,
                'last_event' => $result->lastEvent,
                'error_code' => $result->errorCode,
            ]);

            // A simulated payment must never reach a real booking or customer.
            if ($result->successful && ! $result->simulated) {
                $this->notifyBookingOffice($booking, $registration, $quote, $request, $ip, $userAgent);
            }

            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * The card has been charged by now, so failures here are logged for
     * staff to follow up rather than shown to the customer.
     */
    private function notifyBookingOffice(Booking $booking, ?Registration $registration, AddPlayersQuote $quote, PaymentRequest $request, ?string $ip, ?string $userAgent): void
    {
        $customer = [
            'email' => $registration?->email() ?? $booking->organiserEmail(),
            'name' => $registration?->firstName() ?: $booking->organiserFirstName(),
        ];

        try {
            $this->repository->recordInteraction($booking->id(), [
                'booking_id' => $booking->id(),
                'customer_id' => $booking->customerId(),
                'interaction_channel_id' => 7,
                'direction_id' => 1,
                'notes_user' => $this->interactionNote($booking, $quote, $customer),
                'notes_system' => json_encode(['ip' => $ip, 'user_agent' => $userAgent]),
                'tags' => [(int) $this->config['add_players']['interaction_tag']],
            ]);
        } catch (HapiException $exception) {
            Log::channel('payments')->critical('Paid for extra players but could not record the booking interaction', [
                'booking_id' => $booking->id(),
                'order_code' => $request->orderCode,
                'amount' => $request->amount,
                'lines' => $quote->lines,
                'error' => $exception->getMessage(),
            ]);
        }

        $recipients = array_values(array_filter([
            $customer['email'] ? ['address' => $customer['email'], 'name' => $customer['name']] : null,
            $this->config['add_players']['notify'],
        ]));

        try {
            $this->repository->sendEmail((int) $this->config['add_players']['email_template'], [
                'customer_id' => $booking->customerId(),
                'booking_id' => $booking->id(),
                'to' => $recipients,
            ]);
        } catch (HapiException $exception) {
            Log::channel('payments')->error('Paid for extra players but the confirmation email failed', [
                'booking_id' => $booking->id(),
                'order_code' => $request->orderCode,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{email: ?string, name: string}  $customer
     */
    private function interactionNote(Booking $booking, AddPlayersQuote $quote, array $customer): string
    {
        $packages = array_map(fn (array $line) => [
            'id' => $line['id'],
            'name' => $line['name'],
            'qty_booked' => $line['quantity'],
            'pack_total' => ($line['unit_handling_fee'] + $line['unit_price']) * $line['quantity'],
            'pack_total_no_fee' => $line['subtotal'],
        ], $quote->lines);

        return trim($booking->organiserFirstName().' '.$booking->organiserLastName())
            .' added package '.count($quote->lines).' players via online registration system on '
            .implode('', array_column($quote->lines, 'name'))
            .' date time '.Date::now('UTC')->format('d-m-Y h:i')
            .'ADD_PAX::'.json_encode($packages)
            .'::|COSTOMER|'.json_encode($customer);
    }

    /**
     * FIRSTNAME_{booking id}_REGO{timestamp}. The legacy code used only
     * minutes and seconds, which repeated and so could be rejected by
     * Worldpay as a duplicate order; a full timestamp keeps it unique.
     */
    private function orderCode(Booking $booking, ?Registration $registration): string
    {
        $name = Str::upper(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', Str::ascii($registration?->firstName() ?: $booking->organiserFirstName())), '_'));

        return Str::limit($name !== '' ? $name : 'PLAYER', 30, '').'_'.$booking->id().'_REGO'.Date::now('UTC')->format('ymdHis');
    }
}
