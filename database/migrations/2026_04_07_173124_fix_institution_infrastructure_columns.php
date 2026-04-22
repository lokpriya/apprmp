<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1 — rename + retype columns using CHANGE COLUMN (MariaDB/MySQL compatible)
        DB::statement('ALTER TABLE institution_infrastructure
            CHANGE COLUMN electricity     has_electricity TINYINT(1) NOT NULL DEFAULT 0,
            CHANGE COLUMN drinking_water  has_water       TINYINT(1) NOT NULL DEFAULT 0,
            CHANGE COLUMN internet        has_internet    TINYINT(1) NOT NULL DEFAULT 0,
            CHANGE COLUMN accessible_ramp has_ramp        TINYINT(1) NOT NULL DEFAULT 0,
            CHANGE COLUMN land_area_sqft  total_area      DECIMAL(10,2) NULL DEFAULT NULL,
            CHANGE COLUMN male_toilet     male_toilet     SMALLINT UNSIGNED NULL DEFAULT NULL,
            CHANGE COLUMN female_toilet   female_toilet   SMALLINT UNSIGNED NULL DEFAULT NULL,
            DROP COLUMN building_type,
            DROP COLUMN building_condition
        ');

        // Step 2 — add missing columns the form needs
        Schema::table('institution_infrastructure', function (Blueprint $table) {
            $table->json('buildings')->nullable()->after('institution_id');
            $table->string('condition', 30)->nullable()->after('total_area');
            $table->boolean('has_toilet')->default(false)->after('disabled_toilet');
            $table->boolean('has_library')->default(false)->after('has_ramp');
            $table->boolean('has_lab')->default(false)->after('has_library');
            $table->unsignedSmallInteger('total_classrooms')->nullable()->after('has_lab');
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE institution_infrastructure
            CHANGE COLUMN has_electricity electricity    TINYINT(1) NOT NULL DEFAULT 0,
            CHANGE COLUMN has_water       drinking_water TINYINT(1) NOT NULL DEFAULT 0,
            CHANGE COLUMN has_internet    internet       TINYINT(1) NOT NULL DEFAULT 0,
            CHANGE COLUMN has_ramp        accessible_ramp TINYINT(1) NOT NULL DEFAULT 0,
            CHANGE COLUMN total_area      land_area_sqft DECIMAL(10,2) NULL DEFAULT NULL,
            CHANGE COLUMN male_toilet     male_toilet    TINYINT(1) NOT NULL DEFAULT 0,
            CHANGE COLUMN female_toilet   female_toilet  TINYINT(1) NOT NULL DEFAULT 0
        ');

        Schema::table('institution_infrastructure', function (Blueprint $table) {
            $table->enum('building_type', ['rcc','brick_cement','prefab','temporary','other'])->nullable();
            $table->enum('building_condition', ['good','average','poor','damaged','under_construction'])->nullable();
            $table->dropColumn(['buildings','condition','has_toilet','has_library','has_lab','total_classrooms']);
        });
    }
};
