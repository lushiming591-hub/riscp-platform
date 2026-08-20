<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Payments\Providers\Allinpay;

use App\Core\Payments\Providers\Allinpay\AllinpayHttpTransport;

final class FakeAllinpayHttpTransport implements AllinpayHttpTransport
{
    /** @var list<array{url:string,body:string,headers:array,connectTimeout:int,timeout:int}> */
    public array $requests = [];
    /** @var list<array{status:int,body:string}> */
    private array $responses;

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function post(string $url, string $body, array $headers = [], int $connectTimeout = 5, int $timeout = 15): array
    {
        $this->requests[] = compact('url', 'body', 'headers', 'connectTimeout', 'timeout');
        return array_shift($this->responses) ?? ['status' => 200, 'body' => ''];
    }
}
