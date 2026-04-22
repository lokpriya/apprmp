<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_businesses', function (Blueprint $table) {
            $table->id();

            // ── Links ──────────────────────────────────────────────────────
            $table->foreignId('household_member_id')->unique()->constrained('household_members')->cascadeOnDelete();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('ward_id')->constrained('wards');
            $table->foreignId('settlement_id')->nullable()->constrained('settlements')->nullOnDelete();
            $table->foreignId('enumerator_id')->nullable()->constrained('users')->nullOnDelete();

            // ── Identity ───────────────────────────────────────────────────
            $table->string('unique_code', 60)->unique();

            // ── Workflow ───────────────────────────────────────────────────
            $table->string('status', 20)->default('draft'); // draft|submitted|ward_approved|published|rejected|escalated
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('ward_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ward_reviewed_at')->nullable();
            $table->string('ward_review_comment', 500)->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();

            // ── Step 1: व्यवसाय पहिचान ─────────────────────────────────────
            $table->string('business_name', 200);
            $table->string('owner_father_name', 100)->nullable();
            $table->string('owner_grand_father_name', 100)->nullable();
            $table->string('business_location', 50)->nullable();   // UrbanArea|MarketArea|RuralArea
            $table->string('business_type', 60)->nullable();       // Partnership|Single|PublicCompany|other
            $table->string('business_type_other', 100)->nullable();

            // ── Step 2: व्यवसायको क्षेत्र ──────────────────────────────────
            $table->string('business_field', 80)->nullable();      // from areaofbusiness list
            $table->string('business_field_other', 100)->nullable();
            $table->string('business_sub_category', 100)->nullable(); // conditional sub-list value

            // ── Step 3: दर्ता तथा कर ───────────────────────────────────────
            $table->boolean('is_registered')->nullable();
            $table->json('place_of_registration')->nullable();      // multi-select array
            $table->string('place_of_registration_other', 100)->nullable();
            $table->string('reg_no_municipality', 50)->nullable();
            $table->string('reg_no_home_cottage', 50)->nullable();
            $table->string('reg_no_company_registrar', 50)->nullable();
            $table->string('reg_no_fcci', 50)->nullable();
            $table->string('reg_no_govt_office', 50)->nullable();
            $table->boolean('has_pan_vat')->nullable();
            $table->boolean('paid_business_tax')->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->string('contact_number', 20)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_businesses');
    }
};
