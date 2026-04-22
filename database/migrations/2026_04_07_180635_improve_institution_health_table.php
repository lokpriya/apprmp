<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename mismatched columns (MariaDB CHANGE COLUMN syntax)
        DB::statement('ALTER TABLE institution_health
            CHANGE COLUMN ambulance             has_ambulance    TINYINT(1) NOT NULL DEFAULT 0,
            CHANGE COLUMN xray_service          has_xray         TINYINT(1) NOT NULL DEFAULT 0,
            CHANGE COLUMN lab_service           has_lab          TINYINT(1) NOT NULL DEFAULT 0,
            CHANGE COLUMN monthly_outpatient_avg opd_monthly     INT UNSIGNED NULL DEFAULT NULL,
            CHANGE COLUMN monthly_inpatient_avg  ipd_monthly     INT UNSIGNED NULL DEFAULT NULL
        ');

        Schema::table('institution_health', function (Blueprint $table) {
            // Health type (from inst_health_type survey options)
            $table->string('health_type', 50)->nullable()->after('institution_id');

            // Daily OPD (existing opd_monthly becomes monthly; add daily)
            $table->unsignedSmallInteger('opd_daily')->nullable()->after('bed_count');

            // Bed breakdown by ward
            $table->unsignedSmallInteger('male_ward_beds')->nullable()->after('opd_daily');
            $table->unsignedSmallInteger('female_ward_beds')->nullable()->after('male_ward_beds');
            $table->unsignedSmallInteger('children_ward_beds')->nullable()->after('female_ward_beds');
            $table->unsignedSmallInteger('maternity_beds')->nullable()->after('children_ward_beds');
            $table->unsignedSmallInteger('icu_beds')->nullable()->after('maternity_beds');
            $table->unsignedSmallInteger('emergency_beds')->nullable()->after('icu_beds');

            // Annual stats
            $table->unsignedInteger('annual_delivery_count')->nullable()->after('ipd_monthly');

            // Equipment flags (in addition to has_ambulance/has_xray/has_lab)
            $table->boolean('has_ultrasound')->default(false)->after('has_lab');
            $table->boolean('has_ecg')->default(false)->after('has_ultrasound');
            $table->boolean('has_icu')->default(false)->after('has_ecg');
            $table->boolean('has_operation_theatre')->default(false)->after('has_icu');
            $table->boolean('has_blood_bank')->default(false)->after('has_operation_theatre');
            $table->boolean('has_dialysis')->default(false)->after('has_blood_bank');
            $table->boolean('has_dental')->default(false)->after('has_dialysis');
            $table->boolean('has_physiotherapy')->default(false)->after('has_dental');
            $table->boolean('has_oxygen_plant')->default(false)->after('has_physiotherapy');

            // JSON arrays for multi-select survey options
            $table->json('health_services')->nullable()->after('has_oxygen_plant');
            $table->json('equipment')->nullable()->after('health_services');
            $table->json('lab_services')->nullable()->after('equipment');
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE institution_health
            CHANGE COLUMN has_ambulance has_ambulance    TINYINT(1) NOT NULL DEFAULT 0,
            CHANGE COLUMN has_xray      xray_service     TINYINT(1) NOT NULL DEFAULT 0,
            CHANGE COLUMN has_lab       lab_service      TINYINT(1) NOT NULL DEFAULT 0,
            CHANGE COLUMN opd_monthly   monthly_outpatient_avg INT UNSIGNED NULL DEFAULT NULL,
            CHANGE COLUMN ipd_monthly   monthly_inpatient_avg  INT UNSIGNED NULL DEFAULT NULL
        ');

        Schema::table('institution_health', function (Blueprint $table) {
            $table->dropColumn([
                'health_type', 'opd_daily',
                'male_ward_beds', 'female_ward_beds', 'children_ward_beds',
                'maternity_beds', 'icu_beds', 'emergency_beds',
                'annual_delivery_count',
                'has_ultrasound', 'has_ecg', 'has_icu', 'has_operation_theatre',
                'has_blood_bank', 'has_dialysis', 'has_dental', 'has_physiotherapy',
                'has_oxygen_plant',
                'health_services', 'equipment', 'lab_services',
            ]);
        });
    }
};
