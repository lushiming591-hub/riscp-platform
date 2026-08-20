<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('payment_callbacks')) {
            return;
        }

        $nullProvider = DB::table('payment_callbacks')->whereNull('provider_id')->count();
        $nullEvent = DB::table('payment_callbacks')->whereNull('event_id')->count();

        if ($nullProvider > 0 || $nullEvent > 0) {
            throw new RuntimeException(
                "Cannot enforce payment callback idempotency key: {$nullProvider} row(s) have NULL provider_id and {$nullEvent} row(s) have NULL event_id. Backfill legacy callback data before migrating."
            );
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable();
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payment_callbacks MODIFY provider_id CHAR(36) NOT NULL');
            DB::statement('ALTER TABLE payment_callbacks MODIFY event_id VARCHAR(191) NOT NULL');
            return;
        }

        Schema::table('payment_callbacks', function (Blueprint $table): void {
            $table->uuid('provider_id')->nullable(false)->change();
            $table->string('event_id', 191)->nullable(false)->change();
        });
    }

    private function rebuildSqliteTable(): void
    {
        Schema::create('payment_callbacks_enforce_tmp', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');
            $table->uuid('payment_transaction_id')->nullable()->index();
            $table->string('event_id', 191);
            $table->string('event_type', 64)->nullable();
            $table->string('provider_trade_no', 128)->nullable()->index();
            $table->string('merchant_trade_no', 128)->nullable()->index();
            $table->string('signature_status', 32)->default('unknown');
            $table->json('payload');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['provider_id', 'event_id'], 'payment_callbacks_provider_event_unique');
        });

        DB::statement('INSERT INTO payment_callbacks_enforce_tmp (id, provider_id, payment_transaction_id, event_id, event_type, provider_trade_no, merchant_trade_no, signature_status, payload, received_at, created_at, updated_at) SELECT id, provider_id, payment_transaction_id, event_id, event_type, provider_trade_no, merchant_trade_no, signature_status, payload, received_at, created_at, updated_at FROM payment_callbacks');
        Schema::drop('payment_callbacks');
        Schema::rename('payment_callbacks_enforce_tmp', 'payment_callbacks');
    }

    public function down(): void
    {
        // Deliberately irreversible: relaxing the idempotency key would weaken
        // the payment callback safety guarantees of an already-migrated system.
    }
};
