<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionService extends Model
{
    protected $fillable = ['institution_id','service_name','service_type','capacity','annual_beneficiaries','coverage_description','is_free'];
    protected $casts = ['is_free'=>'boolean'];
    public function institution(): BelongsTo { return $this->belongsTo(Institution::class); }
}
