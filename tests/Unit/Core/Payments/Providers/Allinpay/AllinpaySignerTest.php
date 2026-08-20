<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Payments\Providers\Allinpay;

use App\Core\Payments\Providers\Allinpay\AllinpaySigner;
use PHPUnit\Framework\TestCase;

final class AllinpaySignerTest extends TestCase
{
    public function testCanonicalizeSortsFieldsAndExcludesEmptyAndSignatureFields(): void
    {
        $signer = new AllinpaySigner('not-used');

        self::assertSame(
            'appid=00008102&orgid=990701059986000&reqsn=ORDER-001&trxamt=100',
            $signer->canonicalize([
                'trxamt' => 100,
                'sign' => 'ignored',
                'orgid' => '990701059986000',
                'empty' => '',
                'appid' => '00008102',
                'reqsn' => 'ORDER-001',
                'signature' => 'ignored',
                'optional' => null,
            ])
        );
    }

    public function testSignatureCanBeVerifiedWithMatchingRsaKeys(): void
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 1024,
        ]);
        self::assertNotFalse($key);

        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        $publicKey = $details['key'];

        $signer = new AllinpaySigner($privateKey, $publicKey);
        $params = [
            'reqsn' => 'ORDER-001',
            'trxamt' => '100',
            'appid' => '00008102',
        ];

        $signature = $signer->sign($params);

        self::assertNotSame('', $signature);
        self::assertTrue($signer->verify($params, $signature));
        self::assertFalse($signer->verify([...$params, 'trxamt' => '101'], $signature));
    }
}
