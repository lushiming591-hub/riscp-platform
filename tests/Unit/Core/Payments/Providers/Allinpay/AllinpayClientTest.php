<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Payments\Providers\Allinpay;

use App\Core\Payments\Providers\Allinpay\AllinpayClient;
use App\Core\Payments\Providers\Allinpay\AllinpaySigner;
use PHPUnit\Framework\TestCase;
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
        $fake = new FakeAllinpayHttpTransport([
            ['status' => 200, 'body' => http_build_query($response, '', '&', PHP_QUERY_RFC3986)],
        ]);
        $client = $this->client($signer, $fake);

        $result = $client->post('/apiweb/unitorder/pay', [
            'reqsn' => 'ORDER-001',
            'trxamt' => '100',
        ]);

        self::assertSame('SUCCESS', $result['retcode']);
        self::assertSame('TX-001', $result['trxid']);
        self::assertCount(1, $fake->requests);
        self::assertSame('https://fake.test/apiweb/unitorder/pay', $fake->requests[0]['url']);
        self::assertStringContainsString('appid=00008102', $fake->requests[0]['body']);
        self::assertStringContainsString('orgid=990701059986000', $fake->requests[0]['body']);
        self::assertStringContainsString('cusid=990701059986000', $fake->requests[0]['body']);
        self::assertStringContainsString('reqsn=ORDER-001', $fake->requests[0]['body']);
        self::assertStringContainsString('trxamt=100', $fake->requests[0]['body']);
        self::assertStringContainsString('sign=', $fake->requests[0]['body']);
    }

    public function testPostRejectsInvalidResponseSignature(): void
    {
        $keys = $this->keys();
        $signer = new AllinpaySigner($keys['private'], $keys['public']);
        $fake = new FakeAllinpayHttpTransport([
            ['status' => 200, 'body' => http_build_query([
                'retcode' => 'SUCCESS',
                'reqsn' => 'ORDER-001',
                'trxid' => 'TX-001',
                'trxstatus' => '0000',
                'sign' => 'invalid-signature',
            ], '', '&', PHP_QUERY_RFC3986)],
        ]);
        $client = $this->client($signer, $fake);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('response signature verification failed');
        $client->post('/apiweb/unitorder/pay', ['reqsn' => 'ORDER-001', 'trxamt' => '100']);
    }

    public function testPostRejectsHttpError(): void
    {
        $keys = $this->keys();
        $signer = new AllinpaySigner($keys['private'], $keys['public']);
        $fake = new FakeAllinpayHttpTransport([
            ['status' => 500, 'body' => 'server error'],
        ]);
        $client = $this->client($signer, $fake);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Allinpay HTTP error: 500');
        $client->post('/apiweb/unitorder/pay', ['reqsn' => 'ORDER-001', 'trxamt' => '100']);
    }

    public function testPostRejectsEmptyResponse(): void
    {
        $keys = $this->keys();
        $signer = new AllinpaySigner($keys['private'], $keys['public']);
        $fake = new FakeAllinpayHttpTransport([
            ['status' => 200, 'body' => ''],
        ]);
        $client = $this->client($signer, $fake);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('empty response');
        $client->post('/apiweb/unitorder/pay', ['reqsn' => 'ORDER-001', 'trxamt' => '100']);
    }

    private function client(AllinpaySigner $signer, FakeAllinpayHttpTransport $transport): AllinpayClient
    {
        return new AllinpayClient(
            'https://fake.test',
            '00008102',
            $signer,
            'RSA',
            '990701059986000',
            '990701059986000',
            15,
            $transport,
        );
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
