<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

use App\Core\Payments\DTO\PaymentRequest;

final class AllinpayRefundService
{
    public function __construct(private readonly AllinpayClient $client) {}

    public function refund(PaymentRequest $request, string $originalTransactionId, string $refundNo, float $amount): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Refund amount must be greater than zero.');
        $params = [
            'reqsn' => $refundNo,
            'oldtrxid' => $originalTransactionId,
            'trxamt' => (int) round($amount * 100),
            'randomstr' => bin2hex(random_bytes(16)),
        ];
        $result = $this->client->post('/apiweb/tranx/refund', $params);
        return [
            'success' => ($result['retcode'] ?? '') === 'SUCCESS',
            'provider_refund_no' => $result['trxid'] ?? null,
            'status' => ($result['retcode'] ?? '') === 'SUCCESS' ? 'processing' : 'failed',
            'raw' => $result,
        ];
    }
}
