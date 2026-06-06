<?php

namespace App\Exports;

use App\Models\SurveyOption;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class HouseholdImportTemplate implements WithMultipleSheets
{
    public function __construct(private int $lgId) {}

    public function sheets(): array
    {
        // Fetch live survey options once and share with all sheets
        $grouped = SurveyOption::allGrouped($this->lgId)->all();
        return [
            new HouseholdsTemplateSheet(),
            new MembersTemplateSheet($grouped),
            new ChildHealthTemplateSheet($grouped),
            new HousingTemplateSheet($grouped),
            new UtilitiesTemplateSheet($grouped),
            new EconomicTemplateSheet($grouped),
            new AgricultureTemplateSheet($grouped),
            new LivestockTemplateSheet($grouped),
            new LandParcelsTemplateSheet($grouped),
            new DisasterTemplateSheet($grouped),
            new HhDynamicValidValuesSheet($grouped),
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SHARED HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Build a human-readable hint from live survey options.
 * Falls back to $static if the category has no DB options.
 */
function hhOptHint(array $grouped, string $cat, string $static = '', int $max = 8): string
{
    $opts = $grouped[$cat] ?? [];
    if (empty($opts)) return $static ?: '—';
    $vals = array_keys($opts);
    $preview = implode(' / ', array_slice($vals, 0, $max));
    $extra   = count($vals) > $max ? ' / … (+' . (count($vals) - $max) . ' more)' : '';
    return $preview . $extra . '  ← See Valid Values sheet';
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPER TRAIT — standard 3-row styling (header / hint / sample)
// ─────────────────────────────────────────────────────────────────────────────
trait TemplateSheetStyles
{
    abstract protected function headerColor(): string;
    abstract protected function lightColor(): string;
    abstract protected function sampleColor(): string;
    abstract protected function lastCol(): string;

    public function styles(Worksheet $sheet): array
    {
        $lc = $this->lastCol();
        $sheet->getStyle("A1:{$lc}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $this->headerColor()]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle("A2:{$lc}2")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF6B7280']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $this->lightColor()]],
            'alignment' => ['wrapText' => true],
        ]);
        $highest = $sheet->getHighestRow();
        if ($highest >= 3) {
            $sheet->getStyle("A3:{$lc}{$highest}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $this->sampleColor()]],
            ]);
        }
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(56);
        return [];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. HOUSEHOLDS SHEET  (no survey-option hints needed here)
// ─────────────────────────────────────────────────────────────────────────────
class HouseholdsTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use TemplateSheetStyles;
    public function title(): string { return 'Households'; }
    protected function headerColor(): string { return 'FF1E40AF'; }
    protected function lightColor(): string  { return 'FFEFF6FF'; }
    protected function sampleColor(): string { return 'FFDBEAFE'; }
    protected function lastCol(): string     { return 'S'; }

    public function array(): array
    {
        return [
            ['household_code','ward_number','settlement_name','respondent_name','respondent_contact',
             'total_members','gps_lat','gps_lng','gps_altitude','gps_accuracy',
             'survey_date','photo_number','remarks',
             // Head of household details
             'family_head','head_gender','family_type',
             // Gender breakdown counts
             'total_male','total_female','total_other'],
            ['Auto-generated if blank (e.g. HH-W01-00001)',
             'Ward number (1–9, must exist)',
             'Settlement/Tole (optional)',
             'घरमुलीको नाम (respondent/contact person)',
             'Contact phone',
             'Total family members',
             'GPS Latitude (e.g. 27.7172)','GPS Longitude (e.g. 85.3240)',
             'GPS Altitude metres (opt)','GPS Accuracy metres (opt)',
             'YYYY-MM-DD',
             'Photo file name without extension (e.g. 00247)',
             'Optional remarks',
             // Head details
             'घरमूलीको पूरा नाम (Head of Household full name)',
             'male / female / other — घरमूलीको लिङ्ग',
             'joint / nuclear / extended / single — परिवारको प्रकार',
             // Gender counts
             'पुरुष सदस्य संख्या (No. of Males)',
             'महिला सदस्य संख्या (No. of Females)',
             'अन्य लिङ्ग संख्या (Other Gender Count)'],
            ['','1','Kalyanpur Tole','Ram Bahadur Shrestha','9841000001',
             '5','27.7172','85.3240','1350','5',
             date('Y-m-d'),'00247','',
             'Ram Bahadur Shrestha','male','nuclear',
             '3','2','0'],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. MEMBERS SHEET  — hints use live survey options
// ─────────────────────────────────────────────────────────────────────────────
class MembersTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use TemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Members'; }
    protected function headerColor(): string { return 'FF065F46'; }
    protected function lightColor(): string  { return 'FFECFDF5'; }
    protected function sampleColor(): string { return 'FFD1FAE5'; }
    protected function lastCol(): string     { return 'BK'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            // ── 63 columns (A–BK) ─────────────────────────────────────────────
            // Identity (1–11)
            ['household_code','is_household_head','full_name','father_name','relation_to_head',
             'date_of_birth','age','gender','ethnicity','religion','language',
             // Marital (12–13)
             'marital_status','ageofmarriage',
             // Education (14–18)
             'education_level','education_status','is_literate','school_type','cause_not_school',
             // Occupation & training (19–25)
             'occupation_type','vocational_training','vt_subject',
             'techtraining','techtraining_subject','sa_training','sa_training_subject',
             // Employment (26)
             'employment_status',
             // Disability (27–30)
             'disability_status','disability_type','disability_card','disability_card_color',
             // Health (31–34)
             'chronic_illness','chronic_illness_types','child_labor','workplace_child_labor',
             // Blood & Documents (35–43)
             'blood_group',
             'has_birth_certificate','has_citizenship','has_voters_card',
             'has_national_id_card','has_passport','has_pan',
             'has_drivers_license','drivers_license_category',
             // Location & migration (44–49)
             'residence_status','residing_country','purpose_living_outside',
             'typeofabroadjob','abroadjobstatus','addrslocation',
             // Contact (50–51)
             'contact_phone','national_id',
             // Social & lifestyle (52–55)
             'social_security_allowance',
             'smoking_drinking','smoking_drinking_items','frequency_smoking_drinking',
             // Deceased (56–57)
             'is_deceased','death_cause',
             // Misc (58)
             'remarks',
             // Education detail (59–60)
             'current_class','school_name',
             // Migration detail (61)
             'residing_district',
             // Death detail (62)
             'death_date',
             // Documents detail (63)
             'no_citizenship_reason'],

            // ── Hints row (row 2) ─────────────────────────────────────────────
            ['Match Households sheet',
             '1=yes, 0=no (one per household)',
             'Full name — पूरा नाम',
             'Father/husband name — बुबा/श्रीमानको नाम',
             hhOptHint($g,'relation_to_head','self / wife / husband / son / daughter / other'),
             'YYYY-MM-DD (or blank)',
             'Age in years',
             'male / female / other',
             hhOptHint($g,'ethnicity','brahmin / chhetri / magar / tamang / newar / other'),
             hhOptHint($g,'religion','hindu / buddhist / muslim / christian / kirant / other'),
             hhOptHint($g,'language','nepali / newari / tamang / maithili / tharu / other'),
             hhOptHint($g,'marital_status','unmarried / married / divorced / widowed / separated'),
             'Age at marriage (blank if unmarried)',
             hhOptHint($g,'education_level','illiterate / primary / slc / higher_secondary / bachelors / masters / other'),
             hhOptHint($g,'education_status','enrolled / dropped / never_enrolled (blank if adult)'),
             '1=yes, 0=no — साक्षर',
             hhOptHint($g,'school_type','government / private / community / madrasa / other (blank if not school-age)'),
             hhOptHint($g,'cause_not_school','cost / distance / disability / work / not_interested / other (comma-separated, blank if enrolled)'),
             hhOptHint($g,'occupation_type','agriculture / business / service_govt / service_private / student / homemaker / other'),
             '1=yes, 0=no — सीप तालिम (व्यावसायिक)',
             hhOptHint($g,'vocational_training_subject','driving / tailoring / computer / mason / other (blank if none)'),
             '1=yes, 0=no — प्राविधिक तालिम',
             hhOptHint($g,'technical_training_subject','JTA / overseer / lab / other (blank if none)'),
             '1=yes, 0=no — SA तालिम',
             hhOptHint($g,'sa_training_subject','blank if none'),
             hhOptHint($g,'employment_status','employed / unemployed / student / homemaker / retired'),
             '1=yes, 0=no — अपाङ्गता',
             hhOptHint($g,'disability_type','physical / visual / hearing / speech / intellectual / mental / other'),
             '1=yes, 0=no — अपाङ्गता परिचयपत्र',
             hhOptHint($g,'disability_card_color','red / blue / yellow / white (blank if no card)'),
             '1=yes, 0=no — दीर्घरोग',
             hhOptHint($g,'chronic_illness','diabetes / hypertension / heart / kidney / cancer / mental / other (comma-separated)'),
             '1=yes, 0=no — बाल श्रम',
             'Workplace if child_labor=1 (blank otherwise)',
             'A+ / A- / B+ / B- / O+ / O- / AB+ / AB-',
             '1=yes, 0=no','1=yes, 0=no','1=yes, 0=no','1=yes, 0=no','1=yes, 0=no',
             '1=yes, 0=no — PAN card',
             '1=yes, 0=no — सवारी चालक अनुमतिपत्र',
             hhOptHint($g,'drivers_license_category','A / B / C / D (comma-separated, blank if none)'),
             hhOptHint($g,'residence_status','permanent / temporary / absent'),
             'Country if abroad (comma-separated, blank if here)',
             hhOptHint($g,'purpose_living_outside','study / job / business / other (blank if not absent)'),
             hhOptHint($g,'typeofabroadjob','skilled / semi_skilled / unskilled / other (blank if not abroad)'),
             hhOptHint($g,'abroadjobstatus','working / job_searching / studying / other (blank if not abroad)'),
             'Current address / तोले ठेगाना (optional)',
             'Phone (optional — stored encrypted)',
             'National ID number (optional — stored encrypted)',
             '1=yes, 0=no — सामाजिक सुरक्षा भत्ता',
             '1=yes, 0=no — धुम्रपान/मद्यपान',
             hhOptHint($g,'smoking_drinking_items','cigarette / alcohol / tobacco / other (comma-separated, blank if no)'),
             hhOptHint($g,'frequency_smoking_drinking','daily / weekly / occasionally / rarely (blank if no)'),
             '1=yes, 0=no — मृत्यु भएको?',
             hhOptHint($g,'death_cause','accident / illness / old_age / maternal / suicide / other (blank if alive)'),
             'Optional remarks',
             // Education detail
             'Current class/grade e.g. 7 or "Grade 7" (blank if not student)',
             'School name — विद्यालयको नाम (blank if not student)',
             // Migration detail
             'District name if residing_status=temporary and within Nepal (blank if abroad)',
             // Death detail
             'YYYY-MM-DD — death date (blank if alive)',
             // Documents detail
             'Reason no citizenship e.g. minor / lost / not_applied (blank if has citizenship)'],

            // ── Sample row 1 (household head) ────────────────────────────────
            ['HH-W01-00001','1','Ram Bahadur Shrestha','Hari Bahadur Shrestha','self',
             '1975-06-15','49','male','chhetri','hindu','nepali',
             'married','20',
             'slc','','1','','',
             'agriculture','0','','0','','0','',
             'employed',
             '0','','0','',
             '0','','0','',
             'B+','1','1','1','0','0','0','0','',
             'permanent','','','','','Kalyanpur Tole',
             '9841000001','',
             '0','0','','',
             '0','','',
             '','','','',''],
            // ── Sample row 2 (spouse) ─────────────────────────────────────────
            ['HH-W01-00001','0','Sita Shrestha','Ram Bahadur Shrestha','wife',
             '1980-03-22','44','female','chhetri','hindu','nepali',
             'married','18',
             'slc','','1','','',
             'homemaker','0','','0','','0','',
             'homemaker',
             '0','','0','',
             '0','','0','',
             'O+','1','1','1','0','0','0','0','',
             'permanent','','','','','',
             '','',
             '0','0','','',
             '0','','',
             '','','','',''],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. CHILD HEALTH SHEET  — one row per child member (age 0–15), links by member_name
// ─────────────────────────────────────────────────────────────────────────────
class ChildHealthTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use TemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'ChildHealth'; }
    protected function headerColor(): string { return 'FF0F766E'; }
    protected function lightColor(): string  { return 'FFF0FEFA'; }
    protected function sampleColor(): string { return 'FFCCFBF4'; }
    protected function lastCol(): string     { return 'Y'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            // 25 columns (A–Y) — one row per child (age 0–15 only)
            ['household_code','member_name','age',
             // ── Age 0–3 (cols D–N) ─────────────────────────────────────────
             'childbornplace','child_weight_in_birth',
             'colostrum','childvaccine','vit_a_dewormin','fullmilkcourse',
             'durationbrestfeed','childhealthcheckup','child_caregiver',
             'diarrhea_before_two_weeks','fever_resp',
             // ── Age 4–5 (cols O–R) ────────────────────────────────────────
             'vitaminA','childphydev','childlgdev','childsocialization',
             // ── Age 6–15 (cols S–Y) ───────────────────────────────────────
             'phy_dev_6_15',
             'school_interest_6_15','interesting_subject_6_15','hw_support_6_15',
             'allergy_infection_6_15','watch_mobile_tv_6_15','digital_habit_6_15'],

            // ── Hints row (row 2) ─────────────────────────────────────────────
            ['Match Households sheet',
             'Must match full_name in Members sheet exactly',
             'Age in years — determines which columns apply (0-3 / 4-5 / 6-15)',
             // 0-3
             hhOptHint($g,'childbornplace','hospital / health_post / home / other'),
             'Birth weight kg (e.g. 3.2)',
             '1=yes, 0=no — अक्षम दूध पाएको',
             hhOptHint($g,'childvaccine','bcg / polio / dpt / measles / mr / pentavalent / other (comma-separated)'),
             '1=yes, 0=no — भिटामिन ए / कृमिनाशक',
             '1=yes, 0=no — पूर्ण दूध कोर्स',
             'Duration in months (e.g. 24)',
             '1=yes, 0=no — नियमित स्वास्थ्य जाँच',
             hhOptHint($g,'caregiver','mother / father / grandparent / sibling / other'),
             '1=yes, 0=no — झाडापखाला (२ हप्तामा)',
             '1=yes, 0=no — ज्वरो/श्वास',
             // 4-5
             '1=yes, 0=no — भिटामिन ए (४-५ वर्ष)',
             hhOptHint($g,'childphydev','normal / delayed / severe (४-५ वर्ष शारीरिक विकास)'),
             hhOptHint($g,'childlgdev','normal / delayed / severe (भाषा विकास)'),
             hhOptHint($g,'childsocialization','normal / delayed / severe (सामाजिकीकरण)'),
             // 6-15
             '1=yes, 0=no — सामान्य शारीरिक विकास (६-१५)',
             hhOptHint($g,'schoolinterest','high / medium / low (विद्यालयमा रुचि)'),
             hhOptHint($g,'interestingsubject','nepali / math / science / social / other (मनपर्ने विषय)'),
             hhOptHint($g,'hwsupport','parents / sibling / teacher / self / none (गृहकार्य सहयोग)'),
             '1=yes, 0=no — एलर्जी/संक्रमण',
             '1=yes, 0=no — मोबाइल/टीभी हेर्छन्',
             hhOptHint($g,'digital_habit','productive / entertainment / social_media / gaming / other')],

            // ── Sample rows ───────────────────────────────────────────────────
            // Infant (age 1)
            ['HH-W01-00001','Aarav Shrestha','1',
             'hospital','3.2','1','bcg,polio,dpt','1','1','18','1','mother','0','0',
             '','','','',
             '','','','','','',''],
            // Toddler (age 4)
            ['HH-W01-00001','Sanju Shrestha','4',
             '','','','','','','','','','','',
             '1','normal','normal','normal',
             '','','','','','',''],
            // Child (age 8)
            ['HH-W01-00001','Priya Shrestha','8',
             '','','','','','','','','','','',
             '','','','',
             '1','high','math','parents','0','0','productive'],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. HOUSING SHEET
// ─────────────────────────────────────────────────────────────────────────────
class HousingTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use TemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Housing'; }
    protected function headerColor(): string { return 'FF7C3AED'; }
    protected function lightColor(): string  { return 'FFF5F3FF'; }
    protected function sampleColor(): string { return 'FFEDE9FE'; }
    protected function lastCol(): string     { return 'U'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            ['household_code','house_type','roof_type','wall_type','foundation_type',
             'ownership_type','rent_amount','total_rooms','total_floors','construction_year',
             'house_condition','has_building_permit','is_at_risk','disaster_type',
             'land_measurement_unit','house_land_type',
             'house_purpose','location_house_built','distance_to_mainroad','distance_to_school',
             'has_land_in_municipality'],
            ['Match Households sheet',
             hhOptHint($g,'house_type','rcc_building / wooden / mud_house / temporary / other'),
             hhOptHint($g,'roof_type','rcc / tin / slate / thatch / other'),
             hhOptHint($g,'wall_type','brick_cement / brick_mud / stone_cement / wood / bamboo / other'),
             hhOptHint($g,'foundation_type','rcc / brick_cement / stone_cement / stone_mud / wood / other'),
             hhOptHint($g,'ownership_type','owned / rented / institutional / other'),
             'Monthly rent NPR (blank if not rented)',
             'Number of rooms','Number of floors','Year built (e.g. 2010)',
             hhOptHint($g,'house_condition','good / average / poor / damaged'),
             '1=yes, 0=no','1=yes, 0=no',
             'flood,landslide,fire (comma-separated or blank)',
             hhOptHint($g,'land_measurement_unit','ropani / ana / bigha / kattha / dhur / sq_meter / hectare / acre'),
             hhOptHint($g,'house_land_type','registered / unregistered / leased / guthi / other'),
             hhOptHint($g,'house_purpose','residential / commercial / mixed / other'),
             'rural / urban / roadside / hillside / riverside (optional)',
             'Minutes to main road (optional)','Minutes to nearest school (optional)',
             '1=yes / 0=no — पालिकामा जग्गा छ? (see LandParcels sheet for details)'],
            ['HH-W01-00001','rcc_building','rcc','brick_cement','rcc',
             'owned','','4','2','2010','good','1','0','','ropani','registered',
             'residential','rural','15','10','1'],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. UTILITIES SHEET
// ─────────────────────────────────────────────────────────────────────────────
class UtilitiesTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use TemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Utilities'; }
    protected function headerColor(): string { return 'FF0369A1'; }
    protected function lightColor(): string  { return 'FFF0F9FF'; }
    protected function sampleColor(): string { return 'FFE0F2FE'; }
    protected function lastCol(): string     { return 'AA'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            // 27 columns (A–AA)
            ['household_code','water_source','water_distance_minutes',
             'uses_firewood','firewood_source',
             'cooking_stove','cooking_fuel_arr','cooking_fuel','has_biogas',
             'has_alt_electricity','alt_electricity_source',
             'electricity_source','monthly_electricity_units',
             'waste_management',
             'has_toilet','toilet_type','sanitation_type','handwashing',
             'facilities',
             'has_internet','internet_type',
             'vehicles_bike','vehicles_car','vehicles_tempo','vehicles_tractor','vehicles_other','has_own_vehicle'],
            ['Match Households sheet',
             hhOptHint($g,'water_source','tap_inside / tap_outside / tube_well / river / well / rain / jar_water / other'),
             'Walking minutes to water source (0 = at home)',
             '1=yes, 0=no — दाउरा इन्धन?',
             hhOptHint($g,'firewood_source','community_forest / private_forest / government_forest / purchase / other (comma-separated)'),
             hhOptHint($g,'cooking_stove','improved / traditional / gas / electric / biogas / other'),
             hhOptHint($g,'cooking_fuel','lpg / biogas / firewood / kerosene / electricity / improved_cook / other (comma-separated)'),
             hhOptHint($g,'cooking_fuel','lpg / biogas / firewood / kerosene / electricity / improved_cook / other (primary single value)'),
             '1=yes, 0=no — बायोग्यास',
             '1=yes, 0=no — वैकल्पिक ऊर्जा?',
             hhOptHint($g,'alt_electricity_source','solar / micro_hydro / generator / biogas / wind / other (comma-separated)'),
             hhOptHint($g,'electricity_source','national_grid / solar / generator / micro_hydro / biogas / none'),
             'Monthly kWh',
             hhOptHint($g,'waste_management','municipal / compost / burning / dumping / other'),
             '1=yes, 0=no — शौचालय',
             hhOptHint($g,'toilet_type','flush / pit / eco_san / other'),
             hhOptHint($g,'sanitation_type','flush_piped / flush_septic / simple_pit / improved_pit / open / other'),
             hhOptHint($g,'handwashing','soap_water / ash / water_only / none'),
             hhOptHint($g,'facilities','electricity / road / internet / telephone / market / school / health_post / bank / other (comma-separated)'),
             '1=yes, 0=no — इन्टरनेट',
             hhOptHint($g,'internet_type','broadband / mobile_data / fiber / none'),
             'Motorcycles/scooters count','Cars/jeeps count','Tempo/van count',
             'Tractors count','Other vehicles count','1=yes, 0=no (owns any)'],
            ['HH-W01-00001','tap_water','0',
             '1','community_forest',
             'gas','lpg,firewood','lpg','0',
             '0','',
             'national_grid','50',
             'municipal',
             '1','flush','flush_piped','soap_water',
             'electricity,road,market',
             '1','fiber',
             '1','0','0','0','0','1'],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. ECONOMIC SHEET
// ─────────────────────────────────────────────────────────────────────────────
class EconomicTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use TemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Economic'; }
    protected function headerColor(): string { return 'FFB45309'; }
    protected function lightColor(): string  { return 'FFFEFCE8'; }
    protected function sampleColor(): string { return 'FFFEF9C3'; }
    protected function lastCol(): string     { return 'W'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            ['household_code','income_source','annual_income_range','annual_expenditure_range',
             'remittance_amount','loan_source','total_loan_amount','has_bank_account','has_insurance',
             'poverty_card',
             'decision_household_work','decision_property','decision_consumer_committee',
             'decision_school_mgmt','decision_business','decision_bank_account',
             'has_foreign_employment','foreign_employment_country',
             // Expense breakdown (T4a)
             'food_expense','clothes_expense','education_expense','treatment_expense','other_expense'],
            ['Match Households sheet',
             hhOptHint($g,'income_source','agriculture / livestock / business / service / remittance / daily_wage / pension / other'),
             hhOptHint($g,'annual_income_range','below_50000 / 50000_100000 / 100000_200000 / 200000_500000 / above_500000'),
             hhOptHint($g,'annual_expenditure_range','below_50000 / 50000_100000 / 100000_200000 / 200000_500000 / above_500000'),
             'Annual remittance NPR (0 if none)',
             hhOptHint($g,'loan_source','bank / cooperative / microfinance / moneylender / individual / other (comma-separated)'),
             'Total loan NPR',
             '1=yes, 0=no','1=yes, 0=no','1=yes, 0=no (गरिबी परिचयपत्र)',
             'Male / Female / Both / Other','Male / Female / Both / Other',
             'Male / Female / Both / Other','Male / Female / Both / Other',
             'Male / Female / Both / Other','Male / Female / Both / Other (blank if no bank)',
             '1=yes, 0=no','Country name (blank if none)',
             'Food & grocery NPR/year (0 if unknown)',
             'Clothing NPR/year (0 if unknown)',
             'Education NPR/year (0 if unknown)',
             'Healthcare NPR/year (0 if unknown)',
             'Other NPR/year (0 if unknown)'],
            ['HH-W01-00001','agriculture','100000_200000','80000_150000',
             '0','','0','1','0','0',
             'Both','Male','Female','Female','Male','Both','0','',
             '60000','20000','15000','10000','5000'],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. AGRICULTURE SHEET  (static — no survey-option dropdowns needed)
// ─────────────────────────────────────────────────────────────────────────────
class AgricultureTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use TemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Agriculture'; }
    protected function headerColor(): string { return 'FF166534'; }
    protected function lightColor(): string  { return 'FFF0FDF4'; }
    protected function sampleColor(): string { return 'FFBBF7D0'; }
    protected function lastCol(): string     { return 'P'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            // 16 columns — A–P
            ['household_code','agriculture','owns_land','land_area_ropani','irrigated_area_ropani',
             'irrigation_source',
             'agri_category',
             'use_of_chem_fertilizer','chem_fertilizer','chem_fertilizer_kg','fertilizer_source',
             'use_of_chem_pesticide','chem_pesticide','chem_pesticide_cost',
             'farm_challenge','annual_agri_income'],
            ['Match Households sheet',
             '1=yes (practices agriculture), 0=no',
             '1=yes (owns land), 0=no',
             'Total land area in ropani',
             'Irrigated area in ropani',
             hhOptHint($g, 'irrigation_source', 'canal / rain / groundwater / river / pond / sprinkler / drip / none (comma-separated)'),
             hhOptHint($g, 'agri_category', 'food_crops / pulses / oil_seeds / vegetables / cash_crops / fruits / other_crops (comma-separated)'),
             '1=yes, 0=no — रासायनिक मल प्रयोग',
             hhOptHint($g, 'chem_fertilizer', 'urea / dap / potash / nano_urea / other (comma-separated)'),
             'Fertilizer quantity kg/year',
             hhOptHint($g, 'fertilizer_source', 'small_farmers / private_agro / cooperative / salt_trading / other (comma-separated)'),
             '1=yes, 0=no — रासायनिक विषादी प्रयोग',
             hhOptHint($g, 'chem_pesticide', 'malathion / chlorpyrifos / glyphosate / mancozeb / atrazine (comma-separated)'),
             'Pesticide cost NPR/year',
             hhOptHint($g, 'farm_challenge', 'drought / flood / pest / disease / market / labour / credit / other (comma-separated)'),
             'Annual agri income NPR'],
            ['HH-W01-00001','1','1','4.5','2.0','canal,rain','food_crops,vegetables',
             '1','urea,dap','50','cooperative',
             '0','','','drought,market','85000'],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. LIVESTOCK SHEET
// ─────────────────────────────────────────────────────────────────────────────
class LivestockTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use TemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Livestock'; }
    protected function headerColor(): string { return 'FF92400E'; }
    protected function lightColor(): string  { return 'FFFEF3C7'; }
    protected function sampleColor(): string { return 'FFFDE68A'; }
    protected function lastCol(): string     { return 'S'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            // 19 columns (A–S) — one row per livestock type per household
            ['household_code','livestock_type','count',
             'breed','rearing_method','annual_production','production_unit',
             'vet_service_access','insurance',
             // Bee/fish columns (fill once per HH on the first livestock row)
             'has_beekeeping','mudhe_hive','modern_hive','honey_prod_mudhe','honey_prod_modern','honey_sales',
             'has_fishery','pond_area','fish_prod','fish_sales'],
            ['Match Households sheet (one row per type)',
             hhOptHint($g,'livestock_kind','cow / buffalo / goat / sheep / pig / poultry / yak / other'),
             'Number of animals',
             hhOptHint($g,'livestock_breed','local / improved / hybrid / exotic (blank if unknown)'),
             hhOptHint($g,'livestock_rearing_method','stall / grazing / mixed (blank if unknown)'),
             'Annual production kg/litres (blank if unknown)',
             hhOptHint($g,'livestock_product_unit','kg / litre / piece / dozen (blank if unknown)'),
             '1=yes, 0=no — पशु सेवा','1=yes, 0=no — बीमा',
             '1=yes, 0=no (once per HH)','Traditional hive count','Modern hive count',
             'Honey kg/year (mudhe)','Honey kg/year (modern)','Honey sold kg/year',
             '1=yes, 0=no (once per HH)','Pond area sq.m','Fish prod kg/year','Fish sold kg/year'],
            ['HH-W01-00001','cow','2','local','mixed','800','litre','1','0',
             '1','3','2','25','10','15','0','','',''],
            ['HH-W01-00001','goat','5','local','grazing','','','1','0',
             '','','','','','','','','',''],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ─────────────────────────────────────────────────────────────────────────────
// 8. LAND PARCELS SHEET  — one row per parcel (like Livestock)
// ─────────────────────────────────────────────────────────────────────────────
class LandParcelsTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use TemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'LandParcels'; }
    protected function headerColor(): string { return 'FFB45309'; }
    protected function lightColor(): string  { return 'FFFEF3C7'; }
    protected function sampleColor(): string { return 'FFFDE68A'; }
    protected function lastCol(): string     { return 'D'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            ['household_code', 'land_use_type', 'land_unit', 'quantity'],
            ['Match Households sheet — one row per parcel',
             hhOptHint($g, 'land_use_type', 'khet / bari / pakho / residential / industrial'),
             hhOptHint($g, 'land_measurement_unit', 'ropani / ana / bigha / kattha / dhur / sq_meter'),
             'Amount / परिमाण (e.g. 2.5)'],
            ['HH-W01-00001', 'khet',  'ropani', '2.5'],
            ['HH-W01-00001', 'bari',  'ropani', '1'],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 9. DISASTER SHEET
// ─────────────────────────────────────────────────────────────────────────────
class DisasterTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use TemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Disaster'; }
    protected function headerColor(): string { return 'FF991B1B'; }
    protected function lightColor(): string  { return 'FFFFF1F2'; }
    protected function sampleColor(): string { return 'FFFECDD3'; }
    protected function lastCol(): string     { return 'F'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            ['household_code','has_death_this_year','disaster_type',
             'risk_reduction_plan','affected_last_3years','received_relief'],
            ['Match Households sheet',
             '1=yes, 0=no — यो वर्ष मृत्यु भएको?',
             hhOptHint($g,'disaster_type','flood,landslide,fire,earthquake,drought,other (comma-separated)'),
             'Plan description (optional — जोखिम न्यूनीकरण योजना)',
             '1=yes, 0=no — विपद्बाट पीडित (३ वर्षमा)',
             '1=yes, 0=no — राहत पाएको'],
            ['HH-W01-00001','0','flood,landslide','','0','0'],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 10. DYNAMIC VALID VALUES SHEET  — generated from live survey options
// ─────────────────────────────────────────────────────────────────────────────
class HhDynamicValidValuesSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Valid Values'; }

    // Tab codes matching the import template sheet order.
    private const TAB_CODES = [
        'Members (02)'     => '02',
        'ChildHealth (03)' => '03',
        'Housing (04)'     => '04',
        'Utilities (05)'   => '05',
        'Economic (06)'    => '06',
        'Agriculture (07)' => '07',
        'Livestock (08)'   => '08',
        'LandParcels (09)' => '09',
        'Disaster (10)'    => '10',
    ];

    // Keys: exact column name in the import sheet (becomes raw-sheet header when prefixed).
    // Values: survey_option category key for DB lookup.
    // Sections are organised by sheet tab so the tab code is unambiguous.
    private const SECTIONS = [
        'Members (02)' => [
            // ── Identity & demographic ────────────────────────────────────────
            'relation_to_head'           => 'relation_to_head',
            'ethnicity'                  => 'ethnicity',
            'religion'                   => 'religion',
            'language'                   => 'language',
            'marital_status'             => 'marital_status',
            // ── Education ────────────────────────────────────────────────────
            'education_level'            => 'education_level',
            'education_status'           => 'education_status',
            'school_type'                => 'school_type',
            'cause_not_school'           => 'cause_not_school',
            'current_class'              => 'child_school_grade',   // grade codes for student members
            // ── Occupation & training ─────────────────────────────────────────
            'occupation_type'            => 'occupation_type',
            'employment_status'          => 'employment_status',
            'vt_subject'                 => 'vocational_training_subject',
            'techtraining_subject'       => 'technical_training_subject',
            'technical_skill'            => 'technical_skill',      // detailed skill list
            'sa_training_subject'        => 'sa_training_subject',
            // ── Disability & health ───────────────────────────────────────────
            'disability_type'            => 'disability_type',
            'disability_card_color'      => 'disability_card_color',
            'chronic_illness_types'      => 'chronic_illness',   // kept: boolean chronic_illness column conflict
            // ── Documents ────────────────────────────────────────────────────
            'drivers_license_category'   => 'drivers_license_category',
            // ── Location & migration ──────────────────────────────────────────
            'residence_status'           => 'residence_status',
            'addrslocation'              => 'addrslocation',        // inside_municipality / outside_withinNepal / Abroad
            'residing_country'           => 'residing_country',
            'purpose_living_outside'     => 'purpose_living_outside',
            'typeofabroadjob'            => 'typeofabroadjob',
            'abroadjobstatus'            => 'abroadjobstatus',
            // ── Lifestyle ────────────────────────────────────────────────────
            'smoking_drinking_items'     => 'smoking_drinking_items',
            'frequency_smoking_drinking' => 'frequency_smoking_drinking',
            // ── Deceased ──────────────────────────────────────────────────────
            'death_cause'                => 'death_cause',
        ],
        'ChildHealth (03)' => [
            'childbornplace'            => 'childbornplace',
            'child_birth_place'         => 'child_birth_place',     // alternate/detailed birth-place options
            'childvaccine'              => 'childvaccine',
            'child_caregiver'           => 'caregiver',
            'childphydev'               => 'childphydev',
            'childlgdev'                => 'childlgdev',
            'childsocialization'        => 'childsocialization',
            'child_development_level'   => 'child_development_level',
            'school_interest_6_15'      => 'schoolinterest',
            'interesting_subject_6_15'  => 'interestingsubject',
            'hw_support_6_15'           => 'hwsupport',
            'digital_habit_6_15'        => 'digital_habit',
            'school_performance'        => 'school_performance',
            'other_interest'            => 'other_interest',
        ],
        'Housing (04)' => [
            'house_type'           => 'house_type',
            'roof_type'            => 'roof_type',
            'wall_type'            => 'wall_type',
            'foundation_type'      => 'foundation_type',
            'ownership_type'       => 'ownership_type',
            'house_condition'      => 'house_condition',
            'house_land_type'      => 'house_land_type',
            'house_purpose'        => 'house_purpose',
            'location_house_built' => 'location_house_built',
            'land_measurement_unit'=> 'land_measurement_unit',
            'disaster_type'        => 'disaster_type',   // housing risk assessment (same codes as Disaster tab)
        ],
        'Utilities (05)' => [
            'water_source'            => 'water_source',
            'electricity_source'      => 'electricity_source',
            'alt_electricity_source'  => 'alt_electricity_source',
            'cooking_fuel_arr'        => 'cooking_fuel',
            'cooking_fuel'            => 'cooking_fuel',
            'cooking_stove'           => 'cooking_stove',
            'firewood_source'         => 'firewood_source',
            'toilet_type'             => 'toilet_type',
            'sanitation_type'         => 'sanitation_type',
            'waste_management'        => 'waste_management',
            'internet_type'           => 'internet_type',
            'handwashing'             => 'handwashing',
            'facilities'              => 'facilities',
        ],
        'Economic (06)' => [
            'income_source'            => 'income_source',
            'annual_income_range'      => 'annual_income_range',
            'annual_expenditure_range' => 'annual_expenditure_range',
            'loan_source'              => 'loan_source',
            'loan_purpose'             => 'loan_purpose',
        ],
        'Agriculture (07)' => [
            'agri_category'         => 'agri_category',
            'irrigation_source'     => 'irrigation_source',
            'farm_challenge'        => 'farm_challenge',
            'chem_fertilizer'       => 'chem_fertilizer',
            'fertilizer_source'     => 'fertilizer_source',
            'chem_pesticide'        => 'chem_pesticide',
        ],
        'Livestock (08)' => [
            'livestock_type'  => 'livestock_kind',
            'breed'           => 'livestock_breed',
            'rearing_method'  => 'livestock_rearing_method',
            'production_unit' => 'livestock_product_unit',
        ],
        'LandParcels (09)' => [
            'land_use_type' => 'land_use_type',
            'land_unit'     => 'land_measurement_unit',
        ],
        'Disaster (10)' => [
            'disaster_type'         => 'disaster_type',
            'disaster_property_loss'=> 'disaster_property_loss',
            'relief_type'           => 'relief_type',
            'relief_unit'           => 'relief_unit',
            'relief_source'         => 'relief_source',
        ],
    ];

    private const SUPPRESS = [
        // Business module — separate template
        'business_field','business_location','business_registration_place','business_type',
        'sub_AbroadjobsSecurity','sub_AgricultureAnimalHusbandary','sub_ConsultancyBusiness',
        'sub_EducationalInstutions','sub_FancyBusiness','sub_FinancialInstutions',
        'sub_HospitalandClinc','sub_HotelAndRestaurant','sub_InformationAndCommunication',
        'sub_MechinaryMaintenance','sub_OtherShopBusiness','sub_ProductionIndustry',
        'sub_Suppliers','sub_VehicleAndTransportation',
        // Agri sub-category breakdowns (rolled up into agri_categories column)
        'crop_food','crop_pulse','crop_oil','crop_vegetable','crop_cash','crop_fruit','crop_other',
        // Livestock sub-detail (form-only, not flat import columns)
        'bee_fish_silk','agri_production_unit','livestock_product',
        // Economic detail rows (JSON, not flat columns)
        'expense_category','decision_owner',
        // Settlement UI picker (free-text in import)
        'settlement_type',
        // Form-only social metrics / static fields handled separately
        'family_gathering_freq','neighbor_cooperation','happiness_activities',
        'residence_perception','org_type','family_type',
        // Static fields — covered in the STATIC FIELDS block above
        'blood_group',
        // Child-form sub-questions not yet importable
        'nutrition_diet','treatment_place','child_vaccine',
        // Institution module — separate template/module
        'institutionlocation',
        // Form-only fields with no flat import column
        'major_occupation',     // overlaps occupation_type; form-only variant
        'occupation_sector',    // overlaps employment_status; form-only variant
        'migration_purpose',    // overlaps purpose_living_outside; form-only variant
        'vehicle_type',         // Utilities uses numeric counts (vehicles_bike etc.), not enum
    ];

    public function array(): array
    {
        $rows    = [];
        $covered = [];   // tracks survey-option keys already displayed

        // ── Header row
        $rows[] = [
            'Raw Sheet Header (Tab|Column)',
            'Valid Values  (use these exact codes when filling the import file)',
            'Nepali Labels',
            'Sheet / Notes',
        ];

        // ── Static fields block  (01 = Households, 02 = Members, 06 = Economic)
        $rows[] = ['━━ STATIC FIELDS ━━', '', '', ''];
        $rows[] = ['01|household_code',           'Leave blank for auto-generation',                                       '',                                           'Format: HH-W01-00001'];
        $rows[] = ['01|head_gender',              'male / female / other',                                                 'पुरुष / महिला / अन्य',                      'Households (01)'];
        $rows[] = ['01|family_type',              'joint / nuclear / extended / single',                                   'संयुक्त / एकल / विस्तारित / एकाकी',         'Households (01)'];
        $rows[] = ['01|photo_number',             'Filename without extension  (e.g. 00247)',                              '',                                           'Must match file on photo server'];
        $rows[] = ['02|gender',                   'male / female / other',                                                 'पुरुष / महिला / अन्य',                      'Members (02)'];
        $rows[] = ['02|blood_group',              'A+ / A- / B+ / B- / O+ / O- / AB+ / AB-',                              '',                                           'Members (02)'];
        $rows[] = ['06|annual_income_range',      'below_50000 / 50000_100000 / 100000_200000 / 200000_500000 / above_500000', 'आय दायरा',                             'Economic (06) — add to Survey Options for dynamic'];
        $rows[] = ['06|annual_expenditure_range', 'below_50000 / 50000_100000 / 100000_200000 / 200000_500000 / above_500000', 'खर्च दायरा',                           'Economic (06) — add to Survey Options for dynamic'];
        $rows[] = ['06|decision_*',               'Male / Female / Both / Other',                                          'पुरुष / महिला / दुवै / अन्य',               'Economic (06) — decision columns'];
        $rows[] = ['Boolean 1/0',                 '1 = Yes / True  ·  0 = No / False',                                    '',                                           'For all has_*, is_* columns'];
        $rows[] = ['Date format',                 'YYYY-MM-DD  (e.g. ' . date('Y-m-d') . ')',                             '',                                           'English calendar AD'];
        $rows[] = ['GPS coordinates',             'Decimal degrees  (lat: 27.7172 / lng: 85.3240)',                        '',                                           'Altitude & accuracy in metres'];
        $rows[] = ['', '', '', ''];

        // ── Survey-option sections
        // Column A = "TabCode|column_name" — paste this directly as the raw-sheet header.
        // Column D = section name for context.
        foreach (self::SECTIONS as $section => $map) {
            $tabCode     = self::TAB_CODES[$section] ?? '??';
            $sectionRows = [];
            foreach ($map as $colName => $surveyKey) {
                $covered[] = $surveyKey;
                $opts = $this->grouped[$surveyKey] ?? [];
                if (empty($opts)) continue;
                $values = implode(' / ', array_keys($opts));
                $labels = implode(' / ', array_values($opts));
                $sectionRows[] = [$tabCode . '|' . $colName, $values, $labels, $section];
            }
            if (!empty($sectionRows)) {
                $rows[] = ["━━ {$section} ━━", '', '', ''];
                foreach ($sectionRows as $r) $rows[] = $r;
                $rows[] = ['', '', '', ''];
            }
        }

        // ── Anything else from survey options not yet covered
        $extraRows = [];
        foreach ($this->grouped as $cat => $opts) {
            if (str_starts_with($cat, 'inst_') || in_array($cat, $covered) || in_array($cat, self::SUPPRESS) || empty($opts)) continue;
            $extraRows[] = [$cat, implode(' / ', array_keys($opts)), implode(' / ', array_values($opts)), ''];
        }
        if (!empty($extraRows)) {
            $rows[] = ['━━ Additional Options ━━', '', '', ''];
            foreach ($extraRows as $r) $rows[] = $r;
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $highest = $sheet->getHighestRow();

        // Header row
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Walk rows: style section headers and alternating data rows
        $dataRowColor = true;
        for ($row = 2; $row <= $highest; $row++) {
            $cell = $sheet->getCell("A{$row}")->getValue();
            $isSection = is_string($cell) && str_starts_with($cell, '━━');
            $isEmpty   = empty($cell);

            if ($isSection) {
                $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF1E40AF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEFF6FF']],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(18);
                $dataRowColor = true;
            } elseif ($isEmpty) {
                $sheet->getRowDimension($row)->setRowHeight(6);
            } else {
                $bg = $dataRowColor ? 'FFFAFAFA' : 'FFFFFFFF';
                $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                    'alignment' => ['wrapText' => true],
                ]);
                // Field name bold
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF374151']],
                ]);
                // Values in blue
                $sheet->getStyle("B{$row}")->applyFromArray([
                    'font' => ['color' => ['argb' => 'FF1D4ED8']],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(24);
                $dataRowColor = !$dataRowColor;
            }
        }

        $sheet->getColumnDimension('A')->setWidth(36);
        $sheet->getColumnDimension('B')->setWidth(90);
        $sheet->getColumnDimension('C')->setWidth(50);
        $sheet->getColumnDimension('D')->setWidth(25);
        return [];
    }
}
