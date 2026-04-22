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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * One Excel sheet for a cross-tabulation matrix from CrossTabService::tab().
 *
 * Layout:
 *   Row 1 — Category title (blue header, merged across all cols)
 *   Row 2 — Filter summary + generated date (light blue)
 *   Row 3 — Column headers (bold, dark bg)
 *   Row 4…N+3 — Data rows
 *   Row N+4 — Totals row (bold, tinted)
 */
class CrossTabSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    private int $numCols;  // total columns incl. row-label col + totals col
    private int $numRows;  // total rows incl. 3 header rows + data rows + totals row

    public function __construct(
        private string $sheetTitle,
        private string $tableTitle,
        private array  $data,       // CrossTabService::tab() result
        private string $lgName,
        private string $filterSummary,
    ) {
        $cols          = count($this->data['cols'] ?? []);
        $this->numCols = $cols + 2; // row-label col + data cols + totals col
        $rows          = count($this->data['rows'] ?? []);
        $this->numRows = $rows + 4; // 3 header rows + data rows + totals row
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31); // Excel sheet name limit
    }

    public function array(): array
    {
        $data    = $this->data;
        $cols    = $data['cols']    ?? [];
        $rows    = $data['rows']    ?? [];
        $matrix  = $data['matrix'] ?? [];
        $lastCol = $this->colLetter($this->numCols);

        $output = [];

        // ── Row 1: Title ──
        $titleRow = array_fill(0, $this->numCols, '');
        $titleRow[0] = $this->tableTitle;
        $output[] = $titleRow;

        // ── Row 2: Filter / metadata ──
        $metaRow = array_fill(0, $this->numCols, '');
        $metaRow[0] = $this->filterSummary . '  |  ' . $this->lgName . '  |  ' . now()->format('Y-m-d H:i');
        $output[] = $metaRow;

        // ── Row 3: Column headers ──
        $headerRow = [''];
        foreach ($cols as $col) {
            $headerRow[] = $data['col_labels'][$col] ?? $col;
        }
        $headerRow[] = 'जम्मा';
        $output[] = $headerRow;

        // ── Data rows ──
        foreach ($rows as $rowKey) {
            $row   = [$data['row_labels'][$rowKey] ?? $rowKey];
            foreach ($cols as $col) {
                $row[] = $matrix[$rowKey][$col] ?? 0;
            }
            $row[] = $data['row_totals'][$rowKey] ?? 0;
            $output[] = $row;
        }

        // ── Totals row ──
        $totalsRow = ['जम्मा'];
        foreach ($cols as $col) {
            $totalsRow[] = $data['col_totals'][$col] ?? 0;
        }
        $totalsRow[] = $data['grand_total'] ?? 0;
        $output[] = $totalsRow;

        return $output;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol  = $this->colLetter($this->numCols);
        $dataEnd  = $this->numRows - 1;  // last data row (before totals)
        $totalRow = $this->numRows;      // totals row number

        // ── Row 1: Title — merge + blue header ──
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // ── Row 2: Metadata — merge + light blue ──
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF4A6D9A']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE8F0FC']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // ── Row 3: Column headers ──
        $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF206BC4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(24);

        // ── Data rows ──
        if ($dataEnd >= 4) {
            // Alternating rows
            for ($r = 4; $r <= $dataEnd; $r++) {
                $bg = ($r % 2 === 0) ? 'FFF0F4FA' : 'FFFFFFFF';
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                ]);
            }
            // Row label column (col A) — bold
            $sheet->getStyle("A4:A{$dataEnd}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FF1D273B']],
            ]);
            // Number columns — right-aligned
            $numStart = 'B';
            $sheet->getStyle("{$numStart}4:{$lastCol}{$dataEnd}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
        }

        // ── Totals row ──
        $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FF1D273B']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD0E0F8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $sheet->getStyle("A{$totalRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // ── Totals column (last col) — light highlight ──
        if ($dataEnd >= 4) {
            $sheet->getStyle("{$lastCol}4:{$lastCol}{$dataEnd}")->applyFromArray([
                'font'      => ['bold' => true],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE8F0FC']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
        }

        // ── Outer border on data+totals range ──
        $sheet->getStyle("A3:{$lastCol}{$totalRow}")->applyFromArray([
            'borders' => [
                'outline'     => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCDD5E1']],
                'allBorders'  => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FFE6E7E9']],
            ],
        ]);

        return [];
    }

    private function colLetter(int $n): string
    {
        return Coordinate::stringFromColumnIndex($n);
    }
}
