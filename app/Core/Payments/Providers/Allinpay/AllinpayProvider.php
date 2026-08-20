<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

use App\Core\Payments\Contracts\PaymentProviderContract;
use App\Core\Payments\DTO\PaymentResponse;
use App\Core\Payments\DTO\QueryResponse;
use App\Core\Payments\DTO\RefundResponse;
use Illuminate\Support\Str;
use RuntimeException;

final class AllinpayProvider implements PaymentProviderContract
{
    private const PAY = '/apiweb/unitorder/pay';
    private const NATIVE_PAY = '/apiweb/unitorder/nativepay';
    private const MICRO_PAY = '/apiweb/unitorder/scanqrpay';
    private const QUERY = '/apiweb/tranx/query';
    private const QUERY_CONFIRM = '/apiweb/tranx/queryconfirm';
    private const CLOSE_NATIVE = '/apiweb/unitorder/closenative';
    private const REFUND = '/apiweb/tranx/refund';

    public function __construct(private readonly AllinpayClient $client) {}

    public static function fromConfig(): self
    {
        return new self(AllinpayClient::fromConfig());
    }

    public function pay(array $request): array
    {
        return $this->payment(self::PAY, $request);
    }

    public function scanPay(array $request): array
    {
        return $this->payment(self::NATIVE_PAY, $request);
    }

    public function microPay(array $request): array
    {
        return $this->payment(self::MICRO_PAY, $request);
    }

    public function query(array $request): array
    {
        return $this->queryEndpoint(self::QUERY, $request);
    }

    public function confirmQuery(array $request): array
    {
        return $this->queryEndpoint(self::QUERY_CONFIRM, $request);
    }

    public function closeNative(array $request): array
    {
        return $this->client->post(self::CLOSE_NATIVE, $this->providerPayload($request));
    }

    public function refund(array $request): array
    {
        $request = $this->providerPayload($request);
        $request['reqsn'] ??= 'RF' . Str::upper(Str::random(24));
        $request['trxamt'] ??= $request['amount_fen'] ?? null;
        $request['oldtrxid'] ??= $request['provider_trade_no'] ?? null;
        $request['oldreqsn'] ??= $request['merchant_trade_no'] ?? null;

        return $this->normalizeRefund($this->client->post(self::REFUND, $request));
    }

    public function verifyCallback(array $payload, array $headers = []): bool
    {
        return $this->client->signer()->verify(
            $payload,
            (string) ($payload['sign'] ?? '')
        );
    }

    public function parseCallback(array $payload, array $headers = []): array
    {
        if (!$this->verifyCallback($payload, $headers)) {
            throw new RuntimeException('Invalid Allinpay callback signature.');
        }

        $trxStatus = (string) ($payload['trxstatus'] ?? '');

        return [
            'merchant_trade_no' => $payload['reqsn'] ?? $payload['unireqsn'] ?? null,
            'provider_trade_no' => $payload['trxid'] ?? null,
            'channel_trade_no' => $payload['chnltrxid'] ?? null,
            'ret_code' => $payload['retcode'] ?? null,
            'ret_msg' => $payload['retmsg'] ?? $payload['errmsg'] ?? null,
            'trx_status' => $trxStatus ?: null,
            'status' => $this->status($trxStatus),
            'raw' => $payload,
        ];
    }

    public function reconcile(array $request): array
    {
        throw new RuntimeException('Allinpay reconciliation adapter is not implemented yet.');
    }

    private function payment(string $endpoint, array $request): array
    {
        $payload = $this->providerPayload($request);
        $response = $this->client->post($endpoint, $payload);
        $trxStatus = (string) ($response['trxstatus'] ?? '');

        return (new PaymentResponse(
            status: $this->status($trxStatus),
            merchantTradeNo: $response['reqsn'] ?? $response['unireqsn'] ?? $payload['reqsn'] ?? null,
            providerTradeNo: $response['trxid'] ?? null,
            channelTradeNo: $response['chnltrxid'] ?? null,
            payInfo: $response['payinfo'] ?? null,
            retCode: $response['retcode'] ?? null,
            retMsg: $response['retmsg'] ?? $response['errmsg'] ?? null,
            trxStatus: $trxStatus ?: null,
            raw: $response,
        ))->toArray();
    }

    private function queryEndpoint(string $endpoint, array $request): array
    {
        $payload = $this->providerPayload($request);
        $response = $this->client->post($endpoint, $payload);
        $trxStatus = (string) ($response['trxstatus'] ?? '');

        return (new QueryResponse(
            status: $this->status($trxStatus),
            merchantTradeNo: $response['reqsn'] ?? $response['unireqsn'] ?? $payload['reqsn'] ?? null,
            providerTradeNo: $response['trxid'] ?? null,
            channelTradeNo: $response['chnltrxid'] ?? null,
            retCode: $response['retcode'] ?? null,
            retMsg: $response['retmsg'] ?? $response['errmsg'] ?? null,
            trxStatus: $trxStatus ?: null,
            raw: $response,
        ))->toArray();
    }

    private function normalizeRefund(array $response): array
    {
        $trxStatus = (string) ($response['trxstatus'] ?? '');

        return (new RefundResponse(
            status: $this->status($trxStatus),
            refundTradeNo: $response['reqsn'] ?? null,
            providerTradeNo: $response['trxid'] ?? null,
            retCode: $response['retcode'] ?? null,
            retMsg: $response['retmsg'] ?? $response['errmsg'] ?? null,
            raw: $response,
        ))->toArray();
    }

    private function providerPayload(array $request): array
    {
        $amountFen = $request['amount_fen'] ?? null;
        if ($amountFen === null && isset($request['amount'])) {
            $amountFen = (int) round(((float) $request['amount']) * 100);
        }
        if ($amountFen !== null) {
            $request['trxamt'] ??= $amountFen;
        }
        if (isset($request['merchant_trade_no'])) {
            $request['reqsn'] ??= $request['merchant_trade_no'];
        }
        if (isset($request['provider_trade_no'])) {
            $request['trxid'] ??= $request['provider_trade_no'];
        }

        foreach ([
            'tenant_id', 'tenantId', 'store_id', 'storeId', 'order_id', 'orderId',
            'amount', 'amount_fen', 'merchant_trade_no', 'provider_trade_no',
            'payment_method', 'paymentMethod', 'metadata', 'refund_trade_no',
        ] as $key) {
            unset($request[$key]);
        }

        return $request;
    }

    private function status(string $trxStatus): string
    {
        return match (true) {
            $trxStatus === '0000' => 'paid',
            $trxStatus === '2000' => 'processing',
            $trxStatus !== '' && str_starts_with($trxStatus, '3') => 'failed',
            default => 'unknown',
        };
    }
}
