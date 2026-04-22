<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_disaster', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('risk_types')->nullable();
            $table->boolean('is_flood_zone')->default(false);
            $table->boolean('is_landslide_zone')->default(false);
            $table->boolean('is_drought_zone')->default(false);
            $table->json('past_events')->nullable();
            $table->boolean('has_evacuation_plan')->default(false);
            $table->string('nearest_relief_center', 200)->nullable();
            $table->boolean('disaster_training')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_disaster');
    }
};
