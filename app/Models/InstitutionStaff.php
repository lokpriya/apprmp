<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionStaff extends Model
{
    protected $fillable = ['institution_id','staff_category','male_count','female_count','dalit_count','janajati_count','other_count','permanent_count','contract_count'];
    public function institution(): BelongsTo { return $this->belongsTo(Institution::class); }
    public function totalCount(): int { return $this->male_count + $this->female_count; }
}
