<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

use App\Core\Payments\Contracts\PaymentProviderContract;
use App\Core\Payments\DTO\PaymentResponse;
use App\Core\Payments\DTO\QueryResponse;
use App\Core\Payments\DTO\RefundResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
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

    public function __construct(private readonly AllinpaySigner $signer, private readonly string $baseUrl, private readonly string $orgId, private readonly string $cusId, private readonly string $appId, private readonly string $version = '11') {}

    public function pay(array $request): array { return $this->payment(self::PAY, $request); }
    public function scanPay(array $request): array { return $this->payment(self::NATIVE_PAY, $request); }
    public function microPay(array $request): array { return $this->payment(self::MICRO_PAY, $request); }
    public function query(array $request): array { return $this->queryEndpoint(self::QUERY, $request); }
    public function confirmQuery(array $request): array { return $this->queryEndpoint(self::QUERY_CONFIRM, $request); }

    public function closeNative(array $request): array
    {
        return $this->request(self::CLOSE_NATIVE, $this->basePayload($this->providerPayload($request)));
    }

    public function refund(array $request): array
    {
        $request = $this->providerPayload($request);
        $payload = $this->basePayload($request);
        $payload['reqsn'] ??= $request['refund_trade_no'] ?? ('RF' . Str::upper(Str::random(24)));
        $payload['trxamt'] ??= $request['amount_fen'] ?? $request['trxamt'] ?? null;
        $payload['oldtrxid'] ??= $request['provider_trade_no'] ?? $request['oldtrxid'] ?? null;
        $payload['oldreqsn'] ??= $request['merchant_trade_no'] ?? $request['oldreqsn'] ?? null;
        return $this->normalizeRefund($this->request(self::REFUND, $payload));
    }

    public function verifyCallback(array $payload, array $headers = []): bool
    {
        return $this->signer->verify($payload, (string) ($payload['sign'] ?? ''));
    }

    public function parseCallback(array $payload, array $headers = []): array
    {
        if (!$this->verifyCallback($payload, $headers)) throw new RuntimeException('Invalid Allinpay callback signature.');
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
        $request = $this->providerPayload($request);
        $payload = $this->basePayload($request);
        $payload['reqsn'] ??= $request['merchant_trade_no'] ?? null;
        $payload['trxamt'] ??= $request['amount_fen'] ?? $request['trxamt'] ?? null;
        $response = $this->request($endpoint, $payload);
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
        $request = $this->providerPayload($request);
        $payload = $this->basePayload($request);
        $payload['reqsn'] ??= $request['merchant_trade_no'] ?? null;
        $payload['trxid'] ??= $request['provider_trade_no'] ?? null;
        $response = $this->request($endpoint, $payload);
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

    private function request(string $endpoint, array $payload): array
    {
        $payload = array_filter($payload, static fn ($value) => $value !== null && $value !== '');
        $payload['randomstr'] ??= Str::lower(Str::random(32));
        $payload['signtype'] ??= 'RSA';
        $payload['sign'] = $this->signer->sign($payload);
        $response = $this->http()->asForm()->post($endpoint, $payload);
        if ($response->failed()) throw new RuntimeException('Allinpay HTTP request failed: ' . $response->status());
        $data = $response->json();
        if (!is_array($data)) throw new RuntimeException('Allinpay returned an invalid JSON response.');
        $signature = (string) ($data['sign'] ?? '');
        if ($signature !== '' && !$this->signer->verify($data, $signature)) throw new RuntimeException('Invalid Allinpay response signature.');
        return $data;
    }

    private function basePayload(array $request): array
    {
        return array_merge(['orgid' => $this->orgId, 'cusid' => $this->cusId, 'appid' => $this->appId, 'version' => $this->version], $request);
    }

    private function providerPayload(array $request): array
    {
        foreach (['tenant_id','tenantId','store_id','storeId','order_id','orderId','payment_method','paymentMethod','metadata'] as $key) unset($request[$key]);
        if (isset($request['merchant_trade_no']) && !isset($request['reqsn'])) $request['reqsn'] = $request['merchant_trade_no'];
        if (isset($request['amount']) && !isset($request['trxamt'])) $request['trxamt'] = (int) round(((float) $request['amount']) * 100);
        return $request;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))->acceptJson()->timeout(15)->connectTimeout(5);
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
