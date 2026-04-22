<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('unique_code', 25)->unique();
            $table->foreignId('ward_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('enumerator_id')->constrained('users')->restrictOnDelete();
            $table->string('respondent_name');
            $table->string('respondent_contact', 20)->nullable();
            $table->unsignedTinyInteger('total_members')->default(1);
            $table->decimal('gps_lat', 10, 8)->nullable();
            $table->decimal('gps_lng', 11, 8)->nullable();
            $table->decimal('gps_altitude', 8, 2)->nullable();
            $table->decimal('gps_accuracy', 10, 2)->nullable();
            $table->enum('status', ['draft', 'submitted', 'ward_approved', 'rejected', 'escalated', 'published'])->default('draft');
            $table->date('survey_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('ward_reviewed_at')->nullable();
            $table->unsignedBigInteger('ward_reviewer_id')->nullable();
            $table->string('ward_review_comment')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamp('data_frozen_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreign('ward_reviewer_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['ward_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
