<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Support\Facades\DB;

final class PaymentCallbackService
{
    public function __construct(
        private readonly PaymentTransactionService $transactions,
    ) {
    }

    public function handle(
        string $transactionId,
        string $providerTradeNo,
        array $payload,
        array $headers = [],
        ?string $providerId = null,
        ?string $eventId = null,
        string $signatureStatus = 'verified',
    ): void {
        $transactionProviderId = DB::table('payment_transactions')
            ->where('id', $transactionId)
            ->value('provider_id');

        if ($transactionProviderId === null || $transactionProviderId === '') {
            throw new \RuntimeException('Payment transaction provider is required for callback idempotency.');
        }

        if ($providerId !== null && $providerId !== $transactionProviderId) {
            throw new \RuntimeException('Callback provider does not match payment transaction provider.');
        }

        $this->transactions->markPaid(
            transactionId: $transactionId,
            providerTradeNo: $providerTradeNo,
            raw: [
                'payload' => $payload,
                'headers' => $headers,
            ],
            eventId: $eventId,
            signatureStatus: $signatureStatus,
        );
    }
}
