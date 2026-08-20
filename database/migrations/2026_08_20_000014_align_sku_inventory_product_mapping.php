<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // The inventory domain uses BIGINT identifiers for inventory_products,
        // while core skus use UUIDs. Align the mapping column without changing
        // the already-created UUID mapping primary key.
        Schema::table('sku_inventory_products', function (Blueprint $table): void {
            $table->unsignedBigInteger('inventory_product_id_tmp')->nullable();
        });

        // Existing rows are intentionally not copied here: the mapping table
        // was introduced without valid inventory-product foreign keys. Keeping
        // the migration data-safe avoids silently assigning the wrong product.
        Schema::table('sku_inventory_products', function (Blueprint $table): void {
            $table->dropColumn('inventory_product_id');
        });

        Schema::table('sku_inventory_products', function (Blueprint $table): void {
            $table->renameColumn('inventory_product_id_tmp', 'inventory_product_id');
        });

        Schema::table('sku_inventory_products', function (Blueprint $table): void {
            $table->foreign('sku_id')->references('id')->on('skus')->cascadeOnDelete();
            $table->foreign('inventory_product_id')->references('id')->on('inventory_products')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sku_inventory_products', function (Blueprint $table): void {
            $table->dropForeign(['sku_id']);
            $table->dropForeign(['inventory_product_id']);
            $table->dropColumn('inventory_product_id');
        });

        Schema::table('sku_inventory_products', function (Blueprint $table): void {
            $table->uuid('inventory_product_id')->nullable();
        });
    }
};
