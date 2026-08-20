<?php

declare(strict_types=1);

namespace App\Core\Payments\Listeners;

use App\Core\Payments\Events\PaymentCompletedEvent;
use Illuminate\Support\Facades\DB;

final class PaymentCompletedListener
{
    public function handle(PaymentCompletedEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            $payment = DB::table('payments')
                ->where('merchant_trade_no', $event->merchantTradeNo)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw new \RuntimeException('Payment not found: ' . $event->merchantTradeNo);
            }

            if (($payment->status ?? null) === 'paid') {
                return;
            }

            DB::table('payments')->where('id', $payment->id)->update([
                'status' => 'paid',
                'paid_at' => now(),
                'updated_at' => now(),
            ]);

            // Order/KDS/Inventory handlers consume this domain event in the application layer.
            DB::table('payment_transactions')
                ->where('merchant_trade_no', $event->merchantTradeNo)
                ->update([
                    'status' => 'paid',
                    'provider_trade_no' => $event->providerTradeNo,
                    'updated_at' => now(),
                ]);
        });
    }
}
