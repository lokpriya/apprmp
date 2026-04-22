<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LgRepresentative extends Model
{
    protected $fillable = [
        'local_government_id', 'name', 'position', 'ward_id',
        'birth_place', 'date_of_birth', 'ethnicity', 'gender', 'education',
        'address_ward_id', 'address_tole_id', 'contact', 'email', 'photo',
        'father_name', 'mother_name', 'spouse_name', 'elected_party',
        'work_experience', 'sort_order',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function localGovernment(): BelongsTo
    {
        return $this->belongsTo(LocalGovernment::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'ward_id');
    }

    public function addressWard(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'address_ward_id');
    }

    public function addressTole(): BelongsTo
    {
        return $this->belongsTo(Settlement::class, 'address_tole_id');
    }
}
