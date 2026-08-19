<?php

declare(strict_types=1);

namespace App\Core\Restaurant;

use Illuminate\Http\Request;

final class MenuController
{
    public function index(Request $request)
    {
        $tenantId = (string) $request->query('tenant_id');
        if ($tenantId === '') {
            return response()->json(['message' => 'tenant_id is required'], 422);
        }

        return response()->json([
            'success' => true,
            'data' => app(MenuService::class)->list($tenantId),
        ]);
    }
}
