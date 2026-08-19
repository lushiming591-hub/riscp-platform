<?php

declare(strict_types=1);

namespace App\Core\Restaurant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MenuItemService
{
    public function create(string $tenantId, string $storeId, string $name, float $price, ?string $skuId = null): string
    {
        if ($name === '' || $price < 0) throw new \InvalidArgumentException('Invalid menu item.');

        return DB::transaction(function () use ($tenantId, $storeId, $name, $price, $skuId): string {
            $storeExists = DB::table('stores')->where('id', $storeId)->where('tenant_id', $tenantId)->where('status', 'active')->exists();
            if (!$storeExists) throw new \RuntimeException('Store not found or inactive.');

            $productId = (string) Str::uuid();
            $sku = $skuId ?: (string) Str::uuid();
            $skuCode = 'REST-' . strtoupper(Str::random(10));

            DB::table('products')->insert([
                'id' => $productId,
                'tenant_id' => $tenantId,
                'name' => $name,
                'type' => 'restaurant_item',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('skus')->insert([
                'id' => $sku,
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'sku_code' => $skuCode,
                'unit' => 'pcs',
                'sale_price' => $price,
                'cost_price' => 0,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $productId;
        });
    }
}
