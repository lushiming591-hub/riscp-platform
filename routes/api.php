<?php

declare(strict_types=1);

use App\Core\Restaurant\TableService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => ['ok' => true, 'service' => 'riscp-platform']);

    Route::post('/restaurant/tables/{tableId}/occupy', function (Request $request, string $tableId) {
        app(TableService::class)->occupy(
            (string) $request->input('tenant_id'),
            $tableId,
            (string) $request->input('order_id'),
        );
        return response()->json(['success' => true]);
    });

    Route::post('/restaurant/tables/{tableId}/release', function (Request $request, string $tableId) {
        app(TableService::class)->release((string) $request->input('tenant_id'), $tableId);
        return response()->json(['success' => true]);
    });
});
