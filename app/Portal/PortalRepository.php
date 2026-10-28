<?php

namespace App\Portal;

use App\Exceptions\Hapi\HapiException;
use App\Exceptions\Portal\PortalAccessDenied;
use App\Hapi\Hapi;
use Carbon\CarbonImmutable;

/**
 * Portal reads and writes against HAPI. Queries mirror the legacy API's
 * "includes" / "columns" so HAPI returns exactly the shapes it always has.
 */
class PortalRepository
{
    private const BOOKING_INCLUDES = [
        'site.address',
        'site.address.country',
        'site.address.country.currency',
        'site.site_gametypes',
        'customer.emails',
        'customer.phones',
        'customer.address',
        'customer.address.country',
        'customer.address.country.currency',
        'registrations.customer.emails',
        'registrations.customer.phones',
        'registrations.customer.address',
        'packages',
    ];

    private const BOOKING_COLUMNS = [
        'id', 'pax_booked', 'game_date', 'group', 'status_id', 'gametype_id', 'min_age', 'site_id',
        'site.id', 'site.name', 'site.address_id', 'site.address.id', 'site.address.timezone', 'site.address.country_id',
        'site.site_gametypes.id', 'site.site_gametypes.site_id', 'site.site_gametypes.gametype_id', 'site.site_gametypes.min_age',
        'customer_id', 'customer.id', 'customer.name_first', 'customer.name_last',
        'customer.emails.id', 'customer.emails.customer_id', 'customer.emails.value',
        'customer.phones.id', 'customer.phones.customer_id', 'customer.phones.value', 'customer.address_id',
        'registrations.booking_id', 'registrations.customer_id', 'registrations.customer.id',
        'registrations.confirmed', 'registrations.safety_brief', 'registrations.accept_terms',
        'registrations.customer.name_first', 'registrations.customer.name_last',
        'registrations.customer.emails.id', 'registrations.customer.emails.customer_id', 'registrations.customer.emails.value',
        'registrations.customer.phones.id', 'registrations.customer.phones.customer_id', 'registrations.customer.phones.value',
    ];

    public function __construct(private readonly Hapi $hapi) {}

    /**
     * Confirm the key belongs to the booking's customer and that online
     * access is still open (not cancelled, not a partner centre, not past
     * midnight after the game).
     *
     * @throws PortalAccessDenied
     * @throws HapiException when HAPI is unavailable
     */
    public function authorize(UserKey $key): void
    {
        $includes = ['site.address', 'site.centre'];
        $columns = [
            'id', 'customer_id', 'status_id', 'game_date', 'site_id', 'site.id', 'site.address_id',
            'site.address.id', 'site.address.timezone', 'site.centre.id', 'site.centre.site_id', 'site.centre.is_partner',
        ];

        try {
            $booking = $this->hapi->get($this->bookingUri($key->bookingId, $includes, $columns))['data'] ?? null;
        } catch (HapiException $exception) {
            // Anything other than "not found" is an outage, not a refusal.
            throw $exception->status === 404 ? new PortalAccessDenied('Could not locate booking') : $exception;
        }

        if (! is_array($booking) || ! $key->belongsToCustomer($booking['customer_id'] ?? '')) {
            throw PortalAccessDenied::generic();
        }

        if ((int) ($booking['status_id'] ?? 0) === 3) {
            throw new PortalAccessDenied('The booking has been cancelled');
        }

        if (! empty($booking['site']['centre']['is_partner'])) {
            throw new PortalAccessDenied('Registration is not available for this location');
        }

        if (empty($booking['game_date'])) {
            throw PortalAccessDenied::generic();
        }

        $timezone = ($booking['site']['address']['timezone'] ?? null) ?: 'UTC';
        $closesAt = CarbonImmutable::parse($booking['game_date'], $timezone)->addDay()->startOfDay();

        if (CarbonImmutable::now($timezone)->greaterThanOrEqualTo($closesAt)) {
            throw new PortalAccessDenied('Access to this booking is now closed');
        }
    }

    /**
     * The booking as shown throughout the portal.
     */
    public function booking(UserKey $key): Booking
    {
        return new Booking($this->hapi->get($this->bookingUri($key->bookingId, self::BOOKING_INCLUDES, self::BOOKING_COLUMNS))['data'] ?? []);
    }

    /**
     * The booking with package pricing and taxes, for adding players.
     */
    public function bookingWithPackages(UserKey $key): Booking
    {
        $includes = [...self::BOOKING_INCLUDES, 'packages.products.taxes'];
        // Listing any column of a relation limits HAPI to the listed ones, so
        // the currency's code (charged in) and symbol (shown) must be named.
        $columns = [
            ...array_values(array_diff(self::BOOKING_COLUMNS, ['min_age'])),
            'site.address.country.currency.id',
            'site.address.country.currency.code',
            'site.address.country.currency.symbol',
            'packages.products.taxes.rate',
            'packages.products.taxes.id',
        ];

        return new Booking($this->hapi->get($this->bookingUri($key->bookingId, $includes, $columns))['data'] ?? []);
    }

