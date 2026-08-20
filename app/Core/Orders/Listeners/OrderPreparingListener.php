<?php

declare(strict_types=1);

namespace App\Core\Orders\Listeners;

use App\Core\Orders\Events\OrderPreparingEvent;
use Illuminate\Support\Facades\DB;

final class OrderPreparingListener
{
    public function handle(OrderPreparingEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            $order = DB::table('orders')->where('id', $event->orderId)->lockForUpdate()->first();
            if (!$order) throw new \RuntimeException('Order not found: ' . $event->orderId);
            if (in_array($order->status, ['completed', 'cancelled'], true)) return;

            DB::table('orders')->where('id', $order->id)->update([
                'status' => 'preparing',
                'updated_at' => now(),
            ]);
        });
    }
}
