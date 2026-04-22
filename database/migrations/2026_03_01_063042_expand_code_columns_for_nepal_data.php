<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Provinces: max code is "Sudurpashchim" = 13 chars
        Schema::table('provinces', function (Blueprint $table) {
            $table->string('code', 20)->change();
        });

        // Districts: max code is "Kavrepalanchok" = 14 chars
        Schema::table('districts', function (Blueprint $table) {
            $table->string('code', 20)->change();
        });

        // Local Governments: max code is 27 chars (e.g. "JitpurSimaraSub-Metrpolitan")
        Schema::table('local_governments', function (Blueprint $table) {
            $table->string('code', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('provinces', function (Blueprint $table) {
            $table->string('code', 10)->change();
        });
        Schema::table('districts', function (Blueprint $table) {
            $table->string('code', 10)->change();
        });
        Schema::table('local_governments', function (Blueprint $table) {
            $table->string('code', 20)->change();
        });
    }
};
