<?php

declare(strict_types=1);

namespace App\Core\Payments;

final class PaymentProviderConfig
{
    public function __construct(
        public readonly string $code,
        public readonly string $merchantNo,
        public readonly ?string $appId = null,
        public readonly ?string $apiBaseUrl = null,
        public readonly ?string $certificatePath = null,
        public readonly ?string $privateKeyPath = null,
        public readonly ?string $publicKeyPath = null,
    ) {}
}
