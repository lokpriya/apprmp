<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lg_download_links', function (Blueprint $table) {
            $table->string('thumbnail', 500)->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('lg_download_links', function (Blueprint $table) {
            $table->dropColumn('thumbnail');
        });
    }
};
