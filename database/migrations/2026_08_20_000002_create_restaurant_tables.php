<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('store_id')->constrained('stores');
            $table->string('name');
            $table->string('code');
            $table->unsignedInteger('capacity')->default(2);
            $table->string('status')->default('available');
            $table->timestamps();
            $table->unique(['store_id', 'code']);
        });

        Schema::create('ingredients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->string('name');
            $table->string('unit')->default('kg');
            $table->decimal('cost_price', 12, 4)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('recipes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('sku_id')->constrained('skus');
            $table->foreignUuid('ingredient_id')->constrained('ingredients');
            $table->decimal('quantity', 12, 4);
            $table->string('unit');
            $table->timestamps();
            $table->unique(['sku_id', 'ingredient_id']);
        });

        Schema::create('kitchen_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('store_id')->constrained('stores');
            $table->foreignUuid('order_id')->constrained('orders');
            $table->string('status')->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_tickets');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('restaurant_tables');
    }
};
