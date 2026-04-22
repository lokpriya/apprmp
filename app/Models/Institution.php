<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institution extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'unique_code','ward_id','settlement_id','institution_type_id','name_np','name_en',
        'established_date','ownership_type','registration_number','enumerator_id',
        'gps_lat','gps_lng','gps_altitude','gps_accuracy','photo_number','status','survey_date','submitted_at','ward_reviewed_at',
        'ward_reviewer_id','ward_review_comment','published_at','published_by',
        'data_frozen_at','remarks','respondent_name','respondent_contact','survey_data',
    ];
    protected $casts = [
        'established_date'=>'date','survey_date'=>'date',
        'survey_data'=>'array',
        'submitted_at'=>'datetime','ward_reviewed_at'=>'datetime','published_at'=>'datetime','data_frozen_at'=>'datetime',
    ];

    public function ward(): BelongsTo          { return $this->belongsTo(Ward::class); }
    public function settlement(): BelongsTo    { return $this->belongsTo(Settlement::class); }
    public function institutionType(): BelongsTo { return $this->belongsTo(InstitutionType::class); }
    public function enumerator(): BelongsTo    { return $this->belongsTo(User::class, 'enumerator_id'); }
    public function wardReviewer(): BelongsTo  { return $this->belongsTo(User::class, 'ward_reviewer_id'); }
    public function publisher(): BelongsTo     { return $this->belongsTo(User::class, 'published_by'); }

    public function infrastructure(): HasOne   { return $this->hasOne(InstitutionInfrastructure::class); }
    public function healthDetail(): HasOne     { return $this->hasOne(InstitutionHealth::class); }
    public function services(): HasMany        { return $this->hasMany(InstitutionService::class); }
    public function staff(): HasMany           { return $this->hasMany(InstitutionStaff::class); }
    public function education(): HasMany       { return $this->hasMany(InstitutionEducation::class)->orderBy('grade'); }
    public function submissionLogs(): HasMany  { return $this->hasMany(SubmissionLog::class, 'loggable_id')->where('loggable_type', self::class)->latest(); }

    public function isPublished(): bool { return $this->status === 'published'; }
    public function isDraft(): bool     { return $this->status === 'draft'; }
    public function isFrozen(): bool    { return $this->data_frozen_at !== null; }

    public function isSchool(): bool    { return $this->institutionType?->has_education ?? false; }
    public function isHealth(): bool    { return $this->institutionType?->has_health ?? false; }
}
