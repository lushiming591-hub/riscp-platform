<?php

declare(strict_types=1);

namespace App\Core\Payments;

final readonly class PaymentRequest
{
    public function __construct(
        public string $tenantId,
        public string $storeId,
        public string $orderId,
        public int $amountCents,
        public string $currency = 'CNY',
        public string $channel = 'unknown',
        public ?string $idempotencyKey = null,
    ) {}
}
