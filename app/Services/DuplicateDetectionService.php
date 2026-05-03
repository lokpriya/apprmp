<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Institution;

class DuplicateDetectionService
{
    // ~20-metre bounding box at Nepal's latitude (~28°N)
    const GPS_TOL_LAT = 0.00018;
    const GPS_TOL_LNG = 0.00020;

    // ── Preloaded household maps (null = not preloaded, use DB) ───────────────
    private ?array $hhGps   = null;  // [[id, code, status, name, ward_id, ward_number, lat, lng]]
    private ?array $hhPhone = null;  // [normalized_phone => [[id, code, status, name, ward_id, ward_number]]]
    private ?array $hhName  = null;  // ["wardId:lower_name" => [[...]]]
    private ?array $hhHead  = null;  // ["wardId:lower_head" => [[...]]]

    // ── Preloaded institution maps ─────────────────────────────────────────────
    private ?array $instGps    = null;  // [[id, code, status, name, ward_id, ward_number, lat, lng]]
    private ?array $instReg    = null;  // [reg_num => [[...]]]
    private ?array $instNameNp = null;  // ["wardId:lower_name_np" => [[...]]]
    private ?array $instNameEn = null;  // ["wardId:lower_name_en" => [[...]]]

    // ─────────────────────────────────────────────────────────────────────────
    // Preload (call once before a bulk import loop)
    // ─────────────────────────────────────────────────────────────────────────

    public function preloadHouseholds(array $wardIds): void
    {
        $this->hhGps = $this->hhPhone = $this->hhName = $this->hhHead = [];

        Household::whereIn('ward_id', $wardIds)
            ->withoutTrashed()
            ->with('ward:id,ward_number')
            ->select(['id', 'unique_code', 'status', 'respondent_name', 'ward_id',
                      'gps_lat', 'gps_lng', 'respondent_contact', 'survey_data'])
            ->each(function (Household $h) {
                $base = [
                    'id'          => $h->id,
                    'unique_code' => $h->unique_code,
                    'status'      => $h->status,
                    'name'        => $h->respondent_name,
                    'ward_id'     => $h->ward_id,
                    'ward_number' => $h->ward?->ward_number,
                ];

                if ($h->gps_lat && $h->gps_lng) {
                    $this->hhGps[] = array_merge($base, [
                        'lat' => (float) $h->gps_lat,
                        'lng' => (float) $h->gps_lng,
                    ]);
                }

                $phone = preg_replace('/\D/', '', $h->respondent_contact ?? '');
                if (strlen($phone) >= 7) {
                    $this->hhPhone[$phone][] = $base;
                }

                $name = mb_strtolower(trim($h->respondent_name ?? ''));
                if ($name) {
                    $this->hhName[$h->ward_id . ':' . $name][] = $base;
                }

                $head = mb_strtolower(trim(data_get($h->survey_data, 'social.family_head', '')));
                if ($head) {
                    $this->hhHead[$h->ward_id . ':' . $head][] = $base;
                }
            });
    }

