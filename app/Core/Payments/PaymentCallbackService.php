<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentCallbackService
{
    public function handle(string $transactionId, string $providerTradeNo, array $payload, array $headers = []): void
    {
        DB::transaction(function () use ($transactionId, $providerTradeNo, $payload, $headers): void {
            $tx = DB::table('payment_transactions')->where('id', $transactionId)->lockForUpdate()->first();
            if (!$tx) throw new \RuntimeException('Payment transaction not found.');
            if ($tx->status === 'paid') return;

            DB::table('payment_callbacks')->insert([
                'id' => (string) Str::uuid(),
                'transaction_id' => $transactionId,
                'event_type' => 'payment.callback',
                'payload' => json_encode(['payload' => $payload, 'headers' => $headers], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);

            DB::table('payment_transactions')->where('id', $transactionId)->update([
                'status' => 'paid', 'provider_trade_no' => $providerTradeNo, 'paid_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('orders')->where('id', $tx->order_id)->where('status', 'pending')->update([
                'status' => 'paid', 'updated_at' => now(),
            ]);
        });
    }
}
