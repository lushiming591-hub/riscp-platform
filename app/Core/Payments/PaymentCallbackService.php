<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentCallbackService
{
    public function handle(string $transactionId, string $providerTradeNo, array $payload, array $headers = [], ?string $providerId = null, ?string $eventId = null, string $signatureStatus = 'verified'): void
    {
        DB::transaction(function () use ($transactionId, $providerTradeNo, $payload, $headers, $providerId, $eventId, $signatureStatus): void {
            $tx = DB::table('payment_transactions')->where('id', $transactionId)->lockForUpdate()->first();
            if (!$tx) throw new \RuntimeException('Payment transaction not found.');
            if ($tx->status === 'paid') return;

            if ($providerId && $eventId) {
                $exists = DB::table('payment_callbacks')->where('provider_id', $providerId)->where('event_id', $eventId)->exists();
                if ($exists) return;
            }

            DB::table('payment_callbacks')->insert([
                'id' => (string) Str::uuid(),
                'provider_id' => $providerId ?: $tx->provider_id,
                'payment_transaction_id' => $transactionId,
                'event_id' => $eventId,
                'event_type' => 'payment.callback',
                'signature_status' => $signatureStatus,
                'payload' => json_encode(['payload' => $payload, 'headers' => $headers], JSON_UNESCAPED_UNICODE),
                'received_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('payment_transactions')->where('id', $transactionId)->update([
                'status' => 'paid', 'provider_trade_no' => $providerTradeNo, 'paid_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('orders')->where('id', $tx->order_id)->where('status', 'pending')->update(['status' => 'paid', 'updated_at' => now()]);
        });
    }
}
