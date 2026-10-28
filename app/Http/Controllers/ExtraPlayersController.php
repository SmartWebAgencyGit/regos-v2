<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayForExtraPlayersRequest;
use App\Http\Requests\SelectExtraPlayersRequest;
use App\Portal\AddPlayers\AddPlayersCheckout;
use App\Portal\AddPlayers\AddPlayersPricing;
use App\Portal\AddPlayers\AddPlayersQuote;
use App\Portal\Booking;
use App\Portal\PortalContext;
use App\Portal\PortalRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets a registered player pay to add players to their booking:
 * choose quantities, review the server-calculated total, pay.
 */
class ExtraPlayersController extends Controller
{
    private const SESSION_KEY = 'regos.extra_players';

    public function __construct(
        private readonly PortalContext $portal,
        private readonly PortalRepository $repository,
        private readonly AddPlayersPricing $pricing,
        private readonly AddPlayersCheckout $checkout,
    ) {}

    public function create(): Response
    {
        $booking = $this->bookingWithPackages();
        $selection = $this->selection($booking);

        return Inertia::render('ExtraPlayers/Select', [
            'currencySymbol' => $booking->currencySymbol(),
            'packages' => array_map(fn (array $package) => [
                'id' => $package['id'],
                'name' => $package['name'],
                'unitPrice' => $package['unit_price'],
                'booked' => $package['booked'],
                'max' => $package['max'],
                'quantity' => $selection[$package['id']] ?? 0,
            ], $this->pricing->packages($booking)),
        ]);
    }

    public function store(SelectExtraPlayersRequest $request): RedirectResponse
    {
        $booking = $this->bookingWithPackages();
        $quote = $this->pricing->quote($booking, $request->quantities());

        if ($quote->isEmpty()) {
            return back()->withErrors(['quantities' => 'Add Players to Go Ahead!']);
        }

        $request->session()->put(self::SESSION_KEY, [
            'booking_id' => $booking->id(),
            'quantities' => array_column($quote->lines, 'quantity', 'id'),
        ]);

        return redirect()->route('extra-players.checkout');
    }

    public function checkout(): Response|RedirectResponse
    {
        $booking = $this->bookingWithPackages();
        $quote = $this->quote($booking);

        if ($quote->isEmpty()) {
            return redirect()->route('extra-players');
        }

        return Inertia::render('ExtraPlayers/Checkout', [
            'quote' => [
                'lines' => array_map(fn (array $line) => [
                    'id' => $line['id'],
                    'name' => $line['name'],
                    'quantity' => $line['quantity'],
                    'unitPrice' => $quote->format($line['unit_price']),
                    'subtotal' => $quote->format($line['subtotal']),
                ], $quote->lines),
                'players' => $quote->playerCount(),
                'subtotal' => $quote->format($quote->subtotal()),
                'handlingFee' => $quote->handlingFee() > 0 ? $quote->format($quote->handlingFee()) : null,
                'tax' => $quote->tax() > 0 ? $quote->format($quote->tax()) : null,
                'total' => $quote->format($quote->total()),
            ],
            'organiser' => $booking->organiserName(),
            'billing' => [
                'billing_name_first' => $booking->organiserFirstName(),
                'billing_name_last' => $booking->organiserLastName(),
                'billing_address_street' => $booking->organiserAddress('street'),
                'billing_address_locality' => $booking->organiserAddress('locality'),
                'billing_address_town' => $booking->organiserAddress('town'),
                'billing_address_region' => $booking->organiserAddress('region'),
                'billing_address_country' => $booking->organiserCountryName(),
                'billing_address_postcode' => $booking->organiserAddress('postcode'),
                'card_holder_name' => trim($booking->organiserFirstName().' '.$booking->organiserLastName()),
            ],
        ]);
    }

    public function pay(PayForExtraPlayersRequest $request): RedirectResponse
    {
        $booking = $this->bookingWithPackages();
        $quote = $this->quote($booking);

        if ($quote->isEmpty()) {
            return redirect()->route('extra-players');
        }

        $result = $this->checkout->pay(
            $booking,
            $this->portal->registration(),
            $quote,
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        if (! $result->successful) {
            // Shown beside the Pay button rather than as a page-level notice.
            return back()->withErrors(['payment' => $result->message]);
        }

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('thanks');
    }

    /**
     * Fresh booking (with package prices) after the access checks have run.
     */
    private function bookingWithPackages(): Booking
    {
        abort_unless($this->checkout->isAvailableFor($this->portal->booking()), 404);

        return $this->repository->bookingWithPackages($this->portal->key());
    }

    /**
     * @return array<int, int>
     */
    private function selection(Booking $booking): array
    {
        $selection = session(self::SESSION_KEY);

        return ($selection['booking_id'] ?? null) === $booking->id() ? $selection['quantities'] : [];
    }

    private function quote(Booking $booking): AddPlayersQuote
    {
        return $this->pricing->quote($booking, $this->selection($booking));
    }
}
