<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History of one-file updates applied through the admin panel: which version
 * was installed over which, by whom, whether it completed or was rolled back,
 * and the full step log. Read by Settings → प्रणाली अद्यावधिक.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_updates', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20);
            $table->string('from_version', 20)->nullable();
            $table->string('status', 20);                 // finished | rolled_back | failed
            $table->string('commit', 40)->nullable();
            $table->unsignedInteger('files_count')->default(0);
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('applied_by_name', 150)->nullable();
            $table->longText('log')->nullable();           // JSON step log
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_updates');
    }
};
