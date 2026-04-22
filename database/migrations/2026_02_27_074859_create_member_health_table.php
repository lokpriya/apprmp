<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_health', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained('household_members')->cascadeOnDelete();
            $table->boolean('has_health_insurance')->default(false);
            $table->enum('delivery_type', ['institutional', 'home', 'not_applicable'])->nullable();
            $table->unsignedTinyInteger('antenatal_checkups')->nullable();
            $table->boolean('child_immunization')->nullable();
            $table->boolean('malnutrition')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_health');
    }
};
