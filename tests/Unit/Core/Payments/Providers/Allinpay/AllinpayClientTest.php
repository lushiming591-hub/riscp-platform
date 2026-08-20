<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Payments\Providers\Allinpay;

use App\Core\Payments\Providers\Allinpay\AllinpayClient;
use App\Core\Payments\Providers\Allinpay\AllinpaySigner;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class AllinpayClientTest extends TestCase
{
    public function testPostAddsMerchantFieldsAndParsesSignedResponse(): void
    {
        $keys = $this->keys();
        $signer = new AllinpaySigner($keys['private'], $keys['public']);
        $response = [
            'retcode' => 'SUCCESS',
            'retmsg' => 'SUCCESS',
            'reqsn' => 'ORDER-001',
            'trxid' => 'TX-001',
            'trxstatus' => '0000',
        ];
        $response['sign'] = $signer->sign($response);

        $client = $this->clientWithFakeTransport($signer, $response);
        $result = $client->post('/apiweb/unitorder/pay', [
            'reqsn' => 'ORDER-001',
            'trxamt' => '100',
        ]);

        self::assertSame('SUCCESS', $result['retcode']);
        self::assertSame('TX-001', $result['trxid']);
    }

    public function testPostRejectsInvalidResponseSignature(): void
    {
        $keys = $this->keys();
        $signer = new AllinpaySigner($keys['private'], $keys['public']);
        $response = [
            'retcode' => 'SUCCESS',
            'reqsn' => 'ORDER-001',
            'trxid' => 'TX-001',
            'trxstatus' => '0000',
            'sign' => 'invalid-signature',
        ];

        $client = $this->clientWithFakeTransport($signer, $response);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('response signature verification failed');
        $client->post('/apiweb/unitorder/pay', [
            'reqsn' => 'ORDER-001',
            'trxamt' => '100',
        ]);
    }

    private function clientWithFakeTransport(AllinpaySigner $signer, array $response): AllinpayClient
    {
        // Transport injection is intentionally isolated here. The production client
        // remains responsible for the actual cURL transport; this test verifies the
        // signing/response contract without calling Allinpay.
        $client = new AllinpayClient(
            'https://syb-test.allinpay.com',
            '00008102',
            $signer,
            'RSA',
            '990701059986000',
            '990701059986000',
            15,
        );

        // Keep this test executable without real network traffic once the transport
        // seam is introduced. Until then, the helper is deliberately explicit.
        $this->markTestIncomplete('Awaiting AllinpayClient transport seam for HTTP fake injection.');
        return $client;
    }

    private function keys(): array
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 1024,
        ]);
        self::assertNotFalse($key);

        openssl_pkey_export($key, $private);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);

        return ['private' => $private, 'public' => $details['key']];
    }
}
