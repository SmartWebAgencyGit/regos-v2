<?php

namespace App\Payments;

/**
 * A card payment to authorise. Amounts are in minor units (cents / pence).
 * Card fields are held only for the duration of the request.
 */
final class PaymentRequest
{
    public function __construct(
        public readonly string $merchantCode,
        public readonly string $orderCode,
        public readonly string $description,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $cardNumber,
        public readonly string $cardHolderName,
        public readonly string $expiryMonth,
        public readonly string $expiryYear,
        public readonly string $securityCode,
        public readonly string $address1,
        public readonly string $address2,
        public readonly string $city,
        public readonly string $postcode,
        public readonly string $countryCode,
        // Null to use the gateway's shared password.
        public readonly ?string $merchantPassword = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'merchantCode' => $this->merchantCode,
            'orderCode' => $this->orderCode,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'cardNumber' => '**** '.substr($this->cardNumber, -4),
        ];
    }
}
