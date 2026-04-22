<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('verifiable_type', 50);
            $table->unsignedBigInteger('verifiable_id');
            $table->foreignId('verifier_id')->constrained('users')->restrictOnDelete();
            $table->date('visited_at');
            $table->boolean('is_verified')->default(false);
            $table->json('discrepancies')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['verifiable_type', 'verifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_verifications');
    }
};
