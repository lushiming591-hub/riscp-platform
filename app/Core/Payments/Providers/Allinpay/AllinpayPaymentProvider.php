<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

use App\Core\Payments\Contracts\PaymentProviderInterface;
use App\Core\Payments\DTO\PaymentRequest;
use App\Core\Payments\DTO\PaymentResponse;

final class AllinpayPaymentProvider implements PaymentProviderInterface
{
    public function __construct(private readonly AllinpayClient $client, private readonly AllinpayTransactionMapper $mapper = new AllinpayTransactionMapper()) {}

    public function code(): string { return 'allinpay'; }

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $m = $request->metadata;
        $result = $this->client->post('/apiweb/unitorder/pay', array_filter([
            'cusid' => $m['cusid'] ?? null,
            'orgid' => $m['orgid'] ?? null,
            'trxamt' => (int) round($request->amount * 100),
            'reqsn' => $request->merchantTradeNo,
            'paytype' => $this->mapper->payType($request->paymentMethod),
            'body' => $m['body'] ?? null,
            'notify_url' => $m['notify_url'] ?? null,
            'sub_appid' => $m['sub_appid'] ?? null,
            'acct' => $m['acct'] ?? null,
            'subbranch' => $m['subbranch'] ?? null,
            'operatorid' => $m['operatorid'] ?? null,
        ], static fn ($v) => $v !== null && $v !== ''));

        $status = $this->mapper->trxStatus($result['trxstatus'] ?? null, $result['retcode'] ?? 'FAIL');
        if (($result['retcode'] ?? 'FAIL') !== 'SUCCESS') {
            return new PaymentResponse(false, null, null, $result['retmsg'] ?? 'Allinpay request failed');
        }
        return new PaymentResponse($status !== 'failed', $result['trxid'] ?? null, $result['payinfo'] ?? null, $result['errmsg'] ?? null);
    }

    public function queryPayment(string $providerTradeNo): array
    {
        return $this->client->post('/apiweb/tranx/query', ['trxid' => $providerTradeNo]);
    }

    public function refund(string $providerTradeNo, string $refundNo, float $amount): array
    {
        return $this->client->post('/apiweb/tranx/refund', [
            'oldtrxid' => $providerTradeNo,
            'reqsn' => $refundNo,
            'trxamt' => (int) round($amount * 100),
        ]);
    }

    public function verifyCallback(array $payload, array $headers = []): array
    {
        // Signature verification must be implemented with the merchant public key
        // and Allinpay's documented canonical-signing rules before production use.
        return ['valid' => false, 'message' => 'Allinpay signature verification is not configured yet.'];
    }
}
