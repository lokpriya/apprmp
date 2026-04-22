<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LgEmployee extends Model
{
    protected $fillable = [
        'local_government_id',
        'name', 'position', 'department', 'entry_date',
        'date_of_birth', 'birth_place', 'ethnicity', 'gender', 'education',
        'permanent_address', 'contact', 'email', 'photo',
        'facebook', 'instagram', 'tiktok', 'twitter_x',
        'father_name', 'mother_name', 'spouse_name', 'work_experience',
        'sort_order',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'entry_date'    => 'date',
    ];
}
