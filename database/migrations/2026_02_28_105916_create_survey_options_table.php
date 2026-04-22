<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_options', function (Blueprint $table) {
            $table->id();
            $table->string('category', 80)->index();
            $table->string('value', 100);
            $table->string('label_ne', 200);
            $table->string('label_en', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('local_government_id')
                  ->nullable()
                  ->constrained('local_governments')
                  ->nullOnDelete();
            $table->timestamps();

            $table->unique(['category', 'value', 'local_government_id'], 'survey_options_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_options');
    }
};
