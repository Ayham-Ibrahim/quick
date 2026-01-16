<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * تبسيط الحالات إلى 4 فقط: pending, shipping, delivered, cancelled
 */
return new class extends Migration
{
    public function up(): void
    {
        // تحويل الحالات القديمة للنظام المبسط
        DB::table('orders')->where('status', 'shipped')->update(['status' => 'shipping']);
        DB::table('custom_orders')->whereIn('status', ['draft', 'pending_driver', 'confirmed', 'in_progress'])
            ->update(['status' => 'pending']);
    }

    public function down(): void
    {
        // لا يمكن التراجع - الحالات القديمة غير مدعومة
    }
};
