<?php

namespace Tests\Unit;

use App\Portal\AddPlayers\AddPlayersPricing;
use App\Portal\Booking;
use Tests\Support\HapiFixtures;
use Tests\TestCase;

class AddPlayersPricingTest extends TestCase
{
    public function test_it_charges_the_country_handling_fee_on_the_package_price(): void
    {
        $quote = $this->pricing()->quote(new Booking(HapiFixtures::booking()), [9001 => 3]);

        $this->assertSame(14985, $quote->subtotal());
        $this->assertSame(898, $quote->handlingFee()); // 5.99% of $149.85 = $8.976
        $this->assertSame(0, $quote->tax());
        $this->assertSame(15883, $quote->total());
        $this->assertSame(3, $quote->playerCount());
        $this->assertSame('$158.83', $quote->format($quote->total()));
    }

    public function test_no_handling_fee_when_no_selected_package_carries_one(): void
    {
        $booking = new Booking(HapiFixtures::booking(['packages' => [
            ['id' => 9001, 'unit_handling_fee' => 0],
        ]]));

        $quote = $this->pricing()->quote($booking, [9001 => 2]);

        $this->assertSame(0, $quote->handlingFee());
        $this->assertSame(9990, $quote->total());
    }

    public function test_the_fee_applies_to_every_line_when_any_selected_package_has_one(): void
    {
        $booking = new Booking(HapiFixtures::booking(['packages' => [
            1 => ['id' => 9003, 'package_id' => 79, 'package_type_id' => 1, 'name' => 'Voucher', 'unit_price' => 1000, 'unit_handling_fee' => 0, 'qty_booked' => 0],
        ]]));

        $quote = $this->pricing()->quote($booking, [9001 => 1, 9003 => 1]);

        $this->assertSame([299, 60], array_column($quote->lines, 'handling_fee'));
    }

    public function test_only_player_packages_on_the_booking_can_be_bought_and_limits_apply(): void
    {
        $booking = new Booking(HapiFixtures::booking(['packages' => [
            ['package_id' => 7175277], // Wowcher, limited to 10
        ]]));

        $quote = $this->pricing()->quote($booking, [9001 => 25, 9002 => 5, 123456 => 4]);

        $this->assertCount(1, $quote->lines);
        $this->assertSame(10, $quote->lines[0]['quantity']);
    }

    public function test_canadian_sales_tax_is_added_to_packages_that_are_not_tax_inclusive(): void
    {
        $booking = new Booking(HapiFixtures::booking([
            'site' => ['address' => ['country_id' => 5]],
            'packages' => [['is_inclusive' => 0, 'unit_handling_fee' => 0]],
        ]));

        $quote = $this->pricing()->quote($booking, [9001 => 2]);

        $this->assertSame(9990, $quote->subtotal());
        $this->assertSame(1299, $quote->tax()); // 13% of $99.90 = $12.987
        $this->assertSame(11289, $quote->total());
    }

    public function test_currency_symbols_stored_as_html_entities_are_decoded(): void
    {
        $booking = new Booking(HapiFixtures::booking(['site' => ['address' => ['country' => ['currency' => ['code' => 'GBP', 'symbol' => '&#163;']]]]]));

        $this->assertSame('£49.95', $this->pricing()->quote($booking, [9001 => 1])->format(4995));
    }

    public function test_an_empty_selection_gives_an_empty_quote(): void
    {
        $this->assertTrue($this->pricing()->quote(new Booking(HapiFixtures::booking()), [9001 => 0])->isEmpty());
    }

    private function pricing(): AddPlayersPricing
    {
        return new AddPlayersPricing(config('regos'));
    }
}
