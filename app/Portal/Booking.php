<?php

namespace App\Portal;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Read-only view over a HAPI booking payload.
 */
final class Booking
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private readonly array $data) {}

    public function id(): int
    {
        return (int) $this->get('id');
    }

    public function customerId(): ?int
    {
        return $this->get('customer_id') === null ? null : (int) $this->get('customer_id');
    }

    public function paxBooked(): int
    {
        return (int) $this->get('pax_booked', 0);
    }

    public function group(): ?string
    {
        return $this->get('group') ?: null;
    }

    public function gametypeId(): ?int
    {
        return $this->get('gametype_id') === null ? null : (int) $this->get('gametype_id');
    }

    /**
     * Toolbar heading, e.g. "SMITH Group".
     */
    public function partyName(): string
    {
        $name = $this->get('customer.name_last') ?: $this->get('customer.name_first', '');

        return Str::upper((string) $name).' Group';
    }

    public function organiserName(): string
    {
        return self::formatName($this->get('customer.name_first'), $this->get('customer.name_last'));
    }

    public function organiserFirstName(): string
    {
        return (string) $this->get('customer.name_first', '');
    }

    public function organiserLastName(): string
    {
        return (string) $this->get('customer.name_last', '');
    }

    public function organiserEmail(): ?string
    {
        return $this->get('customer.emails.0.value');
    }

    /**
     * Organiser address field (street, locality, town, region, postcode).
     */
    public function organiserAddress(string $field): string
    {
        return (string) $this->get('customer.address.'.$field, '');
    }

    public function organiserCountryName(): string
    {
        return (string) $this->get('customer.address.country.name', '');
    }

    public function siteName(): string
    {
        return (string) $this->get('site.name', '');
    }

    public function countryId(): ?int
    {
        return $this->get('site.address.country_id') === null ? null : (int) $this->get('site.address.country_id');
    }

    public function timezone(): string
    {
        return $this->get('site.address.timezone') ?: 'UTC';
    }

    /**
     * Game start in the site's local time (HAPI stores local wall-clock time).
     */
    public function gameDate(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->get('game_date'), $this->timezone());
    }

    public function currencyCode(): ?string
    {
        return $this->get('site.address.country.currency.code');
    }

    /**
     * HAPI stores symbols as HTML entities (e.g. "&#163;" for £).
     */
    public function currencySymbol(): string
    {
        $symbol = $this->get('site.address.country.currency.symbol');

        return $symbol ? html_entity_decode((string) $symbol, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '$';
    }

    /**
     * Minimum age for this booking's game type at the site, if configured.
     */
    public function minimumAge(): ?int
    {
        foreach ((array) $this->get('site.site_gametypes', []) as $siteGametype) {
            if ((int) ($siteGametype['gametype_id'] ?? 0) === $this->gametypeId()) {
                return isset($siteGametype['min_age']) ? (int) $siteGametype['min_age'] : null;
            }
        }

        return null;
    }

    /**
     * @return list<Registration>
     */
    public function registrations(): array
    {
        return array_values(array_map(
            fn (array $registration) => new Registration($registration),
            (array) $this->get('registrations', []),
        ));
    }

    public function registration(?int $registrationId): ?Registration
    {
        if ($registrationId === null) {
            return null;
        }

        foreach ($this->registrations() as $registration) {
            if ($registration->id() === $registrationId) {
                return $registration;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function packages(): array
    {
        return array_values((array) $this->get('packages', []));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    public static function formatName(mixed $first, mixed $last): string
    {
        return trim($first.' '.Str::upper((string) $last));
    }
}
