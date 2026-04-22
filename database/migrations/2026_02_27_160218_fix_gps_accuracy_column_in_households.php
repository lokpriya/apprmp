<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            // DECIMAL(6,2) only allows up to 9999.99m; browser GPS can return
            // values up to 50000m+ for IP-based location. Widen to DECIMAL(10,2).
            $table->decimal('gps_accuracy', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->decimal('gps_accuracy', 6, 2)->nullable()->change();
        });
    }
};
