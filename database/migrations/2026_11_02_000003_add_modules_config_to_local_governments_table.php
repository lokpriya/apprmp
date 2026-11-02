<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Optional modules a super admin switches on per municipality (App\Support\Modules). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('local_governments', function (Blueprint $table) {
            $table->json('modules_config')->nullable()->after('survey_config');
        });
    }

    public function down(): void
    {
        Schema::table('local_governments', function (Blueprint $table) {
            $table->dropColumn('modules_config');
        });
    }
};
