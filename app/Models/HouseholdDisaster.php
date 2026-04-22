<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdDisaster extends Model
{
    protected $table = 'household_disaster';
    protected $fillable = [
        'household_id',
        // v2 fields
        'has_death_this_year', 'death_rows',
        'risk_types', 'risk_reduction_plan',
        'affected_last_3years', 'human_loss_rows', 'physical_loss_rows',
        'received_relief', 'relief_rows',
        // legacy fields (kept for migration safety)
        'is_flood_zone','is_landslide_zone','is_drought_zone',
        'past_events','has_evacuation_plan','nearest_relief_center','disaster_training',
        'family_disaster_plan','ThreeNDvictims','natural_disasters',
        'victimHEarthQuake','victimHLandslide','victimHFire','victimHFlood','victimHEpidemic',
    ];
    protected $casts = [
        'risk_types'=>'array','past_events'=>'array','natural_disasters'=>'array',
        'death_rows'=>'array','human_loss_rows'=>'array','physical_loss_rows'=>'array','relief_rows'=>'array',
        'is_flood_zone'=>'boolean','is_landslide_zone'=>'boolean','is_drought_zone'=>'boolean',
        'has_evacuation_plan'=>'boolean','disaster_training'=>'boolean',
    ];
    public function household(): BelongsTo { return $this->belongsTo(Household::class); }
}
