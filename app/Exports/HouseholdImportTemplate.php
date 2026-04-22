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
    protected function lastCol(): string     { return 'O'; }

    public function array(): array
    {
        return [
            ['household_code','ward_number','settlement_name','respondent_name','respondent_contact',
             'total_members','gps_lat','gps_lng','gps_altitude','gps_accuracy',
             'family_head','head_gender','family_type','survey_date','photo_number'],
            ['Auto-generated if blank (e.g. HH-W01-00001)',
             'Ward number (1–9, must exist)',
             'Settlement/Tole (optional)',
             'घरमुलीको नाम (respondent)',
             'Contact phone',
             'Total family members',
             'GPS Latitude (e.g. 27.7172)','GPS Longitude (e.g. 85.3240)',
             'GPS Altitude metres (opt)','GPS Accuracy metres (opt)',
             'Full name of household head',
             'male / female / other',
             'nuclear / joint / single / other  ← See Valid Values',
             'YYYY-MM-DD',
             'Photo file name without extension (e.g. 00247)'],
            ['','1','Kalyanpur Tole','Ram Bahadur Shrestha','9841000001',
             '5','27.7172','85.3240','1350','5',
             'Ram Bahadur Shrestha','male','nuclear',date('Y-m-d'),'00247'],
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
    protected function lastCol(): string     { return 'Z'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            ['household_code','is_household_head','full_name','relation_to_head',
             'date_of_birth','age','gender','ethnicity','religion','mother_tongue',
             'marital_status','education_level','is_literate','occupation_type',
             'employment_status','disability_status','disability_type','blood_group',
             'has_birth_certificate','has_citizenship','has_voters_card',
             'has_national_id_card','has_passport','residence_status',
             'contact_phone','remarks'],
            ['Match Households sheet',
             '1=yes, 0=no (one per household)',
             'Full name',
             hhOptHint($g,'relation_to_head','self / wife / husband / son / daughter / other'),
             'YYYY-MM-DD (or blank)',
             'Age in years',
             'male / female / other',
             hhOptHint($g,'ethnicity','brahmin / chhetri / magar / tamang / newar / other'),
             hhOptHint($g,'religion','hindu / buddhist / muslim / christian / kirant / other'),
             hhOptHint($g,'mother_tongue','nepali / newari / tamang / maithili / tharu / other'),
             hhOptHint($g,'marital_status','unmarried / married / divorced / widowed / separated'),
             hhOptHint($g,'education_level','illiterate / primary / slc / higher_secondary / bachelors / masters / other'),
             '1=yes, 0=no',
             hhOptHint($g,'occupation_type','agriculture / business / service_govt / service_private / student / homemaker / other'),
             hhOptHint($g,'employment_status','employed / unemployed / student / homemaker / retired'),
             '1=yes, 0=no',
             hhOptHint($g,'disability_type','physical / visual / hearing / speech / intellectual / mental / other'),
             'A+ / A- / B+ / B- / O+ / O- / AB+ / AB-',
             '1=yes, 0=no','1=yes, 0=no','1=yes, 0=no','1=yes, 0=no','1=yes, 0=no',
             hhOptHint($g,'residence_status','permanent / temporary / absent'),
             'Phone (optional)','Optional remarks'],
            ['HH-W01-00001','1','Ram Bahadur Shrestha','self',
             '1975-06-15','49','male','chhetri','hindu','nepali',
             'married','slc','1','agriculture','employed',
             '0','','B+','1','1','1','0','0','permanent','9841000001',''],
            ['HH-W01-00001','0','Sita Shrestha','wife',
             '1980-03-22','44','female','chhetri','hindu','nepali',
             'married','slc','1','homemaker','homemaker',
             '0','','O+','1','1','1','0','0','permanent','',''],
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
    protected function lastCol(): string     { return 'P'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            ['household_code','house_type','roof_type','wall_type','foundation_type',
             'ownership_type','rent_amount','total_rooms','total_floors','construction_year',
             'house_condition','has_building_permit','is_at_risk','risk_types',
             'land_measurement_unit','house_land_type'],
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
             hhOptHint($g,'land_unit','ropani / ana / bigha / kattha / dhur / sq_meter / hectare / acre'),
             hhOptHint($g,'house_land_type','registered / unregistered / leased / guthi / other')],
            ['HH-W01-00001','rcc_building','rcc','brick_cement','rcc',
             'owned','','4','2','2010','good','1','0','','ropani','registered'],
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
    protected function lastCol(): string     { return 'S'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            ['household_code','water_source','water_distance_minutes','has_toilet',
             'toilet_type','sanitation_type','electricity_source','monthly_electricity_units',
             'cooking_fuel','has_biogas','solid_waste_management','has_internet','internet_type',
             'vehicles_bike','vehicles_car','vehicles_tempo','vehicles_tractor','vehicles_other','has_own_vehicle'],
            ['Match Households sheet',
             hhOptHint($g,'water_source','tap_water / tube_well / river / spring / well / rainwater / other'),
             'Walking minutes to water source (0 = at home)',
             '1=yes, 0=no',
             hhOptHint($g,'toilet_type','flush / pit / open_field / none'),
             hhOptHint($g,'sanitation_type','sewer / septic / open / other'),
             hhOptHint($g,'electricity_source','grid / solar / generator / none / other'),
             'Monthly kWh',
             hhOptHint($g,'cooking_fuel','lpg / firewood / kerosene / biogas / electric / other'),
             '1=yes, 0=no',
             hhOptHint($g,'solid_waste_management','municipal / compost / burning / dumping / other'),
             '1=yes, 0=no',
             hhOptHint($g,'internet_type','broadband / mobile_data / fiber / none'),
             'Motorcycles/scooters count','Cars/jeeps count','Tempo/van count',
             'Tractors count','Other vehicles count','1=yes, 0=no (owns any)'],
            ['HH-W01-00001','tap_water','0','1','flush','septic',
             'grid','50','lpg','0','municipal','1','fiber',
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
    protected function lastCol(): string     { return 'S'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            ['household_code','primary_income_source','annual_income_range','annual_expenditure_range',
             'remittance_amount','loan_sources','total_loan_amount','has_bank_account','has_insurance',
             'poverty_card','social_security_allowance',
             'decision_household_work','decision_property','decision_consumer_committee',
             'decision_school_mgmt','decision_business','decision_bank_account',
             'has_foreign_employment','foreign_employment_country'],
            ['Match Households sheet',
             hhOptHint($g,'primary_income_source','agriculture / livestock / business / service / remittance / daily_wage / pension / other'),
             hhOptHint($g,'annual_income_range','below_50000 / 50000_100000 / 100000_200000 / 200000_500000 / above_500000'),
             hhOptHint($g,'annual_expenditure_range','below_50000 / 50000_100000 / 100000_200000 / 200000_500000 / above_500000'),
             'Annual remittance NPR (0 if none)',
             'bank,cooperative,moneylender (comma-separated)',
             'Total loan NPR',
             '1=yes, 0=no','1=yes, 0=no','1=yes, 0=no (poverty card)','1=yes, 0=no',
             'Male / Female / Both / Other','Male / Female / Both / Other',
             'Male / Female / Both / Other','Male / Female / Both / Other',
             'Male / Female / Both / Other','Male / Female / Both / Other (blank if no bank)',
             '1=yes, 0=no','Country name (blank if none)'],
            ['HH-W01-00001','agriculture','100000_200000','80000_150000',
             '0','','0','1','0','0','0',
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
    protected function lastCol(): string     { return 'I'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            // Column names match HouseholdAgriculture $fillable exactly
            ['household_code','owns_land','land_area_ropani','irrigated_area_ropani',
             'agri_categories','use_of_chem_fertilizer','farm_challenges','annual_agri_income'],
            ['Match Households sheet',
             '1=yes, 0=no',
             'Total land area in ropani',
             'Irrigated area in ropani',
             hhOptHint($g, 'crop_type', 'food_crops / pulses / oil_crops / vegetables / cash_crops / fruits (comma-separated)'),
             '1=yes, 0=no',
             hhOptHint($g, 'farm_challenge', 'drought / flood / pest / disease / market / labour / credit / other (comma-separated)'),
             'Annual income NPR'],
            ['HH-W01-00001','1','4.5','2.0','food_crops,vegetables','1','drought,market','85000'],
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
             hhOptHint($g,'livestock_type','cow / buffalo / goat / sheep / pig / poultry / yak / other'),
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
// 8. DYNAMIC VALID VALUES SHEET  — generated from live survey options
// ─────────────────────────────────────────────────────────────────────────────
class HhDynamicValidValuesSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Valid Values'; }

    // Ordered sections mapping sheet usage → category keys
    private const SECTIONS = [
        'Member Fields'        => ['relation_to_head','ethnicity','religion','mother_tongue',
                                   'marital_status','education_level','education_status',
                                   'occupation_type','employment_status','disability_type',
                                   'disability_card_color','residence_status',
                                   'chronic_illness_type','family_type','settlement_type'],
        'Housing Fields'       => ['house_type','roof_type','wall_type','foundation_type',
                                   'ownership_type','house_condition','house_land_type',
                                   'house_purpose','risk_types'],
        'Land & Agriculture'   => ['land_unit','crop_type','irrigation_source','farm_challenge'],
        'Utilities & Energy'   => ['water_source','toilet_type','sanitation_type',
                                   'electricity_source','cooking_fuel',
                                   'solid_waste_management','internet_type'],
        'Economic & Income'    => ['primary_income_source','annual_income_range',
                                   'annual_expenditure_range','loan_source'],
        'Livestock & Farming'  => ['livestock_type'],
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
        $rows[] = ['head_gender',  'male / female / other',                                   '',                      ''];
        $rows[] = ['family_type',  'nuclear / joint / single / other',                        '',                      'If not in options below'];
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
