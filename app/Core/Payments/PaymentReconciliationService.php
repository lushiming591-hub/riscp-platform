<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentReconciliationService
{
    public function import(string $providerCode, string $businessDate, array $records): array
    {
        $matched = 0; $unmatched = 0;
        foreach ($records as $record) {
            $tradeNo = (string) ($record['merchant_trade_no'] ?? '');
            $providerTradeNo = (string) ($record['provider_trade_no'] ?? '');
            $amount = (float) ($record['amount'] ?? 0);
            if ($tradeNo === '') { $unmatched++; continue; }
            $tx = DB::table('payment_transactions')->where('merchant_trade_no', $tradeNo)->first();
            if (!$tx) { $unmatched++; continue; }
            DB::table('payment_reconciliation_items')->insert([
                'id' => (string) Str::uuid(), 'transaction_id' => $tx->id, 'provider_code' => $providerCode,
                'business_date' => $businessDate, 'provider_trade_no' => $providerTradeNo, 'provider_amount' => $amount,
                'system_amount' => (float) $tx->amount,
                'status' => abs((float) $tx->amount - $amount) < 0.0001 ? 'matched' : 'amount_mismatch',
                'created_at' => now(),
            ]);
            $matched++;
        }
        return ['matched' => $matched, 'unmatched' => $unmatched];
    }
}
