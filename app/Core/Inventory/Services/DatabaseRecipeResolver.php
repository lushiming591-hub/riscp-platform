<?php

declare(strict_types=1);

namespace App\Core\Inventory\Services;

use App\Core\Inventory\Contracts\RecipeResolver;
use Illuminate\Support\Facades\DB;

final class DatabaseRecipeResolver implements RecipeResolver
{
    public function requirementsForOrder(int|string $orderId): array
    {
        $items = DB::table('order_items')->where('order_id', $orderId)->get();
        if ($items->isEmpty()) return [];

        $requirements = [];
        foreach ($items as $item) {
            $recipes = DB::table('inventory_product_recipes as r')
                ->join('inventory_products as p', 'p.id', '=', 'r.product_id')
                ->where('p.sku', $item->sku ?? $item->product_sku ?? '')
                ->select('r.material_id', 'r.quantity', 'r.unit')
                ->get();

            foreach ($recipes as $recipe) {
                $key = (string) $recipe->material_id . ':' . $recipe->unit;
                $requirements[$key] ??= [
                    'material_id' => $recipe->material_id,
                    'quantity' => 0.0,
                    'unit' => $recipe->unit,
                ];
                $requirements[$key]['quantity'] += (float) $recipe->quantity * (float) ($item->quantity ?? 1);
            }
        }

        return array_values($requirements);
    }
}
