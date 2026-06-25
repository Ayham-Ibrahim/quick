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
        $tables = ['users', 'providers', 'stores', 'drivers'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('token_version')
                      ->default(1)
                      ->after('remember_token');
            });

            // كل المستخدمين الحاليين يبدأون بـ version = 2
            // حتى يختلف عن الـ default في الـ app
            DB::table($table)->update(['token_version' => 2]);
        }
    }

    public function down(): void
    {
        foreach (['users', 'providers', 'stores', 'drivers'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('token_version');
            });
        }
    }
};
