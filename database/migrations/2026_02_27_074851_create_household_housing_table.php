<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_housing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('roof_type', ['rcc', 'tin', 'tile', 'thatch', 'slate', 'other'])->nullable();
            $table->enum('wall_type', ['rcc', 'brick_cement', 'brick_mud', 'stone_cement', 'stone_mud', 'bamboo', 'wood', 'other'])->nullable();
            $table->enum('foundation_type', ['rcc', 'brick_cement', 'stone_cement', 'stone_mud', 'other'])->nullable();
            $table->enum('ownership_type', ['owned', 'rented', 'institutional', 'squatter', 'other'])->nullable();
            $table->decimal('rent_amount', 8, 2)->nullable();
            $table->unsignedTinyInteger('total_rooms')->nullable();
            $table->unsignedTinyInteger('total_floors')->nullable();
            $table->boolean('has_building_permit')->default(false);
            $table->year('construction_year')->nullable();
            $table->enum('house_condition', ['good', 'average', 'poor', 'damaged'])->nullable();
            $table->boolean('is_at_risk')->default(false);
            $table->json('risk_types')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_housing');
    }
};
