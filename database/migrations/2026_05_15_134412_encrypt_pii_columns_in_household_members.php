<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            // Widen to text so encrypted ciphertext (~180 chars) fits
            $table->text('national_id')->nullable()->change();
            $table->text('contact_phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('household_members', function (Blueprint $table) {
            $table->string('national_id', 20)->nullable()->change();
            $table->string('contact_phone', 15)->nullable()->change();
        });
    }
};
