<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use App\Core\Payments\PaymentSignatureVerifier;
use App\Core\Payments\Contracts\PaymentProviderInterface;
use PHPUnit\Framework\TestCase;

final class PaymentSignatureVerifierTest extends TestCase
{
    public function test_valid_callback_is_accepted(): void
    {
        $provider = new class implements PaymentProviderInterface {
            public function code(): string { return 'test'; }
            public function createPayment($request): \App\Core\Payments\DTO\PaymentResponse { throw new \LogicException(); }
            public function queryPayment(string $providerTradeNo): array { return []; }
            public function refund(string $providerTradeNo, string $refundNo, float $amount): array { return []; }
            public function verifyCallback(array $payload, array $headers = []): array { return ['valid' => true]; }
        };
        self::assertTrue((new PaymentSignatureVerifier())->verify($provider, ['ok' => 1])['valid']);
    }

    public function test_invalid_callback_is_rejected(): void
    {
        $provider = new class implements PaymentProviderInterface {
            public function code(): string { return 'test'; }
            public function createPayment($request): \App\Core\Payments\DTO\PaymentResponse { throw new \LogicException(); }
            public function queryPayment(string $providerTradeNo): array { return []; }
            public function refund(string $providerTradeNo, string $refundNo, float $amount): array { return []; }
            public function verifyCallback(array $payload, array $headers = []): array { return ['valid' => false, 'message' => 'bad signature']; }
        };
        $this->expectException(\RuntimeException::class);
        (new PaymentSignatureVerifier())->verify($provider, []);
    }
}
