<?php

namespace App\Imports\Sheets;

use App\Models\Household;
use App\Models\Settlement;
use App\Models\User;
use App\Models\Ward;
use App\Services\DuplicateDetectionService;
use App\Services\UniqueCodeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class HouseholdsSheet implements ToCollection, WithChunkReading, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public function startRow(): int { return 3; }
    public function chunkSize(): int { return 200; }

    /** household_code => Household id */
    private array $codeToId  = [];
    private array $errors     = [];
    private int   $imported   = 0;

    // Pre-loaded lookup tables — populated at start of collection()
    private array $wardMap       = [];   // ward_number => Ward
    private array $wardById      = [];   // ward_id     => Ward
    private array $settlementMap = [];   // "ward_id:name_lower" => Settlement
    private array $existingCodes = [];   // unique_code => true
    private bool  $preloaded    = false;
    private DuplicateDetectionService $dupService;

    public function __construct(
        private readonly int  $enumeratorId,
        private readonly ?int $forceWardId = null,
    ) {}

    public function collection(Collection $rows): void
    {
        $codeService = app(UniqueCodeService::class);

        if (!$this->preloaded) {
            // Pre-load all wards once. Exact-id lookups (the forced-ward flow) may
            // resolve any ward, but the by-number map must be scoped to the
            // importing user's local government: ward_number is only unique within
            // an LG, so an unscoped map lets one municipality's "ward 3" overwrite
            // another's and silently files households under the wrong municipality.
            // Only one LG currently has wards, so this is latent — but the LG table
            // holds all 753 municipalities and the map is keyed purely by number.
            $importerLgId = User::whereKey($this->enumeratorId)->value('local_government_id');

            $wards = Ward::all();
            foreach ($wards as $ward) {
                $this->wardById[$ward->id] = $ward;

                if ($importerLgId === null || $ward->local_government_id === $importerLgId) {
                    $this->wardMap[$ward->ward_number] = $ward;
                }
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

            // Preload duplicate-detection maps once for the whole batch
            $this->dupService = app(DuplicateDetectionService::class);
            $this->dupService->preloadHouseholds(array_keys($this->wardById));

            $this->preloaded = true;
        }

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

        // Duplicate detection — uses preloaded in-memory maps (O(1) per row)
        $dupCheck = [
            'ward_id'            => $wardId,
            'respondent_name'    => trim($row['respondent_name'] ?? ''),
            'respondent_contact' => trim($row['respondent_contact'] ?? ''),
            'gps_lat'            => $row['gps_lat'] ?? null,
            'gps_lng'            => $row['gps_lng'] ?? null,
            'survey_data'        => ['social' => ['family_head' => trim($row['family_head'] ?? '') ?: null]],
        ];
        $dup = $this->dupService->checkHousehold($dupCheck);
        if ($dup['found']) {
            $codes   = implode(', ', array_column($dup['matches'], 'unique_code'));
            $reasons = implode('; ', $dup['matches'][0]['reasons'] ?? []);
            $this->errors[] = "Row {$rowNum}: Probable duplicate of [{$codes}] ({$reasons}) — skipped.";
            return;
        }

        $socialSnap = array_filter([
            'family_head'  => trim($row['family_head']  ?? '') ?: null,
            'family_type'  => trim($row['family_type']  ?? '') ?: null,
            'head_gender'  => trim($row['head_gender']  ?? '') ?: null,
            'total_male'   => is_numeric($row['total_male']   ?? '') ? (int)$row['total_male']   : null,
            'total_female' => is_numeric($row['total_female'] ?? '') ? (int)$row['total_female'] : null,
            'total_other'  => is_numeric($row['total_other']  ?? '') ? (int)$row['total_other']  : null,
        ]);

        [$gpsLat, $gpsLng] = $this->parseGps($row, $rowNum);

        $household = Household::create([
            'unique_code'        => $code,
            'ward_id'            => $wardId,
            'settlement_id'      => $settlementId,
            'enumerator_id'      => $this->enumeratorId,
            'respondent_name'    => trim($row['respondent_name'] ?? '') ?: null,
            'respondent_contact' => trim($row['respondent_contact'] ?? '') ?: null,
            'total_members'      => is_numeric($row['total_members'] ?? '') ? (int)$row['total_members'] : null,
            'gps_lat'            => $gpsLat,
            'gps_lng'            => $gpsLng,
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

    private function parseGps(array $row, int $rowNum): array
    {
        $lat = $row['gps_lat'] ?? null;
        $lng = $row['gps_lng'] ?? null;

        if ($lat === null && $lng === null) {
            return [null, null];
        }

        if (!is_numeric($lat) || !is_numeric($lng)) {
            $this->errors[] = "Row {$rowNum}: Invalid GPS (non-numeric) — coordinates cleared.";
            return [null, null];
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < 26.0 || $lat > 30.5 || $lng < 80.0 || $lng > 88.5) {
            $this->errors[] = "Row {$rowNum}: GPS out of Nepal bounds (lat={$lat}, lng={$lng}) — coordinates cleared.";
            return [null, null];
        }

        return [$lat, $lng];
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
