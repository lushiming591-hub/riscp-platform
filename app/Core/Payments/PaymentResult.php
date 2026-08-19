<?php

declare(strict_types=1);

namespace App\Core\Payments;

final readonly class PaymentResult
{
    public function __construct(
        public string $status,
        public ?string $providerPaymentId = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['paid', 'success'], true);
    }
}
