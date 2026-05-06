<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','municipal_admin','ward_admin','ward_reviewer','enumerator') NOT NULL DEFAULT 'enumerator'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','municipal_admin','ward_reviewer','enumerator') NOT NULL DEFAULT 'enumerator'");
    }
};
