<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionInfrastructure extends Model
{
    protected $table = 'institution_infrastructure';

    protected $fillable = [
        'institution_id',
        'buildings',
        'total_area', 'condition',
        'total_rooms', 'total_floors',
        'has_boundary_wall',
        'toilet_count', 'has_toilet', 'male_toilet', 'female_toilet', 'disabled_toilet',
        'has_electricity', 'has_water', 'has_internet', 'has_ramp',
        'solar', 'generator', 'fire_extinguisher', 'earthquake_safe', 'evacuation_plan',
        'has_library', 'has_lab', 'total_classrooms',
    ];
    protected $casts = [
        'buildings'        => 'array',
        'has_boundary_wall'=> 'boolean',
        'has_toilet'       => 'boolean',
        'disabled_toilet'  => 'boolean',
        'has_electricity'  => 'boolean',
        'has_water'        => 'boolean',
        'has_internet'     => 'boolean',
        'has_ramp'         => 'boolean',
        'solar'            => 'boolean',
        'generator'        => 'boolean',
        'fire_extinguisher'=> 'boolean',
        'earthquake_safe'  => 'boolean',
        'evacuation_plan'  => 'boolean',
        'has_library'      => 'boolean',
        'has_lab'          => 'boolean',
    ];
    public function institution(): BelongsTo { return $this->belongsTo(Institution::class); }
}
