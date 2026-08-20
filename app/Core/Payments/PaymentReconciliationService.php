<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentReconciliationService
{
    /**
     * @param list<array{provider_trade_no:string,merchant_trade_no:?string,amount:float,trade_time:?string,raw:array<int,string>}> $providerRows
     * @return array{reconciliation_id:string,status:string,total:int,matched:int,mismatched:int,missing_local:int}
     */
    public function reconcile(
        string $providerId,
        string $paymentAccountId,
        string $date,
        array $providerRows,
    ): array {
        return DB::transaction(function () use ($providerId, $paymentAccountId, $date, $providerRows): array {
            $reconciliationId = (string) Str::uuid();

            $localRows = DB::table('payment_transactions')
                ->where('provider_id', $providerId)
                ->where('payment_account_id', $paymentAccountId)
                ->whereIn('status', ['paid', 'refunded'])
                ->whereDate('paid_at', $date)
                ->get();

            $localByMerchant = $localRows->keyBy('merchant_trade_no');
            $localByProvider = $localRows->filter(fn ($row) => !empty($row->provider_trade_no))->keyBy('provider_trade_no');

            $matched = 0;
            $mismatched = 0;
            $missingLocal = 0;
            $providerAmount = 0.0;

            foreach ($providerRows as $row) {
                $providerAmount += (float) $row['amount'];
                $local = null;

                if (!empty($row['merchant_trade_no'])) {
                    $local = $localByMerchant->get($row['merchant_trade_no']);
                }
                if (!$local) {
                    $local = $localByProvider->get($row['provider_trade_no']);
                }

                if (!$local) {
                    $missingLocal++;
                    $mismatched++;
                    $this->insertItem($reconciliationId, $providerId, $row, null, 'missing_local');
                    continue;
                }

                $difference = round((float) $local->amount - (float) $row['amount'], 2);
                $status = abs($difference) < 0.01 ? 'matched' : 'amount_mismatch';
                if ($status === 'matched') {
                    $matched++;
                } else {
                    $mismatched++;
                }

                $this->insertItem($reconciliationId, $providerId, $row, $local, $status, $difference);
            }

            $localAmount = (float) $localRows->sum('amount');
            $missingProvider = max(0, $localRows->count() - $matched);
            $mismatched += $missingProvider;

            foreach ($localRows as $local) {
                $seen = !empty($local->merchant_trade_no)
                    ? $localByMerchant->has($local->merchant_trade_no)
                    : false;
                $providerSeen = collect($providerRows)->contains(fn ($row) =>
                    ($local->merchant_trade_no && $row['merchant_trade_no'] === $local->merchant_trade_no)
                    || ($local->provider_trade_no && $row['provider_trade_no'] === $local->provider_trade_no)
                );
                if (!$seen || !$providerSeen) {
                    $exists = DB::table('payment_reconciliation_items')
                        ->where('reconciliation_id', $reconciliationId)
                        ->where('merchant_trade_no', $local->merchant_trade_no)
                        ->exists();
                    if (!$exists) {
                        DB::table('payment_reconciliation_items')->insert([
                            'id' => (string) Str::uuid(),
                            'reconciliation_id' => $reconciliationId,
                            'provider_id' => $providerId,
                            'merchant_trade_no' => $local->merchant_trade_no,
                            'provider_trade_no' => $local->provider_trade_no,
                            'local_amount' => $local->amount,
                            'provider_amount' => null,
                            'difference_amount' => $local->amount,
                            'status' => 'missing_provider',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            $status = $mismatched === 0 ? 'matched' : 'exception';
            DB::table('payment_reconciliations')->insert([
                'id' => $reconciliationId,
                'provider_id' => $providerId,
                'payment_account_id' => $paymentAccountId,
                'reconciliation_date' => $date,
                'transaction_count' => count($providerRows),
                'transaction_amount' => $localAmount,
                'provider_amount' => $providerAmount,
                'difference_amount' => round($localAmount - $providerAmount, 2),
                'status' => $status,
                'summary' => json_encode([
                    'matched' => $matched,
                    'mismatched' => $mismatched,
                    'missing_local' => $missingLocal,
                    'missing_provider' => $missingProvider,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'reconciliation_id' => $reconciliationId,
                'status' => $status,
                'total' => count($providerRows),
                'matched' => $matched,
                'mismatched' => $mismatched,
                'missing_local' => $missingLocal,
            ];
        });
    }

    private function insertItem(string $reconciliationId, string $providerId, array $row, mixed $local, string $status, float $difference = 0): void
    {
        DB::table('payment_reconciliation_items')->insert([
            'id' => (string) Str::uuid(),
            'reconciliation_id' => $reconciliationId,
            'provider_id' => $providerId,
            'merchant_trade_no' => $row['merchant_trade_no'],
            'provider_trade_no' => $row['provider_trade_no'],
            'local_amount' => $local?->amount,
            'provider_amount' => $row['amount'],
            'difference_amount' => $difference,
            'status' => $status,
            'provider_payload' => json_encode($row['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
