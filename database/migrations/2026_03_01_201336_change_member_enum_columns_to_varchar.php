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
        // Change free-form data columns from ENUM to VARCHAR so bulk import
        // can accept field-collected values without truncation.
        Schema::table('household_members', function (Blueprint $table) {
            $table->string('relation_to_head', 50)->nullable()->change();
            $table->string('ethnicity', 80)->nullable()->change();
            $table->string('religion', 80)->nullable()->change();
            $table->string('marital_status', 50)->nullable()->change();
            $table->string('education_level', 50)->nullable()->change();
            $table->string('occupation_type', 50)->nullable()->change();
            $table->string('employment_status', 50)->nullable()->change();
            $table->string('disability_type', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            $table->enum('relation_to_head', ['self','spouse','son','daughter','father','mother','grandfather','grandmother','grandson','granddaughter','brother','sister','daughter_in_law','son_in_law','other'])->nullable()->change();
            $table->enum('ethnicity', ['brahmin_hill','chhetri','magar','tamang','newar','tharu','kami','damai','sarki','musahar','yadav','madhesi_brahmin','other_dalit','other_janajati','other'])->nullable()->change();
            $table->enum('religion', ['hindu','buddhist','muslim','christian','kirant','bon','other'])->nullable()->change();
            $table->enum('marital_status', ['single','married','widowed','divorced','separated'])->nullable()->change();
            $table->enum('education_level', ['none','pre_primary','primary','lower_secondary','secondary','slc','proficiency','bachelor','master','phd'])->nullable()->change();
            $table->enum('occupation_type', ['agriculture','business','service_govt','service_private','student','household_work','foreign_employment','daily_wage','unemployed','other'])->nullable()->change();
            $table->enum('employment_status', ['employed','self_employed','unemployed','student','not_applicable'])->nullable()->change();
            $table->enum('disability_type', ['physical','visual','hearing','speech','intellectual','psychosocial','multiple','other'])->nullable()->change();
        });
    }
};
