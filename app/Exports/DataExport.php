<?php

namespace App\Exports;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Institution;
use App\Models\MemberBusiness;
use App\Models\Ward;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// ─────────────────────────────────────────────────────────────────────────────
// Master export — assembles sheets based on which types were selected
// ─────────────────────────────────────────────────────────────────────────────

class DataExport implements WithMultipleSheets
{
    public function __construct(
        private array  $wardIds,
        private array  $statuses,
        private bool   $includeHouseholds,
        private bool   $includeMembers,
        private bool   $includeInstitutions,
        private bool   $includeBusinesses,
        private string $lgName,
        private array  $wardSummary,
    ) {}

    public function sheets(): array
    {
        $sheets = [new DataSummarySheet($this->lgName, $this->wardSummary, $this->statuses)];

        if ($this->includeHouseholds) {
            $sheets[] = new HouseholdsDataSheet($this->wardIds, $this->statuses);
        }
        if ($this->includeMembers) {
            $sheets[] = new MembersDataSheet($this->wardIds, $this->statuses);
        }
        if ($this->includeInstitutions) {
            $sheets[] = new InstitutionsDataSheet($this->wardIds, $this->statuses);
        }
        if ($this->includeBusinesses) {
            $sheets[] = new BusinessesDataSheet($this->wardIds, $this->statuses);
        }

        return $sheets;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper — shared header row styling
// ─────────────────────────────────────────────────────────────────────────────

trait DataSheetStyles
{
    protected function applyHeaderStyle(Worksheet $sheet, int $colCount): void
    {
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        // Title row (row 1)
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Metadata row (row 2)
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF4A6D9A']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE8F0FC']],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // Column header row (row 3)
        $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF206BC4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(22);
        $sheet->freezePane('A4');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Summary sheet
// ─────────────────────────────────────────────────────────────────────────────

class DataSummarySheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        private string $lgName,
        private array  $wardSummary,
        private array  $statuses,
    ) {}

    public function title(): string { return 'सारांश'; }

    public function array(): array
    {
        $statusLabel = match (true) {
            $this->statuses === ['published']                          => 'प्रकाशित मात्र',
            in_array('submitted', $this->statuses)
                && !in_array('draft', $this->statuses)                => 'मस्यौदा बाहेक सबै',
            in_array('ward_approved', $this->statuses)
                && !in_array('submitted', $this->statuses)            => 'वडा स्वीकृत र माथि',
            default                                                    => 'सबै स्थिति',
        };

        $rows = [];
        $rows[] = [$this->lgName . ' — तथ्याङ्क निर्यात', '', '', '', '', '', ''];
        $rows[] = ['उत्पन्न मिति: ' . now()->format('Y-m-d H:i') . '  |  स्थिति: ' . $statusLabel, '', '', '', '', '', ''];
        $rows[] = ['वडा', 'जम्मा घरधुरी', 'मस्यौदा', 'पेश गरिएको', 'वडा स्वीकृत', 'प्रकाशित', 'अस्वीकृत'];

        $totals = ['वडा', 0, 0, 0, 0, 0, 0];
        foreach ($this->wardSummary as $w) {
            $rows[] = [
                'वडा ' . $w['ward_number'],
                $w['hh_total'],
                $w['hh_draft'],
                $w['hh_submitted'],
                $w['hh_ward_approved'],
                $w['hh_published'],
                $w['hh_rejected'],
            ];
            $totals[1] += $w['hh_total'];
            $totals[2] += $w['hh_draft'];
            $totals[3] += $w['hh_submitted'];
            $totals[4] += $w['hh_ward_approved'];
            $totals[5] += $w['hh_published'];
            $totals[6] += $w['hh_rejected'];
        }
        $totals[0] = 'जम्मा';
        $rows[] = $totals;

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $totalRows = count($this->wardSummary) + 4;

        // Title
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);

