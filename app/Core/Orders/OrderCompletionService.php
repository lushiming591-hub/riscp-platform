<?php

declare(strict_types=1);

namespace App\Core\Orders;

use App\Core\Inventory\Services\OrderInventoryDeductionService;
use Illuminate\Support\Facades\DB;

final class OrderCompletionService
{
    public function __construct(
        private readonly OrderInventoryDeductionService $inventoryDeduction,
    ) {}

    public function complete(string $tenantId, string $storeId, string $warehouseId, string $orderId): void
    {
        DB::transaction(function () use ($tenantId, $storeId, $warehouseId, $orderId): void {
            $order = DB::table('orders')
                ->where('id', $orderId)
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new \RuntimeException('Order not found.');
            }

            if ($order->status === 'completed') {
                return;
            }

            // KitchenCompletionService is the completion gate. At this point
            // the order must already be paid; inventory is deducted exactly
            // once through the inventory-domain RecipeResolver + Ledger path.
            if ($order->status !== 'paid') {
                throw new \RuntimeException('Only paid orders can be completed.');
            }

            $this->inventoryDeduction->deduct($orderId, $warehouseId);

            DB::table('orders')
                ->where('id', $orderId)
                ->update([
                    'status' => 'completed',
                    'updated_at' => now(),
                ]);
        });
    }
}
