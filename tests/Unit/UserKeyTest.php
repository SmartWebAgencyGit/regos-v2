<?php

namespace Tests\Unit;

use App\Exceptions\Portal\InvalidUserKey;
use App\Portal\UserKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserKeyTest extends TestCase
{
    public function test_it_decodes_a_key_issued_by_the_legacy_keygen(): void
    {
        // keygen.php: strip "=" from base64(booking id), append "|" + crc32(customer id), base64 again.
        $key = rtrim(base64_encode(rtrim(base64_encode('12871211'), '=').'|'.hash('crc32', '20525659')), '=');

        $userKey = UserKey::fromString($key);

        $this->assertSame(12871211, $userKey->bookingId);
        $this->assertNull($userKey->registrationId);
        $this->assertTrue($userKey->belongsToCustomer(20525659));
        $this->assertFalse($userKey->belongsToCustomer(20525660));
    }

    public function test_it_decodes_a_key_with_a_registration_built_by_the_old_browser_code(): void
    {
        // The Mithril app re-encoded keys with btoa(), keeping "=" padding.
        $key = base64_encode(rtrim(base64_encode('1001'), '=').'|'.hash('crc32b', '501').'|'.base64_encode('7001'));

        $userKey = UserKey::fromString($key);

        $this->assertSame(1001, $userKey->bookingId);
        $this->assertSame(7001, $userKey->registrationId);
        $this->assertTrue($userKey->belongsToCustomer(501));
    }

    public function test_a_plus_sign_that_arrived_as_a_space_is_restored(): void
    {
        $key = UserKey::forBooking(1001, 501, 7001)->toString();
        $withSpaces = str_replace('+', ' ', $key);

        $this->assertSame(7001, UserKey::fromString($withSpaces)->registrationId);
    }

    public function test_it_round_trips_and_adds_or_removes_a_registration(): void
    {
        $key = UserKey::forBooking(1001, 501);

        $withRegistration = UserKey::fromString($key->withRegistration(7200)->toString());

        $this->assertSame(7200, $withRegistration->registrationId);
        $this->assertTrue($withRegistration->belongsToCustomer(501));
        $this->assertSame($key->toString(), $withRegistration->withRegistration(null)->toString());
        $this->assertStringNotContainsString('=', $withRegistration->toString());
    }

    #[DataProvider('invalidKeys')]
    public function test_it_rejects_malformed_keys(string $key): void
    {
        $this->expectException(InvalidUserKey::class);

        UserKey::fromString($key);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidKeys(): array
    {
        return [
            'not base64' => ['%%%'],
            'single segment' => [base64_encode('MTAwMQ')],
            'non-numeric booking' => [base64_encode(base64_encode('abc').'|'.hash('crc32b', '1'))],
            'short checksum' => [base64_encode(base64_encode('1001').'|abc')],
            'path in registration id' => [base64_encode(base64_encode('1001').'|'.hash('crc32b', '1').'|'.base64_encode('7/../../bookings'))],
        ];
    }
}
