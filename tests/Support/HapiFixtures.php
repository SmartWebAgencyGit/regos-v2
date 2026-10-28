<?php

namespace Tests\Support;

use App\Portal\UserKey;
use Carbon\CarbonImmutable;

/**
 * HAPI payloads shaped like the real API's responses.
 */
final class HapiFixtures
{
    public const BOOKING_ID = 1001;

    public const CUSTOMER_ID = 501;

    /**
     * An Australian booking ten days out at Appin, organised by Jordan Smith.
     *
     * @param  array<string, mixed>  $overrides  merged recursively
     * @return array<string, mixed>
     */
    public static function booking(array $overrides = []): array
    {
        $booking = [
            'id' => self::BOOKING_ID,
            'customer_id' => self::CUSTOMER_ID,
            'status_id' => 1,
            'pax_booked' => 12,
            'group' => 'Bravo Company',
            'gametype_id' => 1,
            'game_date' => CarbonImmutable::now('Australia/Sydney')->addDays(10)->setTime(9, 30)->format('Y-m-d H:i:s'),
            'site_id' => 3,
            'site' => [
                'id' => 3,
                'name' => 'Appin',
                'centre' => ['id' => 1, 'site_id' => 3, 'is_partner' => 0],
                'address' => [
                    'timezone' => 'Australia/Sydney',
                    'country_id' => 3,
                    'country' => ['name' => 'Australia', 'currency' => ['id' => 2, 'code' => 'AUD', 'symbol' => '$']],
                ],
                'site_gametypes' => [['gametype_id' => 1, 'min_age' => 12]],
            ],
            'customer' => [
                'id' => self::CUSTOMER_ID,
                'name_first' => 'Jordan',
                'name_last' => 'Smith',
                'emails' => [['value' => 'jordan@example.test']],
                'phones' => [['value' => '0400 000 000']],
                'address' => ['street' => '1 Example Street', 'locality' => '', 'town' => 'Sydney', 'region' => 'NSW', 'postcode' => '2000', 'country' => ['name' => 'Australia']],
            ],
            'registrations' => [],
            'packages' => [
                ['id' => 9001, 'package_id' => 77, 'package_type_id' => 1, 'name' => 'Delta Force Direct', 'unit_price' => 4995, 'unit_handling_fee' => 299, 'qty_booked' => 12, 'is_inclusive' => 1],
                ['id' => 9002, 'package_id' => 78, 'package_type_id' => 2, 'name' => '500 Paintballs', 'unit_price' => 3500, 'unit_handling_fee' => 0, 'qty_booked' => 4, 'is_inclusive' => 1],
            ],
        ];

        return array_replace_recursive($booking, $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function registration(int $id = 7001, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => $id,
            'booking_id' => self::BOOKING_ID,
            'confirmed' => true,
            'accept_terms' => true,
            'safety_brief' => true,
            'customer' => [
                'name_first' => 'Sam',
                'name_last' => 'Lee',
                'emails' => [['value' => 'sam@example.test']],
                'phones' => [['value' => '0400 000 001']],
            ],
        ], $overrides);
    }

    public static function key(?int $registrationId = null): string
    {
        return UserKey::forBooking(self::BOOKING_ID, self::CUSTOMER_ID, $registrationId)->toString();
    }
}