        // Metadata
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2:G2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF4A6D9A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE8F0FC']],
        ]);

        // Column headers
        $sheet->getStyle('A3:G3')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF206BC4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(22);

        // Alternating data rows
        for ($r = 4; $r < $totalRows; $r++) {
            $bg = ($r % 2 === 0) ? 'FFF0F4FA' : 'FFFFFFFF';
            $sheet->getStyle("A{$r}:G{$r}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
            $sheet->getStyle("B{$r}:G{$r}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // Totals row
        $sheet->getStyle("A{$totalRows}:G{$totalRows}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD0E0F8']],
        ]);
        $sheet->getStyle("B{$totalRows}:G{$totalRows}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->getStyle("A3:G{$totalRows}")->applyFromArray([
            'borders' => [
                'outline'    => ['borderStyle' => Border::BORDER_THIN,  'color' => ['argb' => 'FFCDD5E1']],
                'allBorders' => ['borderStyle' => Border::BORDER_HAIR,  'color' => ['argb' => 'FFE6E7E9']],
            ],
        ]);

        $sheet->freezePane('A4');
        return [];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Households sheet
// ─────────────────────────────────────────────────────────────────────────────

class HouseholdsDataSheet implements FromQuery, WithMapping, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithCustomChunkSize
{
    use DataSheetStyles;

    public function __construct(
        private array $wardIds,
        private array $statuses,
    ) {}

    public function chunkSize(): int { return 500; }
    public function title(): string { return 'घरधुरी'; }

    public function query()
    {
        return Household::with(['ward:id,ward_number', 'settlement:id,name_np', 'enumerator:id,name'])
            ->whereIn('ward_id', $this->wardIds)
            ->whereIn('status', $this->statuses)
            ->orderBy('ward_id')
            ->orderBy('unique_code');
    }

    public function headings(): array
    {
        return [
            ['घरधुरी विवरण', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['उत्पन्न मिति: ' . now()->format('Y-m-d H:i'), '', '', '', '', '', '', '', '', '', '', '', ''],
            [
                'क्र.सं.', 'कोड', 'वडा', 'टोल/बस्ती', 'घरमूलीको नाम', 'सम्पर्क',
                'कुल सदस्य', 'GPS (Lat)', 'GPS (Lng)', 'स्थिति',
                'गणक', 'पेश मिति', 'प्रकाशित मिति',
            ],
        ];
    }

    private static int $hhRow = 0;

    public function map($hh): array
    {
        self::$hhRow++;
        return [
            self::$hhRow,
            $hh->unique_code,
            'वडा ' . ($hh->ward->ward_number ?? '—'),
            $hh->settlement->name_np ?? '—',
            $hh->respondent_name    ?? '—',
            $hh->respondent_contact ?? '—',
            $hh->total_members      ?? 0,
            $hh->gps_lat,
            $hh->gps_lng,
            $this->statusLabel($hh->status),
            $hh->enumerator->name ?? '—',
            $hh->submitted_at?->format('Y-m-d')  ?? '—',
            $hh->published_at?->format('Y-m-d')  ?? '—',
        ];
    }

    public function columnWidths(): array
    {
        return ['A'=>6,'B'=>18,'C'=>10,'D'=>18,'E'=>22,'F'=>15,'G'=>8,'H'=>12,'I'=>12,'J'=>14,'K'=>20,'L'=>14,'M'=>14];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->applyHeaderStyle($sheet, 13);
        return [];
    }

    private function statusLabel(string $s): string
    {
        return match($s) {
            'draft'         => 'मस्यौदा',
            'submitted'     => 'पेश गरिएको',
            'ward_approved' => 'वडा स्वीकृत',
            'escalated'     => 'एस्केलेट',
            'published'     => 'प्रकाशित',
            'rejected'      => 'अस्वीकृत',
            default         => $s,
        };
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Members sheet
// ─────────────────────────────────────────────────────────────────────────────

class MembersDataSheet implements FromQuery, WithMapping, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithCustomChunkSize
{
    use DataSheetStyles;

    public function __construct(
        private array $wardIds,
        private array $statuses,
    ) {}

    public function chunkSize(): int { return 500; }
    public function title(): string { return 'सदस्यहरू'; }

    public function query()
    {
        return HouseholdMember::with(['household:id,unique_code,ward_id', 'household.ward:id,ward_number'])
            ->whereHas('household', fn($q) =>
                $q->whereIn('ward_id', $this->wardIds)->whereIn('status', $this->statuses)
            )
            ->orderBy('household_id')
            ->orderBy('sort_order');
    }

    public function headings(): array
    {
        return [
            ['घरधुरी सदस्य विवरण', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['उत्पन्न मिति: ' . now()->format('Y-m-d H:i'), '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            [
                'क्र.सं.', 'घरधुरी कोड', 'वडा', 'पूरा नाम', 'बुवाको नाम',
                'घरमूलीसँग सम्बन्ध', 'लिङ्ग', 'उमेर', 'जन्म मिति',
                'जाति/समुदाय', 'धर्म', 'मातृभाषा',
                'शैक्षिक स्तर', 'पेशा', 'अपाङ्गता',
            ],
        ];
    }

    private static int $mRow = 0;

    public function map($m): array
    {
        self::$mRow++;
        return [
            self::$mRow,
            $m->household->unique_code      ?? '—',
            'वडा ' . ($m->household->ward->ward_number ?? '—'),
            $m->full_name                   ?? '—',
            $m->father_name                 ?? '—',
            $m->relation_to_head            ?? '—',
            $m->gender                      ?? '—',
            $m->age,
            $m->date_of_birth?->format('Y-m-d') ?? '—',
            $m->ethnicity                   ?? '—',
            $m->religion                    ?? '—',
            $m->mother_tongue               ?? '—',
            $m->education_level             ?? '—',
            $m->occupation_type             ?? '—',
            $m->disability_status           ?? 'छैन',
        ];
    }

    public function columnWidths(): array
    {
        return ['A'=>6,'B'=>18,'C'=>10,'D'=>20,'E'=>16,'F'=>12,'G'=>10,'H'=>14,'I'=>14,'J'=>14,'K'=>18,'L'=>16,'M'=>16,'N'=>18,'O'=>14];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->applyHeaderStyle($sheet, 15);
        return [];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Institutions sheet
// ─────────────────────────────────────────────────────────────────────────────

class InstitutionsDataSheet implements FromQuery, WithMapping, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    use DataSheetStyles;

    public function __construct(
        private array $wardIds,
        private array $statuses,
    ) {}

    public function title(): string { return 'संस्थाहरू'; }

    public function query()
    {
        return Institution::with([
            'ward:id,ward_number',
            'settlement:id,name_np',
            'institutionType:id,name_np',
            'enumerator:id,name',
        ])
            ->whereIn('ward_id', $this->wardIds)
            ->whereIn('status', $this->statuses)
            ->orderBy('ward_id')
            ->orderBy('unique_code');
    }

    public function headings(): array
    {
        return [
            ['संस्थागत विवरण', '', '', '', '', '', '', '', '', '', '', ''],
            ['उत्पन्न मिति: ' . now()->format('Y-m-d H:i'), '', '', '', '', '', '', '', '', '', '', ''],
            [
                'क्र.सं.', 'कोड', 'वडा', 'टोल/बस्ती', 'संस्थाको नाम (नेपाली)', 'संस्थाको नाम (अंग्रेजी)',
                'प्रकार', 'स्वामित्व', 'दर्ता नं.', 'स्थिति', 'गणक', 'पेश मिति',
            ],
        ];
    }

    private static int $iRow = 0;

    public function map($i): array
    {
        self::$iRow++;
        return [
            self::$iRow,
            $i->unique_code,
            'वडा ' . ($i->ward->ward_number ?? '—'),
            $i->settlement->name_np              ?? '—',
            $i->name_np                          ?? '—',
            $i->name_en                          ?? '—',
            $i->institutionType->name_np         ?? '—',
            $i->ownership_type                   ?? '—',
            $i->registration_number              ?? '—',
            $this->statusLabel($i->status),
            $i->enumerator->name                 ?? '—',
            $i->submitted_at?->format('Y-m-d')   ?? '—',
        ];
    }

    public function columnWidths(): array
    {
        return ['A'=>6,'B'=>18,'C'=>10,'D'=>18,'E'=>28,'F'=>24,'G'=>16,'H'=>16,'I'=>16,'J'=>14,'K'=>18,'L'=>14];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->applyHeaderStyle($sheet, 12);
        return [];
    }

    private function statusLabel(string $s): string
    {
        return match($s) {
            'draft'         => 'मस्यौदा',
            'submitted'     => 'पेश गरिएको',
            'ward_approved' => 'वडा स्वीकृत',
            'escalated'     => 'एस्केलेट',
            'published'     => 'प्रकाशित',
            'rejected'      => 'अस्वीकृत',
            default         => $s,
        };
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Businesses sheet
// ─────────────────────────────────────────────────────────────────────────────

class BusinessesDataSheet implements FromQuery, WithMapping, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    use DataSheetStyles;

    public function __construct(
        private array $wardIds,
        private array $statuses,
    ) {}

    public function title(): string { return 'व्यवसायहरू'; }

    public function query()
    {
        return MemberBusiness::with([
            'ward:id,ward_number',
            'settlement:id,name_np',
            'member:id,full_name',
            'household:id,unique_code',
            'enumerator:id,name',
        ])
            ->whereIn('ward_id', $this->wardIds)
            ->whereIn('status', $this->statuses)
            ->orderBy('ward_id')
            ->orderBy('unique_code');
    }

    public function headings(): array
    {
        return [
            ['व्यवसाय विवरण', '', '', '', '', '', '', '', '', '', '', ''],
            ['उत्पन्न मिति: ' . now()->format('Y-m-d H:i'), '', '', '', '', '', '', '', '', '', '', ''],
            [
                'क्र.सं.', 'कोड', 'वडा', 'टोल/बस्ती', 'व्यवसायको नाम', 'प्रकार',
                'क्षेत्र', 'मालिकको नाम', 'घरधुरी कोड',
                'दर्ता भएको', 'PAN/VAT', 'स्थिति',
            ],
        ];
    }

    private static int $bRow = 0;

    public function map($b): array
    {
        self::$bRow++;
        return [
            self::$bRow,
            $b->unique_code,
            'वडा ' . ($b->ward->ward_number  ?? '—'),
            $b->settlement->name_np           ?? '—',
            $b->business_name                 ?? '—',
            $b->business_type                 ?? '—',
            $b->business_field                ?? '—',
            $b->member->full_name             ?? '—',
            $b->household->unique_code        ?? '—',
            $b->is_registered ? 'छ' : 'छैन',
            $b->has_pan_vat   ? 'छ' : 'छैन',
            $this->statusLabel($b->status),
        ];
    }

    public function columnWidths(): array
    {
        return ['A'=>6,'B'=>18,'C'=>10,'D'=>18,'E'=>24,'F'=>16,'G'=>18,'H'=>20,'I'=>18,'J'=>10,'K'=>10,'L'=>14];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->applyHeaderStyle($sheet, 12);
        return [];
    }

    private function statusLabel(string $s): string
    {
        return match($s) {
            'draft'         => 'मस्यौदा',
            'submitted'     => 'पेश गरिएको',
            'ward_approved' => 'वडा स्वीकृत',
            'escalated'     => 'एस्केलेट',
            'published'     => 'प्रकाशित',
            'rejected'      => 'अस्वीकृत',
            default         => $s,
        };
    }
}
