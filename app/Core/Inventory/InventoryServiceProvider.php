<?php

declare(strict_types=1);

namespace App\Core\Inventory;

use App\Core\Inventory\Contracts\InventoryLedger;
use App\Core\Inventory\Contracts\RecipeResolver;
use App\Core\Inventory\Services\DatabaseInventoryLedger;
use App\Core\Inventory\Services\DatabaseRecipeResolver;
use Illuminate\Support\ServiceProvider;

final class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RecipeResolver::class, DatabaseRecipeResolver::class);
        $this->app->singleton(InventoryLedger::class, DatabaseInventoryLedger::class);
    }
}
