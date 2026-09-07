<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the new varchar column (skip if already added from a partial run)
        if (!Schema::hasColumn('institutions', 'institution_type')) {
            Schema::table('institutions', function (Blueprint $table) {
                $table->string('institution_type', 50)->nullable()->after('institution_type_id');
            });
        }

        // 2. Populate from slug where not yet set
        DB::statement('UPDATE institutions i JOIN institution_types it ON i.institution_type_id = it.id SET i.institution_type = it.slug WHERE i.institution_type IS NULL');

        // 3. Drop FK first, then index, then old column
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropForeign('institutions_institution_type_id_foreign');
            $table->dropIndex('institutions_institution_type_id_status_index');
            $table->dropColumn('institution_type_id');
        });

        // 4. Add a simple index on the new column + status
        Schema::table('institutions', function (Blueprint $table) {
            $table->index(['institution_type', 'status'], 'institutions_institution_type_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropIndex('institutions_institution_type_status_index');
            $table->unsignedBigInteger('institution_type_id')->nullable()->after('institution_type');
            $table->foreign('institution_type_id')->references('id')->on('institution_types');
            $table->index(['institution_type_id', 'status'], 'institutions_institution_type_id_status_index');
        });

        DB::statement('UPDATE institutions i JOIN institution_types it ON i.institution_type = it.slug SET i.institution_type_id = it.id');

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('institution_type');
        });
    }
};
