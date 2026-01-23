<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Admin profits from drivers and stores
     */
    public function up(): void
    {
        Schema::create('admin_profits', function (Blueprint $table) {
            $table->id();
            
            // Source type: 'driver' or 'store'
            $table->enum('source_type', ['driver', 'store']);
            
            // The driver or store that the profit came from
            $table->unsignedBigInteger('source_id');
            
            // Related order (regular or custom)
            $table->enum('order_type', ['order', 'custom_order']);
            $table->unsignedBigInteger('order_id');
            
            // Profit amount
            $table->decimal('amount', 12, 2);
            
            // Description
            $table->string('description')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['source_type', 'source_id']);
            $table->index(['order_type', 'order_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_profits');
    }
};
