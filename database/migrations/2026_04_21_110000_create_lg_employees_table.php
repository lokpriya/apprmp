<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lg_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_government_id')->constrained('local_governments')->cascadeOnDelete();

            // Primary fields
            $table->string('name', 150);
            $table->string('position', 150)->nullable();
            $table->string('department', 150)->nullable();
            $table->date('entry_date')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('birth_place', 150)->nullable();
            $table->string('ethnicity', 100)->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('education', 100)->nullable();
            $table->string('permanent_address', 255)->nullable();
            $table->string('contact', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('photo')->nullable();

            // Social IDs
            $table->string('facebook', 255)->nullable();
            $table->string('instagram', 255)->nullable();
            $table->string('tiktok', 255)->nullable();
            $table->string('twitter_x', 255)->nullable();

            // Secondary fields
            $table->string('father_name', 150)->nullable();
            $table->string('mother_name', 150)->nullable();
            $table->string('spouse_name', 150)->nullable();
            $table->text('work_experience')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lg_employees');
    }
};
