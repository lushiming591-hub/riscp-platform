<?php

declare(strict_types=1);

namespace App\Core\Alliance;

final readonly class CommissionPolicy
{
    public function __construct(
        public int $firstOrderRateBps = 300,
        public int $secondOrderRateBps = 100,
        public int $laterOrderRateBps = 0,
        public int $attributionDays = 90,
        public int $platformRateBps = 200,
        public int $merchantDiscountBps = 1000,
    ) {}

    public function referrerRateBps(int $completedOrders): int
    {
        return match (true) {
            $completedOrders <= 0 => $this->firstOrderRateBps,
            $completedOrders === 1 => $this->secondOrderRateBps,
            default => $this->laterOrderRateBps,
        };
    }

    public function calculate(int $grossCents, int $completedOrders): array
    {
        $referrerRate = $this->referrerRateBps($completedOrders);
        return [
            'merchant_discount_cents' => intdiv($grossCents * $this->merchantDiscountBps, 10000),
            'referrer_commission_cents' => intdiv($grossCents * $referrerRate, 10000),
            'platform_fee_cents' => intdiv($grossCents * $this->platformRateBps, 10000),
        ];
    }
}
