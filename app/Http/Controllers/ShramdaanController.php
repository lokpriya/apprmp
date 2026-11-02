<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\Settlement;
use App\Models\ShramdaanEntry;
use App\Models\ShramdaanMemberDetail;
use App\Models\ShramdaanPassbook;
use App\Models\ShramdaanProject;
use App\Models\SurveyOption;
use App\Services\ShramdaanService;
use App\Support\BsDate;
use App\Support\InertiaPaginator;
use App\Support\Modules;
use App\Support\ShramdaanAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * श्रमदान पासबुक — module home, a tole's families, and one family's passbook.
 *
 * Every query here is limited to ShramdaanAccess::settlementIds(): a tole
 * coordinator sees their assigned toles, ward staff their wards' toles, admins
 * the whole municipality. Only published households count — a draft is not a
 * confirmed family yet, the same rule as reports and member search.
 *
 * Entries are entered as "recorded" and can be changed or removed until the
 * ward verifies them; a verified entry is locked here.
 */
class ShramdaanController extends Controller
{
    public function __construct(private ShramdaanService $service) {}

    private function user()
    {
        $user = auth()->user();
        abort_unless(ShramdaanAccess::canUse($user), 403);

        return $user;
    }

    private function requireEdit(): void
    {
        abort_unless(auth()->user()->hasPermission('shramdaan.edit'), 403);
    }

    // ── Home: my toles ───────────────────────────────────────────────────────

    public function index()
    {
        $user    = $this->user();
        $toleIds = ShramdaanAccess::settlementIds($user);
        $fy      = $this->service->currentFiscalYear();

        $toles = DB::table('settlements')
            ->join('wards', 'wards.id', '=', 'settlements.ward_id')
            ->leftJoin('households', function ($j) {
                $j->on('households.settlement_id', '=', 'settlements.id')
                  ->where('households.status', 'published')
                  ->whereNull('households.deleted_at');
            })
            ->leftJoin('shramdaan_passbooks', 'shramdaan_passbooks.household_id', '=', 'households.id')
            ->whereIn('settlements.id', $toleIds)
            ->groupBy('settlements.id', 'settlements.name_np', 'wards.ward_number')
            ->orderBy('wards.ward_number')->orderBy('settlements.name_np')
            ->get([
                'settlements.id', 'settlements.name_np', 'wards.ward_number',
                DB::raw('COUNT(DISTINCT households.id) as households'),
                DB::raw('COUNT(DISTINCT shramdaan_passbooks.id) as passbooks'),
            ])
            ->map(fn($t) => [
                'id'          => (int) $t->id,
                'name'        => $t->name_np,
                'ward_number' => (int) $t->ward_number,
                'households'  => (int) $t->households,
                'passbooks'   => (int) $t->passbooks,
            ]);

        $canVerify = $user->hasPermission('shramdaan.verify');

        return Inertia::render('Shramdaan/Index', [
            'toles'         => $toles,
            'isCoordinator' => $user->isToleCoordinator(),
            'fiscalYear'    => $fy,
            'canManage'     => (bool) ShramdaanAccess::managedWardIds($user),
            'canVerify'     => $canVerify,
            'canReports'    => $user->hasPermission('shramdaan.reports'),
            // Waiting for this user's verification, every fiscal year.
            'pendingCount'  => $canVerify
                ? ShramdaanEntry::where('status', ShramdaanEntry::RECORDED)
                    ->whereHas('passbook.household', fn($q) => $q->whereIn('settlement_id', $toleIds)->where('status', 'published'))
                    ->count()
                : 0,
        ]);
    }

    // ── A tole's families ────────────────────────────────────────────────────

