<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberBusiness extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'household_member_id', 'household_id', 'ward_id', 'settlement_id', 'enumerator_id',
        'unique_code', 'status',
        'submitted_at', 'ward_reviewer_id', 'ward_reviewed_at', 'ward_review_comment',
        'published_by', 'published_at',
        // Step 1
        'business_name', 'owner_father_name', 'owner_grand_father_name',
        'business_location', 'business_type', 'business_type_other',
        // Step 2
        'business_field', 'business_field_other', 'business_sub_category',
        // Step 3
        'is_registered', 'place_of_registration', 'place_of_registration_other',
        'reg_no_municipality', 'reg_no_home_cottage', 'reg_no_company_registrar',
        'reg_no_fcci', 'reg_no_govt_office',
        'has_pan_vat', 'pan_vat_number', 'paid_business_tax', 'tax_amount', 'contact_number',
        'sync_source', 'sync_hash', 'sync_locked',
    ];

    protected $casts = [
        'submitted_at'         => 'datetime',
        'ward_reviewed_at'     => 'datetime',
        'published_at'         => 'datetime',
        'place_of_registration'=> 'array',
        'is_registered'        => 'boolean',
        'has_pan_vat'          => 'boolean',
        'paid_business_tax'    => 'boolean',
        'tax_amount'           => 'decimal:2',
        'sync_locked'          => 'boolean',
    ];

    public function member(): BelongsTo       { return $this->belongsTo(HouseholdMember::class, 'household_member_id'); }
    public function household(): BelongsTo    { return $this->belongsTo(Household::class); }
    public function ward(): BelongsTo         { return $this->belongsTo(Ward::class); }
    public function settlement(): BelongsTo   { return $this->belongsTo(Settlement::class); }
    public function enumerator(): BelongsTo   { return $this->belongsTo(User::class, 'enumerator_id'); }
    public function fieldVerification(): MorphOne { return $this->morphOne(FieldVerification::class, 'verifiable'); }
    public function wardReviewer(): BelongsTo { return $this->belongsTo(User::class, 'ward_reviewer_id'); }
    public function publisher(): BelongsTo    { return $this->belongsTo(User::class, 'published_by'); }

    public function isDraft(): bool      { return $this->status === 'draft'; }
    public function isSubmitted(): bool  { return $this->status === 'submitted'; }
    public function isPublished(): bool  { return $this->status === 'published'; }
}
