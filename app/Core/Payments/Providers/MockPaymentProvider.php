<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers;

use App\Core\Payments\Contracts\PaymentProviderInterface;
use App\Core\Payments\DTO\PaymentRequest;
use App\Core\Payments\DTO\PaymentResponse;

final class MockPaymentProvider implements PaymentProviderInterface
{
    public function code(): string { return 'mock'; }

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        return new PaymentResponse(true, 'MOCK-' . $request->merchantTradeNo, 'mock://pay/' . $request->merchantTradeNo);
    }

    public function queryPayment(string $providerTradeNo): array
    {
        return ['status' => 'paid', 'provider_trade_no' => $providerTradeNo];
    }

    public function refund(string $providerTradeNo, string $refundNo, float $amount): array
    {
        return ['success' => true, 'provider_refund_no' => 'MOCK-R-' . $refundNo, 'amount' => $amount];
    }

    public function verifyCallback(array $payload, array $headers = []): array
    {
        return ['valid' => true, 'provider_trade_no' => $payload['provider_trade_no'] ?? null];
    }
}
