<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sku_inventory_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sku_id');
            $table->uuid('inventory_product_id');
            $table->decimal('quantity_multiplier', 12, 4)->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['sku_id', 'inventory_product_id']);
            $table->index(['sku_id', 'active']);
            $table->index(['inventory_product_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_inventory_products');
    }
};
