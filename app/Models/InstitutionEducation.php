<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionEducation extends Model
{
    protected $fillable = ['institution_id','grade','male_students','female_students','dalit_students','janajati_students','scholarship_students','dropout_count'];
    public function institution(): BelongsTo { return $this->belongsTo(Institution::class); }
    public function totalStudents(): int { return $this->male_students + $this->female_students; }
}
