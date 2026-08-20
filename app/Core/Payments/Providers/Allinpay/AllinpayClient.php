<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

final class AllinpayClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $appId,
        private readonly AllinpaySigner $signer,
        private readonly string $signType = 'RSA',
        private readonly string $orgId = '',
        private readonly string $cusId = '',
        private readonly int $timeout = 15,
    ) {}

    public static function fromConfig(): self
    {
        $privatePath = (string) config('allinpay.private_key_path');
        $publicPath = (string) config('allinpay.public_key_path');
        if ($privatePath === '' || !is_readable($privatePath)) {
            throw new \RuntimeException('Allinpay private key is not configured or readable.');
        }
        if ($publicPath === '' || !is_readable($publicPath)) {
            throw new \RuntimeException('Allinpay public key is not configured or readable.');
        }

        return new self(
            (string) config('allinpay.base_url'),
            (string) config('allinpay.appid'),
            new AllinpaySigner(
                (string) file_get_contents($privatePath),
                (string) file_get_contents($publicPath),
            ),
            (string) config('allinpay.sign_type', 'RSA'),
            (string) config('allinpay.orgid'),
            (string) config('allinpay.cusid'),
            (int) config('allinpay.timeout', 15),
        );
    }

    public function signer(): AllinpaySigner
    {
        return $this->signer;
    }

    public function post(string $path, array $params): array
    {
        $params['orgid'] = $params['orgid'] ?? $this->orgId;
        $params['cusid'] = $params['cusid'] ?? $this->cusId;
        $params['appid'] = $params['appid'] ?? $this->appId;
        $params['version'] = $params['version'] ?? '11';
        $params['signtype'] = $params['signtype'] ?? $this->signType;
        $params['randomstr'] = $params['randomstr'] ?? bin2hex(random_bytes(16));
        $params['sign'] = $this->signer->sign($params);

        $ch = curl_init(rtrim($this->baseUrl, '/') . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded; charset=UTF-8'],
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Allinpay request failed: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException('Allinpay HTTP error: ' . $httpCode);
        }

        parse_str($body, $result);
        if (!$result) {
            $result = json_decode($body, true) ?: [];
        }
        if (!$result) {
            throw new \RuntimeException('Allinpay returned an empty response.');
        }

        if (!empty($result['sign'])) {
            $verifyParams = $result;
            unset($verifyParams['sign']);
            if (!$this->signer->verify($verifyParams, (string) $result['sign'])) {
                throw new \RuntimeException('Allinpay response signature verification failed.');
            }
        }

        return $result;
    }
}
