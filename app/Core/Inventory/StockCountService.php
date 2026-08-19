<?php

declare(strict_types=1);

namespace App\Core\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StockCountService
{
    public function adjust(string $tenantId, string $warehouseId, string $skuId, float $actualQuantity, string $referenceId): void
    {
        if ($actualQuantity < 0) throw new \InvalidArgumentException('Actual quantity cannot be negative.');

        DB::transaction(function () use ($tenantId, $warehouseId, $skuId, $actualQuantity, $referenceId): void {
            $key = 'count:' . $referenceId . ':' . $skuId;
            if (DB::table('stock_transactions')->where('tenant_id', $tenantId)->where('idempotency_key', $key)->exists()) return;

            $stock = DB::table('stock_balances')->where('tenant_id', $tenantId)->where('warehouse_id', $warehouseId)->where('sku_id', $skuId)->lockForUpdate()->first();
            if (!$stock) throw new \RuntimeException('Stock balance not found.');

            $before = (float) $stock->on_hand;
            $difference = $actualQuantity - $before;
            $after = $actualQuantity;
            $available = $after - (float) $stock->reserved;
            if ($available < 0) throw new \RuntimeException('Actual stock cannot be lower than reserved stock.');

            DB::table('stock_balances')->where('id', $stock->id)->update(['on_hand' => $after, 'available' => $available, 'updated_at' => now()]);
            DB::table('stock_transactions')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'warehouse_id' => $warehouseId, 'sku_id' => $skuId,
                'type' => 'count_adjustment', 'quantity' => $difference, 'before_quantity' => $before, 'after_quantity' => $after,
                'source_type' => 'stock_count', 'source_id' => $referenceId, 'idempotency_key' => $key,
                'remark' => 'Inventory count adjustment', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
}
