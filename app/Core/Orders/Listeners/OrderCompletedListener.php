<?php

declare(strict_types=1);

namespace App\Core\Orders\Listeners;

use App\Core\Orders\Events\OrderCompletedEvent;
use Illuminate\Support\Facades\DB;

final class OrderCompletedListener
{
    public function handle(OrderCompletedEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            $order = DB::table('orders')->where('id', $event->orderId)->lockForUpdate()->first();
            if (!$order) throw new \RuntimeException('Order not found: ' . $event->orderId);
            if (in_array($order->status, ['cancelled'], true)) return;
            if ($order->status === 'completed') return;

            DB::table('orders')->where('id', $order->id)->update([
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
