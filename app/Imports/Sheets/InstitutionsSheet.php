<?php

namespace App\Imports\Sheets;

use App\Models\Institution;
use App\Models\InstitutionType;
use App\Models\Settlement;
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
    private array $typeMap       = [];   // slug_lower => InstitutionType
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
            // Pre-load wards
            foreach (Ward::all() as $ward) {
                $this->wardMap[$ward->ward_number] = $ward;
                $this->wardById[$ward->id]         = $ward;
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

            // Pre-load institution types
            foreach (InstitutionType::all() as $t) {
                $this->typeMap[strtolower($t->slug)]    = $t;
                $this->typeMap[strtolower($t->name_en)] = $t;
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
        $type = $this->typeMap[$typeSlug] ?? null;
        if (!$type) {
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

        $institution = Institution::create([
            'unique_code'          => $code,
            'ward_id'              => $wardId,
            'settlement_id'        => $settlementId,
            'institution_type_id'  => $type->id,
            'name_np'              => $nameNp ?: $nameEn,
            'name_en'              => $nameEn ?: $nameNp,
            'ownership_type'       => trim($row['ownership_type'] ?? '') ?: null,
            'registration_number'  => trim($row['registration_number'] ?? '') ?: null,
            'established_date'     => $this->parseDate($row['established_date'] ?? null),
            'enumerator_id'        => $this->enumeratorId,
            'respondent_name'      => trim($row['respondent_name'] ?? '') ?: null,
            'respondent_contact'   => trim($row['respondent_contact'] ?? '') ?: null,
            'gps_lat'              => is_numeric($row['gps_lat'] ?? '') ? $row['gps_lat'] : null,
            'gps_lng'              => is_numeric($row['gps_lng'] ?? '') ? $row['gps_lng'] : null,
            'photo_number'         => trim($row['photo_number'] ?? '') ?: null,
            'survey_date'          => $this->parseDate($row['survey_date'] ?? null),
            'remarks'              => trim($row['remarks'] ?? '') ?: null,
            'status'               => 'draft',
        ]);

        $this->existingCodes[$code] = true;
        $this->codeToId[$code] = $institution->id;
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
    public function getErrors(): array      { return $this->errors; }
    public function getImportedCount(): int { return $this->imported; }
}
