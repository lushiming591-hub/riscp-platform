<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->string('name')->nullable();
            $table->string('mobile')->nullable();
            $table->unsignedInteger('points')->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'mobile']);
        });

        Schema::create('daily_settlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('store_id')->constrained('stores');
            $table->date('business_date');
            $table->unsignedInteger('order_count')->default(0);
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('refund_amount', 14, 2)->default(0);
            $table->string('status')->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_settlements');
        Schema::dropIfExists('members');
    }
};
