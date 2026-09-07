<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\HouseholdMember;
use App\Models\Institution;
use App\Models\LocalGovernment;
use App\Models\MemberBusiness;
use App\Models\Ward;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use Inertia\Inertia;

class OpenDataController extends Controller
{
    private function defaultLg(): ?LocalGovernment
    {
        return Cache::remember('active_lg_full', 600, fn () =>
            LocalGovernment::with(['district.province'])->where('is_active', true)->first()
                ?? LocalGovernment::with(['district.province'])->first()
        );
    }

    private function csvStream(string $filename, callable $writer): Response
    {
        $content = '';
        $out = fopen('php://temp', 'r+');
        // UTF-8 BOM — ensures Excel opens Nepali text correctly
        fputs($out, "\xEF\xBB\xBF");
        $writer($out);
        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return response($content, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Neutralise CSV formula injection. Excel, LibreOffice and Sheets execute a cell
     * beginning with = + - @ or a control character as a formula. These files are
     * publicly downloadable and carry enumerator-entered free text, so a crafted
     * institution or business name would otherwise run on the downloader's machine.
     */
    private static function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value))
            ? "'" . $value
            : $value;
    }

    // ── Landing page ─────────────────────────────────────────────────────────
    public function index(): \Inertia\Response
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);

        $wardIds = Ward::where('local_government_id', $lg->id)->pluck('id');

        return Inertia::render('Public/OpenData/Index', [
            'lg' => [
                'name_np' => $lg->name_np,
                'name_en' => $lg->name_en,
                'logo_url'=> $lg->logo_url ?? null,
            ],
            'stats' => Cache::remember("opendata_stats_{$lg->id}", 600, function () use ($wardIds) {
                return [
                    'households'   => \App\Models\Household::whereIn('ward_id', $wardIds)->where('status', 'published')->count(),
                    'institutions' => \App\Models\Institution::whereIn('ward_id', $wardIds)->where('status', 'published')->count(),
                    'businesses'   => \App\Models\MemberBusiness::whereIn('ward_id', $wardIds)->where('status', 'published')->count(),
                    'members'      => \App\Models\HouseholdMember::whereHas('household', fn($q) => $q->whereIn('ward_id', $wardIds)->where('status', 'published'))->count(),
                    'updated_at'   => \App\Models\Household::whereIn('ward_id', $wardIds)->where('status', 'published')->max('updated_at'),
                ];
            }),
        ]);
    }

    // ── Ward summary CSV ──────────────────────────────────────────────────────
    public function wards(): Response
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);

        $allWardIds = Ward::where('local_government_id', $lg->id)->pluck('id')->toArray();
        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();

        $wardHH = DB::table('households')
            ->whereIn('ward_id', $allWardIds)->where('status', 'published')
            ->select('ward_id', DB::raw('count(*) as hh'))->groupBy('ward_id')->pluck('hh', 'ward_id');

        $wardGender = DB::table('household_members')
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $allWardIds)->where('households.status', 'published')
            ->where('household_members.is_deceased', false)
            ->select('households.ward_id', 'household_members.gender', DB::raw('count(*) as cnt'))
            ->groupBy('households.ward_id', 'household_members.gender')->get()->groupBy('ward_id');

        $wardInst = DB::table('institutions')
            ->whereIn('ward_id', $allWardIds)->where('status', 'published')
            ->select('ward_id', DB::raw('count(*) as cnt'))->groupBy('ward_id')->pluck('cnt', 'ward_id');

        $wardBiz = DB::table('member_businesses')
            ->whereIn('ward_id', $allWardIds)->where('status', 'published')
            ->select('ward_id', DB::raw('count(*) as cnt'))->groupBy('ward_id')->pluck('cnt', 'ward_id');

        $wardLit = DB::table('household_members')
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $allWardIds)->where('households.status', 'published')
            ->where('household_members.is_deceased', false)
            ->whereNotNull('household_members.education_level')->where('household_members.education_level', '!=', '')
            ->select('households.ward_id',
                DB::raw("SUM(CASE WHEN LOWER(household_members.education_level) NOT IN ('illitrate','illiterate') THEN 1 ELSE 0 END) as literate"),
                DB::raw('count(*) as total'))
            ->groupBy('households.ward_id')->get()->keyBy('ward_id');

        $wardUtils = DB::table('household_utilities')
            ->join('households', 'household_utilities.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $allWardIds)->where('households.status', 'published')
            ->select('households.ward_id',
                DB::raw("SUM(CASE WHEN water_source IN ('Tap_public','Tap_house','covered_well') THEN 1 ELSE 0 END) as safe_water"),
                DB::raw('SUM(CAST(has_internet AS UNSIGNED)) as has_internet'),
                DB::raw("SUM(CASE WHEN toilet_type IS NOT NULL AND toilet_type NOT IN ('no_toilet','open_field','openfield','') THEN 1 ELSE 0 END) as has_toilet"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('households.ward_id')->get()->keyBy('ward_id');

        $wardEcon = DB::table('household_economic')
            ->join('households', 'household_economic.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $allWardIds)->where('households.status', 'published')
            ->select('households.ward_id',
                DB::raw('SUM(CAST(has_bank_account AS UNSIGNED)) as has_bank'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('households.ward_id')->get()->keyBy('ward_id');

        $slug = now()->format('Ymd');

        return $this->csvStream("ward-summary-{$slug}.csv", function ($out) use (
            $wards, $wardHH, $wardGender, $wardInst, $wardBiz, $wardLit, $wardUtils, $wardEcon
        ) {
            fputcsv($out, [
                'वडा नं.', 'घरधुरी', 'पुरुष', 'महिला', 'अन्य', 'जम्मा जनसंख्या',
                'साक्षरता %', 'सुरक्षित पानी %', 'शौचालय %', 'इन्टरनेट %', 'बैंक खाता %',
                'संस्था', 'व्यवसाय',
            ]);

            foreach ($wards as $ward) {
                $id  = $ward->id;
                $g   = $wardGender->get($id, collect());
                $lit = $wardLit->get($id);
                $u   = $wardUtils->get($id);
                $e   = $wardEcon->get($id);

                $male   = (int) ($g->firstWhere('gender', 'male')?->cnt  ?? 0);
                $female = (int) ($g->firstWhere('gender', 'female')?->cnt ?? 0);
                $other  = (int) ($g->firstWhere('gender', 'other')?->cnt  ?? 0);
                $total  = $male + $female + $other;

                $litN   = (int) ($lit?->literate ?? 0);
                $litD   = (int) ($lit?->total    ?? 0);
                $uTotal = (int) ($u?->total      ?? 0);
                $eTotal = (int) ($e?->total      ?? 0);

                fputcsv($out, [
                    $ward->ward_number,
                    (int) ($wardHH->get($id, 0)),
                    $male, $female, $other, $total,
                    $litD   > 0 ? round($litN / $litD * 100, 1) : 0,
                    $uTotal > 0 ? round((int) ($u?->safe_water  ?? 0) / $uTotal * 100, 1) : 0,
                    $uTotal > 0 ? round((int) ($u?->has_toilet  ?? 0) / $uTotal * 100, 1) : 0,
                    $uTotal > 0 ? round((int) ($u?->has_internet ?? 0) / $uTotal * 100, 1) : 0,
                    $eTotal > 0 ? round((int) ($e?->has_bank    ?? 0) / $eTotal * 100, 1) : 0,
                    (int) ($wardInst->get($id, 0)),
                    (int) ($wardBiz->get($id, 0)),
                ]);
            }
        });
    }

    // ── Institutions CSV ──────────────────────────────────────────────────────
    public function institutions(): Response
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);

        $allWardIds = Ward::where('local_government_id', $lg->id)->pluck('id')->toArray();

        $instTypeMap = \App\Models\SurveyOption::where('category', 'institution_type')
            ->pluck('label_ne', 'value');
        $rows = DB::table('institutions')
            ->join('wards', 'institutions.ward_id', '=', 'wards.id')
            ->whereIn('institutions.ward_id', $allWardIds)
            ->where('institutions.status', 'published')
            ->orderBy('wards.ward_number')
            ->orderBy('institutions.name_np')
            ->select([
                'wards.ward_number',
                'institutions.name_np',
                'institutions.name_en',
                'institutions.institution_type',
                'institutions.ownership_type',
                'institutions.established_date',
            ])
            ->get()
            ->map(fn($r) => (object) array_merge((array)$r, [
                'type_np' => $instTypeMap[$r->institution_type] ?? $r->institution_type,
            ]));

        $slug = now()->format('Ymd');

        return $this->csvStream("institutions-{$slug}.csv", function ($out) use ($rows) {
            fputcsv($out, ['वडा नं.', 'नाम (नेपाली)', 'नाम (अंग्रेजी)', 'प्रकार', 'स्वामित्व', 'स्थापना मिति']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->ward_number,
                    self::csvSafe($r->name_np),
                    self::csvSafe($r->name_en),
                    self::csvSafe($r->type_np),
                    self::csvSafe($r->ownership_type),
                    self::csvSafe($r->established_date),
                ]);
            }
        });
    }

    // ── Businesses CSV ────────────────────────────────────────────────────────
    public function businesses(): Response
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);

        $allWardIds = Ward::where('local_government_id', $lg->id)->pluck('id')->toArray();

        $rows = DB::table('member_businesses')
            ->join('wards', 'member_businesses.ward_id', '=', 'wards.id')
            ->whereIn('member_businesses.ward_id', $allWardIds)
            ->where('member_businesses.status', 'published')
            ->orderBy('wards.ward_number')
            ->orderBy('member_businesses.business_name')
            ->select([
                'wards.ward_number',
                'member_businesses.business_name',
                'member_businesses.business_type',
                'member_businesses.registration_number',
            ])
            ->get();

        $slug = now()->format('Ymd');

        return $this->csvStream("businesses-{$slug}.csv", function ($out) use ($rows) {
            fputcsv($out, ['वडा नं.', 'व्यवसायको नाम', 'प्रकार', 'दर्ता नम्बर']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->ward_number,
                    self::csvSafe($r->business_name),
                    self::csvSafe($r->business_type),
                    self::csvSafe($r->registration_number),
                ]);
            }
        });
    }
}
