<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('store_id')->nullable()->constrained('stores');
            $table->string('name');
            $table->string('type')->default('store');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('warehouse_id')->constrained('warehouses');
            $table->foreignUuid('sku_id')->constrained('skus');
            $table->decimal('on_hand', 18, 4)->default(0);
            $table->decimal('reserved', 18, 4)->default(0);
            $table->decimal('available', 18, 4)->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'sku_id']);
        });

        Schema::create('stock_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('warehouse_id')->constrained('warehouses');
            $table->foreignUuid('sku_id')->constrained('skus');
            $table->string('type');
            $table->decimal('quantity', 18, 4);
            $table->decimal('before_quantity', 18, 4);
            $table->decimal('after_quantity', 18, 4);
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'sku_id', 'created_at']);
            $table->unique(['tenant_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('warehouses');
    }
};
