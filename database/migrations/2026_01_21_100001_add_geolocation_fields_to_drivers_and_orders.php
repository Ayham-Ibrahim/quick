<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة حقول الموقع الجغرافي للسائقين والطلبات
 * 
 * السائق: موقعه الحالي + حالة الاتصال
 * الطلب: إحداثيات التوصيل لحساب المسافة
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // إضافة حقول الموقع الحالي للسائق
        Schema::table('drivers', function (Blueprint $table) {
            // الموقع الحالي للسائق (يتم تحديثه من التطبيق)
            $table->decimal('current_lat', 10, 7)->nullable()->after('h_location');
            $table->decimal('current_lng', 10, 7)->nullable()->after('current_lat');
            
            // آخر تحديث للموقع
            $table->timestamp('last_location_update')->nullable()->after('current_lng');
            
            // حالة الاتصال (متصل/غير متصل)
            $table->boolean('is_online')->default(false)->after('last_location_update');
            
            // آخر نشاط في التطبيق
            $table->timestamp('last_activity_at')->nullable()->after('is_online');
            
            // فهارس للبحث السريع
            $table->index(['is_online', 'is_active']);
            $table->index(['current_lat', 'current_lng']);
        });

        // إضافة إحداثيات التوصيل للطلب العادي
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('delivery_lat', 10, 7)->nullable()->after('delivery_address');
            $table->decimal('delivery_lng', 10, 7)->nullable()->after('delivery_lat');
            
            // فهرس للبحث الجغرافي
            $table->index(['delivery_lat', 'delivery_lng']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropIndex(['is_online', 'is_active']);
            $table->dropIndex(['current_lat', 'current_lng']);
            $table->dropColumn([
                'current_lat',
                'current_lng',
                'last_location_update',
                'is_online',
                'last_activity_at',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['delivery_lat', 'delivery_lng']);
            $table->dropColumn(['delivery_lat', 'delivery_lng']);
        });
    }
};
