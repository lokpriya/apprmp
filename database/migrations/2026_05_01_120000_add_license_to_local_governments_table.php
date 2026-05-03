<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('local_governments', function (Blueprint $table) {
            $table->date('licensed_until')->nullable()->after('google_sheets_config');
            $table->string('license_key', 500)->nullable()->after('licensed_until');
        });
    }

    public function down(): void
    {
        Schema::table('local_governments', function (Blueprint $table) {
            $table->dropColumn(['licensed_until', 'license_key']);
        });
    }
};
