<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tole coordinator: the person who keeps the श्रमदान passbooks of the families
 * in their tole(s). Gets nothing in the rest of the system — every existing
 * route and query either lists its roles explicitly or fails closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','municipal_admin','ward_admin','ward_reviewer','enumerator','tole_coordinator') NOT NULL DEFAULT 'enumerator'");
    }

    public function down(): void
    {
        // Anyone still holding the role would make the narrower ENUM fail, so
        // they are switched off and parked on the least-privileged role first.
        DB::table('users')->where('role', 'tole_coordinator')->update(['role' => 'enumerator', 'is_active' => false]);
        DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','municipal_admin','ward_admin','ward_reviewer','enumerator') NOT NULL DEFAULT 'enumerator'");
    }
};
