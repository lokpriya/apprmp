<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_utilities', function (Blueprint $table) {
            // cooking_fuel must become JSON to store multi-select array
            $table->json('cooking_fuel_arr')->nullable()->after('cooking_fuel');
            // New firewood / stove / alt-electricity fields
            $table->string('uses_firewood', 10)->nullable()->after('cooking_fuel_arr');
            $table->json('firewood_source')->nullable()->after('uses_firewood');
            $table->string('cooking_stove', 80)->nullable()->after('firewood_source');
            $table->string('has_alt_electricity', 10)->nullable()->after('cooking_stove');
            $table->json('alt_electricity_sources')->nullable()->after('has_alt_electricity');
            // Vehicles as structured Add+ rows [{type, count}]
            $table->json('vehicles')->nullable()->after('type_of_vehicle');
        });
    }

    public function down(): void
    {
        Schema::table('household_utilities', function (Blueprint $table) {
            $table->dropColumn([
                'cooking_fuel_arr', 'uses_firewood', 'firewood_source',
                'cooking_stove', 'has_alt_electricity', 'alt_electricity_sources',
                'vehicles',
            ]);
        });
    }
};
