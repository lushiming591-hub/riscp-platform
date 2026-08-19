<?php

declare(strict_types=1);

namespace App\Core\Restaurant;

use App\Core\Orders\OrderCompletionService;
use Illuminate\Support\Facades\DB;

final class KitchenCompletionService
{
    public function complete(string $tenantId, string $storeId, string $warehouseId, string $ticketId): void
    {
        DB::transaction(function () use ($tenantId, $storeId, $warehouseId, $ticketId): void {
            $ticket = DB::table('kitchen_tickets')
                ->where('id', $ticketId)
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->first();

            if (!$ticket) throw new \RuntimeException('Kitchen ticket not found.');
            if ($ticket->status === 'completed') return;
            if ($ticket->status !== 'preparing') throw new \RuntimeException('Ticket must be preparing before completion.');

            DB::table('kitchen_tickets')->where('id', $ticketId)->update([
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            app(OrderCompletionService::class)->complete($tenantId, $storeId, $warehouseId, (string) $ticket->order_id);
        });
    }
}
