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
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('respondent_name', 150)->nullable()->after('remarks');
            $table->string('respondent_contact', 20)->nullable()->after('respondent_name');
            $table->json('survey_data')->nullable()->after('respondent_contact');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['respondent_name', 'respondent_contact', 'survey_data']);
        });
    }
};
