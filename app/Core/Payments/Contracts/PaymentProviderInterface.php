<?php

declare(strict_types=1);

namespace App\Core\Payments\Contracts;

use App\Core\Payments\DTO\PaymentRequest;
use App\Core\Payments\DTO\PaymentResponse;

interface PaymentProviderInterface
{
    public function code(): string;
    public function createPayment(PaymentRequest $request): PaymentResponse;
    public function queryPayment(string $providerTradeNo): array;
    public function refund(string $providerTradeNo, string $refundNo, float $amount): array;
    public function verifyCallback(array $payload, array $headers = []): array;
}
