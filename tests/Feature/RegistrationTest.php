<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakesHapi;
use Tests\Support\HapiFixtures;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use FakesHapi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeHapi(HapiFixtures::booking());
    }

    public function test_the_form_shows_the_declarations_for_the_bookings_country(): void
    {
        $this->signInAs()
            ->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Register')
                ->where('editing', false)
                ->where('guardianAge', 16)
                ->where('safetyVideo.embed', fn (string $url) => str_contains($url, 'youtube.com/embed/m-NG_8EwbMs'))
                ->where('consents', function ($consents) {
                    $inputs = collect($consents)->pluck('input');

                    return $inputs->contains('pyro_safety_player') && $inputs->contains('pyro_safety_dependent') && ! $inputs->contains('pyro_safety_uk');
                })
                ->where('consents.0', [
                    'input' => 'safety_brief',
                    'label' => 'I have watched and understood the :link',
                    'link' => ['text' => 'Paintball Safety Brief', 'href' => 'https://youtu.be/m-NG_8EwbMs'],
                    'minAge' => null,
                    'maxAge' => null,
                    'required' => true,
                ]));
    }

    public function test_details_and_declarations_are_required(): void
    {
        $this->signInAs()
            ->post('/register', [])
            ->assertSessionHasErrors([
                'name_first' => 'Please enter your first name',
                'email' => 'Please enter your email address',
                'date_of_birth' => 'Please enter your date of birth',
                'safety_brief' => 'Please watch the safety brief',
                'accept_terms' => 'Please accept the Terms and Conditions',
                'gamedate_may_change',
                'agree_dispute_resolution',
                'pyro_safety_player',
            ]);

        $this->assertSame([], $this->hapiRequests('POST', '/registrations'));
    }

    public function test_players_under_16_need_a_guardian(): void
    {
        $this->signInAs()
            ->post('/register', $this->adult(['date_of_birth' => $this->yearsAgo(15)]))
            ->assertSessionHasErrors(['guardian' => "Please enter the Guardian's name", 'guardian_permission' => 'The Guardian must provide permission']);
    }

    public function test_children_must_accept_the_wording_written_for_dependents(): void
    {
        $this->signInAs()
            ->post('/register', $this->adult([
                'date_of_birth' => $this->yearsAgo(10),
                'guardian' => 'Pat Smith',
                'guardian_permission' => '1',
            ]))
            ->assertSessionHasErrors(['pyro_safety_dependent', 'agree_supervision_dependent', 'parent_summary_sheet_check'])
            ->assertSessionDoesntHaveErrors(['pyro_safety_player']);
    }

    public function test_a_player_registers(): void
    {
        $this->signInAs()
            ->post('/register', $this->adult())
            ->assertRedirect(route('confirmation'));

        [$url, $payload] = $this->hapiRequests('POST', '/registrations')[0];

        $this->assertSame(HapiFixtures::BOOKING_ID, $payload['booking_id']);
        $this->assertTrue($payload['safety_brief']);
        $this->assertTrue($payload['accept_terms']);
        $this->assertSame(1, $payload['pyro_safety']);
        $this->assertSame(1, $payload['safety_equipment']);
        $this->assertSame(0, $payload['agree_supervision_dependent']);
        $this->assertSame(0, $payload['animal_safety_check']);
        $this->assertSame(0, $payload['covid_contact']);
        $this->assertSame([
            'name_first' => 'Riley',
            'name_last' => 'Nguyen',
            'date_of_birth' => '1990-05-20',
            'address' => ['postcode' => '2000', 'country_id' => 3],
        ], array_intersect_key($payload['customer'], array_flip(['name_first', 'name_last', 'date_of_birth', 'address'])));
        $this->assertSame('0400 333 444', $payload['customer']['phones'][0]['value']);
        $this->assertSame('riley@example.test', $payload['customer']['emails'][0]['value']);
        $this->assertSame('Online Registration for Riley Nguyen', $payload['customer']['interactions'][0]['notes_user']);

        $this->assertSame(['accept_terms' => true, 'safety_brief' => true], $this->hapiRequests('POST', '/registrations/7200')[0][1]);
        $this->assertSame(HapiFixtures::key(7200), session('regos.user_key'));
    }

    public function test_field_errors_from_hapi_are_shown_on_the_form(): void
    {
        $this->hapiResponses['POST /registrations'] = Http::response(['errors' => ['customer.emails.0.value' => [['The email must be a valid email address.']]]], 400);

        $this->signInAs()
            ->post('/register', $this->adult())
            ->assertSessionHasErrors(['email' => 'The email must be a valid email address.']);

        $this->assertSame(HapiFixtures::key(), session('regos.user_key'));
    }

    public function test_a_player_can_correct_their_details(): void
    {
        $this->correctableRegistration();
        $this->hapiResponses['GET /registrations/7001'] = Http::response(['data' => HapiFixtures::registration(7001, [
            'confirmed' => false,
            'customer' => ['date_of_birth' => '1990-05-20 00:00:00', 'address' => ['postcode' => '2560']],
        ])]);
        $this->fakeHapi($this->hapiBooking);

        $this->signInAs(7001)
            ->get('/register?edit=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Register')
                ->where('editing', true)
                ->where('player', [
                    'name_first' => 'Sam',
                    'name_last' => 'Lee',
                    'date_of_birth' => '1990-05-20',
                    'mobile' => '0400 000 001',
                    'email' => 'sam@example.test',
                    'postcode' => '2560',
                ]));

        $this->post('/register', $this->adult(['editing' => '1']))
            ->assertRedirect(route('confirmation'))
            ->assertSessionHas('status', 'Your details have been updated. We’ve sent a new verification link.');

        // HAPI refuses to update the customer, so the correction is a new registration.
        $this->assertSame('riley@example.test', $this->hapiRequests('POST', '/registrations')[0][1]['customer']['emails'][0]['value']);
        $this->assertSame([], $this->hapiRequests('POST', '/registrations/7001'));
        $this->assertSame(['accept_terms' => true, 'safety_brief' => true], $this->hapiRequests('POST', '/registrations/7200')[0][1]);
        $this->assertSame(HapiFixtures::key(7200), session('regos.user_key'));
    }

    public function test_the_correction_form_falls_back_to_the_bookings_copy_of_the_details(): void
    {
        $this->correctableRegistration();
        $this->hapiResponses['GET /registrations/7001'] = Http::response(['message' => 'Server Error'], 500);
        $this->fakeHapi($this->hapiBooking);

        $this->signInAs(7001)
            ->get('/register?edit=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('editing', true)
                ->where('player.email', 'sam@example.test')
                ->where('player.date_of_birth', ''));
    }

    private function correctableRegistration(): void
    {
        $this->hapiResponses['POST /registrations/7001'] = Http::response([
            'errors' => ['required_abilities' => ['customers_update']],
            'message' => 'Insufficient Permissions',
        ], 403);
        $this->fakeHapi(HapiFixtures::booking(['registrations' => [HapiFixtures::registration(7001, ['confirmed' => false])]]));
    }

    /**
     * A complete Australian adult registration.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function adult(array $overrides = []): array
    {
        return [
            'name_first' => 'Riley',
            'name_last' => 'Nguyen',
            'date_of_birth' => '1990-05-20',
            'mobile' => '0400 333 444',
            'email' => 'riley@example.test',
            'postcode' => '2000',
            'safety_brief' => '1',
            'limited_car_parking' => '1',
            'gamedate_may_change' => '1',
            'accept_terms' => '1',
            'agree_dispute_resolution' => '1',
            'safety_equipment_player' => '1',
            'agree_suitable_footwear_player' => '1',
            'agree_proceed_caution_player' => '1',
            'venues_spectator_friendly_player' => '1',
            'pyro_safety_player' => '1',
            'dispensing_equipment_player' => '1',
            ...$overrides,
        ];
    }

    private function yearsAgo(int $years): string
    {
        return CarbonImmutable::now('Australia/Sydney')->subYears($years)->subDay()->format('Y-m-d');
    }
}
