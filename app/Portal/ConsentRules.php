<?php

namespace App\Portal;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Decides which registration declarations apply to a player, from the
 * "regos.consents" configuration. The same definitions render the form
 * and validate the submission, so the two can no longer drift apart.
 *
 * Each wording has its own form input (e.g. "pyro_safety_dependent"), so
 * the server can check that the wording matching the player's age is the
 * one that was accepted. Several wordings may map to one HAPI field.
 *
 * @phpstan-type Consent array{field: string, input: string, label: string, link: ?string, min_age: ?int, max_age: ?int, required: bool}
 */
class ConsentRules
{
    /**
     * @param  list<array<string, mixed>>  $definitions
     */
    public function __construct(private readonly array $definitions) {}

    /**
     * Every declaration variant for the booking's country and site, before
     * the player's age is known (the form filters by age as they type).
     *
     * @return list<Consent>
     */
    public function forBooking(Booking $booking): array
    {
        $consents = [];

        foreach ($this->definitions as $definition) {
            if (! $this->matchesLocation($definition, $booking->countryId(), $booking->siteName())) {
                continue;
            }

            $consents[] = [
                'field' => (string) $definition['field'],
                'input' => str_replace('.', '_', (string) $definition['label']),
                'label' => (string) $definition['label'],
                'link' => $definition['link'] ?? null,
                'min_age' => isset($definition['min_age']) ? (int) $definition['min_age'] : null,
                'max_age' => isset($definition['max_age']) ? (int) $definition['max_age'] : null,
                'required' => (bool) ($definition['required'] ?? true),
            ];
        }

        return $consents;
    }

    /**
     * Declarations that apply to a player of the given age. An unknown age
     * is treated as an adult.
     *
     * @return list<Consent>
     */
    public function applicable(Booking $booking, ?int $age): array
    {
        return array_values(array_filter(
            $this->forBooking($booking),
            fn (array $consent) => self::matchesAge($consent, $age),
        ));
    }

    /**
     * Laravel validation rules for the declarations that apply.
     *
     * @return array<string, list<string>>
     */
    public function validationRules(Booking $booking, ?int $age): array
    {
        $rules = [];

        foreach ($this->applicable($booking, $age) as $consent) {
            $rules[$consent['input']] = $consent['required'] ? ['accepted'] : ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * Accepted / declined state of every HAPI declaration field, from the
     * submitted inputs. Wordings that do not apply count as declined.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, bool>
     */
    public function declarations(Booking $booking, ?int $age, array $input): array
    {
        $declarations = array_fill_keys($this->fields(), false);

        foreach ($this->applicable($booking, $age) as $consent) {
            if (filter_var($input[$consent['input']] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $declarations[$consent['field']] = true;
            }
        }

        return $declarations;
    }

    /**
     * Every declaration field HAPI stores, regardless of location.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        return array_values(array_unique(array_column($this->definitions, 'field')));
    }

    /**
     * @param  Consent  $consent
     */
    public static function matchesAge(array $consent, ?int $age): bool
    {
        if ($age === null) {
            return $consent['max_age'] === null;
        }

        return ($consent['min_age'] === null || $age >= $consent['min_age'])
            && ($consent['max_age'] === null || $age <= $consent['max_age']);
    }

    /**
     * Whole years of age today in the given timezone, or null when the date
     * of birth is missing or unparseable.
     */
    public static function ageFromDateOfBirth(?string $dateOfBirth, string $timezone): ?int
    {
        if ($dateOfBirth === null || $dateOfBirth === '') {
            return null;
        }

        try {
            $born = CarbonImmutable::createFromFormat('!Y-m-d', $dateOfBirth, $timezone);
        } catch (Throwable) {
            return null;
        }

        if ($born === null) {
            return null;
        }

        $today = CarbonImmutable::now($timezone)->startOfDay();

        return $born->greaterThan($today) ? 0 : (int) $born->diffInYears($today);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function matchesLocation(array $definition, ?int $countryId, string $siteName): bool
    {
        if (isset($definition['countries']) && ! in_array($countryId, $definition['countries'], true)) {
            return false;
        }

        if (isset($definition['except_countries']) && in_array($countryId, $definition['except_countries'], true)) {
            return false;
        }

        if (isset($definition['sites']) && ! in_array($siteName, $definition['sites'], true)) {
            return false;
        }

        return true;
    }
}
