<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Fix: The old unique constraint on (user_id, status) prevented
     * users from having multiple completed carts. 
     * 
     * Solution: Remove the unique constraint and handle "one active cart per user"
     * in the application logic (CartService already does this).
     */
    public function up(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Schema::table('carts', function (Blueprint $table) {
            // Remove the old problematic unique constraint
            $table->dropUnique(['user_id', 'status']);
            
            // Add simple index for faster queries
            $table->index(['user_id', 'status']);
        });

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->unique(['user_id', 'status']);
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
