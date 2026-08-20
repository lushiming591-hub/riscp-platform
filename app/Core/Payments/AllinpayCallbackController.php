<?php

declare(strict_types=1);

namespace App\Core\Payments;

use App\Core\Payments\Providers\Allinpay\AllinpayCallbackHandler;
use App\Core\Payments\Providers\Allinpay\AllinpaySigner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AllinpayCallbackController
{
    public function handle(Request $request)
    {
        try {
            $payload = $request->all();
            $privatePath = (string) config('allinpay.private_key_path');
            $publicPath = (string) config('allinpay.public_key_path');
            if ($publicPath === '' || !is_readable($publicPath)) {
                throw new \RuntimeException('Allinpay public key is not configured.');
            }
            $signer = new AllinpaySigner('', (string) file_get_contents($publicPath));
            $parsed = (new AllinpayCallbackHandler($signer))->handle($payload);

            $providerCode = 'allinpay';
            $eventId = hash('sha256', implode('|', [
                $providerCode,
                (string) ($parsed['merchant_trade_no'] ?? ''),
                (string) ($parsed['provider_trade_no'] ?? ''),
                (string) ($payload['trxstatus'] ?? ''),
                (string) ($payload['trxamt'] ?? ''),
            ]));

            $existing = DB::table('payment_callbacks')->where('provider_code', $providerCode)->where('event_id', $eventId)->first();
            if ($existing) return response()->json(['success' => true, 'idempotent' => true]);

            DB::transaction(function () use ($parsed, $payload, $providerCode, $eventId): void {
                $tx = DB::table('payment_transactions')->where('merchant_trade_no', $parsed['merchant_trade_no'] ?? '')->lockForUpdate()->first();
                DB::table('payment_callbacks')->insert([
                    'id' => (string) Str::uuid(),
                    'transaction_id' => $tx->id ?? null,
                    'provider_code' => $providerCode,
                    'event_id' => $eventId,
                    'event_type' => 'payment.result',
                    'provider_trade_no' => $parsed['provider_trade_no'] ?? null,
                    'merchant_trade_no' => $parsed['merchant_trade_no'] ?? null,
                    'signature_status' => 'verified',
                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'received_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($tx && $parsed['status'] === 'paid') {
                    DB::table('payment_transactions')->where('id', $tx->id)->update([
                        'status' => 'paid',
                        'provider_trade_no' => $parsed['provider_trade_no'],
                        'paid_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('orders')->where('id', $tx->order_id)->where('status', 'pending')->update(['status' => 'paid', 'updated_at' => now()]);
                }
            });

            return response()->json(['success' => true, 'idempotent' => false]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
