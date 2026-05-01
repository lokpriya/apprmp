<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // sync tracking on households
        Schema::table('households', function (Blueprint $table) {
            $table->string('sync_source', 30)->nullable()->after('status');  // 'google_sheets' | null
            $table->string('sync_hash', 64)->nullable()->after('sync_source'); // SHA-256 of key fields
            $table->boolean('sync_locked')->default(false)->after('sync_hash'); // true = manual edit, skip on sync
        });

        // sync tracking on institutions
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('sync_source', 30)->nullable()->after('status');
            $table->string('sync_hash', 64)->nullable()->after('sync_source');
            $table->boolean('sync_locked')->default(false)->after('sync_hash');
        });

        // sync tracking on member_businesses
        Schema::table('member_businesses', function (Blueprint $table) {
            $table->string('sync_source', 30)->nullable()->after('status');
            $table->string('sync_hash', 64)->nullable()->after('sync_source');
            $table->boolean('sync_locked')->default(false)->after('sync_hash');
        });

        // Google Sheets config on local_governments
        Schema::table('local_governments', function (Blueprint $table) {
            $table->json('google_sheets_config')->nullable()->after('maintenance_config');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn(['sync_source', 'sync_hash', 'sync_locked']);
        });
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['sync_source', 'sync_hash', 'sync_locked']);
        });
        Schema::table('member_businesses', function (Blueprint $table) {
            $table->dropColumn(['sync_source', 'sync_hash', 'sync_locked']);
        });
        Schema::table('local_governments', function (Blueprint $table) {
            $table->dropColumn('google_sheets_config');
        });
    }
};
