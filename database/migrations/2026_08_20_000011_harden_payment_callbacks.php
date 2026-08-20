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
                $table->uuid('payment_transaction_id')->nullable()->index();
                $table->uuid('provider_id')->nullable()->index();
                $table->string('event_id', 191);
                $table->string('event_type', 64);
                $table->string('provider_trade_no', 128)->nullable()->index();
                $table->string('merchant_trade_no', 128)->nullable()->index();
                $table->string('signature_status', 32);
                $table->json('payload');
                $table->timestamp('received_at');
                $table->timestamps();
                $table->unique(['provider_id', 'event_id'], 'payment_callbacks_provider_event_unique');
            });
            return;
        }

        Schema::table('payment_callbacks', function (Blueprint $table): void {
            if (!Schema::hasColumn('payment_callbacks', 'payment_transaction_id')) {
                $table->uuid('payment_transaction_id')->nullable()->index();
            }
            if (!Schema::hasColumn('payment_callbacks', 'provider_id')) {
                $table->uuid('provider_id')->nullable()->index();
            }
            if (!Schema::hasColumn('payment_callbacks', 'event_id')) {
                $table->string('event_id', 191)->nullable();
            }
            if (!Schema::hasColumn('payment_callbacks', 'provider_trade_no')) {
                $table->string('provider_trade_no', 128)->nullable()->index();
            }
            if (!Schema::hasColumn('payment_callbacks', 'merchant_trade_no')) {
                $table->string('merchant_trade_no', 128)->nullable()->index();
            }
            if (!Schema::hasColumn('payment_callbacks', 'signature_status')) {
                $table->string('signature_status', 32)->default('verified');
            }
            if (!Schema::hasColumn('payment_callbacks', 'received_at')) {
                $table->timestamp('received_at')->nullable();
            }
        });

        // The new provider_id/event_id key is the canonical idempotency key.
        // Existing provider_code/transaction_id columns are intentionally left in
        // place for backward compatibility with already deployed databases.
        try {
            Schema::table('payment_callbacks', function (Blueprint $table): void {
                $table->unique(['provider_id', 'event_id'], 'payment_callbacks_provider_id_event_unique');
            });
        } catch (Throwable) {
            // The index may already exist on a previously migrated database.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_callbacks');
    }
};
