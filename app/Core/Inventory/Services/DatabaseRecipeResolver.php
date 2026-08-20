<?php

declare(strict_types=1);

namespace App\Core\Inventory\Services;

use App\Core\Inventory\Contracts\RecipeResolver;
use Illuminate\Support\Facades\DB;

final class DatabaseRecipeResolver implements RecipeResolver
{
    /**
     * Resolve an order into inventory-material requirements.
     *
     * The sales SKU is deliberately not treated as an inventory material.
     * order_items.sku_id -> sku_inventory_products -> inventory_products
     * -> inventory_product_recipes -> inventory_materials is the only
     * inventory-BOM path used here.
     *
     * @return array<int,array{material_id:int|string,quantity:float,unit:string}>
     */
    public function requirementsForOrder(int|string $orderId): array
    {
        $items = DB::table('order_items')
            ->where('order_id', $orderId)
            ->select(['id', 'sku_id', 'quantity'])
            ->get();

        if ($items->isEmpty()) {
            return [];
        }

        $requirements = [];

        foreach ($items as $item) {
            $recipes = DB::table('sku_inventory_products as m')
                ->join('inventory_product_recipes as r', 'r.product_id', '=', 'm.inventory_product_id')
                ->join('inventory_products as p', 'p.id', '=', 'm.inventory_product_id')
                ->join('inventory_materials as material', 'material.id', '=', 'r.material_id')
                ->where('m.sku_id', $item->sku_id)
                ->where('m.active', true)
                ->where('p.active', true)
                ->where('material.active', true)
                ->select([
                    'r.material_id',
                    'r.quantity as recipe_quantity',
                    'r.unit',
                    'm.quantity_multiplier',
                ])
                ->get();

            if ($recipes->isEmpty()) {
                throw new \RuntimeException(
                    'No active inventory mapping or recipe found for SKU: ' . $item->sku_id
                );
            }

            foreach ($recipes as $recipe) {
                $quantity = (float) $recipe->recipe_quantity
                    * (float) $recipe->quantity_multiplier
                    * (float) $item->quantity;

                if ($quantity <= 0) {
                    continue;
                }

                $key = (string) $recipe->material_id . ':' . (string) $recipe->unit;
                $requirements[$key] ??= [
                    'material_id' => $recipe->material_id,
                    'quantity' => 0.0,
                    'unit' => (string) $recipe->unit,
                ];

                $requirements[$key]['quantity'] += $quantity;
            }
        }

        return array_values($requirements);
    }
}
