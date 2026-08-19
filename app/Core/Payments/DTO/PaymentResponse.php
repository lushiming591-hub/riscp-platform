<?php

declare(strict_types=1);

namespace App\Core\Payments\DTO;

final readonly class PaymentResponse
{
    public function __construct(
        public string $status,
        public ?string $providerTradeNo = null,
        public ?string $payUrl = null,
        public array $raw = [],
    ) {}
}
