<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_infrastructure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('building_type', ['rcc', 'brick_cement', 'prefab', 'temporary', 'other'])->nullable();
            $table->enum('building_condition', ['good', 'average', 'poor', 'damaged', 'under_construction'])->nullable();
            $table->unsignedSmallInteger('total_rooms')->nullable();
            $table->unsignedTinyInteger('total_floors')->nullable();
            $table->decimal('land_area_sqft', 10, 2)->nullable();
            $table->boolean('has_boundary_wall')->default(false);
            $table->unsignedTinyInteger('toilet_count')->nullable();
            $table->boolean('female_toilet')->default(false);
            $table->boolean('male_toilet')->default(false);
            $table->boolean('disabled_toilet')->default(false);
            $table->boolean('accessible_ramp')->default(false);
            $table->boolean('drinking_water')->default(false);
            $table->boolean('electricity')->default(false);
            $table->boolean('solar')->default(false);
            $table->boolean('generator')->default(false);
            $table->boolean('internet')->default(false);
            $table->boolean('fire_extinguisher')->default(false);
            $table->boolean('earthquake_safe')->default(false);
            $table->boolean('evacuation_plan')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_infrastructure');
    }
};
