<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

use App\Core\Payments\DTO\PaymentRequest;

final class AllinpayScanQrPayService
{
    public function __construct(private readonly AllinpayClient $client, private readonly AllinpayTransactionMapper $mapper = new AllinpayTransactionMapper()) {}

    public function pay(PaymentRequest $request): array
    {
        $m = $request->metadata;
        $result = $this->client->post('/apiweb/unitorder/scanqrpay', array_filter([
            'cusid' => $m['cusid'] ?? null,
            'orgid' => $m['orgid'] ?? null,
            'trxamt' => (int) round($request->amount * 100),
            'reqsn' => $request->merchantTradeNo,
            'paytype' => $this->mapper->payType($request->paymentMethod),
            'body' => $m['body'] ?? null,
            'acct' => $m['acct'] ?? null,
            'notify_url' => $m['notify_url'] ?? null,
            'randomstr' => $m['randomstr'] ?? null,
        ], static fn ($v) => $v !== null && $v !== ''));

        return [
            'status' => $this->mapper->trxStatus($result['trxstatus'] ?? null, $result['retcode'] ?? 'FAIL'),
            'provider_trade_no' => $result['trxid'] ?? null,
            'raw' => $result,
        ];
    }
}
