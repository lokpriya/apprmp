<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LgPhoto extends Model
{
    protected $fillable = [
        'local_government_id',
        'photo_no', 'category', 'title', 'subtitle',
        'gps_location', 'description', 'photo_date',
        'photo_credit', 'photo', 'sort_order',
    ];

    protected $casts = [
        'photo_date' => 'date',
    ];
}
