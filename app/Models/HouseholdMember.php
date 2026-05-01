<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HouseholdMember extends Model
{
    protected $fillable = [
        'household_id','is_household_head','full_name','father_name','relation_to_head',
        'date_of_birth','age','gender','ethnicity','religion','mother_tongue',
        // Marital
        'marital_status','ageofmarriage',
        // Blood group
        'blood_group','blood_group_tested',
        // Birth certificate (age ≤ 18)
        'has_birth_certificate',
        // ── Age 0–3 child fields ──────────────────────────────────────────
        'childbornplace','child_weight_in_birth',
        'colostrum','childvaccine','vit_a_dewormin','fullmilkcourse','durationbrestfeed',
        'childhealthcheckup','childbmi','child_caregiver',
        'diarrhea_before_two_weeks','fever_resp',
        // ── Age 4–5 child fields ──────────────────────────────────────────
        'vitaminA','childphydev','childlgdev','childsocialization',
        // ── Age 6–15 child fields ─────────────────────────────────────────
        'phy_dev_6_15','childphydev_6_15','childlgdev_6_15',
        'school_interest_6_15','interesting_subject_6_15','hw_support_6_15',
        'allergy_infection_6_15','watch_mobile_tv_6_15','digital_habit_6_15','other_interest_6_15',
        // Child labor
        'child_labor','workplace_child_labor',
        // Disability
        'disability_status','disability_type','disability_card','disability_card_color',
        // Chronic illness (age > 15)
        'chronic_illness','chronic_illness_types',
        // Contact
        'contact_phone','national_id',
        // Education
        'education_level','education_status','current_class','higher_education_level',
        'school_type','school_performance','school_location','school_name','cause_not_school',
        // Literacy & occupation (age > 10)
        'is_literate','occupation_type','employment_status',
        // Family
        'no_birth_given',
        // Lifestyle (age ≥ 16)
        'smoking_drinking','smoking_drinking_items','frequency_smoking_drinking',
        // Social security
        'ss_allowance',
        // Documents (age ≥ 16)
        'has_citizenship','no_citizenship_reason',
        'has_voters_card','has_national_id_card',
        'has_passport','passport_info','passport_number','passport_issued_district',
        'passport_issued_date','passport_expiry_date',
        'has_pan',
        'has_drivers_license','drivers_license_category',
        // Contact info (age > 15)
        'has_phone','know_phone','member_phone',
        'has_email','know_email','member_email',
        // Vocational training (age > 15)
        'vocational_training','vt_subject','vt_duration_min','vt_duration_max',
        // Technical training (age > 15)
        'techtraining','techtraining_subject','tech_duration_min','tech_duration_max',
        // SA training (age > 15)
        'sa_training','sa_training_subject','sa_duration_min','sa_duration_max',
        // Address / location
        'addrslocation','residing_district','purpose_living_outside','duration_of_stay',
        'residing_country','purpose_living_abroad','typeofabroadjob','abroadjobstatus',
        // Business (age > 15)
        'involveinbusiness','business_name',
        // Residence
        'residence_status','remarks','sort_order',
        // Deceased
        'is_deceased','death_date','death_cause','death_remarks',
    ];

    protected $casts = [
        'date_of_birth'              => 'date',
        'death_date'                 => 'date',
        'is_household_head'          => 'boolean',
        'is_deceased'                => 'boolean',
        'is_literate'                => 'boolean',
        // Age 0-3
        'colostrum'                  => 'boolean',
        'childvaccine'               => 'array',
        'vit_a_dewormin'             => 'boolean',
        'fullmilkcourse'             => 'boolean',
        'childhealthcheckup'         => 'boolean',
        'childbmi'                   => 'boolean',
        'diarrhea_before_two_weeks'  => 'boolean',
        'fever_resp'                 => 'boolean',
        // Age 4-5
        'vitaminA'                   => 'boolean',
        // Age 6-15
        'phy_dev_6_15'               => 'boolean',
        'allergy_infection_6_15'     => 'boolean',
        'watch_mobile_tv_6_15'       => 'boolean',
        'child_labor'                => 'boolean',
        // Disability
        'disability_status'          => 'boolean',
        // Chronic illness
        'chronic_illness'            => 'boolean',
        'chronic_illness_types'      => 'array',
        // Education
        'cause_not_school'           => 'array',
        // Documents
        'has_birth_certificate'      => 'boolean',
        'has_citizenship'            => 'boolean',
        'has_voters_card'            => 'boolean',
        'has_national_id_card'       => 'boolean',
        'has_passport'               => 'boolean',
        'has_pan'                    => 'boolean',
        'has_drivers_license'        => 'boolean',
        'drivers_license_category'   => 'array',
        // Lifestyle
        'smoking_drinking'           => 'boolean',
        'smoking_drinking_items'     => 'array',
        // Social security
        'ss_allowance'               => 'boolean',
        // Contact info
        'has_phone'                  => 'boolean',
        'know_phone'                 => 'boolean',
        'has_email'                  => 'boolean',
        'know_email'                 => 'boolean',
        // Training
        'vocational_training'        => 'boolean',
        'techtraining'               => 'boolean',
        'sa_training'                => 'boolean',
        // Address
        'residing_country'           => 'array',
        // Business
        'involveinbusiness'          => 'boolean',
    ];

    public function scopeAlive($query) { return $query->where('is_deceased', false); }

    public function household(): BelongsTo { return $this->belongsTo(Household::class); }
    public function migration(): HasOne     { return $this->hasOne(MemberMigration::class, 'member_id'); }
    public function health(): HasOne        { return $this->hasOne(MemberHealth::class, 'member_id'); }
    public function business(): HasOne      { return $this->hasOne(MemberBusiness::class, 'household_member_id'); }
}
