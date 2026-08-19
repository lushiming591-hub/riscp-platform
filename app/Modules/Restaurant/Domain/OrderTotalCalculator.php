<?php

declare(strict_types=1);

namespace App\Modules\Restaurant\Domain;

final class OrderTotalCalculator
{
    /** @param array<int, array{quantity:int|float, unit_price:int|float}> $items */
    public function calculate(array $items, int|float $discount = 0): array
    {
        $subtotal = 0.0;
        foreach ($items as $item) {
            if ($item['quantity'] <= 0 || $item['unit_price'] < 0) {
                throw new \InvalidArgumentException('Invalid order item.');
            }
            $subtotal += (float) $item['quantity'] * (float) $item['unit_price'];
        }

        $discount = max(0, (float) $discount);
        $total = max(0, $subtotal - $discount);

        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discount, 2),
            'total_amount' => round($total, 2),
        ];
    }
}
