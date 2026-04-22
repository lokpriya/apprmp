<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('household_economic', function (Blueprint $table) {
            $table->json('income_rows')->nullable()->after('income_source');
            $table->json('expense_rows')->nullable()->after('income_rows');
        });
    }
    public function down(): void {
        Schema::table('household_economic', function (Blueprint $table) {
            $table->dropColumn(['income_rows', 'expense_rows']);
        });
    }
};
