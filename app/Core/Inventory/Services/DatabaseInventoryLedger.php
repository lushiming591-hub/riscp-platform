<?php

declare(strict_types=1);

namespace App\Core\Inventory\Services;

use App\Core\Inventory\Contracts\InventoryLedger;
use Illuminate\Support\Facades\DB;

final class DatabaseInventoryLedger implements InventoryLedger
{
    public function deductForOrder(int|string $orderId, array $requirements): void
    {
        foreach ($requirements as $requirement) {
            $materialId = $requirement['material_id'];
            $quantity = (float) $requirement['quantity'];
            if ($quantity <= 0) throw new \InvalidArgumentException('Inventory deduction quantity must be positive.');

            $stock = DB::table('inventory_stocks')
                ->where('material_id', $materialId)
                ->orderBy('warehouse_id')
                ->lockForUpdate()
                ->first();

            if (!$stock) throw new \RuntimeException('Inventory stock not found for material: ' . $materialId);
            $available = (float) $stock->quantity - (float) $stock->reserved_quantity;
            if ($available < $quantity) {
                throw new \RuntimeException('Insufficient inventory for material: ' . $materialId);
            }

            $before = (float) $stock->quantity;
            $after = $before - $quantity;
            $key = 'order:' . $orderId . ':material:' . $materialId;

            if (DB::table('inventory_ledger')->where('idempotency_key', $key)->exists()) continue;

            DB::table('inventory_stocks')->where('id', $stock->id)->update([
                'quantity' => $after,
                'updated_at' => now(),
            ]);

            DB::table('inventory_ledger')->insert([
                'idempotency_key' => $key,
                'warehouse_id' => $stock->warehouse_id,
                'material_id' => $materialId,
                'order_id' => $orderId,
                'quantity' => $quantity,
                'unit' => $requirement['unit'],
                'before_quantity' => $before,
                'after_quantity' => $after,
                'operation_type' => 'order_consumption',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
