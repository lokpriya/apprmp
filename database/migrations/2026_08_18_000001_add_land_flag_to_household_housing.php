<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_housing', function (Blueprint $table) {
            $table->string('has_land_in_municipality', 3)->nullable()->after('is_at_risk');
        });
    }

    public function down(): void
    {
        Schema::table('household_housing', function (Blueprint $table) {
            $table->dropColumn('has_land_in_municipality');
        });
    }
};
