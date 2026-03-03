<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة حقل لتتبع إشعار اقتراب السائق
 * 
 * يُستخدم لمنع إرسال الإشعار أكثر من مرة لنفس الطلب
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('driver_approaching_notified_at')->nullable()->after('second_reminder_sent_at');
        });

        Schema::table('custom_orders', function (Blueprint $table) {
            $table->timestamp('driver_approaching_notified_at')->nullable()->after('second_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('driver_approaching_notified_at');
        });

        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropColumn('driver_approaching_notified_at');
        });
    }
};
