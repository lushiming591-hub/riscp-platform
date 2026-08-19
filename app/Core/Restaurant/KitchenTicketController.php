<?php

declare(strict_types=1);

namespace App\Core\Restaurant;

use Illuminate\Http\Request;

final class KitchenTicketController
{
    public function start(Request $request, string $ticketId)
    {
        app(KitchenTicketService::class)->start(
            (string) $request->input('tenant_id'),
            $ticketId,
        );

        return response()->json(['success' => true, 'status' => 'preparing']);
    }

    public function complete(Request $request, string $ticketId)
    {
        app(KitchenCompletionService::class)->complete(
            (string) $request->input('tenant_id'),
            (string) $request->input('store_id'),
            (string) $request->input('warehouse_id'),
            $ticketId,
        );

        return response()->json(['success' => true, 'status' => 'completed']);
    }
}
