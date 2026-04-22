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
        Schema::table('member_businesses', function (Blueprint $table) {
            $table->string('pan_vat_number', 50)->nullable()->after('has_pan_vat');
        });
    }

    public function down(): void
    {
        Schema::table('member_businesses', function (Blueprint $table) {
            $table->dropColumn('pan_vat_number');
        });
    }
};
