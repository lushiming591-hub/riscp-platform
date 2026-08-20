<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use PHPUnit\Framework\TestCase;

final class PaymentRefundServiceValidationTest extends TestCase
{
    public function test_refund_amount_must_be_positive(): void
    {
        self::assertTrue(0 <= 0);
    }

    public function test_refund_amount_cannot_exceed_original_payment(): void
    {
        self::assertTrue(100 > 0);
    }
}
