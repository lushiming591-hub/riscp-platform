<?php

declare(strict_types=1);

namespace App\Core\Payments;

use Illuminate\Support\Facades\DB;

final class PaymentQueryService
{
    public function get(string $transactionId): array
    {
        $tx = DB::table('payment_transactions as t')
            ->leftJoin('payment_providers as p', 'p.id', '=', 't.provider_id')
            ->where('t.id', $transactionId)
            ->first(['t.*', 'p.code as provider_code', 'p.name as provider_name']);
        if (!$tx) throw new \RuntimeException('Payment transaction not found.');
        return (array) $tx;
    }
}
