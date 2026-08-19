<?php

declare(strict_types=1);

namespace App\Core\Settlement;

use Illuminate\Support\Facades\DB;

final class DailySettlementService
{
    public function close(string $tenantId, string $storeId, string $businessDate): void
    {
        DB::transaction(function () use ($tenantId, $storeId, $businessDate): void {
            $existing = DB::table('daily_settlements')->where('store_id', $storeId)->where('business_date', $businessDate)->lockForUpdate()->first();
            if ($existing?->status === 'closed') return;

            $orders = DB::table('orders')->where('tenant_id', $tenantId)->where('store_id', $storeId)->whereDate('created_at', $businessDate)->get();
            $gross = 0.0; $discount = 0.0; $net = 0.0; $refund = 0.0; $count = 0;
            foreach ($orders as $order) {
                $count++;
                $gross += (float) ($order->subtotal ?? 0);
                $discount += (float) ($order->discount_amount ?? 0);
                $net += (float) ($order->total_amount ?? 0);
                if ($order->status === 'refunded') $refund += (float) ($order->total_amount ?? 0);
            }

            $payload = ['order_count' => $count, 'gross_amount' => $gross, 'discount_amount' => $discount, 'net_amount' => $net, 'refund_amount' => $refund, 'status' => 'closed', 'closed_at' => now(), 'updated_at' => now(), 'created_at' => now()];
            if ($existing) DB::table('daily_settlements')->where('id', $existing->id)->update($payload);
            else DB::table('daily_settlements')->insert(array_merge($payload, ['id' => (string) \Illuminate\Support\Str::uuid(), 'tenant_id' => $tenantId, 'store_id' => $storeId, 'business_date' => $businessDate]));
        });
    }
}
