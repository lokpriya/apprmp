<?php

namespace App\Imports\Sheets;

use App\Models\Institution;
use App\Models\InstitutionType;
use App\Models\Settlement;
use App\Models\Ward;
use App\Services\UniqueCodeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class InstitutionsSheet implements ToCollection, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public function startRow(): int { return 3; }

    /** institution_code => Institution id */
    private array $codeToId = [];
    private array $errors   = [];
    private int   $imported = 0;

    public function __construct(
        private readonly int  $enumeratorId,
        private readonly ?int $forceWardId = null,
    ) {}

    public function collection(Collection $rows): void
    {
        $codeService = app(UniqueCodeService::class);

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

        // Resolve institution type (required)
        $typeSlug = strtolower(trim($row['institution_type'] ?? ''));
        if (!$typeSlug) {
            $this->errors[] = "Row {$rowNum}: institution_type is required.";
            return;
        }
        $type = InstitutionType::where('slug', $typeSlug)
            ->orWhere('name_en', 'like', $typeSlug)
            ->first();
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

        // Resolve settlement (optional)
        $settlementId   = null;
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
        $code = trim($row['institution_code'] ?? '');
        if (!$code) {
            $code = $codeService->generateInstitutionCode($ward);
        } elseif (Institution::where('unique_code', $code)->exists()) {
            $this->errors[] = "Row {$rowNum}: Code '{$code}' already exists, skipped.";
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
