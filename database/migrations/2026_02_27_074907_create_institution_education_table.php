<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_education', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 20);
            $table->unsignedSmallInteger('male_students')->default(0);
            $table->unsignedSmallInteger('female_students')->default(0);
            $table->unsignedSmallInteger('dalit_students')->default(0);
            $table->unsignedSmallInteger('janajati_students')->default(0);
            $table->unsignedSmallInteger('scholarship_students')->default(0);
            $table->unsignedSmallInteger('dropout_count')->default(0);
            $table->timestamps();

            $table->index('institution_id');
            $table->unique(['institution_id', 'grade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_education');
    }
};
