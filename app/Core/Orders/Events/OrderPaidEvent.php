<?php

declare(strict_types=1);

namespace App\Core\Orders\Events;

final class OrderPaidEvent
{
    public function __construct(
        public readonly int|string $orderId,
        public readonly string $merchantTradeNo,
        public readonly array $metadata = [],
    ) {}
}
