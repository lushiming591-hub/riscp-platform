<?php

declare(strict_types=1);

return [
    'base_url' => env('ALLINPAY_BASE_URL', 'https://syb-test.allinpay.com'),
    'orgid' => env('ALLINPAY_ORGID'),
    'cusid' => env('ALLINPAY_CUSID'),
    'appid' => env('ALLINPAY_APPID'),
    'sign_type' => env('ALLINPAY_SIGN_TYPE', 'RSA'),
    'private_key_path' => env('ALLINPAY_PRIVATE_KEY_PATH'),
    'public_key_path' => env('ALLINPAY_PUBLIC_KEY_PATH'),
    'timeout' => (int) env('ALLINPAY_TIMEOUT', 15),
];
