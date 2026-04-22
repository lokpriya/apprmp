<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            // ── Age 0–3 child fields ───────────────────────────────────────────
            $table->string('childbornplace', 50)->nullable()->after('has_birth_certificate');
            $table->decimal('child_weight_in_birth', 5, 2)->nullable()->after('childbornplace');
            $table->boolean('colostrum')->nullable()->after('child_weight_in_birth');
            $table->json('childvaccine')->nullable()->after('colostrum');
            $table->boolean('vit_a_dewormin')->nullable()->after('childvaccine');
            $table->boolean('fullmilkcourse')->nullable()->after('vit_a_dewormin');
            $table->unsignedSmallInteger('durationbrestfeed')->nullable()->after('fullmilkcourse');
            $table->boolean('childhealthcheckup')->nullable()->after('durationbrestfeed');
            $table->boolean('childbmi')->nullable()->after('childhealthcheckup');
            $table->string('child_caregiver', 30)->nullable()->after('childbmi');
            $table->boolean('diarrhea_before_two_weeks')->nullable()->after('child_caregiver');
            $table->boolean('fever_resp')->nullable()->after('diarrhea_before_two_weeks');

            // ── Age 4–5 child fields ───────────────────────────────────────────
            $table->boolean('vitaminA')->nullable()->after('fever_resp');
            $table->string('childphydev', 50)->nullable()->after('vitaminA');
            $table->string('childlgdev', 50)->nullable()->after('childphydev');
            $table->string('childsocialization', 50)->nullable()->after('childlgdev');

            // ── Age 6–15 child fields ──────────────────────────────────────────
            $table->boolean('phy_dev_6_15')->nullable()->after('childsocialization');
            $table->string('childphydev_6_15', 50)->nullable()->after('phy_dev_6_15');
            $table->string('childlgdev_6_15', 50)->nullable()->after('childphydev_6_15');
            $table->string('school_interest_6_15', 30)->nullable()->after('childlgdev_6_15');
            $table->string('interesting_subject_6_15', 30)->nullable()->after('school_interest_6_15');
            $table->string('hw_support_6_15', 30)->nullable()->after('interesting_subject_6_15');
            $table->boolean('allergy_infection_6_15')->nullable()->after('hw_support_6_15');
            $table->boolean('watch_mobile_tv_6_15')->nullable()->after('allergy_infection_6_15');
            $table->string('digital_habit_6_15', 30)->nullable()->after('watch_mobile_tv_6_15');
            $table->string('other_interest_6_15', 30)->nullable()->after('digital_habit_6_15');

            // ── Child labor (age ≤ 18 + Stopped education) ────────────────────
            $table->boolean('child_labor')->nullable()->after('other_interest_6_15');
            $table->text('workplace_child_labor')->nullable()->after('child_labor');
        });
    }

    public function down(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            $table->dropColumn([
                // 0-3
                'childbornplace', 'child_weight_in_birth', 'colostrum', 'childvaccine',
                'vit_a_dewormin', 'fullmilkcourse', 'durationbrestfeed',
                'childhealthcheckup', 'childbmi', 'child_caregiver',
                'diarrhea_before_two_weeks', 'fever_resp',
                // 4-5
                'vitaminA', 'childphydev', 'childlgdev', 'childsocialization',
                // 6-15
                'phy_dev_6_15', 'childphydev_6_15', 'childlgdev_6_15',
                'school_interest_6_15', 'interesting_subject_6_15', 'hw_support_6_15',
                'allergy_infection_6_15', 'watch_mobile_tv_6_15', 'digital_habit_6_15', 'other_interest_6_15',
                // child labor
                'child_labor', 'workplace_child_labor',
            ]);
        });
    }
};
