<?php

declare(strict_types=1);

namespace App\Core\Payments\DTO;

final readonly class RefundResponse
{
    public function __construct(
        public string $status,
        public ?string $refundTradeNo = null,
        public ?string $providerTradeNo = null,
        public ?string $retCode = null,
        public ?string $retMsg = null,
        public array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'refund_trade_no' => $this->refundTradeNo,
            'provider_trade_no' => $this->providerTradeNo,
            'ret_code' => $this->retCode,
            'ret_msg' => $this->retMsg,
            'raw' => $this->raw,
        ];
    }
}
