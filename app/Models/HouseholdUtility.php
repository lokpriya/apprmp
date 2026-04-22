<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdUtility extends Model
{
    protected $table = 'household_utilities';
    protected $fillable = [
        'household_id','water_source','water_distance_minutes','has_toilet','toilet_type',
        'sanitation_type','electricity_source','monthly_electricity_units',
        'cooking_fuel','cooking_fuel_arr','uses_firewood','firewood_source',
        'cooking_stove','has_alt_electricity','alt_electricity_sources',
        'has_biogas','solid_waste_management','has_internet','internet_type',
        'handwashing','access_to_facilities','vehicle','type_of_vehicle','vehicles',
    ];
    protected $casts = [
        'has_toilet'=>'boolean','has_biogas'=>'boolean','has_internet'=>'boolean',
        'access_to_facilities'=>'array','type_of_vehicle'=>'array',
        'cooking_fuel_arr'=>'array','firewood_source'=>'array',
        'alt_electricity_sources'=>'array','vehicles'=>'array',
    ];
    public function household(): BelongsTo { return $this->belongsTo(Household::class); }
}
