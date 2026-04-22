<?php

namespace App\Services;

use App\Models\SurveyOption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * CrossTabService — generates cross-tabulation matrices for public reports.
 *
 * Usage:
 *   $result = CrossTabService::tab(
 *       query:    HouseholdMember::query()->whereHas('household', ...),
 *       rowExpr:  'gender',
 *       colExpr:  CrossTabService::ageGroupExpr(),
 *       rowDef:   CrossTabService::genderLabels(),
 *       colDef:   CrossTabService::ageGroupLabels(),
 *       cacheKey: "demo_gender_age_{$lgId}",
 *   );
 *
 * Return shape:
 *   [
 *     'rows'        => ['male', 'female', 'other'],
 *     'row_labels'  => ['male' => 'पुरुष', ...],
 *     'cols'        => ['0-3', '4-5', ...],
 *     'col_labels'  => ['0-3' => '०–३ वर्ष', ...],
 *     'matrix'      => ['male' => ['0-3' => 12, '4-5' => 8, ...], ...],
 *     'row_totals'  => ['male' => 120, ...],
 *     'col_totals'  => ['0-3' => 45, ...],
 *     'grand_total' => 350,
 *   ]
 */
class CrossTabService
{
    // ── Age buckets ─────────────────────────────────────────────────────────
    public const AGE_BUCKETS = [
        '0-4'   => [0,  4,  '०–४ वर्ष'],
        '5-9'   => [5,  9,  '५–९ वर्ष'],
        '10-14' => [10, 14, '१०–१४ वर्ष'],
        '15-19' => [15, 19, '१५–१९ वर्ष'],
        '20-24' => [20, 24, '२०–२४ वर्ष'],
        '25-29' => [25, 29, '२५–२९ वर्ष'],
        '30-34' => [30, 34, '३०–३४ वर्ष'],
        '35-39' => [35, 39, '३५–३९ वर्ष'],
        '40-44' => [40, 44, '४०–४४ वर्ष'],
        '45-49' => [45, 49, '४५–४९ वर्ष'],
        '50-54' => [50, 54, '५०–५४ वर्ष'],
        '55-59' => [55, 59, '५५–५९ वर्ष'],
        '60-64' => [60, 64, '६०–६४ वर्ष'],
        '65+'   => [65, 999, '६५+ वर्ष'],
    ];

    // ── Age group CASE expression ────────────────────────────────────────────
    public static function ageGroupExpr(string $col = 'age'): string
    {
        // Properly quote qualified names like "household_members.age" → `household_members`.`age`
        $quotedCol = str_contains($col, '.')
            ? implode('.', array_map(fn($p) => "`{$p}`", explode('.', $col)))
            : "`{$col}`";

        $cases = '';
        foreach (self::AGE_BUCKETS as $key => [$min, $max, $_label]) {
            if ($max === 999) {
                $cases .= " WHEN {$quotedCol} >= {$min} THEN '{$key}'";
            } else {
                $cases .= " WHEN {$quotedCol} BETWEEN {$min} AND {$max} THEN '{$key}'";
            }
        }
        return "CASE{$cases} ELSE 'unknown' END";
    }

    // ── Age group labels (ordered) ───────────────────────────────────────────
    public static function ageGroupLabels(): array
    {
        $labels = [];
        foreach (self::AGE_BUCKETS as $key => [, , $label]) {
            $labels[$key] = $label;
        }
        return $labels;
    }

    // ── Gender labels ────────────────────────────────────────────────────────
    public static function genderLabels(): array
    {
        return [
            'male'   => 'पुरुष',
            'female' => 'महिला',
            'other'  => 'अन्य',
        ];
    }

    // ── Ward column definitions from Ward collection ─────────────────────────
    public static function wardColDef(iterable $wards): array
    {
        $def = [];
        foreach ($wards as $ward) {
            $def[(string)$ward->ward_number] = 'वडा ' . $ward->ward_number;
        }
        ksort($def, SORT_NUMERIC);
        return $def;
    }

