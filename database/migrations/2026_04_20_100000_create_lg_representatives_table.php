<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lg_representatives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('local_government_id');
            $table->string('name', 150);
            $table->string('position', 60);
            $table->unsignedBigInteger('ward_id')->nullable();          // represented ward
            $table->string('birth_place', 150)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('ethnicity', 100)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('education', 100)->nullable();
            $table->unsignedBigInteger('address_ward_id')->nullable();  // home ward
            $table->unsignedBigInteger('address_tole_id')->nullable();  // home tole
            $table->string('contact', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('photo', 500)->nullable();
            $table->string('father_name', 150)->nullable();
            $table->string('mother_name', 150)->nullable();
            $table->string('spouse_name', 150)->nullable();
            $table->string('elected_party', 150)->nullable();
            $table->text('work_experience')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('local_government_id')->references('id')->on('local_governments')->cascadeOnDelete();
            $table->foreign('ward_id')->references('id')->on('wards')->nullOnDelete();
            $table->foreign('address_ward_id')->references('id')->on('wards')->nullOnDelete();
            $table->foreign('address_tole_id')->references('id')->on('settlements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lg_representatives');
    }
};
