<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdLivestock extends Model
{
    protected $table = 'household_livestock';
    protected $fillable = ['household_id','livestock_type','count','vet_service_access','insurance'];
    protected $casts = ['vet_service_access'=>'boolean','insurance'=>'boolean'];
    public function household(): BelongsTo { return $this->belongsTo(Household::class); }
}
