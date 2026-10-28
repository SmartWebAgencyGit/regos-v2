<?php

namespace Tests\Feature;

use App\Portal\UserKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakesHapi;
use Tests\Support\HapiFixtures;
use Tests\TestCase;

class PortalEntryTest extends TestCase
{
    use FakesHapi;

    public function test_visitors_without_a_link_are_told_how_to_get_in(): void
    {
        $this->fakeHapi(HapiFixtures::booking());

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Message')
                ->where('tone', 'denied')
                ->where('message', 'Please use the link in the confirmation SMS or Email to access this portal')
                ->where('portal', null));

        $this->assertSame([], $this->hapiRequests('GET', '/bookings/\d+'), 'Pages shown before sign-in must not call HAPI.');
    }

    public function test_a_valid_link_signs_the_organiser_in_and_opens_the_dashboard(): void
    {
        $this->fakeHapi(HapiFixtures::booking());

        $this->get('/?k='.HapiFixtures::key())->assertRedirect(route('portal.entry'));
        $this->assertSame(HapiFixtures::key(), session('regos.user_key'));

        $this->get('/')->assertRedirect(route('dashboard'));
        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('booking.party', 'SMITH Group')
                ->where('booking.site', 'Appin')
                ->where('booking.gameDate.time', '9:30 am')
                ->where('registration', null)
                ->where('portal.party', 'SMITH Group')
                ->where('portal.hasRegistration', false));
    }

    public function test_a_registration_link_verifies_the_registration_and_opens_the_boarding_pass(): void
    {
        $this->fakeHapi(HapiFixtures::booking(['registrations' => [HapiFixtures::registration(7001)]]));

        $this->followingRedirects()
            ->get('/?k='.HapiFixtures::key(7001))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('BoardingPass')->where('registration.complete', true));

        $this->assertSame(['confirmed' => true], $this->hapiRequests('POST', '/registrations/7001')[0][1]);
    }

    public function test_registrations_without_accepted_declarations_are_sent_to_verify(): void
    {
        $this->fakeHapi(HapiFixtures::booking(['registrations' => [HapiFixtures::registration(7001, ['accept_terms' => false])]]));

        $this->signInAs(7001)->get('/')->assertRedirect(route('verify'));
    }

    public function test_a_removed_registration_falls_back_to_the_group_dashboard(): void
    {
        $this->fakeHapi(HapiFixtures::booking());

        $this->signInAs(7999)->get('/')->assertRedirect(route('dashboard'));
        $this->assertSame(HapiFixtures::key(), session('regos.user_key'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('refusedBookings')]
    public function test_links_to_unavailable_bookings_are_refused(array $overrides, string $message, ?string $key = null): void
    {
        $this->fakeHapi(HapiFixtures::booking($overrides));

        $this->followingRedirects()
            ->get('/?k='.($key ?? HapiFixtures::key()))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Message')
                ->where('tone', 'denied')
                ->where('message', fn (string $shown) => str_contains($shown, $message)));

        $this->assertNull(session('regos.user_key'));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2?: string}>
     */
    public static function refusedBookings(): array
    {
        return [
            'link for another customer' => [[], 'Unable to access the Portal', UserKey::forBooking(HapiFixtures::BOOKING_ID, 999)->toString()],
            'garbled link' => [[], 'Error decoding link', 'not-a-key'],
            'cancelled' => [['status_id' => 3], 'The booking has been cancelled'],
            'partner centre' => [['site' => ['centre' => ['is_partner' => 1]]], 'Registration is not available for this location'],
            'after game day' => [['game_date' => CarbonImmutable::now('Australia/Sydney')->subDay()->format('Y-m-d 09:30:00')], 'Access to this booking is now closed'],
        ];
    }

    public function test_access_stays_open_until_midnight_on_game_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-07 23:30', 'Australia/Sydney'));
        $this->fakeHapi(HapiFixtures::booking(['game_date' => '2026-11-07 09:30:00']));

        $this->signInAs()->get('/dashboard')->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-11-08 00:00', 'Australia/Sydney'));

        $this->followingRedirects()
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->component('Message')->where('message', 'Access to this booking is now closed'));
    }

    public function test_a_missing_booking_is_reported(): void
    {
        $this->hapiResponses['GET /bookings/\d+'] = Http::response(['message' => 'Not found'], 404);
        $this->fakeHapi(HapiFixtures::booking());

        $this->followingRedirects()
            ->get('/?k='.HapiFixtures::key())
            ->assertInertia(fn (Assert $page) => $page->component('Message')->where('message', 'Could not locate booking'));
    }

    public function test_portal_pages_need_a_link(): void
    {
        $this->fakeHapi(HapiFixtures::booking());

        $this->get('/dashboard')->assertRedirect(route('portal.entry'));
        $this->get('/confirmation')->assertRedirect(route('portal.entry'));
    }

    public function test_a_hapi_outage_shows_a_friendly_error_and_keeps_the_visitor_signed_in(): void
    {
        $this->hapiResponses['GET /bookings/\d+'] = Http::response(['message' => 'Server Error'], 500);
        $this->fakeHapi(HapiFixtures::booking());

        $this->signInAs()
            ->get('/dashboard')
            ->assertStatus(503)
            ->assertInertia(fn (Assert $page) => $page->component('Message')->where('tone', 'unavailable'));

        $this->assertSame(HapiFixtures::key(), session('regos.user_key'));
    }

    public function test_on_site_visitors_without_a_link_go_to_the_group_picker(): void
    {
        $this->fakeHapi(HapiFixtures::booking());

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.3.20'])->get('/')->assertRedirect(route('groups.index'));
    }

    public function test_a_forwarded_for_header_cannot_fake_an_on_site_address(): void
    {
        $this->fakeHapi(HapiFixtures::booking());

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
            ->withHeader('X-Forwarded-For', '10.1.3.20')
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Message')->where('onSite', false));
    }
}
