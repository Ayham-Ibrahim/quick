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
     * This migration normalizes existing legacy statuses and updates the
     * `orders.status` enum to only allow the four simplified values:
     * 'pending', 'shipping', 'delivered', 'cancelled'.
     *
     * Note: we update rows that used legacy values (e.g. 'shipped',
     * 'confirmed', 'processing', 'ready') to their appropriate new values
     * or to 'pending' when ambiguous.
     *
     * @return void
     */
    public function up()
    {
        // Normalize legacy statuses first
        DB::table('orders')->where('status', 'shipped')->update(['status' => 'shipping']);
        DB::table('orders')->whereIn('status', ['confirmed', 'processing', 'ready'])->update(['status' => 'pending']);

        // Now alter enum to the reduced set
        DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM('pending','shipping','delivered','cancelled') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     *
     * Reverts the enum back to the previous wider set of values.
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM('pending','confirmed','processing','ready','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
