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

            // Build lowercase→canonical key maps for case-insensitive matching
            $rowKeyMap = [];
            foreach ($rowKeys as $k) { $rowKeyMap[strtolower($k)] = $k; }
            $colKeyMap = [];
            foreach ($colKeys as $k) { $colKeyMap[strtolower($k)] = $k; }

            // Fill matrix
            foreach ($rows as $row) {
                $r = (string)$row->_row;
                $c = (string)$row->_col;
                // Normalize to canonical key via case-insensitive lookup
                $r = $rowKeyMap[strtolower($r)] ?? $r;
                $c = $colKeyMap[strtolower($c)] ?? $c;
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

            // Merge rows that display the same label.
            //
            // optionLabels() deliberately adds Kobo alias keys that carry the *same*
            // label as the system key — electricity_source maps both 'National_grid'
            // and 'Grid' onto 'national_grid', so all three read "राष्ट्रिय ग्रिड".
            // That is needed so data stored in either format still finds a bucket,
            // but they are alternative spellings of one answer, not separate answers.
            // Without this, every aliased option rendered as two or three identical
            // rows — one carrying the counts and the rest showing zeros. Twelve
            // categories have aliases, so it affected most report tables.
            //
            // Counts are summed rather than discarded: real data does live under the
            // alias keys wherever Google Sync wrote Kobo values.
            $canonicalForLabel = [];
            $mergedRowKeys     = [];

            foreach ($rowKeys as $r) {
                $label = (string) ($rowDef[$r] ?? $r);

                if (isset($canonicalForLabel[$label])) {
                    $canon = $canonicalForLabel[$label];
                    foreach ($colKeys as $c) {
                        $matrix[$canon][$c] = ($matrix[$canon][$c] ?? 0) + ($matrix[$r][$c] ?? 0);
                    }
                    unset($matrix[$r], $rowDef[$r]);
                    continue;
                }

                $canonicalForLabel[$label] = $r;
                $mergedRowKeys[]           = $r;
            }

            $rowKeys = $mergedRowKeys;

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

            // Build lowercase→canonical key map for case-insensitive label lookup
            $labelKeyMap = [];
            foreach (array_keys($labelDef) as $k) {
                $labelKeyMap[strtolower($k)] = $k;
            }

            $result = [];
            foreach ($rows as $row) {
                $key = (string)$row->_val;
                if (!$key) continue;
                // Normalize to canonical label key (case-insensitive)
                $canonKey = $labelKeyMap[strtolower($key)] ?? null;
                $result[] = [
                    'key'   => $key,
                    'label' => ($canonKey !== null ? ($labelDef[$canonKey] ?? $key) : $key),
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
            'vt_subject'             => 'vocational_training_subject',
            'Farm_Challenges'        => 'farm_challenge',
            'loan_sources'           => 'loan_source',
            'type_of_vehicle'        => 'vehicle_type',
            'addrslocation'          => 'addrslocation',
            'occupation'             => 'occupation_type',
        ];

        // Static fallback labels for fields with no survey_options category
        $staticFallback = [
            'agriculture' => [
                'yes' => 'कृषि गर्नुहुन्छ',
                'no'  => 'कृषि गर्दैन',
            ],
            'vehicle' => [
                'yes' => 'सवारी साधन छ',
                'no'  => 'सवारी साधन छैन',
            ],
            'owns_land' => [
                'yes' => 'जमिन स्वामित्व छ',
                'no'  => 'जमिन छैन',
            ],
            'uses_chemical_fertilizer' => [
                'yes' => 'रासायनिक मल प्रयोग गर्छ',
                'no'  => 'प्रयोग गर्दैन',
            ],
            'uses_organic_fertilizer' => [
                'yes' => 'जैविक मल प्रयोग गर्छ',
                'no'  => 'प्रयोग गर्दैन',
            ],
            'sells_produce' => [
                'yes' => 'उपज बिक्री गर्छ',
                'no'  => 'बिक्री गर्दैन',
            ],
            'has_agri_loan' => [
                'yes' => 'कृषि ऋण छ',
                'no'  => 'कृषि ऋण छैन',
            ],
            'has_building_permit' => [
                '1' => 'नक्शा पास छ',
                '0' => 'नक्शा पास छैन',
            ],
            'is_at_risk' => [
                '1' => 'जोखिमग्रस्त',
                '0' => 'सुरक्षित',
            ],
            'has_insurance' => [
                '1' => 'बीमा छ',
                '0' => 'बीमा छैन',
            ],
            'ss_allowance' => [
                '1' => 'सामाजिक सुरक्षा भत्ता पाउँछ',
                '0' => 'भत्ता पाउँदैनन्',
            ],
            'livestock_kind' => [
                'Cow'     => 'गाई',       'cow'     => 'गाई',
                'OX'      => 'गोरु',      'ox'      => 'गोरु',     'oxen'    => 'गोरु',
                'Buffalo' => 'भैंसी',     'buffalo' => 'भैंसी',
                'Goats'   => 'बाख्रा',    'goats'   => 'बाख्रा',
                'Sheep'   => 'भेडा',      'sheep'   => 'भेडा',
                'Swine'   => 'बंगुर',     'swine'   => 'बंगुर',    'pigs'    => 'बंगुर',
                'Horse'   => 'घोडा',      'horse'   => 'घोडा',
                'Poultry' => 'कुखुरा',    'poultry' => 'कुखुरा',
                'Ducks'   => 'हाँस',      'ducks'   => 'हाँस',
                'Rabbit'  => 'खरायो',     'rabbit'  => 'खरायो',
                'Yak'     => 'चौरी/याक',  'yak'     => 'चौरी/याक',
                'other'   => 'अन्य',      'Other'   => 'अन्य',
            ],
            'disability_card' => [
                'red'    => 'रातो (पूर्ण अशक्त)',
                'blue'   => 'निलो (अति अशक्त)',
                'yellow' => 'पहेंलो (मध्यम अशक्त)',
                'white'  => 'सेतो (सामान्य अशक्त)',
            ],
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
            $labels = SurveyOption::where('category', $category)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('label_ne', 'value')
                ->toArray();

            // Augment with Kobo-format aliases (Google Sync stored raw Kobo choice names
            // before normalization was added). Maps alias → same label as system key.
            foreach ((CrossTabService::KOBO_LABEL_ALIASES[$category] ?? []) as $koboVal => $sysKey) {
                if (!isset($labels[$koboVal]) && isset($labels[$sysKey])) {
                    $labels[$koboVal] = $labels[$sysKey];
                }
            }

            return $labels;
        });
    }

    /** Kobo choice names → canonical survey_option key (for display fallback only). */
    private const KOBO_LABEL_ALIASES = [
        'water_source' => [
            'Tap_house'     => 'tap_inside',  'Tap_public'    => 'tap_outside',
            'covered_well'  => 'well',         'open_well'     => 'well',
            'Jaruwa'        => 'tube_well',    'Tube_well'     => 'tube_well',
            'River'         => 'river',        'नदी/खोला'     => 'river',
            'Spring'        => 'spring',       'मूल/झरना'     => 'spring',
            'Rain_water'    => 'rain',         'वर्षाको पानी' => 'rain',
            'Jar_water'     => 'jar_water',    'इनार/कुवा'    => 'well',
        ],
        'toilet_type' => [
            'Flush'       => 'flush',    'Water_seal'  => 'flush',
            'Simple_pit'  => 'pit',      'no_toilet'   => 'none',
            'No_toilet'   => 'none',
        ],
        'sanitation_type' => [
            'Standard_septictank'     => 'flush_septic',
            'Standard_publicdrainage' => 'flush_piped',
            'normal'                  => 'simple_pit',
            'no_toilet'               => 'open',       'No_toilet'    => 'open',
            'Simple_pit'              => 'simple_pit', 'Improved_pit' => 'improved_pit',
        ],
        'roof_type' => [
            'CGI_sheet'   => 'tin',    'Tin'         => 'tin',   'Metal_sheet' => 'tin',
            'RCC'         => 'rcc',    'Concrete'    => 'rcc',
            'Tile'        => 'tile',   'Clay_tile'   => 'tile',
            'Thatch'      => 'thatch', 'Straw'       => 'thatch',
            'Slate'       => 'slate',  'Stone_slate' => 'slate',
        ],
        'wall_type' => [
            'Brick_cement'  => 'brick_cement', 'Brick_mud'    => 'brick_mud',
            'Stone_cement'  => 'stone_cement', 'Stone_mud'    => 'stone_mud',
            'RCC'           => 'rcc',          'Bamboo'       => 'bamboo',
            'Wood'          => 'wood',         'Timber'       => 'wood',
        ],
        'electricity_source' => [
            'National_grid' => 'national_grid', 'Grid'    => 'national_grid',
            'Solar'         => 'solar',         'Generator' => 'generator',
            'Micro_hydro'   => 'micro_hydro',
        ],
        'cooking_fuel' => [
            'LPG'      => 'lpg',      'Firewood'      => 'firewood', 'Wood'   => 'firewood',
            'Biogas'   => 'biogas',   'Kerosene'      => 'kerosene',
            'Electricity' => 'electricity', 'Improved_cook' => 'improved_cook',
        ],
        'waste_management' => [
            'Organized_disposal' => 'govt_collection', 'Organized' => 'govt_collection',
            'Burned'   => 'burning', 'Composting' => 'composting', 'Compost' => 'composting',
            'Open_dumping' => 'dumping',
        ],
        'house_condition' => [
            'Good' => 'good', 'Average' => 'average', 'Poor' => 'poor', 'Damaged' => 'damaged',
        ],
        'child_vaccine' => [
            'दादुरा' => 'measles',
            'BCG'    => 'bcg',
            'DPT-1'  => 'dpt1',  'DPT1' => 'dpt1',
            'DPT-2'  => 'dpt2',  'DPT2' => 'dpt2',
            'DPT-3'  => 'dpt3',  'DPT3' => 'dpt3',
        ],
        'ethnicity' => [
            'माझी'   => 'majhi',   'जोगी'  => 'jogi',
            'मधेसी'  => 'madhesi', 'चौधरी' => 'chaudhary',
        ],
        'irrigation_source' => [
            'लिफ्ट/ पम्प सेट' => 'lift_pump',
            'लिफ्ट/पम्प सेट'  => 'lift_pump',
            'नहर'              => 'Canal',
            'ट्यूबवेल'         => 'Borewell',
            'नदी/खोला'         => 'River',
            'पोखरी'            => 'Pond',
            'वर्षा'            => 'Rainfall',
            'मूल'              => 'Spring',
        ],
    ];

    // ── Cache key helper ─────────────────────────────────────────────────────
    /**
     * Build a stable cache key from LG id + filter params.
     */
    public static function cacheKey(string $prefix, int $lgId, array $filters = []): string
    {
        $filterStr = http_build_query(array_filter($filters, fn($v) => $v !== null && $v !== ''));
        $v         = \Illuminate\Support\Facades\Cache::get('analytics_version', 0);
        return "crosstab.{$prefix}.{$lgId}.v{$v}." . ($filterStr ? md5($filterStr) : 'all');
    }
}
