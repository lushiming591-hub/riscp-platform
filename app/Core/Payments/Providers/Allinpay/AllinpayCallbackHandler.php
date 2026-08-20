<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

final class AllinpayCallbackHandler
{
    public function __construct(private readonly AllinpaySigner $signer) {}

    public function handle(array $payload): array
    {
        $sign = (string) ($payload['sign'] ?? '');
        if ($sign === '') {
            throw new \RuntimeException('Allinpay callback signature is missing.');
        }

        $verify = $payload;
        unset($verify['sign']);
        if (!$this->signer->verify($verify, $sign)) {
            throw new \RuntimeException('Allinpay callback signature verification failed.');
        }

        $trxstatus = (string) ($payload['trxstatus'] ?? '');
        $status = match ($trxstatus) {
            '0000' => 'paid',
            '2000', '' => 'processing',
            default => 'failed',
        };

        return [
            'status' => $status,
            'provider_trade_no' => $payload['trxid'] ?? null,
            // Allinpay unified transaction notifications use reqsn as the
            // merchant transaction number.
            'merchant_trade_no' => $payload['reqsn'] ?? null,
            'amount' => isset($payload['trxamt']) ? ((int) $payload['trxamt']) / 100 : null,
            'fee' => isset($payload['fee']) ? ((int) $payload['fee']) / 100 : null,
            'raw' => $payload,
        ];
    }
}
