<?php

namespace App\Payments;

/**
 * Local-development gateway: approves every card except one ending 0002.
 */
class FakePaymentGateway implements PaymentGateway
{
    public const DECLINED_CARD = '4000000000000002';

    public function charge(PaymentRequest $request): PaymentResult
    {
        if ($request->cardNumber === self::DECLINED_CARD) {
            return PaymentResult::failed(PaymentResult::REFUSED_MESSAGE, 'REFUSED');
        }

        return PaymentResult::authorised('AUTHORISED', simulated: true);
    }
}
