<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->json('survey_data')->nullable()->after('remarks');
        });

        Schema::table('household_members', function (Blueprint $table) {
            if (!Schema::hasColumn('household_members', 'blood_group')) {
                $table->string('blood_group', 20)->nullable()->after('chronic_illness_types');
            }
            if (!Schema::hasColumn('household_members', 'has_voters_card')) {
                $table->boolean('has_voters_card')->default(false)->after('has_citizenship');
            }
            if (!Schema::hasColumn('household_members', 'has_national_id_card')) {
                $table->boolean('has_national_id_card')->default(false)->after('has_voters_card');
            }
            if (!Schema::hasColumn('household_members', 'has_passport')) {
                $table->boolean('has_passport')->default(false)->after('has_national_id_card');
            }
            if (!Schema::hasColumn('household_members', 'residence_status')) {
                $table->string('residence_status', 50)->nullable()->after('has_passport');
            }
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('survey_data');
        });
        Schema::table('household_members', function (Blueprint $table) {
            $table->dropColumn(['blood_group', 'has_voters_card', 'has_national_id_card', 'has_passport', 'residence_status']);
        });
    }
};
