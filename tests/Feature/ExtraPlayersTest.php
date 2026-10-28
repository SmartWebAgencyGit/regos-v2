<?php

namespace Tests\Feature;

use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakesHapi;
use Tests\Support\HapiFixtures;
use Tests\TestCase;

class ExtraPlayersTest extends TestCase
{
    use FakesHapi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeHapi(HapiFixtures::booking(['registrations' => [HapiFixtures::registration(7001)]]));
    }

    public function test_players_choose_how_many_to_add_to_their_player_packages(): void
    {
        $this->signInAs(7001)
            ->get('/extraplayers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ExtraPlayers/Select')
                ->where('currencySymbol', '$')
                ->has('packages', 1)
                ->where('packages.0', ['id' => 9001, 'name' => 'Delta Force Direct', 'unitPrice' => 4995, 'booked' => 12, 'max' => 100, 'quantity' => 0]));
    }

    public function test_package_prices_are_loaded_with_the_currency_code_and_symbol(): void
    {
        $this->signInAs(7001)->get('/extraplayers')->assertOk();

        // HAPI returns only the listed columns of a relation once any are listed.
        $columns = collect($this->hapiRequests('GET', '/bookings/1001'))
            ->map(function (array $request) {
                parse_str((string) parse_url($request[0], PHP_URL_QUERY), $query);

                return $query;
            })
            ->first(fn (array $query) => str_contains($query['includes'] ?? '', 'packages.products.taxes'));

        $columns = explode(',', $columns['columns'] ?? '');

        $this->assertContains('site.address.country.currency.code', $columns);
        $this->assertContains('site.address.country.currency.symbol', $columns);
    }

    public function test_at_least_one_player_must_be_added(): void
    {
        $this->signInAs(7001)
            ->post('/extraplayers', ['quantities' => [9001 => 0]])
            ->assertSessionHasErrors(['quantities' => 'Add Players to Go Ahead!']);
    }

    public function test_checkout_shows_the_total_calculated_from_hapi_prices(): void
    {
        $this->signInAs(7001)
            ->post('/extraplayers', ['quantities' => [9001 => 3, 9002 => 5]])
            ->assertRedirect(route('extra-players.checkout'));

        $this->get('/extraplayers/checkout')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ExtraPlayers/Checkout')
                ->has('quote.lines', 1)
                ->where('quote.lines.0.name', 'Delta Force Direct')
                ->where('quote.subtotal', '$149.85')
                ->where('quote.handlingFee', '$8.98')
                ->where('quote.tax', null)
                ->where('quote.total', '$158.83')
                ->where('billing.billing_address_town', 'Sydney')
                ->missing('billing.card_number'));
    }

    public function test_a_successful_payment_notifies_the_booking_office(): void
    {
        $this->signInAs(7001)->post('/extraplayers', ['quantities' => [9001 => 3]]);

        $this->post('/extraplayers/checkout', $this->paymentDetails())->assertRedirect(route('thanks'));

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://worldpay.test')
            && str_contains($request->body(), 'merchantCode="WORLDPAINTBALLAUCCGBP"')
            && str_contains($request->body(), '<amount value="15883" currencyCode="AUD" exponent="2"/>')
            && str_contains($request->body(), '<cardNumber>4111111111111111</cardNumber>')
            && (bool) preg_match('/orderCode="SAM_1001_REGO\d{12}"/', $request->body()));

        $interaction = $this->hapiRequests('POST', '/bookings/1001')[0][1]['interactions'];
        $this->assertSame([7173821], $interaction['tags']);
        $this->assertStringStartsWith('Jordan Smith added package 1 players via online registration system on Delta Force Direct date time ', $interaction['notes_user']);
        $this->assertStringEndsWith(
            'ADD_PAX::[{"id":9001,"name":"Delta Force Direct","qty_booked":3,"pack_total":15882,"pack_total_no_fee":14985}]::|COSTOMER|{"email":"sam@example.test","name":"Sam"}',
            $interaction['notes_user'],
        );

        $email = $this->hapiRequests('POST', '/emails/7170079/send')[0][1];
        $this->assertSame(['sam@example.test', 'email@paintballbookingoffice.com'], array_column($email['to'], 'address'));

        $this->get('/extraplayers/checkout')->assertRedirect(route('extra-players'));
    }

    public function test_a_refused_payment_records_nothing_and_never_echoes_card_details(): void
    {
        $this->worldpayReply = self::worldpayReply('REFUSED');
        $this->signInAs(7001)->post('/extraplayers', ['quantities' => [9001 => 1]]);

        $this->from('/extraplayers/checkout')
            ->post('/extraplayers/checkout', $this->paymentDetails())
            ->assertRedirect('/extraplayers/checkout')
            ->assertSessionHasErrors(['payment' => 'Payment Refused - error in processing payment']);

        $this->assertSame([], $this->hapiRequests('POST', '/bookings/\d+'));
        $this->assertNull(session()->getOldInput('card_number'));
        $this->assertNull(session()->getOldInput('security_code'));
    }

    public function test_card_details_are_checked_before_contacting_worldpay(): void
    {
        $this->signInAs(7001)->post('/extraplayers', ['quantities' => [9001 => 1]]);

        $this->post('/extraplayers/checkout', $this->paymentDetails(['card_number' => '4111111111111112', 'expiry_date' => '1230', 'security_code' => '12']))
            ->assertSessionHasErrors(['card_number', 'expiry_date', 'security_code']);

        $this->assertNull(session()->getOldInput('card_number'));
        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://worldpay.test'));
    }

    public function test_a_simulated_payment_never_updates_the_booking_or_emails_anyone(): void
    {
        $this->app->instance(PaymentGateway::class, new FakePaymentGateway);
        $this->signInAs(7001)->post('/extraplayers', ['quantities' => [9001 => 1]]);

        $this->post('/extraplayers/checkout', $this->paymentDetails())->assertRedirect(route('thanks'));

        $this->assertSame([], $this->hapiRequests('POST', '/bookings/\d+'));
        $this->assertSame([], $this->hapiRequests('POST', '/emails/\d+/send'));
    }

    public function test_the_customer_is_thanked_even_if_hapi_fails_after_payment(): void
    {
        $this->hapiResponses['POST /bookings/\d+'] = Http::response(['message' => 'Server Error'], 500);
        $this->fakeHapi($this->hapiBooking);

        $this->signInAs(7001)->post('/extraplayers', ['quantities' => [9001 => 1]]);

        $this->post('/extraplayers/checkout', $this->paymentDetails())->assertRedirect(route('thanks'));
    }

    public function test_adding_players_is_unavailable_at_restricted_sites(): void
    {
        $this->fakeHapi(HapiFixtures::booking([
            'site' => ['name' => 'Leicester', 'address' => ['country_id' => 1]],
            'registrations' => [HapiFixtures::registration(7001)],
        ]));

        $this->signInAs(7001)->get('/extraplayers')->assertNotFound();
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function paymentDetails(array $overrides = []): array
    {
        return [
            'billing_name_first' => 'Jordan',
            'billing_name_last' => 'Smith',
            'billing_address_street' => '1 Example Street',
            'billing_address_town' => 'Sydney',
            'billing_address_region' => 'NSW',
            'billing_address_country' => 'Australia',
            'billing_address_postcode' => '2000',
            'card_holder_name' => 'Jordan Smith',
            'card_number' => '4111 1111 1111 1111',
            'expiry_date' => '12/30',
            'security_code' => '123',
            ...$overrides,
        ];
    }
}