    // ── Core cross-tab engine ────────────────────────────────────────────────
    /**
     * @param  Builder  $query   Base query (scoped to LG/ward/filter already)
     * @param  string   $rowExpr SQL expression or column name for rows
     * @param  string   $colExpr SQL expression or column name for columns
     * @param  array    $rowDef  Ordered [key => Nepali label] for rows; empty = auto-discover
     * @param  array    $colDef  Ordered [key => Nepali label] for cols; empty = auto-discover
     * @param  string   $cacheKey Cache key; empty = no cache
     * @param  int      $ttl     Cache TTL in seconds
     */
    public static function tab(
        Builder $query,
        string  $rowExpr,
        string  $colExpr,
        array   $rowDef    = [],
        array   $colDef    = [],
        string  $cacheKey  = '',
        int     $ttl       = 3600
    ): array {
        $compute = function () use ($query, $rowExpr, $colExpr, $rowDef, $colDef) {
            // Clone query and add GROUP BY select
            $q = clone $query;
            $q->getQuery()->columns = null; // clear any pre-existing select to avoid ONLY_FULL_GROUP_BY errors
            $rows = $q->selectRaw("({$rowExpr}) as _row, ({$colExpr}) as _col, COUNT(*) as cnt")
                ->groupBy(DB::raw("_row"), DB::raw("_col"))
                ->orderBy(DB::raw("_row"))
                ->orderBy(DB::raw("_col"))
                ->get();

            // Determine row/col keys
            $rowKeys = array_keys($rowDef);
            $colKeys = array_keys($colDef);

            // Auto-discover if not provided
            if (empty($rowKeys)) {
                $rowKeys = $rows->pluck('_row')->unique()->filter()->sort()->values()->toArray();
                foreach ($rowKeys as $k) {
                    if (!isset($rowDef[$k])) $rowDef[$k] = $k;
                }
            }
            if (empty($colKeys)) {
                $colKeys = $rows->pluck('_col')->unique()->filter()->sort()->values()->toArray();
                foreach ($colKeys as $k) {
                    if (!isset($colDef[$k])) $colDef[$k] = $k;
                }
            }

            // Build zero-filled matrix
            $matrix = [];
            foreach ($rowKeys as $r) {
                $matrix[$r] = [];
                foreach ($colKeys as $c) {
                    $matrix[$r][$c] = 0;
                }
            }

            // Fill matrix
            foreach ($rows as $row) {
                $r = (string)$row->_row;
                $c = (string)$row->_col;
                if (isset($matrix[$r][$c])) {
                    $matrix[$r][$c] = (int)$row->cnt;
                } elseif (!isset($rowDef[$r]) && !empty($rowDef)) {
                    // skip unknown rows if rowDef was explicitly provided
                } elseif (!isset($colDef[$c]) && !empty($colDef)) {
                    // skip unknown cols
                } else {
                    // dynamic mode — add to matrix
                    if (!isset($matrix[$r])) {
                        $matrix[$r] = array_fill_keys($colKeys, 0);
                        $rowKeys[]  = $r;
                        $rowDef[$r] = $r;
                    }
                    if (!in_array($c, $colKeys)) {
                        foreach ($matrix as &$matRow) $matRow[$c] = 0;
                        $colKeys[]  = $c;
                        $colDef[$c] = $c;
                    }
                    $matrix[$r][$c] = (int)$row->cnt;
                }
            }

            // Row totals
            $rowTotals = [];
            foreach ($rowKeys as $r) {
                $rowTotals[$r] = array_sum($matrix[$r] ?? []);
            }

            // Col totals
            $colTotals = [];
            foreach ($colKeys as $c) {
                $colTotals[$c] = 0;
                foreach ($rowKeys as $r) {
                    $colTotals[$c] += $matrix[$r][$c] ?? 0;
                }
            }

            return [
                'rows'        => $rowKeys,
                'row_labels'  => $rowDef,
                'cols'        => $colKeys,
                'col_labels'  => $colDef,
                'matrix'      => $matrix,
                'row_totals'  => $rowTotals,
                'col_totals'  => $colTotals,
                'grand_total' => array_sum($rowTotals),
            ];
        };

        if ($cacheKey) {
            return Cache::remember($cacheKey, $ttl, $compute);
        }

        return $compute();
    }

