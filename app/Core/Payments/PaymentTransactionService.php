<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentTransactionService
{
    public function createFromOrder(string $tenantId, string $storeId, string $orderId, string $paymentMethod): array
    {
        return DB::transaction(function () use ($tenantId, $storeId, $orderId, $paymentMethod): array {
            $order = DB::table('orders')->where('id', $orderId)->where('tenant_id', $tenantId)->where('store_id', $storeId)->lockForUpdate()->first();
            if (!$order) throw new \RuntimeException('Order not found.');
            if ($order->status !== 'pending') throw new \RuntimeException('Order is not payable.');

            $route = DB::table('payment_route_settings as r')->join('payment_channels as c', 'c.id', '=', 'r.channel_id')
                ->join('payment_accounts as a', 'a.id', '=', 'c.payment_account_id')
                ->join('payment_providers as p', 'p.id', '=', 'a.provider_id')
                ->where('r.tenant_id', $tenantId)->where('r.store_id', $storeId)->where('r.payment_method', $paymentMethod)->where('r.enabled', true)->where('c.status', 'active')->first();
            if (!$route) throw new \RuntimeException('No active manual payment route configured.');

            $id = (string) Str::uuid();
            $tradeNo = 'RISC'.date('YmdHis').strtoupper(Str::random(10));
            DB::table('payment_transactions')->insert([
                'id' => $id, 'tenant_id' => $tenantId, 'store_id' => $storeId, 'order_id' => $orderId,
                'provider_id' => $route->provider_id, 'payment_account_id' => $route->payment_account_id, 'channel_id' => $route->channel_id,
                'merchant_trade_no' => $tradeNo, 'amount' => $order->total_amount, 'status' => 'created',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return ['id' => $id, 'merchant_trade_no' => $tradeNo, 'provider_code' => $route->code, 'amount' => $order->total_amount];
        });
    }

    public function markPaid(string $transactionId, string $providerTradeNo, array $raw = []): void
    {
        DB::transaction(function () use ($transactionId, $providerTradeNo, $raw): void {
            $tx = DB::table('payment_transactions')->where('id', $transactionId)->lockForUpdate()->first();
            if (!$tx || $tx->status === 'paid') return;
            DB::table('payment_transactions')->where('id', $transactionId)->update(['status' => 'paid', 'provider_trade_no' => $providerTradeNo, 'paid_at' => now(), 'updated_at' => now()]);
            DB::table('orders')->where('id', $tx->order_id)->where('status', 'pending')->update(['status' => 'paid', 'updated_at' => now()]);
            DB::table('payment_callbacks')->insert(['id' => (string) Str::uuid(), 'transaction_id' => $transactionId, 'event_type' => 'payment.succeeded', 'payload' => json_encode($raw, JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
        });
    }
}
