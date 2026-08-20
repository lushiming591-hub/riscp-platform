<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use App\Core\Payments\PaymentProviderResolver;
use App\Core\Payments\Contracts\PaymentProviderInterface;
use PHPUnit\Framework\TestCase;

final class PaymentProviderResolverTest extends TestCase
{
    public function test_resolves_provider_by_code(): void
    {
        $provider = new class implements PaymentProviderInterface {
            public function code(): string { return 'test-provider'; }
            public function createPayment($request): \App\Core\Payments\DTO\PaymentResponse { throw new \LogicException(); }
            public function queryPayment(string $providerTradeNo): array { return []; }
            public function refund(string $providerTradeNo, string $refundNo, float $amount): array { return []; }
            public function verifyCallback(array $payload, array $headers = []): array { return ['valid' => true]; }
        };
        self::assertSame($provider, (new PaymentProviderResolver([$provider]))->resolve('test-provider'));
    }

    public function test_unknown_provider_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        (new PaymentProviderResolver([]))->resolve('missing');
    }
}
