<?php

declare(strict_types=1);

namespace App\Core\Payments;

use App\Core\Payments\Contracts\PaymentProviderContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentRefundService
{
    public function request(
        string $transactionId,
        float $amount,
        string $refundNo,
        PaymentProviderContract $provider,
        ?string $reason = null,
    ): array {
        $refund = $this->createIntent($transactionId, $amount, $refundNo, $reason);

        if ($refund['status'] === 'completed') {
            return $refund;
        }

        try {
            $response = $provider->refund([
                'merchant_trade_no' => $refund['merchant_trade_no'],
                'provider_trade_no' => $refund['provider_trade_no'],
                'refund_trade_no' => $refundNo,
                'amount' => $amount,
                'amount_fen' => (int) round($amount * 100),
                'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            $this->markFailed($refund['id'], $e->getMessage());
            throw $e;
        }

        $this->recordProviderResponse($refund['id'], $response);

        return array_merge($refund, [
            'status' => (string) ($response['status'] ?? 'processing'),
            'provider_refund_no' => $response['provider_trade_no'] ?? $response['refund_trade_no'] ?? null,
            'provider_response' => $response,
        ]);
    }

    /** @return array<string,mixed> */
    public function createIntent(string $transactionId, float $amount, string $refundNo, ?string $reason = null): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Refund amount must be greater than zero.');
        }

        return DB::transaction(function () use ($transactionId, $amount, $refundNo, $reason): array {
            $tx = DB::table('payment_transactions')->where('id', $transactionId)->lockForUpdate()->first();
            if (!$tx) {
                throw new \RuntimeException('Payment transaction not found.');
            }
            if ($tx->status !== 'paid') {
                throw new \RuntimeException('Only paid transactions can be refunded.');
            }
            if (!$tx->provider_id) {
                throw new \RuntimeException('Payment transaction provider is required for refund.');
            }

            $existing = DB::table('payment_refunds as r')
                ->join('payment_transactions as t', 't.id', '=', 'r.payment_transaction_id')
                ->where('r.provider_id', $tx->provider_id)
                ->where('r.refund_no', $refundNo)
                ->select('r.*', 't.merchant_trade_no', 't.provider_trade_no')
                ->first();

            if ($existing) {
                return (array) $existing;
            }

            $refunded = (float) DB::table('payment_refunds')
                ->where('payment_transaction_id', $transactionId)
                ->whereIn('status', ['processing', 'completed'])
                ->sum('amount');

            if ($refunded + $amount > (float) $tx->amount) {
                throw new \RuntimeException('Refund amount exceeds refundable transaction amount.');
            }

            $id = (string) Str::uuid();
            DB::table('payment_refunds')->insert([
                'id' => $id,
                'payment_transaction_id' => $transactionId,
                'provider_id' => $tx->provider_id,
                'refund_no' => $refundNo,
                'amount' => $amount,
                'status' => 'processing',
                'reason' => $reason,
                'request_payload' => json_encode(['amount' => $amount, 'reason' => $reason], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'requested_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'id' => $id,
                'payment_transaction_id' => $transactionId,
                'provider_id' => $tx->provider_id,
                'refund_no' => $refundNo,
                'merchant_trade_no' => $tx->merchant_trade_no,
                'provider_trade_no' => $tx->provider_trade_no,
                'amount' => $amount,
                'status' => 'processing',
            ];
        });
    }

    public function recordCallback(string $refundId, string $status, ?string $providerRefundNo = null, array $raw = []): bool
    {
        return DB::transaction(function () use ($refundId, $status, $providerRefundNo, $raw): bool {
            $refund = DB::table('payment_refunds')->where('id', $refundId)->lockForUpdate()->first();
            if (!$refund) {
                throw new \RuntimeException('Payment refund not found.');
            }
            if ($refund->status === 'completed') {
                return false;
            }

            $normalized = match ($status) {
                'paid', 'completed', 'success' => 'completed',
                'failed', 'cancelled' => 'failed',
                default => 'processing',
            };

            DB::table('payment_refunds')->where('id', $refundId)->update([
                'status' => $normalized,
                'provider_refund_no' => $providerRefundNo,
                'provider_response' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'processed_at' => $normalized === 'completed' ? now() : null,
                'updated_at' => now(),
            ]);

            if ($normalized === 'completed') {
                $this->syncTransactionRefunded((string) $refund->payment_transaction_id);
            }

            return true;
        });
    }

    private function recordProviderResponse(string $refundId, array $response): void
    {
        $providerRefundNo = $response['provider_trade_no'] ?? $response['refund_trade_no'] ?? null;
        $status = (string) ($response['status'] ?? 'processing');

        DB::transaction(function () use ($refundId, $providerRefundNo, $status, $response): void {
            $refund = DB::table('payment_refunds')->where('id', $refundId)->lockForUpdate()->first();
            if (!$refund || $refund->status === 'completed') {
                return;
            }

            $normalized = match ($status) {
                'paid', 'completed', 'success' => 'completed',
                'failed', 'cancelled' => 'failed',
                default => 'processing',
            };

            DB::table('payment_refunds')->where('id', $refundId)->update([
                'status' => $normalized,
                'provider_refund_no' => $providerRefundNo,
                'provider_response' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'processed_at' => $normalized === 'completed' ? now() : null,
                'updated_at' => now(),
            ]);

            if ($normalized === 'completed') {
                $this->syncTransactionRefunded((string) $refund->payment_transaction_id);
            }
        });
    }

    private function markFailed(string $refundId, string $message): void
    {
        DB::table('payment_refunds')->where('id', $refundId)->update([
            'status' => 'failed',
            'provider_response' => json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'processed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function syncTransactionRefunded(string $transactionId): void
    {
        $tx = DB::table('payment_transactions')->where('id', $transactionId)->lockForUpdate()->first();
        if (!$tx) {
            return;
        }

        $completedAmount = (float) DB::table('payment_refunds')
            ->where('payment_transaction_id', $transactionId)
            ->where('status', 'completed')
            ->sum('amount');

        if ($completedAmount >= (float) $tx->amount) {
            DB::table('payment_transactions')->where('id', $transactionId)->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
