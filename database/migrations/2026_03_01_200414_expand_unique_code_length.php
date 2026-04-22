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
            $table->dropUnique('households_unique_code_unique');
            $table->string('unique_code', 60)->change();
            $table->unique('unique_code');
        });
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropUnique('institutions_unique_code_unique');
            $table->string('unique_code', 60)->change();
            $table->unique('unique_code');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropUnique('households_unique_code_unique');
            $table->string('unique_code', 25)->change();
            $table->unique('unique_code');
        });
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropUnique('institutions_unique_code_unique');
            $table->string('unique_code', 25)->change();
            $table->unique('unique_code');
        });
    }
};
