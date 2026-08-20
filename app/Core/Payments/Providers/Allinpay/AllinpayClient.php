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
        private readonly ?AllinpayHttpTransport $transport = null,
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

        $body = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $transport = $this->transport ?? new CurlAllinpayHttpTransport();
        $response = $transport->post(
            rtrim($this->baseUrl, '/') . $path,
            $body,
            ['Content-Type: application/x-www-form-urlencoded; charset=UTF-8'],
            min(5, $this->timeout),
            $this->timeout,
        );

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new \RuntimeException('Allinpay HTTP error: ' . $response['status']);
        }

        parse_str($response['body'], $result);
        if (!$result) {
            $result = json_decode($response['body'], true) ?: [];
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
