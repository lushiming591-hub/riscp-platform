<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

final class AllinpayPendingPaymentResolver
{
    public function __construct(private readonly AllinpayQueryService $query) {}

    public function resolve(string $merchantTradeNo, ?string $providerTradeNo = null, int $maxAttempts = 3, int $intervalSeconds = 2): array
    {
        $maxAttempts = max(1, $maxAttempts);
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $result = $this->query->query($merchantTradeNo, $providerTradeNo);
            if (in_array($result['status'], ['paid', 'failed'], true)) {
                $result['attempts'] = $attempt;
                return $result;
            }
            if ($attempt < $maxAttempts) sleep(max(0, $intervalSeconds));
            $providerTradeNo = $result['provider_trade_no'] ?? $providerTradeNo;
        }

        return [
            'status' => 'processing',
            'provider_trade_no' => $providerTradeNo,
            'merchant_trade_no' => $merchantTradeNo,
            'attempts' => $maxAttempts,
        ];
    }
}
