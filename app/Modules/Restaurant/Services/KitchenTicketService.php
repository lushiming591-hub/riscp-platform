<?php

declare(strict_types=1);

namespace App\Modules\Restaurant\Services;

use Illuminate\Support\Facades\DB;

final class KitchenTicketService
{
    public function queue(string $tenantId, string $storeId, string $orderId): string
    {
        $id = (string) \Illuminate\Support\Str::uuid();

        DB::table('kitchen_tickets')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'order_id' => $orderId,
            'status' => 'queued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function start(string $ticketId): void
    {
        DB::table('kitchen_tickets')->where('id', $ticketId)->update([
            'status' => 'preparing',
            'started_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function complete(string $ticketId): void
    {
        DB::table('kitchen_tickets')->where('id', $ticketId)->update([
            'status' => 'completed',
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
