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
            $merchantTradeNo = (string) ($parsed['merchant_trade_no'] ?? '');
            $providerTradeNo = (string) ($parsed['provider_trade_no'] ?? '');
            if ($merchantTradeNo === '') throw new \RuntimeException('Allinpay callback merchant trade number is missing.');

            $tx = DB::table('payment_transactions as t')->join('payment_providers as p', 'p.id', '=', 't.provider_id')->where('t.merchant_trade_no', $merchantTradeNo)->select('t.*', 'p.code as provider_code')->first();
            if (!$tx) throw new \RuntimeException('Payment transaction not found.');
            if ($tx->provider_code !== 'allinpay') throw new \RuntimeException('Payment transaction provider mismatch.');

            $eventId = hash('sha256', implode('|', ['allinpay', $merchantTradeNo, $providerTradeNo, (string) ($payload['trxstatus'] ?? ''), (string) ($payload['trxamt'] ?? '')]));
            $service = app(PaymentTransactionService::class);

            if (($parsed['status'] ?? null) === 'paid') {
                $service->markPaid($tx->id, $providerTradeNo, $payload, $eventId, 'verified');
            } else {
                $service->recordCallback($tx->id, $payload, $eventId, 'verified');
            }

            return response()->json(['success' => true, 'idempotent' => $tx->status === 'paid' && ($parsed['status'] ?? null) === 'paid']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
