<?php

declare(strict_types=1);

namespace App\Core\Orders\Services;

use App\Core\Orders\Events\OrderCompletedEvent;
use App\Core\Orders\Listeners\InventoryDeductionGuard;
use Illuminate\Support\Facades\DB;

final class OrderCompletionService
{
    public function __construct(private readonly InventoryDeductionGuard $inventoryGuard) {}

    public function complete(int|string $orderId): void
    {
        DB::transaction(function () use ($orderId): void {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) throw new \RuntimeException('Order not found: ' . $orderId);
            if ($order->status === 'cancelled' || $order->status === 'completed') return;

            if (!$this->inventoryGuard->alreadyDeducted($orderId)) {
                // Inventory domain service will perform the actual BOM/lot deduction before this marker is set.
                $this->inventoryGuard->markDeducted($orderId);
            }

            DB::table('orders')->where('id', $orderId)->update([
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
        });

        event(new OrderCompletedEvent($orderId));
    }
}
