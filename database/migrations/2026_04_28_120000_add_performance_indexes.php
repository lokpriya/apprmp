<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // household_members.is_deceased — used by alive() scope everywhere in analytics
        Schema::table('household_members', function (Blueprint $table) {
            $table->index('is_deceased', 'idx_hm_is_deceased');
            // Composite for the most common pattern: alive members of published households
            $table->index(['is_deceased', 'household_id'], 'idx_hm_deceased_hh');
        });

        // households.status — used in every analytics query cross-ward
        Schema::table('households', function (Blueprint $table) {
            $table->index('status', 'idx_hh_status');
            // ward_id + status already may exist; add status + ward_id for filter-first queries
            $table->index(['status', 'ward_id'], 'idx_hh_status_ward');
        });

        // institutions.status + ward_id
        Schema::table('institutions', function (Blueprint $table) {
            $table->index(['status', 'ward_id'], 'idx_inst_status_ward');
        });
    }

    public function down(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            $table->dropIndex('idx_hm_is_deceased');
            $table->dropIndex('idx_hm_deceased_hh');
        });
        Schema::table('households', function (Blueprint $table) {
            $table->dropIndex('idx_hh_status');
            $table->dropIndex('idx_hh_status_ward');
        });
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropIndex('idx_inst_status_ward');
        });
    }
};
