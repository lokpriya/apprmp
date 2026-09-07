<?php

namespace App\Imports\Sheets;

use App\Models\Institution;
use App\Models\Settlement;
use App\Models\SurveyOption;
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

class InstitutionsSheet implements ToCollection, WithChunkReading, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public function startRow(): int { return 3; }
    public function chunkSize(): int { return 200; }

    /** institution_code => Institution id */
    private array $codeToId = [];
    private array $errors   = [];
    private int   $imported = 0;

    // Pre-loaded lookup tables
    private array $wardMap       = [];   // ward_number => Ward
    private array $wardById      = [];   // ward_id     => Ward
    private array $settlementMap = [];   // "ward_id:name_lower" => Settlement
    private array $typeMap       = [];   // slug_lower => canonical slug
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
            // Pre-load wards. The by-number map is scoped to the importing user's
            // local government — see the matching comment in HouseholdsSheet:
            // ward_number is unique only within an LG, so an unscoped map files
            // records under the wrong municipality. Exact-id lookups stay global so
            // the forced-ward flow is unaffected.
            $importerLgId = User::whereKey($this->enumeratorId)->value('local_government_id');

            foreach (Ward::all() as $ward) {
                $this->wardById[$ward->id] = $ward;

                if ($importerLgId === null || $ward->local_government_id === $importerLgId) {
                    $this->wardMap[$ward->ward_number] = $ward;
                }
            }

            // Pre-load settlements
            $settlQuery = Settlement::query();
            if ($this->forceWardId) {
                $settlQuery->where('ward_id', $this->forceWardId);
            }
            foreach ($settlQuery->get() as $s) {
                $this->settlementMap[$s->ward_id . ':' . mb_strtolower(trim($s->name_en))] = $s;
                $this->settlementMap[$s->ward_id . ':' . mb_strtolower(trim($s->name_np))] = $s;
            }

            // Pre-load institution types from survey_options
            foreach (SurveyOption::where('category', 'institution_type')->get() as $t) {
                $this->typeMap[strtolower($t->value)]    = $t->value;
                $this->typeMap[strtolower($t->label_en)] = $t->value;
            }

            // Pre-load existing codes
            $this->existingCodes = Institution::pluck('unique_code')->flip()->toArray();

            // Preload duplicate-detection maps once for the whole batch
            $this->dupService = app(DuplicateDetectionService::class);
            $this->dupService->preloadInstitutions(array_keys($this->wardById));

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
        // Resolve ward — in-memory
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

        // Resolve institution type — in-memory
        $typeSlug = strtolower(trim($row['institution_type'] ?? ''));
        if (!$typeSlug) {
            $this->errors[] = "Row {$rowNum}: institution_type is required.";
            return;
        }
        $resolvedType = $this->typeMap[$typeSlug] ?? null;
        if (!$resolvedType) {
            $this->errors[] = "Row {$rowNum}: Institution type '{$typeSlug}' not found. See Valid Values sheet.";
            return;
        }

        // Name required
        $nameNp = trim($row['name_np'] ?? '');
        $nameEn = trim($row['name_en'] ?? '');
        if (!$nameNp && !$nameEn) {
            $this->errors[] = "Row {$rowNum}: name_np or name_en is required.";
            return;
        }

        // Resolve settlement — in-memory
        $settlementId   = null;
        $settlementName = mb_strtolower(trim($row['settlement_name'] ?? ''));
        if ($settlementName) {
            $settlementId = ($this->settlementMap[$wardId . ':' . $settlementName] ?? null)?->id;
        }

        // Code existence check — in-memory
        $code = trim($row['institution_code'] ?? '');
        if (!$code) {
            $code = $codeService->generateInstitutionCode($ward);
        } elseif (isset($this->existingCodes[$code])) {
            $this->errors[] = "Row {$rowNum}: Code '{$code}' already exists, skipped.";
            return;
        }

        // Duplicate detection
        $dupCheck = [
            'ward_id'             => $wardId,
            'name_np'             => $nameNp,
            'name_en'             => $nameEn,
            'registration_number' => trim($row['registration_number'] ?? ''),
            'gps_lat'             => $row['gps_lat'] ?? null,
            'gps_lng'             => $row['gps_lng'] ?? null,
        ];
        $dup = $this->dupService->checkInstitution($dupCheck);
        if ($dup['found']) {
            $codes   = implode(', ', array_column($dup['matches'], 'unique_code'));
            $reasons = implode('; ', $dup['matches'][0]['reasons'] ?? []);
            $this->errors[] = "Row {$rowNum}: Probable duplicate of [{$codes}] ({$reasons}) — skipped.";
            return;
        }

        [$gpsLat, $gpsLng] = $this->parseGps($row, $rowNum);

        $institution = Institution::create([
            'unique_code'          => $code,
            'ward_id'              => $wardId,
            'settlement_id'        => $settlementId,
            'institution_type'     => $resolvedType,
            'name_np'              => $nameNp ?: $nameEn,
            'name_en'              => $nameEn ?: $nameNp,
            'ownership_type'       => trim($row['ownership_type'] ?? '') ?: null,
            'registration_number'  => trim($row['registration_number'] ?? '') ?: null,
            'established_date'     => $this->parseDate($row['established_date'] ?? null),
            'enumerator_id'        => $this->enumeratorId,
            'respondent_name'      => trim($row['respondent_name'] ?? '') ?: null,
            'respondent_contact'   => trim($row['respondent_contact'] ?? '') ?: null,
            'gps_lat'              => $gpsLat,
            'gps_lng'              => $gpsLng,
            'photo_number'         => trim($row['photo_number'] ?? '') ?: null,
            'survey_date'          => $this->parseDate($row['survey_date'] ?? null),
            'remarks'              => trim($row['remarks'] ?? '') ?: null,
            'status'               => 'draft',
        ]);

        $this->existingCodes[$code] = true;
        $this->codeToId[$code] = $institution->id;
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
    public function getErrors(): array      { return $this->errors; }
    public function getImportedCount(): int { return $this->imported; }
}
