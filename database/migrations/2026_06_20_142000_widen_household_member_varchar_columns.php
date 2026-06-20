<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            // All these were sized for short English codes; Kobo sends full Nepali text
            $table->string('addrslocation',              200)->nullable()->change();
            $table->string('purpose_living_outside',     200)->nullable()->change();
            $table->string('purpose_living_abroad',      200)->nullable()->change();
            $table->string('typeofabroadjob',            100)->nullable()->change();
            $table->string('abroadjobstatus',            100)->nullable()->change();
            $table->text('vt_subject')->nullable()->change();
            $table->text('techtraining_subject')->nullable()->change();
            $table->text('sa_training_subject')->nullable()->change();
            $table->text('chronic_illness_types')->nullable()->change();
            $table->string('disability_card',            200)->nullable()->change();
            $table->string('disability_card_color',      100)->nullable()->change();
            $table->string('blood_group_tested',         100)->nullable()->change();
            $table->string('passport_info',              100)->nullable()->change();
            $table->string('child_caregiver',            200)->nullable()->change();
            $table->string('childbornplace',             200)->nullable()->change();
            $table->string('childsocialization',         200)->nullable()->change();
            $table->string('childphydev',                200)->nullable()->change();
            $table->string('childlgdev',                 200)->nullable()->change();
            $table->string('school_location',            100)->nullable()->change();
            $table->string('school_type',                100)->nullable()->change();
            $table->string('school_performance',         100)->nullable()->change();
            $table->string('education_status',           200)->nullable()->change();
            $table->string('current_class',              200)->nullable()->change();
            $table->string('higher_education_level',     200)->nullable()->change();
            $table->string('school_interest_6_15',       200)->nullable()->change();
            $table->string('digital_habit_6_15',         200)->nullable()->change();
            $table->string('interesting_subject_6_15',   200)->nullable()->change();
            $table->string('hw_support_6_15',            200)->nullable()->change();
            $table->string('other_interest_6_15',        200)->nullable()->change();
            $table->string('frequency_smoking_drinking', 200)->nullable()->change();
            $table->string('school_dropout_reason',      500)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intentionally not reverting — shrinking would truncate existing Nepali data
    }
};
