<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            $table->string('father_name', 200)->nullable()->after('full_name');
            $table->string('blood_group_tested', 10)->nullable()->after('blood_group');
            $table->string('disability_card', 10)->nullable()->after('disability_type');
            $table->string('disability_card_color', 20)->nullable()->after('disability_card');
            $table->string('education_status', 50)->nullable()->after('education_level');
            $table->string('current_class', 30)->nullable()->after('education_status');
            $table->string('higher_education_level', 50)->nullable()->after('current_class');
            $table->string('school_type', 30)->nullable()->after('higher_education_level');
            $table->string('school_performance', 30)->nullable()->after('school_type');
            $table->string('school_location', 20)->nullable()->after('school_performance');
            $table->string('school_name', 200)->nullable()->after('school_location');
            $table->text('school_dropout_reason')->nullable()->after('school_name');
        });
    }

    public function down(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            $table->dropColumn([
                'father_name', 'blood_group_tested', 'disability_card', 'disability_card_color',
                'education_status', 'current_class', 'higher_education_level',
                'school_type', 'school_performance', 'school_location',
                'school_name', 'school_dropout_reason',
            ]);
        });
    }
};
