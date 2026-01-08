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
        Schema::table('coupon_usages', function (Blueprint $table) {
            // إضافة order_id لتتبع الطلب الذي استُخدم فيه الكوبون
            $table->foreignId('order_id')
                ->nullable()
                ->after('user_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // فهرس فريد لضمان عدم استخدام نفس الكوبون لنفس الطلب أكثر من مرة
            $table->unique(['coupon_id', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->dropUnique(['coupon_id', 'order_id']);
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');
        });
    }
};
