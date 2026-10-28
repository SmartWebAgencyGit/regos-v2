<?php

namespace App\Portal;

use App\Exceptions\Portal\InvalidUserKey;

/**
 * The access key carried in SMS / email links ("/?k=...").
 *
 * Format (each segment base64 encoded, padding stripped):
 *   base64( base64(booking_id) | crc32(customer_id) | base64(registration_id)? )
 *
 * The CRC proves the holder received the link for that booking's customer.
 * The registration segment is present once a player has registered.
 */
final class UserKey
{
    private function __construct(
        public readonly int $bookingId,
        public readonly string $customerHash,
        public readonly ?int $registrationId,
    ) {}

    /**
     * @throws InvalidUserKey
     */
    public static function fromString(string $key): self
    {
        // "+" arrives as a space when the link was not URL-encoded.
        $key = strtr(trim($key), [' ' => '+', '-' => '+', '_' => '/']);

        $decoded = base64_decode($key, true);
        $parts = $decoded === false ? [] : explode('|', $decoded);

        if (count($parts) < 2) {
            throw new InvalidUserKey('Error decoding link. Please check the URL and try again');
        }

        $bookingId = base64_decode($parts[0], true);
        $customerHash = $parts[1];
        $registrationId = isset($parts[2]) && $parts[2] !== '' ? base64_decode($parts[2], true) : null;

        if (! self::isId($bookingId) || strlen($customerHash) !== 8 || ($registrationId !== null && ! self::isId($registrationId))) {
            throw new InvalidUserKey('Error decoding link. Please check the URL and try again');
        }

        return new self((int) $bookingId, $customerHash, $registrationId === null ? null : (int) $registrationId);
    }

    /**
     * Build the key HAPI issues for a booking (mirrors the legacy keygen).
     */
    public static function forBooking(int $bookingId, int $customerId, ?int $registrationId = null): self
    {
        return new self($bookingId, hash('crc32b', (string) $customerId), $registrationId);
    }

    public function withRegistration(?int $registrationId): self
    {
        return new self($this->bookingId, $this->customerHash, $registrationId);
    }

    /**
     * Both CRC variants are accepted: "crc32" was the original choice and
     * "crc32b" is what the check-in app generates.
     */
    public function belongsToCustomer(int|string $customerId): bool
    {
        return hash_equals(hash('crc32b', (string) $customerId), $this->customerHash)
            || hash_equals(hash('crc32', (string) $customerId), $this->customerHash);
    }

    public function hasRegistration(): bool
    {
        return $this->registrationId !== null;
    }

    public function toString(): string
    {
        $parts = [self::encode((string) $this->bookingId), $this->customerHash];

        if ($this->registrationId !== null) {
            $parts[] = self::encode((string) $this->registrationId);
        }

        return self::encode(implode('|', $parts));
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private static function encode(string $value): string
    {
        return rtrim(base64_encode($value), '=');
    }

    private static function isId(string|false|null $value): bool
    {
        return is_string($value) && $value !== '' && ctype_digit($value) && strlen($value) <= 18;
    }
}
