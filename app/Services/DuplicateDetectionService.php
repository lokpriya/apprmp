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
    private ?array $hhGps        = null;  // [[id, code, status, name, ward_id, ward_number, lat, lng]]
    private ?array $hhPhone      = null;  // [normalized_phone => [[id, code, status, name, ward_id, ward_number]]]
    private ?array $hhName       = null;  // ["wardId:lower_name" => [[...]]]          — exact
    private ?array $hhHead       = null;  // ["wardId:lower_head" => [[...]]]          — exact
    private ?array $hhNameByWard = null;  // [wardId => [[...base, '_name' => name]]]  — fuzzy
    private ?array $hhHeadByWard = null;  // [wardId => [[...base, '_name' => head]]]  — fuzzy

    // ── Preloaded institution maps ─────────────────────────────────────────────
    private ?array $instGps    = null;  // [[id, code, status, name, ward_id, ward_number, lat, lng]]
    private ?array $instReg    = null;  // [reg_num => [[...]]]
    private ?array $instNameNp = null;  // ["wardId:lower_name_np" => [[...]]]         — exact
    private ?array $instNameEn = null;  // ["wardId:lower_name_en" => [[...]]]         — exact
    private ?array $instNpByWard = null; // [wardId => [[...base, '_name' => name_np]] — fuzzy
    private ?array $instEnByWard = null; // [wardId => [[...base, '_name' => name_en]] — fuzzy

    // ─────────────────────────────────────────────────────────────────────────
    // Preload (call once before a bulk import loop)
    // ─────────────────────────────────────────────────────────────────────────

    public function preloadHouseholds(array $wardIds): void
    {
        $this->hhGps = $this->hhPhone = $this->hhName = $this->hhHead = [];
        $this->hhNameByWard = $this->hhHeadByWard = [];

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

                $phone = self::normalizePhone($h->respondent_contact ?? '');
                if (strlen($phone) >= 7) {
                    $this->hhPhone[$phone][] = $base;
                }

                $name = mb_strtolower(trim($h->respondent_name ?? ''));
                if ($name) {
                    $this->hhName[$h->ward_id . ':' . $name][] = $base;
                    $this->hhNameByWard[$h->ward_id][] = ['_name' => $name] + $base;
                }

                $head = mb_strtolower(trim(data_get($h->survey_data, 'social.family_head', '')));
                if ($head) {
                    $this->hhHead[$h->ward_id . ':' . $head][] = $base;
                    $this->hhHeadByWard[$h->ward_id][] = ['_name' => $head] + $base;
                }
            });
    }

    public function preloadInstitutions(array $wardIds): void
    {
        $this->instGps = $this->instReg = $this->instNameNp = $this->instNameEn = [];
        $this->instNpByWard = $this->instEnByWard = [];

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
                    $this->instNpByWard[$inst->ward_id][] = ['_name' => $nameNp] + $base;
                }

                $nameEn = mb_strtolower(trim($inst->name_en ?? ''));
                if ($nameEn) {
                    $this->instNameEn[$inst->ward_id . ':' . $nameEn][] = $base;
                    $this->instEnByWard[$inst->ward_id][] = ['_name' => $nameEn] + $base;
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
        $phone      = self::normalizePhone($data['respondent_contact'] ?? '');
        $phoneWarn  = self::validateNepalPhone($data['respondent_contact'] ?? '');
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
                if (mb_strlen($respName) >= 4) {
                    foreach ($this->hhNameByWard[$wardId] ?? [] as $row) {
                        if (self::fuzzyMatch($respName, $row['_name'])) {
                            $addBase($row, "मिल्दोजुल्दो उत्तरदाताको नाम — \"{$row['_name']}\"");
                        }
                    }
                }
            }
            if ($familyHead && $wardId) {
                foreach ($this->hhHead[$wardId . ':' . $familyHead] ?? [] as $row) {
                    $addBase($row, 'सोही परिवार प्रमुखको नाम (एउटै वडा)');
                }
                if (mb_strlen($familyHead) >= 4) {
                    foreach ($this->hhHeadByWard[$wardId] ?? [] as $row) {
                        if (self::fuzzyMatch($familyHead, $row['_name'])) {
                            $addBase($row, "मिल्दोजुल्दो परिवार प्रमुखको नाम — \"{$row['_name']}\"");
                        }
                    }
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
                    ->whereNotNull('respondent_contact')
                    ->select(['id', 'unique_code', 'status', 'respondent_name', 'ward_id', 'respondent_contact'])
                    ->each(function ($h) use ($addModel, $phone) {
                        if (self::normalizePhone($h->respondent_contact ?? '') === $phone) {
                            $addModel($h, 'सोही फोन नम्बर');
                        }
                    });
            }
            if ($respName && $wardId) {
                (clone $base)
                    ->where('ward_id', $wardId)
                    ->whereNotNull('respondent_name')
                    ->select(['id', 'unique_code', 'status', 'respondent_name', 'ward_id'])
                    ->each(function ($h) use ($addModel, $respName) {
                        $existing = mb_strtolower(trim($h->respondent_name ?? ''));
                        if ($existing === $respName) {
                            $addModel($h, 'सोही उत्तरदाताको नाम (एउटै वडा)');
                        } elseif (mb_strlen($respName) >= 4 && self::fuzzyMatch($respName, $existing)) {
                            $addModel($h, "मिल्दोजुल्दो उत्तरदाताको नाम — \"{$h->respondent_name}\"");
                        }
                    });
            }
            if ($familyHead && $wardId) {
                (clone $base)
                    ->where('ward_id', $wardId)
                    ->whereNotNull('survey_data')
                    ->select(['id', 'unique_code', 'status', 'respondent_name', 'ward_id', 'survey_data'])
                    ->each(function ($h) use ($addModel, $familyHead) {
                        $existing = mb_strtolower(trim(data_get($h->survey_data, 'social.family_head', '')));
                        if (!$existing) return;
                        if ($existing === $familyHead) {
                            $addModel($h, 'सोही परिवार प्रमुखको नाम (एउटै वडा)');
                        } elseif (mb_strlen($familyHead) >= 4 && self::fuzzyMatch($familyHead, $existing)) {
                            $addModel($h, "मिल्दोजुल्दो परिवार प्रमुखको नाम — \"{$existing}\"");
                        }
                    });
            }
        }

        if (empty($hits)) {
            return ['found' => false, 'matches' => [], 'phone_warning' => $phoneWarn];
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

        return ['found' => true, 'matches' => $matches, 'phone_warning' => $phoneWarn];
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
                if (mb_strlen($nameNp) >= 4) {
                    foreach ($this->instNpByWard[$wardId] ?? [] as $row) {
                        if (self::fuzzyMatch($nameNp, $row['_name'])) {
                            $addBase($row, "मिल्दोजुल्दो नाम (नेपाली) — \"{$row['_name']}\"");
                        }
                    }
                }
            }
            if ($nameEn && $wardId) {
                foreach ($this->instNameEn[$wardId . ':' . $nameEn] ?? [] as $row) {
                    $addBase($row, 'सोही नाम (अंग्रेजी) — एउटै वडा');
                }
                if (mb_strlen($nameEn) >= 4) {
                    foreach ($this->instEnByWard[$wardId] ?? [] as $row) {
                        if (self::fuzzyMatch($nameEn, $row['_name'])) {
                            $addBase($row, "मिल्दोजुल्दो नाम (अंग्रेजी) — \"{$row['_name']}\"");
                        }
                    }
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
                    ->whereNotNull('name_np')
                    ->select(['id', 'unique_code', 'status', 'name_np', 'name_en', 'ward_id'])
                    ->each(function ($inst) use ($addModel, $nameNp) {
                        $existing = mb_strtolower(trim($inst->name_np ?? ''));
                        if (!$existing) return;
                        if ($existing === $nameNp) {
                            $addModel($inst, 'सोही नाम (नेपाली) — एउटै वडा');
                        } elseif (mb_strlen($nameNp) >= 4 && self::fuzzyMatch($nameNp, $existing)) {
                            $addModel($inst, "मिल्दोजुल्दो नाम (नेपाली) — \"{$inst->name_np}\"");
                        }
                    });
            }
            if ($nameEn && $wardId) {
                (clone $base)
                    ->where('ward_id', $wardId)
                    ->whereNotNull('name_en')
                    ->select(['id', 'unique_code', 'status', 'name_np', 'name_en', 'ward_id'])
                    ->each(function ($inst) use ($addModel, $nameEn) {
                        $existing = mb_strtolower(trim($inst->name_en ?? ''));
                        if (!$existing) return;
                        if ($existing === $nameEn) {
                            $addModel($inst, 'सोही नाम (अंग्रेजी) — एउटै वडा');
                        } elseif (mb_strlen($nameEn) >= 4 && self::fuzzyMatch($nameEn, $existing)) {
                            $addModel($inst, "मिल्दोजुल्दो नाम (अंग्रेजी) — \"{$inst->name_en}\"");
                        }
                    });
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

    // ─────────────────────────────────────────────────────────────────────────
    // Fuzzy helpers (character-level, Devanagari/Unicode safe)
    // ─────────────────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────────────────
    // Phone helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Strip all non-digits and remove the Nepal country code prefix (+977 / 977)
     * so that "9779841234567", "+977-984-123-4567", and "9841234567" all normalize
     * to the same 10-digit string for duplicate comparison.
     */
    public static function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if (strlen($digits) === 13 && str_starts_with($digits, '977')) {
            $digits = substr($digits, 3);
        }
        return $digits;
    }

    /**
     * Validate a Nepal phone number (after normalization).
     * Returns null when valid, or a Nepali warning string when suspicious.
     */
    public static function validateNepalPhone(string $raw): ?string
    {
        if (trim($raw) === '') return null;
        $digits = self::normalizePhone($raw);
        $len    = strlen($digits);
        if ($len === 0)  return null;
        if ($len < 7)    return 'फोन नम्बर धेरै छोटो छ — कम्तीमा ७ अंक हुनुपर्छ';
        if ($len > 10)   return 'फोन नम्बर धेरै लामो छ — बढीमा १० अंक (वा +९७७ सहित १३)';
        // 10-digit mobile must start with 96, 97, or 98 (Ncell / NTC / Smart)
        if ($len === 10 && !preg_match('/^9[678]/', $digits)) {
            return 'नेपाली मोबाइल नम्बर ९६/९७/९८ बाट सुरु हुनुपर्छ';
        }
        return null;
    }

    /**
     * Returns true when $a and $b differ by at most $maxDist characters but are
     * NOT identical (identical strings are already caught by exact-match checks).
     * Strings shorter than 4 characters are skipped to avoid false positives.
     */
    private static function fuzzyMatch(string $a, string $b, int $maxDist = 2): bool
    {
        if ($a === $b) return false;
        $la = mb_strlen($a);
        $lb = mb_strlen($b);
        if ($la < 4 || $lb < 4) return false;
        if (abs($la - $lb) > $maxDist) return false;
        return self::mbLevenshtein($a, $b) <= $maxDist;
    }

    /** Character-level Levenshtein distance — works correctly on multibyte strings. */
    private static function mbLevenshtein(string $a, string $b): int
    {
        $a  = mb_str_split($a);
        $b  = mb_str_split($b);
        $la = count($a);
        $lb = count($b);
        $dp = range(0, $lb);
        for ($i = 1; $i <= $la; $i++) {
            $prev = $i;
            for ($j = 1; $j <= $lb; $j++) {
                $cost     = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $new      = min($dp[$j - 1] + $cost, $dp[$j] + 1, $prev + 1);
                $dp[$j - 1] = $prev;
                $prev     = $new;
            }
            $dp[$lb] = $prev;
        }
        return $dp[$lb];
    }
}
