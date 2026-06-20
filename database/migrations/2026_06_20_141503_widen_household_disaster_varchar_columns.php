<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_disaster', function (Blueprint $table) {
            // Were VARCHAR(10) — too short for full Nepali text from Kobo sync
            $table->string('risk_reduction_plan', 500)->nullable()->change();
            $table->string('affected_last_3years', 500)->nullable()->change();
            $table->string('has_death_this_year',  100)->nullable()->change();
            $table->string('received_relief',       100)->nullable()->change();
            $table->string('nearest_relief_center', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('household_disaster', function (Blueprint $table) {
            $table->string('risk_reduction_plan',   10)->nullable()->change();
            $table->string('affected_last_3years',  10)->nullable()->change();
            $table->string('has_death_this_year',   10)->nullable()->change();
            $table->string('received_relief',       10)->nullable()->change();
            $table->string('nearest_relief_center', 200)->nullable()->change();
        });
    }
};
