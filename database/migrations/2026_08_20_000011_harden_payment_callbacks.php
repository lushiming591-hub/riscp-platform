<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('payment_callbacks')) {
            Schema::create('payment_callbacks', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('transaction_id')->nullable()->index();
                $table->string('provider_code', 64)->nullable()->index();
                $table->string('event_id', 191);
                $table->string('event_type', 64);
                $table->string('provider_trade_no', 128)->nullable()->index();
                $table->string('merchant_trade_no', 128)->nullable()->index();
                $table->string('signature_status', 32);
                $table->json('payload');
                $table->timestamp('received_at');
                $table->timestamps();
                $table->unique(['provider_code', 'event_id'], 'payment_callbacks_provider_event_unique');
            });
            return;
        }

        Schema::table('payment_callbacks', function (Blueprint $table): void {
            if (!Schema::hasColumn('payment_callbacks', 'provider_code')) $table->string('provider_code', 64)->nullable()->index();
            if (!Schema::hasColumn('payment_callbacks', 'event_id')) $table->string('event_id', 191)->nullable();
            if (!Schema::hasColumn('payment_callbacks', 'provider_trade_no')) $table->string('provider_trade_no', 128)->nullable()->index();
            if (!Schema::hasColumn('payment_callbacks', 'merchant_trade_no')) $table->string('merchant_trade_no', 128)->nullable()->index();
            if (!Schema::hasColumn('payment_callbacks', 'signature_status')) $table->string('signature_status', 32)->default('verified');
            if (!Schema::hasColumn('payment_callbacks', 'received_at')) $table->timestamp('received_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_callbacks');
    }
};
