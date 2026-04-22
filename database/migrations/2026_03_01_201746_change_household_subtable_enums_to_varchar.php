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
        Schema::table('household_utilities', function (Blueprint $table) {
            $table->string('water_source', 50)->nullable()->change();
            $table->string('toilet_type', 50)->nullable()->change();
            $table->string('sanitation_type', 50)->nullable()->change();
            $table->string('electricity_source', 50)->nullable()->change();
            $table->string('cooking_fuel', 50)->nullable()->change();
            $table->string('solid_waste_management', 50)->nullable()->change();
            $table->string('internet_type', 50)->nullable()->change();
        });

        Schema::table('household_housing', function (Blueprint $table) {
            $table->string('roof_type', 50)->nullable()->change();
            $table->string('wall_type', 50)->nullable()->change();
            $table->string('foundation_type', 50)->nullable()->change();
            $table->string('ownership_type', 50)->nullable()->change();
            $table->string('house_condition', 50)->nullable()->change();
        });

        Schema::table('household_economic', function (Blueprint $table) {
            $table->string('primary_income_source', 50)->nullable()->change();
            $table->string('annual_income_range', 50)->nullable()->change();
            $table->string('annual_expenditure_range', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Reversing VARCHAR→ENUM is intentionally left as no-op
        // since re-adding ENUM could fail on existing data that doesn't match.
    }
};
