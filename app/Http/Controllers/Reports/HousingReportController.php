<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\HouseholdHousing;
use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HousingReportController extends Controller
{
    /** Labels for each field value */
    private array $labels = [
        'wall_type' => [
            'rcc'          => 'RCC/Concrete',
            'brick_cement' => 'Brick (Cement)',
            'brick_mud'    => 'Brick (Mud)',
            'stone_cement' => 'Stone (Cement)',
            'stone_mud'    => 'Stone (Mud)',
            'bamboo'       => 'Bamboo',
            'wood'         => 'Wood',
            'other'        => 'Other',
        ],
        'roof_type' => [
            'rcc'    => 'RCC/Concrete',
            'tin'    => 'Tin/Metal',
            'tile'   => 'Tile',
            'thatch' => 'Thatch/Straw',
            'slate'  => 'Slate',
            'other'  => 'Other',
        ],
        'house_condition' => [
            'good'    => 'Good',
            'average' => 'Average',
            'poor'    => 'Poor',
            'damaged' => 'Damaged',
        ],
        'ownership_type' => [
            'owned'       => 'Owned',
            'rented'      => 'Rented',
            'institutional' => 'Institutional',
            'squatter'    => 'Squatter',
            'other'       => 'Other',
        ],
    ];

    private array $detailConfig = [
        'house_type' => [
            'title'   => 'Type of House (Wall Construction)',
            'column'  => 'wall_type',
            'color'   => 'blue',
            'icon'    => '🏗️',
        ],
        'roof' => [
            'title'  => 'Roof Type',
            'column' => 'roof_type',
            'color'  => 'green',
            'icon'   => '🏠',
        ],
        'condition' => [
            'title'  => 'House Condition',
            'column' => 'house_condition',
            'color'  => 'yellow',
            'icon'   => '🔍',
        ],
        'ownership' => [
            'title'  => 'House Ownership',
            'column' => 'ownership_type',
            'color'  => 'purple',
            'icon'   => '🔑',
        ],
        'rooms' => [
            'title'  => 'Room Distribution',
            'column' => 'total_rooms',
            'color'  => 'blue',
            'icon'   => '🛏️',
        ],
    ];

    /** Overview page */
    public function index(Request $request)
    {
        $user       = auth()->user();
        $wardIds    = $this->resolveWardIds($user);
        $wardFilter = $request->integer('ward_id') ?: null;

        if ($wardFilter && in_array($wardFilter, $wardIds)) {
            $scope = [$wardFilter];
        } else {
            $scope     = $wardIds;
            $wardFilter = null;
        }

        $base = HouseholdHousing::whereHas('household', fn($q) =>
            $q->whereIn('ward_id', $scope)->where('status', 'published')
        );

        $totalHouseholds = (clone $base)->count();

        $wallData      = $this->breakdown($base, 'wall_type',      $this->labels['wall_type']);
        $roofData      = $this->breakdown($base, 'roof_type',      $this->labels['roof_type']);
        $conditionData = $this->breakdown($base, 'house_condition', $this->labels['house_condition']);
        $ownershipData = $this->breakdown($base, 'ownership_type', $this->labels['ownership_type']);
        $roomData      = $this->roomRangeBreakdown($base);

        $wards = Ward::where('local_government_id', $user->local_government_id)
            ->orderBy('ward_number')->get();

        return view('reports.housing.index', compact(
            'totalHouseholds', 'wallData', 'roofData', 'conditionData',
            'ownershipData', 'roomData', 'wards', 'wardFilter'
        ));
    }

    /** Detail page for one category */
    public function detail(Request $request, string $type)
    {
        abort_unless(array_key_exists($type, $this->detailConfig), 404);

        $user       = auth()->user();
        $wardIds    = $this->resolveWardIds($user);
        $wardFilter = $request->integer('ward_id') ?: null;

        if ($wardFilter && in_array($wardFilter, $wardIds)) {
            $scope = [$wardFilter];
        } else {
            $scope      = $wardIds;
            $wardFilter = null;
        }

        $config = $this->detailConfig[$type];
        $column = $config['column'];

        $base = HouseholdHousing::whereHas('household', fn($q) =>
            $q->whereIn('ward_id', $scope)->where('status', 'published')
        );

        $total = (clone $base)->count();

        if ($type === 'rooms') {
            $breakdown = $this->roomRangeBreakdown($base);
        } else {
            $labelMap  = $this->labels[$column] ?? [];
            $breakdown = $this->breakdown($base, $column, $labelMap);
        }

        // Ward-wise breakdown
        $wards = Ward::where('local_government_id', $user->local_government_id)
            ->orderBy('ward_number')->get();

        if ($type === 'rooms') {
            $wardBreakdown = $this->wardRoomBreakdown($wardIds, $scope);
        } else {
            $wardBreakdown = $this->wardColumnBreakdown($wardIds, $scope, $column, $this->labels[$column] ?? []);
        }

        return view('reports.housing.detail', compact(
            'config', 'type', 'breakdown', 'total',
            'wards', 'wardFilter', 'wardBreakdown'
        ));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function resolveWardIds($user): array
    {
        $lgId = $user->local_government_id;
        $allWards = Ward::where('local_government_id', $lgId)->pluck('id')->toArray();

        if ($user->role === 'ward_reviewer' || $user->role === 'enumerator') {
            return array_intersect($user->ward_ids ?? [], $allWards) ?: $allWards;
        }

        return $allWards;
    }

    private function breakdown($baseQuery, string $column, array $labelMap): array
    {
        $rows = (clone $baseQuery)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column, DB::raw('count(*) as count'))
            ->groupBy($column)
            ->orderByDesc('count')
            ->get();

        $total = $rows->sum('count');
        if (!$total) return [];

        return $rows->map(fn($r) => [
            'value'      => $r->$column,
            'label'      => $labelMap[$r->$column] ?? ucfirst(str_replace('_', ' ', $r->$column)),
            'count'      => $r->count,
            'percentage' => round(($r->count / $total) * 100, 1),
        ])->toArray();
    }

    private function roomRangeBreakdown($baseQuery): array
    {
        $ranges = [
            '1'    => '1 Room',
            '2'    => '2 Rooms',
            '3'    => '3 Rooms',
            '4'    => '4 Rooms',
            '5'    => '5 Rooms',
            '6-8'  => '6–8 Rooms',
            '9+'   => '9+ Rooms',
        ];

        $rows = (clone $baseQuery)
            ->whereNotNull('total_rooms')
            ->select(DB::raw("
                CASE
                    WHEN total_rooms = 1 THEN '1'
                    WHEN total_rooms = 2 THEN '2'
                    WHEN total_rooms = 3 THEN '3'
                    WHEN total_rooms = 4 THEN '4'
                    WHEN total_rooms = 5 THEN '5'
                    WHEN total_rooms BETWEEN 6 AND 8 THEN '6-8'
                    ELSE '9+'
                END as range_key
            "), DB::raw('count(*) as count'))
            ->groupBy('range_key')
            ->get()
            ->keyBy('range_key');

        $total = $rows->sum('count');
        if (!$total) return [];

        $result = [];
        foreach ($ranges as $key => $label) {
            $count = $rows->get($key)?->count ?? 0;
            if ($count === 0) continue;
            $result[] = [
                'value'      => $key,
                'label'      => $label,
                'count'      => $count,
                'percentage' => round(($count / $total) * 100, 1),
            ];
        }
        return $result;
    }

    private function wardColumnBreakdown(array $allWardIds, array $scopeIds, string $column, array $labelMap): array
    {
        $rows = HouseholdHousing::whereHas('household', fn($q) =>
                $q->whereIn('ward_id', $scopeIds)->where('status', 'published')
            )
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->join('households', 'household_housing.household_id', '=', 'households.id')
            ->join('wards', 'households.ward_id', '=', 'wards.id')
            ->select('wards.ward_number', $column, DB::raw('count(*) as count'))
            ->groupBy('wards.ward_number', $column)
            ->orderBy('wards.ward_number')
            ->get();

        $wards = $rows->pluck('ward_number')->unique()->sort()->values();
        $categories = $rows->pluck($column)->unique()->values();

        $matrix = [];
        foreach ($wards as $w) {
            $wardRows = $rows->where('ward_number', $w);
            $total = $wardRows->sum('count');
            $matrix[$w] = [
                'total'    => $total,
                'breakdown' => [],
            ];
            foreach ($categories as $cat) {
                $count = $wardRows->firstWhere($column, $cat)?->count ?? 0;
                $matrix[$w]['breakdown'][$cat] = [
                    'label'      => $labelMap[$cat] ?? ucfirst(str_replace('_', ' ', $cat)),
                    'count'      => $count,
                    'percentage' => $total ? round(($count / $total) * 100, 1) : 0,
                ];
            }
        }

        return [
            'wards'      => $wards->toArray(),
            'categories' => $categories->map(fn($c) => [
                'value' => $c,
                'label' => $labelMap[$c] ?? ucfirst(str_replace('_', ' ', $c)),
            ])->toArray(),
            'matrix'     => $matrix,
        ];
    }

    private function wardRoomBreakdown(array $allWardIds, array $scopeIds): array
    {
        $rows = HouseholdHousing::whereHas('household', fn($q) =>
                $q->whereIn('ward_id', $scopeIds)->where('status', 'published')
            )
            ->whereNotNull('total_rooms')
            ->join('households', 'household_housing.household_id', '=', 'households.id')
            ->join('wards', 'households.ward_id', '=', 'wards.id')
            ->select('wards.ward_number', DB::raw("
                CASE
                    WHEN total_rooms = 1 THEN '1'
                    WHEN total_rooms = 2 THEN '2'
                    WHEN total_rooms = 3 THEN '3'
                    WHEN total_rooms = 4 THEN '4'
                    WHEN total_rooms = 5 THEN '5'
                    WHEN total_rooms BETWEEN 6 AND 8 THEN '6-8'
                    ELSE '9+'
                END as range_key
            "), DB::raw('count(*) as count'))
            ->groupBy('wards.ward_number', 'range_key')
            ->orderBy('wards.ward_number')
            ->get();

        $categories = ['1','2','3','4','5','6-8','9+'];
        $labels = ['1 Room','2 Rooms','3 Rooms','4 Rooms','5 Rooms','6–8 Rooms','9+ Rooms'];
        $wards = $rows->pluck('ward_number')->unique()->sort()->values();

        $matrix = [];
        foreach ($wards as $w) {
            $wardRows = $rows->where('ward_number', $w);
            $total = $wardRows->sum('count');
            $matrix[$w] = ['total' => $total, 'breakdown' => []];
            foreach ($categories as $i => $cat) {
                $count = $wardRows->firstWhere('range_key', $cat)?->count ?? 0;
                $matrix[$w]['breakdown'][$cat] = [
                    'label'      => $labels[$i],
                    'count'      => $count,
                    'percentage' => $total ? round(($count / $total) * 100, 1) : 0,
                ];
            }
        }

        return [
            'wards'      => $wards->toArray(),
            'categories' => collect($categories)->map(fn($v, $i) => ['value' => $v, 'label' => $labels[$i]])->toArray(),
            'matrix'     => $matrix,
        ];
    }
}
