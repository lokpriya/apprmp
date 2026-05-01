<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            $table->boolean('is_deceased')->default(false)->after('residence_status');
            $table->date('death_date')->nullable()->after('is_deceased');
            $table->string('death_cause', 100)->nullable()->after('death_date');
            $table->text('death_remarks')->nullable()->after('death_cause');
        });
    }

    public function down(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            $table->dropColumn(['is_deceased', 'death_date', 'death_cause', 'death_remarks']);
        });
    }
};
