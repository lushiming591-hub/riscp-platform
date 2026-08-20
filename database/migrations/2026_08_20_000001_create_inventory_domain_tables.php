<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_materials', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('name', 160);
            $table->string('base_unit', 32);
            $table->decimal('conversion_factor', 18, 6)->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('name', 160);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_product_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('inventory_products');
            $table->foreignId('material_id')->constrained('inventory_materials');
            $table->decimal('quantity', 18, 6);
            $table->string('unit', 32);
            $table->timestamps();
            $table->unique(['product_id', 'material_id']);
        });

        Schema::create('inventory_warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 160);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses');
            $table->foreignId('material_id')->constrained('inventory_materials');
            $table->decimal('quantity', 18, 6)->default(0);
            $table->decimal('reserved_quantity', 18, 6)->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'material_id']);
        });

        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses');
            $table->foreignId('material_id')->constrained('inventory_materials');
            $table->string('lot_no', 96);
            $table->decimal('quantity', 18, 6)->default(0);
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['warehouse_id', 'material_id', 'lot_no']);
        });

        Schema::create('inventory_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 160)->unique();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses');
            $table->foreignId('material_id')->constrained('inventory_materials');
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('order_item_id')->nullable()->index();
            $table->decimal('quantity', 18, 6);
            $table->string('unit', 32);
            $table->decimal('before_quantity', 18, 6);
            $table->decimal('after_quantity', 18, 6);
            $table->string('operation_type', 48);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_ledger');
        Schema::dropIfExists('inventory_lots');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('inventory_warehouses');
        Schema::dropIfExists('inventory_product_recipes');
        Schema::dropIfExists('inventory_products');
        Schema::dropIfExists('inventory_materials');
    }
};
