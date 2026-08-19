<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Alliance;

use App\Core\Alliance\CommissionPolicy;
use PHPUnit\Framework\TestCase;

final class CommissionPolicyTest extends TestCase
{
    public function test_first_order_uses_three_percent_referrer_rate(): void
    {
        $policy = new CommissionPolicy();
        $fees = $policy->calculate(10000, 0);

        self::assertSame(1000, $fees['merchant_discount_cents']);
        self::assertSame(300, $fees['referrer_commission_cents']);
        self::assertSame(200, $fees['platform_fee_cents']);
    }

    public function test_second_order_uses_one_percent_referrer_rate(): void
    {
        $policy = new CommissionPolicy();
        $fees = $policy->calculate(10000, 1);

        self::assertSame(100, $fees['referrer_commission_cents']);
    }

    public function test_later_orders_have_no_referrer_commission(): void
    {
        $policy = new CommissionPolicy();
        $fees = $policy->calculate(10000, 2);

        self::assertSame(0, $fees['referrer_commission_cents']);
    }
}
