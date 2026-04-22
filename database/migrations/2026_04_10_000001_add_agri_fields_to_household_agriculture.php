<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('household_agriculture', function (Blueprint $table) {
            $table->json('agri_categories')->nullable()->after('agriculture');
            $table->json('chem_fertilizer_types')->nullable()->after('use_of_chem_fertilizer');
            $table->unsignedSmallInteger('chem_fertilizer_kg')->nullable()->after('chem_fertilizer_types');
            $table->json('fertilizer_sources')->nullable()->after('chem_fertilizer_kg');
            $table->json('chem_pesticide_types')->nullable()->after('use_of_chem_pesticide');
            $table->unsignedInteger('chem_pesticide_cost')->nullable()->after('chem_pesticide_types');
        });
    }
    public function down(): void {
        Schema::table('household_agriculture', function (Blueprint $table) {
            $table->dropColumn(['agri_categories','chem_fertilizer_types','chem_fertilizer_kg',
                'fertilizer_sources','chem_pesticide_types','chem_pesticide_cost']);
        });
    }
};
