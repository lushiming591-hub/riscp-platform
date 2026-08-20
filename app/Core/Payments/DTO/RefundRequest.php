<?php

declare(strict_types=1);

namespace App\Core\Payments\DTO;

final readonly class RefundRequest
{
    public function __construct(
        public string $merchantTradeNo,
        public int $amountFen,
        public ?string $providerTradeNo = null,
        public ?string $refundTradeNo = null,
        public array $metadata = [],
    ) {}
}
