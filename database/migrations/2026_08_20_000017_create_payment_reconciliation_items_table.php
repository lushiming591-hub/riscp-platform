<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_reconciliation_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('reconciliation_id')->index();
            $table->uuid('provider_id')->index();
            $table->string('merchant_trade_no', 128)->nullable()->index();
            $table->string('provider_trade_no', 128)->nullable()->index();
            $table->decimal('local_amount', 14, 2)->nullable();
            $table->decimal('provider_amount', 14, 2)->nullable();
            $table->decimal('difference_amount', 14, 2)->default(0);
            $table->string('status', 32)->index();
            $table->json('provider_payload')->nullable();
            $table->timestamps();
            $table->unique(['reconciliation_id', 'merchant_trade_no'], 'payment_reconcile_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_items');
    }
};
