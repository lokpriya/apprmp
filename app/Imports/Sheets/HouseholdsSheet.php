<?php

namespace App\Imports\Sheets;

use App\Models\Household;
use App\Models\Settlement;
use App\Models\Ward;
use App\Services\DuplicateDetectionService;
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

    // Pre-loaded lookup tables — populated at start of collection()
    private array $wardMap       = [];   // ward_number => Ward
    private array $wardById      = [];   // ward_id     => Ward
    private array $settlementMap = [];   // "ward_id:name_lower" => Settlement
    private array $existingCodes = [];   // unique_code => true

    public function __construct(
        private readonly int  $enumeratorId,
        private readonly ?int $forceWardId = null,
    ) {}

    public function collection(Collection $rows): void
    {
        $codeService = app(UniqueCodeService::class);

        // Pre-load all wards once
        $wards = Ward::all();
        foreach ($wards as $ward) {
            $this->wardMap[$ward->ward_number] = $ward;
            $this->wardById[$ward->id]         = $ward;
        }

        // If force ward, pre-load settlements for that ward only; otherwise all
        $settlQuery = Settlement::query();
        if ($this->forceWardId) {
            $settlQuery->where('ward_id', $this->forceWardId);
        }
        foreach ($settlQuery->get() as $s) {
            $key = $s->ward_id . ':' . mb_strtolower(trim($s->name_en));
            $this->settlementMap[$key] = $s;
            $key2 = $s->ward_id . ':' . mb_strtolower(trim($s->name_np));
            $this->settlementMap[$key2] = $s;
        }

        // Pre-load all existing household codes so we can check existence in-memory
        $this->existingCodes = Household::pluck('unique_code')->flip()->toArray();

        DB::transaction(function () use ($rows, $codeService) {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;
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
        // Resolve ward — in-memory lookup
        if ($this->forceWardId) {
            $ward   = $this->wardById[$this->forceWardId] ?? null;
            $wardId = $this->forceWardId;
        } else {
            $wardNum = $row['ward_number'] ?? null;
            if (!$wardNum) {
                $this->errors[] = "Row {$rowNum}: ward_number is required.";
                return;
            }
            $ward = $this->wardMap[$wardNum] ?? null;
            if (!$ward) {
                $this->errors[] = "Row {$rowNum}: Ward #{$wardNum} not found.";
                return;
            }
            $wardId = $ward->id;
        }

        // Resolve settlement — in-memory lookup
        $settlementId   = null;
        $settlementName = mb_strtolower(trim($row['settlement_name'] ?? ''));
        if ($settlementName) {
            $key = $wardId . ':' . $settlementName;
            $settlementId = ($this->settlementMap[$key] ?? null)?->id;
        }

        // Check code existence in-memory
        $code = trim($row['household_code'] ?? '');
        if (!$code) {
            $code = $codeService->generateHouseholdCode($ward);
        } elseif (isset($this->existingCodes[$code])) {
            $this->errors[] = "Row {$rowNum}: Household code '{$code}' already exists, skipped.";
            return;
        }

        // Duplicate detection (still runs per-row, but only for new codes)
        $dupCheck = [
            'ward_id'            => $wardId,
            'respondent_name'    => trim($row['respondent_name'] ?? ''),
            'respondent_contact' => trim($row['respondent_contact'] ?? ''),
            'gps_lat'            => $row['gps_lat'] ?? null,
            'gps_lng'            => $row['gps_lng'] ?? null,
        ];
        $dup = app(DuplicateDetectionService::class)->checkHousehold($dupCheck);
        if ($dup['found']) {
            $codes   = implode(', ', array_column($dup['matches'], 'unique_code'));
            $reasons = implode('; ', $dup['matches'][0]['reasons'] ?? []);
            $this->errors[] = "Row {$rowNum}: Probable duplicate of [{$codes}] ({$reasons}) — skipped.";
            return;
        }

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

        // Track new code so subsequent rows in same batch don't get false "exists" pass
        $this->existingCodes[$code] = true;
        $this->codeToId[$code] = $household->id;
        $this->imported++;
    }

    private function parseDate(mixed $val): ?string
    {
        if (!$val) return null;
        if (is_numeric($val)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val)
                    ->format('Y-m-d');
            } catch (\Throwable) {}
        }
        $d = \DateTime::createFromFormat('Y-m-d', trim((string)$val));
        return $d ? $d->format('Y-m-d') : null;
    }

    public function getCodeToIdMap(): array { return $this->codeToId; }
    public function getErrors(): array       { return $this->errors; }
    public function getImportedCount(): int  { return $this->imported; }
}
