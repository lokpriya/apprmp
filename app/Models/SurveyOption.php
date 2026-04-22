<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SurveyOption extends Model
{
    protected $fillable = [
        'category', 'value', 'label_ne', 'label_en',
        'sort_order', 'is_active', 'local_government_id',
    ];

    protected $casts = ['is_active' => 'boolean'];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function localGovernment(): BelongsTo
    {
        return $this->belongsTo(LocalGovernment::class);
    }

    // ── Query helpers ─────────────────────────────────────────────────────────

    /** Fetch active options for a category (global + LG-specific merged, LG wins). */
    public static function forCategory(string $category, ?int $lgId = null): Collection
    {
        $key = "survey_options.{$category}." . ($lgId ?? 'global');
        return Cache::remember($key, 3600, function () use ($category, $lgId) {
            return static::where('category', $category)
                ->where('is_active', true)
                ->where(function ($q) use ($lgId) {
                    $q->whereNull('local_government_id');
                    if ($lgId) $q->orWhere('local_government_id', $lgId);
                })
                ->orderBy('sort_order')
                ->orderBy('label_ne')
                ->get();
        });
    }

    /** Return as ['value' => 'label_ne'] associative array for use in blade. */
    public static function asArray(string $category, ?int $lgId = null): array
    {
        return static::forCategory($category, $lgId)
            ->pluck('label_ne', 'value')
            ->toArray();
    }

    /** Fetch all active options grouped by category for a given LG. */
    public static function allGrouped(?int $lgId = null): Collection
    {
        $key = "survey_options.all." . ($lgId ?? 'global');
        return Cache::remember($key, 3600, function () use ($lgId) {
            return static::where('is_active', true)
                ->where(function ($q) use ($lgId) {
                    $q->whereNull('local_government_id');
                    if ($lgId) $q->orWhere('local_government_id', $lgId);
                })
                ->orderBy('sort_order')
                ->orderBy('label_ne')
                ->get()
                ->groupBy('category')
                ->map(fn($items) => $items->pluck('label_ne', 'value')->toArray());
        });
    }

    /** Clear all cache entries for a category. */
    public static function clearCache(string $category, ?int $lgId = null): void
    {
        Cache::forget("survey_options.{$category}." . ($lgId ?? 'global'));
        Cache::forget("survey_options.all." . ($lgId ?? 'global'));
        Cache::forget("survey_options.all.global");
    }

    // ── Category registry ─────────────────────────────────────────────────────

    /** Institution-specific categories (keys starting with 'inst_'). */
    public static function institutionCategories(): array
    {
        return array_filter(static::categories(), fn($k) => str_starts_with($k, 'inst_'), ARRAY_FILTER_USE_KEY);
    }

    /** Business-specific categories (keys starting with 'business_' or 'sub_'). */
    public static function businessCategories(): array
    {
        return array_filter(static::categories(), fn($k) =>
            str_starts_with($k, 'business_') || str_starts_with($k, 'sub_'),
            ARRAY_FILTER_USE_KEY
        );
    }

    /** Household categories (keys not belonging to institution or business). */
    public static function householdCategories(): array
    {
        return array_filter(static::categories(), fn($k) =>
            !str_starts_with($k, 'inst_') &&
            !str_starts_with($k, 'business_') &&
            !str_starts_with($k, 'sub_'),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Institution categories grouped by form step, for sidebar navigation.
     * 8 sections matching the 8-step institution form structure.
     */
    public static function institutionCategorySections(): array
    {
        return [
            [
                'step'     => 1,
                'title_ne' => 'सामान्य विवरण',
                'title_en' => 'General Details',
                'keys'     => ['inst_ownership_type', 'inst_building_type', 'inst_building_condition'],
            ],
            [
                'step'     => 2,
                'title_ne' => 'कर्मचारी तथा सेवा',
                'title_en' => 'Staff & Services',
                'keys'     => ['inst_staff_category', 'inst_service_type', 'inst_committee_position'],
            ],
            [
                'step'     => 3,
                'title_ne' => 'शिक्षा विवरण',
                'title_en' => 'Education Details',
                'keys'     => [
                    'inst_school_level', 'inst_management_type',
                    'inst_medium', 'inst_grade', 'inst_teacher_category',
                    'inst_teacher_qualification', 'inst_teacher_subject', 'inst_teacher_level',
                ],
            ],
            [
                'step'     => 4,
                'title_ne' => 'स्वास्थ्य विवरण',
                'title_en' => 'Health Details',
                'keys'     => [
                    'inst_health_type', 'inst_health_service',
                    'inst_health_equipment', 'inst_health_staff_type', 'inst_lab_service',
                ],
            ],
            [
                'step'     => 5,
                'title_ne' => 'वित्त तथा सहकारी',
                'title_en' => 'Finance & Cooperative',
                'keys'     => ['inst_coop_type', 'inst_bank_type'],
            ],
            [
                'step'     => 6,
                'title_ne' => 'सरकारी कार्यालय / NGO',
                'title_en' => 'Govt Office / NGO',
                'keys'     => ['inst_office_level', 'inst_registration_type', 'inst_working_area'],
            ],
            [
                'step'     => 7,
                'title_ne' => 'धर्म / खेल / उद्योग / मिडिया',
                'title_en' => 'Religion / Sports / Industry / Media',
                'keys'     => [
                    'inst_religion_type', 'inst_game_type',
                    'inst_industry_type', 'inst_media_type', 'inst_market_day',
                ],
            ],
            [
                'step'     => 8,
                'title_ne' => 'वन तथा प्राकृतिक स्रोत',
                'title_en' => 'Forest & Natural Resources',
                'keys'     => [
                    'inst_forest_type', 'inst_tree_type',
                    'inst_herb_type', 'inst_wildlife', 'inst_forest_product',
                ],
            ],
        ];
    }

    /**
     * Business categories grouped into sections for sidebar navigation.
     */
    public static function businessCategorySections(): array
    {
        return [
            [
                'step'     => 1,
                'title_ne' => 'व्यवसाय पहिचान',
                'title_en' => 'Business Identification',
                'keys'     => ['business_location', 'business_type'],
            ],
            [
                'step'     => 2,
                'title_ne' => 'व्यवसाय क्षेत्र',
                'title_en' => 'Business Field',
                'keys'     => ['business_field'],
            ],
            [
                'step'     => 3,
                'title_ne' => 'उप-वर्गहरू',
                'title_en' => 'Sub-categories',
                'keys'     => [
                    'sub_ProductionIndustry', 'sub_AgricultureAnimalHusbandary',
                    'sub_Suppliers', 'sub_InformationAndCommunication',
                    'sub_HotelAndRestaurant', 'sub_VehicleAndTransportation',
                    'sub_EducationalInstutions', 'sub_ConsultancyBusiness',
                    'sub_MechinaryMaintenance', 'sub_HospitalandClinc',
                    'sub_FinancialInstutions', 'sub_AbroadjobsSecurity',
                    'sub_FancyBusiness', 'sub_OtherShopBusiness',
                ],
            ],
            [
                'step'     => 4,
                'title_ne' => 'दर्ता विवरण',
                'title_en' => 'Registration',
                'keys'     => ['business_registration_place'],
            ],
        ];
    }

    /**
     * Household categories grouped by form step, for sidebar navigation.
     * Returns [ ['step'=>1,'title_ne'=>'...','title_en'=>'...','keys'=>[...]], ... ]
     */
    public static function householdCategorySections(): array
    {
        return [
            [
                'step'     => 1,
                'title_ne' => 'घर विवरण',
                'title_en' => 'House Details',
                'keys'     => [
                    'house_land_type', 'land_measurement_unit', 'house_type', 'house_purpose',
                    'location_house_built', 'roof_type', 'wall_type', 'foundation_type',
                    'ownership_type', 'house_condition',
                ],
            ],
            [
                'step'     => 2,
                'title_ne' => 'परिवार तथा सामाजिक',
                'title_en' => 'Family & Social',
                'keys'     => [
                    'ethnicity', 'religion', 'language', 'family_type',
                    'major_occupation', 'settlement_type', 'migration_purpose',
                ],
            ],
            [
                'step'     => 3,
                'title_ne' => 'सरसफाई तथा सुविधा',
                'title_en' => 'Utilities & Sanitation',
                'keys'     => [
                    'water_source', 'toilet_type', 'sanitation_type', 'handwashing',
                    'nutrition_diet', 'treatment_place',
                    'electricity_source', 'alt_electricity_source',
                    'cooking_fuel', 'firewood_source', 'cooking_stove',
                    'waste_management', 'internet_type', 'facilities',
                ],
            ],
            [
                'step'     => 4,
                'title_ne' => 'यातायात र निर्णय',
                'title_en' => 'Transport & Decisions',
                'keys'     => [
                    'vehicle_type', 'decision_owner',
                    'residence_perception', 'family_gathering_freq',
                    'happiness_activities', 'neighbor_cooperation',
                ],
            ],
            [
                'step'     => 5,
                'title_ne' => 'व्यवसाय तथा रोजगारी',
                'title_en' => 'Occupation & Employment',
                'keys'     => [
                    'occupation_sector', 'technical_skill',
                ],
            ],
            [
                'step'     => 6,
                'title_ne' => 'ऋण तथा संस्था',
                'title_en' => 'Loans & Organisations',
                'keys'     => [
                    'loan_source', 'loan_purpose', 'org_type',
                ],
            ],
            [
                'step'     => 8,
                'title_ne' => 'खेतीपाती',
                'title_en' => 'Agriculture',
                'keys'     => [
                    'agri_category', 'chem_fertilizer', 'fertilizer_source',
                    'chem_pesticide', 'farm_challenge', 'irrigation_source',
                ],
            ],
            [
                'step'     => 9,
                'title_ne' => 'कृषि उत्पादन',
                'title_en' => 'Crop Production',
                'keys'     => [
                    'crop_food', 'crop_pulse', 'crop_oil', 'crop_vegetable',
                    'crop_cash', 'crop_fruit', 'crop_other', 'agri_production_unit',
                ],
            ],
            [
                'step'     => 10,
                'title_ne' => 'पशुपालन',
                'title_en' => 'Livestock',
                'keys'     => [
                    'livestock_kind', 'livestock_breed',
                    'livestock_rearing_method', 'livestock_product', 'livestock_product_unit',
                ],
            ],
            [
                'step'     => 11,
                'title_ne' => 'मौरी / माछा / रेशम',
                'title_en' => 'Bee / Fish / Silk',
                'keys'     => ['bee_fish_silk'],
            ],
            [
                'step'     => 12,
                'title_ne' => 'आम्दानी र खर्च',
                'title_en' => 'Income & Expenditure',
                'keys'     => ['income_source', 'expense_category'],
            ],
            [
                'step'     => 13,
                'title_ne' => 'प्राकृतिक प्रकोप',
                'title_en' => 'Disaster & Risk',
                'keys'     => [
                    'disaster_type', 'death_cause',
                    'disaster_property_loss', 'relief_type', 'relief_unit', 'relief_source',
                ],
            ],
            [
                'step'     => 14,
                'title_ne' => 'परिवार सदस्य',
                'title_en' => 'Family Members',
                'keys'     => [
                    'relation_to_head', 'marital_status',
                    'blood_group',
                    // Age 0-3
                    'childbornplace', 'childvaccine', 'caregiver',
                    // Age 4-5 & 6-15
                    'childphydev', 'childlgdev', 'childsocialization',
                    'schoolinterest', 'interestingsubject', 'hwsupport',
                    'digital_habit', 'other_interest',
                    // Disability
                    'disability_type', 'disability_card_color',
                    // Education
                    'education_level', 'education_status',
                    'school_type', 'school_performance', 'institutionlocation',
                    'cause_not_school',
                    // Occupation, residence
                    'occupation_type', 'employment_status', 'residence_status',
                    // Chronic illness
                    'chronic_illness',
                    // Lifestyle
                    'frequency_smoking_drinking', 'smoking_drinking_items',
                    'drivers_license_category',
                    // Phase 3: Training
                    'vocational_training_subject', 'technical_training_subject', 'sa_training_subject',
                    // Phase 3: Address / abroad
                    'addrslocation', 'purpose_living_outside',
                    'typeofabroadjob', 'abroadjobstatus', 'residing_country',
                ],
            ],
        ];
    }

    public static function categories(): array
    {
        return [
            // ── House Details
            'house_land_type'         => 'घरधुरी: जग्गाको किसिम (Land Type)',
            'land_measurement_unit'   => 'घरधुरी: जग्गाको नाप एकाइ (Land Measurement Unit)',
            'house_type'              => 'घरधुरी: घरको बनावट (House Type)',
            'house_purpose'           => 'घरधुरी: घरको उद्देश्य (House Purpose)',
            'location_house_built'    => 'घरधुरी: घर बनेको ठाउँको अवस्था (Location House Built)',
            'roof_type'               => 'घरधुरी: छानोको प्रकार (Roof Type)',
            'wall_type'               => 'घरधुरी: भित्ताको प्रकार (Wall Type)',
            'foundation_type'         => 'घरधुरी: जगको प्रकार (Foundation Type)',
            'ownership_type'          => 'घरधुरी: स्वामित्वको किसिम (Ownership Type)',
            'house_condition'         => 'घरधुरी: घरको अवस्था (House Condition)',
            // ── Social & Identity
            'ethnicity'               => 'घरधुरी: जाति/समुदाय (Ethnicity)',
            'religion'                => 'घरधुरी: धर्म (Religion)',
            'language'                => 'घरधुरी: भाषा (Language)',
            'family_type'             => 'घरधुरी: परिवारको किसिम (Family Type)',
            'major_occupation'        => 'घरधुरी: मुख्य पेशा (Major Occupation)',
            'settlement_type'         => 'घरधुरी: बसोबासको प्रकार (Settlement Type)',
            'migration_purpose'       => 'घरधुरी: बसाइसराइको उद्देश्य (Migration Purpose)',
            // ── Financial
            'loan_source'             => 'घरधुरी: ऋणको स्रोत (Loan Source)',
            'loan_purpose'            => 'घरधुरी: ऋणको उद्देश्य (Loan Purpose)',
            'org_type'                => 'घरधुरी: संस्थाको प्रकार (Org Type)',
            'income_source'           => 'घरधुरी: आम्दानीको स्रोत (Income Source)',
            'expense_category'        => 'घरधुरी: खर्च शिर्षक (Expense Category)',
            // ── Sanitation & Utilities
            'water_source'            => 'घरधुरी: खानेपानीको स्रोत (Water Source)',
            'toilet_type'             => 'घरधुरी: शौचालयको किसिम (Toilet Type)',
            'sanitation_type'         => 'घरधुरी: सरसफाइको प्रकार (Sanitation Type)',
            'handwashing'             => 'घरधुरी: हात धुने सुविधा (Handwashing Facility)',
            'nutrition_diet'          => 'घरधुरी: पोषण तथा सन्तुलित आहार (Nutrition & Diet)',
            'treatment_place'         => 'घरधुरी: उपचारको लागि जाने ठाउँ (Treatment Place)',
            'electricity_source'      => 'घरधुरी: विद्युतको स्रोत (Electricity Source)',
            'alt_electricity_source'  => 'घरधुरी: बत्तिको बैकल्पिक स्रोत (Alternative Electricity Source)',
            'cooking_fuel'            => 'घरधुरी: खाना पकाउने इन्धन (Cooking Fuel)',
            'firewood_source'         => 'घरधुरी: दाउराको स्रोत (Firewood Source)',
            'cooking_stove'           => 'घरधुरी: खाना पकाउने चुलो (Cooking Stove)',
            'waste_management'        => 'घरधुरी: फोहोर व्यवस्थापन (Waste Management)',
            'internet_type'           => 'घरधुरी: इन्टरनेटको किसिम (Internet Type)',
            'facilities'              => 'घरधुरी: सुविधाहरु (Facilities)',
            'vehicle_type'            => 'घरधुरी: सवारी साधनको किसिम (Vehicle Type)',
            // ── Agriculture
            'crop_food'               => 'घरधुरी: खाद्यान्न बाली (Food Crops)',
            'crop_pulse'              => 'घरधुरी: दलहन बाली (Pulses)',
            'crop_oil'                => 'घरधुरी: तेलहन बाली (Oil Crops)',
            'crop_vegetable'          => 'घरधुरी: तरकारी बाली (Vegetables)',
            'crop_cash'               => 'घरधुरी: नगदे बाली (Cash Crops)',
            'crop_fruit'              => 'घरधुरी: फलफूल (Fruits)',
            'farm_challenge'          => 'घरधुरी: कृषि समस्याहरु (Farm Challenges)',
            'irrigation_source'       => 'घरधुरी: सिँचाइ स्रोत (Irrigation Source)',
            // ── Livestock
            'livestock_kind'          => 'घरधुरी: पशुपक्षीको किसिम (Livestock Kind)',
            // ── Bee/Fish/Silk
            'bee_fish_silk'           => 'घरधुरी: मौरी/माछा/रेशम (Bee/Fish/Silk)',
            // ── Disaster
            'disaster_type'           => 'घरधुरी: प्रकोपको किसिम (Disaster Type)',
            'death_cause'             => 'घरधुरी: मृत्युको कारण (Death Cause)',
            'disaster_property_loss'  => 'घरधुरी: प्रकोप क्षतिको किसिम (Disaster Property Loss)',
            'relief_type'             => 'घरधुरी: राहतको किसिम (Relief Type)',
            'relief_unit'             => 'घरधुरी: राहत एकाइ (Relief Unit)',
            'relief_source'           => 'घरधुरी: राहतको स्रोत (Relief Source)',
            // ── Members
            'decision_owner'          => 'घरधुरी: निर्णय तथा स्वामित्व (Decision & Ownership)',
            'residence_perception'    => 'घरधुरी: बसोबास प्रति दृष्टिकोण (Residence Perception)',
            'family_gathering_freq'   => 'घरधुरी: परिवार भेटघाट (Family Get-Together Frequency)',
            'happiness_activities'    => 'घरधुरी: खुसीका लागि क्रियाकलाप (Happiness Activities)',
            'neighbor_cooperation'    => 'घरधुरी: छिमेकी सहाकार्य (Neighbor Cooperation)',
            'technical_skill'         => 'घरधुरी: प्राबिधिक सीप (Technical Skill)',
            'occupation_sector'       => 'घरधुरी: पेशागत क्षेत्र (Occupation Sector)',
            'agri_category'           => 'घरधुरी: मुख्य कृषि शिर्षकहरु (Agri Category)',
            'chem_fertilizer'         => 'घरधुरी: रासायनिक मल (Chemical Fertilizer)',
            'fertilizer_source'       => 'घरधुरी: रासायनिक मलको स्रोत (Fertilizer Source)',
            'chem_pesticide'          => 'घरधुरी: रासायनिक बिषादी (Chemical Pesticide)',
            'agri_production_unit'      => 'घरधुरी: कृषि उत्पादन एकाइ (Production Unit)',
            'crop_other'                => 'घरधुरी: अन्य खेति (Other Crops)',
            'livestock_breed'            => 'घरधुरी: पशुपन्छी जात (Livestock Breed)',
            'livestock_rearing_method'  => 'घरधुरी: पशुपन्छी पालनको तरिका (Rearing Method)',
            'livestock_product'         => 'घरधुरी: पशुपन्छी जन्य उत्पादन (Livestock Product)',
            'livestock_product_unit'    => 'घरधुरी: पशुपन्छी जन्य उत्पादन एकाइ (Product Unit)',
            'relation_to_head'          => 'घरधुरी: सम्बन्ध (Relation to Head)',
            'marital_status'            => 'घरधुरी: वैवाहिक स्थिति (Marital Status) — XLS: maritalstatus',
            'blood_group'               => '🔒 घरधुरी: रक्तसमुह (Blood Group) — निश्चित',
            'disability_type'           => 'घरधुरी: अपाङ्गताको किसिम (Disability Type)',
            'disability_card_color'     => '🔒 घरधुरी: अपाङ्गता परिचय कार्ड रंग (Disability Card Color) — निश्चित',
            'education_level'           => '🔒 घरधुरी: शैक्षिक योग्यता (Education Level) — XLS: eduqualification',
            'education_status'          => '🔒 घरधुरी: शैक्षिक अवस्था (Education Status) — निश्चित',
            'school_type'               => 'घरधुरी: शैक्षिक संस्थाको प्रकार (School Type)',
            'school_performance'        => 'घरधुरी: शिक्षा अवस्था (School Performance) — XLS: schoolingstatus',
            'institutionlocation'       => 'घरधुरी: शैक्षिक संस्थाको स्थान (Institution Location)',
            'cause_not_school'          => 'घरधुरी: विद्यालय नजाने कारण (Cause Not in School) — XLS: cause_not_school',
            'occupation_type'           => 'घरधुरी: पेशाको किसिम (Occupation Type)',
            'employment_status'         => 'घरधुरी: रोजगारी स्थिति (Employment Status)',
            'residence_status'          => 'घरधुरी: बसोबासको स्थिति (Residence Status)',
            'chronic_illness'           => 'घरधुरी: दीर्घ रोगको किसिम (Chronic Illness)',
            'frequency_smoking_drinking'=> 'घरधुरी: धुम्रपान/मद्यपानको आवृत्ति (Frequency of Smoking/Drinking)',
            'smoking_drinking_items'    => 'घरधुरी: धुम्रपान/मद्यपानका वस्तुहरू (Smoking/Drinking Items)',
            'drivers_license_category'  => '🔒 घरधुरी: सवारी चालक अनुमती वर्ग (Drivers License Category) — निश्चित',
            'child_birth_place'       => 'घरधुरी: बच्चा जन्मिएको स्थान (Child Birth Place)',
            'child_vaccine'           => 'घरधुरी: बालबालिका खोप (Child Vaccine)',
            'child_school_grade'      => 'घरधुरी: बालबालिका कक्षा/तह (Child School Grade)',
            'child_development_level' => 'घरधुरी: बाल विकास स्तर (Child Development Level)',
            // ── Phase 2: Child Health & Development ─────────────────────────────
            'childbornplace'          => 'घरधुरी: बच्चा जन्मिएको ठाउँ (Place of Birth — 0-3)',
            'childvaccine'            => 'घरधुरी: बच्चाको खोप (Child Vaccines — 0-3)',
            'caregiver'               => 'घरधुरी: बच्चाको हेरचाह गर्ने (Child Caregiver — 0-3)',
            'childphydev'             => 'घरधुरी: शारीरिक विकास स्तर (Physical Development — 4-5)',
            'childlgdev'              => 'घरधुरी: भाषा विकास स्तर (Language Development — 4-5)',
            'childsocialization'      => 'घरधुरी: सामाजिकीकरण (Socialization — 4-5)',
            'schoolinterest'          => 'घरधुरी: पढाइमा रुचि (School Interest — 6-15)',
            'interestingsubject'      => 'घरधुरी: रुचाउने विषय (Interesting Subject — 6-15)',
            'hwsupport'               => 'घरधुरी: गृहकार्य सहयोग (Homework Support — 6-15)',
            'digital_habit'           => 'घरधुरी: डिजिटल उपकरण समय (Digital/Screen Time — 6-15)',
            'other_interest'          => 'घरधुरी: अन्य रुचि (Other Interests — 6-15)',
            // ── Phase 3: Training ────────────────────────────────────────────────
            'vocational_training_subject'  => 'घरधुरी: व्यावसायिक तालिम विषय (Vocational Training Subject)',
            'technical_training_subject'   => 'घरधुरी: प्राविधिक तालिम विषय (Technical Training Subject)',
            'sa_training_subject'          => 'घरधुरी: साक्षरता/अन्य तालिम विषय (SA/Other Training Subject)',
            // ── Phase 3: Address / Abroad ────────────────────────────────────────
            'addrslocation'                => 'घरधुरी: बसोबासको स्थान (Address Location)',
            'purpose_living_outside'       => 'घरधुरी: बाहिर बस्नुको उद्देश्य (Purpose of Living Outside)',
            'typeofabroadjob'              => 'घरधुरी: विदेशी रोजगारीको प्रकार (Type of Abroad Job)',
            'abroadjobstatus'              => 'घरधुरी: विदेशी रोजगारीको अवस्था (Abroad Job Status)',
            'residing_country'             => 'घरधुरी: बसोबासको देश (Residing Country)',
            // ── Business — Identification
            'business_location'               => 'व्यवसाय: व्यवसाय गरेको स्थान (Business Location)',
            'business_type'                   => 'व्यवसाय: व्यवसायको किसिम (Business Type)',
            // ── Business — Field
            'business_field'                  => 'व्यवसाय: व्यवसाय क्षेत्र (Business Field)',
            // ── Business — Sub-categories
            'sub_ProductionIndustry'          => 'व्यवसाय उप-वर्ग: उत्पादनमूलक उद्योग (Production Industry)',
            'sub_AgricultureAnimalHusbandary' => 'व्यवसाय उप-वर्ग: कृषि तथा पशुपालन (Agriculture & Animal Husbandry)',
            'sub_Suppliers'                   => 'व्यवसाय उप-वर्ग: आपूर्तिकर्ता (Suppliers)',
            'sub_InformationAndCommunication' => 'व्यवसाय उप-वर्ग: सूचना तथा सञ्चार (Information & Communication)',
            'sub_HotelAndRestaurant'          => 'व्यवसाय उप-वर्ग: होटल तथा रेस्टुरेन्ट (Hotel & Restaurant)',
            'sub_VehicleAndTransportation'    => 'व्यवसाय उप-वर्ग: यातायात तथा सवारी (Vehicle & Transportation)',
            'sub_EducationalInstutions'       => 'व्यवसाय उप-वर्ग: शैक्षिक संस्थाहरू (Educational Institutions)',
            'sub_ConsultancyBusiness'         => 'व्यवसाय उप-वर्ग: परामर्श व्यवसाय (Consultancy Business)',
            'sub_MechinaryMaintenance'        => 'व्यवसाय उप-वर्ग: मेशिनरी मर्मत (Machinery Maintenance)',
            'sub_HospitalandClinc'            => 'व्यवसाय उप-वर्ग: अस्पताल तथा क्लिनिक (Hospital & Clinic)',
            'sub_FinancialInstutions'         => 'व्यवसाय उप-वर्ग: वित्तीय संस्थाहरू (Financial Institutions)',
            'sub_AbroadjobsSecurity'          => 'व्यवसाय उप-वर्ग: वैदेशिक रोजगार सुरक्षा (Abroad Jobs Security)',
            'sub_FancyBusiness'               => 'व्यवसाय उप-वर्ग: फेन्सी व्यवसाय (Fancy Business)',
            'sub_OtherShopBusiness'           => 'व्यवसाय उप-वर्ग: अन्य पसल व्यवसाय (Other Shop Business)',
            // ── Business — Registration
            'business_registration_place'     => 'व्यवसाय: दर्ता स्थान (Registration Place)',
            // ── Institution — Building
            'inst_building_type'      => 'संस्था: भवनको किसिम (Building Type)',
            'inst_building_condition' => 'संस्था: भवनको अवस्था (Building Condition)',
            'inst_ownership_type'     => 'संस्था: स्वामित्वको किसिम (Institution Ownership)',
            // ── Institution — Staff & Services
            'inst_staff_category'      => 'संस्था: कर्मचारी श्रेणी (Staff Category)',
            'inst_service_type'        => 'संस्था: सेवाको किसिम (Service Type)',
            'inst_committee_position'  => 'संस्था: कार्यसमिति पद (Committee Position)',
            // ── Institution — Education
            'inst_grade'              => 'संस्था: कक्षा (Grade)',
            'inst_school_level'       => 'संस्था: विद्यालयको तह (School Level)',
            'inst_management_type'    => 'संस्था: व्यवस्थापन प्रकार (Management Type)',
            'inst_medium'             => 'संस्था: शिक्षण माध्यम (Teaching Medium)',
            'inst_teacher_category'       => 'संस्था: शिक्षकको श्रेणी (Teacher Category)',
            'inst_teacher_qualification'  => 'संस्था: शिक्षक शैक्षिक योग्यता (Teacher Qualification)',
            'inst_teacher_subject'        => 'संस्था: शिक्षक विषय विशिष्टीकरण (Teacher Subject)',
            'inst_teacher_level'          => 'संस्था: शिक्षक नियुक्ति तह (Teacher Appointment Level)',
            // ── Institution — Health
            'inst_health_type'        => 'संस्था: स्वास्थ्य संस्थाको प्रकार (Health Facility Type)',
            'inst_health_service'     => 'संस्था: स्वास्थ्य सेवाहरू (Health Services)',
            'inst_health_equipment'   => 'संस्था: स्वास्थ्य उपकरण (Health Equipment)',
            'inst_health_staff_type'  => 'संस्था: स्वास्थ्यकर्मी पद (Health Staff Type)',
            'inst_lab_service'        => 'संस्था: प्रयोगशाला सेवा (Lab Services)',
            // ── Institution — Type-Specific
            'inst_coop_type'          => 'संस्था: सहकारीको प्रकार (Cooperative Type)',
            'inst_bank_type'          => 'संस्था: वित्तीय संस्थाको प्रकार (Bank/Finance Type)',
            'inst_office_level'       => 'संस्था: कार्यालयको तह (Office Level)',
            'inst_registration_type'  => 'संस्था: दर्ताको प्रकार (Registration Type — NGO)',
            'inst_working_area'       => 'संस्था: कार्यक्षेत्र (Working Area — NGO)',
            'inst_religion_type'      => 'संस्था: धर्मको किसिम (Religion Type)',
            'inst_game_type'          => 'संस्था: खेलको किसिम (Game Type)',
            'inst_industry_type'      => 'संस्था: उद्योगको किसिम (Industry Type)',
            'inst_media_type'         => 'संस्था: सञ्चारको किसिम (Media Type)',
            'inst_market_day'         => 'संस्था: साप्ताहिक बजार दिन (Weekly Market Day)',
            // ── Institution — Forest
            'inst_forest_type'        => 'संस्था: वनको किसिम (Forest Type)',
            'inst_tree_type'          => 'संस्था: रुखका प्रजाति (Tree Species)',
            'inst_herb_type'          => 'संस्था: जडिबुटी (Medicinal Herbs)',
            'inst_wildlife'           => 'संस्था: वन्यजन्तु (Wildlife)',
            'inst_forest_product'     => 'संस्था: वन उत्पादन (Forest Products)',
        ];
    }
}
