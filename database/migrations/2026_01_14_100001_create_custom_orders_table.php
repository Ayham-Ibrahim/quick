<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();

            // الحالة: pending, shipping, delivered, cancelled
            $table->string('status')->default('pending');

            // التوصيل
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->string('delivery_address', 500);
            $table->decimal('delivery_lat', 10, 7)->nullable();
            $table->decimal('delivery_lng', 10, 7)->nullable();

            // الجدولة
            $table->boolean('is_immediate')->default(true);
            $table->timestamp('scheduled_at')->nullable();

            // انتظار السائق (5 دقائق)
            $table->timestamp('confirmation_expires_at')->nullable();
            $table->timestamp('driver_assigned_at')->nullable();

            // ملاحظات وإلغاء
            $table->text('notes')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();
            $table->index(['status', 'confirmation_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_orders');
    }
};
