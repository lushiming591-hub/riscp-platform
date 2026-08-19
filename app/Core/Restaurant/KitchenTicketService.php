<?php

declare(strict_types=1);

namespace App\Core\Restaurant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class KitchenTicketService
{
    public function createForOrder(string $tenantId, string $storeId, string $orderId): string
    {
        $order = DB::table('orders')->where('id', $orderId)->where('tenant_id', $tenantId)->where('store_id', $storeId)->first();
        if (!$order) throw new \RuntimeException('Order not found.');
        $existing = DB::table('kitchen_tickets')->where('order_id', $orderId)->whereIn('status', ['queued', 'preparing'])->first();
        if ($existing) return (string) $existing->id;
        $id = (string) Str::uuid();
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

    public function start(string $tenantId, string $ticketId): void
    {
        $updated = DB::table('kitchen_tickets')->where('id', $ticketId)->where('tenant_id', $tenantId)->where('status', 'queued')->update(['status' => 'preparing', 'started_at' => now(), 'updated_at' => now()]);
        if ($updated !== 1) throw new \RuntimeException('Ticket is not queued.');
    }

    public function complete(string $tenantId, string $ticketId): void
    {
        $updated = DB::table('kitchen_tickets')->where('id', $ticketId)->where('tenant_id', $tenantId)->where('status', 'preparing')->update(['status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);
        if ($updated !== 1) throw new \RuntimeException('Ticket is not preparing.');
    }
}
