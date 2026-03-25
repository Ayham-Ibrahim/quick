<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * إضافة حقل is_settled لتتبع الأرباح المسددة من المتاجر
     */
    public function up(): void
    {
        Schema::table('admin_profits', function (Blueprint $table) {
            $table->boolean('is_settled')->default(false)->after('description');
            $table->timestamp('settled_at')->nullable()->after('is_settled');
            
            $table->index('is_settled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_profits', function (Blueprint $table) {
            $table->dropIndex(['is_settled']);
            $table->dropColumn(['is_settled', 'settled_at']);
        });
    }
};
