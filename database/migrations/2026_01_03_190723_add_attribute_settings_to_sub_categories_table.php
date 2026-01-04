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
        Schema::table('sub_categories', function (Blueprint $table) {
            $table->boolean('price_depends_on_attributes')->default(false)->after('image');
            $table->boolean('quantity_depends_on_attributes')->default(false)->after('price_depends_on_attributes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_categories', function (Blueprint $table) {
            $table->dropColumn(['price_depends_on_attributes', 'quantity_depends_on_attributes']);
        });
    }
};
