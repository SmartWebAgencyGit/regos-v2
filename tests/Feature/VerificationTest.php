<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakesHapi;
use Tests\Support\HapiFixtures;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use FakesHapi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeHapi(HapiFixtures::booking(['registrations' => [
            HapiFixtures::registration(7001, ['accept_terms' => false, 'safety_brief' => false]),
        ]]));
    }

    public function test_the_player_accepts_the_indemnity_and_safety_brief(): void
    {
        $this->signInAs(7001)
            ->get('/verify')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verify')
                ->where('player', 'Sam')
                ->where('onSite', false)
                ->where('consents.accept_terms.link', ['text' => 'Indemnity Statement', 'href' => route('rules')])
                ->where('safetyVideo.embed', fn (string $url) => str_contains($url, 'youtube.com/embed/m-NG_8EwbMs')));

        $this->post('/verify', ['accept_terms' => '1', 'safety_brief' => '1'])->assertRedirect(route('confirmation'));

        $this->assertSame(['accept_terms' => true, 'safety_brief' => true], $this->hapiRequests('POST', '/registrations/7001')[0][1]);
    }

    public function test_both_declarations_are_required(): void
    {
        $this->signInAs(7001)
            ->post('/verify', [])
            ->assertSessionHasErrors(['accept_terms' => 'Please accept the Terms and Conditions', 'safety_brief' => 'Please watch the safety brief']);
    }

    public function test_on_site_the_safety_brief_is_given_in_person(): void
    {
        $this->signInAs(7001)
            ->withServerVariables(['REMOTE_ADDR' => '10.3.2.40'])
            ->get('/verify')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Verify')->where('onSite', true));

        $this->post('/verify', ['accept_terms' => '1'])->assertRedirect(route('confirmation'));
    }
}
