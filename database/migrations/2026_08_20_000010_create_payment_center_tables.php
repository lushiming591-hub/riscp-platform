<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->string('provider_type', 32)->default('licensed_payment');
            $table->string('status', 20)->default('inactive')->index();
            $table->json('capabilities')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id')->index();
            $table->uuid('tenant_id')->index();
            $table->uuid('store_id')->nullable()->index();
            $table->string('merchant_no', 128);
            $table->string('terminal_no', 128)->nullable();
            $table->text('credential_ref')->nullable();
            $table->string('status', 20)->default('inactive')->index();
            $table->timestamps();
            $table->unique(['provider_id', 'tenant_id', 'store_id', 'merchant_no'], 'payment_account_unique');
        });

        Schema::create('payment_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id')->index();
            $table->uuid('payment_account_id')->index();
            $table->string('channel_code', 64);
            $table->string('channel_name', 120);
            $table->string('payment_method', 32)->index();
            $table->decimal('fee_rate', 8, 5)->default(0);
            $table->decimal('fixed_fee', 12, 2)->default(0);
            $table->string('status', 20)->default('inactive')->index();
            $table->timestamps();
            $table->unique(['payment_account_id', 'channel_code'], 'payment_channel_unique');
        });

        Schema::create('payment_route_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('store_id')->nullable()->index();
            $table->string('payment_method', 32)->index();
            $table->uuid('provider_id')->index();
            $table->uuid('channel_id')->index();
            $table->boolean('enabled')->default(true);
            $table->timestamp('effective_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'store_id', 'payment_method'], 'payment_route_unique');
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('store_id')->index();
            $table->uuid('order_id')->index();
            $table->uuid('provider_id')->index();
            $table->uuid('payment_account_id')->index();
            $table->uuid('channel_id')->index();
            $table->string('merchant_trade_no', 128)->unique();
            $table->string('provider_trade_no', 128)->nullable()->index();
            $table->decimal('amount', 14, 2);
            $table->decimal('fee_amount', 14, 2)->default(0);
            $table->string('status', 24)->default('created')->index();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_callbacks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id')->index();
            $table->uuid('payment_transaction_id')->nullable()->index();
            $table->string('event_id', 160)->nullable()->index();
            $table->string('event_type', 64)->nullable();
            $table->string('signature_status', 20)->default('unknown');
            $table->json('payload');
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();
            $table->unique(['provider_id', 'event_id'], 'payment_callback_event_unique');
        });

        Schema::create('payment_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id')->index();
            $table->uuid('payment_account_id')->index();
            $table->date('reconciliation_date')->index();
            $table->integer('transaction_count')->default(0);
            $table->decimal('transaction_amount', 16, 2)->default(0);
            $table->decimal('provider_amount', 16, 2)->default(0);
            $table->decimal('difference_amount', 16, 2)->default(0);
            $table->string('status', 24)->default('pending')->index();
            $table->json('summary')->nullable();
            $table->timestamps();
            $table->unique(['payment_account_id', 'reconciliation_date'], 'payment_reconciliation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliations');
        Schema::dropIfExists('payment_callbacks');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_route_settings');
        Schema::dropIfExists('payment_channels');
        Schema::dropIfExists('payment_accounts');
        Schema::dropIfExists('payment_providers');
    }
};
