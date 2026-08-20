<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory;

use App\Core\Orders\Listeners\InventoryDeductionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InventoryDeductionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_order_is_marked_once(): void
    {
        $guard = app(InventoryDeductionGuard::class);

        $this->assertFalse($guard->alreadyDeducted(1001));
        $guard->markDeducted(1001);
        $this->assertTrue($guard->alreadyDeducted(1001));

        $guard->markDeducted(1001);
        $this->assertSame(1, DB::table('order_events')
            ->where('event_key', 'inventory-deducted:1001')
            ->count());
    }
}
