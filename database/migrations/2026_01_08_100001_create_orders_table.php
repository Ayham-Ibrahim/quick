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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // المستخدم صاحب الطلب
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // الكوبون المستخدم (اختياري)
            $table->foreignId('coupon_id')
                ->nullable()
                ->constrained('coupons')
                ->nullOnDelete();

            // نحفظ كود الكوبون للتاريخ (في حال حذف الكوبون لاحقاً)
            $table->string('coupon_code')->nullable();

            // المبالغ
            $table->decimal('subtotal', 12, 2);              // المجموع قبل الخصم
            $table->decimal('discount_amount', 12, 2)->default(0); // قيمة الخصم
            $table->decimal('delivery_fee', 12, 2)->default(0);    // رسوم التوصيل
            $table->decimal('total', 12, 2);                 // المجموع النهائي

            // حالة الطلب
            $table->enum('status', [
                'pending',      // بانتظار التأكيد
                'confirmed',    // تم التأكيد
                'processing',   // قيد التحضير
                'ready',        // جاهز للاستلام/التوصيل
                'shipped',      // تم الشحن
                'delivered',    // تم التوصيل
                'cancelled',    // ملغي
            ])->default('pending');

            // عنوان التوصيل (نص بسيط حالياً - سيتم تطويره مستقبلاً)
            $table->string('delivery_address', 500);

            // موعد التوصيل المطلوب
            $table->timestamp('requested_delivery_at')->nullable();

            // ملاحظات
            $table->text('notes')->nullable();

            // سبب الإلغاء
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            // فهارس للبحث السريع
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
