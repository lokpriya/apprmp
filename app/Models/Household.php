<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Household extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'unique_code', 'ward_id', 'settlement_id', 'enumerator_id',
        'respondent_name', 'respondent_contact', 'total_members',
        'gps_lat', 'gps_lng', 'gps_altitude', 'gps_accuracy', 'photo_number',
        'status', 'survey_date', 'submitted_at', 'ward_reviewed_at',
        'ward_reviewer_id', 'ward_review_comment', 'published_at',
        'published_by', 'data_frozen_at', 'remarks', 'survey_data',
        'sync_source', 'sync_hash', 'sync_locked',
    ];

    protected $casts = [
        'survey_date'       => 'date',
        'submitted_at'      => 'datetime',
        'ward_reviewed_at'  => 'datetime',
        'published_at'      => 'datetime',
        'data_frozen_at'    => 'datetime',
        'gps_lat'           => 'decimal:8',
        'gps_lng'           => 'decimal:8',
        'survey_data'       => 'array',
        'sync_locked'       => 'boolean',
    ];

    public function ward(): BelongsTo      { return $this->belongsTo(Ward::class); }
    public function settlement(): BelongsTo { return $this->belongsTo(Settlement::class); }
    public function enumerator(): BelongsTo { return $this->belongsTo(User::class, 'enumerator_id'); }
    public function wardReviewer(): BelongsTo { return $this->belongsTo(User::class, 'ward_reviewer_id'); }
    public function publisher(): BelongsTo  { return $this->belongsTo(User::class, 'published_by'); }

    public function housing(): HasOne         { return $this->hasOne(HouseholdHousing::class); }
    public function utilities(): HasOne       { return $this->hasOne(HouseholdUtility::class); }
    public function economic(): HasOne        { return $this->hasOne(HouseholdEconomic::class); }
    public function agriculture(): HasOne     { return $this->hasOne(HouseholdAgriculture::class); }
    public function disaster(): HasOne        { return $this->hasOne(HouseholdDisaster::class); }
    public function members(): HasMany        { return $this->hasMany(HouseholdMember::class)->orderBy('sort_order'); }
    public function livestock(): HasMany      { return $this->hasMany(HouseholdLivestock::class); }
    public function submissionLogs(): HasMany { return $this->hasMany(SubmissionLog::class, 'loggable_id')->where('loggable_type', self::class)->latest(); }
    public function fieldVerification(): MorphOne { return $this->morphOne(FieldVerification::class, 'verifiable'); }

    public function isPublished(): bool   { return $this->status === 'published'; }
    public function isDraft(): bool       { return $this->status === 'draft'; }
    public function isSubmitted(): bool   { return $this->status === 'submitted'; }
    public function isFrozen(): bool      { return $this->data_frozen_at !== null; }

    public function head(): ?HouseholdMember
    {
        return $this->members()->where('is_household_head', true)->first();
    }
}
