<?php

declare(strict_types=1);

namespace App\Core\Orders\Services;

use App\Core\Inventory\Services\OrderInventoryDeductionService;
use App\Core\Orders\Events\OrderCompletedEvent;
use Illuminate\Support\Facades\DB;

final class OrderCompletionService
{
    public function __construct(private readonly OrderInventoryDeductionService $inventory) {}

    public function complete(int|string $orderId): void
    {
        DB::transaction(function () use ($orderId): void {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) throw new \RuntimeException('Order not found: ' . $orderId);
            if (in_array($order->status, ['cancelled', 'completed'], true)) return;
            if (!in_array($order->status, ['paid', 'preparing', 'ready'], true)) {
                throw new \RuntimeException('Order cannot be completed from status: ' . $order->status);
            }

            $this->inventory->deduct($orderId);

            DB::table('orders')->where('id', $orderId)->update([
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
        });

        event(new OrderCompletedEvent($orderId));
    }
}
