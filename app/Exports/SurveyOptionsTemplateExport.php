<?php

namespace App\Exports;

use App\Models\SurveyOption;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SurveyOptionsTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'घरधुरी'     => new SurveyOptionsDataSheet('household'),
            'संस्था'      => new SurveyOptionsDataSheet('institution'),
            'व्यवसाय'    => new SurveyOptionsDataSheet('business'),
            'Categories' => new SurveyOptionsCategoriesSheet(),
        ];
    }
}

class SurveyOptionsDataSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    private static array $samples = [
        'household' => [
            ['ethnicity',      'brahmin',         'ब्राह्मण',        'Brahmin',         1, 1],
            ['ethnicity',      'chhetri',         'क्षेत्री',         'Chhetri',         2, 1],
            ['religion',       'hindu',           'हिन्दू',           'Hindu',           1, 1],
            ['religion',       'buddhist',        'बौद्ध',            'Buddhist',        2, 1],
            ['water_source',   'piped_water',     'पाइप धारा',       'Piped Water',     1, 1],
            ['roof_type',      'tin_sheet',       'जस्ता पाता',      'Tin Sheet',       1, 1],
            ['education_level','primary',         'प्राथमिक',        'Primary',         1, 1],
        ],
        'institution' => [
            ['inst_ownership_type',    'govt',        'सरकारी',          'Government',      1, 1],
            ['inst_ownership_type',    'private',     'निजी',            'Private',         2, 1],
            ['inst_ownership_type',    'community',   'सामुदायिक',       'Community',       3, 1],
            ['inst_building_condition','good',        'राम्रो',           'Good',            1, 1],
            ['inst_building_condition','poor',        'जीर्ण',            'Poor',            3, 1],
            ['inst_school_level',      'primary',     'प्राथमिक',        'Primary',         1, 1],
            ['inst_health_type',       'hospital',    'अस्पताल',         'Hospital',        1, 1],
        ],
        'business' => [
            ['business_type',  'sole_proprietor', 'एकल स्वामित्व',  'Sole Proprietor', 1, 1],
            ['business_type',  'partnership',     'साझेदारी',        'Partnership',     2, 1],
            ['business_field', 'retail',          'खुद्रा व्यापार',  'Retail',          1, 1],
            ['business_field', 'manufacturing',   'उत्पादन',         'Manufacturing',   2, 1],
            ['business_field', 'service',         'सेवा',            'Service',         3, 1],
        ],
    ];

    private static array $colors = [
        'household'   => '206bc4',
        'institution' => '4c6ef5',
        'business'    => 'f76707',
    ];

    public function __construct(private string $type) {}

    public function title(): string
    {
        return match ($this->type) {
            'household'   => 'घरधुरी',
            'institution' => 'संस्था',
            'business'    => 'व्यवसाय',
        };
    }

    public function headings(): array
    {
        return ['category', 'value', 'label_ne', 'label_en', 'sort_order', 'is_active'];
    }

    public function array(): array
    {
        return self::$samples[$this->type];
    }

    public function columnWidths(): array
    {
        return ['A' => 24, 'B' => 22, 'C' => 28, 'D' => 25, 'E' => 13, 'F' => 11];
    }

    public function styles(Worksheet $sheet): array
    {
        $color    = self::$colors[$this->type];
        $rowCount = count(self::$samples[$this->type]);
        $lastRow  = $rowCount + 1;

        $sheet->getStyle("A1:F1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
        ]);
        $sheet->getStyle("A2:F{$lastRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f4f6fb']],
        ]);

        $notesRow = $lastRow + 2;
        $sheet->setCellValue("A{$notesRow}",       '⚠  category must match a key from the "Categories" sheet exactly.');
        $sheet->setCellValue("A" . ($notesRow + 1), '⚠  value must contain only a-z, 0-9, underscore (_) or hyphen (-).');
        $sheet->setCellValue("A" . ($notesRow + 2), '⚠  is_active: 1 = active, 0 = inactive. Leave blank for active.');
        $sheet->getStyle("A{$notesRow}:A" . ($notesRow + 2))->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => 'd47b00'], 'size' => 10],
        ]);

        return [];
    }
}

class SurveyOptionsCategoriesSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function title(): string { return 'Categories'; }

    public function headings(): array
    {
        return ['category_key', 'label', 'sheet'];
    }

    public function array(): array
    {
        $rows = [];

        $sections = [
            'घरधुरी (Household)'   => SurveyOption::householdCategories(),
            'संस्था (Institution)'  => SurveyOption::institutionCategories(),
            'व्यवसाय (Business)'   => SurveyOption::businessCategories(),
        ];

        foreach ($sections as $sheetName => $categories) {
            $rows[] = ['── ' . $sheetName . ' ──', '', ''];
            foreach ($categories as $key => $label) {
                $rows[] = [$key, $label, $sheetName];
            }
            $rows[] = ['', '', ''];
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 30, 'B' => 52, 'C' => 26];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '182433']],
        ]);

        $sectionColors = [
            'घरधुरी (Household)'   => ['bg' => 'dbeafe', 'font' => '1e40af'],
            'संस्था (Institution)'  => ['bg' => 'e0e7ff', 'font' => '3730a3'],
            'व्यवसाय (Business)'   => ['bg' => 'ffedd5', 'font' => '9a3412'],
        ];

        $row = 2;
        $sections = [
            'घरधुरी (Household)'   => SurveyOption::householdCategories(),
            'संस्था (Institution)'  => SurveyOption::institutionCategories(),
            'व्यवसाय (Business)'   => SurveyOption::businessCategories(),
        ];

        foreach ($sections as $sheetName => $categories) {
            $c = $sectionColors[$sheetName];
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => $c['font']], 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $c['bg']]],
            ]);
            $row++;
            $row += count($categories);
            $row++; // blank separator row
        }

        return [];
    }
}
