<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            // ── Contact info (age > 15, inside six_to_fifteen group in XLS) ──
            $table->boolean('has_phone')->nullable()->after('other_interest_6_15');
            $table->boolean('know_phone')->nullable()->after('has_phone');
            $table->string('member_phone', 20)->nullable()->after('know_phone');
            $table->boolean('has_email')->nullable()->after('member_phone');
            $table->boolean('know_email')->nullable()->after('has_email');
            $table->string('member_email', 100)->nullable()->after('know_email');

            // ── Vocational training (age > 15) ────────────────────────────
            $table->boolean('vocational_training')->nullable()->after('member_email');
            $table->string('vt_subject', 60)->nullable()->after('vocational_training');
            $table->unsignedSmallInteger('vt_duration_min')->nullable()->after('vt_subject');
            $table->unsignedSmallInteger('vt_duration_max')->nullable()->after('vt_duration_min');

            // ── Technical training (age > 15) ─────────────────────────────
            $table->boolean('techtraining')->nullable()->after('vt_duration_max');
            $table->string('techtraining_subject', 100)->nullable()->after('techtraining');
            $table->unsignedSmallInteger('tech_duration_min')->nullable()->after('techtraining_subject');
            $table->unsignedSmallInteger('tech_duration_max')->nullable()->after('tech_duration_min');

            // ── SA training (age > 15) ────────────────────────────────────
            $table->boolean('sa_training')->nullable()->after('tech_duration_max');
            $table->string('sa_training_subject', 100)->nullable()->after('sa_training');
            $table->unsignedSmallInteger('sa_duration_min')->nullable()->after('sa_training_subject');
            $table->unsignedSmallInteger('sa_duration_max')->nullable()->after('sa_duration_min');

            // ── Address / location ────────────────────────────────────────
            // addrslocation: inside_municipality | outside_withinNepal | Abroad
            $table->string('addrslocation', 30)->nullable()->after('sa_duration_max');

            // Outside Nepal (within Nepal, different municipality)
            $table->string('residing_district', 100)->nullable()->after('addrslocation');
            $table->string('purpose_living_outside', 50)->nullable()->after('residing_district');
            $table->unsignedSmallInteger('duration_of_stay')->nullable()->after('purpose_living_outside');

            // Abroad
            $table->json('residing_country')->nullable()->after('duration_of_stay');
            $table->string('purpose_living_abroad', 50)->nullable()->after('residing_country');
            $table->string('typeofabroadjob', 30)->nullable()->after('purpose_living_abroad');
            $table->string('abroadjobstatus', 30)->nullable()->after('typeofabroadjob');

            // ── Business (age > 15) ───────────────────────────────────────
            $table->boolean('involveinbusiness')->nullable()->after('abroadjobstatus');
            $table->text('business_name')->nullable()->after('involveinbusiness');
        });
    }

    public function down(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            $table->dropColumn([
                // Contact
                'has_phone', 'know_phone', 'member_phone',
                'has_email', 'know_email', 'member_email',
                // Training
                'vocational_training', 'vt_subject', 'vt_duration_min', 'vt_duration_max',
                'techtraining', 'techtraining_subject', 'tech_duration_min', 'tech_duration_max',
                'sa_training', 'sa_training_subject', 'sa_duration_min', 'sa_duration_max',
                // Address
                'addrslocation', 'residing_district', 'purpose_living_outside', 'duration_of_stay',
                'residing_country', 'purpose_living_abroad', 'typeofabroadjob', 'abroadjobstatus',
                // Business
                'involveinbusiness', 'business_name',
            ]);
        });
    }
};
