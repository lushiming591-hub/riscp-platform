<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

use App\Core\Payments\Contracts\PaymentProviderInterface;
use App\Core\Payments\DTO\PaymentRequest;
use App\Core\Payments\DTO\PaymentResponse;

final class AllinpayPaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly AllinpayClient $client,
        private readonly AllinpayTransactionMapper $mapper = new AllinpayTransactionMapper(),
    ) {}

    public function code(): string
    {
        return 'allinpay';
    }

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
            'remark' => $m['remark'] ?? null,
            'validtime' => $m['validtime'] ?? null,
            'expiretime' => $m['expiretime'] ?? null,
            'notify_url' => $m['notify_url'] ?? null,
            'sub_appid' => $m['sub_appid'] ?? null,
            'acct' => $m['acct'] ?? null,
            'subbranch' => $m['subbranch'] ?? null,
            'chnlstoreid' => $m['chnlstoreid'] ?? null,
            'extendparams' => $m['extendparams'] ?? null,
            'front_url' => $m['front_url'] ?? null,
            'limit_pay' => $m['limit_pay'] ?? null,
            'operatorid' => $m['operatorid'] ?? null,
        ], static fn ($v) => $v !== null && $v !== ''));

        $status = $this->mapper->trxStatus($result['trxstatus'] ?? null, $result['retcode'] ?? 'FAIL');

        if (($result['retcode'] ?? 'FAIL') !== 'SUCCESS') {
            return new PaymentResponse(
                'failed',
                null,
                null,
                ['retcode' => $result['retcode'] ?? null, 'retmsg' => $result['retmsg'] ?? null, 'raw' => $result],
            );
        }

        return new PaymentResponse(
            $status,
            $result['trxid'] ?? null,
            $result['payinfo'] ?? null,
            $result,
        );
    }

    public function queryPayment(string $providerTradeNo): array
    {
        return $this->client->post('/apiweb/tranx/query', [
            'trxid' => $providerTradeNo,
        ]);
    }

    public function refund(string $providerTradeNo, string $refundNo, float $amount): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Refund amount must be greater than zero.');
        }

        return $this->client->post('/apiweb/tranx/refund', [
            'oldtrxid' => $providerTradeNo,
            'reqsn' => $refundNo,
            'trxamt' => (int) round($amount * 100),
        ]);
    }

    public function verifyCallback(array $payload, array $headers = []): array
    {
        $handler = new AllinpayCallbackHandler($this->client->signer());
        return $handler->handle($payload);
    }
}
