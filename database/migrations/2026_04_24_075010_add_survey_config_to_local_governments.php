<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('local_governments', function (Blueprint $table) {
            $table->json('survey_config')->nullable()->after('maintenance_config');
        });
    }

    public function down(): void
    {
        Schema::table('local_governments', function (Blueprint $table) {
            $table->dropColumn('survey_config');
        });
    }
};
