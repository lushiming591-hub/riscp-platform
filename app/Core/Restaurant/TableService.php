<?php

declare(strict_types=1);

namespace App\Core\Restaurant;

use Illuminate\Support\Facades\DB;

final class TableService
{
    public function occupy(string $tenantId, string $tableId, string $orderId): void
    {
        DB::transaction(function () use ($tenantId, $tableId, $orderId): void {
            $table = DB::table('restaurant_tables')->where('id', $tableId)->where('tenant_id', $tenantId)->lockForUpdate()->first();
            if (!$table) throw new \RuntimeException('Table not found.');
            if ($table->status !== 'available') throw new \RuntimeException('Table is not available.');
            DB::table('restaurant_tables')->where('id', $tableId)->update(['status' => 'occupied', 'updated_at' => now()]);
        });
    }

    public function release(string $tenantId, string $tableId): void
    {
        DB::table('restaurant_tables')->where('id', $tableId)->where('tenant_id', $tenantId)->update(['status' => 'available', 'updated_at' => now()]);
    }
}
