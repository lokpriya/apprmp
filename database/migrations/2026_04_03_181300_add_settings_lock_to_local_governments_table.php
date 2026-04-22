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
        Schema::table('local_governments', function (Blueprint $table) {
            $table->timestamp('settings_locked_at')->nullable()->after('role_permissions');
            $table->foreignId('settings_locked_by')->nullable()->constrained('users')->nullOnDelete()->after('settings_locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('local_governments', function (Blueprint $table) {
            $table->dropForeign(['settings_locked_by']);
            $table->dropColumn(['settings_locked_at', 'settings_locked_by']);
        });
    }
};
