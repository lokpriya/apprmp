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
        Schema::table('local_governments', function (Blueprint $table) {
            $table->json('welcome_config')->nullable()->after('photo_dir');
        });
    }

    public function down(): void
    {
        Schema::table('local_governments', function (Blueprint $table) {
            $table->dropColumn('welcome_config');
        });
    }
};
