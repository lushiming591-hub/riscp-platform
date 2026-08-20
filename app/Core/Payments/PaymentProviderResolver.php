<?php

declare(strict_types=1);

namespace App\Core\Payments;

use App\Core\Payments\Contracts\PaymentProviderInterface;

final class PaymentProviderResolver
{
    /** @param iterable<PaymentProviderInterface> $providers */
    public function __construct(private iterable $providers = []) {}

    public function resolve(string $code): PaymentProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->code() === $code) return $provider;
        }
        throw new \RuntimeException("Unsupported payment provider: {$code}");
    }
}
