<?php

namespace App\Http\Controllers;

use App\Exports\ShramdaanExport;
use App\Models\AuditLog;
use App\Models\ShramdaanEntry;
use App\Services\ShramdaanService;
use App\Support\InertiaPaginator;
use App\Support\ShramdaanAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The ward's side of the work log: verifying entries, and the summary reports.
 *
 * Verification is what makes a day count — only verified days reach the
 * श्रेणी and print as ✓ प्रमाणित. So:
 *   - only shramdaan.verify, and only for entries in the user's own toles
 *     (ShramdaanAccess::settlementIds — a ward admin's wards, all for admins)
 *   - nobody verifies an entry they entered themselves
 *   - un-verifying is possible, for mistakes, and is written to the audit log
 */
class ShramdaanReviewController extends Controller
{
    public function __construct(private ShramdaanService $service) {}

    /** @return int[] the toles this user may verify or report on */
    private function scope(string $permission): array
    {
        $user = auth()->user();
        abort_unless(ShramdaanAccess::canUse($user) && $user->hasPermission($permission), 403);

        return ShramdaanAccess::settlementIds($user);
    }

    /** Entries in scope, joined to their family, for the queue and the export. */
    private function entriesIn(array $toleIds)
    {
        return ShramdaanEntry::query()
            ->join('shramdaan_passbooks as pb', 'pb.id', '=', 'shramdaan_entries.passbook_id')
            ->join('households as hh', 'hh.id', '=', 'pb.household_id')
            ->join('settlements as s', 's.id', '=', 'hh.settlement_id')
            ->join('wards as w', 'w.id', '=', 'hh.ward_id')
            ->whereIn('hh.settlement_id', $toleIds)
            ->where('hh.status', 'published')->whereNull('hh.deleted_at');
    }

    private function fiscalYear(Request $request): string
    {
        $fy = (string) $request->query('fy');

        return preg_match('#^\d{4}/\d{2}$#', $fy) ? $fy : $this->service->currentFiscalYear();
    }

    // ── Verification queue ───────────────────────────────────────────────────

    public function queue(Request $request)
    {
        $toleIds = $this->scope('shramdaan.verify');
        $fy      = $this->fiscalYear($request);
        $toleId  = (int) $request->query('tole') ?: null;
        if ($toleId && !in_array($toleId, $toleIds, true)) $toleId = null;

        $page = $this->entriesIn($toleId ? [$toleId] : $toleIds)
            ->where('shramdaan_entries.status', ShramdaanEntry::RECORDED)
            ->where('shramdaan_entries.fiscal_year', $fy)
            ->leftJoin('users as u', 'u.id', '=', 'shramdaan_entries.entered_by')
            ->orderBy('w.ward_number')->orderBy('s.name_np')->orderBy('hh.unique_code')->orderBy('shramdaan_entries.work_date')
            ->select('shramdaan_entries.*', 'hh.id as household_id', 'hh.unique_code', 'hh.respondent_name',
                     's.name_np as tole', 'w.ward_number', 'u.name as entered_by_name')
            ->paginate(50)->withQueryString();

        $me = auth()->id();
        $page->through(fn($e) => [
            'id'              => $e->id,
            'household_id'    => $e->household_id,
            'unique_code'     => $e->unique_code,
            'family'          => $e->respondent_name,
            'tole'            => $e->tole,
            'ward_number'     => (int) $e->ward_number,
            'work_date_bs'    => $e->work_date_bs,
            'member_name'     => $e->member_name,
            'project_name'    => $e->project_name,
            'hours'           => (float) $e->hours,
            'days'            => (float) $e->days,
            'work_nature'     => $e->work_nature,
            'supervisor'      => $e->supervisor,
            'entered_by_name' => $e->entered_by_name,
            'own'             => (int) $e->entered_by === (int) $me,
        ]);

        return Inertia::render('Shramdaan/Verify', [
            'entries'    => InertiaPaginator::format($page),
            'toles'      => $this->toleList($toleIds),
            'filters'    => ['fy' => $fy, 'tole' => $toleId],
            'fiscalYears'=> $this->years($toleIds, $fy),
        ]);
    }

    /** Verify one or many entries. Out-of-scope, already verified and own entries are skipped, not failed. */
    public function verify(Request $request): RedirectResponse
    {
        $toleIds = $this->scope('shramdaan.verify');
        $data    = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:500'], 'ids.*' => ['integer']]);
        $me      = auth()->id();

        $ids = $this->entriesIn($toleIds)
            ->whereIn('shramdaan_entries.id', $data['ids'])
            ->where('shramdaan_entries.status', ShramdaanEntry::RECORDED)
            ->where(fn($q) => $q->whereNull('shramdaan_entries.entered_by')->orWhere('shramdaan_entries.entered_by', '!=', $me))
            ->pluck('shramdaan_entries.id')->all();

        if ($ids) {
            ShramdaanEntry::whereIn('id', $ids)->update([
                'status' => ShramdaanEntry::VERIFIED, 'verified_by' => $me, 'verified_at' => now(),
            ]);
            $this->audit('shramdaan_verify', ['entry_ids' => $ids]);
        }

        $skipped = count(array_unique($data['ids'])) - count($ids);
        $msg = count($ids) . ' श्रमदान प्रमाणित गरियो।';
        if ($skipped) $msg .= " {$skipped} छोडियो (आफैंले दर्ता गरेको, पहिल्यै प्रमाणित वा तपाईंको क्षेत्र बाहिर)।";

        return back()->with(count($ids) ? 'success' : 'error', $msg);
    }

    /** Undo a verification made by mistake — back to "recorded", editable again. */
    public function unverify(ShramdaanEntry $entry): RedirectResponse
    {
        $toleIds = $this->scope('shramdaan.verify');
        abort_unless($this->entriesIn($toleIds)->where('shramdaan_entries.id', $entry->id)->exists(), 404);
        abort_unless($entry->isVerified(), 422, 'यो श्रमदान प्रमाणित भएको छैन।');

        $before = ['verified_by' => $entry->verified_by, 'verified_at' => $entry->verified_at?->toDateTimeString()];
        $entry->update(['status' => ShramdaanEntry::RECORDED, 'verified_by' => null, 'verified_at' => null]);
        $this->audit('shramdaan_unverify', ['entry_id' => $entry->id] + $before);

        return back()->with('success', 'प्रमाणीकरण फिर्ता लिइयो।');
    }

    // ── Reports ──────────────────────────────────────────────────────────────

    public function reports(Request $request)
    {
        $toleIds = $this->scope('shramdaan.reports');
        $fy      = $this->fiscalYear($request);
        $lgId    = (int) auth()->user()->local_government_id;

        return Inertia::render('Shramdaan/Reports', [
            'rows'        => $this->service->summary($toleIds, $fy, $lgId),
            'fiscalYear'  => $fy,
            'fiscalYears' => $this->years($toleIds, $fy),
            'top'         => $this->topFamilies($toleIds, $fy),
        ]);
    }

    /** Excel: tole summary + every entry. Citizenship numbers are never exported. */
    public function export(Request $request)
    {
        $toleIds = $this->scope('shramdaan.reports');
        $fy      = $this->fiscalYear($request);
        $lgId    = (int) auth()->user()->local_government_id;

        $entries = $this->entriesIn($toleIds)
            ->where('shramdaan_entries.fiscal_year', $fy)
            ->leftJoin('users as v', 'v.id', '=', 'shramdaan_entries.verified_by')
            ->orderBy('w.ward_number')->orderBy('s.name_np')->orderBy('hh.unique_code')->orderBy('shramdaan_entries.work_date')
            ->get(['w.ward_number', 's.name_np as tole', 'hh.unique_code', 'hh.respondent_name',
                   'shramdaan_entries.member_name', 'shramdaan_entries.work_date_bs', 'shramdaan_entries.project_name',
                   'shramdaan_entries.hours', 'shramdaan_entries.days', 'shramdaan_entries.work_nature',
                   'shramdaan_entries.supervisor', 'shramdaan_entries.status', 'v.name as verified_by_name']);

        $this->audit('shramdaan_export', ['fiscal_year' => $fy, 'rows' => $entries->count()]);

        return Excel::download(
            new ShramdaanExport($this->service->summary($toleIds, $fy, $lgId), $entries, $fy),
            'shramdaan-' . str_replace('/', '-', $fy) . '.xlsx'
        );
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function topFamilies(array $toleIds, string $fy): array
    {
        return $this->entriesIn($toleIds)
            ->where('shramdaan_entries.fiscal_year', $fy)
            ->where('shramdaan_entries.status', ShramdaanEntry::VERIFIED)
            ->groupBy('hh.id', 'hh.unique_code', 'hh.respondent_name', 's.name_np', 'w.ward_number')
            ->orderByDesc(DB::raw('SUM(shramdaan_entries.days)'))
            ->limit(10)
            ->get(['hh.id', 'hh.unique_code', 'hh.respondent_name', 's.name_np as tole', 'w.ward_number',
                   DB::raw('SUM(shramdaan_entries.days) as days')])
            ->map(fn($r) => [
                'household_id' => (int) $r->id, 'unique_code' => $r->unique_code, 'family' => $r->respondent_name,
                'tole' => $r->tole, 'ward_number' => (int) $r->ward_number, 'days' => round((float) $r->days, 2),
            ])->all();
    }

    private function toleList(array $toleIds)
    {
        return DB::table('settlements')->join('wards', 'wards.id', '=', 'settlements.ward_id')
            ->whereIn('settlements.id', $toleIds)
            ->orderBy('wards.ward_number')->orderBy('settlements.name_np')
            ->get(['settlements.id', 'settlements.name_np as name', 'wards.ward_number']);
    }

    private function years(array $toleIds, string $selected)
    {
        return $this->entriesIn($toleIds)->distinct()->pluck('shramdaan_entries.fiscal_year')
            ->push($this->service->currentFiscalYear())->push($selected)
            ->unique()->sortDesc()->values();
    }

    private function audit(string $event, array $values): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'event_type' => $event,
            'new_values' => $values,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
