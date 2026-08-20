<?php

declare(strict_types=1);

namespace App\Core\Payments\Contracts;

interface PaymentProviderContract
{
    public function pay(array $request): array;

    public function scanPay(array $request): array;

    public function microPay(array $request): array;

    public function query(array $request): array;

    public function confirmQuery(array $request): array;

    public function closeNative(array $request): array;

    public function refund(array $request): array;

    public function verifyCallback(array $payload, array $headers = []): bool;

    public function parseCallback(array $payload, array $headers = []): array;

    public function reconcile(array $request): array;
}