    /**
     * Today's groups (in the site's timezone) for the on-site group picker.
     *
     * @return list<array{booking_id: int, name_first: string, name_last: string, registration_key: string, pax_booked: int}>
     */
    public function groups(int $siteId): array
    {
        $site = $this->hapi->get('/sites/'.$siteId.'?includes=address')['data'] ?? [];
        $timezone = ($site['address']['timezone'] ?? null) ?: 'UTC';
        $today = CarbonImmutable::now($timezone);

        $arguments = [
            'bookings.status_id!=3',
            'bookings.site_id='.($site['id'] ?? $siteId),
            'bookings.game_date>='.$today->format('Y-m-d'),
            'bookings.game_date<'.$today->addDay()->format('Y-m-d'),
            'bookings.pax_booked>0',
            'order_by=name_last,asc',
            'limit=unlimited',
        ];
        $columns = ['id', 'name_first', 'name_last', 'bookings.id', 'bookings.customer_id', 'bookings.registration_key', 'bookings.pax_booked'];

        $customers = $this->hapi->get('/customers?'.implode('&', $arguments).'&columns='.implode(',', $columns).'&includes=bookings')['data'] ?? [];

        $groups = [];

        foreach ($customers as $customer) {
            $booking = $customer['bookings'][0] ?? null;

            if (! $booking || empty($booking['registration_key'])) {
                continue;
            }

            $groups[] = [
                'booking_id' => (int) $booking['id'],
                'name_first' => (string) ($customer['name_first'] ?? ''),
                'name_last' => (string) ($customer['name_last'] ?? ''),
                'registration_key' => (string) $booking['registration_key'],
                'pax_booked' => (int) ($booking['pax_booked'] ?? 0),
            ];
        }

        return $groups;
    }

    /**
     * Mark the registration verified (the player opened their SMS / email link).
     */
    public function confirmRegistration(int $registrationId): void
    {
        $this->hapi->post('/registrations/'.$registrationId, ['confirmed' => true]);
    }

    public function acceptDeclarations(int $registrationId): void
    {
        $this->hapi->post('/registrations/'.$registrationId, ['accept_terms' => true, 'safety_brief' => true]);
    }

    /**
     * The player's details as they registered them, for correcting them.
     */
    public function registration(int $registrationId): Registration
    {
        $includes = ['customer.address', 'customer.phones', 'customer.emails'];
        $columns = [
            'id', 'booking_id', 'customer_id',
            'customer.id', 'customer.name_first', 'customer.name_last', 'customer.date_of_birth', 'customer.address_id',
            'customer.address.id', 'customer.address.postcode',
            'customer.phones.id', 'customer.phones.customer_id', 'customer.phones.value',
            'customer.emails.id', 'customer.emails.customer_id', 'customer.emails.value',
        ];

        return new Registration($this->hapi->get('/registrations/'.$registrationId.'?includes='.implode(',', $includes).'&columns='.implode(',', $columns))['data'] ?? []);
    }

    /**
     * Create a registration, returning its id. There is no update: HAPI
     * refuses to let the portal's account change a player's customer record.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createRegistration(array $payload): int
    {
        return (int) ($this->hapi->post('/registrations', $payload)['data']['id'] ?? 0);
    }

    public function registrationQr(int $registrationId): string
    {
        return $this->hapi->raw('/registrations/'.$registrationId.'/qr');
    }

    public function captainQr(int $bookingId): string
    {
        return $this->hapi->raw('/bookings/'.$bookingId.'/registration/qr_captain');
    }

    public function shortLink(int $bookingId): string
    {
        return $this->hapi->raw('/bookings/'.$bookingId.'/registration/short_link');
    }

    /**
     * @param  array<string, mixed>  $interaction
     */
    public function recordInteraction(int $bookingId, array $interaction): void
    {
        $this->hapi->post('/bookings/'.$bookingId, ['interactions' => $interaction]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendEmail(int $templateId, array $payload): void
    {
        $this->hapi->post('/emails/'.$templateId.'/send', $payload);
    }

    /**
     * @param  list<string>  $includes
     * @param  list<string>  $columns
     */
    private function bookingUri(int $bookingId, array $includes, array $columns): string
    {
        return '/bookings/'.$bookingId.'?includes='.implode(',', $includes).'&columns='.implode(',', $columns);
    }
}
