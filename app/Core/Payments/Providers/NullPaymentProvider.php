<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers;

use App\Core\Payments\Contracts\PaymentProviderInterface;
use App\Core\Payments\DTO\PaymentRequest;
use App\Core\Payments\DTO\PaymentResponse;

final class NullPaymentProvider implements PaymentProviderInterface
{
    public function code(): string { return 'null'; }
    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        return new PaymentResponse(false, null, 'Provider adapter not configured');
    }
    public function queryPayment(string $providerTradeNo): array { return ['status' => 'unknown']; }
    public function refund(string $providerTradeNo, string $refundNo, float $amount): array { return ['success' => false, 'message' => 'Provider adapter not configured']; }
    public function verifyCallback(array $payload, array $headers = []): array { return ['valid' => false, 'message' => 'Provider adapter not configured']; }
}
