<?php

namespace App\Services;

use App\Imports\RawRowCollector;
use App\Models\Household;
use App\Models\Institution;
use App\Models\MemberBusiness;
use App\Models\Ward;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\SurveyValueValidator;

class GoogleSheetsSyncService
{
    // ── Download ──────────────────────────────────────────────────────────────

    /**
     * Download a Google Sheet as XLSX and return the temp file path.
     * Throws \RuntimeException if the sheet is inaccessible.
     */
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
        $collector = new RawRowCollector();
        Excel::import($collector, $filePath);
        $rows      = $collector->rows;
        $ward      = Ward::find($wardId);
        $wardNum   = $ward?->ward_number;
        $validator = new SurveyValueValidator($lgId);

        // Pre-fetch all households for this ward — single query instead of one per row
        $existingMap = Household::where('ward_id', $wardId)
            ->select(['id', 'unique_code', 'sync_hash', 'sync_locked'])
            ->get()
            ->keyBy('unique_code');

        $new = $changed = $locked = $upToDate = $errors = [];
        $rowNum = 2; // data starts at row 3 in template (row 1=header, row 2=hints)

        foreach ($rows as $row) {
            $rowNum++;
            if ((string)($row['ward_number'] ?? '') !== (string)$wardNum) continue;

            // Accept both 'unique_code' and 'household_code' column names
            $uniqueCode = trim($row['unique_code'] ?? $row['household_code'] ?? '');
            if (!$uniqueCode) {
                $errors[] = ['row' => $row, 'reason' => 'unique_code / household_code खाली छ'];
                continue;
            }
            // Normalise so downstream helpers always see 'unique_code'
            $row['unique_code'] = $uniqueCode;

            $hash     = $this->hashRow($row);
            $existing = $existingMap->get($uniqueCode);

            if (!$existing) {
                $validationErrors = $validator->checkFields($row, $rowNum);
                if (!empty($validationErrors)) {
                    $errors[] = ['row' => $row, 'reason' => 'अमान्य विकल्प मानहरू', 'validation_errors' => $validationErrors];
                } else {
                    $new[] = array_merge($row, ['_hash' => $hash, '_action' => 'new']);
                }
            } elseif ($existing->sync_locked) {
                $locked[] = array_merge($row, ['_hash' => $hash, '_action' => 'locked', '_id' => $existing->id]);
            } elseif ($existing->sync_hash === $hash) {
                $upToDate[] = array_merge($row, ['_hash' => $hash, '_action' => 'up_to_date', '_id' => $existing->id]);
            } else {
                $validationErrors = $validator->checkFields($row, $rowNum);
                if (!empty($validationErrors)) {
                    $errors[] = ['row' => $row, 'reason' => 'अमान्य विकल्प मानहरू', 'validation_errors' => $validationErrors];
                } else {
                    $changed[] = array_merge($row, ['_hash' => $hash, '_action' => 'changed', '_id' => $existing->id, '_old_hash' => $existing->sync_hash]);
                }
            }
        }

