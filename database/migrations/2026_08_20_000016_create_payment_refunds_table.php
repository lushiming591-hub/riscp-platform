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
            $table->uuid('payment_transaction_id')->index();
            $table->uuid('provider_id')->index();
            $table->string('refund_no', 128);
            $table->string('provider_refund_no', 128)->nullable()->index();
            $table->decimal('amount', 14, 2);
            $table->string('status', 24)->default('processing')->index();
            $table->string('reason', 255)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider_id', 'refund_no'], 'payment_refund_provider_no_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
