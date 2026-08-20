<?php

declare(strict_types=1);

namespace App\Core\Payments\DTO;

final readonly class ConfirmQueryResponse
{
    public function __construct(
        public string $status,
        public ?string $merchantTradeNo = null,
        public ?string $providerTradeNo = null,
        public ?string $channelTradeNo = null,
        public ?string $retCode = null,
        public ?string $retMsg = null,
        public ?string $trxStatus = null,
        public array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'merchant_trade_no' => $this->merchantTradeNo,
            'provider_trade_no' => $this->providerTradeNo,
            'channel_trade_no' => $this->channelTradeNo,
            'ret_code' => $this->retCode,
            'ret_msg' => $this->retMsg,
            'trx_status' => $this->trxStatus,
            'raw' => $this->raw,
        ];
    }
}
