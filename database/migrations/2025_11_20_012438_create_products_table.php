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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id'); 
            $table->string('name');
            $table->text('description'); 
            $table->integer('quantity')->nullable();  
            $table->decimal('current_price', 12, 2)->nullable();  
            $table->decimal('previous_price', 12, 2)->nullable(); 
            $table->boolean('is_accepted')->index();
            $table->unsignedBigInteger('sub_category_id'); 

            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('sub_category_id')->references('id')->on('sub_categories')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
