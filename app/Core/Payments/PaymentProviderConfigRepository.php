<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Support\Facades\DB;

final class PaymentProviderConfigRepository
{
    public function forChannel(string $channelId): PaymentProviderConfig
    {
        $row = DB::table('payment_channels as c')
            ->join('payment_accounts as a', 'a.id', '=', 'c.payment_account_id')
            ->join('payment_providers as p', 'p.id', '=', 'a.provider_id')
            ->where('c.id', $channelId)->where('c.status', 'active')->first();
        if (!$row) throw new \RuntimeException('Payment channel configuration not found.');

        $credentials = json_decode((string) ($row->credentials ?? '{}'), true) ?: [];
        return new PaymentProviderConfig(
            (string) $row->code,
            (string) ($credentials['merchant_no'] ?? $row->merchant_no ?? ''),
            $credentials['app_id'] ?? null,
            $credentials['api_base_url'] ?? null,
            $credentials['certificate_path'] ?? null,
            $credentials['private_key_path'] ?? null,
            $credentials['public_key_path'] ?? null,
        );
    }
}
