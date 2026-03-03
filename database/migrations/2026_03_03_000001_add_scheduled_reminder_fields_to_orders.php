<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة حقول تذكير الطلب المجدول للسائق
 * 
 * reminder_sent_at: وقت إرسال التذكير الأول (30 دقيقة قبل الموعد)
 * second_reminder_sent_at: وقت إرسال التذكير الثاني (10 دقائق قبل الموعد)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // إضافة للطلبات العادية
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('is_immediate_delivery');
            $table->timestamp('second_reminder_sent_at')->nullable()->after('reminder_sent_at');
        });

        // إضافة للطلبات الخاصة
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('scheduled_at');
            $table->timestamp('second_reminder_sent_at')->nullable()->after('reminder_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['reminder_sent_at', 'second_reminder_sent_at']);
        });

        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropColumn(['reminder_sent_at', 'second_reminder_sent_at']);
        });
    }
};
