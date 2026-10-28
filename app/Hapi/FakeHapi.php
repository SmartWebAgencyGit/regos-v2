<?php

namespace App\Hapi;

use App\Exceptions\Hapi\HapiException;
use App\Portal\UserKey;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * In-memory stand-in for HAPI used for local development (HAPI_FAKE=true).
 * Serves a few demo bookings and keeps new registrations in the cache so
 * the full register / verify / boarding pass flow can be clicked through.
 *
 * Print sign-in links with: php artisan regos:demo-links
 */
class FakeHapi implements Hapi
{
    private const STATE_KEY = 'regos:fake-hapi:state';

    public function __construct(private readonly Cache $cache) {}

    public function get(string $uri): array
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);

        if (preg_match('#^/bookings/(\d+)$#', $path, $match)) {
            return ['data' => $this->booking((int) $match[1])];
        }

        if (preg_match('#^/sites/(\d+)$#', $path, $match)) {
            return ['data' => ['id' => (int) $match[1], 'address' => ['timezone' => 'Europe/London']]];
        }

        if ($path === '/customers') {
            return ['data' => array_map(fn (array $booking) => [
                'id' => $booking['customer_id'],
                'name_first' => $booking['customer']['name_first'],
                'name_last' => $booking['customer']['name_last'],
                'bookings' => [[
                    'id' => $booking['id'],
                    'customer_id' => $booking['customer_id'],
                    'registration_key' => self::organiserKey($booking),
                    'pax_booked' => $booking['pax_booked'],
                ]],
            ], array_map(fn (int $id) => $this->booking($id), array_keys(self::bookings())))];
        }

        throw new HapiException('Not found', 404);
    }

    public function post(string $uri, array $payload): array
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $state = $this->state();

        if ($path === '/registrations') {
            $id = $state['next_id']++;
            $state['registrations'][$id] = [
                'id' => $id,
                'booking_id' => (int) $payload['booking_id'],
                'confirmed' => false,
                'accept_terms' => (bool) ($payload['accept_terms'] ?? false),
                'safety_brief' => (bool) ($payload['safety_brief'] ?? false),
                'customer' => self::customerFromPayload($payload),
            ];
            $this->cache->forever(self::STATE_KEY, $state);

            return ['data' => ['id' => $id]];
        }

        if (preg_match('#^/registrations/(\d+)$#', $path, $match) && isset($state['registrations'][(int) $match[1]])) {
            $registration = &$state['registrations'][(int) $match[1]];

            foreach (['confirmed', 'accept_terms', 'safety_brief'] as $flag) {
                if (array_key_exists($flag, $payload)) {
                    $registration[$flag] = (bool) $payload[$flag];
                }
            }

            if (isset($payload['customer'])) {
                $registration['customer'] = self::customerFromPayload($payload);
            }

            unset($registration);
            $this->cache->forever(self::STATE_KEY, $state);

            return ['data' => ['id' => (int) $match[1]]];
        }

        if (preg_match('#^/bookings/\d+$#', $path) || preg_match('#^/emails/\d+/send$#', $path)) {
            return ['data' => []];
        }

        throw new HapiException('Not found', 404);
    }

    public function raw(string $uri): string
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);

        if (str_ends_with($path, '/short_link')) {
            return 'https://example.test/r/demo';
        }

        if (str_ends_with($path, '/qr') || str_ends_with($path, '/qr_captain')) {
            return self::qrPlaceholder($path);
        }

        throw new HapiException('Not found', 404);
    }

    /**
     * Demo sign-in keys for the organiser of each demo booking.
     *
     * @return array<int, string> booking id => key
     */
    public static function demoKeys(): array
    {
        return array_map(fn (array $booking) => self::organiserKey($booking), self::bookings());
    }

    /**
     * @return array<string, mixed>
     */
    private function booking(int $id): array
    {
        $booking = self::bookings()[$id] ?? throw new HapiException('Not found', 404);

        foreach ($this->state()['registrations'] as $registration) {
            if ($registration['booking_id'] === $id) {
                $booking['registrations'][] = $registration;
            }
        }

        return $booking;
    }

    /**
     * @return array{next_id: int, registrations: array<int, array<string, mixed>>}
     */
    private function state(): array
    {
        return $this->cache->get(self::STATE_KEY, [
            'next_id' => 7100,
            'registrations' => [
                7001 => [
                    'id' => 7001,
                    'booking_id' => 1001,
                    'confirmed' => true,
                    'accept_terms' => true,
                    'safety_brief' => true,
                    'customer' => ['name_first' => 'Sam', 'name_last' => 'Lee', 'emails' => [['value' => 'sam@example.test']], 'phones' => [['value' => '0400 000 001']]],
                ],
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function bookings(): array
    {
        return [
            1001 => self::demoBooking(1001, 501, 'Jordan', 'Smith', 'Appin', 3, 'Australia/Sydney', ['code' => 'AUD', 'symbol' => '$'], [
                ['id' => 9001, 'package_id' => 77, 'package_type_id' => 1, 'name' => 'Delta Force Direct', 'unit_price' => 4995, 'unit_handling_fee' => 299, 'qty_booked' => 12, 'is_inclusive' => 1],
                ['id' => 9002, 'package_id' => 78, 'package_type_id' => 2, 'name' => '500 Paintballs', 'unit_price' => 3500, 'unit_handling_fee' => 0, 'qty_booked' => 4, 'is_inclusive' => 1],
            ]),
            1002 => self::demoBooking(1002, 502, 'Alex', 'Taylor', 'Liverpool', 1, 'Europe/London', ['code' => 'GBP', 'symbol' => '£'], [
                ['id' => 9101, 'package_id' => 7175277, 'package_type_id' => 1, 'name' => 'Wowcher 100+50', 'unit_price' => 1495, 'unit_handling_fee' => 0, 'qty_booked' => 8, 'is_inclusive' => 1],
                ['id' => 9102, 'package_id' => 88, 'package_type_id' => 1, 'name' => 'Ultimate Package', 'unit_price' => 2995, 'unit_handling_fee' => 210, 'qty_booked' => 0, 'is_inclusive' => 1],
            ]),
        ];
    }

    /**
     * @param  array{code: string, symbol: string}  $currency
     * @param  list<array<string, mixed>>  $packages
     * @return array<string, mixed>
     */
    private static function demoBooking(int $id, int $customerId, string $first, string $last, string $site, int $countryId, string $timezone, array $currency, array $packages): array
    {
        return [
            'id' => $id,
            'customer_id' => $customerId,
            'status_id' => 1,
            'pax_booked' => 12,
            'group' => 'Bravo Company',
            'gametype_id' => 1,
            'game_date' => CarbonImmutable::now($timezone)->addDays(10)->setTime(9, 30)->format('Y-m-d H:i:s'),
            'site_id' => $id,
            'site' => [
                'id' => $id,
                'name' => $site,
                'centre' => ['is_partner' => 0],
                'address' => ['timezone' => $timezone, 'country_id' => $countryId, 'country' => ['name' => '', 'currency' => $currency]],
                'site_gametypes' => [['gametype_id' => 1, 'min_age' => 12]],
            ],
            'customer' => [
                'id' => $customerId,
                'name_first' => $first,
                'name_last' => $last,
                'emails' => [['value' => strtolower($first).'@example.test']],
                'phones' => [['value' => '0400 000 000']],
                'address' => $countryId === 1
                    ? ['street' => '1 Example Street', 'locality' => '', 'town' => 'Liverpool', 'region' => 'Merseyside', 'postcode' => 'L1 1AA', 'country' => ['name' => 'United Kingdom']]
                    : ['street' => '1 Example Street', 'locality' => '', 'town' => 'Sydney', 'region' => 'NSW', 'postcode' => '2000', 'country' => ['name' => 'Australia']],
            ],
            'registrations' => [],
            'packages' => $packages,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function customerFromPayload(array $payload): array
    {
        return [
            'name_first' => $payload['customer']['name_first'] ?? '',
            'name_last' => $payload['customer']['name_last'] ?? '',
            'emails' => [['value' => $payload['customer']['emails'][0]['value'] ?? '']],
            'phones' => [['value' => $payload['customer']['phones'][0]['value'] ?? '']],
        ];
    }

    /**
     * @param  array<string, mixed>  $booking
     */
    private static function organiserKey(array $booking): string
    {
        return UserKey::forBooking($booking['id'], $booking['customer_id'])->toString();
    }

    private static function qrPlaceholder(string $seed): string
    {
        $size = 21;
        $bits = '';

        while (strlen($bits) < $size * $size) {
            $bits .= implode('', array_map(fn (string $char) => str_pad(base_convert($char, 16, 2), 4, '0', STR_PAD_LEFT), str_split(md5($seed.strlen($bits)))));
        }

        $cells = '';

        for ($i = 0; $i < $size * $size; $i++) {
            if ($bits[$i] === '1') {
                $cells .= sprintf('<rect x="%d" y="%d" width="1" height="1"/>', $i % $size, intdiv($i, $size));
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-2 -2 25 25"><rect x="-2" y="-2" width="25" height="25" fill="#fff"/><g fill="#000">'.$cells.'</g></svg>';
    }
}
