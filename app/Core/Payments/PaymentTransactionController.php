<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Http\Request;

final class PaymentTransactionController
{
    public function create(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid'], 'store_id' => ['required', 'uuid'],
            'order_id' => ['required', 'uuid'], 'payment_method' => ['required', 'string', 'max:32'],
        ]);
        try {
            return response()->json(['success' => true, 'data' => app(PaymentTransactionService::class)->createFromOrder($data['tenant_id'], $data['store_id'], $data['order_id'], $data['payment_method'])], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        }
    }

    public function webhook(Request $request, string $transactionId)
    {
        $data = $request->validate(['provider_trade_no' => ['required', 'string', 'max:128']]);
        app(PaymentTransactionService::class)->markPaid($transactionId, $data['provider_trade_no'], $request->all());
        return response()->json(['success' => true]);
    }
}
