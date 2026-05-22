<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('base_price_usd', 12, 6)->nullable()->after('current_price');
            $table->boolean('sync_enabled')->default(false)->after('base_price_usd');
            $table->index(['sync_enabled', 'id'], 'products_sync_enabled_id_index');
        });

        if (Schema::hasColumn('product_variants', 'sync_enabled')) {
            DB::table('products')
                ->whereIn('id', function ($query) {
                    $query->select('product_id')
                        ->from('product_variants')
                        ->where('sync_enabled', true);
                })
                ->update(['sync_enabled' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_sync_enabled_id_index');
            $table->dropColumn(['base_price_usd', 'sync_enabled']);
        });
    }
};