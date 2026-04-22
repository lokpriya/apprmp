<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends Model
{
    protected $fillable = ['ward_id', 'name_np', 'name_en', 'settlement_type'];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function households(): HasMany
    {
        return $this->hasMany(Household::class);
    }

    public function institutions(): HasMany
    {
        return $this->hasMany(Institution::class);
    }
}
