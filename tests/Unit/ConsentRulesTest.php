<?php

namespace Tests\Unit;

use App\Portal\Booking;
use App\Portal\ConsentRules;
use Tests\Support\HapiFixtures;
use Tests\TestCase;

class ConsentRulesTest extends TestCase
{
    public function test_adults_in_australia_get_the_player_wording(): void
    {
        $inputs = $this->inputs($this->australia(), 30);

        $this->assertSame([
            'safety_brief', 'limited_car_parking', 'gamedate_may_change', 'accept_terms', 'agree_dispute_resolution',
            'safety_equipment_player', 'agree_suitable_footwear_player', 'agree_proceed_caution_player',
            'venues_spectator_friendly_player', 'pyro_safety_player', 'dispensing_equipment_player',
        ], $inputs);
    }

    public function test_children_under_13_get_the_dependent_wording_and_parent_sheet(): void
    {
        $inputs = $this->inputs($this->australia(), 10);

        $this->assertContains('agree_supervision_dependent', $inputs);
        $this->assertContains('pyro_safety_dependent', $inputs);
        $this->assertContains('parent_summary_sheet_check', $inputs);
        $this->assertNotContains('pyro_safety_player', $inputs);
    }

    public function test_uk_players_get_uk_declarations_and_liverpool_adds_the_safari_park_notice(): void
    {
        $liverpool = new Booking(HapiFixtures::booking(['site' => ['name' => 'Liverpool', 'address' => ['country_id' => 1]]]));

        $inputs = $this->inputs($liverpool, 30);

        $this->assertContains('pyro_safety_uk', $inputs);
        $this->assertContains('animal_safety_check', $inputs);
        $this->assertContains('early_arrival_parking_wait', $inputs);
        $this->assertNotContains('safety_equipment_player', $inputs);
    }

    public function test_marketing_consent_is_optional(): void
    {
        $uk = new Booking(HapiFixtures::booking(['site' => ['address' => ['country_id' => 1]]]));

        $rules = $this->rules()->validationRules($uk, 30);

        $this->assertSame(['nullable', 'boolean'], $rules['accept_promotions_sms_emails']);
        $this->assertSame(['accepted'], $rules['accept_terms']);
    }

    public function test_an_unknown_age_is_treated_as_an_adult(): void
    {
        $this->assertSame($this->inputs($this->australia(), 30), $this->inputs($this->australia(), null));
    }

    public function test_declarations_map_each_wording_to_its_hapi_field(): void
    {
        $declarations = $this->rules()->declarations($this->australia(), 10, [
            'pyro_safety_dependent' => '1',
            'pyro_safety_player' => '1',
            'safety_equipment_player' => '1', // adult wording does not count for a child
        ]);

        $this->assertTrue($declarations['pyro_safety']);
        $this->assertFalse($declarations['safety_equipment']);
        $this->assertFalse($declarations['animal_safety_check']);
    }

    public function test_age_is_whole_years_in_the_site_timezone(): void
    {
        // Still 14 June in UTC but already 06:00 on 15 June in Sydney.
        $this->travelTo('2026-06-14 20:00:00');

        $this->assertSame(16, ConsentRules::ageFromDateOfBirth('2010-06-15', 'Australia/Sydney'));
        $this->assertSame(15, ConsentRules::ageFromDateOfBirth('2010-06-15', 'UTC'));
        $this->assertSame(15, ConsentRules::ageFromDateOfBirth('2010-06-16', 'Australia/Sydney'));
        $this->assertNull(ConsentRules::ageFromDateOfBirth('15/06/2010', 'Australia/Sydney'));
    }

    private function australia(): Booking
    {
        return new Booking(HapiFixtures::booking());
    }

    /**
     * @return list<string>
     */
    private function inputs(Booking $booking, ?int $age): array
    {
        return array_column($this->rules()->applicable($booking, $age), 'input');
    }

    private function rules(): ConsentRules
    {
        return new ConsentRules(config('regos.consents'));
    }
}
