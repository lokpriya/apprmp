<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstitutionType extends Model
{
    protected $fillable = ['name_np','name_en','slug','icon','has_education','has_health','sort_order','is_active'];
    protected $casts = ['has_education'=>'boolean','has_health'=>'boolean','is_active'=>'boolean'];
    public function institutions(): HasMany { return $this->hasMany(Institution::class); }
}
