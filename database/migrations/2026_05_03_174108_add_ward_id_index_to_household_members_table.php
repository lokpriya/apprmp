<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            $table->unsignedBigInteger('ward_id')->nullable()->after('household_id');
            $table->index('ward_id', 'hm_ward_id_idx');
        });

        // Backfill from parent household
        \DB::statement('UPDATE household_members hm
            JOIN households h ON h.id = hm.household_id
            SET hm.ward_id = h.ward_id');
    }

    public function down(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            $table->dropIndex('hm_ward_id_idx');
            $table->dropColumn('ward_id');
        });
    }
};
