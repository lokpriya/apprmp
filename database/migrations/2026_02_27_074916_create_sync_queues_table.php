<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_queues', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 100);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('record_type', 50);
            $table->longText('payload');
            $table->enum('sync_status', ['pending', 'processing', 'synced', 'failed'])->default('pending');
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->useCurrent();
            $table->timestamp('synced_at')->nullable();

            $table->index(['user_id', 'sync_status']);
            $table->index('sync_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_queues');
    }
};
