<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Cache;

class EditRequest extends Model
{
    protected $fillable = [
        'requestable_type',
        'requestable_id',
        'requested_by',
        'reviewed_by',
        'status',
        'reason',
        'admin_note',
    ];

    /* ── Cache invalidation ────────────────────────────────── */

    protected static function booted(): void
    {
        $bust = fn () => Cache::forget('edit_requests_pending');
        static::created($bust);
        static::updated($bust);
        static::deleted($bust);
    }

    /* ── Relationships ─────────────────────────────────────── */

    public function requestable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /* ── Helpers ───────────────────────────────────────────── */

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    /** Label of the requestable resource type in Nepali */
    public function resourceLabel(): string
    {
        return match ($this->requestable_type) {
            Household::class       => 'घरधुरी',
            Institution::class     => 'संस्था',
            MemberBusiness::class  => 'व्यवसाय',
            default                => 'रेकर्ड',
        };
    }
}
