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
            'Data'       => new SurveyOptionsDataSheet(),
            'Categories' => new SurveyOptionsCategoriesSheet(),
        ];
    }
}

class SurveyOptionsDataSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function title(): string { return 'Data'; }

    public function headings(): array
    {
        return ['category', 'value', 'label_ne', 'label_en', 'sort_order', 'is_active'];
    }

    public function array(): array
    {
        // Sample rows — one per commonly used category
        return [
            ['ethnicity',    'brahmin',      'ब्राह्मण',      'Brahmin',      1, 1],
            ['ethnicity',    'chhetri',      'क्षेत्री',       'Chhetri',      2, 1],
            ['religion',     'hindu',        'हिन्दू',         'Hindu',        1, 1],
            ['religion',     'buddhist',     'बौद्ध',          'Buddhist',     2, 1],
            ['water_source', 'piped_water',  'पाइप धारा',     'Piped Water',  1, 1],
            ['roof_type',    'tin_sheet',    'जस्ता पाता',    'Tin Sheet',    1, 1],
            ['education_level','primary',    'प्राथमिक',      'Primary',      1, 1],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 22, 'B' => 20, 'C' => 28, 'D' => 25, 'E' => 13, 'F' => 11];
    }

    public function styles(Worksheet $sheet): array
    {
        // Header row styling
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '206bc4']],
        ]);
        // Data rows — zebra
        $sheet->getStyle('A2:F8')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f4f6fb']],
        ]);
        // Instruction note in row 10
        $sheet->setCellValue('A10', '⚠  category must match a key from the "Categories" sheet exactly.');
        $sheet->setCellValue('A11', '⚠  value must contain only a-z, 0-9, underscore (_) or hyphen (-).');
        $sheet->setCellValue('A12', '⚠  is_active: 1 = active, 0 = inactive. Leave blank for active.');
        $sheet->getStyle('A10:A12')->applyFromArray([
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
        return ['category_key', 'label'];
    }

    public function array(): array
    {
        return array_map(
            fn($key, $label) => [$key, $label],
            array_keys(SurveyOption::categories()),
            array_values(SurveyOption::categories())
        );
    }

    public function columnWidths(): array
    {
        return ['A' => 28, 'B' => 50];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '182433']],
        ]);

        return [];
    }
}
