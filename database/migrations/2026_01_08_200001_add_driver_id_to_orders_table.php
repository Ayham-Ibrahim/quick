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
        Schema::table('orders', function (Blueprint $table) {
            // السائق المسؤول عن التوصيل (يتم تعيينه بعد إنشاء الطلب)
            $table->foreignId('driver_id')
                ->nullable()
                ->after('user_id')
                ->constrained('drivers')
                ->nullOnDelete();

            // وقت تعيين السائق
            $table->timestamp('driver_assigned_at')->nullable()->after('requested_delivery_at');
            
            // فهرس للبحث السريع
            $table->index('driver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->dropColumn(['driver_id', 'driver_assigned_at']);
        });
    }
};
