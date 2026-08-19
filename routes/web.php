<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => 'RISCP Platform',
    'version' => '1.0.0-dev',
    'status' => 'running',
]));
