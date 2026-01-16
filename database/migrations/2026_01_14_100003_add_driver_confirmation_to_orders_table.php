<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('confirmation_expires_at')->nullable()->after('status');
            $table->boolean('is_immediate_delivery')->default(true)->after('requested_delivery_at');

            $table->index(['status', 'confirmation_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'confirmation_expires_at']);
            $table->dropColumn(['confirmation_expires_at', 'is_immediate_delivery']);
        });
    }
};
