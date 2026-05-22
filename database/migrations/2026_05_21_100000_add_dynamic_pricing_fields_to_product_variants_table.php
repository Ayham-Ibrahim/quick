<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('base_price_usd', 12, 6)->nullable()->after('price');
            $table->boolean('sync_enabled')->default(false)->after('base_price_usd');
            $table->index(['sync_enabled', 'id'], 'product_variants_sync_enabled_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('product_variants_sync_enabled_id_index');
            $table->dropColumn(['base_price_usd', 'sync_enabled']);
        });
    }
};