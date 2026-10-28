<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakesHapi;
use Tests\Support\HapiFixtures;
use Tests\TestCase;

class BoardingPassTest extends TestCase
{
    use FakesHapi;

    public function test_a_verified_player_sees_their_boarding_pass(): void
    {
        $this->fakeHapi(HapiFixtures::booking(['registrations' => [HapiFixtures::registration(7001)]]));

        $this->signInAs(7001)
            ->get('/confirmation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('BoardingPass')
                ->where('registration.name', 'Sam LEE')
                ->where('registration.complete', true)
                ->where('booking.organiser', 'Jordan SMITH')
                ->where('booking.group', 'Bravo Company')
                ->where('qrUrl', route('qr', ['r' => 7001]))
                ->where('links.infoPack', 'https://www.paintballing.com.au/delta-force-paintball-appin-information-packs')
                ->where('links.itinerary', fn (string $url) => str_ends_with($url, 'DF-AUNZ-Winter-Paintball-Itinerary.pdf'))
                ->where('links.map', null)
                ->where('canAddPlayers', true)
                ->where('portal.registrationComplete', true));
    }

    public function test_an_unverified_player_is_asked_to_use_their_link(): void
    {
        $this->fakeHapi(HapiFixtures::booking(['registrations' => [HapiFixtures::registration(7001, ['confirmed' => false])]]));

        $this->signInAs(7001)
            ->get('/confirmation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('BoardingPass')
                ->where('registration.confirmed', false)
                ->where('qrUrl', null));
    }

    public function test_players_who_have_not_accepted_the_declarations_are_sent_to_verify(): void
    {
        $this->fakeHapi(HapiFixtures::booking(['registrations' => [HapiFixtures::registration(7001, ['safety_brief' => false])]]));

        $this->signInAs(7001)->get('/confirmation')->assertRedirect(route('verify'));
    }

    public function test_restricted_sites_hide_event_links_and_adding_players(): void
    {
        $this->fakeHapi(HapiFixtures::booking([
            'site' => ['name' => 'Leicester', 'address' => ['country_id' => 1]],
            'registrations' => [HapiFixtures::registration(7001)],
        ]));

        $this->signInAs(7001)
            ->get('/confirmation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('links.directions', null)
                ->where('links.itinerary', null)
                ->where('links.infoPack', null)
                ->where('canAddPlayers', false));
    }

    public function test_canadian_bookings_cannot_add_players_online(): void
    {
        $this->fakeHapi(HapiFixtures::booking(['site' => ['address' => ['country_id' => 5]], 'registrations' => [HapiFixtures::registration(7001)]]));

        $this->signInAs(7001)->get('/confirmation')->assertInertia(fn (Assert $page) => $page->where('canAddPlayers', false));
    }

    public function test_uk_sites_offer_their_map_and_miniball_itinerary(): void
    {
        $this->fakeHapi(HapiFixtures::booking([
            'gametype_id' => 2,
            'site' => ['name' => 'Banbury', 'address' => ['country_id' => 1]],
            'registrations' => [HapiFixtures::registration(7001)],
        ]));

        $this->signInAs(7001)
            ->get('/confirmation')
            ->assertInertia(fn (Assert $page) => $page
                ->where('links.map', asset('pdfs/maps/uk/Map%20United%20Kingdom%20Banbury.pdf'))
                ->where('links.itinerary', fn (string $url) => str_contains($url, 'miniball-day-itinerary')));
    }

    public function test_the_qr_code_is_served_from_hapi_as_a_locked_down_svg(): void
    {
        $this->fakeHapi(HapiFixtures::booking(['registrations' => [HapiFixtures::registration(7001)]]));

        $this->signInAs(7001)
            ->get('/qr')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'");

        $this->assertCount(1, $this->hapiRequests('GET', '/registrations/7001/qr'));
    }

    public function test_the_boarding_pass_needs_a_registration(): void
    {
        $this->fakeHapi(HapiFixtures::booking());

        $this->signInAs()->get('/confirmation')->assertRedirect(route('portal.entry'));
    }
}
