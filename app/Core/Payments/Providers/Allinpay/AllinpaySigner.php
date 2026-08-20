<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

final class AllinpaySigner
{
    public function __construct(private readonly string $privateKeyPem, private readonly ?string $publicKeyPem = null) {}

    public function sign(array $params): string
    {
        $data = $this->canonicalize($params);
        $key = openssl_pkey_get_private($this->privateKeyPem);
        if ($key === false) throw new \RuntimeException('Invalid Allinpay private key.');
        if (!openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) throw new \RuntimeException('Allinpay signing failed.');
        return base64_encode($signature);
    }

    public function verify(array $params, string $signature): bool
    {
        if (!$this->publicKeyPem) throw new \RuntimeException('Allinpay public key is not configured.');
        $key = openssl_pkey_get_public($this->publicKeyPem);
        if ($key === false) throw new \RuntimeException('Invalid Allinpay public key.');
        return openssl_verify($this->canonicalize($params), base64_decode($signature, true), $key, OPENSSL_ALGO_SHA256) === 1;
    }

    public function canonicalize(array $params): string
    {
        unset($params['sign'], $params['signature']);
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '' || is_array($value)) continue;
            $pairs[] = $key . '=' . $value;
        }
        return implode('&', $pairs);
    }
}
