<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ManualPaymentRouter
{
    public function selectChannel(string $tenantId, string $storeId, string $orderId, string $channelId): string
    {
        return DB::transaction(function () use ($tenantId, $storeId, $orderId, $channelId): string {
            $channel = DB::table('payment_channels')
                ->where('id', $channelId)
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$channel) throw new \RuntimeException('Payment channel is not active or does not belong to this store.');

            $order = DB::table('orders')
                ->where('id', $orderId)
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->first();

            if (!$order) throw new \RuntimeException('Order not found.');
            if ($order->status !== 'pending') throw new \RuntimeException('Only pending orders can be routed to payment.');

            $amount = (float) $order->total_amount;
            $feeAmount = round($amount * (float) $channel->fee_rate / 100, 2);
            $paymentId = (string) Str::uuid();

            DB::table('payments')->insert([
                'id' => $paymentId,
                'tenant_id' => $tenantId,
                'order_id' => $orderId,
                'status' => 'pending',
                'amount' => $amount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('payment_transactions')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'order_id' => $orderId,
                'channel_id' => $channelId,
                'status' => 'created',
                'amount' => $amount,
                'fee_amount' => $feeAmount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $paymentId;
        });
    }
}
