<?php

declare(strict_types=1);

namespace App\Core\Inventory\Contracts;

interface RecipeResolver
{
    /** @return array<int,array{material_id:int|string,quantity:float,unit:string}> */
    public function requirementsForOrder(int|string $orderId): array;
}
