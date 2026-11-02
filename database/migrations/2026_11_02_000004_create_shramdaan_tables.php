<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * श्रमदान पासबुक — the family voluntary-labour record.
 *
 * Nothing here changes the survey tables. Who the family is (head, members,
 * ward, tole, phone) is read live from households / household_members; these
 * tables hold only what the survey never collected.
 *
 * Google Sync matches members by name + gender, so correcting a spelling in the
 * sheet deletes and re-creates the member row. Everything that points at a
 * member therefore keeps a copy of the name and lets the link go null, so a
 * family's history is never lost to a sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One passbook per household, created the first time it is opened.
        Schema::create('shramdaan_passbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('passbook_no', 80)->unique();          // "SD-" + household unique_code
            $table->date('registered_on');
            $table->text('head_citizenship')->nullable();         // encrypted cast
            $table->string('certifying_officer', 150)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // What the coordinator adds about each member.
        Schema::create('shramdaan_member_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passbook_id')->constrained('shramdaan_passbooks')->cascadeOnDelete();
            $table->foreignId('household_member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('member_name', 200);                   // snapshot, survives a re-sync
            $table->text('citizenship')->nullable();              // encrypted cast
            $table->string('skill', 150)->nullable();
            $table->boolean('excluded')->default(false);          // unable to do श्रमदान
            $table->timestamps();
            $table->unique(['passbook_id', 'household_member_id']);
        });

        // योजना list, so entries name the same project the same way.
        Schema::create('shramdaan_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 250);
            $table->string('fiscal_year', 9);                     // "2082/83"
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['ward_id', 'fiscal_year']);
        });

        // One day (or part day) of work by one member.
        Schema::create('shramdaan_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passbook_id')->constrained('shramdaan_passbooks')->cascadeOnDelete();
            $table->foreignId('household_member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('member_name', 200);                   // snapshot
            $table->foreignId('project_id')->nullable()->constrained('shramdaan_projects')->nullOnDelete();
            $table->string('project_name', 250);                  // snapshot
            $table->date('work_date');                            // AD, for sorting and ranges
            $table->string('work_date_bs', 10);                   // "2082/04/15", as printed
            $table->string('fiscal_year', 9);                     // derived from the BS date
            $table->decimal('hours', 5, 2);
            $table->decimal('days', 5, 2);                        // hours ÷ hours-per-day, never typed
            $table->string('work_nature', 250)->nullable();
            $table->string('supervisor', 150)->nullable();
            $table->string('status', 20)->default('recorded');    // recorded | verified
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['passbook_id', 'fiscal_year']);
            $table->index(['fiscal_year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shramdaan_entries');
        Schema::dropIfExists('shramdaan_projects');
        Schema::dropIfExists('shramdaan_member_details');
        Schema::dropIfExists('shramdaan_passbooks');
    }
};
