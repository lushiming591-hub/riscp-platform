<?php

declare(strict_types=1);

namespace App\Core\Inventory;

use Illuminate\Support\Facades\DB;

final class RecipeConsumptionService
{
    public function consumeForSku(string $tenantId, string $storeId, string $skuId, float $productQuantity, string $orderId): void
    {
        if ($productQuantity <= 0) {
            throw new \InvalidArgumentException('Product quantity must be greater than zero.');
        }

        $recipes = DB::table('recipes')
            ->where('recipes.sku_id', $skuId)
            ->where('recipes.tenant_id', $tenantId)
            ->get();

        foreach ($recipes as $recipe) {
            $quantity = (float) $recipe->quantity * $productQuantity;
            app(StockTransactionService::class)->deduct(
                $tenantId,
                $storeId,
                (string) $recipe->ingredient_id,
                'order_recipe',
                $orderId . ':' . $skuId . ':' . $recipe->ingredient_id,
                (int) ceil($quantity * 1000),
            );
        }
    }
}
