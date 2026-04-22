<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_agriculture', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('owns_land')->default(false);
            $table->decimal('land_area_ropani', 8, 2)->nullable();
            $table->decimal('irrigated_area_ropani', 8, 2)->nullable();
            $table->json('crops')->nullable();
            $table->json('irrigation_sources')->nullable();
            $table->boolean('uses_chemical_fertilizer')->default(false);
            $table->boolean('uses_organic_fertilizer')->default(false);
            $table->boolean('sells_produce')->default(false);
            $table->decimal('annual_agri_income', 10, 2)->nullable();
            $table->boolean('has_agri_loan')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_agriculture');
    }
};
