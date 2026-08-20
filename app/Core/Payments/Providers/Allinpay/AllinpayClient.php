<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

final class AllinpayClient
{
    public function __construct(private readonly string $baseUrl, private readonly string $appId, private readonly string $signType = 'RSA') {}

    public function post(string $path, array $params): array
    {
        $params['appid'] = $params['appid'] ?? $this->appId;
        $params['version'] = $params['version'] ?? '11';
        $params['signtype'] = $params['signtype'] ?? $this->signType;
        $params['randomstr'] = $params['randomstr'] ?? bin2hex(random_bytes(16));

        $ch = curl_init(rtrim($this->baseUrl, '/') . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $body = curl_exec($ch);
        if ($body === false) throw new \RuntimeException('Allinpay request failed: ' . curl_error($ch));
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode < 200 || $httpCode >= 300) throw new \RuntimeException('Allinpay HTTP error: ' . $httpCode);

        parse_str($body, $result);
        if (!$result) $result = json_decode($body, true) ?: [];
        return $result;
    }
}
