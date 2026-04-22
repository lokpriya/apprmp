<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('staff_category', 100);
            $table->unsignedSmallInteger('male_count')->default(0);
            $table->unsignedSmallInteger('female_count')->default(0);
            $table->unsignedSmallInteger('dalit_count')->default(0);
            $table->unsignedSmallInteger('janajati_count')->default(0);
            $table->unsignedSmallInteger('other_count')->default(0);
            $table->unsignedSmallInteger('permanent_count')->default(0);
            $table->unsignedSmallInteger('contract_count')->default(0);
            $table->timestamps();

            $table->index('institution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_staff');
    }
};
