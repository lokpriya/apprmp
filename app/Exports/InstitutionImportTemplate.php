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

class InstitutionImportTemplate implements WithMultipleSheets
{
    public function __construct(private int $lgId) {}

    public function sheets(): array
    {
        // Fetch live survey options once and share with all sheets
        $grouped = SurveyOption::allGrouped($this->lgId)->all();
        return [
            new InstitutionsTemplateSheet($grouped),
            new InfrastructureTemplateSheet($grouped),
            new StaffTemplateSheet($grouped),
            new EducationTemplateSheet($grouped),
            new HealthTemplateSheet($grouped),
            new ServicesTemplateSheet($grouped),
            new TeachersTemplateSheet($grouped),
            new TypeSpecificTemplateSheet($grouped),
            new InstDynamicValidValuesSheet($grouped),
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SHARED HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Build a human-readable hint from live survey options (institution variant).
 * Falls back to $static if the category has no DB options.
 */
function instOptHint(array $grouped, string $cat, string $static = '', int $max = 8): string
{
    $opts = $grouped[$cat] ?? [];
    if (empty($opts)) return $static ?: '—';
    $vals    = array_keys($opts);
    $preview = implode(' / ', array_slice($vals, 0, $max));
    $extra   = count($vals) > $max ? ' / … (+' . (count($vals) - $max) . ' more)' : '';
    return $preview . $extra . '  ← See Valid Values sheet';
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPER TRAIT — standard 3-row styling (header / hint / sample)
// ─────────────────────────────────────────────────────────────────────────────
trait InstTemplateSheetStyles
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
// INSTITUTIONS SHEET
// ─────────────────────────────────────────────────────────────────────────────
class InstitutionsTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use InstTemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Institutions'; }
    protected function headerColor(): string { return 'FF1E40AF'; }
    protected function lightColor(): string  { return 'FFEFF6FF'; }
    protected function sampleColor(): string { return 'FFDBEAFE'; }
    protected function lastCol(): string     { return 'P'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            [
                'institution_code', 'institution_type', 'ward_number', 'settlement_name',
                'name_np', 'name_en', 'ownership_type', 'registration_number',
                'established_date', 'respondent_name', 'respondent_contact',
                'gps_lat', 'gps_lng', 'survey_date', 'remarks', 'photo_number',
            ],
            [
                'Auto-generated if blank (e.g. IN-0001-W01-00001)',
                instOptHint($g, 'inst_institution_type', 'school / health_post / hospital / ngo / cooperative / bank / other'),
                'Ward number (must exist in system)',
                'Settlement / Tole name (optional)',
                'Name in Nepali',
                'Name in English',
                instOptHint($g, 'inst_ownership_type', 'government / private / community / ngo / cooperative'),
                'Official registration number (optional)',
                'Established date (YYYY-MM-DD, optional)',
                'Name of respondent / contact person',
                'Contact phone number',
                'GPS Latitude (decimal, optional)',
                'GPS Longitude (decimal, optional)',
                'Survey date (YYYY-MM-DD)',
                'Optional remarks',
                'Photo file name without extension (e.g. 00131)',
            ],
            [
                '',
                'school',
                '1',
                'Kalyanpur Tole',
                'कल्याणपुर माध्यमिक विद्यालय',
                'Kalyanpur Secondary School',
                'government',
                'REG-2024-001',
                '2050-01-15',
                'Hari Prasad Sharma',
                '9841000001',
                '27.7172',
                '85.3240',
                date('Y-m-d'),
                '',
                '00131',
            ],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// INFRASTRUCTURE SHEET
// ─────────────────────────────────────────────────────────────────────────────
class InfrastructureTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use InstTemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Infrastructure'; }
    protected function headerColor(): string { return 'FF7C3AED'; }
    protected function lightColor(): string  { return 'FFF5F3FF'; }
    protected function sampleColor(): string { return 'FFEDE9FE'; }
    protected function lastCol(): string     { return 'W'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            [
                'institution_code', 'building_type', 'building_condition',
                'total_rooms', 'total_floors', 'land_area_sqft', 'has_boundary_wall',
                'toilet_count', 'female_toilet', 'male_toilet', 'disabled_toilet',
                'accessible_ramp', 'drinking_water', 'electricity', 'solar',
                'generator', 'internet', 'fire_extinguisher', 'earthquake_safe', 'evacuation_plan',
                'total_classrooms', 'has_library', 'has_lab',
            ],
            [
                'Matches institution_code from Institutions sheet',
                instOptHint($g, 'inst_building_type', 'concrete / tin / bamboo / brick / stone / wood / mixed'),
                instOptHint($g, 'inst_building_condition', 'good / average / poor / dilapidated'),
                'Total number of rooms',
                'Total number of floors',
                'Land area in square feet',
                'yes/no',
                'Total toilet count',
                'Female toilets count',
                'Male toilets count',
                'Disabled-accessible toilets count',
                'yes/no — accessible ramp for disability',
                'yes/no — drinking water available',
                'yes/no — electricity connected',
                'yes/no — solar power',
                'yes/no — generator backup',
                'yes/no — internet access',
                'yes/no — fire extinguisher present',
                'yes/no — earthquake-safe construction',
                'yes/no — evacuation plan exists',
                'Total classrooms (schools only)',
                'yes/no — library available (schools only)',
                'yes/no — science lab available (schools only)',
            ],
            [
                'IN-0001-W01-00001', 'concrete', 'good', '12', '2', '5000',
                'yes', '4', '2', '2', '0', 'yes', 'yes', 'yes', 'no', 'no', 'yes', 'yes', 'yes', 'no',
                '10', 'yes', 'yes',
            ],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// STAFF SHEET
// ─────────────────────────────────────────────────────────────────────────────
class StaffTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use InstTemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Staff'; }
    protected function headerColor(): string { return 'FF065F46'; }
    protected function lightColor(): string  { return 'FFECFDF5'; }
    protected function sampleColor(): string { return 'FFD1FAE5'; }
    protected function lastCol(): string     { return 'I'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            [
                'institution_code', 'staff_category',
                'male_count', 'female_count', 'dalit_count', 'janajati_count',
                'other_count', 'permanent_count', 'contract_count',
            ],
            [
                'Matches institution_code from Institutions sheet',
                instOptHint($g, 'inst_staff_category', 'teacher / health_worker / admin / support / security / volunteer'),
                'Number of male staff',
                'Number of female staff',
                'Number of Dalit staff',
                'Number of Janajati staff',
                'Number of other category staff',
                'Number of permanent staff',
                'Number of contract/temporary staff',
            ],
            ['IN-0001-W01-00001', 'teacher', '8', '6', '1', '2', '11', '12', '2'],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// EDUCATION SHEET
// ─────────────────────────────────────────────────────────────────────────────
class EducationTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use InstTemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Education'; }
    protected function headerColor(): string { return 'FF92400E'; }
    protected function lightColor(): string  { return 'FFFFFBEB'; }
    protected function sampleColor(): string { return 'FFFDE68A'; }
    protected function lastCol(): string     { return 'H'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            [
                'institution_code', 'grade',
                'male_students', 'female_students', 'dalit_students',
                'janajati_students', 'scholarship_students', 'dropout_count',
            ],
            [
                'Matches institution_code from Institutions sheet (schools only)',
                instOptHint($g, 'inst_grade', 'ecd / grade_1 / grade_2 / grade_3 / grade_4 / grade_5 / grade_6 / grade_7 / grade_8 / grade_9 / grade_10 / grade_11 / grade_12'),
                'Male students enrolled',
                'Female students enrolled',
                'Dalit students count',
                'Janajati students count',
                'Scholarship recipients count',
                'Dropout count this year',
            ],
            ['IN-0001-W01-00001', 'grade_6', '25', '22', '3', '5', '4', '1'],
            ['IN-0001-W01-00001', 'grade_7', '23', '20', '2', '4', '3', '0'],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// HEALTH SHEET
// ─────────────────────────────────────────────────────────────────────────────
class HealthTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use InstTemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Health'; }
    protected function headerColor(): string { return 'FF9F1239'; }
    protected function lightColor(): string  { return 'FFFFF1F2'; }
    protected function sampleColor(): string { return 'FFFECDD3'; }
    protected function lastCol(): string     { return 'AE'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            [
                'institution_code', 'health_type',
                'bed_count', 'male_ward_beds', 'female_ward_beds', 'children_ward_beds',
                'maternity_beds', 'icu_beds', 'emergency_beds',
                'delivery_service', 'emergency_service', 'lab_service',
                'xray_service', 'ambulance', 'vaccine_service', 'family_planning',
                'opd_daily', 'monthly_outpatient_avg', 'monthly_inpatient_avg',
                'has_ultrasound', 'has_ecg', 'has_icu', 'has_operation_theatre',
                'has_blood_bank', 'has_dialysis', 'has_dental', 'has_physiotherapy', 'has_oxygen_plant',
                'health_services', 'equipment', 'lab_services',
            ],
            [
                'Matches institution_code from Institutions sheet (health institutions only)',
                instOptHint($g, 'inst_health_type', 'health_post / hospital / clinic / ayurvedic / pharmacy'),
                'Total beds (0 if outpatient only)',
                'Male ward beds',
                'Female ward beds',
                'Children ward beds',
                'Maternity / delivery ward beds',
                'ICU beds',
                'Emergency ward beds',
                'yes/no — delivery / maternity service',
                'yes/no — 24hr emergency service',
                'yes/no — laboratory service',
                'yes/no — X-ray service',
                'yes/no — ambulance available',
                'yes/no — vaccination service',
                'yes/no — family planning service',
                'Daily OPD patient average',
                'Monthly outpatient average',
                'Monthly inpatient average',
                'yes/no — ultrasound machine',
                'yes/no — ECG machine',
                'yes/no — ICU facility',
                'yes/no — operation theatre',
                'yes/no — blood bank',
                'yes/no — dialysis unit',
                'yes/no — dental unit',
                'yes/no — physiotherapy unit',
                'yes/no — oxygen plant',
                instOptHint($g, 'inst_health_service', 'comma-separated values from Valid Values sheet'),
                instOptHint($g, 'inst_health_equipment', 'comma-separated equipment codes'),
                instOptHint($g, 'inst_lab_service', 'comma-separated lab service codes'),
            ],
            [
                'IN-0001-W01-00001', 'health_post',
                '10', '4', '4', '0', '2', '0', '2',
                'yes', 'yes', 'yes', 'no', 'yes', 'yes', 'yes',
                '50', '250', '15',
                'no', 'yes', 'no', 'no', 'no', 'no', 'no', 'no', 'no',
                'antenatal,immunization', 'weighing_scale,bp_machine', 'blood_test,urine_test',
            ],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SERVICES SHEET
// ─────────────────────────────────────────────────────────────────────────────
class ServicesTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use InstTemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Services'; }
    protected function headerColor(): string { return 'FF0369A1'; }
    protected function lightColor(): string  { return 'FFF0F9FF'; }
    protected function sampleColor(): string { return 'FFE0F2FE'; }
    protected function lastCol(): string     { return 'G'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            [
                'institution_code', 'service_name', 'service_type',
                'capacity', 'annual_beneficiaries', 'coverage_description', 'is_free',
            ],
            [
                'Matches institution_code from Institutions sheet',
                'Name of the service provided',
                instOptHint($g, 'inst_service_type', 'education / health / financial / social / legal / emergency / agriculture / other'),
                'Service capacity (number, optional)',
                'Annual beneficiaries count (optional)',
                'Description of service coverage area',
                'yes/no — is the service free of charge?',
            ],
            ['IN-0001-W01-00001', 'Primary Education', 'education', '500', '480', 'Ward 1 students', 'yes'],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// TEACHERS SHEET  (individual teacher records → survey_data.teachers)
// ─────────────────────────────────────────────────────────────────────────────
class TeachersTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use InstTemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Teachers'; }
    protected function headerColor(): string { return 'FF3730A3'; }
    protected function lightColor(): string  { return 'FFEEF2FF'; }
    protected function sampleColor(): string { return 'FFE0E7FF'; }
    protected function lastCol(): string     { return 'I'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            [
                'institution_code', 'full_name', 'gender', 'category',
                'qualification', 'appointment_level', 'specialization',
                'subjects', 'enrollment_date',
            ],
            [
                'Matches institution_code from Institutions sheet (schools only)',
                'Full name of teacher',
                'male / female / other',
                instOptHint($g, 'inst_teacher_category', 'permanent / temporary / contract / relief / volunteer'),
                instOptHint($g, 'inst_teacher_qualification', 'slc_see / plus_two / bachelor / master / mphil / phd / professor'),
                instOptHint($g, 'inst_teacher_level', 'early_childhood / basic_1_5 / basic_6_8 / secondary_9_10 / secondary_11_12 / bachelor / master'),
                instOptHint($g, 'inst_teacher_subject', 'nepali / english / math / science / social / …  ← See Valid Values'),
                'Pipe-separated codes for multiple subjects  (e.g. math|science|nepali)',
                'Enrollment date BS (YYYY-MM-DD, e.g. 2065-04-15)',
            ],
            [
                'IN-0001-W01-00001', 'Ram Prasad Sharma', 'male', 'permanent',
                'bachelor', 'secondary_9_10', 'math', 'math|science', '2065-04-15',
            ],
            [
                'IN-0001-W01-00001', 'Sita Devi Thapa', 'female', 'permanent',
                'master', 'secondary_11_12', 'nepali', 'nepali|literature_arts', '2060-07-22',
            ],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// TYPE-SPECIFIC SHEET  (school / cooperative / bank / NGO / market / industry / media / forest)
// ─────────────────────────────────────────────────────────────────────────────
class TypeSpecificTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    use InstTemplateSheetStyles;
    public function __construct(private array $grouped) {}
    public function title(): string { return 'TypeSpecific'; }
    protected function headerColor(): string { return 'FF065F46'; }
    protected function lightColor(): string  { return 'FFECFDF5'; }
    protected function sampleColor(): string { return 'FFD1FAE5'; }
    protected function lastCol(): string     { return 'AU'; }

    public function array(): array
    {
        $g = $this->grouped;
        return [
            // ── Header row
            [
                'institution_code', 'institution_type',
                // School
                'school_level', 'management_type', 'medium',
                // Cooperative
                'coop_type', 'share_capital', 'loan_outstanding',
                'male_members', 'female_members', 'other_members',
                // Bank
                'bank_type', 'branch_count', 'total_deposits', 'total_loans',
                // Government
                'office_level', 'department',
                // NGO / INGO
                'registration_type', 'donor', 'beneficiaries_m', 'beneficiaries_f', 'working_areas',
                // Religious
                'religion_type', 'daily_visitors', 'has_trust',
                // Market / Haat
                'shop_count', 'goods_types', 'market_days',
                // Industry
                'industry_type', 'annual_production', 'male_workers', 'female_workers',
                // Media
                'media_type', 'coverage_area', 'frequency',
                // Forest / CFUG
                'forest_type', 'forest_area_hectare', 'forest_covered_wards',
                'consumer_households', 'total_trees', 'annual_budget', 'annual_income',
                'member_count_m', 'member_count_f', 'committee_chairperson',
                'tree_types', 'herb_types',
            ],
            // ── Hints row
            [
                'Matches institution_code from Institutions sheet',
                instOptHint($g, 'inst_institution_type', 'school / health_post / cooperative / bank / government / ngo / religious / market / industry / media / forest'),
                // School hints
                instOptHint($g, 'inst_school_level', 'basic / secondary'),
                instOptHint($g, 'inst_management_type', 'community / institutional / government'),
                instOptHint($g, 'inst_medium', 'nepali / english / both'),
                // Cooperative hints
                instOptHint($g, 'inst_coop_type', 'savings / multipurpose / agriculture / consumer'),
                'Share capital in NPR',
                'Outstanding loan amount in NPR',
                'Male member count',
                'Female member count',
                'Other gender member count',
                // Bank hints
                instOptHint($g, 'inst_bank_type', 'commercial / development / finance / microfinance / cooperative'),
                'Number of branches',
                'Total deposits in NPR',
                'Total loans outstanding in NPR',
                // Government hints
                instOptHint($g, 'inst_office_level', 'federal / provincial / local'),
                'Department or ministry name',
                // NGO hints
                instOptHint($g, 'inst_registration_type', 'ngo / ingo / club / network'),
                'Donor / partner organization name',
                'Male beneficiaries count',
                'Female beneficiaries count',
                'Comma-separated working areas  ← See Valid Values',
                // Religious hints
                instOptHint($g, 'inst_religion_type', 'hindu / buddhist / christian / muslim / kirant / other'),
                'Average daily visitors',
                'yes/no — has registered trust/guthi',
                // Market hints
                'Number of shops',
                'Types of goods (free text)',
                'Comma-separated market days  (e.g. monday,thursday)',
                // Industry hints
                instOptHint($g, 'inst_industry_type', 'agro / food / textile / metal / wood / chemical / other'),
                'Annual production value in NPR',
                'Male worker count',
                'Female worker count',
                // Media hints
                instOptHint($g, 'inst_media_type', 'newspaper / radio / television / online / fm'),
                'Coverage area description',
                'Publication/broadcast frequency',
                // Forest hints
                instOptHint($g, 'inst_forest_type', 'community / leasehold / government / religious / buffer_zone'),
                'Forest area in hectares',
                'Comma-separated ward numbers covered',
                'Consumer household count',
                'Total trees count',
                'Annual budget in NPR',
                'Annual income in NPR',
                'Male committee member count',
                'Female committee member count',
                'Chairperson full name',
                'Comma-separated tree species codes  ← See Valid Values',
                'Comma-separated herb/plant codes  ← See Valid Values',
            ],
            // ── Sample row (school)
            [
                'IN-0001-W01-00001', 'school',
                'basic', 'community', 'nepali',
                '', '', '', '', '', '',
                '', '', '', '',
                '', '',
                '', '', '', '', '',
                '', '', '',
                '', '', '',
                '', '', '', '',
                '', '', '',
                '', '', '', '', '', '', '', '', '', '', '', '',
            ],
            // ── Sample row (cooperative)
            [
                'IN-0001-W01-00002', 'cooperative',
                '', '', '',
                'multipurpose', '5000000', '2000000', '120', '85', '5',
                '', '', '', '',
                '', '',
                '', '', '', '', '',
                '', '', '',
                '', '', '',
                '', '', '', '',
                '', '', '',
                '', '', '', '', '', '', '', '', '', '', '', '',
            ],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// DYNAMIC VALID VALUES SHEET  — generated from live survey options
// ─────────────────────────────────────────────────────────────────────────────
class InstDynamicValidValuesSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Valid Values'; }

    // Ordered sections mapping usage label → inst_ category keys
    private const SECTIONS = [
        'Institution & Ownership'    => ['inst_institution_type', 'inst_ownership_type'],
        'Infrastructure & Building'  => ['inst_building_type', 'inst_building_condition'],
        'Staff'                      => ['inst_staff_category'],
        'Education Grades'           => ['inst_grade'],
        'Services'                   => ['inst_service_type'],
        'Teacher Category'           => ['inst_teacher_category'],
        'Teacher Qualification'      => ['inst_teacher_qualification'],
        'Teacher Appointment Level'  => ['inst_teacher_level'],
        'Teacher Subject (विषय)'     => ['inst_teacher_subject'],
    ];

    public function array(): array
    {
        $rows    = [];
        $covered = [];

        // ── Header row
        $rows[] = [
            'Field / Category',
            'Valid Values  (use these exact codes when filling the import file)',
            'Nepali Labels',
            'Notes',
        ];

        // ── Static fields block
        $rows[] = ['━━ STATIC FIELDS ━━', '', '', ''];
        $rows[] = ['institution_code',  'Leave blank for auto-generation',                                     '',  'Format: IN-0001-W01-00001'];
        $rows[] = ['photo_number',      'Filename without extension  (e.g. 00131 or IN-131)',                  '',  'Must match file on photo server'];
        $rows[] = ['Boolean fields',    '1 = Yes / True  ·  0 = No / False  ·  yes / no  (case-insensitive)', '',  'For all has_*, is_* columns'];
        $rows[] = ['Date format',       'YYYY-MM-DD  (e.g. ' . date('Y-m-d') . ')',                           '',  'English calendar AD'];
        $rows[] = ['GPS coordinates',   'Decimal degrees  (lat: 27.7172 / lng: 85.3240)',                      '',  'Optional'];
        $rows[] = ['ward_number',       'Integer matching a ward in the system',                               '',  'e.g. 1, 2, 3 …'];
        $rows[] = ['', '', '', ''];

        // ── Survey-option sections (inst_ categories)
        foreach (self::SECTIONS as $section => $cats) {
            $sectionRows = [];
            foreach ($cats as $cat) {
                $covered[] = $cat;
                $opts = $this->grouped[$cat] ?? [];
                if (empty($opts)) continue;
                $values = implode(' / ', array_keys($opts));
                $labels = implode(' / ', array_values($opts));
                // Display without the inst_ prefix for readability
                $fieldName    = preg_replace('/^inst_/', '', $cat);
                $sectionRows[] = [$fieldName, $values, $labels, ''];
            }
            if (!empty($sectionRows)) {
                $rows[] = ["━━ {$section} ━━", '', '', ''];
                foreach ($sectionRows as $r) $rows[] = $r;
                $rows[] = ['', '', '', ''];
            }
        }

        // ── Any remaining inst_ categories not already in the predefined sections
        $extraRows = [];
        foreach ($this->grouped as $cat => $opts) {
            if (!str_starts_with($cat, 'inst_') || in_array($cat, $covered) || empty($opts)) continue;
            $fieldName  = preg_replace('/^inst_/', '', $cat);
            $extraRows[] = [$fieldName, implode(' / ', array_keys($opts)), implode(' / ', array_values($opts)), ''];
        }
        if (!empty($extraRows)) {
            $rows[] = ['━━ Additional Institution Options ━━', '', '', ''];
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
            $cell      = $sheet->getCell("A{$row}")->getValue();
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
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                    'alignment' => ['wrapText' => true],
                ]);
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF374151']],
                ]);
                $sheet->getStyle("B{$row}")->applyFromArray([
                    'font' => ['color' => ['argb' => 'FF1D4ED8']],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(24);
                $dataRowColor = !$dataRowColor;
            }
        }

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(90);
        $sheet->getColumnDimension('C')->setWidth(50);
        $sheet->getColumnDimension('D')->setWidth(25);
        return [];
    }
}
