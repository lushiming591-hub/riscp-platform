<?php

declare(strict_types=1);

namespace App\Core\Orders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PosOrderService
{
    public function create(string $tenantId, string $storeId, string $orderNo, array $items): string
    {
        if ($items === []) throw new \InvalidArgumentException('Order items cannot be empty.');

        return DB::transaction(function () use ($tenantId, $storeId, $orderNo, $items): string {
            if (DB::table('orders')->where('tenant_id', $tenantId)->where('order_no', $orderNo)->exists()) {
                throw new \RuntimeException('Order number already exists.');
            }

            $subtotal = 0.0;
            $orderId = (string) Str::uuid();
            $now = now();

            foreach ($items as $item) {
                $sku = DB::table('skus')->where('id', $item['sku_id'])->where('tenant_id', $tenantId)->where('status', 'active')->first();
                if (!$sku) throw new \RuntimeException('SKU not found or inactive.');
                $quantity = (float) $item['quantity'];
                if ($quantity <= 0) throw new \InvalidArgumentException('Item quantity must be greater than zero.');
                $unitPrice = array_key_exists('unit_price', $item) ? (float) $item['unit_price'] : (float) $sku->sale_price;
                $lineTotal = round($quantity * $unitPrice, 2);
                $subtotal += $lineTotal;
                DB::table('order_items')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'order_id' => $orderId, 'sku_id' => $sku->id, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'line_total' => $lineTotal, 'created_at' => $now, 'updated_at' => $now]);
            }

            DB::table('orders')->insert(['id' => $orderId, 'tenant_id' => $tenantId, 'store_id' => $storeId, 'order_no' => $orderNo, 'status' => 'pending', 'subtotal' => $subtotal, 'discount_amount' => 0, 'total_amount' => $subtotal, 'created_at' => $now, 'updated_at' => $now]);
            return $orderId;
        });
    }
}
