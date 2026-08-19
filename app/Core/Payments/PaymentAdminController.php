<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Http\Request;

final class PaymentAdminController
{
    public function providers(Request $request)
    {
        return response()->json(['success' => true, 'data' => app(PaymentAdminService::class)->providers($request->query('status'))]);
    }

    public function accounts(Request $request)
    {
        $tenantId = (string) $request->query('tenant_id');
        if ($tenantId === '') return response()->json(['message' => 'tenant_id is required'], 422);
        return response()->json(['success' => true, 'data' => app(PaymentAdminService::class)->accounts($tenantId, $request->query('store_id'))]);
    }

    public function route(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid'], 'store_id' => ['required', 'uuid'],
            'payment_method' => ['required', 'string', 'max:32'], 'channel_id' => ['required', 'uuid'],
            'operator_id' => ['required', 'string', 'max:128'], 'reason' => ['nullable', 'string', 'max:500'],
        ]);
        app(PaymentAdminService::class)->setRoute($data['tenant_id'], $data['store_id'], $data['payment_method'], $data['channel_id'], $data['operator_id'], $data['reason'] ?? null);
        return response()->json(['success' => true]);
    }
}
