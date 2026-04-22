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

class WardsTemplateExport implements WithMultipleSheets
{
    public function __construct(private readonly int $lgId) {}

    public function sheets(): array
    {
        return [
            new WardsDataSheet($this->lgId),
            new WardsExistingSheet($this->lgId),
        ];
    }
}

// ── Data sheet ────────────────────────────────────────────────────────────────

class WardsDataSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(private readonly int $lgId) {}

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
        $sheet->setCellValue('A6', '⚠  ward_number 1–35 को बीचमा हुनुपर्छ।');
        $sheet->setCellValue('A7', '⚠  अवस्थित ward_number भएमा Chairperson र Phone अपडेट हुन्छ।');
        $sheet->setCellValue('A8', '⚠  नयाँ ward_number भएमा नयाँ वडा सिर्जना हुन्छ (ward_count सेटिङसँग मिल्नुपर्छ)।');
        $sheet->getStyle('A6:A8')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => 'd47b00'], 'size' => 10],
        ]);

        return [];
    }
}

// ── Existing wards reference sheet ────────────────────────────────────────────

class WardsExistingSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(private readonly int $lgId) {}

    public function title(): string { return 'Existing Wards'; }

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
