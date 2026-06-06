<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('household_agriculture', function (Blueprint $table) {
            $table->string('irrigation_source', 150)->nullable()->after('irrigated_area_ropani');
        });

        Schema::table('household_livestock', function (Blueprint $table) {
            $table->string('breed', 100)->nullable()->after('count');
            $table->string('rearing_method', 100)->nullable()->after('breed');
            $table->decimal('annual_production', 10, 2)->nullable()->after('rearing_method');
            $table->string('production_unit', 50)->nullable()->after('annual_production');
        });
    }

    public function down(): void
    {
        Schema::table('household_agriculture', function (Blueprint $table) {
            $table->dropColumn('irrigation_source');
        });

        Schema::table('household_livestock', function (Blueprint $table) {
            $table->dropColumn(['breed', 'rearing_method', 'annual_production', 'production_unit']);
        });
    }
};
