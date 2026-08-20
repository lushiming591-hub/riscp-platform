<?php

declare(strict_types=1);

namespace App\Core\Orders\Listeners;

use Illuminate\Support\Facades\DB;

final class InventoryDeductionGuard
{
    public function alreadyDeducted(int|string $orderId): bool
    {
        return DB::table('order_events')
            ->where('event_key', 'inventory-deducted:' . $orderId)
            ->exists();
    }

    public function markDeducted(int|string $orderId): void
    {
        DB::table('order_events')->insertOrIgnore([
            'event_key' => 'inventory-deducted:' . $orderId,
            'order_id' => $orderId,
            'event_type' => 'inventory.deducted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
