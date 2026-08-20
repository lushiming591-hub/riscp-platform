<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Payments;

use App\Core\Payments\Providers\Allinpay\AllinpayReconciliationFileParser;
use PHPUnit\Framework\TestCase;

final class AllinpayReconciliationFileParserTest extends TestCase
{
    public function test_parses_standard_allinpay_pipe_delimited_rows_and_converts_fen_to_yuan(): void
    {
        $parser = new AllinpayReconciliationFileParser();
        $rows = $parser->parse("ALI-001|PAY|10000|0|20260820093000|RISC-001||||||||||||\n");

        self::assertCount(1, $rows);
        self::assertSame('ALI-001', $rows[0]['provider_trade_no']);
        self::assertSame('RISC-001', $rows[0]['merchant_trade_no']);
        self::assertSame(100.0, $rows[0]['amount']);
        self::assertSame('20260820093000', $rows[0]['trade_time']);
    }

    public function test_rejects_malformed_rows(): void
    {
        $this->expectException(\RuntimeException::class);
        (new AllinpayReconciliationFileParser())->parse('bad');
    }
}
