<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_channels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('store_id')->constrained('stores');
            $table->string('code', 64);
            $table->string('name', 128);
            $table->string('provider', 64);
            $table->decimal('fee_rate', 8, 5)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['tenant_id', 'store_id', 'status']);
        });

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('store_id')->constrained('stores');
            $table->foreignUuid('order_id')->constrained('orders');
            $table->foreignUuid('channel_id')->constrained('payment_channels');
            $table->string('provider_reference')->nullable();
            $table->string('status')->default('created');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('fee_amount', 12, 2)->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'order_id', 'status']);
            $table->index(['provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_channels');
    }
};
