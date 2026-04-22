<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_migration', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('household_members')->cascadeOnDelete();
            $table->enum('migration_status', ['not_migrated', 'internal', 'abroad'])->default('not_migrated');
            $table->string('internal_destination', 100)->nullable();
            $table->string('destination_country', 100)->nullable();
            $table->unsignedTinyInteger('years_abroad')->nullable();
            $table->string('occupation_abroad', 100)->nullable();
            $table->decimal('annual_remittance', 10, 2)->nullable();
            $table->year('last_visit_year')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_migration');
    }
};
