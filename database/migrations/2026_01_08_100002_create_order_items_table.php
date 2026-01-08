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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            // المتغير (اختياري - في حال المنتج له variants)
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();

            // المتجر (لتسهيل التجميع والفلترة)
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            // الكمية
            $table->integer('quantity');

            // السعر الأصلي للوحدة (من variant أو product)
            $table->decimal('unit_price', 12, 2);

            // الخصم المطبق على هذا العنصر (من الكوبون)
            $table->decimal('discount_amount', 12, 2)->default(0);

            // المجموع للعنصر = (unit_price × quantity) - discount_amount
            $table->decimal('line_total', 12, 2);

            // نحفظ اسم المنتج والتفاصيل للتاريخ
            $table->string('product_name');
            $table->string('variant_details')->nullable(); // مثل: "اللون: أحمر، المقاس: XL"

            $table->timestamps();

            // فهرس للبحث
            $table->index(['order_id', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
