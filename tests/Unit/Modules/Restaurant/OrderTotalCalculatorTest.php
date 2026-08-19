<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Restaurant;

use App\Modules\Restaurant\Domain\OrderTotalCalculator;
use PHPUnit\Framework\TestCase;

final class OrderTotalCalculatorTest extends TestCase
{
    public function test_it_calculates_subtotal_discount_and_total(): void
    {
        $result = (new OrderTotalCalculator())->calculate([
            ['quantity' => 2, 'unit_price' => 35],
            ['quantity' => 1, 'unit_price' => 50],
        ], 10);

        self::assertSame(120.0, $result['subtotal']);
        self::assertSame(10.0, $result['discount_amount']);
        self::assertSame(110.0, $result['total_amount']);
    }

    public function test_total_cannot_be_negative(): void
    {
        $result = (new OrderTotalCalculator())->calculate([
            ['quantity' => 1, 'unit_price' => 20],
        ], 50);

        self::assertSame(0.0, $result['total_amount']);
    }
}
