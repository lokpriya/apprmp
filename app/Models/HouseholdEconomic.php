<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdEconomic extends Model
{
    protected $table = 'household_economic';
    protected $fillable = [
        'household_id','primary_income_source','annual_income_range','annual_expenditure_range',
        'remittance_amount','loan_sources','total_loan_amount','has_bank_account',
        'has_insurance','poverty_card','social_security_allowance',
        'income_source',
        'agri_livestock_income','business_industry_income','employment_service_income','abroad_job_income',
        'food_expense','clothes_expense','education_expense','treatment_expense','other_expense',
        'income_rows','expense_rows',
    ];
    protected $casts = [
        'loan_sources'=>'array','income_source'=>'array',
        'income_rows'=>'array','expense_rows'=>'array',
        'has_bank_account'=>'boolean','has_insurance'=>'boolean',
        'poverty_card'=>'boolean','social_security_allowance'=>'boolean',
    ];
    public function household(): BelongsTo { return $this->belongsTo(Household::class); }
}
