<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Support\Facades\DB;

final class PaymentStatusService
{
    public function markPaid(string $tenantId, string $orderId, string $paymentId, string $providerReference): void
    {
        DB::transaction(function () use ($tenantId, $orderId, $paymentId, $providerReference): void {
            $order = DB::table('orders')->where('id', $orderId)->where('tenant_id', $tenantId)->lockForUpdate()->first();
            if (!$order) throw new \RuntimeException('Order not found.');
            if (in_array($order->status, ['paid', 'completed'], true)) return;
            if (!in_array($order->status, ['pending', 'confirmed'], true)) throw new \RuntimeException('Order cannot be paid from current status.');

            DB::table('orders')->where('id', $orderId)->update(['status' => 'paid', 'updated_at' => now()]);
            DB::table('payments')->updateOrInsert(
                ['id' => $paymentId, 'tenant_id' => $tenantId],
                ['order_id' => $orderId, 'provider_reference' => $providerReference, 'status' => 'paid', 'paid_at' => now(), 'updated_at' => now(), 'created_at' => now()]
            );
        });
    }
}
