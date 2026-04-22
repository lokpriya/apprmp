<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('edit_requests', function (Blueprint $table) {
            $table->id();
            $table->morphs('requestable');                                       // requestable_type + requestable_id
            $table->foreignId('requested_by')->constrained('users');             // ward reviewer who sent the request
            $table->foreignId('reviewed_by')->nullable()->constrained('users');  // admin who approved/rejected
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reason');                                              // reviewer's reason for the request
            $table->text('admin_note')->nullable();                              // admin's response note
            $table->timestamps();

            // Note: morphs() already creates the requestable_type+requestable_id composite index
            $table->index('status');
            $table->index('requested_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edit_requests');
    }
};
