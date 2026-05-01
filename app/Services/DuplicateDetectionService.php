<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Institution;
use Illuminate\Support\Facades\DB;

class DuplicateDetectionService
{
    // ~20-metre bounding box at Nepal's latitude (~28°N)
    const GPS_TOL_LAT = 0.00018;
    const GPS_TOL_LNG = 0.00020;

    // ─────────────────────────────────────────────────────────────────────────
    // Households
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns ['found' => bool, 'matches' => [...]]
     * Each match: id, unique_code, status, respondent_name, ward_number, reasons[], edit_url
     */
    public function checkHousehold(array $data, ?int $excludeId = null): array
    {
        $wardId     = $data['ward_id']           ?? null;
        $gpsLat     = is_numeric($data['gps_lat'] ?? '')  ? (float)$data['gps_lat']  : null;
        $gpsLng     = is_numeric($data['gps_lng'] ?? '')  ? (float)$data['gps_lng']  : null;
        $phone      = preg_replace('/\D/', '', $data['respondent_contact'] ?? '');
        $respName   = mb_strtolower(trim($data['respondent_name'] ?? ''));
        $familyHead = mb_strtolower(trim($data['survey_data']['social']['family_head'] ?? ''));

        // Accumulator: id => ['record', 'reasons']
        $hits = [];

        $add = function (Household $h, string $reason) use (&$hits) {
            if (!isset($hits[$h->id])) {
                $hits[$h->id] = ['record' => $h, 'reasons' => []];
            }
            $hits[$h->id]['reasons'][] = $reason;
        };

        $base = Household::withoutTrashed()
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->with('ward:id,ward_number');

        // 1. GPS proximity — highest confidence
        if ($gpsLat !== null && $gpsLng !== null) {
            (clone $base)
                ->whereNotNull('gps_lat')->whereNotNull('gps_lng')
                ->whereRaw('ABS(gps_lat - ?) < ?',  [$gpsLat, self::GPS_TOL_LAT])
                ->whereRaw('ABS(gps_lng - ?) < ?',  [$gpsLng, self::GPS_TOL_LNG])
                ->select(['id', 'unique_code', 'status', 'respondent_name', 'ward_id', 'gps_lat', 'gps_lng'])
                ->each(fn($h) => $add($h, 'GPS स्थान नजिक (२० मिटर भित्र)'));
        }

        // 2. Respondent contact phone — high confidence
        if (strlen($phone) >= 7) {
            (clone $base)
                ->whereRaw("REGEXP_REPLACE(respondent_contact, '[^0-9]', '') = ?", [$phone])
                ->select(['id', 'unique_code', 'status', 'respondent_name', 'ward_id'])
                ->each(fn($h) => $add($h, 'सोही फोन नम्बर'));
        }

        // 3. Respondent name + same ward — medium confidence
        if ($respName && $wardId) {
            (clone $base)
                ->where('ward_id', $wardId)
                ->whereRaw('LOWER(TRIM(respondent_name)) = ?', [$respName])
                ->select(['id', 'unique_code', 'status', 'respondent_name', 'ward_id'])
                ->each(fn($h) => $add($h, 'सोही उत्तरदाताको नाम (एउटै वडा)'));
        }

        // 4. Family head name in survey_data JSON + same ward
        if ($familyHead && $wardId) {
            (clone $base)
                ->where('ward_id', $wardId)
                ->whereNotNull('survey_data')
                ->whereRaw("LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.social.family_head')))) = ?", [$familyHead])
                ->select(['id', 'unique_code', 'status', 'respondent_name', 'ward_id'])
                ->each(fn($h) => $add($h, 'सोही परिवार प्रमुखको नाम (एउटै वडा)'));
        }

        if (empty($hits)) {
            return ['found' => false, 'matches' => []];
        }

        $matches = array_map(function ($hit) {
            $h = $hit['record'];
            return [
                'id'             => $h->id,
                'unique_code'    => $h->unique_code,
                'status'         => $h->status,
                'respondent_name'=> $h->respondent_name,
                'ward_number'    => $h->ward?->ward_number,
                'reasons'        => $hit['reasons'],
                'edit_url'       => "/households/{$h->id}",
            ];
        }, array_values($hits));

        return ['found' => true, 'matches' => $matches];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Institutions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns ['found' => bool, 'hard_block' => bool, 'matches' => [...]]
     * hard_block = true when registration_number matches exactly (same registry ≠ different record)
     */
    public function checkInstitution(array $data, ?int $excludeId = null): array
    {
        $wardId   = $data['ward_id']            ?? null;
        $gpsLat   = is_numeric($data['gps_lat'] ?? '') ? (float)$data['gps_lat'] : null;
        $gpsLng   = is_numeric($data['gps_lng'] ?? '') ? (float)$data['gps_lng'] : null;
        $regNum   = trim($data['registration_number'] ?? '');
        $nameNp   = mb_strtolower(trim($data['name_np'] ?? ''));
        $nameEn   = mb_strtolower(trim($data['name_en'] ?? ''));

        $hits      = [];
        $hardBlock = false;

        $add = function (Institution $inst, string $reason, bool $isHard = false) use (&$hits, &$hardBlock) {
            if ($isHard) $hardBlock = true;
            if (!isset($hits[$inst->id])) {
                $hits[$inst->id] = ['record' => $inst, 'reasons' => [], 'hard' => false];
            }
            $hits[$inst->id]['reasons'][] = $reason;
            if ($isHard) $hits[$inst->id]['hard'] = true;
        };

        $base = Institution::withoutTrashed()
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->with('ward:id,ward_number');

        // 1. Registration number — hard block
        if ($regNum) {
            (clone $base)
                ->whereRaw('TRIM(registration_number) = ?', [$regNum])
                ->select(['id', 'unique_code', 'status', 'name_np', 'name_en', 'ward_id', 'registration_number'])
                ->each(fn($inst) => $add($inst, 'सोही दर्ता नम्बर — ' . $regNum, true));
        }

        // 2. GPS proximity — high confidence
        if ($gpsLat !== null && $gpsLng !== null) {
            (clone $base)
                ->whereNotNull('gps_lat')->whereNotNull('gps_lng')
                ->whereRaw('ABS(gps_lat - ?) < ?', [$gpsLat, self::GPS_TOL_LAT])
                ->whereRaw('ABS(gps_lng - ?) < ?', [$gpsLng, self::GPS_TOL_LNG])
                ->select(['id', 'unique_code', 'status', 'name_np', 'name_en', 'ward_id'])
                ->each(fn($inst) => $add($inst, 'GPS स्थान नजिक (२० मिटर भित्र)'));
        }

        // 3. Same Nepali name + same ward
        if ($nameNp && $wardId) {
            (clone $base)
                ->where('ward_id', $wardId)
                ->whereRaw('LOWER(TRIM(name_np)) = ?', [$nameNp])
                ->select(['id', 'unique_code', 'status', 'name_np', 'name_en', 'ward_id'])
                ->each(fn($inst) => $add($inst, 'सोही नाम (नेपाली) — एउटै वडा'));
        }

        // 4. Same English name + same ward (if provided)
        if ($nameEn && $wardId) {
            (clone $base)
                ->where('ward_id', $wardId)
                ->whereRaw('LOWER(TRIM(name_en)) = ?', [$nameEn])
                ->select(['id', 'unique_code', 'status', 'name_np', 'name_en', 'ward_id'])
                ->each(fn($inst) => $add($inst, 'सोही नाम (अंग्रेजी) — एउटै वडा'));
        }

        if (empty($hits)) {
            return ['found' => false, 'hard_block' => false, 'matches' => []];
        }

        $matches = array_map(function ($hit) {
            $inst = $hit['record'];
            return [
                'id'          => $inst->id,
                'unique_code' => $inst->unique_code,
                'status'      => $inst->status,
                'name'        => $inst->name_np ?: $inst->name_en,
                'ward_number' => $inst->ward?->ward_number,
                'reasons'     => $hit['reasons'],
                'hard'        => $hit['hard'],
                'edit_url'    => "/institutions/{$inst->id}",
            ];
        }, array_values($hits));

        return ['found' => true, 'hard_block' => $hardBlock, 'matches' => $matches];
    }
}
