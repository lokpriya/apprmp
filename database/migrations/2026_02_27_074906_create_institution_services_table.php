<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('service_name', 100);
            $table->string('service_type', 50)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->unsignedInteger('annual_beneficiaries')->nullable();
            $table->text('coverage_description')->nullable();
            $table->boolean('is_free')->default(true);
            $table->timestamps();

            $table->index('institution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_services');
    }
};
