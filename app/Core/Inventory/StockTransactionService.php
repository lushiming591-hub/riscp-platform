<?php

declare(strict_types=1);

namespace App\Core\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StockTransactionService
{
    public function deduct(string $tenantId, string $warehouseId, string $skuId, string $referenceType, string $referenceId, float $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero.');
        }

        $idempotencyKey = $referenceType . ':' . $referenceId . ':' . $skuId;

        DB::transaction(function () use ($tenantId, $warehouseId, $skuId, $referenceType, $referenceId, $quantity, $idempotencyKey): void {
            if (DB::table('stock_transactions')->where('tenant_id', $tenantId)->where('idempotency_key', $idempotencyKey)->exists()) {
                return;
            }

            $stock = DB::table('stock_balances')
                ->where('tenant_id', $tenantId)
                ->where('warehouse_id', $warehouseId)
                ->where('sku_id', $skuId)
                ->lockForUpdate()
                ->first();

            if ($stock === null) {
                throw new \RuntimeException('Stock balance not found.');
            }

            $available = (float) $stock->available;
            if ($available < $quantity) {
                throw new \RuntimeException('Insufficient available stock.');
            }

            $before = (float) $stock->on_hand;
            $after = $before - $quantity;
            $reserved = (float) $stock->reserved;
            $newAvailable = $after - $reserved;

            DB::table('stock_balances')->where('id', $stock->id)->update([
                'on_hand' => $after,
                'available' => $newAvailable,
                'updated_at' => now(),
            ]);

            DB::table('stock_transactions')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'warehouse_id' => $warehouseId,
                'sku_id' => $skuId,
                'type' => 'deduct',
                'quantity' => -$quantity,
                'before_quantity' => $before,
                'after_quantity' => $after,
                'source_type' => $referenceType,
                'source_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'remark' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
