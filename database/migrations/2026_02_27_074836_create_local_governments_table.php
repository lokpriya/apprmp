<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_governments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('name_np');
            $table->string('name_en');
            $table->enum('type', ['metropolitan', 'sub_metropolitan', 'municipality', 'rural_municipality']);
            $table->string('code', 20)->unique();
            $table->unsignedTinyInteger('ward_count')->default(1);
            $table->string('logo')->nullable();
            $table->date('established_date')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_governments');
    }
};
