<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_ledger', function (Blueprint $table): void {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['order_item_id']);
        });

        Schema::table('inventory_ledger', function (Blueprint $table): void {
            $table->uuid('order_id')->nullable()->change();
            $table->uuid('order_item_id')->nullable()->change();
            $table->index('order_id');
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_ledger', function (Blueprint $table): void {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['order_item_id']);
        });

        Schema::table('inventory_ledger', function (Blueprint $table): void {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->unsignedBigInteger('order_item_id')->nullable()->change();
            $table->index('order_id');
            $table->index('order_item_id');
        });
    }
};
