<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('store_id')->index();
            $table->uuid('payment_transaction_id')->index();
            $table->uuid('order_id')->index();
            $table->string('refund_no', 128)->unique();
            $table->string('provider_refund_no', 128)->nullable()->index();
            $table->decimal('refund_amount', 14, 2);
            $table->string('currency', 3)->default('CNY');
            $table->string('status', 24)->default('requested')->index();
            $table->string('reason', 255)->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamps();

            $table->foreign('payment_transaction_id')->references('id')->on('payment_transactions')->restrictOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
        });

        Schema::create('payment_refund_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('refund_id')->index();
            $table->uuid('order_item_id')->index();
            $table->decimal('quantity', 14, 4);
            $table->decimal('refund_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->foreign('refund_id')->references('id')->on('payment_refunds')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->restrictOnDelete();
            $table->unique(['refund_id', 'order_item_id'], 'payment_refund_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refund_items');
        Schema::dropIfExists('payment_refunds');
    }
};
