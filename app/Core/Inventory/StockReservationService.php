<?php

declare(strict_types=1);

namespace App\Core\Inventory;

use Illuminate\Support\Facades\DB;

final class StockReservationService
{
    public function reserve(string $tenantId, string $warehouseId, string $skuId, float $quantity): void
    {
        if ($quantity <= 0) throw new \InvalidArgumentException('Quantity must be greater than zero.');
        DB::transaction(function () use ($tenantId, $warehouseId, $skuId, $quantity): void {
            $stock = DB::table('stock_balances')->where('tenant_id', $tenantId)->where('warehouse_id', $warehouseId)->where('sku_id', $skuId)->lockForUpdate()->first();
            if (!$stock || (float) $stock->available < $quantity) throw new \RuntimeException('Insufficient available stock.');
            DB::table('stock_balances')->where('id', $stock->id)->update(['reserved' => (float) $stock->reserved + $quantity, 'available' => (float) $stock->available - $quantity, 'updated_at' => now()]);
        });
    }

    public function release(string $tenantId, string $warehouseId, string $skuId, float $quantity): void
    {
        if ($quantity <= 0) throw new \InvalidArgumentException('Quantity must be greater than zero.');
        DB::transaction(function () use ($tenantId, $warehouseId, $skuId, $quantity): void {
            $stock = DB::table('stock_balances')->where('tenant_id', $tenantId)->where('warehouse_id', $warehouseId)->where('sku_id', $skuId)->lockForUpdate()->first();
            if (!$stock || (float) $stock->reserved < $quantity) throw new \RuntimeException('Reserved stock is insufficient.');
            DB::table('stock_balances')->where('id', $stock->id)->update(['reserved' => (float) $stock->reserved - $quantity, 'available' => (float) $stock->available + $quantity, 'updated_at' => now()]);
        });
    }
}
