<?php

declare(strict_types=1);

namespace App\Core\Orders;

use App\Core\Inventory\RecipeConsumptionService;
use Illuminate\Support\Facades\DB;

final class OrderCompletionService
{
    public function complete(string $tenantId, string $storeId, string $warehouseId, string $orderId): void
    {
        DB::transaction(function () use ($tenantId, $storeId, $warehouseId, $orderId): void {
            $order = DB::table('orders')->where('id', $orderId)->where('tenant_id', $tenantId)->where('store_id', $storeId)->lockForUpdate()->first();
            if (!$order) throw new \RuntimeException('Order not found.');
            if ($order->status === 'completed') return;
            if ($order->status !== 'paid') throw new \RuntimeException('Only paid orders can be completed.');

            $items = DB::table('order_items')->where('order_id', $orderId)->get();
            $recipe = app(RecipeConsumptionService::class);
            foreach ($items as $item) {
                $recipe->consumeForSku($tenantId, $warehouseId, (string) $item->sku_id, (float) $item->quantity, $orderId);
            }

            DB::table('orders')->where('id', $orderId)->update(['status' => 'completed', 'updated_at' => now()]);
        });
    }
}