    // ── Simple frequency (single column) ────────────────────────────────────
    /**
     * Returns ['key' => count] sorted by count desc, with Nepali labels if provided.
     */
    public static function freq(
        Builder $query,
        string  $colExpr,
        array   $labelDef  = [],
        string  $cacheKey  = '',
        int     $ttl       = 3600
    ): array {
        $compute = function () use ($query, $colExpr, $labelDef) {
            $q = clone $query;
            $q->getQuery()->columns = null; // clear any pre-existing select to avoid ONLY_FULL_GROUP_BY errors
            $rows = $q->selectRaw("({$colExpr}) as _val, COUNT(*) as cnt")
                ->groupBy(DB::raw("_val"))
                ->orderByDesc('cnt')
                ->get();

            $result = [];
            foreach ($rows as $row) {
                $key = (string)$row->_val;
                if (!$key) continue;
                $result[] = [
                    'key'   => $key,
                    'label' => $labelDef[$key] ?? $key,
                    'count' => (int)$row->cnt,
                ];
            }
            return $result;
        };

        if ($cacheKey) {
            return Cache::remember($cacheKey, $ttl, $compute);
        }

        return $compute();
    }

    // ── Survey-option label loader ───────────────────────────────────────────
    /**
     * Load [value => label] map from survey_options for a given category.
     * Cached for 1 hour.  Falls back to the value itself if no match found.
     * Field-to-category aliases handled here (e.g. solid_waste_management → waste_management).
     */
    public static function optionLabels(string $field): array
    {
        // Map DB column name → survey_options.category when they differ
        $categoryMap = [
            'solid_waste_management' => 'waste_management',
            'primary_income_source'  => 'income_source',
            'mother_tongue'          => 'language',
        ];

        // Static fallback labels for fields with no survey_options category
        $staticFallback = [
            'annual_income_range' => [
                'below_50000'    => 'रु ५०,००० भन्दा कम',
                '50000_100000'   => 'रु ५०,०००–१,००,०००',
                '50k_100k'       => 'रु ५०,०००–१,००,०००',
                '100000_200000'  => 'रु १,००,०००–२,००,०००',
                '100k_200k'      => 'रु १,००,०००–२,००,०००',
                '200000_500000'  => 'रु २,००,०००–५,००,०००',
                '200k_500k'      => 'रु २,००,०००–५,००,०००',
                'above_500000'   => 'रु ५,००,००० माथि',
                'above_500k'     => 'रु ५,००,००० माथि',
                'other'          => 'अन्य',
            ],
            'annual_expenditure_range' => [
                'below_50000'    => 'रु ५०,००० भन्दा कम',
                '50000_100000'   => 'रु ५०,०००–१,००,०००',
                '50k_100k'       => 'रु ५०,०००–१,००,०००',
                '100000_200000'  => 'रु १,००,०००–२,००,०००',
                '100k_200k'      => 'रु १,००,०००–२,००,०००',
                '200000_500000'  => 'रु २,००,०००–५,००,०००',
                '200k_500k'      => 'रु २,००,०००–५,००,०००',
                'above_500000'   => 'रु ५,००,००० माथि',
                'above_500k'     => 'रु ५,००,००० माथि',
                'other'          => 'अन्य',
            ],
        ];

        if (isset($staticFallback[$field])) {
            return $staticFallback[$field];
        }

        $category = $categoryMap[$field] ?? $field;

        return Cache::remember("opt_labels.{$category}", 3600, function () use ($category) {
            return SurveyOption::where('category', $category)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('label_ne', 'value')
                ->toArray();
        });
    }

    // ── Cache key helper ─────────────────────────────────────────────────────
    /**
     * Build a stable cache key from LG id + filter params.
     */
    public static function cacheKey(string $prefix, int $lgId, array $filters = []): string
    {
        $filterStr = http_build_query(array_filter($filters, fn($v) => $v !== null && $v !== ''));
        return "crosstab.{$prefix}.{$lgId}." . ($filterStr ? md5($filterStr) : 'all');
    }
}
