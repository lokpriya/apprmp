<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberHealth extends Model
{
    protected $table = 'member_health';
    protected $fillable = [
        'member_id','has_health_insurance','delivery_type','antenatal_checkups',
        'child_immunization','malnutrition',
    ];
    protected $casts = [
        'has_health_insurance'=>'boolean','child_immunization'=>'boolean','malnutrition'=>'boolean',
    ];
    public function member(): BelongsTo { return $this->belongsTo(HouseholdMember::class, 'member_id'); }
}
