<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

use App\Core\Payments\DTO\PaymentRequest;

final class AllinpayNativePayService
{
    public function __construct(private readonly AllinpayClient $client, private readonly AllinpayTransactionMapper $mapper = new AllinpayTransactionMapper()) {}

    public function create(PaymentRequest $request): array
    {
        $m = $request->metadata;
        $result = $this->client->post('/apiweb/unitorder/nativepay', array_filter([
            'cusid' => $m['cusid'] ?? null,
            'orgid' => $m['orgid'] ?? null,
            'trxamt' => (int) round($request->amount * 100),
            'reqsn' => $request->merchantTradeNo,
            'paytype' => $this->mapper->payType($request->paymentMethod),
            'body' => $m['body'] ?? null,
            'notify_url' => $m['notify_url'] ?? null,
            'randomstr' => $m['randomstr'] ?? null,
        ], static fn ($v) => $v !== null && $v !== ''));

        if (($result['retcode'] ?? 'FAIL') !== 'SUCCESS') {
            throw new \RuntimeException($result['retmsg'] ?? 'Allinpay native pay request failed.');
        }
        return [
            'provider_trade_no' => $result['trxid'] ?? null,
            'payinfo' => $result['payinfo'] ?? null,
            'trxstatus' => $result['trxstatus'] ?? null,
            'raw' => $result,
        ];
    }
}
