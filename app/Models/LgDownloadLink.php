<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LgDownloadLink extends Model
{
    protected $fillable = [
        'local_government_id',
        'title',
        'description',
        'url',
        'thumbnail',
        'created_date',
        'sort_order',
    ];

    protected $casts = [
        'created_date' => 'date',
    ];

    public function localGovernment(): BelongsTo
    {
        return $this->belongsTo(LocalGovernment::class);
    }
}
