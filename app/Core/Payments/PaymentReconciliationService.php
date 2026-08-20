<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentReconciliationService
{
    public function import(string $providerCode, string $businessDate, array $records): array
    {
        $provider = DB::table('payment_providers')->where('code', $providerCode)->first();
        if (!$provider) throw new \RuntimeException('Payment provider not found.');

        $accountIds = DB::table('payment_accounts')->where('provider_id', $provider->id)->pluck('id')->all();
        $accountId = $accountIds[0] ?? null;
        if (!$accountId) throw new \RuntimeException('No payment account configured for provider.');

        $matched = 0; $unmatched = 0; $mismatch = 0; $systemTotal = 0.0; $providerTotal = 0.0;
        foreach ($records as $record) {
            $tradeNo = (string) ($record['merchant_trade_no'] ?? '');
            $providerTradeNo = (string) ($record['provider_trade_no'] ?? '');
            $amount = (float) ($record['amount'] ?? 0);
            $providerTotal += $amount;
            if ($tradeNo === '') { $unmatched++; continue; }
            $tx = DB::table('payment_transactions')->where('merchant_trade_no', $tradeNo)->where('provider_id', $provider->id)->first();
            if (!$tx) { $unmatched++; continue; }
            $systemTotal += (float) $tx->amount;
            $isMatch = abs((float) $tx->amount - $amount) < 0.0001;
            if ($isMatch) $matched++; else $mismatch++;
        }

        $difference = round($systemTotal - $providerTotal, 2);
        DB::table('payment_reconciliations')->updateOrInsert(
            ['payment_account_id' => $accountId, 'reconciliation_date' => $businessDate],
            ['id' => (string) Str::uuid(), 'provider_id' => $provider->id, 'transaction_count' => count($records),
             'transaction_amount' => $systemTotal, 'provider_amount' => $providerTotal, 'difference_amount' => $difference,
             'status' => ($unmatched === 0 && $mismatch === 0 && abs($difference) < 0.0001) ? 'matched' : 'exception',
             'summary' => json_encode(['matched' => $matched, 'unmatched' => $unmatched, 'amount_mismatch' => $mismatch], JSON_UNESCAPED_UNICODE),
             'updated_at' => now(), 'created_at' => now()]
        );

        return ['matched' => $matched, 'unmatched' => $unmatched, 'amount_mismatch' => $mismatch, 'difference_amount' => $difference];
    }
}
