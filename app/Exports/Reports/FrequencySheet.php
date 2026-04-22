<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * One Excel sheet for a frequency distribution from CrossTabService::freq().
 *
 * Layout:
 *   Row 1 — Title (blue header, merged A:D)
 *   Row 2 — Filter / metadata (light blue)
 *   Row 3 — Column headers: श्रेणी | क्र.सं. | संख्या | प्रतिशत
 *   Row 4…N+3 — Data rows (rank, label, count, pct)
 *   Row N+4 — Totals row
 */
class FrequencySheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    private int $numRows; // incl. 3 header rows + data rows + totals row

    public function __construct(
        private string $sheetTitle,
        private string $tableTitle,
        private array  $data,          // CrossTabService::freq() result: [{key, label, count}]
        private string $lgName,
        private string $filterSummary,
    ) {
        $this->numRows = count($this->data) + 4; // 3 headers + data + totals
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31);
    }

    public function array(): array
    {
        $output     = [];
        $grandTotal = array_sum(array_column($this->data, 'count'));

        // ── Row 1: Title ──
        $output[] = [$this->tableTitle, '', '', ''];

        // ── Row 2: Metadata ──
        $output[] = [
            $this->filterSummary . '  |  ' . $this->lgName . '  |  ' . now()->format('Y-m-d H:i'),
            '', '', '',
        ];

        // ── Row 3: Headers ──
        $output[] = ['क्र.सं.', 'श्रेणी', 'संख्या', 'प्रतिशत (%)'];

        // ── Data rows ──
        foreach ($this->data as $idx => $item) {
            $pct = $grandTotal > 0 ? round(($item['count'] / $grandTotal) * 100, 2) : 0;
            $output[] = [
                $idx + 1,
                $item['label'] ?? $item['key'],
                $item['count'],
                $pct,
            ];
        }

        // ── Totals row ──
        $output[] = ['', 'जम्मा', $grandTotal, 100.00];

        return $output;
    }

    public function styles(Worksheet $sheet): array
    {
        $dataEnd  = $this->numRows - 1;
        $totalRow = $this->numRows;

        // ── Row 1: Title — merge + blue header ──
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // ── Row 2: Metadata — merge + light blue ──
        $sheet->mergeCells('A2:D2');
        $sheet->getStyle('A2:D2')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF4A6D9A']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE8F0FC']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // ── Row 3: Column headers ──
        $sheet->getStyle('A3:D3')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF206BC4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(24);

        // ── Data rows — alternating + number formatting ──
        if ($dataEnd >= 4) {
            for ($r = 4; $r <= $dataEnd; $r++) {
                $bg = ($r % 2 === 0) ? 'FFF0F4FA' : 'FFFFFFFF';
                $sheet->getStyle("A{$r}:D{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                ]);
            }
            // Rank col (A) — center + muted
            $sheet->getStyle("A4:A{$dataEnd}")->applyFromArray([
                'font'      => ['color' => ['argb' => 'FF9AA0AC']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            // Count col (C) + pct col (D) — right aligned
            $sheet->getStyle("C4:D{$dataEnd}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
            // Number format for percentage column
            $sheet->getStyle("D4:D{$dataEnd}")
                  ->getNumberFormat()->setFormatCode('0.00"%"');
        }

        // ── Totals row ──
        $sheet->getStyle("A{$totalRow}:D{$totalRow}")->applyFromArray([
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD0E0F8']],
        ]);
        $sheet->getStyle("C{$totalRow}:D{$totalRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $sheet->getStyle("D{$totalRow}")
              ->getNumberFormat()->setFormatCode('0.00"%"');

        // ── Border on data range ──
        $sheet->getStyle("A3:D{$totalRow}")->applyFromArray([
            'borders' => [
                'outline'    => ['borderStyle' => Border::BORDER_THIN,  'color' => ['argb' => 'FFCDD5E1']],
                'allBorders' => ['borderStyle' => Border::BORDER_HAIR,  'color' => ['argb' => 'FFE6E7E9']],
            ],
        ]);

        // ── Freeze top 3 header rows ──
        $sheet->freezePane('A4');

        return [];
    }
}
