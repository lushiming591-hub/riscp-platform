<?php

declare(strict_types=1);

namespace App\Core\Payments\Events;

final class PaymentCompletedEvent
{
    public function __construct(
        public readonly string $merchantTradeNo,
        public readonly string $providerTradeNo,
        public readonly int $amountCents,
        public readonly string $providerCode,
        public readonly array $metadata = [],
    ) {}
}
