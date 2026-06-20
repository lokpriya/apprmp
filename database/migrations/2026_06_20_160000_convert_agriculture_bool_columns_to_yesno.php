<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change boolean (TINYINT) columns to VARCHAR so sync can store 'yes'/'no'
        // matching survey_options keys used in reports
        Schema::table('household_agriculture', function (Blueprint $table) {
            $table->string('agriculture',              10)->nullable()->change();
            $table->string('owns_land',                10)->nullable()->change();
            $table->string('uses_chemical_fertilizer', 10)->nullable()->change();
            $table->string('uses_organic_fertilizer',  10)->nullable()->change();
            $table->string('sells_produce',            10)->nullable()->change();
            $table->string('has_agri_loan',            10)->nullable()->change();
        });

        // Convert stored 1/0 integers to 'yes'/'no' strings
        $cols = ['agriculture','owns_land','uses_chemical_fertilizer','uses_organic_fertilizer','sells_produce','has_agri_loan'];
        foreach ($cols as $c) {
            DB::statement("UPDATE household_agriculture SET `$c` = CASE
                WHEN `$c` IN ('1','true','yes','Yes') THEN 'yes'
                WHEN `$c` IN ('0','false','no','No') THEN 'no'
                ELSE NULL END");
        }
    }

    public function down(): void
    {
        // Intentionally not reverting — converting text back to boolean would lose data
    }
};
