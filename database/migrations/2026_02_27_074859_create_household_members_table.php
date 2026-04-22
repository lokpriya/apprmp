<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_household_head')->default(false);
            $table->string('full_name');
            $table->enum('relation_to_head', ['self', 'spouse', 'son', 'daughter', 'father', 'mother', 'grandfather', 'grandmother', 'grandson', 'granddaughter', 'brother', 'sister', 'daughter_in_law', 'son_in_law', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->enum('ethnicity', ['brahmin_hill', 'chhetri', 'magar', 'tamang', 'newar', 'tharu', 'kami', 'damai', 'sarki', 'musahar', 'yadav', 'madhesi_brahmin', 'other_dalit', 'other_janajati', 'other'])->nullable();
            $table->enum('religion', ['hindu', 'buddhist', 'muslim', 'christian', 'kirant', 'bon', 'other'])->nullable();
            $table->string('mother_tongue', 50)->nullable();
            $table->enum('marital_status', ['single', 'married', 'widowed', 'divorced', 'separated'])->nullable();
            $table->enum('education_level', ['none', 'pre_primary', 'primary', 'lower_secondary', 'secondary', 'slc', 'proficiency', 'bachelor', 'master', 'phd'])->nullable();
            $table->boolean('is_literate')->default(false);
            $table->enum('occupation_type', ['agriculture', 'business', 'service_govt', 'service_private', 'student', 'household_work', 'foreign_employment', 'daily_wage', 'unemployed', 'other'])->nullable();
            $table->enum('employment_status', ['employed', 'self_employed', 'unemployed', 'student', 'not_applicable'])->nullable();
            $table->boolean('disability_status')->default(false);
            $table->enum('disability_type', ['physical', 'visual', 'hearing', 'speech', 'intellectual', 'psychosocial', 'multiple', 'other'])->nullable();
            $table->boolean('chronic_illness')->default(false);
            $table->json('chronic_illness_types')->nullable();
            $table->string('contact_phone', 15)->nullable();
            $table->string('national_id', 20)->nullable();
            $table->boolean('has_birth_certificate')->default(false);
            $table->boolean('has_citizenship')->default(false);
            $table->text('remarks')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('household_id');
            $table->index(['household_id', 'is_household_head']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_members');
    }
};
