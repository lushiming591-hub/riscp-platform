<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

final class AllinpayQueryService
{
    public function __construct(
        private readonly AllinpayClient $client,
        private readonly AllinpayTransactionMapper $mapper = new AllinpayTransactionMapper(),
    ) {}

    public function query(string $merchantTradeNo, ?string $providerTradeNo = null): array
    {
        $result = $this->client->post('/apiweb/tranx/query', array_filter([
            'reqsn' => $merchantTradeNo,
            'trxid' => $providerTradeNo,
        ], static fn ($v) => $v !== null && $v !== ''));

        return [
            'status' => $this->mapper->trxStatus($result['trxstatus'] ?? null, $result['retcode'] ?? 'FAIL'),
            'provider_trade_no' => $result['trxid'] ?? $providerTradeNo,
            'merchant_trade_no' => $result['reqsn'] ?? $merchantTradeNo,
            'amount' => isset($result['trxamt']) ? ((int) $result['trxamt']) / 100 : null,
            'fee' => isset($result['fee']) ? ((int) $result['fee']) / 100 : null,
            'raw' => $result,
        ];
    }
}
