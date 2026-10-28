<?php

namespace Tests\Feature;

use App\Portal\UserKey;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesHapi;
use Tests\Support\HapiFixtures;
use Tests\TestCase;

class LegacyEndpointTest extends TestCase
{
    use FakesHapi;

    private const URL = '/forms/public/regos/index.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->hapiResponses['GET /bookings/\d+/registration/qr_captain'] = Http::response('<svg xmlns="http://www.w3.org/2000/svg" id="captain"/>');
        $this->hapiResponses['GET /bookings/\d+/registration/short_link'] = Http::response('https://dfp.example/r/abc');
        $this->fakeHapi(HapiFixtures::booking());
    }

    public function test_the_captain_qr_code_is_still_served(): void
    {
        $this->get(self::URL.'?action=qr_captain&user_key='.HapiFixtures::key())
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('id="captain"', false);
    }

    public function test_the_registration_qr_code_is_still_served(): void
    {
        $this->get(self::URL.'?action=qr&user_key='.HapiFixtures::key(7001))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_the_short_link_is_still_served(): void
    {
        $this->get(self::URL.'?action=short&user_key='.HapiFixtures::key())->assertOk()->assertSee('https://dfp.example/r/abc');
    }

    public function test_keys_are_still_checked(): void
    {
        $this->get(self::URL.'?action=qr_captain&user_key='.UserKey::forBooking(HapiFixtures::BOOKING_ID, 999))
            ->assertForbidden()
            ->assertJson(['message' => 'Unable to access the Portal']);
    }

    public function test_retired_actions_are_not_served(): void
    {
        $this->get(self::URL.'?action=bookablepackages&user_key='.HapiFixtures::key())->assertNotFound();
    }
}
