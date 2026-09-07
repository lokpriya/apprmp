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
use App\Models\Settlement;
use App\Models\SurveyOption;
use App\Models\Ward;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class GoogleSheetsSyncService
{
    private int   $syncLgId       = 0;
    private array $unmappedValues = [];

    /**
     * Kobo choice-name → system survey_option value.
     * KoboToTemplate.gs copies raw Kobo choice codes; normalize them before DB storage.
     */
    private const KOBO_NORM = [
        'water_source' => [
            'Tap_house'      => 'tap_inside',   'Tap_public'   => 'tap_outside',
            'covered_well'   => 'well',          'open_well'    => 'well',
            'Jaruwa'         => 'tube_well',     'Tube_well'    => 'tube_well',
            'River'          => 'river',         'नदी/खोला'    => 'river',
            'Spring'         => 'spring',        'मूल/झरना'    => 'spring',
            'Rain_water'     => 'rain',          'Rainwater'    => 'rain',
            'वर्षाको पानी'  => 'rain',
            'Jar_water'      => 'jar_water',     'जार पानी'    => 'jar_water',
            'Well'           => 'well',          'इनार/कुवा'   => 'well',
        ],
        'toilet_type' => [
            'Flush'        => 'flush',           'Water_seal'   => 'flush',
            'Simple_pit'   => 'pit',             'Pit'          => 'pit',
            'Eco_san'      => 'eco_san',
            'no_toilet'    => 'none',            'No_toilet'    => 'none',
        ],
        'sanitation_type' => [
            'Standard_septictank'     => 'flush_septic',
            'Standard_publicdrainage' => 'flush_piped',
            'normal'                  => 'simple_pit',   'Normal'      => 'simple_pit',
            'no_toilet'               => 'open',         'No_toilet'   => 'open',
            'Open_field'              => 'open',
            'Simple_pit'              => 'simple_pit',
            'Improved_pit'            => 'improved_pit', 'VIP'         => 'improved_pit',
        ],
        'electricity_source' => [
            'National_grid' => 'national_grid',  'Grid'         => 'national_grid',
            'Solar'         => 'solar',          'Generator'    => 'generator',
            'Micro_hydro'   => 'micro_hydro',    'Biogas'       => 'biogas',
        ],
        'cooking_fuel' => [
            'LPG'           => 'lpg',            'Biogas'       => 'biogas',
            'Firewood'      => 'firewood',       'Wood'         => 'firewood',
            'Kerosene'      => 'kerosene',       'Electricity'  => 'electricity',
            'Improved_cook' => 'improved_cook',  'Dung'         => 'other',
        ],
        'waste_management' => [
            'Organized_disposal' => 'govt_collection',
            'Organized'          => 'govt_collection',
            'Burned'             => 'burning',   'Burning'      => 'burning',
            'Compost'            => 'composting','Composting'   => 'composting',
            'Open_dumping'       => 'dumping',   'Open_dump'    => 'dumping',
            'Pit_disposal'       => 'pit',
        ],
        'internet_type' => [
            'Broadband'   => 'broadband',        'Mobile_data'  => 'mobile_data',
            'Wifi'        => 'wifi',             'WiFi'         => 'wifi',
            'Dish'        => 'dish',             'VSAT'         => 'dish',
        ],
        'roof_type' => [
            'CGI_sheet'     => 'tin',    'Tin'          => 'tin',
            'Metal_sheet'   => 'tin',    'Iron_sheet'   => 'tin',
            'RCC'           => 'rcc',    'Concrete'     => 'rcc',  'rcc_concrete' => 'rcc',
            'Tile'          => 'tile',   'Clay_tile'    => 'tile', 'Wood_tile'    => 'tile',
            'Thatch'        => 'thatch', 'Straw'        => 'thatch','Grass'       => 'thatch',
            'Slate'         => 'slate',  'Stone_slate'  => 'slate',
        ],
        'wall_type' => [
            'Brick_cement'        => 'brick_cement',
            'Burnt_brick_cement'  => 'brick_cement',
            'Brick_mud'           => 'brick_mud',
            'Burnt_brick_mud'     => 'brick_mud',    'Adobe'      => 'brick_mud',
            'Stone_cement'        => 'stone_cement',
            'Stone_mud'           => 'stone_mud',    'Mud_stone'  => 'stone_mud',
            'RCC'                 => 'rcc',          'Concrete'   => 'rcc',
            'Bamboo'              => 'bamboo',       'Bamboo_mud' => 'bamboo',
            'Wood'                => 'wood',         'Timber'     => 'wood',
        ],
        'foundation_type' => [
            'RCC'          => 'rcc',    'Concrete'     => 'rcc',
            'Brick_cement' => 'brick_cement',
            'Stone_cement' => 'stone_cement',
            'Stone_mud'    => 'stone_mud',   'Mud'     => 'stone_mud',
        ],
        'house_condition' => [
            'Good'        => 'good',    'Average'  => 'average',  'Fair'       => 'average',
            'Poor'        => 'poor',    'Damaged'  => 'damaged',  'Dilapidated'=> 'damaged',
        ],
        'ownership_type' => [
            'Owned'         => 'owned',   'Rented'       => 'rented',
            'Institutional' => 'institutional',           'Squatter' => 'squatter',
        ],
        'ethnicity' => [
            'माझी'    => 'majhi',   'जोगी'   => 'jogi',
            'मधेसी'   => 'madhesi', 'चौधरी'  => 'chaudhary',
            // Common Nepali label → code mappings (Kobo forms that output labels)
            'ब्राह्मण (पहाड)'  => 'brahmin_hill',
            'ब्राह्मण (तराई)'  => 'brahmin_tarai',
            'क्षेत्री'         => 'chhetri',
            'मगर'              => 'magar',
            'तामाङ'            => 'tamang',
            'नेवार'            => 'newar',
            'राई'              => 'rai',
            'लिम्बू'           => 'limbu',
            'थारू'             => 'tharu',
            'यादव'             => 'yadav',
            'कामी'             => 'kami',
            'दमाई'             => 'damai',
            'सार्की'           => 'sarki',
        ],
        'irrigation_source' => [
            'लिफ्ट/ पम्प सेट' => 'lift_pump',
            'लिफ्ट/पम्प सेट'  => 'lift_pump',
            'Lift'             => 'lift_pump',
            'Lift_pump'        => 'lift_pump',
            'नहर'              => 'Canal',
            'ट्यूबवेल'         => 'Borewell',
            'नदी/खोला'         => 'River',
            'पोखरी'            => 'Pond',
            'वर्षा'            => 'Rainfall',
            'मूल'              => 'Spring',
        ],
    ];

    /**
     * Canonicalize an enum field value via SurveyOption lookup.
     * Applies Kobo normalization first, then survey_options registry.
     * Tracks values that are not found in the registry so they can be surfaced in preview/apply results.
     */
    private function opt(string $category, mixed $raw): ?string
    {
        $value = trim((string)($raw ?? ''));
        if ($value === '') return null;

        // Normalize raw Kobo choice names to system survey_option values
        $value = self::KOBO_NORM[$category][$value] ?? $value;

        $lgId = $this->syncLgId ?: null;
        if (!SurveyOption::isKnown($category, $value, $lgId)) {
            $this->unmappedValues[$category][$value] = ($this->unmappedValues[$category][$value] ?? 0) + 1;
        }
        return SurveyOption::canonicalize($category, $value, $lgId);
    }

    // ── CSV sheet download (gviz/tq — no XLSX, no temp file, no library) ────────

    private function headingToKey(string $h): string
    {
        $s = strtolower(trim((string)$h));
        // Strip leading "01|", "02|", "123|" style Kobo prefixes
        $s = preg_replace('/^\d+\|/', '', $s);
        $s = preg_replace('/[\s\-\/\\\\\.]+/', '_', $s);
        $s = preg_replace('/[^a-z0-9_]/', '', $s);
        return trim($s, '_');
    }

    /** Parse a raw CSV body string into an array of keyed rows. */
    private function parseCsvBody(string $body): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $body);
        rewind($stream);

        $headers = null;
        $rows    = [];
        while (($cols = fgetcsv($stream)) !== false) {
            if ($headers === null) {
                $headers = array_map([$this, 'headingToKey'], $cols);
                continue;
            }
            if (empty(array_filter($cols, fn($v) => $v !== '' && $v !== null))) continue;
            $cols   = array_pad($cols, count($headers), '');
            $rows[] = array_combine($headers, array_slice($cols, 0, count($headers)));
        }
        fclose($stream);
        return $rows;
    }

    /**
     * Download multiple sheets in parallel using Http::pool().
     * Falls back to sequential retry for any sheet that fails.
     */
    private function downloadSheetsParallel(string $sheetId, array $sheetNames): array
    {
        $baseUrl = 'https://docs.google.com/spreadsheets/d/'
                 . rawurlencode($sheetId)
                 . '/gviz/tq?tqx=out:csv&sheet=';

        $ua    = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $multi = curl_multi_init();
        $handles = [];

        foreach ($sheetNames as $name) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $baseUrl . rawurlencode($name),
                CURLOPT_USERAGENT      => $ua,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$name] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi);
        } while ($running > 0);

        $result   = [];
        $failures = [];

        foreach ($sheetNames as $name) {
            $ch    = $handles[$name];
            $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $body  = curl_multi_getcontent($ch);
            $errNo = curl_errno($ch);
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);

            if ($errNo || $code !== 200 || $body === false) {
                $failures[] = $name;
            } else {
                $body = trim($body);
                $result[$name] = $body === '' ? [] : $this->parseCsvBody($body);
            }
        }

        curl_multi_close($multi);

        foreach ($failures as $name) {
            $result[$name] = $this->downloadSheetCsvRetry($baseUrl . rawurlencode($name), $name);
        }

        return $result;
    }

    /** Single-sheet download with up to 3 retry attempts. */
    private function downloadSheetCsvRetry(string $url, string $sheetName): array
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_USERAGENT      => $ua,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            $body  = curl_exec($ch);
            $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errNo = curl_errno($ch);
            curl_close($ch);

            if (!$errNo && $code === 200 && $body !== false) {
                $body = trim($body);
                return $body === '' ? [] : $this->parseCsvBody($body);
            }
            if ($code === 404) break;
            if ($attempt < 3) usleep(1_500_000);
        }
        throw new \RuntimeException(
            "Sheet '{$sheetName}' डाउनलोड गर्न सकिएन (3 attempts). "
            . "Sheet \"Anyone with link can view\" मा सेट गर्नुभएको छ?"
        );
    }

    /** @deprecated Use downloadSheetsParallel(); kept for external callers. */
    private function downloadSheetCsv(string $sheetId, string $sheetName): array
    {
        $url = 'https://docs.google.com/spreadsheets/d/'
             . rawurlencode($sheetId)
             . '/gviz/tq?tqx=out:csv&sheet=' . rawurlencode($sheetName);
        $response = Http::connectTimeout(8)->timeout(20)->get($url);
        if (!$response->successful()) {
            throw new \RuntimeException(
                "Sheet '{$sheetName}' डाउनलोड गर्न सकिएन (HTTP {$response->status()}). "
                . "Sheet \"Anyone with link can view\" मा सेट गर्नुभएको छ?"
            );
        }
        $body = trim($response->body());
        return $body === '' ? [] : $this->parseCsvBody($body);
    }

    // Keep downloadSheet() for any future/external callers but sync no longer uses it
    public function downloadSheet(string $sheetId): string
    {
        $url      = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=xlsx";
        $response = Http::connectTimeout(8)->timeout(20)->get($url);
        if (!$response->successful()) {
            throw new \RuntimeException("Sheet डाउनलोड गर्न सकिएन (HTTP {$response->status()}).");
        }
        $filename    = 'gs_sync_' . uniqid() . '.xlsx';
        $storagePath = storage_path('framework/cache/' . $filename);
        file_put_contents($storagePath, $response->body());
        return $storagePath;
    }

    // ── Household sync ────────────────────────────────────────────────────────

    public function previewHouseholds(string $sheetId, int $wardId, int $lgId): array
    {
        $diff    = $this->diffHouseholds($sheetId, $wardId, $lgId);
        $diffKey = 'gs_diff_hh_' . uniqid('', true);
        Cache::put($diffKey, $diff, now()->addMinutes(15));
        return array_merge($diff, ['_diff_key' => $diffKey]);
    }

    public function applyHouseholds(string $sheetId, int $wardId, int $lgId, bool $forceLockedRecords = false, ?string $diffKey = null): array
    {
        $diff = ($diffKey && Cache::has($diffKey))
            ? Cache::pull($diffKey)
            : $this->diffHouseholds($sheetId, $wardId, $lgId);
        return $this->writeHouseholds($diff, $wardId, $lgId, $forceLockedRecords);
    }

    /**
     * Dry-run scan of opt() fields for one household row + its related sub-rows.
     * Populates $this->unmappedValues without touching the DB.
     */
    private function scanRowForUnmapped(array $related): void
    {
        if ($h = $related['housingRow'] ?? null) {
            foreach ([
                'house_land_type', 'land_measurement_unit', 'house_type', 'house_purpose',
                'location_house_built', 'roof_type', 'wall_type', 'foundation_type',
                'ownership_type', 'house_condition',
            ] as $cat) {
                $this->opt($cat, $h[$cat] ?? null);
            }
        }
        if ($u = $related['utilitiesRow'] ?? null) {
            foreach ([
                'water_source', 'toilet_type', 'sanitation_type', 'electricity_source',
                'cooking_fuel', 'internet_type', 'handwashing',
            ] as $cat) {
                $this->opt($cat, $u[$cat] ?? null);
            }
            $this->opt('waste_management', $u['waste_management'] ?? null);
        }
        foreach ($related['memberRows'] ?? [] as $m) {
            foreach ([
                'relation_to_head', 'ethnicity', 'religion', 'marital_status',
                'education_level', 'occupation_type', 'employment_status',
                'disability_type', 'residence_status',
            ] as $cat) {
                $this->opt($cat, $m[$cat] ?? null);
            }
            $this->opt('language', $m['language'] ?? null);
        }
    }

    private function diffHouseholds(string $sheetId, int $wardId, int $lgId): array
    {
        $this->syncLgId       = $lgId;
        $this->unmappedValues = [];

        $sheets  = $this->loadAllSheetsAsCsv($sheetId);
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
        $disasterIdx     = $this->indexBy($sheets['disaster'],    'household_code');

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
            $disasterRow     = $disasterIdx[$codeKey]    ?? null;

            $hash = $this->hashAllSheets(
                $row, $housingRow, $utilitiesRow, $economicRow,
                $agricultureRow, $memberRows, $livestockRows, $landParcelRows, $disasterRow
            );
            $existing = $existingMap->get($uniqueCode);

            // Attach sub-sheet data (stripped before sending to frontend)
            $row['_related'] = compact('housingRow', 'utilitiesRow', 'economicRow', 'agricultureRow', 'memberRows', 'livestockRows', 'landParcelRows', 'disasterRow');

            // Scan opt() fields now so preview surfaces unmapped values before apply
            $this->scanRowForUnmapped($row['_related']);

            // Completeness snapshot — visible in preview
            $row['_completeness'] = [
                'members'     => count($memberRows),
                'housing'     => !empty($housingRow),
                'utilities'   => !empty($utilitiesRow),
                'economic'    => !empty($economicRow),
                'agriculture' => !empty($agricultureRow),
                'livestock'   => count($livestockRows),
                'landParcels' => count($landParcelRows),
                'disaster'    => !empty($disasterRow),
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
                'disaster_in_file'  => count($sheets['disaster']),
            ],
        ];

        $unmappedValues = $this->unmappedValues;
        return compact('new', 'changed', 'locked', 'upToDate', 'errors', 'debug', 'unmappedValues');
    }

    private function writeHouseholds(array $diff, int $wardId, int $lgId, bool $forceLockedRecords): array
    {
        $this->syncLgId       = $lgId;
        $this->unmappedValues = [];
        $imported   = 0;
        $updated    = 0;
        $skipped    = 0;
        $allErrors  = [];
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
            $outcome = null;
            try {
                DB::transaction(function () use ($row, $wardId, $lgId, $existingMap, &$outcome) {
                    $data     = $this->rowToHouseholdData($row, $wardId, $lgId);
                    $existing = isset($row['_id']) ? ($existingMap[$row['_id']] ?? null) : null;

                    $syncMeta = [
                        'sync_source' => 'google_sheets',
                        'sync_hash'   => $row['_hash'],
                        'sync_locked' => false,
                        'status'      => 'submitted',
                    ];

                    if ($existing) {
                        $existing->update(array_merge($data, $syncMeta));
                        $household = $existing;
                        $outcome   = 'updated';
                    } else {
                        // Use updateOrCreate so stale cached diffs or duplicate GAS rows
                        // don't cause a unique_code constraint violation.
                        $household = Household::updateOrCreate(
                            ['unique_code' => $data['unique_code']],
                            array_merge($data, ['ward_id' => $wardId, 'enumerator_id' => null], $syncMeta)
                        );
                        $outcome = $household->wasRecentlyCreated ? 'imported' : 'updated';
                    }

                    // Populate survey_data.social from Households sheet columns
                    $socialPatch = array_filter([
                        'family_head' => trim($row['family_head'] ?? '') ?: null,
                        'family_type' => $this->opt('family_type', $row['family_type'] ?? null),
                        'male_num'    => is_numeric($row['total_male']   ?? '') ? (int)$row['total_male']   : null,
                        'female_num'  => is_numeric($row['total_female'] ?? '') ? (int)$row['total_female'] : null,
                        'other_num'   => is_numeric($row['total_other']  ?? '') ? (int)$row['total_other']  : null,
                    ], fn($v) => $v !== null && $v !== '');
                    if (!empty($socialPatch)) {
                        $sd = $household->survey_data ?? [];
                        $sd['social'] = array_merge($sd['social'] ?? [], $socialPatch);
                        $household->update(['survey_data' => $sd]);
                    }

                    $this->syncRelated($household, $row['_related'] ?? []);
                });

                if ($outcome === 'imported') $imported++;
                else                         $updated++;

            } catch (\Throwable $e) {
                Log::warning('GS sync household row failed', [
                    'code' => $row['unique_code'] ?? '?',
                    'err'  => $e->getMessage(),
                ]);
                $allErrors[] = ['code' => $row['unique_code'] ?? '?', 'error' => $e->getMessage()];
                $firstError ??= $e->getMessage();
                $skipped++;
            }
        }

        $this->logSyncAudit('household', $wardId, $imported, $updated, $skipped);

        return [
            'imported'       => $imported,
            'updated'        => $updated,
            'skipped'        => $skipped,
            'firstError'     => $firstError,
            'errors'         => $allErrors,
            'unmappedValues' => $this->unmappedValues,
        ];
    }

    // ── Related table sync ────────────────────────────────────────────────────

    private function syncRelated(Household $household, array $related): void
    {
        // Read survey_data once; accumulate all changes; write once at the end
        $surveyData        = $household->survey_data ?? [];
        $surveyDataChanged = false;

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

            $decisionsMap = [
                'decisioninHHWork'     => trim($economicRow['decision_household_work']     ?? ''),
                'decisioninProperty'   => trim($economicRow['decision_property']           ?? ''),
                'decisioninCCommittee' => trim($economicRow['decision_consumer_committee'] ?? ''),
                'decisioninSMC'        => trim($economicRow['decision_school_mgmt']        ?? ''),
                'decisioninBusiness'   => trim($economicRow['decision_business']           ?? ''),
                'bank_account_owner'   => trim($economicRow['decision_bank_account']       ?? ''),
                'has_bank_account'     => $this->parseBoolYesNo($economicRow['has_bank_account'] ?? null) ?? '',
            ];
            $decisionData = array_filter($decisionsMap, fn($v) => $v !== '');

            $occupationData = array_filter([
                'has_foreign_employment'     => trim($economicRow['has_foreign_employment']     ?? '') ?: null,
                'foreign_employment_country' => trim($economicRow['foreign_employment_country'] ?? '') ?: null,
            ]);

            // Loan and insurance fields → survey_data.social
            $loanSources = $this->parseJsonArray($economicRow['loan_source'] ?? null) ?? [];
            $socialEconPatch = array_filter([
                'bankloan'         => !empty($loanSources) ? 'yes' : null,
                'loansource'       => !empty($loanSources) ? $loanSources : null,
                'health_insurance' => $this->parseBoolYesNo($economicRow['has_insurance'] ?? null),
            ], fn($v) => $v !== null && $v !== '');

            if ($decisionData) {
                $surveyData['decisions'] = array_merge($surveyData['decisions'] ?? [], $decisionData);
                $surveyDataChanged = true;
            }
            if ($occupationData) {
                $surveyData['occupation'] = array_merge($surveyData['occupation'] ?? [], $occupationData);
                $surveyDataChanged = true;
            }
            if ($socialEconPatch) {
                $surveyData['social'] = array_merge($surveyData['social'] ?? [], $socialEconPatch);
                $surveyDataChanged = true;
            }
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

            $beeFish = $this->extractBeeFishData($livestockRows);
            if (!empty($beeFish)) {
                $surveyData['beeFishSilk'] = array_merge($surveyData['beeFishSilk'] ?? [], $beeFish);
                $surveyDataChanged = true;
            }
        }

        if (!empty($related['memberRows'])) {
            $this->syncMembers($household, $related['memberRows']);

            // Derive household-level social fields from the head-of-household member
            $headMember = $household->members()->where('relation_to_head', 'self')->first()
                       ?? $household->members()->where('is_household_head', true)->first();
            if ($headMember) {
                $headSocial = array_filter([
                    'ethnicity'               => $headMember->ethnicity       ?: null,
                    'religion'                => $headMember->religion        ?: null,
                    'mother_tongue'           => $headMember->mother_tongue   ?: null,
                    'com_language'            => $headMember->mother_tongue   ?: null,
                    'major_family_occupation' => $headMember->occupation_type ?: null,
                ], fn($v) => $v !== null && $v !== '');
                if (!empty($headSocial)) {
                    $surveyData['social'] = array_merge($surveyData['social'] ?? [], $headSocial);
                    $surveyDataChanged = true;
                }
            }
        }

        // Disaster sheet → household_disaster table + survey_data.social.death
        if ($disasterRow = $related['disasterRow'] ?? null) {
            $household->disaster()->updateOrCreate(
                ['household_id' => $household->id],
                $this->rowToDisasterData($disasterRow)
            );
            $deathVal = $this->parseBoolYesNo($disasterRow['has_death_this_year'] ?? null);
            if ($deathVal !== null) {
                $surveyData['social']['death'] = $deathVal;
                $surveyDataChanged = true;
            }
        }

        $landParcelRows = $related['landParcelRows'] ?? [];
        $rawFlag = $housingRow ? trim($housingRow['has_land_in_municipality'] ?? '') : '';
        if ($rawFlag !== '' || !empty($landParcelRows)) {
            $hasLand = $rawFlag !== ''
                ? ($this->parseBool($rawFlag) ? 'yes' : 'no')
                : 'yes';
            $parcels = array_values(array_filter(
                array_map(fn($r) => $this->rowToLandParcelData($r), $landParcelRows)
            ));
            $surveyData['hasLandInMunicipality'] = $hasLand;
            $surveyData['landParcels']            = $parcels;
            $surveyDataChanged = true;
        }

        if ($surveyDataChanged) {
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
        $diff    = $this->diffInstitutions($sheetId, $wardId, $lgId);
        $diffKey = 'gs_diff_in_' . uniqid('', true);
        Cache::put($diffKey, $diff, now()->addMinutes(15));
        return array_merge($diff, ['_diff_key' => $diffKey]);
    }

    public function applyInstitutions(string $sheetId, int $wardId, int $lgId, bool $forceLockedRecords = false, ?string $diffKey = null): array
    {
        $diff = ($diffKey && Cache::has($diffKey))
            ? Cache::pull($diffKey)
            : $this->diffInstitutions($sheetId, $wardId, $lgId);
        return $this->writeInstitutions($diff, $wardId, $lgId, $forceLockedRecords);
    }

    private function diffInstitutions(string $sheetId, int $wardId, int $lgId): array
    {
        $this->syncLgId       = $lgId;
        $this->unmappedValues = [];

        $sheets  = $this->loadAllInstitutionSheetsAsCsv($sheetId);
        $ward    = Ward::find($wardId);
        $wardNum = $ward?->ward_number;

        $infraIdx     = $this->indexBy($sheets['infrastructure'],  'institution_code');
        $healthIdx    = $this->indexBy($sheets['health'],          'institution_code');
        $staffIdx     = $this->groupBy($sheets['staff'],           'institution_code');
        $educationIdx = $this->groupBy($sheets['education'],       'institution_code');
        $servicesIdx  = $this->groupBy($sheets['services'],        'institution_code');
        $teachersIdx  = $this->groupBy($sheets['teachers'],        'institution_code');
        $typesIdx     = $this->indexBy($sheets['typespecific'] ?? [], 'institution_code');

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

            $infraRow    = $infraIdx[$codeKey]     ?? null;
            $healthRow   = $healthIdx[$codeKey]    ?? null;
            $staffRows   = $staffIdx[$codeKey]     ?? [];
            $educRows    = $educationIdx[$codeKey]  ?? [];
            $serviceRows = $servicesIdx[$codeKey]  ?? [];
            $teacherRows = $teachersIdx[$codeKey]  ?? [];
            $tsRow       = $typesIdx[$codeKey]     ?? null;

            $hash     = $this->hashInstitutionAllSheets($row, $infraRow, $healthRow, $staffRows, $educRows, $serviceRows, $teacherRows, $tsRow);
            $existing = $existingMap->get($uniqueCode);

            $row['_related'] = compact('infraRow', 'healthRow', 'staffRows', 'educRows', 'serviceRows', 'teacherRows', 'tsRow');

            $row['_completeness'] = [
                'infrastructure' => !empty($infraRow),
                'health'         => !empty($healthRow),
                'staff'          => count($staffRows),
                'education'      => count($educRows),
                'services'       => count($serviceRows),
                'teachers'       => count($teacherRows),
                'typespecific'   => !empty($tsRow),
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

        $unmappedValues = $this->unmappedValues;
        return compact('new', 'changed', 'locked', 'upToDate', 'errors', 'debug', 'unmappedValues');
    }

    private function writeInstitutions(array $diff, int $wardId, int $lgId, bool $forceLockedRecords): array
    {
        $this->syncLgId       = $lgId;
        $this->unmappedValues = [];
        $imported   = 0;
        $updated    = 0;
        $skipped    = 0;
        $allErrors  = [];
        $firstError = null;

        $toProcess = array_merge($diff['new'], $diff['changed']);
        if ($forceLockedRecords) $toProcess = array_merge($toProcess, $diff['locked']);
        else $skipped += count($diff['locked']);

        $existingIds = array_filter(array_column($toProcess, '_id'));
        $existingMap = $existingIds
            ? Institution::whereIn('id', array_values($existingIds))->get()->keyBy('id')->all()
            : [];

        foreach ($toProcess as $row) {
            $outcome = null;
            try {
                DB::transaction(function () use ($row, $wardId, $lgId, $existingMap, &$outcome) {
                    $data     = $this->rowToInstitutionData($row, $wardId, $lgId);
                    $existing = isset($row['_id']) ? ($existingMap[$row['_id']] ?? null) : null;

                    $syncMeta = [
                        'sync_source' => 'google_sheets',
                        'sync_hash'   => $row['_hash'],
                        'sync_locked' => false,
                        'status'      => 'submitted',
                    ];

                    if ($existing) {
                        $existing->update(array_merge($data, $syncMeta));
                        $institution = $existing;
                        $outcome     = 'updated';
                    } else {
                        $institution = Institution::create(array_merge($data, [
                            'ward_id'       => $wardId,
                            'enumerator_id' => null,
                        ], $syncMeta));
                        $outcome = 'imported';
                    }

                    $this->syncInstitutionRelated($institution, $row['_related'] ?? []);
                });

                if ($outcome === 'imported') $imported++;
                else                         $updated++;

            } catch (\Throwable $e) {
                Log::warning('GS sync institution row failed', [
                    'code' => $row['unique_code'] ?? '?',
                    'err'  => $e->getMessage(),
                ]);
                $allErrors[] = ['code' => $row['unique_code'] ?? '?', 'error' => $e->getMessage()];
                $firstError ??= $e->getMessage();
                $skipped++;
            }
        }

        $this->logSyncAudit('institution', $wardId, $imported, $updated, $skipped);

        return [
            'imported'       => $imported,
            'updated'        => $updated,
            'skipped'        => $skipped,
            'firstError'     => $firstError,
            'errors'         => $allErrors,
            'unmappedValues' => $this->unmappedValues,
        ];
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
            $existing   = $institution->education()->get()->keyBy('grade');
            $seenGrades = [];
            foreach ($educRows as $row) {
                $grade = trim($row['grade'] ?? '');
                if (!$grade) continue;
                $seenGrades[$grade] = true;
                $data = $this->rowToEducationData($row);
                $institution->education()->updateOrCreate(['grade' => $grade], $data);
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

        // TypeSpecific: merge into survey_data.typespecific JSON
        if ($tsRow = $related['tsRow'] ?? null) {
            $ts = $this->rowToTypespecificData($tsRow);
            if (!empty($ts)) {
                $surveyData = $institution->survey_data ?? [];
                $surveyData['typespecific'] = array_merge($surveyData['typespecific'] ?? [], $ts);
                $institution->update(['survey_data' => $surveyData]);
            }
        }
    }

    // ── Multi-sheet CSV loading ───────────────────────────────────────────────

    private function loadAllSheetsAsCsv(string $sheetId): array
    {
        $raw = $this->downloadSheetsParallel($sheetId, [
            'Households', 'Members', 'Housing', 'Utilities',
            'Economic', 'Agriculture', 'Livestock', 'LandParcels', 'Disaster',
        ]);
        return [
            'households'  => $raw['Households'],
            'members'     => $raw['Members'],
            'housing'     => $raw['Housing'],
            'utilities'   => $raw['Utilities'],
            'economic'    => $raw['Economic'],
            'agriculture' => $raw['Agriculture'],
            'livestock'   => $raw['Livestock'],
            'landParcels' => $raw['LandParcels'],
            'disaster'    => $raw['Disaster'] ?? [],
        ];
    }

    private function loadAllInstitutionSheetsAsCsv(string $sheetId): array
    {
        $raw = $this->downloadSheetsParallel($sheetId, [
            'Institutions', 'Infrastructure', 'Staff', 'Education',
            'Health', 'Services', 'Teachers', 'TypeSpecific',
        ]);
        return [
            'institutions'   => $raw['Institutions'],
            'infrastructure' => $raw['Infrastructure'],
            'staff'          => $raw['Staff'],
            'education'      => $raw['Education'],
            'health'         => $raw['Health'],
            'services'       => $raw['Services'],
            'teachers'       => $raw['Teachers'],
            'typespecific'   => $raw['TypeSpecific'],
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

    /** Combined hash across all 9 sheets for a single household */
    private function hashAllSheets(
        array  $hhRow,
        ?array $housingRow,
        ?array $utilitiesRow,
        ?array $economicRow,
        ?array $agricultureRow,
        array  $memberRows,
        array  $livestockRows,
        array  $landParcelRows = [],
        ?array $disasterRow = null
    ): string {
        usort($memberRows,    fn($a, $b) => strcmp(
            strtolower(trim($a['full_name'] ?? '')),
            strtolower(trim($b['full_name'] ?? ''))
        ));
        usort($livestockRows, fn($a, $b) => strcmp(
            strtolower(trim($a['livestock_type'] ?? '')),
            strtolower(trim($b['livestock_type'] ?? ''))
        ));
        $combined = compact('hhRow', 'housingRow', 'utilitiesRow', 'economicRow', 'agricultureRow', 'memberRows', 'livestockRows', 'landParcelRows', 'disasterRow');
        return hash('sha256', json_encode($combined, JSON_UNESCAPED_UNICODE));
    }

    private function hashInstitutionAllSheets(
        array  $instRow,
        ?array $infraRow,
        ?array $healthRow,
        array  $staffRows,
        array  $educRows,
        array  $serviceRows,
        array  $teacherRows,
        ?array $tsRow = null
    ): string {
        usort($staffRows,    fn($a, $b) => strcmp($a['staff_category'] ?? '', $b['staff_category'] ?? ''));
        usort($educRows,     fn($a, $b) => strcmp($a['grade'] ?? '',          $b['grade'] ?? ''));
        usort($serviceRows,  fn($a, $b) => strcmp($a['service_name'] ?? '',   $b['service_name'] ?? ''));
        usort($teacherRows,  fn($a, $b) => strcmp($a['full_name'] ?? '',      $b['full_name'] ?? ''));
        $combined = compact('instRow', 'infraRow', 'healthRow', 'staffRows', 'educRows', 'serviceRows', 'teacherRows', 'tsRow');
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
            'house_land_type'       => $this->opt('house_land_type',       $row['house_land_type']       ?? null),
            'land_measurement_unit' => $this->opt('land_measurement_unit', $row['land_measurement_unit'] ?? null),
            'house_type'            => $this->opt('house_type',            $row['house_type']            ?? null),
            'house_purpose'         => $this->opt('house_purpose',         $row['house_purpose']         ?? null),
            'location_house_built'  => $this->opt('location_house_built',  $row['location_house_built']  ?? null),
            'roof_type'             => $this->opt('roof_type',             $row['roof_type']             ?? null),
            'wall_type'             => $this->opt('wall_type',             $row['wall_type']             ?? null),
            'foundation_type'       => $this->opt('foundation_type',       $row['foundation_type']       ?? null),
            'ownership_type'        => $this->opt('ownership_type',        $row['ownership_type']        ?? null),
            'rent_amount'           => is_numeric($row['rent_amount']          ?? '') ? (int)$row['rent_amount']           : null,
            'distance_to_mainroad'  => is_numeric($row['distance_to_mainroad'] ?? '') ? (int)$row['distance_to_mainroad']  : null,
            'distance_to_school'    => is_numeric($row['distance_to_school']   ?? '') ? (int)$row['distance_to_school']    : null,
            'total_rooms'           => is_numeric($row['total_rooms']          ?? '') ? (int)$row['total_rooms']           : null,
            'total_floors'          => is_numeric($row['total_floors']         ?? '') ? (int)$row['total_floors']          : null,
            'has_building_permit'   => $this->parseBool($row['has_building_permit'] ?? null) ?? false,
            'construction_year'     => is_numeric($row['construction_year']    ?? '') ? (int)$row['construction_year']     : null,
            'house_condition'       => $this->opt('house_condition',       $row['house_condition']       ?? null),
            'is_at_risk'            => $this->parseBool($row['is_at_risk'] ?? null) ?? false,
            'risk_types'            => $this->parseJsonArray($row['disaster_type'] ?? null),
            'has_land_in_municipality' => $this->parseBoolYesNo($row['has_land_in_municipality'] ?? null),
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
            'water_source'              => $this->opt('water_source',      $row['water_source']           ?? null),
            'water_distance_minutes'    => is_numeric($row['water_distance_minutes'] ?? '') ? (int)$row['water_distance_minutes'] : null,
            'uses_firewood'             => $this->parseBoolYesNo($row['uses_firewood'] ?? null),
            'firewood_source'           => $this->parseJsonArray($row['firewood_source'] ?? null),
            'cooking_stove'             => $this->opt('cooking_stove',     $row['cooking_stove']          ?? null),
            'cooking_fuel_arr'          => $this->parseJsonArray($row['cooking_fuel_arr'] ?? null),
            'cooking_fuel'              => $this->opt('cooking_fuel',      $row['cooking_fuel']           ?? null),
            'has_biogas'                => $this->parseBool($row['has_biogas']    ?? null) ?? false,
            'has_alt_electricity'       => $this->parseBoolYesNo($row['has_alt_electricity'] ?? null),
            'alt_electricity_sources'   => $this->parseJsonArray($row['alt_electricity_source'] ?? null),
            'electricity_source'        => $this->opt('electricity_source',$row['electricity_source']     ?? null),
            'monthly_electricity_units' => is_numeric($row['monthly_electricity_units'] ?? '') ? (int)$row['monthly_electricity_units'] : null,
            'solid_waste_management'    => $this->opt('waste_management',  $row['waste_management'] ?? null),
            'has_toilet'                => $this->parseBool($row['has_toilet']    ?? null) ?? false,
            'toilet_type'               => $this->opt('toilet_type',       $row['toilet_type']            ?? null),
            'sanitation_type'           => $this->opt('sanitation_type',   $row['sanitation_type']        ?? null),
            'handwashing'               => $this->opt('handwashing',       $row['handwashing']            ?? null),
            'access_to_facilities'      => $this->parseJsonArray($row['facilities'] ?? null),
            'has_internet'              => $this->parseBool($row['has_internet']  ?? null) ?? false,
            'internet_type'             => $this->opt('internet_type',     $row['internet_type']          ?? null),
            // vehicles_bike/car/tempo/tractor/other → vehicles array; has_own_vehicle → vehicle (varchar Yes/No)
            'vehicle'                   => $this->parseBoolYesNo($row['has_own_vehicle'] ?? null),
            'vehicles'                  => $this->parseVehiclesArray($row),
        ];
    }

    private function rowToEconomicData(array $row): array
    {
        return [
            'primary_income_source'     => trim($row['income_source']             ?? '') ?: null,
            'annual_income_range'       => trim($row['annual_income_range']       ?? '') ?: null,
            'annual_expenditure_range'  => trim($row['annual_expenditure_range']  ?? '') ?: null,
            'remittance_amount'         => is_numeric($row['remittance_amount']   ?? '') ? (int)$row['remittance_amount'] : null,
            'loan_sources'              => $this->parseJsonArray($row['loan_source'] ?? null),
            'total_loan_amount'         => is_numeric($row['total_loan_amount']   ?? '') ? (int)$row['total_loan_amount'] : null,
            'has_bank_account'          => $this->parseBool($row['has_bank_account']          ?? null) ?? false,
            'has_insurance'             => $this->parseBool($row['has_insurance']             ?? null) ?? false,
            'poverty_card'              => $this->parseBool($row['poverty_card']              ?? null) ?? false,
            'food_expense'              => is_numeric($row['food_expense']        ?? '') ? (int)$row['food_expense']        : null,
            'clothes_expense'           => is_numeric($row['clothes_expense']     ?? '') ? (int)$row['clothes_expense']     : null,
            'education_expense'         => is_numeric($row['education_expense']   ?? '') ? (int)$row['education_expense']   : null,
            'treatment_expense'         => is_numeric($row['treatment_expense']   ?? '') ? (int)$row['treatment_expense']   : null,
            'other_expense'             => is_numeric($row['other_expense']       ?? '') ? (int)$row['other_expense']       : null,
            // social_security_allowance is per-person — synced via Members sheet (ss_allowance)
            // decision_* and has_foreign_employment → synced to survey_data in syncRelated()
        ];
    }

    private function rowToAgricultureData(array $row): array
    {
        $yn = fn($v) => ($b = $this->parseBool($v)) === null ? null : ($b ? 'yes' : 'no');
        return [
            'agriculture'              => $yn($row['agriculture'] ?? null),
            'agri_categories'          => $this->parseJsonArray($row['agri_category'] ?? null),
            'use_of_chem_fertilizer'   => trim($row['use_of_chem_fertilizer'] ?? '') ?: null,
            'uses_chemical_fertilizer' => $yn($row['uses_chemical_fertilizer'] ?? $row['use_of_chem_fertilizer'] ?? null),
            'chem_fertilizer_types'    => $this->parseJsonArray($row['chem_fertilizer'] ?? null),
            'chem_fertilizer_kg'       => is_numeric($row['chem_fertilizer_kg'] ?? '') ? (int)$row['chem_fertilizer_kg'] : null,
            'fertilizer_sources'       => $this->parseJsonArray($row['fertilizer_source'] ?? null),
            'use_of_chem_pesticide'    => trim($row['use_of_chem_pesticide'] ?? '') ?: null,
            'chem_pesticide_types'     => $this->parseJsonArray($row['chem_pesticide'] ?? null),
            'chem_pesticide_cost'      => is_numeric($row['chem_pesticide_cost'] ?? '') ? (int)$row['chem_pesticide_cost'] : null,
            'uses_organic_fertilizer'  => $yn($row['uses_organic_fertilizer']  ?? null),
            'sells_produce'            => $yn($row['sells_produce']             ?? null),
            'has_agri_loan'            => $yn($row['has_agri_loan']             ?? null),
            'irrigation_source'        => $this->opt('irrigation_source',      $row['irrigation_source']       ?? null),
            'Farm_Challenges'          => $this->parseJsonArray($row['farm_challenge'] ?? null),
            'owns_land'                => $yn($row['owns_land']   ?? null),
            'land_area_ropani'         => is_numeric($row['land_area_ropani']      ?? '') ? (float)$row['land_area_ropani']      : null,
            'irrigated_area_ropani'    => is_numeric($row['irrigated_area_ropani'] ?? '') ? (float)$row['irrigated_area_ropani'] : null,
            'annual_agri_income'       => is_numeric($row['annual_agri_income']    ?? '') ? (int)$row['annual_agri_income']      : null,
        ];
    }

    private function rowToDisasterData(array $row): array
    {
        return [
            'has_death_this_year'   => trim($row['has_death_this_year']   ?? '') ?: null,
            'risk_types'            => $this->parseJsonArray($row['disaster_type']         ?? $row['risk_types']         ?? null),
            'is_flood_zone'         => $this->parseBool($row['is_flood_zone']              ?? null) ?? false,
            'is_landslide_zone'     => $this->parseBool($row['is_landslide_zone']          ?? null) ?? false,
            'is_drought_zone'       => $this->parseBool($row['is_drought_zone']            ?? null) ?? false,
            'has_evacuation_plan'   => $this->parseBool($row['has_evacuation_plan']        ?? null) ?? false,
            'disaster_training'     => $this->parseBool($row['disaster_training']          ?? null) ?? false,
            'affected_last_3years'  => trim($row['affected_last_3years']  ?? '') ?: null,
            'received_relief'       => trim($row['received_relief']       ?? '') ?: null,
            'risk_reduction_plan'   => trim($row['risk_reduction_plan']   ?? '') ?: null,
            'nearest_relief_center' => trim($row['nearest_relief_center'] ?? '') ?: null,
            'natural_disasters'     => $this->parseJsonArray($row['natural_disasters']     ?? null),
        ];
    }

    private function rowToMemberData(array $row): array
    {
        return [
            'full_name'         => trim($row['full_name']         ?? '') ?: null,
            'father_name'       => trim($row['father_name']       ?? '') ?: null,
            'gender'            => trim($row['gender']            ?? '') ?: null,
            'age'               => is_numeric($row['age']         ?? '') ? (int)$row['age'] : null,
            'date_of_birth'     => $this->parseDate($row['date_of_birth'] ?? null),
            'relation_to_head'  => $this->opt('relation_to_head',  $row['relation_to_head']  ?? null),
            'ethnicity'         => $this->opt('ethnicity',         $row['ethnicity']         ?? null),
            'religion'          => $this->opt('religion',          $row['religion']          ?? null),
            'mother_tongue'     => $this->opt('language',          $row['language']          ?? null),
            'marital_status'    => $this->opt('marital_status',    $row['marital_status']    ?? null),
            'ageofmarriage'     => is_numeric($row['ageofmarriage'] ?? '') ? (int)$row['ageofmarriage'] : null,
            'education_level'   => $this->opt('education_level',   $row['education_level']   ?? null),
            'education_status'  => $this->opt('education_status',  $row['education_status']  ?? null),
            'is_literate'       => $this->parseBool($row['is_literate']       ?? null) ?? false,
            'school_type'       => $this->opt('school_type',       $row['school_type']       ?? null),
            'cause_not_school'  => $this->parseJsonArray($row['cause_not_school'] ?? null),
            'current_class'     => trim($row['current_class']     ?? '') ?: null,
            'school_name'       => trim($row['school_name']       ?? '') ?: null,
            'occupation_type'   => $this->opt('occupation_type',   $row['occupation_type']   ?? null),
            'vocational_training'     => $this->parseBool($row['vocational_training']     ?? null) ?? false,
            'vt_subject'              => trim($row['vt_subject']           ?? '') ?: null,
            'vt_duration_min'         => is_numeric($row['vt_duration_min'] ?? '') ? (int)$row['vt_duration_min'] : null,
            'vt_duration_max'         => is_numeric($row['vt_duration_max'] ?? '') ? (int)$row['vt_duration_max'] : null,
            'techtraining'            => $this->parseBool($row['techtraining']            ?? null) ?? false,
            'techtraining_subject'    => trim($row['techtraining_subject']  ?? '') ?: null,
            'sa_training'             => $this->parseBool($row['sa_training']             ?? null) ?? false,
            'sa_training_subject'     => trim($row['sa_training_subject']   ?? '') ?: null,
            'employment_status' => $this->opt('employment_status', $row['employment_status'] ?? null),
            'disability_status'       => $this->parseBool($row['disability_status']       ?? null) ?? false,
            'disability_type'         => $this->opt('disability_type', $row['disability_type'] ?? null),
            'disability_card'         => trim($row['disability_card']       ?? '') ?: null,
            'disability_card_color'   => trim($row['disability_card_color'] ?? '') ?: null,
            'chronic_illness'         => $this->parseBool($row['chronic_illness']         ?? null) ?? false,
            'chronic_illness_types'   => $this->parseJsonArray($row['chronic_illness_types'] ?? null),
            'child_labor'             => $this->parseBool($row['child_labor']             ?? null) ?? false,
            'workplace_child_labor'   => trim($row['workplace_child_labor'] ?? '') ?: null,
            'blood_group'             => trim($row['blood_group']       ?? '') ?: null,
            'blood_group_tested'      => trim($row['blood_group_tested'] ?? '') ?: null,
            'has_birth_certificate'      => $this->parseBool($row['has_birth_certificate']      ?? null) ?? false,
            'has_citizenship'         => $this->parseBool($row['has_citizenship']         ?? null) ?? false,
            'no_citizenship_reason'   => trim($row['no_citizenship_reason'] ?? '') ?: null,
            'has_voters_card'         => $this->parseBool($row['has_voters_card']         ?? null) ?? false,
            'has_national_id_card'    => $this->parseBool($row['has_national_id_card']    ?? null) ?? false,
            'has_passport'            => $this->parseBool($row['has_passport']            ?? null) ?? false,
            'has_pan'                 => $this->parseBool($row['has_pan']                 ?? null) ?? false,
            'has_drivers_license'     => $this->parseBool($row['has_drivers_license']     ?? null) ?? false,
            'drivers_license_category'=> trim($row['drivers_license_category'] ?? '') ?: null,
            'residence_status'        => $this->opt('residence_status', $row['residence_status'] ?? null),
            'residing_country'        => trim($row['residing_country']         ?? '') ?: null,
            'purpose_living_outside'  => trim($row['purpose_living_outside']   ?? '') ?: null,
            'typeofabroadjob'         => $this->opt('typeofabroadjob',  $row['typeofabroadjob']  ?? null),
            'abroadjobstatus'         => $this->opt('abroadjobstatus',  $row['abroadjobstatus']  ?? null),
            'addrslocation'           => trim($row['addrslocation']       ?? '') ?: null,
            'residing_district'       => trim($row['residing_district']   ?? '') ?: null,
            'contact_phone'           => trim($row['contact_phone']        ?? '') ?: null,
            'national_id'             => trim($row['national_id']          ?? '') ?: null,
            'ss_allowance'               => $this->parseBool($row['social_security_allowance']  ?? null) ?? false,
            'smoking_drinking'        => $this->parseBool($row['smoking_drinking']        ?? null) ?? false,
            'smoking_drinking_items'  => $this->parseJsonArray($row['smoking_drinking_items'] ?? null),
            'frequency_smoking_drinking' => trim($row['frequency_smoking_drinking'] ?? '') ?: null,
            'is_deceased'             => $this->parseBool($row['is_deceased']             ?? null) ?? false,
            'death_cause'             => trim($row['death_cause']          ?? '') ?: null,
            'death_date'              => $this->parseDate($row['death_date'] ?? null),
            'remarks'                 => trim($row['remarks'] ?? '') ?: null,
            'has_phone'               => $this->parseBool($row['has_phone']               ?? null),
            'is_household_head'       => $this->parseBool($row['is_household_head']       ?? null) ?? false,
            // Child health (0–3 yrs)
            'colostrum'                  => $this->parseBool($row['colostrum']                  ?? null),
            'vit_a_dewormin'             => $this->parseBool($row['vit_a_dewormin']             ?? null),
            'fullmilkcourse'             => $this->parseBool($row['fullmilkcourse']             ?? null),
            'childhealthcheckup'         => $this->parseBool($row['childhealthcheckup']         ?? null),
            'diarrhea_before_two_weeks'  => $this->parseBool($row['diarrhea_before_two_weeks']  ?? null),
            'fever_resp'                 => $this->parseBool($row['fever_resp']                 ?? null),
            'childvaccine'               => $this->parseJsonArray($row['childvaccine']          ?? null),
            'vitaminA'                   => $this->parseBool($row['vitaminA']                   ?? null),
            'childbornplace'             => trim($row['childbornplace']   ?? '') ?: null,
            // Child development (6–15 yrs)
            'phy_dev_6_15'               => $this->parseBool($row['phy_dev_6_15']               ?? null),
            'allergy_infection_6_15'     => $this->parseBool($row['allergy_infection_6_15']     ?? null),
            'watch_mobile_tv_6_15'       => $this->parseBool($row['watch_mobile_tv_6_15']       ?? null),
            'school_interest_6_15'       => trim($row['school_interest_6_15']   ?? '') ?: null,
        ];
    }

    private function rowToLivestockData(array $row): array
    {
        return [
            'livestock_type'     => trim($row['livestock_type'] ?? '') ?: null,
            'count'              => is_numeric($row['count']    ?? '') ? (int)$row['count'] : null,
            'vet_service_access' => $this->parseBool($row['vet_service_access'] ?? null) ?? false,
            'insurance'          => $this->parseBool($row['insurance']           ?? null) ?? false,
        ];
    }

    private function rowToInstitutionData(array $row, int $wardId, int $lgId): array
    {
        [$gpsLat, $gpsLng] = $this->parseGps($row);
        return [
            'unique_code'         => trim($row['unique_code'] ?? $row['institution_code'] ?? '') ?: null,
            'name_en'             => trim($row['name_en']             ?? '') ?: null,
            'name_np'             => trim($row['name_np']             ?? '') ?: null,
            'ownership_type'      => $this->opt('inst_ownership_type', $row['ownership_type'] ?? null),
            'registration_number' => trim($row['registration_number'] ?? '') ?: null,
            'institution_type'    => $this->opt('institution_type', $row['institution_type'] ?? null),
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
            'buildings'        => array_filter([
                                     $this->opt('inst_building_type', $row['building_type'] ?? null)
                                         ? ['building_type' => $this->opt('inst_building_type', $row['building_type'] ?? null), 'building_count' => 1, 'room_count' => null]
                                         : null,
                                 ]),
            'condition'        => $this->opt('inst_building_condition', $row['building_condition'] ?? null),
            'total_rooms'      => is_numeric($row['total_rooms']  ?? '') ? (int)$row['total_rooms']  : null,
            'total_floors'     => is_numeric($row['total_floors'] ?? '') ? (int)$row['total_floors'] : null,
            'total_area'       => is_numeric($row['land_area_sqft'] ?? '') ? (float)$row['land_area_sqft'] : null,
            'has_boundary_wall'  => $this->parseBool($row['has_boundary_wall']  ?? null) ?? false,
            'toilet_count'       => is_numeric($row['toilet_count']   ?? '') ? (int)$row['toilet_count']   : null,
            'female_toilet'      => is_numeric($row['female_toilet']  ?? '') ? (int)$row['female_toilet']  : null,
            'male_toilet'        => is_numeric($row['male_toilet']    ?? '') ? (int)$row['male_toilet']    : null,
            'disabled_toilet'    => $this->parseBool($row['disabled_toilet']    ?? null) ?? false,
            'has_ramp'           => $this->parseBool($row['accessible_ramp']    ?? null) ?? false,
            'has_water'          => $this->parseBool($row['drinking_water']     ?? null) ?? false,
            'has_electricity'    => $this->parseBool($row['electricity']        ?? null) ?? false,
            'solar'              => $this->parseBool($row['solar']              ?? null) ?? false,
            'generator'          => $this->parseBool($row['generator']          ?? null) ?? false,
            'has_internet'       => $this->parseBool($row['internet']           ?? null) ?? false,
            'fire_extinguisher'  => $this->parseBool($row['fire_extinguisher']  ?? null) ?? false,
            'earthquake_safe'    => $this->parseBool($row['earthquake_safe']    ?? null) ?? false,
            'evacuation_plan'    => $this->parseBool($row['evacuation_plan']    ?? null) ?? false,
            'total_classrooms'   => is_numeric($row['total_classrooms'] ?? '') ? (int)$row['total_classrooms'] : null,
            'has_library'        => $this->parseBool($row['has_library']  ?? null) ?? false,
            'has_lab'            => $this->parseBool($row['has_lab']      ?? null) ?? false,
        ];
    }

    private function rowToStaffData(array $row): array
    {
        return [
            'staff_category'   => $this->opt('inst_staff_category', $row['staff_category'] ?? null),
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
            'health_type'           => trim($row['health_type'] ?? '') ?: null,
            'bed_count'             => is_numeric($row['bed_count'] ?? '') ? (int)$row['bed_count'] : null,
            'male_ward_beds'        => is_numeric($row['male_ward_beds'] ?? '') ? (int)$row['male_ward_beds'] : null,
            'female_ward_beds'      => is_numeric($row['female_ward_beds'] ?? '') ? (int)$row['female_ward_beds'] : null,
            'children_ward_beds'    => is_numeric($row['children_ward_beds'] ?? '') ? (int)$row['children_ward_beds'] : null,
            'maternity_beds'        => is_numeric($row['maternity_beds'] ?? '') ? (int)$row['maternity_beds'] : null,
            'icu_beds'              => is_numeric($row['icu_beds'] ?? '') ? (int)$row['icu_beds'] : null,
            'emergency_beds'        => is_numeric($row['emergency_beds'] ?? '') ? (int)$row['emergency_beds'] : null,
            'delivery_service'      => $this->parseBool($row['delivery_service']      ?? null) ?? false,
            'emergency_service'     => $this->parseBool($row['emergency_service']     ?? null) ?? false,
            'has_lab'               => $this->parseBool($row['lab_service']            ?? null) ?? false,
            'has_xray'              => $this->parseBool($row['xray_service']           ?? null) ?? false,
            'has_ambulance'         => $this->parseBool($row['ambulance']              ?? null) ?? false,
            'vaccine_service'       => $this->parseBool($row['vaccine_service']        ?? null) ?? false,
            'family_planning'       => $this->parseBool($row['family_planning']        ?? null) ?? false,
            'opd_daily'             => is_numeric($row['opd_daily'] ?? '') ? (int)$row['opd_daily'] : null,
            'opd_monthly'           => is_numeric($row['monthly_outpatient_avg'] ?? '') ? (int)$row['monthly_outpatient_avg'] : null,
            'ipd_monthly'           => is_numeric($row['monthly_inpatient_avg']  ?? '') ? (int)$row['monthly_inpatient_avg']  : null,
            'has_ultrasound'        => $this->parseBool($row['has_ultrasound']        ?? null) ?? false,
            'has_ecg'               => $this->parseBool($row['has_ecg']               ?? null) ?? false,
            'has_icu'               => $this->parseBool($row['has_icu']               ?? null) ?? false,
            'has_operation_theatre' => $this->parseBool($row['has_operation_theatre'] ?? null) ?? false,
            'has_blood_bank'        => $this->parseBool($row['has_blood_bank']        ?? null) ?? false,
            'has_dialysis'          => $this->parseBool($row['has_dialysis']          ?? null) ?? false,
            'has_dental'            => $this->parseBool($row['has_dental']            ?? null) ?? false,
            'has_physiotherapy'     => $this->parseBool($row['has_physiotherapy']     ?? null) ?? false,
            'has_oxygen_plant'      => $this->parseBool($row['has_oxygen_plant']      ?? null) ?? false,
            'health_services'       => $this->parseJsonArray($row['health_services']  ?? null),
            'equipment'             => $this->parseJsonArray($row['equipment']         ?? null),
            'lab_services'          => $this->parseJsonArray($row['lab_services']      ?? null),
        ];
    }

    private function rowToTypespecificData(array $row): array
    {
        $arr = fn($v) => $this->parseJsonArray($v) ?? [];
        return array_filter([
            // School
            'school_level'          => trim($row['school_level']    ?? '') ?: null,
            'management_type'       => trim($row['management_type'] ?? '') ?: null,
            'medium'                => trim($row['medium']          ?? '') ?: null,
            // Cooperative
            'coop_type'             => trim($row['coop_type']       ?? '') ?: null,
            'share_capital'         => is_numeric($row['share_capital']    ?? '') ? (float)$row['share_capital']    : null,
            'loan_outstanding'      => is_numeric($row['loan_outstanding'] ?? '') ? (float)$row['loan_outstanding'] : null,
            'male_members'          => is_numeric($row['male_members']     ?? '') ? (int)$row['male_members']       : null,
            'female_members'        => is_numeric($row['female_members']   ?? '') ? (int)$row['female_members']     : null,
            'other_members'         => is_numeric($row['other_members']    ?? '') ? (int)$row['other_members']      : null,
            // Bank
            'bank_type'             => trim($row['bank_type']       ?? '') ?: null,
            'branch_count'          => is_numeric($row['branch_count']    ?? '') ? (int)$row['branch_count']    : null,
            'total_deposits'        => is_numeric($row['total_deposits']   ?? '') ? (float)$row['total_deposits'] : null,
            'total_loans'           => is_numeric($row['total_loans']      ?? '') ? (float)$row['total_loans']    : null,
            // Government
            'office_level'          => trim($row['office_level'] ?? '') ?: null,
            'department'            => trim($row['department']   ?? '') ?: null,
            // NGO / INGO
            'registration_type'     => trim($row['registration_type'] ?? '') ?: null,
            'donor'                 => trim($row['donor']             ?? '') ?: null,
            'beneficiaries_m'       => is_numeric($row['beneficiaries_m'] ?? '') ? (int)$row['beneficiaries_m'] : null,
            'beneficiaries_f'       => is_numeric($row['beneficiaries_f'] ?? '') ? (int)$row['beneficiaries_f'] : null,
            'working_areas'         => $arr($row['working_areas'] ?? null) ?: null,
            // Religious
            'religion_type'         => trim($row['religion_type']  ?? '') ?: null,
            'daily_visitors'        => is_numeric($row['daily_visitors'] ?? '') ? (int)$row['daily_visitors'] : null,
            'has_trust'             => ($this->parseBool($row['has_trust'] ?? null)) ? true : null,
            // Market
            'shop_count'            => is_numeric($row['shop_count'] ?? '') ? (int)$row['shop_count'] : null,
            'goods_types'           => trim($row['goods_types']      ?? '') ?: null,
            'market_days'           => $arr($row['market_days']      ?? null) ?: null,
            // Industry
            'industry_type'         => trim($row['industry_type']      ?? '') ?: null,
            'annual_production'     => is_numeric($row['annual_production'] ?? '') ? (float)$row['annual_production'] : null,
            'male_workers'          => is_numeric($row['male_workers']      ?? '') ? (int)$row['male_workers']   : null,
            'female_workers'        => is_numeric($row['female_workers']    ?? '') ? (int)$row['female_workers'] : null,
            // Media
            'media_type'            => trim($row['media_type']    ?? '') ?: null,
            'coverage_area'         => trim($row['coverage_area'] ?? '') ?: null,
            'frequency'             => trim($row['frequency']     ?? '') ?: null,
            // Forest / CFUG
            'forest_type'           => trim($row['forest_type']           ?? '') ?: null,
            'forest_area_hectare'   => is_numeric($row['forest_area_hectare']   ?? '') ? (float)$row['forest_area_hectare']   : null,
            'forest_covered_wards'  => trim($row['forest_covered_wards']  ?? '') ?: null,
            'consumer_households'   => is_numeric($row['consumer_households']   ?? '') ? (int)$row['consumer_households']   : null,
            'total_trees'           => is_numeric($row['total_trees']           ?? '') ? (int)$row['total_trees']           : null,
            'annual_budget'         => is_numeric($row['annual_budget']         ?? '') ? (float)$row['annual_budget']       : null,
            'annual_income'         => is_numeric($row['annual_income']         ?? '') ? (float)$row['annual_income']       : null,
            'member_count_m'        => is_numeric($row['member_count_m']        ?? '') ? (int)$row['member_count_m']        : null,
            'member_count_f'        => is_numeric($row['member_count_f']        ?? '') ? (int)$row['member_count_f']        : null,
            'committee_chairperson' => trim($row['committee_chairperson']  ?? '') ?: null,
            'tree_types'            => $arr($row['tree_types']  ?? null) ?: null,
            'herb_types'            => $arr($row['herb_types']  ?? null) ?: null,
        ], fn ($v) => $v !== null);
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
        if (!trim((string)($row['unique_code'] ?? ''))) {
            $errs[] = "Row {$rowNum}: unique_code (घरधुरी कोड) आवश्यक छ";
        }
        if (!trim((string)($row['respondent_name'] ?? ''))) {
            $errs[] = "Row {$rowNum}: respondent_name (उत्तरदाताको नाम) आवश्यक छ";
        }
        if (!trim((string)($row['survey_date'] ?? ''))) {
            $errs[] = "Row {$rowNum}: survey_date (सर्वेक्षण मिति) आवश्यक छ";
        }
        if (!is_numeric($row['total_members'] ?? '') || (int)$row['total_members'] < 1) {
            $errs[] = "Row {$rowNum}: total_members (कुल सदस्य) आवश्यक छ र १ भन्दा बढी हुनुपर्छ";
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
        if (!trim((string)($row['institution_type'] ?? ''))) {
            $errs[] = "Row {$rowNum}: institution_type (संस्थाको प्रकार) आवश्यक छ";
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


    private function logSyncAudit(string $type, int $wardId, int $imported, int $updated, int $skipped): void
    {
        if ($imported + $updated === 0) return;
        try {
            \App\Models\AuditLog::create([
                'user_id'        => auth()->id(),
                'event_type'     => 'google_sync',
                'auditable_type' => 'App\\Models\\Ward',
                'auditable_id'   => $wardId,
                'old_values'     => [],
                'new_values'     => compact('type', 'imported', 'updated', 'skipped'),
                'ip_address'     => request()->ip(),
                'user_agent'     => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('GS sync audit log failed', ['err' => $e->getMessage()]);
        }
    }
}
