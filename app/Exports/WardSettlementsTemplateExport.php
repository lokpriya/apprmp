<?php

namespace App\Exports;

use App\Models\Ward;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WardSettlementsTemplateExport implements WithMultipleSheets
{
    public function __construct(private readonly int $lgId) {}

    public function sheets(): array
    {
        return [
            new WardSheet(),
            new SettlementSheet(),
            new WardReferenceSheet($this->lgId),
        ];
    }
}

// ── Sheet 1: Wards ────────────────────────────────────────────────────────────

class WardSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function title(): string { return 'Wards'; }

    public function headings(): array
    {
        return ['ward_number', 'ward_chairperson', 'contact_phone'];
    }

    public function array(): array
    {
        return [
            [1, 'राम बहादुर थापा',  '9812345678'],
            [2, 'सीता देवी श्रेष्ठ', '9823456789'],
            [3, 'हरि प्रसाद पौडेल',  '9834567890'],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 28, 'C' => 18];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '206bc4']],
        ]);
        $sheet->getStyle('A2:C4')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f4f6fb']],
        ]);
        $sheet->setCellValue('A6', '⚠  ward_number must be between 1 and 35.');
        $sheet->setCellValue('A7', '⚠  Existing ward_number → updates chairperson & phone only.');
        $sheet->setCellValue('A8', '⚠  New ward_number → creates a new ward.');
        $sheet->getStyle('A6:A8')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => 'd47b00'], 'size' => 10],
        ]);

        return [];
    }
}

// ── Sheet 2: Settlements ──────────────────────────────────────────────────────

class SettlementSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function title(): string { return 'Settlements'; }

    public function headings(): array
    {
        return ['ward_number', 'name_np', 'name_en', 'settlement_type'];
    }

    public function array(): array
    {
        return [
            [1, 'मेचीनगर टोल',  'Mechinagar Tole',  'tole'],
            [1, 'बजार चोक',     'Bazar Chowk',      'chowk'],
            [2, 'माझगाउँ',      'Majhgaun',         'gau'],
            [2, 'नयाँ बस्ती',   'Naya Basti',       'basti'],
            [3, 'पुरानो टोल',   'Purano Tole',      'tole'],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 28, 'C' => 28, 'D' => 18];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '206bc4']],
        ]);
        $sheet->getStyle('A2:D6')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f4f6fb']],
        ]);
        $sheet->setCellValue('A8',  '⚠  ward_number must match a ward in the "Wards" sheet or an existing ward.');
        $sheet->setCellValue('A9',  '⚠  settlement_type must be exactly: tole | gau | chowk | basti');
        $sheet->setCellValue('A10', '⚠  Duplicate name_np in the same ward → updates the existing record.');
        $sheet->getStyle('A8:A10')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => 'd47b00'], 'size' => 10],
        ]);

        return [];
    }
}

// ── Sheet 3: Reference (existing wards) ──────────────────────────────────────

class WardReferenceSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(private readonly int $lgId) {}

    public function title(): string { return 'Reference'; }

    public function headings(): array
    {
        return ['ward_number', 'ward_chairperson', 'contact_phone', 'settlements_count'];
    }

    public function array(): array
    {
        return Ward::where('local_government_id', $this->lgId)
            ->withCount('settlements')
            ->orderBy('ward_number')
            ->get()
            ->map(fn($w) => [
                $w->ward_number,
                $w->ward_chairperson ?? '',
                $w->contact_phone    ?? '',
                $w->settlements_count,
            ])
            ->toArray();
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 28, 'C' => 18, 'D' => 18];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '182433']],
        ]);

        return [];
    }
}
