<?php

declare(strict_types=1);

namespace App\Core\Inventory\Services;

use App\Core\Inventory\Contracts\InventoryLedger;
use App\Core\Inventory\Contracts\RecipeResolver;
use App\Core\Orders\Listeners\InventoryDeductionGuard;
use Illuminate\Support\Facades\DB;

final class OrderInventoryDeductionService
{
    public function __construct(
        private readonly RecipeResolver $recipeResolver,
        private readonly InventoryLedger $ledger,
        private readonly InventoryDeductionGuard $guard,
    ) {}

    public function deduct(int|string $orderId): void
    {
        DB::transaction(function () use ($orderId): void {
            if ($this->guard->alreadyDeducted($orderId)) return;

            $requirements = $this->recipeResolver->requirementsForOrder($orderId);
            if ($requirements === []) {
                throw new \RuntimeException('No inventory requirements resolved for order: ' . $orderId);
            }

            $this->ledger->deductForOrder($orderId, $requirements);
            $this->guard->markDeducted($orderId);
        });
    }
}
