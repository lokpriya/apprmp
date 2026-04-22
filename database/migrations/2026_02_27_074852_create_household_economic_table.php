<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_economic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('primary_income_source', ['agriculture', 'business', 'service_govt', 'service_private', 'foreign_employment', 'daily_wage', 'pension', 'remittance', 'other'])->nullable();
            $table->enum('annual_income_range', ['below_50k', '50k_100k', '100k_200k', '200k_500k', '500k_1m', 'above_1m'])->nullable();
            $table->enum('annual_expenditure_range', ['below_50k', '50k_100k', '100k_200k', '200k_500k', '500k_1m', 'above_1m'])->nullable();
            $table->decimal('remittance_amount', 12, 2)->nullable();
            $table->json('loan_sources')->nullable();
            $table->decimal('total_loan_amount', 12, 2)->nullable();
            $table->boolean('has_bank_account')->default(false);
            $table->boolean('has_insurance')->default(false);
            $table->boolean('poverty_card')->default(false);
            $table->boolean('social_security_allowance')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_economic');
    }
};
