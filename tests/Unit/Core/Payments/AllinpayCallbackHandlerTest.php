<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Payments;

use App\Core\Payments\Providers\Allinpay\AllinpayCallbackHandler;
use App\Core\Payments\Providers\Allinpay\AllinpaySigner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AllinpayCallbackHandlerTest extends TestCase
{
    public function testPaidCallbackIsVerifiedAndNormalized(): void
    {
        [$signer] = $this->signer();
        $handler = new AllinpayCallbackHandler($signer);
        $payload = [
            'reqsn' => 'ORDER-001',
            'trxid' => 'TX-001',
            'trxamt' => '100',
            'fee' => '2',
            'trxstatus' => '0000',
        ];
        $payload['sign'] = $signer->sign($payload);

        $result = $handler->handle($payload);

        self::assertSame('paid', $result['status']);
        self::assertSame('ORDER-001', $result['merchant_trade_no']);
        self::assertSame('TX-001', $result['provider_trade_no']);
        self::assertSame(1.0, $result['amount']);
        self::assertSame(0.02, $result['fee']);
    }

    public function testProcessingCallbackDoesNotBecomePaid(): void
    {
        [$signer] = $this->signer();
        $handler = new AllinpayCallbackHandler($signer);
        $payload = [
            'reqsn' => 'ORDER-001',
            'trxid' => 'TX-001',
            'trxamt' => '100',
            'trxstatus' => '2000',
        ];
        $payload['sign'] = $signer->sign($payload);

        self::assertSame('processing', $handler->handle($payload)['status']);
    }

    public function testMissingSignatureIsRejected(): void
    {
        [$signer] = $this->signer();
        $handler = new AllinpayCallbackHandler($signer);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('signature is missing');
        $handler->handle(['reqsn' => 'ORDER-001', 'trxstatus' => '0000']);
    }

    public function testInvalidSignatureIsRejected(): void
    {
        [$signer] = $this->signer();
        $handler = new AllinpayCallbackHandler($signer);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('signature verification failed');
        $handler->handle([
            'reqsn' => 'ORDER-001',
            'trxid' => 'TX-001',
            'trxstatus' => '0000',
            'sign' => 'invalid',
        ]);
    }

    private function signer(): array
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 1024,
        ]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $private);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        return [new AllinpaySigner($private, $details['key'])];
    }
}
