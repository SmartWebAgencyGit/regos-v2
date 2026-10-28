<?php

namespace App\Portal;

use App\Exceptions\Hapi\HapiException;
use Illuminate\Validation\ValidationException;

/**
 * Saves a player's registration to HAPI in the shape the legacy portal
 * used, translating HAPI's field errors back to form fields.
 */
class RegistrationService
{
    /**
     * HAPI error keys => registration form fields.
     */
    private const ERROR_FIELDS = [
        'customer.phones.0.value' => 'mobile',
        'customer.emails.0.value' => 'email',
        'customer.name_first' => 'name_first',
        'customer.name_last' => 'name_last',
        'customer.address.postcode' => 'postcode',
        'customer.date_of_birth' => 'date_of_birth',
    ];

    /**
     * Declarations HAPI receives as booleans; the rest are sent as 1 / 0.
     */
    private const BOOLEAN_DECLARATIONS = ['safety_brief', 'accept_terms', 'gamedate_may_change'];

    /**
     * Retired COVID-19 declarations HAPI still expects.
     */
    private const RETIRED_DECLARATIONS = ['covid_positive_recently', 'covid_result_wait', 'covid_symptoms', 'covid_contact', 'covid_spectators_not_allowed'];

    public function __construct(private readonly PortalRepository $repository) {}

    /**
     * Create the registration.
     *
     * @param  array{name_first: string, name_last: string, date_of_birth: string, mobile: string, email: string, postcode: string}  $player
     * @param  array<string, bool>  $declarations
     * @return int the registration id
     *
     * @throws ValidationException when HAPI rejects a field
     * @throws HapiException for any other HAPI failure
     */
    public function save(Booking $booking, array $player, array $declarations, ?string $ip, ?string $userAgent): int
    {
        try {
            return $this->repository->createRegistration($this->payload($booking, $player, $declarations, $ip, $userAgent));
        } catch (HapiException $exception) {
            $errors = $this->formErrors($exception);

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            throw $exception;
        }
    }

    /**
     * @param  array{name_first: string, name_last: string, date_of_birth: string, mobile: string, email: string, postcode: string}  $player
     * @param  array<string, bool>  $declarations
     * @return array<string, mixed>
     */
    public function payload(Booking $booking, array $player, array $declarations, ?string $ip, ?string $userAgent): array
    {
        $payload = ['booking_id' => $booking->id()];

        foreach ($declarations as $field => $accepted) {
            $payload[$field] = in_array($field, self::BOOLEAN_DECLARATIONS, true) ? $accepted : (int) $accepted;
        }

        foreach (self::RETIRED_DECLARATIONS as $field) {
            $payload[$field] = 0;
        }

        $payload['customer'] = [
            'name_first' => $player['name_first'],
            'name_last' => $player['name_last'],
            'date_of_birth' => $player['date_of_birth'],
            'address' => [
                'postcode' => $player['postcode'],
                'country_id' => $booking->countryId(),
            ],
            'interactions' => [[
                'booking_id' => $booking->id(),
                'interaction_channel_id' => 7,
                'direction_id' => 1,
                'notes_user' => 'Online Registration for '.trim($player['name_first'].' '.$player['name_last']),
                'notes_system' => json_encode(['ip' => $ip, 'user_agent' => $userAgent]),
                'tags' => [(int) config('regos.registration.interaction_tag')],
            ]],
            'phones' => [[
                'communication' => true,
                'customer_phone_type_id' => 1,
                'value' => $player['mobile'],
            ]],
            'emails' => [[
                'communication' => true,
                'customer_email_type_id' => 1,
                'value' => $player['email'],
            ]],
        ];

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function formErrors(HapiException $exception): array
    {
        $errors = [];

        foreach ($exception->validationErrors() as $key => $messages) {
            if (! isset(self::ERROR_FIELDS[$key])) {
                continue;
            }

            $message = is_array($messages) ? reset($messages) : $messages;

            // HAPI sometimes double-wraps the message array.
            while (is_array($message)) {
                $message = reset($message);
            }

            $errors[self::ERROR_FIELDS[$key]] = (string) $message;
        }

        return $errors;
    }
}
