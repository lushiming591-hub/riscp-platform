<?php

declare(strict_types=1);

namespace App\Core\Inventory\Contracts;

interface InventoryLedger
{
    /** @param array<int,array{material_id:int|string,quantity:float,unit:string}> $requirements */
    public function deductForOrder(int|string $orderId, array $requirements): void;
}
