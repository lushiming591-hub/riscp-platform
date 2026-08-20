<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

final class AllinpayTransactionMapper
{
    private const PAY_TYPES = [
        'wechat_scan' => 'W01',
        'wechat_miniapp' => 'W06',
        'alipay_scan' => 'A01',
        'unionpay_scan' => 'U01',
        'wechat_jsapi' => 'W02',
        'alipay_jsapi' => 'A02',
        'wechat_app' => 'W03',
        'alipay_app' => 'A03',
    ];

    public function payType(string $paymentMethod): string
    {
        return self::PAY_TYPES[$paymentMethod] ?? throw new \InvalidArgumentException("Unsupported Allinpay payment method: {$paymentMethod}");
    }

    public function trxStatus(?string $status, string $retcode = 'SUCCESS'): string
    {
        if ($retcode !== 'SUCCESS') return 'failed';
        return match ($status) {
            '0000' => 'paid',
            '2000', null, '' => 'processing',
            default => 'failed',
        };
    }
}
