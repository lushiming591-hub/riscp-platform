<?php

declare(strict_types=1);

namespace App\Core\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StockMovementService
{
    public function receive(string $tenantId, string $warehouseId, string $skuId, float $quantity, string $referenceId): void
    {
        $this->move($tenantId, $warehouseId, $skuId, abs($quantity), 'receive', 'purchase', $referenceId);
    }

    public function returnToStock(string $tenantId, string $warehouseId, string $skuId, float $quantity, string $referenceId): void
    {
        $this->move($tenantId, $warehouseId, $skuId, abs($quantity), 'return', 'return', $referenceId);
    }

    public function waste(string $tenantId, string $warehouseId, string $skuId, float $quantity, string $referenceId): void
    {
        $this->deduct($tenantId, $warehouseId, $skuId, abs($quantity), 'waste', $referenceId);
    }

    public function transfer(string $tenantId, string $fromWarehouseId, string $toWarehouseId, string $skuId, float $quantity, string $referenceId): void
    {
        DB::transaction(function () use ($tenantId, $fromWarehouseId, $toWarehouseId, $skuId, $quantity, $referenceId): void {
            $this->deduct($tenantId, $fromWarehouseId, $skuId, $quantity, 'transfer_out', $referenceId);
            $this->move($tenantId, $toWarehouseId, $skuId, $quantity, 'transfer_in', 'transfer', $referenceId);
        });
    }

    private function move(string $tenantId, string $warehouseId, string $skuId, float $quantity, string $type, string $sourceType, string $referenceId): void
    {
        if ($quantity <= 0) throw new \InvalidArgumentException('Quantity must be greater than zero.');
        $key = $type . ':' . $referenceId . ':' . $skuId;

        DB::transaction(function () use ($tenantId, $warehouseId, $skuId, $quantity, $type, $sourceType, $referenceId, $key): void {
            if (DB::table('stock_transactions')->where('tenant_id', $tenantId)->where('idempotency_key', $key)->exists()) return;
            $stock = DB::table('stock_balances')->where('tenant_id', $tenantId)->where('warehouse_id', $warehouseId)->where('sku_id', $skuId)->lockForUpdate()->first();
            if (!$stock) throw new \RuntimeException('Stock balance not found.');
            $before = (float) $stock->on_hand;
            $after = $before + $quantity;
            $available = $after - (float) $stock->reserved;
            DB::table('stock_balances')->where('id', $stock->id)->update(['on_hand' => $after, 'available' => $available, 'updated_at' => now()]);
            DB::table('stock_transactions')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'warehouse_id' => $warehouseId, 'sku_id' => $skuId, 'type' => $type, 'quantity' => $quantity, 'before_quantity' => $before, 'after_quantity' => $after, 'source_type' => $sourceType, 'source_id' => $referenceId, 'idempotency_key' => $key, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    private function deduct(string $tenantId, string $warehouseId, string $skuId, float $quantity, string $type, string $referenceId): void
    {
        $key = $type . ':' . $referenceId . ':' . $skuId;
        DB::transaction(function () use ($tenantId, $warehouseId, $skuId, $quantity, $type, $referenceId, $key): void {
            if (DB::table('stock_transactions')->where('tenant_id', $tenantId)->where('idempotency_key', $key)->exists()) return;
            $stock = DB::table('stock_balances')->where('tenant_id', $tenantId)->where('warehouse_id', $warehouseId)->where('sku_id', $skuId)->lockForUpdate()->first();
            if (!$stock || (float) $stock->available < $quantity) throw new \RuntimeException('Insufficient available stock.');
            $before = (float) $stock->on_hand;
            $after = $before - $quantity;
            DB::table('stock_balances')->where('id', $stock->id)->update(['on_hand' => $after, 'available' => $after - (float) $stock->reserved, 'updated_at' => now()]);
            DB::table('stock_transactions')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'warehouse_id' => $warehouseId, 'sku_id' => $skuId, 'type' => $type, 'quantity' => -$quantity, 'before_quantity' => $before, 'after_quantity' => $after, 'source_type' => $type, 'source_id' => $referenceId, 'idempotency_key' => $key, 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}
