<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentRefundService
{
    public function create(string $transactionId, float $amount, string $reason): array
    {
        return DB::transaction(function () use ($transactionId, $amount, $reason): array {
            $tx = DB::table('payment_transactions')->where('id', $transactionId)->lockForUpdate()->first();
            if (!$tx) throw new \RuntimeException('Payment transaction not found.');
            if ($tx->status !== 'paid') throw new \RuntimeException('Only paid transactions can be refunded.');
            if ($amount <= 0 || $amount > (float) $tx->amount) throw new \RuntimeException('Invalid refund amount.');
            $refundNo = 'RFR'.date('YmdHis').strtoupper(Str::random(10));
            DB::table('payment_transactions')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $tx->tenant_id, 'store_id' => $tx->store_id,
                'order_id' => $tx->order_id, 'provider_id' => $tx->provider_id, 'payment_account_id' => $tx->payment_account_id,
                'channel_id' => $tx->channel_id, 'merchant_trade_no' => $refundNo, 'amount' => -$amount,
                'status' => 'refund_pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
            return ['refund_no' => $refundNo, 'amount' => $amount, 'reason' => $reason];
        });
    }
}
