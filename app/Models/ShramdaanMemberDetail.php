<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What the tole coordinator adds about one member: citizenship no., skill, able to work. */
class ShramdaanMemberDetail extends Model
{
    protected $fillable = [
        'passbook_id', 'household_member_id', 'member_name', 'citizenship', 'skill', 'excluded',
    ];

    protected $casts = [
        'citizenship' => 'encrypted',
        'excluded'    => 'boolean',
    ];

    protected $hidden = ['citizenship'];

    public function passbook(): BelongsTo { return $this->belongsTo(ShramdaanPassbook::class, 'passbook_id'); }
    public function member(): BelongsTo   { return $this->belongsTo(HouseholdMember::class, 'household_member_id'); }
}
