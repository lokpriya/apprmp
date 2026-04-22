<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            // Replace school_dropout_reason (text) with cause_not_school (JSON multi-select)
            if (Schema::hasColumn('household_members', 'school_dropout_reason')) {
                $table->dropColumn('school_dropout_reason');
            }
            $table->json('cause_not_school')->nullable()->after('school_name');

            // Passport details — passport_info is "do you know the details?" toggle
            $table->string('passport_info', 10)->nullable()->after('has_passport');
            $table->string('passport_number', 50)->nullable()->after('passport_info');
            $table->string('passport_issued_district', 100)->nullable()->after('passport_number');
            $table->string('passport_issued_date', 20)->nullable()->after('passport_issued_district');
            $table->string('passport_expiry_date', 20)->nullable()->after('passport_issued_date');

            // PAN (age >= 16)
            $table->boolean('has_pan')->nullable()->after('passport_expiry_date');

            // Citizenship reason (when has_citizenship = No)
            $table->text('no_citizenship_reason')->nullable()->after('has_citizenship');

            // Drivers license (age >= 16)
            $table->boolean('has_drivers_license')->nullable()->after('has_pan');
            $table->json('drivers_license_category')->nullable()->after('has_drivers_license');

            // Marriage age (when married — any married status)
            $table->unsignedTinyInteger('ageofmarriage')->nullable()->after('marital_status');

            // Births given (female + married)
            $table->unsignedSmallInteger('no_birth_given')->nullable()->after('ageofmarriage');

            // Smoking/drinking (age >= 16)
            $table->boolean('smoking_drinking')->nullable()->after('employment_status');
            $table->json('smoking_drinking_items')->nullable()->after('smoking_drinking');
            $table->string('frequency_smoking_drinking', 50)->nullable()->after('smoking_drinking_items');

            // Social security allowance
            $table->boolean('ss_allowance')->nullable()->after('frequency_smoking_drinking');
        });
    }

    public function down(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            $table->dropColumn([
                'cause_not_school',
                'passport_info', 'passport_number', 'passport_issued_district',
                'passport_issued_date', 'passport_expiry_date',
                'has_pan', 'no_citizenship_reason',
                'has_drivers_license', 'drivers_license_category',
                'ageofmarriage', 'no_birth_given',
                'smoking_drinking', 'smoking_drinking_items', 'frequency_smoking_drinking',
                'ss_allowance',
            ]);
            $table->string('school_dropout_reason', 100)->nullable();
        });
    }
};
