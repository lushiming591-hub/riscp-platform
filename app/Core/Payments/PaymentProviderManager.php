<?php

declare(strict_types=1);

namespace App\Core\Payments;

use App\Core\Payments\Contracts\PaymentProviderContract;
use InvalidArgumentException;

final class PaymentProviderManager
{
    /** @var array<string, PaymentProviderContract> */
    private array $providers = [];

    public function register(string $providerId, PaymentProviderContract $provider): void
    {
        $this->providers[$providerId] = $provider;
    }

    public function provider(string $providerId): PaymentProviderContract
    {
        $provider = $this->providers[$providerId] ?? null;

        if (!$provider) {
            throw new InvalidArgumentException("Payment provider [{$providerId}] is not registered.");
        }

        return $provider;
    }
}
