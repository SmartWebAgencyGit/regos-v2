<?php

namespace App\Payments;

final class PaymentResult
{
    public const REFUSED_MESSAGE = 'Payment Refused - error in processing payment';

    public const UNCONFIRMED_MESSAGE = 'We could not confirm your payment with the card processor. Please check with your bank before trying again.';

    /**
     * @param  bool  $simulated  no money moved (local fake gateway)
     */
    private function __construct(
        public readonly bool $successful,
        public readonly string $message,
        public readonly ?string $lastEvent = null,
        public readonly ?string $errorCode = null,
        public readonly bool $simulated = false,
    ) {}

    public static function authorised(string $lastEvent, bool $simulated = false): self
    {
        return new self(true, 'Payment authorised', $lastEvent, null, $simulated);
    }

    public static function failed(string $message, ?string $lastEvent = null, ?string $errorCode = null): self
    {
        return new self(false, $message, $lastEvent, $errorCode);
    }
}
