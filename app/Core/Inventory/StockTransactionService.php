<?php

declare(strict_types=1);

namespace App\Core\Inventory;

use Illuminate\Support\Facades\DB;

final class StockTransactionService
{
    /**
     * Inventory is changed through an auditable transaction instead of directly
     * decrementing stock from an Order model. A unique business reference should
     * be enforced at database level by the caller.
     */
    public function deduct(
        string $tenantId,
        string $storeId,
        string $skuId,
        string $referenceType,
        string $referenceId,
        int $quantity,
    ): void {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero.');
        }

        DB::transaction(function () use ($tenantId, $storeId, $skuId, $referenceType, $referenceId, $quantity): void {
            // Repository implementation will lock the stock row, validate availability,
            // update balance and append the immutable stock transaction ledger.
            // This service intentionally contains no direct Order-model mutation.
        });
    }
}
