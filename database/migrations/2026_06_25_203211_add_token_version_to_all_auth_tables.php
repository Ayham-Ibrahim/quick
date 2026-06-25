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

        foreach (['users', 'providers', 'stores'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('token_version')
                      ->default(1)
                      ->after('remember_token');
            });
            DB::table($table)->update(['token_version' => 2]);
        }

        // drivers ما عنده remember_token — نضيف بعد updated_at
        Schema::table('drivers', function (Blueprint $table) {
            $table->unsignedBigInteger('token_version')
                  ->default(1)
                  ->after('updated_at');
        });
        DB::table('drivers')->update(['token_version' => 2]);
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
