<?php

declare(strict_types=1);

namespace App\Core\Orders\Listeners;

use App\Core\Orders\Events\OrderPaidEvent;
use Illuminate\Support\Facades\DB;

final class OrderPaidInventoryGuard
{
    public function handle(OrderPaidEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            $order = DB::table('orders')->where('id', $event->orderId)->lockForUpdate()->first();
            if (!$order) throw new \RuntimeException('Order not found: ' . $event->orderId);

            $marker = 'order-paid-inventory:' . $event->orderId;
            $exists = DB::table('order_events')->where('event_key', $marker)->exists();
            if ($exists) return;

            DB::table('order_events')->insert([
                'event_key' => $marker,
                'order_id' => $event->orderId,
                'event_type' => 'payment.completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
