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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('driver_name');
            $table->string('phone')->unique();
            $table->string('password');
            $table->string('driver_image');
            $table->string('front_id_image');
            $table->string('back_id_image');
            $table->string('city')->nullable();
            $table->string('v_location');
            $table->string('h_location');
            $table->foreignId('vehicle_type_id')
                ->nullable()
                ->constrained('vehicle_types')
                ->nullOnDelete();
            $table->decimal('wallet_balance', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
