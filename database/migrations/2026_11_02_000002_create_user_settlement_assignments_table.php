<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Which toles a tole coordinator looks after — the tole-level twin of user_ward_assignments. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settlement_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->unique(['user_id', 'settlement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settlement_assignments');
    }
};
