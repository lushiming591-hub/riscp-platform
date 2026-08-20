<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

use RuntimeException;

final class AllinpayReconciliationFileParser
{
    /**
     * Parse the standard Allinpay merchant reconciliation text format.
     * Amounts are represented in fen in the source file.
     *
     * @return list<array{provider_trade_no:string,merchant_trade_no:?string,amount:float,trade_time:?string,raw:array<int,string>}>
     */
    public function parse(string $contents): array
    {
        $rows = [];

        foreach (preg_split('/\R/u', trim($contents)) ?: [] as $lineNumber => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $fields = array_map('trim', explode('|', $line));
            if (($fields[0] ?? '') === 'PDSMK') {
                continue;
            }
            if (count($fields) < 6) {
                throw new RuntimeException('Invalid Allinpay reconciliation row at line '.($lineNumber + 1).'.');
            }

            $providerTradeNo = (string) ($fields[0] ?? '');
            $amountFen = (string) ($fields[2] ?? '');
            if ($providerTradeNo === '' || !is_numeric($amountFen)) {
                throw new RuntimeException('Invalid Allinpay reconciliation row at line '.($lineNumber + 1).'.');
            }

            $rows[] = [
                'provider_trade_no' => $providerTradeNo,
                'merchant_trade_no' => (($fields[5] ?? '') !== '') ? $fields[5] : null,
                'amount' => ((float) $amountFen) / 100,
                'trade_time' => (($fields[4] ?? '') !== '') ? $fields[4] : null,
                'raw' => $fields,
            ];
        }

        return $rows;
    }
}
