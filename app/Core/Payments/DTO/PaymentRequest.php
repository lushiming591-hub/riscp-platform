<?php

declare(strict_types=1);

namespace App\Core\Payments\DTO;

final readonly class PaymentRequest
{
    public function __construct(
        public string $tenantId,
        public string $storeId,
        public string $orderId,
        public string $merchantTradeNo,
        public float $amount,
        public string $paymentMethod,
        public array $metadata = [],
    ) {}
}