    public function tole(Request $request, Settlement $settlement)
    {
        $user = $this->user();
        abort_unless(in_array((int) $settlement->id, ShramdaanAccess::settlementIds($user), true), 404);

        $fy   = $this->service->currentFiscalYear();
        $term = trim((string) $request->query('q', ''));
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

        $page = Household::query()
            ->where('settlement_id', $settlement->id)
            ->where('status', 'published')
            ->when($term !== '', fn($q) => $q->where(fn($w) => $w
                ->where('unique_code', 'like', $like)
                ->orWhere('respondent_name', 'like', $like)
                ->orWhereHas('members', fn($m) => $m->where('full_name', 'like', $like))))
            ->with(['members' => fn($m) => $m->select('id', 'household_id', 'full_name', 'is_household_head', 'is_deceased')])
            ->leftJoin('shramdaan_passbooks', 'shramdaan_passbooks.household_id', '=', 'households.id')
            ->select('households.*', 'shramdaan_passbooks.id as passbook_id')
            ->orderBy('households.unique_code')
            ->paginate(30)->withQueryString();

        // Verified days this fiscal year, one query for the whole page.
        $days = ShramdaanEntry::whereIn('passbook_id', collect($page->items())->pluck('passbook_id')->filter())
            ->where('fiscal_year', $fy)
            ->selectRaw('passbook_id, status, SUM(days) as days')
            ->groupBy('passbook_id', 'status')->get()
            ->groupBy('passbook_id');

        $page->through(function (Household $hh) use ($days) {
            $rows = $days->get($hh->passbook_id, collect())->keyBy('status');
            $head = $hh->members->firstWhere('is_household_head', true);
            return [
                'id'            => $hh->id,
                'unique_code'   => $hh->unique_code,
                'head_name'     => $head?->full_name ?: $hh->respondent_name,
                'members'       => $hh->members->where('is_deceased', false)->count(),
                'has_passbook'  => (bool) $hh->passbook_id,
                'verified_days' => round((float) ($rows[ShramdaanEntry::VERIFIED]->days ?? 0), 2),
                'pending_days'  => round((float) ($rows[ShramdaanEntry::RECORDED]->days ?? 0), 2),
            ];
        });

        return Inertia::render('Shramdaan/Tole', [
            'tole'       => ['id' => $settlement->id, 'name' => $settlement->name_np, 'ward_number' => $settlement->ward?->ward_number],
            'households' => InertiaPaginator::format($page),
            'filters'    => ['q' => $term],
            'fiscalYear' => $fy,
        ]);
    }

    // ── One family's passbook ────────────────────────────────────────────────

