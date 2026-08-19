<?php

declare(strict_types=1);

namespace App\Core\Orders;

use App\Core\Restaurant\KitchenTicketService;
use Illuminate\Support\Facades\DB;

final class PaidOrderFulfillmentService
{
    public function fulfill(string $tenantId, string $storeId, string $warehouseId, string $orderId): string
    {
        return DB::transaction(function () use ($tenantId, $storeId, $warehouseId, $orderId): string {
            $order = DB::table('orders')->where('id', $orderId)->where('tenant_id', $tenantId)->where('store_id', $storeId)->lockForUpdate()->first();
            if (!$order) throw new \RuntimeException('Order not found.');
            if ($order->status !== 'paid') throw new \RuntimeException('Only paid orders can be fulfilled.');

            $ticketId = app(KitchenTicketService::class)->createForOrder($tenantId, $storeId, $orderId);
            app(OrderCompletionService::class)->complete($tenantId, $storeId, $warehouseId, $orderId);
            return $ticketId;
        });
    }
}
