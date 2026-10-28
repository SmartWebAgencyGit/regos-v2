<?php

namespace App\Payments;

interface PaymentGateway
{
    /**
     * Authorise a card payment. Never throws for a declined or failed
     * payment; inspect the result instead.
     */
    public function charge(PaymentRequest $request): PaymentResult;
}
