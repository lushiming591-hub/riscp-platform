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
        $id = (string) Str::uuid();
        DB::table('products')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'name' => $name,
            'type' => 'restaurant_item',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('skus')->insert([
            'id' => $skuId ?: (string) Str::uuid(),
            'product_id' => $id,
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'name' => $name,
            'price' => $price,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $id;
    }
}
