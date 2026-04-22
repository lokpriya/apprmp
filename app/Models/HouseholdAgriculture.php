<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdAgriculture extends Model
{
    protected $table = 'household_agriculture';
    protected $fillable = [
        'household_id',
        'agriculture','agri_categories',
        'use_of_chem_fertilizer','chem_fertilizer_types','chem_fertilizer_kg','fertilizer_sources',
        'use_of_chem_pesticide','chem_pesticide_types','chem_pesticide_cost',
        'Farm_Challenges',
        'owns_land','land_area_ropani','irrigated_area_ropani',
        'annual_agri_income',
    ];
    protected $casts = [
        'agri_categories'=>'array',
        'chem_fertilizer_types'=>'array','fertilizer_sources'=>'array',
        'chem_pesticide_types'=>'array',
        'Farm_Challenges'=>'array',
        'owns_land'=>'boolean',
    ];
    public function household(): BelongsTo { return $this->belongsTo(Household::class); }
}
