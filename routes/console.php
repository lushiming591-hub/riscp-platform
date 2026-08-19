<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

Artisan::command('riscp:about', function (): void {
    $this->info('RISCP Platform — Restaurant V1 development build');
});
