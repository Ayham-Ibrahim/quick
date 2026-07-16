<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * تقريب الأسعار الموجودة حالياً لأقرب رقم صحيح (بدون كسور عشرية)
     * ليتوافق مع تغيير PRICE_SCALE في DynamicPricingService.
     * إجراء مؤقت بناءً على قرار العميل - قابل للتراجع لاحقاً.
     */
    public function up(): void
    {
        DB::statement('UPDATE products SET current_price = ROUND(current_price) WHERE current_price IS NOT NULL');
        DB::statement('UPDATE products SET previous_price = ROUND(previous_price) WHERE previous_price IS NOT NULL');
        DB::statement('UPDATE product_variants SET price = ROUND(price) WHERE price IS NOT NULL');
        DB::statement('UPDATE cart_items SET unit_price = ROUND(unit_price) WHERE unit_price IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     *
     * لا يمكن استرجاع الكسور العشرية الأصلية بعد التقريب (فقدان بيانات لا رجعة فيه).
     */
    public function down(): void
    {
        //
    }
};
