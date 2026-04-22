<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_logs', function (Blueprint $table) {
            $table->id();
            $table->string('loggable_type', 50);
            $table->unsignedBigInteger('loggable_id');
            $table->enum('from_status', ['draft', 'submitted', 'ward_approved', 'rejected', 'escalated', 'published'])->nullable();
            $table->enum('to_status', ['draft', 'submitted', 'ward_approved', 'rejected', 'escalated', 'published']);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['loggable_type', 'loggable_id']);
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_logs');
    }
};
