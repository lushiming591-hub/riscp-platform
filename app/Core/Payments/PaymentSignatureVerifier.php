<?php

declare(strict_types=1);

namespace App\Core\Payments;

use App\Core\Payments\Contracts\PaymentProviderInterface;

final class PaymentSignatureVerifier
{
    public function verify(PaymentProviderInterface $provider, array $payload, array $headers = []): array
    {
        $result = $provider->verifyCallback($payload, $headers);
        if (!($result['valid'] ?? false)) {
            throw new \RuntimeException($result['message'] ?? 'Invalid payment callback signature.');
        }
        return $result;
    }
}
