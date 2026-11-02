<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A योजना that families give श्रमदान to, listed per ward (optionally per tole) and fiscal year. */
class ShramdaanProject extends Model
{
    protected $fillable = ['ward_id', 'settlement_id', 'name', 'fiscal_year', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    public function ward(): BelongsTo       { return $this->belongsTo(Ward::class); }
    public function settlement(): BelongsTo { return $this->belongsTo(Settlement::class); }
    public function entries(): HasMany      { return $this->hasMany(ShramdaanEntry::class, 'project_id'); }
}
