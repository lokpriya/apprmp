<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LocalGovernment;
use App\Models\Ward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SurveyRoundController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('settings.general'), 403);

        $request->validate([
            'label_np' => 'required|string|max:100',
            'label_en' => 'nullable|string|max:100',
        ]);

        $lg = LocalGovernment::getFirstCached();
        abort_unless($lg, 404);

        $config = $lg->survey_config ?? [];
        $rounds = $config['rounds'] ?? [];
        $number = count($rounds) + 1;

        $wardIds = Ward::where('local_government_id', $lg->id)->pluck('id')->toArray();
        $wards   = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();

        $hhCount = DB::table('households')
            ->whereIn('ward_id', $wardIds)->where('status', 'published')
            ->selectRaw('ward_id, COUNT(*) as n')->groupBy('ward_id')->pluck('n', 'ward_id');

        $pop = DB::table('household_members')
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $wardIds)->where('households.status', 'published')
            ->where('household_members.is_deceased', false)
            ->selectRaw('households.ward_id, COUNT(*) as total, SUM(gender="male") as male, SUM(gender="female") as female')
            ->groupBy('households.ward_id')->get()->keyBy('ward_id');

        $lit = DB::table('household_members')
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $wardIds)->where('households.status', 'published')
            ->where('household_members.is_deceased', false)
            ->whereNotNull('household_members.education_level')->where('household_members.education_level', '!=', '')
            ->selectRaw("households.ward_id,
                SUM(LOWER(household_members.education_level) NOT IN ('illitrate','illiterate')) as literate,
                COUNT(*) as total")
            ->groupBy('households.ward_id')->get()->keyBy('ward_id');

        $utils = DB::table('household_utilities')
            ->join('households', 'household_utilities.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $wardIds)->where('households.status', 'published')
            ->selectRaw("households.ward_id,
                SUM(water_source IN ('Tap_public','Tap_house','covered_well')) as safe_water,
                SUM(CAST(has_internet AS UNSIGNED)) as internet,
                SUM(toilet_type IS NOT NULL AND toilet_type NOT IN ('no_toilet','open_field','openfield','')) as toilet,
                COUNT(*) as total")
            ->groupBy('households.ward_id')->get()->keyBy('ward_id');

        $econ = DB::table('household_economic')
            ->join('households', 'household_economic.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $wardIds)->where('households.status', 'published')
            ->selectRaw("households.ward_id, SUM(CAST(has_bank_account AS UNSIGNED)) as bank, COUNT(*) as total")
            ->groupBy('households.ward_id')->get()->keyBy('ward_id');

        $byWard = $wards->map(function ($w) use ($hhCount, $pop, $lit, $utils, $econ) {
            $p = $pop->get($w->id);
            $l = $lit->get($w->id);
            $u = $utils->get($w->id);
            $e = $econ->get($w->id);
            return [
                'ward_number'      => $w->ward_number,
                'households'       => (int) ($hhCount->get($w->id, 0)),
                'population'       => (int) ($p?->total ?? 0),
                'male'             => (int) ($p?->male ?? 0),
                'female'           => (int) ($p?->female ?? 0),
                'literacy_pct'     => $l && $l->total > 0 ? round($l->literate / $l->total * 100, 1) : 0,
                'safe_water_pct'   => $u && $u->total > 0 ? round($u->safe_water / $u->total * 100, 1) : 0,
                'toilet_pct'       => $u && $u->total > 0 ? round($u->toilet    / $u->total * 100, 1) : 0,
                'internet_pct'     => $u && $u->total > 0 ? round($u->internet  / $u->total * 100, 1) : 0,
                'bank_account_pct' => $e && $e->total > 0 ? round($e->bank      / $e->total * 100, 1) : 0,
            ];
        })->values()->toArray();

        $rounds[] = [
            'number'           => $number,
            'label_np'         => trim($request->label_np),
            'label_en'         => trim($request->label_en ?? "Round {$number}"),
            'closed_at'        => now()->toIso8601String(),
            'total_households' => array_sum(array_column($byWard, 'households')),
            'total_population' => array_sum(array_column($byWard, 'population')),
            'by_ward'          => $byWard,
        ];

        $config['rounds'] = $rounds;
        $lg->update(['survey_config' => $config]);
        LocalGovernment::bustCache($lg->id);

        return back()->with('success', "सर्वेक्षण राउन्ड #{$number} सुरक्षित गरियो।");
    }

    public function destroy(int $number): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('settings.general'), 403);

        $lg = LocalGovernment::getFirstCached();
        abort_unless($lg, 404);

        $config = $lg->survey_config ?? [];
        $rounds = array_values(array_filter($config['rounds'] ?? [], fn($r) => $r['number'] !== $number));

        foreach ($rounds as $i => &$r) {
            $r['number'] = $i + 1;
        }
        unset($r);

        $config['rounds'] = $rounds;
        $lg->update(['survey_config' => $config]);
        LocalGovernment::bustCache($lg->id);

        return back()->with('success', "राउन्ड हटाइयो।");
    }
}
