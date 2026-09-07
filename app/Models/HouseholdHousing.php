<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdHousing extends Model
{
    protected $table = 'household_housing';
    protected $fillable = [
        'household_id',
        'house_land_type','land_measurement_unit','house_type','house_purpose','location_house_built',
        'roof_type','wall_type','foundation_type','ownership_type',
        'rent_amount','distance_to_mainroad','distance_to_school',
        'total_rooms','total_floors','has_building_permit',
        'construction_year','house_condition','is_at_risk','risk_types',
        'has_land_in_municipality',
    ];
    protected $casts = [
        'risk_types'=>'array','has_building_permit'=>'boolean','is_at_risk'=>'boolean',
    ];
    public function household(): BelongsTo { return $this->belongsTo(Household::class); }
}
