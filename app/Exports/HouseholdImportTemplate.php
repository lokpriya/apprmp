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
            new HousingTemplateSheet($grouped),
            new UtilitiesTemplateSheet($grouped),
            new EconomicTemplateSheet($grouped),
            new AgricultureTemplateSheet($grouped),
            new LivestockTemplateSheet($grouped),
            new LandParcelsTemplateSheet($grouped),
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
    protected function lastCol(): string     { return 'M'; }

    public function array(): array
    {
        return [
            ['household_code','ward_number','settlement_name','respondent_name','respondent_contact',
             'total_members','gps_lat','gps_lng','gps_altitude','gps_accuracy',
             'survey_date','photo_number','remarks'],
            ['Auto-generated if blank (e.g. HH-W01-00001)',
             'Ward number (1–9, must exist)',
             'Settlement/Tole (optional)',
             'घरमुलीको नाम (respondent)',
             'Contact phone',
             'Total family members',
             'GPS Latitude (e.g. 27.7172)','GPS Longitude (e.g. 85.3240)',
             'GPS Altitude metres (opt)','GPS Accuracy metres (opt)',
             'YYYY-MM-DD',
             'Photo file name without extension (e.g. 00247)',
             'Optional remarks'],
            ['','1','Kalyanpur Tole','Ram Bahadur Shrestha','9841000001',
             '5','27.7172','85.3240','1350','5',
             date('Y-m-d'),'00247',''],
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
    protected function lastCol(): string     { return 'AP'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            // ── Identity ──────────────────────────────────────────────────────
            ['household_code','is_household_head','full_name','father_name','relation_to_head',
             'date_of_birth','age','gender','ethnicity','religion','mother_tongue',
             // ── Marital ───────────────────────────────────────────────────────
             'marital_status','ageofmarriage',
             // ── Education & Occupation ────────────────────────────────────────
             'education_level','is_literate',
             'occupation_type','vocational_training','vt_subject','employment_status',
             // ── Disability & Health ───────────────────────────────────────────
             'disability_status','disability_type',
             'chronic_illness','chronic_illness_types',
             'child_labor','workplace_child_labor',
             // ── Blood & Documents ─────────────────────────────────────────────
             'blood_group',
             'has_birth_certificate','has_citizenship','has_voters_card',
             'has_national_id_card','has_passport','has_pan',
             'has_drivers_license','drivers_license_category',
             // ── Location & Contact ────────────────────────────────────────────
             'residence_status','addrslocation',
             'contact_phone','national_id',
             // ── Social & Deceased ─────────────────────────────────────────────
             'social_security_allowance',
             'is_deceased','death_cause',
             'remarks'],
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
             '1=yes, 0=no',
             hhOptHint($g,'occupation_type','agriculture / business / service_govt / service_private / student / homemaker / other'),
             '1=yes, 0=no — सीप तालिम छ?',
             hhOptHint($g,'vocational_training_subject','driving / tailoring / computer / mason / other (blank if none)'),
             hhOptHint($g,'employment_status','employed / unemployed / student / homemaker / retired'),
             '1=yes, 0=no',
             hhOptHint($g,'disability_type','physical / visual / hearing / speech / intellectual / mental / other'),
             '1=yes, 0=no — दीर्घरोग छ?',
             hhOptHint($g,'chronic_illness','diabetes / hypertension / heart / kidney / cancer / mental / other (comma-separated)'),
             '1=yes, 0=no — बाल श्रम',
             'Workplace if child_labor=1 (blank otherwise)',
             'A+ / A- / B+ / B- / O+ / O- / AB+ / AB-',
             '1=yes, 0=no','1=yes, 0=no','1=yes, 0=no','1=yes, 0=no','1=yes, 0=no',
             '1=yes, 0=no — PAN card',
             '1=yes, 0=no — सवारी चालक अनुमतिपत्र',
             hhOptHint($g,'drivers_license_category','A / B / C / D (comma-separated, blank if none)'),
             hhOptHint($g,'residence_status','permanent / temporary / absent'),
             'Current address / तोले ठेगाना (optional)',
             'Phone (optional — stored encrypted)',
             'National ID number (optional — stored encrypted)',
             '1=yes, 0=no — सामाजिक सुरक्षा भत्ता',
             '1=yes, 0=no — मृत्यु भएको?',
             hhOptHint($g,'death_cause','accident / illness / old_age / maternal / suicide / other (blank if alive)'),
             'Optional remarks'],
            // ── Sample rows ───────────────────────────────────────────────────
            ['HH-W01-00001','1','Ram Bahadur Shrestha','Hari Bahadur Shrestha','self',
             '1975-06-15','49','male','chhetri','hindu','nepali',
             'married','20','slc','1',
             'agriculture','0','','employed',
             '0','','0','','0','',
             'B+','1','1','1','0','0','0','0','',
             'permanent','Kalyanpur Tole','9841000001','',
             '0','0','',''],
            ['HH-W01-00001','0','Sita Shrestha','Ram Bahadur Shrestha','wife',
             '1980-03-22','44','female','chhetri','hindu','nepali',
             'married','18','slc','1',
             'homemaker','0','','homemaker',
             '0','','0','','0','',
             'O+','1','1','1','0','0','0','0','',
             'permanent','','','',
             '0','0','',''],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. HOUSING SHEET
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
             'house_condition','has_building_permit','is_at_risk','risk_types',
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
    protected function lastCol(): string     { return 'T'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            ['household_code','water_source','water_distance_minutes','has_toilet',
             'toilet_type','sanitation_type','electricity_source','monthly_electricity_units',
             'cooking_fuel','has_biogas','solid_waste_management','has_internet','internet_type',
             'handwashing',
             'vehicles_bike','vehicles_car','vehicles_tempo','vehicles_tractor','vehicles_other','has_own_vehicle'],
            ['Match Households sheet',
             hhOptHint($g,'water_source','tap_inside / tap_outside / tube_well / river / well / rain / jar_water / other'),
             'Walking minutes to water source (0 = at home)',
             '1=yes, 0=no',
             hhOptHint($g,'toilet_type','flush / pit / eco_san / other'),
             hhOptHint($g,'sanitation_type','flush_piped / flush_septic / simple_pit / improved_pit / open / other'),
             hhOptHint($g,'electricity_source','national_grid / solar / generator / micro_hydro / biogas / none'),
             'Monthly kWh',
             hhOptHint($g,'cooking_fuel','lpg / biogas / firewood / kerosene / electricity / improved_cook / other'),
             '1=yes, 0=no',
             hhOptHint($g,'waste_management','municipal / compost / burning / dumping / other'),
             '1=yes, 0=no',
             hhOptHint($g,'internet_type','broadband / mobile_data / fiber / none'),
             hhOptHint($g,'handwashing','soap_water / ash / water_only / none'),
             'Motorcycles/scooters count','Cars/jeeps count','Tempo/van count',
             'Tractors count','Other vehicles count','1=yes, 0=no (owns any)'],
            ['HH-W01-00001','tap_water','0','1','flush','septic',
             'grid','50','lpg','0','municipal','1','fiber',
             'soap_water',
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
    protected function lastCol(): string     { return 'R'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            ['household_code','primary_income_source','annual_income_range','annual_expenditure_range',
             'remittance_amount','loan_sources','total_loan_amount','has_bank_account','has_insurance',
             'poverty_card',
             'decision_household_work','decision_property','decision_consumer_committee',
             'decision_school_mgmt','decision_business','decision_bank_account',
             'has_foreign_employment','foreign_employment_country'],
            ['Match Households sheet',
             hhOptHint($g,'income_source','agriculture / livestock / business / service / remittance / daily_wage / pension / other'),
             hhOptHint($g,'annual_income_range','below_50000 / 50000_100000 / 100000_200000 / 200000_500000 / above_500000'),
             hhOptHint($g,'annual_expenditure_range','below_50000 / 50000_100000 / 100000_200000 / 200000_500000 / above_500000'),
             'Annual remittance NPR (0 if none)',
             'bank,cooperative,moneylender (comma-separated)',
             'Total loan NPR',
             '1=yes, 0=no','1=yes, 0=no','1=yes, 0=no (गरिबी परिचयपत्र)',
             'Male / Female / Both / Other','Male / Female / Both / Other',
             'Male / Female / Both / Other','Male / Female / Both / Other',
             'Male / Female / Both / Other','Male / Female / Both / Other (blank if no bank)',
             '1=yes, 0=no','Country name (blank if none)'],
            ['HH-W01-00001','agriculture','100000_200000','80000_150000',
             '0','','0','1','0','0',
             'Both','Male','Female','Female','Male','Both','0',''],
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
    protected function lastCol(): string     { return 'O'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            // Column names match HouseholdAgriculture $fillable exactly
            ['household_code','agriculture','owns_land','land_area_ropani','irrigated_area_ropani',
             'agri_categories',
             'use_of_chem_fertilizer','chem_fertilizer_types','chem_fertilizer_kg','fertilizer_sources',
             'use_of_chem_pesticide','chem_pesticide_types','chem_pesticide_cost',
             'farm_challenges','annual_agri_income'],
            ['Match Households sheet',
             '1=yes (practices agriculture), 0=no',
             '1=yes (owns land), 0=no',
             'Total land area in ropani',
             'Irrigated area in ropani',
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
            ['HH-W01-00001','1','1','4.5','2.0','food_crops,vegetables',
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
    protected function lastCol(): string     { return 'N'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            // Bee/fish column names match beeFishSilk keys used by the form (fill once per HH)
            ['household_code','livestock_type','count','vet_service_access','insurance',
             'has_beekeeping','mudhe_hive','modern_hive','honey_prod_mudhe','honey_prod_modern','honey_sales',
             'has_fishery','pond_area','fish_prod','fish_sales'],
            ['Match Households sheet (one row per type)',
             hhOptHint($g,'livestock_kind','cow / buffalo / goat / sheep / pig / poultry / yak / other'),
             'Number of animals','1=yes, 0=no','1=yes, 0=no',
             '1=yes, 0=no (once per HH)','Traditional hive count','Modern hive count',
             'Honey kg/year (mudhe)','Honey kg/year (modern)','Honey sold kg/year',
             '1=yes, 0=no (once per HH)','Pond area sq.m','Fish prod kg/year','Fish sold kg/year'],
            ['HH-W01-00001','cow','2','1','0','1','3','2','25','10','15','0','','',''],
            ['HH-W01-00001','goat','5','1','0','','','','','','','','','',''],
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
// 9. DYNAMIC VALID VALUES SHEET  — generated from live survey options
// ─────────────────────────────────────────────────────────────────────────────
class HhDynamicValidValuesSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Valid Values'; }

    // Ordered sections mapping sheet usage → category keys
    private const SECTIONS = [
        'Member Fields'        => ['relation_to_head','ethnicity','religion','language',
                                   'marital_status','education_level','education_status',
                                   'occupation_type','employment_status','disability_type',
                                   'disability_card_color','residence_status',
                                   'chronic_illness','settlement_type'],
        'Housing Fields'       => ['house_type','roof_type','wall_type','foundation_type',
                                   'ownership_type','house_condition','house_land_type',
                                   'house_purpose','location_house_built'],
        'Land & Agriculture'   => ['land_measurement_unit','land_use_type','agri_category',
                                   'irrigation_source','farm_challenge',
                                   'chem_fertilizer','fertilizer_source','chem_pesticide'],
        'Utilities & Energy'   => ['water_source','toilet_type','sanitation_type',
                                   'electricity_source','cooking_fuel',
                                   'waste_management','internet_type','handwashing'],
        'Economic & Income'    => ['income_source','loan_source','expense_category'],
        'Livestock & Farming'  => ['livestock_kind','livestock_breed','livestock_rearing_method',
                                   'livestock_product','livestock_product_unit'],
    ];

    public function array(): array
    {
        $rows     = [];
        $covered  = [];

        // ── Header
        $rows[] = [
            'Field / Category',
            'Valid Values  (use these exact codes when filling the import file)',
            'Nepali Labels',
            'Notes',
        ];

        // ── Static fields block
        $rows[] = ['━━ STATIC FIELDS ━━', '', '', ''];
        $rows[] = ['gender',       'male / female / other',                                   'पुरुष / महिला / अन्य',  ''];
        $rows[] = ['blood_group',  'A+ / A- / B+ / B- / O+ / O- / AB+ / AB-',                '',                      ''];
        $rows[] = ['Boolean 1/0',  '1 = Yes / True  ·  0 = No / False',                      '',                      'For all has_*, is_* columns'];
        $rows[] = ['Date format',  'YYYY-MM-DD  (e.g. ' . date('Y-m-d') . ')',               '',                      'English calendar AD'];
        $rows[] = ['household_code','Leave blank for auto-generation',                         '',                      'Format: HH-W01-00001'];
        $rows[] = ['photo_number',  'Filename without extension  (e.g. 00247 or HH-247)',      '',                      'Must match file on photo server'];
        $rows[] = ['GPS coordinates','Decimal degrees  (lat: 27.7172 / lng: 85.3240)',         '',                      'Altitude & accuracy in metres'];
        $rows[] = ['', '', '', ''];

        // ── Survey-option sections
        foreach (self::SECTIONS as $section => $cats) {
            $sectionRows = [];
            foreach ($cats as $cat) {
                $covered[] = $cat;
                $opts = $this->grouped[$cat] ?? [];
                if (empty($opts)) continue;
                $values = implode(' / ', array_keys($opts));
                $labels = implode(' / ', array_values($opts));
                $sectionRows[] = [$cat, $values, $labels, ''];
            }
            if (!empty($sectionRows)) {
                $rows[] = ["━━ {$section} ━━", '', '', ''];
                foreach ($sectionRows as $r) $rows[] = $r;
                $rows[] = ['', '', '', ''];
            }
        }

        // ── Anything else from survey options (not inst_ and not already covered)
        $extraRows = [];
        foreach ($this->grouped as $cat => $opts) {
            if (str_starts_with($cat, 'inst_') || in_array($cat, $covered) || empty($opts)) continue;
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

        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(90);
        $sheet->getColumnDimension('C')->setWidth(50);
        $sheet->getColumnDimension('D')->setWidth(25);
        return [];
    }
}
