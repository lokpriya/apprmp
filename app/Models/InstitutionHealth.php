<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionHealth extends Model
{
    protected $table = 'institution_health';
    protected $fillable = [
        'institution_id',
        'health_type',
        'bed_count', 'male_ward_beds', 'female_ward_beds', 'children_ward_beds',
        'maternity_beds', 'icu_beds', 'emergency_beds',
        'opd_daily', 'opd_monthly', 'ipd_monthly', 'annual_delivery_count',
        'has_ambulance', 'has_xray', 'has_lab', 'has_ultrasound', 'has_ecg',
        'has_icu', 'has_operation_theatre', 'has_blood_bank', 'has_dialysis',
        'has_dental', 'has_physiotherapy', 'has_oxygen_plant',
        'health_services', 'equipment', 'lab_services',
        'delivery_service', 'emergency_service', 'vaccine_service', 'family_planning',
    ];
    protected $casts = [
        'has_ambulance'        => 'boolean',
        'has_xray'             => 'boolean',
        'has_lab'              => 'boolean',
        'has_ultrasound'       => 'boolean',
        'has_ecg'              => 'boolean',
        'has_icu'              => 'boolean',
        'has_operation_theatre'=> 'boolean',
        'has_blood_bank'       => 'boolean',
        'has_dialysis'         => 'boolean',
        'has_dental'           => 'boolean',
        'has_physiotherapy'    => 'boolean',
        'has_oxygen_plant'     => 'boolean',
        'delivery_service'     => 'boolean',
        'emergency_service'    => 'boolean',
        'vaccine_service'      => 'boolean',
        'family_planning'      => 'boolean',
        'health_services'      => 'array',
        'equipment'            => 'array',
        'lab_services'         => 'array',
    ];
    public function institution(): BelongsTo { return $this->belongsTo(Institution::class); }
}
