<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->decimal('gps_altitude', 8, 2)->nullable()->after('gps_lng');
            $table->decimal('gps_accuracy', 8, 2)->nullable()->after('gps_altitude');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['gps_altitude', 'gps_accuracy']);
        });
    }
};
