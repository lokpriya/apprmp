<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_utilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('water_source', ['tap_inside', 'tap_outside', 'tube_well', 'river', 'well', 'rain', 'other'])->nullable();
            $table->unsignedSmallInteger('water_distance_minutes')->nullable();
            $table->boolean('has_toilet')->default(false);
            $table->enum('toilet_type', ['flush', 'pit', 'open_field', 'other'])->nullable();
            $table->enum('sanitation_type', ['individual', 'shared', 'public', 'none'])->nullable();
            $table->enum('electricity_source', ['national_grid', 'solar', 'generator', 'micro_hydro', 'none'])->nullable();
            $table->unsignedSmallInteger('monthly_electricity_units')->nullable();
            $table->enum('cooking_fuel', ['lpg', 'biogas', 'firewood', 'kerosene', 'electricity', 'other'])->nullable();
            $table->boolean('has_biogas')->default(false);
            $table->enum('solid_waste_management', ['govt_collection', 'pit', 'burning', 'composting', 'dumping', 'other'])->nullable();
            $table->boolean('has_internet')->default(false);
            $table->enum('internet_type', ['broadband', 'mobile_data', 'wifi', 'other'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_utilities');
    }
};
