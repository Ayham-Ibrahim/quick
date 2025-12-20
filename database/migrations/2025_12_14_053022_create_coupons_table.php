<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();
            $table->enum('type', ['percentage', 'fixed']);

            $table->decimal('discount_amount', 8, 2);
            $table->unsignedInteger('expiration_duration'); // بالأيام
            $table->unsignedInteger('usage_limit');
            $table->unsignedInteger('times_used')->default(0);

            $table->dateTime('start_at')->nullable();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('copons');
    }
};
