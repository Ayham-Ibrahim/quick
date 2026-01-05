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
        Schema::create('sub_category_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_category_id')
                ->constrained('sub_categories')
                ->cascadeOnDelete();
            $table->foreignId('attribute_id')
                ->constrained('attributes')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sub_category_id', 'attribute_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_category_attributes');
    }
};
