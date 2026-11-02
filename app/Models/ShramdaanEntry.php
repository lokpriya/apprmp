<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One member's work on one day. Entered by the tole coordinator as "recorded";
 * only "verified" entries — checked by the ward — count towards the श्रेणी and
 * print as ✓ प्रमाणित.
 */
class ShramdaanEntry extends Model
{
    use SoftDeletes;

    public const RECORDED = 'recorded';
    public const VERIFIED = 'verified';

    protected $fillable = [
        'passbook_id', 'household_member_id', 'member_name', 'project_id', 'project_name',
        'work_date', 'work_date_bs', 'fiscal_year', 'hours', 'days', 'work_nature', 'supervisor',
        'status', 'entered_by', 'verified_by', 'verified_at',
    ];

    protected $casts = [
        'work_date'   => 'date',
        'hours'       => 'decimal:2',
        'days'        => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    public function passbook(): BelongsTo { return $this->belongsTo(ShramdaanPassbook::class, 'passbook_id'); }
    public function member(): BelongsTo   { return $this->belongsTo(HouseholdMember::class, 'household_member_id'); }
    public function project(): BelongsTo  { return $this->belongsTo(ShramdaanProject::class, 'project_id'); }

    public function isVerified(): bool { return $this->status === self::VERIFIED; }
}
