<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use App\Core\Payments\DTO\PaymentRequest;
use App\Core\Payments\Providers\MockPaymentProvider;
use PHPUnit\Framework\TestCase;

final class MockPaymentProviderTest extends TestCase
{
    public function test_create_query_and_refund_flow(): void
    {
        $provider = new MockPaymentProvider();
        $request = new PaymentRequest('tenant', 'store', 'order', 'ORDER-001', 100.00, 'wechat');
        $payment = $provider->createPayment($request);
        self::assertSame('paid', $payment->status);
        self::assertSame('MOCK-ORDER-001', $payment->providerTradeNo);
        self::assertSame('paid', $provider->queryPayment($payment->providerTradeNo)['status']);
        self::assertTrue($provider->refund($payment->providerTradeNo, 'REF-001', 30.00)['success']);
    }

    public function test_callback_is_verified_by_mock_adapter(): void
    {
        $result = (new MockPaymentProvider())->verifyCallback(['provider_trade_no' => 'MOCK-1']);
        self::assertTrue($result['valid']);
        self::assertSame('MOCK-1', $result['provider_trade_no']);
    }
}
