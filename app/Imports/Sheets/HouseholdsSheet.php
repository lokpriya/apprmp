<?php

namespace App\Imports\Sheets;

use App\Models\Household;
use App\Models\Settlement;
use App\Models\Ward;
use App\Services\UniqueCodeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class HouseholdsSheet implements ToCollection, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public function startRow(): int { return 3; }

    /** household_code => Household id */
    private array $codeToId  = [];
    private array $errors     = [];
    private int   $imported   = 0;

    public function __construct(
        private readonly int  $enumeratorId,
        private readonly ?int $forceWardId = null,
    ) {}

    public function collection(Collection $rows): void
    {
        $codeService = app(UniqueCodeService::class);

        DB::transaction(function () use ($rows, $codeService) {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2; // account for header row
                try {
                    $this->processRow($row->toArray(), $rowNum, $codeService);
                } catch (\Throwable $e) {
                    $this->errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
            }
        });
    }

    private function processRow(array $row, int $rowNum, UniqueCodeService $codeService): void
    {
        // Resolve ward
        $wardId = $this->forceWardId;
        if (!$wardId) {
            $wardNum = $row['ward_number'] ?? null;
            if (!$wardNum) {
                $this->errors[] = "Row {$rowNum}: ward_number is required.";
                return;
            }
            $ward = Ward::where('ward_number', $wardNum)->first();
            if (!$ward) {
                $this->errors[] = "Row {$rowNum}: Ward #{$wardNum} not found.";
                return;
            }
            $wardId = $ward->id;
        } else {
            $ward = Ward::findOrFail($wardId);
        }

        // Resolve settlement (optional)
        $settlementId = null;
        $settlementName = trim($row['settlement_name'] ?? '');
        if ($settlementName) {
            $settlement = Settlement::where('ward_id', $wardId)
                ->where(function ($q) use ($settlementName) {
                    $q->where('name_en', 'like', $settlementName)
                      ->orWhere('name_np', 'like', $settlementName);
                })->first();
            $settlementId = $settlement?->id;
        }

        // Generate or use provided code
        $code = trim($row['household_code'] ?? '');
        if (!$code) {
            $code = $codeService->generateHouseholdCode($ward);
        } elseif (Household::where('unique_code', $code)->exists()) {
            $this->errors[] = "Row {$rowNum}: Household code '{$code}' already exists, skipped.";
            return;
        }

        // Build survey_data.social from Households sheet columns
        $socialSnap = array_filter([
            'family_head' => trim($row['family_head'] ?? '') ?: null,
            'family_type' => trim($row['family_type'] ?? '') ?: null,
            'head_gender' => trim($row['head_gender'] ?? '') ?: null,
        ]);

        $household = Household::create([
            'unique_code'        => $code,
            'ward_id'            => $wardId,
            'settlement_id'      => $settlementId,
            'enumerator_id'      => $this->enumeratorId,
            'respondent_name'    => trim($row['respondent_name'] ?? '') ?: null,
            'respondent_contact' => trim($row['respondent_contact'] ?? '') ?: null,
            'total_members'      => is_numeric($row['total_members'] ?? '') ? (int)$row['total_members'] : null,
            'gps_lat'            => is_numeric($row['gps_lat'] ?? '') ? $row['gps_lat'] : null,
            'gps_lng'            => is_numeric($row['gps_lng'] ?? '') ? $row['gps_lng'] : null,
            'gps_altitude'       => is_numeric($row['gps_altitude'] ?? '') ? $row['gps_altitude'] : null,
            'gps_accuracy'       => is_numeric($row['gps_accuracy'] ?? '') ? $row['gps_accuracy'] : null,
            'photo_number'       => trim($row['photo_number'] ?? '') ?: null,
            'survey_date'        => $this->parseDate($row['survey_date'] ?? null),
            'remarks'            => trim($row['remarks'] ?? '') ?: null,
            'status'             => 'draft',
            'survey_data'        => $socialSnap ? ['social' => $socialSnap] : null,
        ]);

        $this->codeToId[$code] = $household->id;
        $this->imported++;
    }

    private function parseDate(mixed $val): ?string
    {
        if (!$val) return null;
        // Excel may give float (OLE date serial) or string
        if (is_numeric($val)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val)
                    ->format('Y-m-d');
            } catch (\Throwable) {}
        }
        $d = \DateTime::createFromFormat('Y-m-d', trim((string)$val));
        return $d ? $d->format('Y-m-d') : null;
    }

    // ─── Accessors for dependent sheets ──────────────────────────────────────
    public function getCodeToIdMap(): array { return $this->codeToId; }
    public function getErrors(): array       { return $this->errors; }
    public function getImportedCount(): int  { return $this->imported; }
}
