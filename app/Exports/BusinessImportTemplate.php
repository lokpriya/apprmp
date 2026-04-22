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

class BusinessImportTemplate implements WithMultipleSheets
{
    public function __construct(private int $lgId) {}

    public function sheets(): array
    {
        $grouped = SurveyOption::allGrouped($this->lgId)->all();
        return [
            new BusinessMainTemplateSheet($grouped),
            new BusinessValidValuesSheet($grouped),
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SHARED HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Build a human-readable hint from live survey options (business variant).
 * Falls back to $static if the category has no DB options.
 */
function bizOptHint(array $grouped, string $cat, string $static = '', int $max = 6): string
{
    $opts = $grouped[$cat] ?? [];
    if (empty($opts)) return $static ?: '—';
    $vals    = array_keys($opts);
    $preview = implode(' / ', array_slice($vals, 0, $max));
    $extra   = count($vals) > $max ? ' / … (+' . (count($vals) - $max) . ' more)' : '';
    return $preview . $extra . '  ← See Valid Values sheet';
}

// ─────────────────────────────────────────────────────────────────────────────
// MAIN BUSINESSES SHEET
// ─────────────────────────────────────────────────────────────────────────────
class BusinessMainTemplateSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Businesses'; }

    private const HEADER_COLOR  = 'FFD97706';   // amber-600
    private const LIGHT_COLOR   = 'FFFEF3C7';   // amber-50
    private const SAMPLE_COLOR  = 'FFFDE68A';   // amber-200

    public function array(): array
    {
        $g = $this->grouped;

        // ── Row 1: Column names ──────────────────────────────────────────────
        $headers = [
            // Identification
            'business_code', 'household_code', 'member_name',
            'ward_number', 'settlement_name',

            // Step 1 — General info
            'business_name', 'owner_father_name', 'owner_grand_father_name',
            'business_location', 'business_type', 'business_type_other',

            // Step 2 — Field / sub-category
            'business_field', 'business_field_other', 'business_sub_category',

            // Step 3 — Registration & tax
            'is_registered',
            'place_of_registration',
            'place_of_registration_other',
            'reg_no_municipality', 'reg_no_home_cottage',
            'reg_no_company_registrar', 'reg_no_fcci', 'reg_no_govt_office',
            'has_pan_vat', 'pan_vat_number',
            'paid_business_tax', 'tax_amount', 'contact_number',
        ];

        // ── Row 2: Hints ─────────────────────────────────────────────────────
        $hints = [
            'Auto-generated if blank (e.g. BZ-0001-W01-00001)',
            'Required — household unique_code (e.g. HH-0001-W01-00001)',
            'Required — full name of the member who owns this business',
            'Ward number (auto-resolved from household if blank)',
            'Settlement / Tole name (optional)',

            'Required — business name',
            'Owner\'s father name (optional)',
            'Owner\'s grandfather name (optional)',
            bizOptHint($g, 'business_location', 'home / market / road_side / rented / own_building / other'),
            bizOptHint($g, 'business_type', 'sole_proprietorship / partnership / cooperative / company / other'),
            'If business_type = other, specify here',

            bizOptHint($g, 'business_field', 'production / agriculture / trade / services / other'),
            'If business_field = other, specify here',
            'Sub-category code (optional) — See Valid Values sheet for all sub_ categories',

            'yes/no — is the business registered?',
            'Comma-separated registration places: ' . bizOptHint($g, 'business_registration_place', 'municipality / cottage_industry / company_registrar / fcci / other'),
            'If place_of_registration includes other, specify here',
            'Municipality registration number',
            'Cottage/Home industry registration number',
            'Company registrar registration number',
            'FCCI registration number',
            'Other government office registration number',
            'yes/no — registered for PAN or VAT?',
            'PAN or VAT number (if has_pan_vat = yes)',
            'yes/no — paid business tax last year?',
            'Tax amount in NPR (if paid_business_tax = yes)',
            'Contact phone number',
        ];

        // ── Row 3: Sample data ───────────────────────────────────────────────
        $bizLocationKeys  = array_keys($g['business_location'] ?? []);
        $bizTypeKeys      = array_keys($g['business_type'] ?? []);
        $bizFieldKeys     = array_keys($g['business_field'] ?? []);
        $bizRegPlaceKeys  = array_keys($g['business_registration_place'] ?? []);

        $sample = [
            '',                                    // business_code (auto)
            'HH-0001-W01-00001',                   // household_code
            'राम बहादुर श्रेष्ठ',                  // member_name
            '1',                                   // ward_number
            '',                                    // settlement_name
            'श्रेष्ठ किराना पसल',                 // business_name
            'लक्ष्मण श्रेष्ठ',                   // owner_father_name
            'धन बहादुर श्रेष्ठ',                   // owner_grand_father_name
            $bizLocationKeys[0] ?? 'market',       // business_location
            $bizTypeKeys[0]     ?? 'sole_proprietorship', // business_type
            '',                                    // business_type_other
            $bizFieldKeys[0]    ?? 'trade',        // business_field
            '',                                    // business_field_other
            '',                                    // business_sub_category
            'yes',                                 // is_registered
            $bizRegPlaceKeys[0] ?? 'municipality', // place_of_registration
            '',                                    // place_of_registration_other
            'MUN-2081-001',                        // reg_no_municipality
            '',                                    // reg_no_home_cottage
            '',                                    // reg_no_company_registrar
            '',                                    // reg_no_fcci
            '',                                    // reg_no_govt_office
            'no',                                  // has_pan_vat
            '',                                    // pan_vat_number
            'yes',                                 // paid_business_tax
            '5000',                                // tax_amount
            '9841000001',                          // contact_number
        ];

        return [$headers, $hints, $sample];
    }

    public function styles(Worksheet $sheet): array
    {
        $lc = 'AA';   // last column (27 columns = A..AA)
        $sheet->getStyle("A1:{$lc}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_COLOR]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle("A2:{$lc}2")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF6B7280']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::LIGHT_COLOR]],
            'alignment' => ['wrapText' => true],
        ]);
        $highest = $sheet->getHighestRow();
        if ($highest >= 3) {
            $sheet->getStyle("A3:{$lc}{$highest}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::SAMPLE_COLOR]],
            ]);
        }
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(60);
        return [];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// VALID VALUES SHEET — dynamic from live survey options
// ─────────────────────────────────────────────────────────────────────────────
class BusinessValidValuesSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(private array $grouped) {}
    public function title(): string { return 'Valid Values'; }

    private const SECTIONS = [
        'Business Location'     => ['business_location'],
        'Business Type'         => ['business_type'],
        'Business Field'        => ['business_field'],
        'Registration Places'   => ['business_registration_place'],
        'Sub-categories'        => [
            'sub_ProductionIndustry', 'sub_AgricultureAnimalHusbandary',
            'sub_Suppliers', 'sub_InformationAndCommunication',
            'sub_HotelAndRestaurant', 'sub_VehicleAndTransportation',
            'sub_EducationalInstutions', 'sub_ConsultancyBusiness',
            'sub_MechinaryMaintenance', 'sub_HospitalandClinc',
            'sub_FinancialInstutions', 'sub_AbroadjobsSecurity',
            'sub_FancyBusiness', 'sub_OtherShopBusiness',
        ],
    ];

    public function array(): array
    {
        $rows    = [];
        $covered = [];

        // ── Header
        $rows[] = ['Field / Category', 'Valid Values  (use these exact codes)', 'Nepali Labels', 'Notes'];

        // ── Static fields
        $rows[] = ['━━ STATIC / FIXED FIELDS ━━', '', '', ''];
        $rows[] = ['business_code',        'Leave blank for auto-generation',                                      '', 'Format: BZ-0001-W01-00001'];
        $rows[] = ['household_code',       'Must match unique_code of an existing household',                      '', 'e.g. HH-0001-W01-00001'];
        $rows[] = ['member_name',          'Full name of the household member who owns this business',             '', 'Must be ≥16 yrs old & no existing business record'];
        $rows[] = ['Boolean fields',       '1 = Yes  ·  0 = No  ·  yes / no  (case-insensitive)',                 '', 'For is_registered, has_pan_vat, paid_business_tax'];
        $rows[] = ['place_of_registration','Comma-separated codes  (e.g. municipality,fcci)',                      '', 'Multiple values allowed'];
        $rows[] = ['tax_amount',           'Numeric value in NPR  (e.g. 5000 or 5000.00)',                        '', 'Only if paid_business_tax = yes'];
        $rows[] = ['', '', '', ''];

        // ── Survey option sections
        foreach (self::SECTIONS as $section => $cats) {
            $sectionRows = [];
            foreach ($cats as $cat) {
                $covered[] = $cat;
                $opts = $this->grouped[$cat] ?? [];
                if (empty($opts)) continue;
                // Strip prefix for display
                $fieldName = preg_replace('/^(business_|sub_)/', '', $cat);
                $values    = implode(' / ', array_keys($opts));
                $labels    = implode(' / ', array_values($opts));
                $sectionRows[] = [$fieldName, $values, $labels, ''];
            }
            if (!empty($sectionRows)) {
                $rows[] = ["━━ {$section} ━━", '', '', ''];
                foreach ($sectionRows as $r) $rows[] = $r;
                $rows[] = ['', '', '', ''];
            }
        }

        // ── Any remaining business_ or sub_ categories not yet listed
        $extraRows = [];
        foreach ($this->grouped as $cat => $opts) {
            $isBiz = str_starts_with($cat, 'business_') || str_starts_with($cat, 'sub_');
            if (!$isBiz || in_array($cat, $covered) || empty($opts)) continue;
            $fieldName   = preg_replace('/^(business_|sub_)/', '', $cat);
            $extraRows[] = [$fieldName, implode(' / ', array_keys($opts)), implode(' / ', array_values($opts)), ''];
        }
        if (!empty($extraRows)) {
            $rows[] = ['━━ Additional Business Options ━━', '', '', ''];
            foreach ($extraRows as $r) $rows[] = $r;
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $highest = $sheet->getHighestRow();

        $sheet->getStyle('A1:D1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF92400E']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $dataRowColor = true;
        for ($row = 2; $row <= $highest; $row++) {
            $cell      = $sheet->getCell("A{$row}")->getValue();
            $isSection = is_string($cell) && str_starts_with($cell, '━━');
            $isEmpty   = empty($cell);

            if ($isSection) {
                $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF92400E']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEF3C7']],
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
                    'font' => ['color' => ['argb' => 'FFB45309']],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(24);
                $dataRowColor = !$dataRowColor;
            }
        }

        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(90);
        $sheet->getColumnDimension('C')->setWidth(50);
        $sheet->getColumnDimension('D')->setWidth(30);
        return [];
    }
}
