<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Photo directory base URLs per LG
        Schema::table('local_governments', function (Blueprint $table) {
            $table->json('photo_dir')->nullable()->after('map_config');
        });

        // Photo reference number on each household
        Schema::table('households', function (Blueprint $table) {
            $table->string('photo_number', 60)->nullable()->after('gps_accuracy');
        });

        // Photo reference number on each institution
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('photo_number', 60)->nullable()->after('gps_accuracy');
        });
    }

    public function down(): void
    {
        Schema::table('local_governments', function (Blueprint $table) {
            $table->dropColumn('photo_dir');
        });
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('photo_number');
        });
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('photo_number');
        });
    }
};
