<?php

declare(strict_types=1);

namespace App\Core\Orders\Events;

final class OrderPreparingEvent
{
    public function __construct(public readonly int|string $orderId) {}
}