    public function show(Request $request, Household $household)
    {
        $user = $this->user();
        ShramdaanAccess::authorizeHousehold($user, $household);

        $lgId     = (int) $user->local_government_id;
        $passbook = ShramdaanPassbook::where('household_id', $household->id)->first();
        $current  = $this->service->currentFiscalYear();

        $years = $passbook
            ? $passbook->entries()->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year')->push($current)->unique()->sortDesc()->values()
            : collect([$current]);
        $fy = in_array($request->query('fy'), $years->all(), true) ? $request->query('fy') : $current;

        $household->load(['ward:id,ward_number', 'settlement:id,name_np', 'members']);
        $head       = $household->members->firstWhere('is_household_head', true);
        $occupation = $head?->occupation_type
            ? (SurveyOption::asArray('occupation_type', $lgId)[$head->occupation_type] ?? $head->occupation_type)
            : null;

        $entries = $passbook
            ? $passbook->entries()->where('fiscal_year', $fy)->with('verifier:id,name')->orderBy('work_date')->orderBy('id')->get()
                ->map(fn(ShramdaanEntry $e) => [
                    'id'                  => $e->id,
                    'work_date'           => $e->work_date->toDateString(),
                    'work_date_bs'        => $e->work_date_bs,
                    'household_member_id' => $e->household_member_id,
                    'member_name'         => $e->member_name,
                    'project_id'          => $e->project_id,
                    'project_name'        => $e->project_name,
                    'hours'               => (float) $e->hours,
                    'days'                => (float) $e->days,
                    'work_nature'         => $e->work_nature,
                    'supervisor'          => $e->supervisor,
                    'verified'            => $e->isVerified(),
                    'verified_by'         => $e->verifier?->name,
                    'own'                 => (int) $e->entered_by === (int) $user->id,
                ])
            : collect();

        $projects = ShramdaanProject::where('ward_id', $household->ward_id)
            ->where('fiscal_year', $fy)->where('is_active', true)
            ->where(fn($q) => $q->whereNull('settlement_id')->orWhere('settlement_id', $household->settlement_id))
            ->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Shramdaan/Passbook', [
            'household' => [
                'id'          => $household->id,
                'unique_code' => $household->unique_code,
                'ward_number' => $household->ward?->ward_number,
                'tole'        => $household->settlement?->name_np,
                'tole_id'     => $household->settlement_id,
                'head_name'   => $head?->full_name ?: $household->respondent_name,
                'phone'       => $household->respondent_contact,
                'family_size' => $household->members->where('is_deceased', false)->count(),
                'occupation'  => $occupation,
            ],
            'passbook'    => $passbook ? [
                'passbook_no'        => $passbook->passbook_no,
                'registered_on_bs'   => BsDate::format($passbook->registered_on->toDateString()),
                'head_citizenship'   => $passbook->head_citizenship,
                'certifying_officer' => $passbook->certifying_officer,
                'remarks'            => $passbook->remarks,
            ] : null,
            'members'     => $this->service->members($household, $passbook, $lgId),
            'entries'     => $entries,
            'totals'      => $passbook ? $this->service->totals($passbook, $fy, $lgId) : null,
            'projects'    => $projects,
            'fiscalYear'  => $fy,
            'fiscalYears' => $years,
            'isCurrentFy' => $fy === $current,
            'fyRange'     => BsDate::fiscalYearRange($fy),
            'settings'    => Modules::settings(Modules::SHRAMDAAN, $lgId),
            'canEdit'     => $user->hasPermission('shramdaan.edit'),
            'canVerify'   => $user->hasPermission('shramdaan.verify'),
        ]);
    }

    /** Open the passbook — an explicit step, so merely viewing a family never writes. */
    public function open(Household $household): RedirectResponse
    {
        $user = $this->user();
        $this->requireEdit();
        ShramdaanAccess::authorizeHousehold($user, $household);

        ShramdaanPassbook::firstOrCreate(
            ['household_id' => $household->id],
            ['passbook_no' => ShramdaanPassbook::numberFor($household), 'registered_on' => now()->toDateString(), 'created_by' => $user->id],
        );

        return back()->with('success', 'पासबुक खोलियो।');
    }

    /** Head citizenship, officer, remarks and each member's citizenship / skill / able-to-work. */
    public function saveDetails(Request $request, Household $household): RedirectResponse
    {
        $user = $this->user();
        $this->requireEdit();
        ShramdaanAccess::authorizeHousehold($user, $household);
        $passbook = ShramdaanPassbook::where('household_id', $household->id)->firstOrFail();

        $memberIds = $household->members()->pluck('id')->map(fn($id) => (int) $id)->all();
        $data = $request->validate([
            'head_citizenship'               => ['nullable', 'string', 'max:60'],
            'certifying_officer'             => ['nullable', 'string', 'max:150'],
            'remarks'                        => ['nullable', 'string', 'max:500'],
            'members'                        => ['array'],
            'members.*.household_member_id'  => ['required', 'integer', Rule::in($memberIds)],
            'members.*.citizenship'          => ['nullable', 'string', 'max:60'],
            'members.*.skill'                => ['nullable', 'string', 'max:150'],
            'members.*.excluded'             => ['boolean'],
        ]);

        DB::transaction(function () use ($passbook, $household, $data) {
            $passbook->update([
                'head_citizenship'   => $data['head_citizenship'] ?? null,
                'certifying_officer' => $data['certifying_officer'] ?? null,
                'remarks'            => $data['remarks'] ?? null,
            ]);

            $names = $household->members()->pluck('full_name', 'id');
            foreach ($data['members'] ?? [] as $m) {
                ShramdaanMemberDetail::updateOrCreate(
                    ['passbook_id' => $passbook->id, 'household_member_id' => $m['household_member_id']],
                    [
                        'member_name' => $names[$m['household_member_id']] ?? '',
                        'citizenship' => $m['citizenship'] ?? null,
                        'skill'       => $m['skill'] ?? null,
                        'excluded'    => (bool) ($m['excluded'] ?? false),
                    ],
                );
            }
        });

        return back()->with('success', 'विवरण सुरक्षित गरियो।');
    }

    // ── Work entries ─────────────────────────────────────────────────────────

    public function storeEntry(Request $request, Household $household): RedirectResponse
    {
        $user = $this->user();
        $this->requireEdit();
        ShramdaanAccess::authorizeHousehold($user, $household);
        $passbook = ShramdaanPassbook::where('household_id', $household->id)->firstOrFail();

        $passbook->entries()->create($this->entryData($request, $household) + [
            'status'     => ShramdaanEntry::RECORDED,
            'entered_by' => $user->id,
        ]);

        return back()->with('success', 'श्रमदान थपियो — वडाले प्रमाणित गरेपछि गणना हुनेछ।');
    }

    public function updateEntry(Request $request, ShramdaanEntry $entry): RedirectResponse
    {
        $household = $this->entryHousehold($entry);
        $entry->update($this->entryData($request, $household));

        return back()->with('success', 'श्रमदान विवरण सच्याइयो।');
    }

    public function destroyEntry(ShramdaanEntry $entry): RedirectResponse
    {
        $this->entryHousehold($entry);
        $entry->delete();

        return back()->with('success', 'श्रमदान विवरण हटाइयो।');
    }

    /** The entry's family, after checking access, edit permission and that it is still unverified. */
    private function entryHousehold(ShramdaanEntry $entry): Household
    {
        $user = $this->user();
        $this->requireEdit();
        $household = $entry->passbook->household;
        abort_unless($household, 404);
        ShramdaanAccess::authorizeHousehold($user, $household);
        abort_if($entry->isVerified(), 422, 'प्रमाणित भइसकेको श्रमदान सच्याउन मिल्दैन।');

        return $household;
    }

    /** Validated entry fields, with the BS date, fiscal year and days worked out here, never typed. */
    private function entryData(Request $request, Household $household): array
    {
        $lgId = (int) auth()->user()->local_government_id;
        $data = $request->validate([
            'work_date'           => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:2010-01-01'],
            'household_member_id' => ['required', 'integer', Rule::in($household->members()->pluck('id')->all())],
            'project_id'          => ['nullable', 'integer', Rule::in(
                ShramdaanProject::where('ward_id', $household->ward_id)->where('is_active', true)
                    ->where(fn($q) => $q->whereNull('settlement_id')->orWhere('settlement_id', $household->settlement_id))
                    ->pluck('id')->all()
            )],
            'project_name'        => ['required_without:project_id', 'nullable', 'string', 'max:250'],
            'hours'               => ['required', 'numeric', 'min:0.5', 'max:24'],
            'work_nature'         => ['nullable', 'string', 'max:250'],
            'supervisor'          => ['nullable', 'string', 'max:150'],
        ], [
            'work_date.before_or_equal'      => 'भविष्यको मिति राख्न मिल्दैन।',
            'project_name.required_without'  => 'योजना छान्नुहोस् वा योजनाको नाम लेख्नुहोस्।',
        ]);

        $project = !empty($data['project_id']) ? ShramdaanProject::find($data['project_id']) : null;

        return [
            'work_date'           => $data['work_date'],
            'work_date_bs'        => BsDate::format($data['work_date']),
            'fiscal_year'         => BsDate::fiscalYear($data['work_date']),
            'household_member_id' => $data['household_member_id'],
            'member_name'         => $household->members()->whereKey($data['household_member_id'])->value('full_name'),
            'project_id'          => $project?->id,
            'project_name'        => $project?->name ?? trim($data['project_name']),
            'hours'               => $data['hours'],
            'days'                => $this->service->daysFor((float) $data['hours'], $lgId),
            'work_nature'         => $data['work_nature'] ?? null,
            'supervisor'          => $data['supervisor'] ?? null,
        ];
    }
}
