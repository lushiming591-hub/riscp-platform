<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('payment_callbacks')) {
            return;
        }

        Schema::table('payment_callbacks', function (Blueprint $table): void {
            if (Schema::hasColumn('payment_callbacks', 'transaction_id') && !Schema::hasColumn('payment_callbacks', 'payment_transaction_id')) {
                $table->uuid('payment_transaction_id')->nullable()->index();
            }
            if (Schema::hasColumn('payment_callbacks', 'provider_code') && !Schema::hasColumn('payment_callbacks', 'provider_id')) {
                $table->uuid('provider_id')->nullable()->index();
            }
            if (!Schema::hasColumn('payment_callbacks', 'provider_trade_no')) {
                $table->string('provider_trade_no', 128)->nullable()->index();
            }
            if (!Schema::hasColumn('payment_callbacks', 'merchant_trade_no')) {
                $table->string('merchant_trade_no', 128)->nullable()->index();
            }
            if (!Schema::hasColumn('payment_callbacks', 'signature_status')) {
                $table->string('signature_status', 32)->default('unknown');
            }
            if (!Schema::hasColumn('payment_callbacks', 'received_at')) {
                $table->timestamp('received_at')->nullable();
            }
        });

        // Migrate legacy provider_code / transaction_id values when the referenced
        // payment provider/transaction can be resolved. Do this before adding the
        // new unique index so legacy rows remain usable.
        if (Schema::hasColumn('payment_callbacks', 'provider_code') && Schema::hasColumn('payment_callbacks', 'provider_id')) {
            DB::statement(<<<'SQL'
                UPDATE payment_callbacks pc
                INNER JOIN payment_providers pp ON pp.code = pc.provider_code
                SET pc.provider_id = pp.id
                WHERE pc.provider_id IS NULL AND pc.provider_code IS NOT NULL
            SQL);
        }

        if (Schema::hasColumn('payment_callbacks', 'transaction_id') && Schema::hasColumn('payment_callbacks', 'payment_transaction_id')) {
            DB::statement(<<<'SQL'
                UPDATE payment_callbacks
                SET payment_transaction_id = transaction_id
                WHERE payment_transaction_id IS NULL AND transaction_id IS NOT NULL
            SQL);
        }

        // The original migration already defines the desired unique key on fresh
        // databases. This migration only adds the aligned schema for databases that
        // were created from the hardened callback migration first.
        try {
            Schema::table('payment_callbacks', function (Blueprint $table): void {
                $table->unique(['provider_id', 'event_id'], 'payment_callback_provider_event_unique');
            });
        } catch (\Throwable) {
            // Existing deployments may already have an equivalent unique index.
        }
    }

    public function down(): void
    {
        // Keep the migration non-destructive for callback data. The legacy columns
        // are intentionally retained for rollback compatibility.
    }
};
