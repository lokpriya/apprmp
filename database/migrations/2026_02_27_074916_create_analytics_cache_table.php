<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key', 150)->unique();
            $table->unsignedBigInteger('local_government_id')->nullable();
            $table->unsignedBigInteger('ward_id')->nullable();
            $table->longText('data_json');
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            $table->index('local_government_id');
            $table->index('ward_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_cache');
    }
};
