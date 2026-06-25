<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tablesWithRememberToken = ['users', 'providers', 'stores'];

        foreach ($tablesWithRememberToken as $table) {
            // تحقق إذا العمود غير موجود قبل الإضافة
            if (!Schema::hasColumn($table, 'token_version')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('token_version')
                      ->default(1)
                      ->after('remember_token');
                });
            }
            DB::table($table)->update(['token_version' => 2]);
        }

        // drivers بدون remember_token
        if (!Schema::hasColumn('drivers', 'token_version')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->unsignedBigInteger('token_version')
                      ->default(1)
                      ->after('updated_at');
            });
        }
        DB::table('drivers')->update(['token_version' => 2]);
    }

    public function down(): void
    {
        foreach (['users', 'providers', 'stores', 'drivers'] as $table) {
            if (Schema::hasColumn($table, 'token_version')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('token_version');
                });
            }
        }
    }
};