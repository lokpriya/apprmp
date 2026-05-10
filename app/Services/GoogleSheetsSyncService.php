<?php

namespace App\Services;

use App\Imports\GoogleSyncAllSheetsCollector;
use App\Imports\InstitutionSyncAllSheetsCollector;
use App\Imports\NamedSheetRawRowCollector;
use App\Imports\RawRowCollector;
use App\Models\Household;
use App\Models\HouseholdLivestock;
use App\Models\HouseholdMember;
use App\Models\Institution;
use App\Models\InstitutionType;
use App\Models\Settlement;
use App\Models\Ward;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class GoogleSheetsSyncService
{
    // ── Download ──────────────────────────────────────────────────────────────

    public function downloadSheet(string $sheetId): string
    {
        $url      = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=xlsx";
        $response = Http::timeout(30)->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException(
                "Sheet डाउनलोड गर्न सकिएन (HTTP {$response->status()}). Sheet \"Anyone with link can view\" मा सेट गर्नुभएको छ?"
            );
        }

        $tmp = tempnam(sys_get_temp_dir(), 'gs_sync_') . '.xlsx';
        file_put_contents($tmp, $response->body());
        return $tmp;
    }

    // ── Household sync ────────────────────────────────────────────────────────

    public function previewHouseholds(string $sheetId, int $wardId, int $lgId): array
    {
        $tmp = $this->downloadSheet($sheetId);
        try {
            return $this->diffHouseholds($tmp, $wardId, $lgId);
        } finally {
            @unlink($tmp);
        }
    }

    public function applyHouseholds(string $sheetId, int $wardId, int $lgId, bool $forceLockedRecords = false): array
    {
        $tmp = $this->downloadSheet($sheetId);
        try {
            $diff = $this->diffHouseholds($tmp, $wardId, $lgId);
            return $this->writeHouseholds($diff, $wardId, $lgId, $forceLockedRecords);
        } finally {
            @unlink($tmp);
        }
    }

    private function diffHouseholds(string $filePath, int $wardId, int $lgId): array
    {
        $sheets  = $this->loadAllSheets($filePath);
        $ward    = Ward::find($wardId);
        $wardNum = $ward?->ward_number;

        // Index supporting sheets by lowercase household_code
        $housingIdx      = $this->indexBy($sheets['housing'],     'household_code');
        $utilitiesIdx    = $this->indexBy($sheets['utilities'],   'household_code');
        $economicIdx     = $this->indexBy($sheets['economic'],    'household_code');
        $agricultureIdx  = $this->indexBy($sheets['agriculture'], 'household_code');
        $membersIdx      = $this->groupBy($sheets['members'],     'household_code');
        $livestockIdx    = $this->groupBy($sheets['livestock'],   'household_code');
        $landParcelsIdx  = $this->groupBy($sheets['landParcels'], 'household_code');

        $existingMap = Household::where('ward_id', $wardId)
            ->select(['id', 'unique_code', 'sync_hash', 'sync_locked'])
            ->get()
            ->keyBy('unique_code');

        $new = $changed = $locked = $upToDate = $errors = [];
        $rowNum          = 1;
        $wardNumbersSeen = [];

        foreach ($sheets['households'] as $row) {
            $rowNum++;
            $rowWard = (string)($row['ward_number'] ?? '');
            if ($rowWard !== '') $wardNumbersSeen[$rowWard] = true;
            if ($rowWard !== (string)$wardNum) continue;

            $uniqueCode = trim($row['unique_code'] ?? $row['household_code'] ?? '');
            if (!$uniqueCode) {
                $errors[] = ['row' => $row, 'reason' => 'unique_code / household_code खाली छ'];
                continue;
            }
            $row['unique_code'] = $uniqueCode;
            $codeKey = strtolower($uniqueCode);

            $housingRow      = $housingIdx[$codeKey]     ?? null;
            $utilitiesRow    = $utilitiesIdx[$codeKey]   ?? null;
            $economicRow     = $economicIdx[$codeKey]    ?? null;
            $agricultureRow  = $agricultureIdx[$codeKey] ?? null;
            $memberRows      = $membersIdx[$codeKey]     ?? [];
            $livestockRows   = $livestockIdx[$codeKey]   ?? [];
            $landParcelRows  = $landParcelsIdx[$codeKey] ?? [];

            $hash = $this->hashAllSheets(
                $row, $housingRow, $utilitiesRow, $economicRow,
                $agricultureRow, $memberRows, $livestockRows, $landParcelRows
            );
            $existing = $existingMap->get($uniqueCode);

            // Attach sub-sheet data (stripped before sending to frontend)
            $row['_related'] = compact('housingRow', 'utilitiesRow', 'economicRow', 'agricultureRow', 'memberRows', 'livestockRows', 'landParcelRows');

            // Completeness snapshot — visible in preview
            $row['_completeness'] = [
                'members'     => count($memberRows),
                'housing'     => !empty($housingRow),
                'utilities'   => !empty($utilitiesRow),
                'economic'    => !empty($economicRow),
                'agriculture' => !empty($agricultureRow),
                'livestock'   => count($livestockRows),
                'landParcels' => count($landParcelRows),
                'settlement'  => !empty(trim($row['settlement_name'] ?? '')),
            ];

            if (!$existing) {
                $errs = $this->requiredHouseholdErrors($row, $rowNum);
                if (!empty($errs)) {
                    $errors[] = ['row' => $row, 'reason' => 'आवश्यक/अमान्य विवरण', 'validation_errors' => $errs];
                } else {
                    $new[] = array_merge($row, ['_hash' => $hash, '_action' => 'new']);
                }
            } elseif ($existing->sync_locked) {
                $locked[] = array_merge($row, ['_hash' => $hash, '_action' => 'locked', '_id' => $existing->id]);
            } elseif ($existing->sync_hash === $hash) {
                $upToDate[] = array_merge($row, ['_hash' => $hash, '_action' => 'up_to_date', '_id' => $existing->id]);
            } else {
                $errs = $this->requiredHouseholdErrors($row, $rowNum);
                if (!empty($errs)) {
                    $errors[] = ['row' => $row, 'reason' => 'आवश्यक/अमान्य विवरण', 'validation_errors' => $errs];
                } else {
                    $changed[] = array_merge($row, ['_hash' => $hash, '_action' => 'changed', '_id' => $existing->id, '_old_hash' => $existing->sync_hash]);
                }
            }
        }

        $debug = [
            'total_rows'         => count($sheets['households']),
            'column_names'       => array_keys($sheets['households'][0] ?? []),
            'wards_in_file'      => array_keys($wardNumbersSeen),
            'filtering_for_ward' => (string)$wardNum,
            'sub_counts'         => [
                'members_in_file'   => count($sheets['members']),
                'livestock_in_file' => count($sheets['livestock']),
                'housing_in_file'   => count($sheets['housing']),
            ],
        ];

        return compact('new', 'changed', 'locked', 'upToDate', 'errors', 'debug');
    }

    private function writeHouseholds(array $diff, int $wardId, int $lgId, bool $forceLockedRecords): array
    {
        $imported   = 0;
        $updated    = 0;
        $skipped    = 0;
        $firstError = null;

        $toProcess = array_merge($diff['new'], $diff['changed']);
        if ($forceLockedRecords) {
            $toProcess = array_merge($toProcess, $diff['locked']);
        } else {
            $skipped += count($diff['locked']);
        }

        $existingIds = array_filter(array_column($toProcess, '_id'));
        $existingMap = $existingIds
            ? Household::whereIn('id', array_values($existingIds))->get()->keyBy('id')->all()
            : [];

        foreach ($toProcess as $row) {
            try {
                // Resolve settlement + build data OUTSIDE transaction (settlement auto-create is safe)
                $data     = $this->rowToHouseholdData($row, $wardId, $lgId);
                $existing = isset($row['_id']) ? ($existingMap[$row['_id']] ?? null) : null;

                DB::transaction(function () use ($row, $data, $wardId, $lgId, $existing, &$imported, &$updated) {
                    $syncMeta = [
                        'sync_source' => 'google_sheets',
                        'sync_hash'   => $row['_hash'],
                        'sync_locked' => false,
                        'status'      => 'submitted',
                    ];

                    if ($existing) {
                        $existing->update(array_merge($data, $syncMeta));
                        $household = $existing->fresh();
                        $updated++;
                    } else {
                        $household = Household::create(array_merge($data, [
                            'ward_id'       => $wardId,
                            'enumerator_id' => null,
                        ], $syncMeta));
                        $imported++;
                    }

                    $this->syncRelated($household, $row['_related'] ?? []);
                });
            } catch (\Throwable $e) {
                Log::warning('GS sync household row failed', [
                    'code' => $row['unique_code'] ?? '?',
                    'err'  => $e->getMessage(),
                ]);
                $firstError ??= $e->getMessage();
                $skipped++;
            }
        }

        return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped, 'firstError' => $firstError];
    }

    // ── Related table sync ────────────────────────────────────────────────────

    private function syncRelated(Household $household, array $related): void
    {
        $housingRow = $related['housingRow'] ?? null;
        if ($housingRow) {
            $household->housing()->updateOrCreate(
                ['household_id' => $household->id],
                $this->rowToHousingData($housingRow)
            );
        }
        if ($utilitiesRow = $related['utilitiesRow'] ?? null) {
            $household->utilities()->updateOrCreate(
                ['household_id' => $household->id],
                $this->rowToUtilitiesData($utilitiesRow)
            );
        }
        if ($economicRow = $related['economicRow'] ?? null) {
            $household->economic()->updateOrCreate(
                ['household_id' => $household->id],
                $this->rowToEconomicData($economicRow)
            );
        }
        if ($agricultureRow = $related['agricultureRow'] ?? null) {
            $household->agriculture()->updateOrCreate(
                ['household_id' => $household->id],
                $this->rowToAgricultureData($agricultureRow)
            );
        }

        $livestockRows = $related['livestockRows'] ?? [];
        if (!empty($livestockRows)) {
            $this->syncLivestock($household, $livestockRows);

            // Extract bee/fish/silk data from livestock rows → save to survey_data
            $beeFish = $this->extractBeeFishData($livestockRows);
            if (!empty($beeFish)) {
                $surveyData = $household->survey_data ?? [];
                $surveyData['beeFishSilk'] = array_merge($surveyData['beeFishSilk'] ?? [], $beeFish);
                $household->update(['survey_data' => $surveyData]);
            }
        }

        if (!empty($related['memberRows'])) {
            $this->syncMembers($household, $related['memberRows']);
        }

        // Sync land parcel data → survey_data
        $landParcelRows = $related['landParcelRows'] ?? [];
        $rawFlag = $housingRow ? trim($housingRow['has_land_in_municipality'] ?? '') : '';
        if ($rawFlag !== '' || !empty($landParcelRows)) {
            $hasLand = $rawFlag !== ''
                ? ($this->parseBool($rawFlag) ? 'yes' : 'no')
                : 'yes';
            $parcels = array_values(array_filter(
                array_map(fn($r) => $this->rowToLandParcelData($r), $landParcelRows)
            ));
            $surveyData = $household->survey_data ?? [];
            $surveyData['hasLandInMunicipality'] = $hasLand;
            $surveyData['landParcels']            = $parcels;
            $household->update(['survey_data' => $surveyData]);
        }
    }

    private function syncMembers(Household $household, array $memberRows): void
    {
        $existing = $household->members()->get();
        $existingByKey = [];
        foreach ($existing as $member) {
            $key = strtolower(trim($member->full_name ?? '')) . '|' . strtolower(trim($member->gender ?? ''));
            $existingByKey[$key] = $member;
        }

        $seenKeys  = [];
        $sortOrder = 0;

        foreach ($memberRows as $row) {
            $name   = trim($row['full_name'] ?? '');
            $gender = trim($row['gender'] ?? '');
            if (!$name) continue;

            $key  = strtolower($name) . '|' . strtolower($gender);
            $seenKeys[$key] = true;
            $data = array_merge($this->rowToMemberData($row), ['sort_order' => $sortOrder++]);

            if (isset($existingByKey[$key])) {
                $existingByKey[$key]->update($data);
            } else {
                $household->members()->create($data);
            }
        }

        // Remove members no longer in the sheet
        foreach ($existingByKey as $key => $member) {
            if (!isset($seenKeys[$key])) {
                $member->delete();
            }
        }
    }

    private function syncLivestock(Household $household, array $livestockRows): void
    {
        $existing = $household->livestock()->get();
        $existingByType = [];
        foreach ($existing as $ls) {
            $key = strtolower(trim($ls->livestock_type ?? ''));
            $existingByType[$key] = $ls;
        }

        $seenTypes = [];

        foreach ($livestockRows as $row) {
            $type = trim($row['livestock_type'] ?? '');
            if (!$type) continue;

            $key = strtolower($type);
            $seenTypes[$key] = true;
            $data = $this->rowToLivestockData($row);

            if (isset($existingByType[$key])) {
                $existingByType[$key]->update($data);
            } else {
                $household->livestock()->create($data);
            }
        }

        // Remove livestock no longer in the sheet
        foreach ($existingByType as $key => $ls) {
            if (!isset($seenTypes[$key])) {
                $ls->delete();
            }
        }
    }

    // ── Institution sync ──────────────────────────────────────────────────────

    public function previewInstitutions(string $sheetId, int $wardId, int $lgId): array
    {
        $tmp = $this->downloadSheet($sheetId);
        try {
            return $this->diffInstitutions($tmp, $wardId, $lgId);
        } finally {
            @unlink($tmp);
        }
    }

    public function applyInstitutions(string $sheetId, int $wardId, int $lgId, bool $forceLockedRecords = false): array
    {
        $tmp = $this->downloadSheet($sheetId);
        try {
            $diff = $this->diffInstitutions($tmp, $wardId, $lgId);
            return $this->writeInstitutions($diff, $wardId, $lgId, $forceLockedRecords);
        } finally {
            @unlink($tmp);
        }
    }

    private function diffInstitutions(string $filePath, int $wardId, int $lgId): array
    {
        $sheets  = $this->loadInstitutionSheets($filePath);
        $ward    = Ward::find($wardId);
        $wardNum = $ward?->ward_number;

        $infraIdx    = $this->indexBy($sheets['infrastructure'], 'institution_code');
        $healthIdx   = $this->indexBy($sheets['health'],         'institution_code');
        $staffIdx    = $this->groupBy($sheets['staff'],          'institution_code');
        $educationIdx = $this->groupBy($sheets['education'],     'institution_code');
        $servicesIdx = $this->groupBy($sheets['services'],       'institution_code');
        $teachersIdx = $this->groupBy($sheets['teachers'],       'institution_code');

        $existingMap = Institution::where('ward_id', $wardId)
            ->select(['id', 'unique_code', 'sync_hash', 'sync_locked'])
            ->get()
            ->keyBy('unique_code');

        $new = $changed = $locked = $upToDate = $errors = [];
        $rowNum          = 1;
        $wardNumbersSeen = [];

        foreach ($sheets['institutions'] as $row) {
            $rowNum++;
            $rowWard = (string)($row['ward_number'] ?? '');
            if ($rowWard !== '') $wardNumbersSeen[$rowWard] = true;
            if ($rowWard !== (string)$wardNum) continue;

            $uniqueCode = trim($row['unique_code'] ?? $row['institution_code'] ?? '');
            if (!$uniqueCode) {
                $errors[] = ['row' => $row, 'reason' => 'unique_code / institution_code खाली छ'];
                continue;
            }
            $row['unique_code'] = $uniqueCode;
            $codeKey = strtolower($uniqueCode);

            $infraRow    = $infraIdx[$codeKey]    ?? null;
            $healthRow   = $healthIdx[$codeKey]   ?? null;
            $staffRows   = $staffIdx[$codeKey]    ?? [];
            $educRows    = $educationIdx[$codeKey] ?? [];
            $serviceRows = $servicesIdx[$codeKey] ?? [];
            $teacherRows = $teachersIdx[$codeKey] ?? [];

            $hash     = $this->hashInstitutionAllSheets($row, $infraRow, $healthRow, $staffRows, $educRows, $serviceRows, $teacherRows);
            $existing = $existingMap->get($uniqueCode);

            $row['_related'] = compact('infraRow', 'healthRow', 'staffRows', 'educRows', 'serviceRows', 'teacherRows');

            $row['_completeness'] = [
                'infrastructure' => !empty($infraRow),
                'health'         => !empty($healthRow),
                'staff'          => count($staffRows),
                'education'      => count($educRows),
                'services'       => count($serviceRows),
                'teachers'       => count($teacherRows),
                'settlement'     => !empty(trim($row['settlement_name'] ?? '')),
            ];

            if (!$existing) {
                $errs = $this->requiredInstitutionErrors($row, $rowNum);
                if (!empty($errs)) {
                    $errors[] = ['row' => $row, 'reason' => 'आवश्यक/अमान्य विवरण', 'validation_errors' => $errs];
                } else {
                    $new[] = array_merge($row, ['_hash' => $hash, '_action' => 'new']);
                }
            } elseif ($existing->sync_locked) {
                $locked[] = array_merge($row, ['_hash' => $hash, '_action' => 'locked', '_id' => $existing->id]);
            } elseif ($existing->sync_hash === $hash) {
                $upToDate[] = array_merge($row, ['_hash' => $hash, '_action' => 'up_to_date', '_id' => $existing->id]);
            } else {
                $errs = $this->requiredInstitutionErrors($row, $rowNum);
                if (!empty($errs)) {
                    $errors[] = ['row' => $row, 'reason' => 'आवश्यक/अमान्य विवरण', 'validation_errors' => $errs];
                } else {
                    $changed[] = array_merge($row, ['_hash' => $hash, '_action' => 'changed', '_id' => $existing->id]);
                }
            }
        }

        $debug = [
            'total_rows'         => count($sheets['institutions']),
            'column_names'       => array_keys($sheets['institutions'][0] ?? []),
            'wards_in_file'      => array_keys($wardNumbersSeen),
            'filtering_for_ward' => (string)$wardNum,
            'sub_counts'         => [
                'infrastructure_in_file' => count($sheets['infrastructure']),
                'staff_in_file'          => count($sheets['staff']),
                'education_in_file'      => count($sheets['education']),
                'health_in_file'         => count($sheets['health']),
                'services_in_file'       => count($sheets['services']),
                'teachers_in_file'       => count($sheets['teachers']),
            ],
        ];

        return compact('new', 'changed', 'locked', 'upToDate', 'errors', 'debug');
    }

    private function writeInstitutions(array $diff, int $wardId, int $lgId, bool $forceLockedRecords): array
    {
        $imported = 0; $updated = 0; $skipped = 0; $firstError = null;

        $toProcess = array_merge($diff['new'], $diff['changed']);
        if ($forceLockedRecords) $toProcess = array_merge($toProcess, $diff['locked']);
        else $skipped += count($diff['locked']);

        $existingIds = array_filter(array_column($toProcess, '_id'));
        $existingMap = $existingIds
            ? Institution::whereIn('id', array_values($existingIds))->get()->keyBy('id')->all()
            : [];

        foreach ($toProcess as $row) {
            try {
                $data     = $this->rowToInstitutionData($row, $wardId, $lgId);
                $existing = isset($row['_id']) ? ($existingMap[$row['_id']] ?? null) : null;

                DB::transaction(function () use ($row, $data, $wardId, $lgId, $existing, &$imported, &$updated) {
                    $syncMeta = [
                        'sync_source' => 'google_sheets',
                        'sync_hash'   => $row['_hash'],
                        'sync_locked' => false,
                        'status'      => 'submitted',
                    ];

                    if ($existing) {
                        $existing->update(array_merge($data, $syncMeta));
                        $institution = $existing->fresh();
                        $updated++;
                    } else {
                        $institution = Institution::create(array_merge($data, [
                            'ward_id'       => $wardId,
                            'enumerator_id' => null,
                        ], $syncMeta));
                        $imported++;
                    }

                    $this->syncInstitutionRelated($institution, $row['_related'] ?? []);
                });
            } catch (\Throwable $e) {
                Log::warning('GS sync institution row failed', ['code' => $row['unique_code'] ?? '?', 'err' => $e->getMessage()]);
                $firstError ??= $e->getMessage();
                $skipped++;
            }
        }

        return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped, 'firstError' => $firstError];
    }

    private function syncInstitutionRelated(Institution $institution, array $related): void
    {
        if ($infraRow = $related['infraRow'] ?? null) {
            $institution->infrastructure()->updateOrCreate(
                ['institution_id' => $institution->id],
                $this->rowToInfrastructureData($infraRow)
            );
        }

        if ($healthRow = $related['healthRow'] ?? null) {
            $institution->healthDetail()->updateOrCreate(
                ['institution_id' => $institution->id],
                $this->rowToHealthData($healthRow)
            );
        }

        // Staff: match by staff_category, update/create/delete
        $staffRows = $related['staffRows'] ?? [];
        if (!empty($staffRows)) {
            $existing    = $institution->staff()->get()->keyBy('staff_category');
            $seenCats    = [];
            foreach ($staffRows as $row) {
                $cat = trim($row['staff_category'] ?? '');
                if (!$cat) continue;
                $seenCats[$cat] = true;
                $data = $this->rowToStaffData($row);
                isset($existing[$cat])
                    ? $existing[$cat]->update($data)
                    : $institution->staff()->create($data);
            }
            foreach ($existing as $cat => $staff) {
                if (!isset($seenCats[$cat])) $staff->delete();
            }
        }

        // Education: match by grade
        $educRows = $related['educRows'] ?? [];
        if (!empty($educRows)) {
            $existing  = $institution->education()->get()->keyBy('grade');
            $seenGrades = [];
            foreach ($educRows as $row) {
                $grade = trim($row['grade'] ?? '');
                if (!$grade) continue;
                $seenGrades[$grade] = true;
                $data = $this->rowToEducationData($row);
                isset($existing[$grade])
                    ? $existing[$grade]->update($data)
                    : $institution->education()->create($data);
            }
            foreach ($existing as $grade => $educ) {
                if (!isset($seenGrades[$grade])) $educ->delete();
            }
        }

        // Services: replace all (no stable natural key)
        $serviceRows = $related['serviceRows'] ?? [];
        if (!empty($serviceRows)) {
            $institution->services()->delete();
            foreach ($serviceRows as $row) {
                $name = trim($row['service_name'] ?? '');
                if (!$name) continue;
                $institution->services()->create($this->rowToServicesData($row));
            }
        }

        // Teachers: save to survey_data.teachers JSON
        $teacherRows = $related['teacherRows'] ?? [];
        if (!empty($teacherRows)) {
            $teachers = array_map(fn($r) => [
                'full_name'          => trim($r['full_name']          ?? '') ?: null,
                'gender'             => trim($r['gender']             ?? '') ?: null,
                'category'           => trim($r['category']           ?? '') ?: null,
                'qualification'      => trim($r['qualification']      ?? '') ?: null,
                'appointment_level'  => trim($r['appointment_level']  ?? '') ?: null,
                'specialization'     => trim($r['specialization']     ?? '') ?: null,
                'subjects'           => trim($r['subjects']           ?? '') ?: null,
                'enrollment_date'    => trim($r['enrollment_date']    ?? '') ?: null,
            ], $teacherRows);
            $surveyData = $institution->survey_data ?? [];
            $surveyData['teachers'] = $teachers;
            $institution->update(['survey_data' => $surveyData]);
        }
    }

    // ── Multi-sheet loading ───────────────────────────────────────────────────

    private function loadInstitutionSheets(string $filePath): array
    {
        $collector = new InstitutionSyncAllSheetsCollector();
        Excel::import($collector, $filePath);
        return [
            'institutions'   => $collector->getRows('Institutions'),
            'infrastructure' => $collector->getRows('Infrastructure'),
            'staff'          => $collector->getRows('Staff'),
            'education'      => $collector->getRows('Education'),
            'health'         => $collector->getRows('Health'),
            'services'       => $collector->getRows('Services'),
            'teachers'       => $collector->getRows('Teachers'),
        ];
    }

    private function loadAllSheets(string $filePath): array
    {
        $collector = new GoogleSyncAllSheetsCollector();
        Excel::import($collector, $filePath);
        return [
            'households'  => $collector->getRows('Households'),
            'members'     => $collector->getRows('Members'),
            'housing'     => $collector->getRows('Housing'),
            'utilities'   => $collector->getRows('Utilities'),
            'economic'    => $collector->getRows('Economic'),
            'agriculture' => $collector->getRows('Agriculture'),
            'livestock'   => $collector->getRows('Livestock'),
            'landParcels' => $collector->getRows('LandParcels'),
        ];
    }

    /** One-to-one index: key (lowercase) → row */
    private function indexBy(array $rows, string $key): array
    {
        $map = [];
        foreach ($rows as $row) {
            $k = strtolower(trim((string)($row[$key] ?? '')));
            if ($k !== '') $map[$k] = $row;
        }
        return $map;
    }

    /** One-to-many index: key (lowercase) → [rows] */
    private function groupBy(array $rows, string $key): array
    {
        $map = [];
        foreach ($rows as $row) {
            $k = strtolower(trim((string)($row[$key] ?? '')));
            if ($k !== '') $map[$k][] = $row;
        }
        return $map;
    }

    /** Combined hash across all 8 sheets for a single household */
    private function hashAllSheets(
        array  $hhRow,
        ?array $housingRow,
        ?array $utilitiesRow,
        ?array $economicRow,
        ?array $agricultureRow,
        array  $memberRows,
        array  $livestockRows,
        array  $landParcelRows = []
    ): string {
        usort($memberRows,    fn($a, $b) => strcmp(
            strtolower(trim($a['full_name'] ?? '')),
            strtolower(trim($b['full_name'] ?? ''))
        ));
        usort($livestockRows, fn($a, $b) => strcmp(
            strtolower(trim($a['livestock_type'] ?? '')),
            strtolower(trim($b['livestock_type'] ?? ''))
        ));
        $combined = compact('hhRow', 'housingRow', 'utilitiesRow', 'economicRow', 'agricultureRow', 'memberRows', 'livestockRows', 'landParcelRows');
        return hash('sha256', json_encode($combined, JSON_UNESCAPED_UNICODE));
    }

    private function hashInstitutionAllSheets(
        array  $instRow,
        ?array $infraRow,
        ?array $healthRow,
        array  $staffRows,
        array  $educRows,
        array  $serviceRows,
        array  $teacherRows
    ): string {
        usort($staffRows,    fn($a, $b) => strcmp($a['staff_category'] ?? '', $b['staff_category'] ?? ''));
        usort($educRows,     fn($a, $b) => strcmp($a['grade'] ?? '',          $b['grade'] ?? ''));
        usort($serviceRows,  fn($a, $b) => strcmp($a['service_name'] ?? '',   $b['service_name'] ?? ''));
        usort($teacherRows,  fn($a, $b) => strcmp($a['full_name'] ?? '',      $b['full_name'] ?? ''));
        $combined = compact('instRow', 'infraRow', 'healthRow', 'staffRows', 'educRows', 'serviceRows', 'teacherRows');
        return hash('sha256', json_encode($combined, JSON_UNESCAPED_UNICODE));
    }

    // ── Row mappers ───────────────────────────────────────────────────────────

    private function rowToHouseholdData(array $row, int $wardId, int $lgId): array
    {
        [$gpsLat, $gpsLng] = $this->parseGps($row);
        return [
            'unique_code'        => trim($row['unique_code']        ?? '') ?: null,
            'settlement_id'      => $this->resolveSettlementId(trim($row['settlement_name'] ?? ''), $wardId),
            'respondent_name'    => trim($row['respondent_name']    ?? '') ?: null,
            'respondent_contact' => trim($row['respondent_contact'] ?? '') ?: null,
            'total_members'      => is_numeric($row['total_members'] ?? '') ? (int)$row['total_members'] : null,
            'gps_lat'            => $gpsLat,
            'gps_lng'            => $gpsLng,
            'gps_altitude'       => is_numeric($row['gps_altitude']  ?? '') ? (float)$row['gps_altitude']  : null,
            'gps_accuracy'       => is_numeric($row['gps_accuracy']  ?? '') ? (float)$row['gps_accuracy']  : null,
            'photo_number'       => trim($row['photo_number']       ?? '') ?: null,
            'survey_date'        => $this->parseDate($row['survey_date'] ?? null),
            'remarks'            => trim($row['remarks']             ?? '') ?: null,
        ];
    }

    private function rowToHousingData(array $row): array
    {
        return [
            'house_land_type'       => trim($row['house_land_type']       ?? '') ?: null,
            'land_measurement_unit' => trim($row['land_measurement_unit'] ?? '') ?: null,
            'house_type'            => trim($row['house_type']            ?? '') ?: null,
            'house_purpose'         => trim($row['house_purpose']         ?? '') ?: null,
            'location_house_built'  => trim($row['location_house_built']  ?? '') ?: null,
            'roof_type'             => trim($row['roof_type']             ?? '') ?: null,
            'wall_type'             => trim($row['wall_type']             ?? '') ?: null,
            'foundation_type'       => trim($row['foundation_type']       ?? '') ?: null,
            'ownership_type'        => trim($row['ownership_type']        ?? '') ?: null,
            'rent_amount'           => is_numeric($row['rent_amount']          ?? '') ? (int)$row['rent_amount']           : null,
            'distance_to_mainroad'  => is_numeric($row['distance_to_mainroad'] ?? '') ? (int)$row['distance_to_mainroad']  : null,
            'distance_to_school'    => is_numeric($row['distance_to_school']   ?? '') ? (int)$row['distance_to_school']    : null,
            'total_rooms'           => is_numeric($row['total_rooms']          ?? '') ? (int)$row['total_rooms']           : null,
            'total_floors'          => is_numeric($row['total_floors']         ?? '') ? (int)$row['total_floors']          : null,
            'has_building_permit'   => $this->parseBool($row['has_building_permit'] ?? null),
            'construction_year'     => is_numeric($row['construction_year']    ?? '') ? (int)$row['construction_year']     : null,
            'house_condition'       => trim($row['house_condition']       ?? '') ?: null,
            'is_at_risk'            => $this->parseBool($row['is_at_risk'] ?? null),
            'risk_types'            => $this->parseJsonArray($row['risk_types'] ?? null),
        ];
    }

    private function rowToLandParcelData(array $row): ?array
    {
        $landUnit    = trim($row['land_unit']    ?? '');
        $landUseType = trim($row['land_use_type'] ?? '');
        $quantity    = trim($row['quantity']     ?? '');
        if ($landUnit === '' && $landUseType === '' && $quantity === '') return null;
        return [
            'land_unit'     => $landUnit    ?: null,
            'land_use_type' => $landUseType ?: null,
            'quantity'      => $quantity    ?: null,
        ];
    }

    private function rowToUtilitiesData(array $row): array
    {
        return [
            'water_source'              => trim($row['water_source']              ?? '') ?: null,
            'water_distance_minutes'    => is_numeric($row['water_distance_minutes'] ?? '') ? (int)$row['water_distance_minutes'] : null,
            'has_toilet'                => $this->parseBool($row['has_toilet']    ?? null),
            'toilet_type'               => trim($row['toilet_type']               ?? '') ?: null,
            'sanitation_type'           => trim($row['sanitation_type']           ?? '') ?: null,
            'electricity_source'        => trim($row['electricity_source']        ?? '') ?: null,
            'monthly_electricity_units' => is_numeric($row['monthly_electricity_units'] ?? '') ? (int)$row['monthly_electricity_units'] : null,
            'cooking_fuel'              => trim($row['cooking_fuel']              ?? '') ?: null,
            'has_biogas'                => $this->parseBool($row['has_biogas']    ?? null),
            'solid_waste_management'    => trim($row['solid_waste_management']    ?? '') ?: null,
            'has_internet'              => $this->parseBool($row['has_internet']  ?? null),
            'internet_type'             => trim($row['internet_type']             ?? '') ?: null,
            'handwashing'               => trim($row['handwashing']               ?? '') ?: null,
            // vehicles_bike/car/tempo/tractor/other → vehicles array; has_own_vehicle → vehicle (varchar Yes/No)
            'vehicle'                   => $this->parseBoolYesNo($row['has_own_vehicle'] ?? null),
            'vehicles'                  => $this->parseVehiclesArray($row),
        ];
    }

    private function rowToEconomicData(array $row): array
    {
        return [
            'primary_income_source'     => trim($row['primary_income_source']     ?? '') ?: null,
            'annual_income_range'       => trim($row['annual_income_range']       ?? '') ?: null,
            'annual_expenditure_range'  => trim($row['annual_expenditure_range']  ?? '') ?: null,
            'remittance_amount'         => is_numeric($row['remittance_amount']   ?? '') ? (int)$row['remittance_amount'] : null,
            'loan_sources'              => $this->parseJsonArray($row['loan_sources'] ?? null),
            'total_loan_amount'         => is_numeric($row['total_loan_amount']   ?? '') ? (int)$row['total_loan_amount'] : null,
            'has_bank_account'          => $this->parseBool($row['has_bank_account']          ?? null),
            'has_insurance'             => $this->parseBool($row['has_insurance']             ?? null),
            'poverty_card'              => $this->parseBool($row['poverty_card']              ?? null),
            'social_security_allowance' => $this->parseBool($row['social_security_allowance'] ?? null),
            // decision_* and has_foreign_employment go to survey_data — skipped here
        ];
    }

    private function rowToAgricultureData(array $row): array
    {
        return [
            // 'agriculture' column = yes/no toggle for whether the HH practices agriculture
            'agriculture'            => trim($row['agriculture']            ?? '') ?: null,
            'agri_categories'        => $this->parseJsonArray($row['agri_categories'] ?? null),
            'use_of_chem_fertilizer' => trim($row['use_of_chem_fertilizer'] ?? '') ?: null,
            'Farm_Challenges'        => $this->parseJsonArray($row['farm_challenges'] ?? null),
            'owns_land'              => $this->parseBool($row['owns_land']  ?? null),
            'land_area_ropani'       => is_numeric($row['land_area_ropani']      ?? '') ? (float)$row['land_area_ropani']      : null,
            'irrigated_area_ropani'  => is_numeric($row['irrigated_area_ropani'] ?? '') ? (float)$row['irrigated_area_ropani'] : null,
            'annual_agri_income'     => is_numeric($row['annual_agri_income']    ?? '') ? (int)$row['annual_agri_income']      : null,
        ];
    }

    private function rowToMemberData(array $row): array
    {
        return [
            'full_name'         => trim($row['full_name']         ?? '') ?: null,
            'gender'            => trim($row['gender']            ?? '') ?: null,
            'age'               => is_numeric($row['age']         ?? '') ? (int)$row['age'] : null,
            'date_of_birth'     => $this->parseDate($row['date_of_birth'] ?? null),
            'relation_to_head'  => trim($row['relation_to_head']  ?? '') ?: null,
            'ethnicity'         => trim($row['ethnicity']         ?? '') ?: null,
            'religion'          => trim($row['religion']          ?? '') ?: null,
            'mother_tongue'     => trim($row['mother_tongue']     ?? '') ?: null,
            'marital_status'    => trim($row['marital_status']    ?? '') ?: null,
            'education_level'   => trim($row['education_level']   ?? '') ?: null,
            'is_literate'       => $this->parseBool($row['is_literate']       ?? null),
            'occupation_type'   => trim($row['occupation_type']   ?? '') ?: null,
            'employment_status' => trim($row['employment_status'] ?? '') ?: null,
            'disability_status' => $this->parseBool($row['disability_status'] ?? null),
            'disability_type'   => trim($row['disability_type']   ?? '') ?: null,
            'blood_group'       => trim($row['blood_group']       ?? '') ?: null,
            'residence_status'  => trim($row['residence_status']  ?? '') ?: null,
            'contact_phone'     => trim($row['contact_phone']     ?? '') ?: null,
            'is_household_head' => $this->parseBool($row['is_household_head'] ?? null),
            'has_citizenship'   => $this->parseBool($row['has_citizenship']   ?? null),
            'has_voters_card'   => $this->parseBool($row['has_voters_card']   ?? null),
            'has_national_id_card' => $this->parseBool($row['has_national_id_card'] ?? null),
            'has_passport'      => $this->parseBool($row['has_passport']      ?? null),
            'has_birth_certificate' => $this->parseBool($row['has_birth_certificate'] ?? null),
            'remarks'           => trim($row['remarks'] ?? '') ?: null,
        ];
    }

    private function rowToLivestockData(array $row): array
    {
        return [
            'livestock_type'     => trim($row['livestock_type'] ?? '') ?: null,
            'count'              => is_numeric($row['count']    ?? '') ? (int)$row['count'] : null,
            'vet_service_access' => $this->parseBool($row['vet_service_access'] ?? null),
            'insurance'          => $this->parseBool($row['insurance']           ?? null),
        ];
    }

    private function rowToInstitutionData(array $row, int $wardId, int $lgId): array
    {
        [$gpsLat, $gpsLng] = $this->parseGps($row);
        return [
            'unique_code'         => trim($row['unique_code'] ?? $row['institution_code'] ?? '') ?: null,
            'name_en'             => trim($row['name_en']             ?? '') ?: null,
            'name_np'             => trim($row['name_np']             ?? '') ?: null,
            'ownership_type'      => trim($row['ownership_type']      ?? '') ?: null,
            'registration_number' => trim($row['registration_number'] ?? '') ?: null,
            'institution_type_id' => $this->resolveInstitutionTypeId($row['institution_type'] ?? null),
            'settlement_id'       => $this->resolveSettlementId(trim($row['settlement_name'] ?? ''), $wardId),
            'gps_lat'             => $gpsLat,
            'gps_lng'             => $gpsLng,
            'photo_number'        => trim($row['photo_number']        ?? '') ?: null,
            'respondent_name'     => trim($row['respondent_name']     ?? '') ?: null,
            'respondent_contact'  => trim($row['respondent_contact']  ?? '') ?: null,
            'remarks'             => trim($row['remarks']             ?? '') ?: null,
            'survey_date'         => $this->parseDate($row['survey_date']      ?? null),
            'established_date'    => $this->parseDate($row['established_date'] ?? null),
        ];
    }

    private function rowToInfrastructureData(array $row): array
    {
        return [
            'buildings'        => trim($row['building_type']     ?? '') ?: null,
            'condition'        => trim($row['building_condition'] ?? '') ?: null,
            'total_rooms'      => is_numeric($row['total_rooms']  ?? '') ? (int)$row['total_rooms']  : null,
            'total_floors'     => is_numeric($row['total_floors'] ?? '') ? (int)$row['total_floors'] : null,
            'total_area'       => is_numeric($row['land_area_sqft'] ?? '') ? (float)$row['land_area_sqft'] : null,
            'has_boundary_wall'  => $this->parseBool($row['has_boundary_wall']  ?? null),
            'toilet_count'       => is_numeric($row['toilet_count']   ?? '') ? (int)$row['toilet_count']   : null,
            'female_toilet'      => is_numeric($row['female_toilet']  ?? '') ? (int)$row['female_toilet']  : null,
            'male_toilet'        => is_numeric($row['male_toilet']    ?? '') ? (int)$row['male_toilet']    : null,
            'disabled_toilet'    => $this->parseBool($row['disabled_toilet']    ?? null),
            'has_ramp'           => $this->parseBool($row['accessible_ramp']    ?? null),
            'has_water'          => $this->parseBool($row['drinking_water']     ?? null),
            'has_electricity'    => $this->parseBool($row['electricity']        ?? null),
            'solar'              => $this->parseBool($row['solar']              ?? null),
            'generator'          => $this->parseBool($row['generator']          ?? null),
            'has_internet'       => $this->parseBool($row['internet']           ?? null),
            'fire_extinguisher'  => $this->parseBool($row['fire_extinguisher']  ?? null),
            'earthquake_safe'    => $this->parseBool($row['earthquake_safe']    ?? null),
            'evacuation_plan'    => $this->parseBool($row['evacuation_plan']    ?? null),
        ];
    }

    private function rowToStaffData(array $row): array
    {
        return [
            'staff_category'   => trim($row['staff_category']   ?? '') ?: null,
            'male_count'       => is_numeric($row['male_count']       ?? '') ? (int)$row['male_count']       : 0,
            'female_count'     => is_numeric($row['female_count']     ?? '') ? (int)$row['female_count']     : 0,
            'dalit_count'      => is_numeric($row['dalit_count']      ?? '') ? (int)$row['dalit_count']      : 0,
            'janajati_count'   => is_numeric($row['janajati_count']   ?? '') ? (int)$row['janajati_count']   : 0,
            'other_count'      => is_numeric($row['other_count']      ?? '') ? (int)$row['other_count']      : 0,
            'permanent_count'  => is_numeric($row['permanent_count']  ?? '') ? (int)$row['permanent_count']  : 0,
            'contract_count'   => is_numeric($row['contract_count']   ?? '') ? (int)$row['contract_count']   : 0,
        ];
    }

    private function rowToEducationData(array $row): array
    {
        return [
            'grade'                => trim($row['grade']                ?? '') ?: null,
            'male_students'        => is_numeric($row['male_students']        ?? '') ? (int)$row['male_students']        : 0,
            'female_students'      => is_numeric($row['female_students']      ?? '') ? (int)$row['female_students']      : 0,
            'dalit_students'       => is_numeric($row['dalit_students']       ?? '') ? (int)$row['dalit_students']       : 0,
            'janajati_students'    => is_numeric($row['janajati_students']    ?? '') ? (int)$row['janajati_students']    : 0,
            'scholarship_students' => is_numeric($row['scholarship_students'] ?? '') ? (int)$row['scholarship_students'] : 0,
            'dropout_count'        => is_numeric($row['dropout_count']        ?? '') ? (int)$row['dropout_count']        : 0,
        ];
    }

    private function rowToHealthData(array $row): array
    {
        return [
            'bed_count'              => is_numeric($row['bed_count'] ?? '') ? (int)$row['bed_count'] : null,
            'delivery_service'       => $this->parseBool($row['delivery_service']       ?? null),
            'emergency_service'      => $this->parseBool($row['emergency_service']      ?? null),
            'has_lab'                => $this->parseBool($row['lab_service']             ?? null),
            'has_xray'               => $this->parseBool($row['xray_service']            ?? null),
            'has_ambulance'          => $this->parseBool($row['ambulance']               ?? null),
            'opd_monthly'            => is_numeric($row['monthly_outpatient_avg'] ?? '') ? (int)$row['monthly_outpatient_avg'] : null,
            'ipd_monthly'            => is_numeric($row['monthly_inpatient_avg']  ?? '') ? (int)$row['monthly_inpatient_avg']  : null,
            'vaccine_service'        => $this->parseBool($row['vaccine_service']         ?? null),
            'family_planning'        => $this->parseBool($row['family_planning']         ?? null),
        ];
    }

    private function rowToServicesData(array $row): array
    {
        return [
            'service_name'          => trim($row['service_name']    ?? '') ?: null,
            'service_type'          => trim($row['service_type']     ?? '') ?: null,
            'capacity'              => is_numeric($row['capacity']              ?? '') ? (int)$row['capacity']              : null,
            'annual_beneficiaries'  => is_numeric($row['annual_beneficiaries'] ?? '') ? (int)$row['annual_beneficiaries']  : null,
            'coverage_description'  => trim($row['coverage_description'] ?? '') ?: null,
            'is_free'               => $this->parseBool($row['is_free'] ?? null) ?? true,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function parseGps(array $row): array
    {
        $lat = $row['gps_lat'] ?? null;
        $lng = $row['gps_lng'] ?? null;
        if ($lat === null && $lng === null) return [null, null];
        if (!is_numeric($lat) || !is_numeric($lng)) return [null, null];
        $lat = (float)$lat; $lng = (float)$lng;
        if ($lat < 26.0 || $lat > 30.5 || $lng < 80.0 || $lng > 88.5) return [null, null];
        return [$lat, $lng];
    }

    private function parseDate(mixed $val): ?string
    {
        if (!$val) return null;
        if (is_numeric($val)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$val)
                    ->format('Y-m-d');
            } catch (\Throwable) {}
        }
        $d = \DateTime::createFromFormat('Y-m-d', trim((string)$val));
        return $d ? $d->format('Y-m-d') : null;
    }

    private function parseBool(mixed $val): ?bool
    {
        if ($val === null || $val === '') return null;
        if (is_bool($val)) return $val;
        $s = strtolower(trim((string)$val));
        if (in_array($s, ['1', 'yes', 'true', 'छ', 'छन्', 'हो'], true))  return true;
        if (in_array($s, ['0', 'no',  'false', 'छैन', 'होइन'], true)) return false;
        return null;
    }

    private function parseBoolYesNo(mixed $val): ?string
    {
        $b = $this->parseBool($val);
        if ($b === null) return null;
        return $b ? 'Yes' : 'No';
    }

    private function parseJsonArray(mixed $val): ?array
    {
        if ($val === null || $val === '') return null;
        if (is_array($val)) return $val;
        $s = trim((string)$val);
        if ($s === '') return null;
        if (str_starts_with($s, '[')) {
            $decoded = json_decode($s, true);
            if (is_array($decoded)) return $decoded;
        }
        return array_values(array_filter(array_map('trim', explode(',', $s))));
    }

    /**
     * Convert individual vehicle count columns into the vehicles array.
     * Template: vehicles_bike, vehicles_car, vehicles_tempo, vehicles_tractor, vehicles_other
     * Model:    vehicles (array of type strings, e.g. ['bike','car'])
     */
    private function parseVehiclesArray(array $row): ?array
    {
        $types = [];
        $map = [
            'vehicles_bike'    => 'bike',
            'vehicles_car'     => 'car',
            'vehicles_tempo'   => 'tempo',
            'vehicles_tractor' => 'tractor',
            'vehicles_other'   => 'other',
        ];
        foreach ($map as $col => $label) {
            if (isset($row[$col]) && is_numeric($row[$col]) && (int)$row[$col] > 0) {
                $types[] = $label;
            }
        }
        return empty($types) ? null : $types;
    }

    /**
     * Extract bee/fishery data from livestock rows (typically the first row per household).
     * Maps to survey_data['beeFishSilk'] keys used by the form.
     */
    private function extractBeeFishData(array $livestockRows): array
    {
        $data = [];
        $beeTypes = [];
        foreach ($livestockRows as $row) {
            if (!empty($row['has_beekeeping']) && $row['has_beekeeping'] !== '0') {
                $beeTypes[] = 'bee';
                if (is_numeric($row['mudhe_hive']       ?? '')) $data['Mudhe_Hive']                    = (int)$row['mudhe_hive'];
                if (is_numeric($row['modern_hive']      ?? '')) $data['Modern_Hive']                   = (int)$row['modern_hive'];
                if (is_numeric($row['honey_prod_mudhe'] ?? '')) $data['Honey_Production_Mudhe_Hive']   = (float)$row['honey_prod_mudhe'];
                if (is_numeric($row['honey_prod_modern']?? '')) $data['Honey_Production_Modern_Hive']  = (float)$row['honey_prod_modern'];
                if (is_numeric($row['honey_sales']      ?? '')) $data['honey_sales']                   = (float)$row['honey_sales'];
            }
            if (!empty($row['has_fishery']) && $row['has_fishery'] !== '0') {
                $beeTypes[] = 'fish';
                if (is_numeric($row['pond_area']  ?? '')) $data['Pond_area']   = (float)$row['pond_area'];
                if (is_numeric($row['fish_prod']  ?? '')) $data['fish_prod']   = (float)$row['fish_prod'];
                if (is_numeric($row['fish_sales'] ?? '')) $data['fish_sales']  = (float)$row['fish_sales'];
            }
        }
        if (!empty($beeTypes)) {
            $data['has_bee_fish_silk']    = true;
            $data['bee_fish_silk_select'] = array_values(array_unique($beeTypes));
        }
        return $data;
    }

    private function requiredHouseholdErrors(array $row, int $rowNum): array
    {
        $errs = [];
        if (!trim((string)($row['respondent_name'] ?? ''))) {
            $errs[] = "Row {$rowNum}: respondent_name (उत्तरदाताको नाम) आवश्यक छ";
        }
        if (!trim((string)($row['survey_date'] ?? ''))) {
            $errs[] = "Row {$rowNum}: survey_date (सर्वेक्षण मिति) आवश्यक छ";
        }
        return $errs;
    }

    private function requiredInstitutionErrors(array $row, int $rowNum): array
    {
        $errs = [];
        if (!trim((string)($row['name_np'] ?? '')) && !trim((string)($row['name_en'] ?? ''))) {
            $errs[] = "Row {$rowNum}: name_np वा name_en (संस्थाको नाम) आवश्यक छ";
        }
        if (!trim((string)($row['respondent_name'] ?? ''))) {
            $errs[] = "Row {$rowNum}: respondent_name (उत्तरदाताको नाम) आवश्यक छ";
        }
        if (!trim((string)($row['survey_date'] ?? ''))) {
            $errs[] = "Row {$rowNum}: survey_date (सर्वेक्षण मिति) आवश्यक छ";
        }
        return $errs;
    }

    /** Cache: "wardId|lowerName" → settlement_id */
    private array $settlementCache = [];

    /**
     * Look up a settlement by name within the ward.
     * If not found, auto-creates a new settlement with settlement_type = 'tole'.
     * Returns null when $name is blank.
     */
    private function resolveSettlementId(string $name, int $wardId): ?int
    {
        $name = trim($name);
        if ($name === '') return null;

        $cacheKey = $wardId . '|' . strtolower($name);
        if (!array_key_exists($cacheKey, $this->settlementCache)) {
            $id = Settlement::where('ward_id', $wardId)
                ->where(fn($q) => $q
                    ->whereRaw('LOWER(name_np) = ?', [strtolower($name)])
                    ->orWhereRaw('LOWER(name_en) = ?', [strtolower($name)])
                )
                ->value('id');

            if (!$id) {
                $settlement = Settlement::create([
                    'ward_id'         => $wardId,
                    'name_np'         => $name,
                    'name_en'         => $name,
                    'settlement_type' => 'tole',
                ]);
                $id = $settlement->id;
                Log::info("GS sync: auto-created settlement '{$name}' (ward {$wardId}, id {$id})");
            }

            $this->settlementCache[$cacheKey] = $id;
        }

        return $this->settlementCache[$cacheKey];
    }

    private array $institutionTypeCache = [];

    private function resolveInstitutionTypeId(?string $slug): ?int
    {
        if (!$slug) return null;
        $slug = trim($slug);
        if (!$slug) return null;
        if (!array_key_exists($slug, $this->institutionTypeCache)) {
            $this->institutionTypeCache[$slug] = InstitutionType::where('slug', $slug)->value('id');
        }
        return $this->institutionTypeCache[$slug];
    }
}