        return compact('new', 'changed', 'locked', 'upToDate', 'errors');
    }

    private function writeHouseholds(array $diff, int $wardId, int $lgId, bool $forceLockedRecords): array
    {
        $imported = 0;
        $updated  = 0;
        $skipped  = 0;

        $toProcess = array_merge($diff['new'], $diff['changed']);
        if ($forceLockedRecords) {
            $toProcess = array_merge($toProcess, $diff['locked']);
        } else {
            $skipped += count($diff['locked']);
        }

        // Pre-fetch all existing households by ID — single query instead of one per row
        $existingIds = array_filter(array_column($toProcess, '_id'));
        $existingMap = $existingIds
            ? Household::whereIn('id', array_values($existingIds))->get()->keyBy('id')->all()
            : [];

        foreach ($toProcess as $row) {
            try {
                $data     = $this->rowToHouseholdData($row, $wardId, $lgId);
                $existing = isset($row['_id']) ? ($existingMap[$row['_id']] ?? null) : null;

                if ($existing) {
                    $existing->update(array_merge($data, [
                        'sync_source' => 'google_sheets',
                        'sync_hash'   => $row['_hash'],
                        'sync_locked' => false,
                    ]));
                    $updated++;
                } else {
                    Household::create(array_merge($data, [
                        'ward_id'       => $wardId,
                        'enumerator_id' => null,
                        'status'        => 'draft',
                        'sync_source'   => 'google_sheets',
                        'sync_hash'     => $row['_hash'],
                        'sync_locked'   => false,
                    ]));
                    $imported++;
                }
            } catch (\Throwable $e) {
                Log::warning('GS sync household row failed', ['code' => $row['unique_code'] ?? '?', 'err' => $e->getMessage()]);
                $skipped++;
            }
        }

        return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped];
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
        $collector = new RawRowCollector();
        Excel::import($collector, $filePath);
        $rows      = $collector->rows;
        $ward      = Ward::find($wardId);
        $wardNum   = $ward?->ward_number;
        $validator = new SurveyValueValidator($lgId);

        // Pre-fetch all institutions for this ward — single query instead of one per row
        $existingMap = Institution::where('ward_id', $wardId)
            ->select(['id', 'unique_code', 'sync_hash', 'sync_locked'])
            ->get()
            ->keyBy('unique_code');

        $new = $changed = $locked = $upToDate = $errors = [];
        $rowNum = 2;

        foreach ($rows as $row) {
            $rowNum++;
            if ((string)($row['ward_number'] ?? '') !== (string)$wardNum) continue;

            // Accept both 'unique_code' and 'institution_code' column names
            $uniqueCode = trim($row['unique_code'] ?? $row['institution_code'] ?? '');
            if (!$uniqueCode) {
                $errors[] = ['row' => $row, 'reason' => 'unique_code / institution_code खाली छ'];
                continue;
            }
            $row['unique_code'] = $uniqueCode;

            $hash     = $this->hashRow($row);
            $existing = $existingMap->get($uniqueCode);

            if (!$existing) {
                $validationErrors = $validator->checkFields($row, $rowNum);
                if (!empty($validationErrors)) {
                    $errors[] = ['row' => $row, 'reason' => 'अमान्य विकल्प मानहरू', 'validation_errors' => $validationErrors];
                } else {
                    $new[] = array_merge($row, ['_hash' => $hash, '_action' => 'new']);
                }
            } elseif ($existing->sync_locked) {
                $locked[] = array_merge($row, ['_hash' => $hash, '_action' => 'locked', '_id' => $existing->id]);
            } elseif ($existing->sync_hash === $hash) {
                $upToDate[] = array_merge($row, ['_hash' => $hash, '_action' => 'up_to_date', '_id' => $existing->id]);
            } else {
                $validationErrors = $validator->checkFields($row, $rowNum);
                if (!empty($validationErrors)) {
                    $errors[] = ['row' => $row, 'reason' => 'अमान्य विकल्प मानहरू', 'validation_errors' => $validationErrors];
                } else {
                    $changed[] = array_merge($row, ['_hash' => $hash, '_action' => 'changed', '_id' => $existing->id]);
                }
            }
        }

        return compact('new', 'changed', 'locked', 'upToDate', 'errors');
    }

    private function writeInstitutions(array $diff, int $wardId, int $lgId, bool $forceLockedRecords): array
    {
        $imported = 0; $updated = 0; $skipped = 0;
        $toProcess = array_merge($diff['new'], $diff['changed']);
        if ($forceLockedRecords) $toProcess = array_merge($toProcess, $diff['locked']);
        else $skipped += count($diff['locked']);

        // Pre-fetch all existing institutions by ID — single query instead of one per row
        $existingIds = array_filter(array_column($toProcess, '_id'));
        $existingMap = $existingIds
            ? Institution::whereIn('id', array_values($existingIds))->get()->keyBy('id')->all()
            : [];

        foreach ($toProcess as $row) {
            try {
                $existing = isset($row['_id']) ? ($existingMap[$row['_id']] ?? null) : null;
                $syncMeta = ['sync_source' => 'google_sheets', 'sync_hash' => $row['_hash'], 'sync_locked' => false];

                if ($existing) {
                    $existing->update(array_merge($this->rowToInstitutionData($row, $wardId, $lgId), $syncMeta));
                    $updated++;
                } else {
                    Institution::create(array_merge(
                        $this->rowToInstitutionData($row, $wardId, $lgId),
                        ['ward_id' => $wardId, 'status' => 'draft'],
                        $syncMeta
                    ));
                    $imported++;
                }
            } catch (\Throwable $e) {
                Log::warning('GS sync institution row failed', ['code' => $row['unique_code'] ?? '?', 'err' => $e->getMessage()]);
                $skipped++;
            }
        }

        return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Stable SHA-256 fingerprint of a row's key fields */
    private function hashRow(array $row): string
    {
        $exclude = ['_hash', '_action', '_id', '_old_hash'];
        $clean   = array_diff_key($row, array_flip($exclude));
        ksort($clean);
        return hash('sha256', json_encode($clean, JSON_UNESCAPED_UNICODE));
    }

    private function parseGps(array $row): array
    {
        $lat = $row['gps_lat'] ?? null;
        $lng = $row['gps_lng'] ?? null;

        if ($lat === null && $lng === null) {
            return [null, null];
        }

        if (!is_numeric($lat) || !is_numeric($lng)) {
            return [null, null];
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < 26.0 || $lat > 30.5 || $lng < 80.0 || $lng > 88.5) {
            return [null, null];
        }

        return [$lat, $lng];
    }

    private function rowToHouseholdData(array $row, int $wardId, int $lgId): array
    {
        [$gpsLat, $gpsLng] = $this->parseGps($row);
        return [
            'unique_code'        => $row['unique_code']        ?? null,
            'respondent_name'    => $row['respondent_name']    ?? null,
            'respondent_contact' => $row['respondent_contact'] ?? null,
            'total_members'      => $row['total_members']      ?? null,
            'gps_lat'            => $gpsLat,
            'gps_lng'            => $gpsLng,
            'gps_altitude'       => $row['gps_altitude']       ?? null,
            'survey_date'        => $row['survey_date']        ?? null,
            'remarks'            => $row['remarks']            ?? null,
        ];
    }

    private function rowToInstitutionData(array $row, int $wardId, int $lgId): array
    {
        [$gpsLat, $gpsLng] = $this->parseGps($row);
        return [
            'unique_code'         => $row['unique_code']         ?? null,
            'name_en'             => $row['name_en']             ?? null,
            'name_np'             => $row['name_np']             ?? null,
            'registration_number' => $row['registration_number'] ?? null,
            'gps_lat'             => $gpsLat,
            'gps_lng'             => $gpsLng,
            'respondent_name'     => $row['respondent_name']     ?? null,
            'respondent_contact'  => $row['respondent_contact']  ?? null,
            'survey_date'         => $row['survey_date']         ?? null,
        ];
    }
}
