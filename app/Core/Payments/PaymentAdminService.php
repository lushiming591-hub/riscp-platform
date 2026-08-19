<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentAdminService
{
    public function providers(?string $status = null): array
    {
        $q = DB::table('payment_providers')->orderBy('name');
        if ($status !== null) $q->where('status', $status);
        return $q->get()->map(fn ($r) => (array) $r)->all();
    }

    public function accounts(string $tenantId, ?string $storeId = null): array
    {
        $q = DB::table('payment_accounts as a')->join('payment_providers as p', 'p.id', '=', 'a.provider_id')
            ->where('a.tenant_id', $tenantId)->select('a.*', 'p.code as provider_code', 'p.name as provider_name')->orderBy('p.name');
        if ($storeId !== null) $q->where('a.store_id', $storeId);
        return $q->get()->map(fn ($r) => (array) $r)->all();
    }

    public function setRoute(string $tenantId, string $storeId, string $paymentMethod, string $channelId, string $operatorId, ?string $reason = null): void
    {
        DB::transaction(function () use ($tenantId, $storeId, $paymentMethod, $channelId, $operatorId, $reason): void {
            $channel = DB::table('payment_channels as c')->join('payment_accounts as a', 'a.id', '=', 'c.payment_account_id')
                ->where('c.id', $channelId)->where('a.tenant_id', $tenantId)->where('a.store_id', $storeId)
                ->where('c.status', 'active')->first();
            if (!$channel) throw new \RuntimeException('Payment channel is not active for this store.');

            DB::table('payment_route_settings')->where('tenant_id', $tenantId)->where('store_id', $storeId)
                ->where('payment_method', $paymentMethod)->update(['enabled' => false, 'updated_at' => now()]);

            DB::table('payment_route_settings')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'store_id' => $storeId,
                'payment_method' => $paymentMethod, 'channel_id' => $channelId, 'enabled' => true,
                'effective_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('payment_route_audits')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'store_id' => $storeId,
                'payment_method' => $paymentMethod, 'channel_id' => $channelId,
                'operator_id' => $operatorId, 'reason' => $reason, 'created_at' => now(),
            ]);
        });
    }
}
