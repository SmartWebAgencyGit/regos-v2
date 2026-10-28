<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakesHapi;
use Tests\Support\HapiFixtures;
use Tests\TestCase;

class GroupsTest extends TestCase
{
    use FakesHapi;

    private const ON_SITE = ['REMOTE_ADDR' => '10.1.3.20'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->hapiCustomers = [[
            'id' => HapiFixtures::CUSTOMER_ID,
            'name_first' => 'Jordan',
            'name_last' => 'Smith',
            'bookings' => [['id' => HapiFixtures::BOOKING_ID, 'customer_id' => HapiFixtures::CUSTOMER_ID, 'registration_key' => HapiFixtures::key(), 'pax_booked' => 12]],
        ]];

        $this->fakeHapi(HapiFixtures::booking());
    }

    public function test_on_site_devices_list_todays_groups_for_their_site(): void
    {
        $this->withServerVariables(self::ON_SITE)
            ->get('/groups')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Groups')
                ->where('onSite', true)
                ->has('groups', 1)
                ->where('groups.0', ['bookingId' => HapiFixtures::BOOKING_ID, 'name' => 'Jordan SMITH', 'initials' => 'JS', 'players' => 12]))
            ->assertDontSee(HapiFixtures::key());

        $url = $this->hapiRequests('GET', '/customers')[0][0];
        $this->assertStringContainsString('bookings.site_id=87', $url);
        $this->assertStringContainsString('bookings.status_id!=3', $url);
    }

    public function test_joining_a_group_signs_the_device_in(): void
    {
        $this->withServerVariables(self::ON_SITE)
            ->post('/groups/'.HapiFixtures::BOOKING_ID)
            ->assertRedirect(route('dashboard'));

        $this->assertSame(HapiFixtures::key(), session('regos.user_key'));
    }

    public function test_only_todays_groups_can_be_joined(): void
    {
        $this->withServerVariables(self::ON_SITE)
            ->from('/groups')
            ->post('/groups/555')
            ->assertRedirect('/groups')
            ->assertSessionHas('error', 'Error Joining Group');
    }

    public function test_off_site_visitors_cannot_use_the_group_picker(): void
    {
        $this->get('/groups')->assertRedirect(route('portal.entry'));
        $this->post('/groups/'.HapiFixtures::BOOKING_ID)->assertRedirect(route('portal.entry'));
    }

    public function test_leaving_a_group_returns_to_the_picker(): void
    {
        $this->signInAs()
            ->withServerVariables(self::ON_SITE)
            ->post('/logout')
            ->assertRedirect(route('groups.index'));

        $this->assertNull(session('regos.user_key'));
    }
}
