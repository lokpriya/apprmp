<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_health', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('bed_count')->nullable();
            $table->boolean('delivery_service')->default(false);
            $table->boolean('emergency_service')->default(false);
            $table->boolean('lab_service')->default(false);
            $table->boolean('xray_service')->default(false);
            $table->boolean('ambulance')->default(false);
            $table->unsignedInteger('monthly_outpatient_avg')->nullable();
            $table->unsignedInteger('monthly_inpatient_avg')->nullable();
            $table->boolean('vaccine_service')->default(false);
            $table->boolean('family_planning')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_health');
    }
};
