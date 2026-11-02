<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * श्रमदान for one fiscal year: a tole summary sheet and an entries sheet.
 *
 * No citizenship numbers — they appear only on the printed passbook. Text the
 * coordinators typed (project names, supervisors) goes through the global
 * SafeStringValueBinder, so a value such as "=HYPERLINK(...)" stays plain text.
 */
class ShramdaanExport implements WithMultipleSheets
{
    public function __construct(
        private array $summary,
        private Collection $entries,
        private string $fiscalYear,
    ) {}

    public function sheets(): array
    {
        $fy = $this->fiscalYear;

        $summary = array_map(fn($r) => [
            $r['ward_number'], $r['tole'], $r['households'], $r['passbooks'], $r['active'],
            $r['verified'], $r['pending'], $r['gold'], $r['silver'], $r['bronze'],
        ], $this->summary);

        $entries = $this->entries->map(fn($e) => [
            (int) $e->ward_number, $e->tole, $e->unique_code, $e->respondent_name, $e->member_name,
            $e->work_date_bs, $e->project_name, (float) $e->hours, (float) $e->days,
            $e->work_nature, $e->supervisor,
            $e->status === 'verified' ? 'प्रमाणित' : 'प्रमाणित हुन बाँकी',
            $e->verified_by_name,
        ])->all();

        return [
            $this->sheet("टोल सारांश $fy", [
                'वडा', 'टोल', 'प्रकाशित घरधुरी', 'पासबुक', 'प्रमाणित श्रमदान भएका परिवार',
                'प्रमाणित दिन', 'प्रमाणित हुन बाँकी दिन', 'स्वर्ण', 'रजत', 'कांस्य',
            ], $summary),
            $this->sheet("श्रमदान विवरण $fy", [
                'वडा', 'टोल', 'घर नं.', 'घरमुली', 'सदस्य', 'मिति (बि.सं.)', 'योजना',
                'घण्टा', 'दिन', 'कामको प्रकृति', 'सुपरिवेक्षक', 'स्थिति', 'प्रमाणित गर्ने',
            ], $entries),
        ];
    }

    private function sheet(string $title, array $headings, array $rows): object
    {
        return new class($title, $headings, $rows) implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize {
            public function __construct(private string $t, private array $h, private array $r) {}
            public function array(): array    { return $this->r; }
            public function headings(): array { return $this->h; }
            // Excel sheet names: 31 characters, no "/" (the fiscal year has one).
            public function title(): string   { return mb_substr(str_replace('/', '-', $this->t), 0, 31); }
            public function styles(Worksheet $sheet): array
            {
                $sheet->freezePane('A2');
                return [1 => ['font' => ['bold' => true]]];
            }
        };
    }
}
