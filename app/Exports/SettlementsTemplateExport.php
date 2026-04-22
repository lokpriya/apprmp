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

class SettlementsTemplateExport implements WithMultipleSheets
{
    public function __construct(private readonly int $lgId) {}

    public function sheets(): array
    {
        return [
            new SettlementsDataSheet($this->lgId),
            new SettlementsWardsSheet($this->lgId),
        ];
    }
}

// ── Data sheet ────────────────────────────────────────────────────────────────

class SettlementsDataSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(private readonly int $lgId) {}

    public function title(): string { return 'Settlements'; }

    public function headings(): array
    {
        return ['ward_number', 'name_np', 'name_en', 'settlement_type'];
    }

    public function array(): array
    {
        // Sample rows
        return [
            [1, 'मेचीनगर टोल',    'Mechinagar Tole',    'tole'],
            [1, 'बजार चोक',       'Bazar Chowk',        'chowk'],
            [2, 'माझगाउँ',        'Majhgaun',           'gau'],
            [2, 'नयाँ बस्ती',     'Naya Basti',         'basti'],
            [3, 'पुरानो टोल',     'Purano Tole',        'tole'],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 28, 'C' => 28, 'D' => 18];
    }

    public function styles(Worksheet $sheet): array
    {
        // Header row
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '206bc4']],
        ]);
        // Sample data rows
        $sheet->getStyle('A2:D6')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f4f6fb']],
        ]);
        // Notes
        $sheet->setCellValue('A8', '⚠  ward_number must match a ward listed in the "Wards" sheet.');
        $sheet->setCellValue('A9', '⚠  settlement_type must be exactly: tole | gau | chowk | basti');
        $sheet->setCellValue('A10', '⚠  Duplicate name_np in the same ward will update the existing record.');
        $sheet->getStyle('A8:A10')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => 'd47b00'], 'size' => 10],
        ]);

        return [];
    }
}

// ── Wards reference sheet ─────────────────────────────────────────────────────

class SettlementsWardsSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(private readonly int $lgId) {}

    public function title(): string { return 'Wards'; }

    public function headings(): array
    {
        return ['ward_number', 'ward_chairperson', 'existing_settlements'];
    }

    public function array(): array
    {
        return Ward::where('local_government_id', $this->lgId)
            ->withCount('settlements')
            ->orderBy('ward_number')
            ->get()
            ->map(fn($w) => [
                $w->ward_number,
                $w->ward_chairperson ?? '—',
                $w->settlements_count,
            ])
            ->toArray();
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 28, 'C' => 22];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '182433']],
        ]);

        return [];
    }
}
