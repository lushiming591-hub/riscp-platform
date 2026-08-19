<?php

declare(strict_types=1);

namespace App\Core\Payments;

interface PaymentGateway
{
    /**
     * Create a payment intent/order at an external licensed payment provider.
     */
    public function createPayment(PaymentRequest $request): PaymentResult;

    /**
     * Query the provider for the authoritative payment status.
     */
    public function query(string $providerPaymentId): PaymentResult;

    /**
     * Request a refund through the provider.
     */
    public function refund(string $providerPaymentId, int $amountCents, string $reason): PaymentResult;
}
