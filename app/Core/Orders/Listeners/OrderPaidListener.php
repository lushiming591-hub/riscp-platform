<?php

declare(strict_types=1);

namespace App\Core\Orders\Listeners;

use App\Core\Orders\Events\OrderPaidEvent;
use Illuminate\Support\Facades\DB;

final class OrderPaidListener
{
    public function handle(OrderPaidEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            $order = DB::table('orders')->where('id', $event->orderId)->lockForUpdate()->first();
            if (!$order) throw new \RuntimeException('Order not found: ' . $event->orderId);

            if (in_array($order->status, ['paid', 'preparing', 'ready', 'completed'], true)) return;

            DB::table('orders')->where('id', $order->id)->update([
                'status' => 'paid',
                'paid_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
