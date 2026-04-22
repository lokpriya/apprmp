<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_disaster', function (Blueprint $table) {
            $table->string('has_death_this_year', 10)->nullable()->after('household_id');
            $table->json('death_rows')->nullable()->after('has_death_this_year');
            $table->string('risk_reduction_plan', 10)->nullable()->after('risk_types');
            $table->string('affected_last_3years', 10)->nullable()->after('risk_reduction_plan');
            $table->json('human_loss_rows')->nullable()->after('affected_last_3years');
            $table->json('physical_loss_rows')->nullable()->after('human_loss_rows');
            $table->string('received_relief', 10)->nullable()->after('physical_loss_rows');
            $table->json('relief_rows')->nullable()->after('received_relief');
        });
    }

    public function down(): void
    {
        Schema::table('household_disaster', function (Blueprint $table) {
            $table->dropColumn([
                'has_death_this_year', 'death_rows',
                'risk_reduction_plan', 'affected_last_3years',
                'human_loss_rows', 'physical_loss_rows',
                'received_relief', 'relief_rows',
            ]);
        });
    }
};
