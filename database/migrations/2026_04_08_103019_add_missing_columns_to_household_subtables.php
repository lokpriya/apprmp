<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── household_housing: add missing form fields ─────────────────
        Schema::table('household_housing', function (Blueprint $table) {
            $table->string('house_land_type', 60)->nullable()->after('household_id');
            $table->string('land_measurement_unit', 50)->nullable()->after('house_land_type');
            $table->string('house_type', 60)->nullable()->after('land_measurement_unit');
            $table->string('house_purpose', 60)->nullable()->after('house_type');
            $table->string('location_house_built', 100)->nullable()->after('house_purpose');
            $table->unsignedSmallInteger('distance_to_mainroad')->nullable()->after('rent_amount');
            $table->unsignedSmallInteger('distance_to_school')->nullable()->after('distance_to_mainroad');
        });

        // ── household_utilities: add missing form fields ───────────────
        Schema::table('household_utilities', function (Blueprint $table) {
            $table->string('handwashing', 100)->nullable();
            $table->json('access_to_facilities')->nullable();
            $table->string('vehicle', 10)->nullable();
            $table->json('type_of_vehicle')->nullable();
        });

        // ── household_economic: add missing income/expense fields ──────
        Schema::table('household_economic', function (Blueprint $table) {
            $table->json('income_source')->nullable();
            $table->decimal('agri_livestock_income', 12, 2)->nullable();
            $table->decimal('business_industry_income', 12, 2)->nullable();
            $table->decimal('employment_service_income', 12, 2)->nullable();
            $table->decimal('abroad_job_income', 12, 2)->nullable();
            $table->decimal('food_expense', 12, 2)->nullable();
            $table->decimal('clothes_expense', 12, 2)->nullable();
            $table->decimal('education_expense', 12, 2)->nullable();
            $table->decimal('treatment_expense', 12, 2)->nullable();
            $table->decimal('other_expense', 12, 2)->nullable();
        });

        // ── household_agriculture: add missing / renamed fields ────────
        Schema::table('household_agriculture', function (Blueprint $table) {
            $table->string('agriculture', 10)->nullable();          // yes / no
            $table->json('crop_type')->nullable();
            $table->json('food_crops')->nullable();
            $table->json('pulses')->nullable();
            $table->json('vegetables')->nullable();
            $table->json('cash_crops')->nullable();
            $table->json('fruits')->nullable();
            $table->string('use_of_chem_fertilizer', 10)->nullable();
            $table->string('use_of_chem_pesticide', 10)->nullable();
            $table->json('Farm_Challenges')->nullable();
        });

        // ── household_disaster: add missing detail fields ──────────────
        Schema::table('household_disaster', function (Blueprint $table) {
            $table->text('family_disaster_plan')->nullable();
            $table->unsignedSmallInteger('ThreeNDvictims')->nullable();
            $table->json('natural_disasters')->nullable();
            $table->unsignedSmallInteger('victimHEarthQuake')->nullable();
            $table->unsignedSmallInteger('victimHLandslide')->nullable();
            $table->unsignedSmallInteger('victimHFire')->nullable();
            $table->unsignedSmallInteger('victimHFlood')->nullable();
            $table->unsignedSmallInteger('victimHEpidemic')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('household_housing', function (Blueprint $table) {
            $table->dropColumn(['house_land_type','land_measurement_unit','house_type',
                'house_purpose','location_house_built','distance_to_mainroad','distance_to_school']);
        });
        Schema::table('household_utilities', function (Blueprint $table) {
            $table->dropColumn(['handwashing','access_to_facilities','vehicle','type_of_vehicle']);
        });
        Schema::table('household_economic', function (Blueprint $table) {
            $table->dropColumn(['income_source','agri_livestock_income','business_industry_income',
                'employment_service_income','abroad_job_income','food_expense','clothes_expense',
                'education_expense','treatment_expense','other_expense']);
        });
        Schema::table('household_agriculture', function (Blueprint $table) {
            $table->dropColumn(['agriculture','crop_type','food_crops','pulses','vegetables',
                'cash_crops','fruits','use_of_chem_fertilizer','use_of_chem_pesticide','Farm_Challenges']);
        });
        Schema::table('household_disaster', function (Blueprint $table) {
            $table->dropColumn(['family_disaster_plan','ThreeNDvictims','natural_disasters',
                'victimHEarthQuake','victimHLandslide','victimHFire','victimHFlood','victimHEpidemic']);
        });
    }
};