    public function preloadInstitutions(array $wardIds): void
    {
        $this->instGps = $this->instReg = $this->instNameNp = $this->instNameEn = [];

        Institution::whereIn('ward_id', $wardIds)
            ->withoutTrashed()
            ->with('ward:id,ward_number')
            ->select(['id', 'unique_code', 'status', 'name_np', 'name_en', 'ward_id',
                      'gps_lat', 'gps_lng', 'registration_number'])
            ->each(function (Institution $inst) {
                $base = [
                    'id'          => $inst->id,
                    'unique_code' => $inst->unique_code,
                    'status'      => $inst->status,
                    'name'        => $inst->name_np ?: $inst->name_en,
                    'ward_id'     => $inst->ward_id,
                    'ward_number' => $inst->ward?->ward_number,
                ];

                if ($inst->gps_lat && $inst->gps_lng) {
                    $this->instGps[] = array_merge($base, [
                        'lat' => (float) $inst->gps_lat,
                        'lng' => (float) $inst->gps_lng,
                    ]);
                }

                $reg = trim($inst->registration_number ?? '');
                if ($reg) {
                    $this->instReg[$reg][] = $base;
                }

                $nameNp = mb_strtolower(trim($inst->name_np ?? ''));
                if ($nameNp) {
                    $this->instNameNp[$inst->ward_id . ':' . $nameNp][] = $base;
                }

                $nameEn = mb_strtolower(trim($inst->name_en ?? ''));
                if ($nameEn) {
                    $this->instNameEn[$inst->ward_id . ':' . $nameEn][] = $base;
                }
            });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Households
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns ['found' => bool, 'matches' => [...]]
     * Each match: id, unique_code, status, respondent_name, ward_number, reasons[], edit_url
     */
    public function checkHousehold(array $data, ?int $excludeId = null): array
    {
        $wardId     = $data['ward_id']          ?? null;
        $gpsLat     = is_numeric($data['gps_lat'] ?? '') ? (float) $data['gps_lat'] : null;
        $gpsLng     = is_numeric($data['gps_lng'] ?? '') ? (float) $data['gps_lng'] : null;
        $phone      = preg_replace('/\D/', '', $data['respondent_contact'] ?? '');
        $respName   = mb_strtolower(trim($data['respondent_name'] ?? ''));
        $familyHead = mb_strtolower(trim($data['survey_data']['social']['family_head'] ?? ''));

        $hits = [];

        $addBase = function (array $base, string $reason) use (&$hits, $excludeId) {
            if ($excludeId && $base['id'] === $excludeId) return;
            if (!isset($hits[$base['id']])) {
                $hits[$base['id']] = ['base' => $base, 'reasons' => []];
            }
            $hits[$base['id']]['reasons'][] = $reason;
        };

        if ($this->hhGps !== null) {
            // ── Preloaded path (bulk import) ──────────────────────────────────
            if ($gpsLat !== null && $gpsLng !== null) {
                foreach ($this->hhGps as $row) {
                    if (abs($row['lat'] - $gpsLat) < self::GPS_TOL_LAT
                        && abs($row['lng'] - $gpsLng) < self::GPS_TOL_LNG) {
                        $addBase($row, 'GPS स्थान नजिक (२० मिटर भित्र)');
                    }
                }
            }
            if (strlen($phone) >= 7) {
                foreach ($this->hhPhone[$phone] ?? [] as $row) {
                    $addBase($row, 'सोही फोन नम्बर');
                }
            }
            if ($respName && $wardId) {
                foreach ($this->hhName[$wardId . ':' . $respName] ?? [] as $row) {
                    $addBase($row, 'सोही उत्तरदाताको नाम (एउटै वडा)');
                }
            }
            if ($familyHead && $wardId) {
                foreach ($this->hhHead[$wardId . ':' . $familyHead] ?? [] as $row) {
                    $addBase($row, 'सोही परिवार प्रमुखको नाम (एउटै वडा)');
                }
            }
        } else {
            // ── DB path (single form submission) ─────────────────────────────
            $addModel = function (Household $h, string $reason) use (&$hits, $excludeId) {
                if ($excludeId && $h->id === $excludeId) return;
                if (!isset($hits[$h->id])) {
                    $hits[$h->id] = ['base' => [
                        'id'          => $h->id,
                        'unique_code' => $h->unique_code,
                        'status'      => $h->status,
                        'name'        => $h->respondent_name,
                        'ward_id'     => $h->ward_id,
                        'ward_number' => $h->ward?->ward_number,
                    ], 'reasons' => []];
                }
                $hits[$h->id]['reasons'][] = $reason;
            };

            $base = Household::withoutTrashed()
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->with('ward:id,ward_number');

            if ($gpsLat !== null && $gpsLng !== null) {
                (clone $base)
                    ->whereNotNull('gps_lat')->whereNotNull('gps_lng')
                    ->whereRaw('ABS(gps_lat - ?) < ?', [$gpsLat, self::GPS_TOL_LAT])
                    ->whereRaw('ABS(gps_lng - ?) < ?', [$gpsLng, self::GPS_TOL_LNG])
                    ->select(['id', 'unique_code', 'status', 'respondent_name', 'ward_id'])
                    ->each(fn($h) => $addModel($h, 'GPS स्थान नजिक (२० मिटर भित्र)'));
            }
            if (strlen($phone) >= 7) {
                (clone $base)
                    ->whereRaw("REGEXP_REPLACE(respondent_contact, '[^0-9]', '') = ?", [$phone])
                    ->select(['id', 'unique_code', 'status', 'respondent_name', 'ward_id'])
                    ->each(fn($h) => $addModel($h, 'सोही फोन नम्बर'));
            }
            if ($respName && $wardId) {
                (clone $base)
                    ->where('ward_id', $wardId)
                    ->whereRaw('LOWER(TRIM(respondent_name)) = ?', [$respName])
                    ->select(['id', 'unique_code', 'status', 'respondent_name', 'ward_id'])
                    ->each(fn($h) => $addModel($h, 'सोही उत्तरदाताको नाम (एउटै वडा)'));
            }
            if ($familyHead && $wardId) {
                (clone $base)
                    ->where('ward_id', $wardId)
                    ->whereNotNull('survey_data')
                    ->whereRaw("LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.social.family_head')))) = ?", [$familyHead])
                    ->select(['id', 'unique_code', 'status', 'respondent_name', 'ward_id'])
                    ->each(fn($h) => $addModel($h, 'सोही परिवार प्रमुखको नाम (एउटै वडा)'));
            }
        }

        if (empty($hits)) {
            return ['found' => false, 'matches' => []];
        }

        $matches = array_map(function ($hit) {
            $b = $hit['base'];
            return [
                'id'              => $b['id'],
                'unique_code'     => $b['unique_code'],
                'status'          => $b['status'],
                'respondent_name' => $b['name'],
                'ward_number'     => $b['ward_number'],
                'reasons'         => $hit['reasons'],
                'edit_url'        => "/households/{$b['id']}",
            ];
        }, array_values($hits));

        return ['found' => true, 'matches' => $matches];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Institutions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns ['found' => bool, 'hard_block' => bool, 'matches' => [...]]
     */
    public function checkInstitution(array $data, ?int $excludeId = null): array
    {
        $wardId = $data['ward_id']            ?? null;
        $gpsLat = is_numeric($data['gps_lat'] ?? '') ? (float) $data['gps_lat'] : null;
        $gpsLng = is_numeric($data['gps_lng'] ?? '') ? (float) $data['gps_lng'] : null;
        $regNum = trim($data['registration_number'] ?? '');
        $nameNp = mb_strtolower(trim($data['name_np'] ?? ''));
        $nameEn = mb_strtolower(trim($data['name_en'] ?? ''));

        $hits      = [];
        $hardBlock = false;

        $addBase = function (array $base, string $reason, bool $isHard = false) use (&$hits, &$hardBlock, $excludeId) {
            if ($excludeId && $base['id'] === $excludeId) return;
            if ($isHard) $hardBlock = true;
            if (!isset($hits[$base['id']])) {
                $hits[$base['id']] = ['base' => $base, 'reasons' => [], 'hard' => false];
            }
            $hits[$base['id']]['reasons'][] = $reason;
            if ($isHard) $hits[$base['id']]['hard'] = true;
        };

        if ($this->instGps !== null) {
            // ── Preloaded path (bulk import) ──────────────────────────────────
            if ($regNum) {
                foreach ($this->instReg[$regNum] ?? [] as $row) {
                    $addBase($row, 'सोही दर्ता नम्बर — ' . $regNum, true);
                }
            }
            if ($gpsLat !== null && $gpsLng !== null) {
                foreach ($this->instGps as $row) {
                    if (abs($row['lat'] - $gpsLat) < self::GPS_TOL_LAT
                        && abs($row['lng'] - $gpsLng) < self::GPS_TOL_LNG) {
                        $addBase($row, 'GPS स्थान नजिक (२० मिटर भित्र)');
                    }
                }
            }
            if ($nameNp && $wardId) {
                foreach ($this->instNameNp[$wardId . ':' . $nameNp] ?? [] as $row) {
                    $addBase($row, 'सोही नाम (नेपाली) — एउटै वडा');
                }
            }
            if ($nameEn && $wardId) {
                foreach ($this->instNameEn[$wardId . ':' . $nameEn] ?? [] as $row) {
                    $addBase($row, 'सोही नाम (अंग्रेजी) — एउटै वडा');
                }
            }
        } else {
            // ── DB path (single form submission) ─────────────────────────────
            $addModel = function (Institution $inst, string $reason, bool $isHard = false) use (&$hits, &$hardBlock, $excludeId) {
                if ($excludeId && $inst->id === $excludeId) return;
                if ($isHard) $hardBlock = true;
                if (!isset($hits[$inst->id])) {
                    $hits[$inst->id] = ['base' => [
                        'id'          => $inst->id,
                        'unique_code' => $inst->unique_code,
                        'status'      => $inst->status,
                        'name'        => $inst->name_np ?: $inst->name_en,
                        'ward_id'     => $inst->ward_id,
                        'ward_number' => $inst->ward?->ward_number,
                    ], 'reasons' => [], 'hard' => false];
                }
                $hits[$inst->id]['reasons'][] = $reason;
                if ($isHard) $hits[$inst->id]['hard'] = true;
            };

            $base = Institution::withoutTrashed()
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->with('ward:id,ward_number');

            if ($regNum) {
                (clone $base)
                    ->whereRaw('TRIM(registration_number) = ?', [$regNum])
                    ->select(['id', 'unique_code', 'status', 'name_np', 'name_en', 'ward_id', 'registration_number'])
                    ->each(fn($inst) => $addModel($inst, 'सोही दर्ता नम्बर — ' . $regNum, true));
            }
            if ($gpsLat !== null && $gpsLng !== null) {
                (clone $base)
                    ->whereNotNull('gps_lat')->whereNotNull('gps_lng')
                    ->whereRaw('ABS(gps_lat - ?) < ?', [$gpsLat, self::GPS_TOL_LAT])
                    ->whereRaw('ABS(gps_lng - ?) < ?', [$gpsLng, self::GPS_TOL_LNG])
                    ->select(['id', 'unique_code', 'status', 'name_np', 'name_en', 'ward_id'])
                    ->each(fn($inst) => $addModel($inst, 'GPS स्थान नजिक (२० मिटर भित्र)'));
            }
            if ($nameNp && $wardId) {
                (clone $base)
                    ->where('ward_id', $wardId)
                    ->whereRaw('LOWER(TRIM(name_np)) = ?', [$nameNp])
                    ->select(['id', 'unique_code', 'status', 'name_np', 'name_en', 'ward_id'])
                    ->each(fn($inst) => $addModel($inst, 'सोही नाम (नेपाली) — एउटै वडा'));
            }
            if ($nameEn && $wardId) {
                (clone $base)
                    ->where('ward_id', $wardId)
                    ->whereRaw('LOWER(TRIM(name_en)) = ?', [$nameEn])
                    ->select(['id', 'unique_code', 'status', 'name_np', 'name_en', 'ward_id'])
                    ->each(fn($inst) => $addModel($inst, 'सोही नाम (अंग्रेजी) — एउटै वडा'));
            }
        }

        if (empty($hits)) {
            return ['found' => false, 'hard_block' => false, 'matches' => []];
        }

        $matches = array_map(function ($hit) {
            $b = $hit['base'];
            return [
                'id'          => $b['id'],
                'unique_code' => $b['unique_code'],
                'status'      => $b['status'],
                'name'        => $b['name'],
                'ward_number' => $b['ward_number'],
                'reasons'     => $hit['reasons'],
                'hard'        => $hit['hard'],
                'edit_url'    => "/institutions/{$b['id']}",
            ];
        }, array_values($hits));

        return ['found' => true, 'hard_block' => $hardBlock, 'matches' => $matches];
    }
}
