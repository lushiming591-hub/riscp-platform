<?php

declare(strict_types=1);

namespace App\Core\Payments\Providers\Allinpay;

interface AllinpayHttpTransport
{
    /**
     * @return array{status:int, body:string}
     */
    public function post(string $url, string $body, array $headers = [], int $connectTimeout = 5, int $timeout = 15): array;
}
