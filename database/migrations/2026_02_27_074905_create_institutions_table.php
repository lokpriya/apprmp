<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('unique_code', 25)->unique();
            $table->foreignId('ward_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('institution_type_id')->constrained()->restrictOnDelete();
            $table->string('name_np');
            $table->string('name_en')->nullable();
            $table->date('established_date')->nullable();
            $table->enum('ownership_type', ['govt', 'private', 'community', 'ngo', 'cooperative', 'other'])->nullable();
            $table->string('registration_number', 50)->nullable();
            $table->foreignId('enumerator_id')->constrained('users')->restrictOnDelete();
            $table->decimal('gps_lat', 10, 8)->nullable();
            $table->decimal('gps_lng', 11, 8)->nullable();
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
            $table->index(['institution_type_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};
