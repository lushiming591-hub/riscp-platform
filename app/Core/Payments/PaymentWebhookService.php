<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Support\Facades\DB;

final class PaymentWebhookService
{
    public function handle(string $tenantId, string $orderId, string $paymentId, string $providerReference, string $status): void
    {
        if (!in_array($status, ['paid', 'failed'], true)) {
            throw new \InvalidArgumentException('Unsupported payment status.');
        }

        DB::transaction(function () use ($tenantId, $orderId, $paymentId, $providerReference, $status): void {
            $order = DB::table('orders')->where('id', $orderId)->where('tenant_id', $tenantId)->lockForUpdate()->first();
            if (!$order) throw new \RuntimeException('Order not found.');

            $payment = DB::table('payments')->where('id', $paymentId)->where('tenant_id', $tenantId)->lockForUpdate()->first();
            if ($payment && $payment->status === 'paid') return;

            if ($payment) {
                DB::table('payments')->where('id', $paymentId)->update(['status' => $status, 'provider_reference' => $providerReference, 'paid_at' => $status === 'paid' ? now() : null, 'updated_at' => now()]);
            } else {
                DB::table('payments')->insert(['id' => $paymentId, 'tenant_id' => $tenantId, 'order_id' => $orderId, 'provider_reference' => $providerReference, 'status' => $status, 'amount' => $order->total_amount, 'paid_at' => $status === 'paid' ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
            }

            DB::table('orders')->where('id', $orderId)->update(['status' => $status === 'paid' ? 'paid' : 'pending', 'updated_at' => now()]);
        });
    }
}
