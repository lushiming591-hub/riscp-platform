<?php

declare(strict_types=1);

namespace App\Core\Restaurant;

use Illuminate\Support\Facades\DB;

final class MenuService
{
    public function list(string $tenantId, ?string $status = 'active'): array
    {
        $query = DB::table('skus as s')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->where('p.tenant_id', $tenantId)
            ->select([
                's.id as sku_id',
                'p.id as product_id',
                'p.name',
                's.sku_code',
                's.unit',
                's.sale_price',
                's.cost_price',
                's.status as sku_status',
                'p.status as product_status',
            ])
            ->orderBy('p.name');

        if ($status !== null) {
            $query->where('p.status', $status)->where('s.status', $status);
        }

        return $query->get()->map(fn ($row) => [
            'sku_id' => (string) $row->sku_id,
            'product_id' => (string) $row->product_id,
            'name' => $row->name,
            'sku_code' => $row->sku_code,
            'unit' => $row->unit,
            'sale_price' => (float) $row->sale_price,
        ])->all();
    }
}
