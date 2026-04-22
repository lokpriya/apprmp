<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Multi-sheet Excel export for a public cross-tab report.
 *
 * Sheet layout:
 *   Sheet 0 — "सारांश" cover sheet (LG info + table of contents)
 *   Sheet 1…N — One CrossTabSheet per cross-tab table
 *   Sheet N+1…M — One FrequencySheet per frequency distribution
 */
class ReportExport implements WithMultipleSheets
{
    public function __construct(
        private string $categoryTitle,
        private string $lgName,
        private string $filterSummary,
        private array  $tables,       // [['id','title','title_en','data' => tab() result]]
        private array  $frequencies,  // [['id','title','title_en','data' => freq() result]]
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        // ── Cover sheet ──
        $sheets[] = new ReportCoverSheet(
            $this->categoryTitle,
            $this->lgName,
            $this->filterSummary,
            $this->tables,
            $this->frequencies,
        );

        // ── Cross-tab sheets ──
        foreach ($this->tables as $table) {
            $sheets[] = new CrossTabSheet(
                sheetTitle:    $this->abbrev($table['title_en'] ?? $table['title'], 1),
                tableTitle:    $table['title'],
                data:          $table['data'],
                lgName:        $this->lgName,
                filterSummary: $this->filterSummary,
            );
        }

        // ── Frequency sheets ──
        foreach ($this->frequencies as $freq) {
            $sheets[] = new FrequencySheet(
                sheetTitle:    $this->abbrev($freq['title_en'] ?? $freq['title'], count($this->tables) + 1),
                tableTitle:    $freq['title'],
                data:          $freq['data'],
                lgName:        $this->lgName,
                filterSummary: $this->filterSummary,
            );
        }

        return $sheets;
    }

    /** Ensure unique, max-31-char sheet name. */
    private function abbrev(string $title, int $idx): string
    {
        $clean = preg_replace('/[\/\\\?\*\[\]:]+/', ' ', $title);
        return mb_substr(trim($clean), 0, 28) . ' ' . ($idx + 1);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Cover / Summary sheet
// ─────────────────────────────────────────────────────────────────────────────

class ReportCoverSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        private string $categoryTitle,
        private string $lgName,
        private string $filterSummary,
        private array  $tables,
        private array  $frequencies,
    ) {}

    public function title(): string { return 'सारांश'; }

    public function array(): array
    {
        $rows = [];

        // Header block
        $rows[] = [$this->lgName, '', ''];
        $rows[] = [$this->categoryTitle, '', ''];
        $rows[] = ['', '', ''];
        $rows[] = ['फिल्टर:', $this->filterSummary ?: 'कुनै फिल्टर छैन', ''];
        $rows[] = ['उत्पन्न मिति:', now()->format('Y-m-d H:i'), ''];
        $rows[] = ['', '', ''];

        // Table of contents header
        $rows[] = ['तालिकाको सूची', '', ''];
        $rows[] = ['क्र.सं.', 'शीर्षक', 'प्रकार'];

        $idx = 1;
        foreach ($this->tables as $table) {
            $rows[] = [$idx++, $table['title'], 'क्रस-तालिका'];
        }
        foreach ($this->frequencies as $freq) {
            $rows[] = [$idx++, $freq['title'], 'वितरण'];
        }

        $rows[] = ['', '', ''];
        $rows[] = ['', 'सबै तथ्याङ्क प्रकाशित तथा प्रमाणित अभिलेखमा आधारित छन्।', ''];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        // LG name — large
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF1D273B']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
        ]);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FFFFFFFF']],
        ]);
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(36);

        // Category title
        $sheet->mergeCells('A2:C2');
        $sheet->getStyle('A2:C2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FF206BC4']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE8F0FC']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(28);

        // Filter / date rows
        $sheet->getStyle('A4:A5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF626976']],
        ]);

        // TOC header
        $sheet->getStyle('A7:C7')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1D273B']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF0F4FA']],
        ]);
        $sheet->mergeCells('A7:C7');

        // TOC column headers
        $sheet->getStyle('A8:C8')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF206BC4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Width hints
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(45);
        $sheet->getColumnDimension('C')->setWidth(16);

        return [];
    }
}
