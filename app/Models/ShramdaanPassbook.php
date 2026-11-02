<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One family's श्रमदान passbook. Who the family is comes live from the household. */
class ShramdaanPassbook extends Model
{
    protected $fillable = [
        'household_id', 'passbook_no', 'registered_on', 'head_citizenship',
        'certifying_officer', 'remarks', 'created_by',
    ];

    protected $casts = [
        'registered_on'    => 'date',
        'head_citizenship' => 'encrypted',
    ];

    // Never serialised by accident; the passbook PDF reads it explicitly.
    protected $hidden = ['head_citizenship'];

    public function household(): BelongsTo     { return $this->belongsTo(Household::class); }
    public function memberDetails(): HasMany   { return $this->hasMany(ShramdaanMemberDetail::class, 'passbook_id'); }
    public function entries(): HasMany         { return $this->hasMany(ShramdaanEntry::class, 'passbook_id'); }

    /** The number printed on the passbook: "SD-" + the household number. */
    public static function numberFor(Household $household): string
    {
        return 'SD-' . $household->unique_code;
    }
}
