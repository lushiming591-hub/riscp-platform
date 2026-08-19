<?php

declare(strict_types=1);

use App\Core\Orders\PosOrderService;
use App\Core\Restaurant\TableService;
use App\Core\Restaurant\KitchenTicketController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => ['ok' => true, 'service' => 'riscp-platform']);

    Route::post('/restaurant/tables/{tableId}/occupy', function (Request $request, string $tableId) {
        app(TableService::class)->occupy((string) $request->input('tenant_id'), $tableId, (string) $request->input('order_id'));
        return response()->json(['success' => true]);
    });

    Route::post('/restaurant/tables/{tableId}/release', function (Request $request, string $tableId) {
        app(TableService::class)->release((string) $request->input('tenant_id'), $tableId);
        return response()->json(['success' => true]);
    });

    Route::post('/restaurant/orders', function (Request $request) {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid'], 'store_id' => ['required', 'uuid'], 'order_no' => ['required', 'string', 'max:64'],
            'table_id' => ['nullable', 'uuid'], 'items' => ['required', 'array', 'min:1'], 'items.*.sku_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
        ]);
        $orderId = app(PosOrderService::class)->create($data['tenant_id'], $data['store_id'], $data['order_no'], $data['items'], $data['table_id'] ?? null);
        return response()->json(['success' => true, 'order_id' => $orderId], 201);
    });

    Route::post('/restaurant/kitchen-tickets/{ticketId}/start', [KitchenTicketController::class, 'start']);
    Route::post('/restaurant/kitchen-tickets/{ticketId}/complete', [KitchenTicketController::class, 'complete']);
});
