<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $database = DB::getDatabaseName();

        // Alter database default charset/collation
        DB::statement("ALTER DATABASE `{$database}` CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci");

        // Convert all tables to utf8mb4
        $tables = DB::select('SHOW TABLES');
        foreach ($tables as $row) {
            $table = array_values((array) $row)[0];
            DB::statement("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank. Reverting charset changes can be destructive.
    }
};
