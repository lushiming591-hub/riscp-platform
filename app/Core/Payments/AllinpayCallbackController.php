<?php

declare(strict_types=1);

namespace App\Core\Payments;

use App\Core\Payments\Providers\Allinpay\AllinpayCallbackHandler;
use App\Core\Payments\Providers\Allinpay\AllinpaySigner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AllinpayCallbackController
{
    public function handle(Request $request)
    {
        try {
            $payload = $request->all();
            $publicPath = (string) config('allinpay.public_key_path');
            if ($publicPath === '' || !is_readable($publicPath)) {
                throw new \RuntimeException('Allinpay public key is not configured.');
            }

            $signer = new AllinpaySigner('', (string) file_get_contents($publicPath));
            $parsed = (new AllinpayCallbackHandler($signer))->handle($payload);
            $providerCode = 'allinpay';
            $merchantTradeNo = (string) ($parsed['merchant_trade_no'] ?? '');
            $providerTradeNo = (string) ($parsed['provider_trade_no'] ?? '');
            $eventId = hash('sha256', implode('|', [
                $providerCode, $merchantTradeNo, $providerTradeNo,
                (string) ($payload['trxstatus'] ?? ''), (string) ($payload['trxamt'] ?? ''),
            ]));

            if (DB::table('payment_callbacks')->where('provider_code', $providerCode)->where('event_id', $eventId)->exists()) {
                return response()->json(['success' => true, 'idempotent' => true]);
            }

            $tx = DB::table('payment_transactions')->where('merchant_trade_no', $merchantTradeNo)->first();
            if (!$tx) throw new \RuntimeException('Payment transaction not found.');

            DB::transaction(function () use ($eventId, $providerCode, $merchantTradeNo, $providerTradeNo, $payload, $tx): void {
                DB::table('payment_callbacks')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'transaction_id' => $tx->id,
                    'provider_code' => $providerCode,
                    'event_id' => $eventId,
                    'event_type' => 'payment.result',
                    'provider_trade_no' => $providerTradeNo,
                    'merchant_trade_no' => $merchantTradeNo,
                    'signature_status' => 'verified',
                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'received_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            if (($parsed['status'] ?? null) === 'paid') {
                app(PaymentTransactionService::class)->markPaid($tx->id, $providerTradeNo, $payload);
            }

            return response()->json(['success' => true, 'idempotent' => false]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
