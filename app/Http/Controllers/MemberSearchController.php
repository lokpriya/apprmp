<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Settlement;
use App\Models\SurveyOption;
use App\Models\User;
use App\Models\Ward;
use App\Support\InertiaPaginator;
use App\Support\ScopesWardAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * सदस्य खोज — read-only lookup of published households for field staff.
 *
 * Enumerators and supervisors use it on their phones to confirm a family and
 * copy its household number into other data sets. It searches by household
 * number, family head, phone number or any member's name, and lists results by
 * ward → tole → household number.
 *
 * Deliberately narrow:
 *   - published households only — a draft is unverified, and rejected records
 *     may be wrong, so neither should be copied anywhere
 *   - the assigned wards only, unless the role is granted members.search_all_wards
 *     (admins already see every ward)
 *   - nothing here writes, and every search is written to the audit log,
 *     because the results carry full phone numbers
 *
 * The only searchable phone is households.respondent_contact. Member phone
 * fields are encrypted at rest, so LIKE cannot match them — and they are empty
 * in every synced data set anyway.
 */
class MemberSearchController extends Controller
{
    use ScopesWardAccess;

    public const MIN_LENGTH = 3;
    public const MAX_LENGTH = 50;
    private const PER_PAGE  = 20;

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('members.search'), 403);

        $wardIds = $this->searchableWardIds($user);
        $term    = $this->normalise((string) $request->query('q', ''));
        $wardId  = (int) $request->query('ward_id') ?: null;
        if ($wardId && !in_array($wardId, $wardIds, true)) $wardId = null;
        // A single-ward user has nothing to choose, and the tole list needs a ward.
        if (count($wardIds) === 1) $wardId = (int) $wardIds[0];

        // Every tole of the searchable wards, so the page can switch the tole
        // list as soon as the ward changes. A tole only counts inside the chosen
        // ward; anything else is ignored, so a stale or tampered id cannot
        // reach outside the user's wards.
        $toles  = Settlement::whereIn('ward_id', $wardIds)->orderBy('name_np')->get(['id', 'ward_id', 'name_np']);
        $toleId = (int) $request->query('settlement_id') ?: null;
        if ($toleId && !$toles->contains(fn($t) => (int) $t->id === $toleId && (int) $t->ward_id === $wardId)) $toleId = null;

        // With a tole chosen the search box may be left empty: that lists the
        // whole tole, which is how field staff narrow down a common name.
        $results = null;
        if (mb_strlen($term) >= self::MIN_LENGTH || ($toleId && $term === '')) {
            $results = $this->search($user, $term, $wardId ? [$wardId] : $wardIds, $toleId);
            $this->audit($request, $user, $term, $wardId, $toleId, $results['meta']['total']);
        }

        return Inertia::render('MemberSearch/Index', [
            'results'   => $results,
            'filters'   => ['q' => $term, 'ward_id' => $wardId, 'settlement_id' => $toleId],
            'wards'     => Ward::whereIn('id', $wardIds)->orderBy('ward_number')->get(['id', 'ward_number']),
            'toles'     => $toles,
            'allWards'  => $user->hasPermission('members.search_all_wards') || $user->isAdmin(),
            'minLength' => self::MIN_LENGTH,
        ]);
    }

    /** Assigned wards, or the whole municipality when the role is allowed it. */
    private function searchableWardIds(User $user): array
    {
        if ($user->hasPermission('members.search_all_wards')) {
            return Ward::where('local_government_id', $user->local_government_id)
                ->orderBy('ward_number')
                ->pluck('id')
                ->all();
        }

        return $this->scopedWardIds($user);
    }

    /**
     * Trim, collapse spaces, and turn Nepali digits into ASCII — phones and
     * household numbers are stored with ASCII digits, and a Nepali keyboard
     * types ९८४….
     */
    private function normalise(string $raw): string
    {
        $s = strtr($raw, array_combine(
            ['०', '१', '२', '३', '४', '५', '६', '७', '८', '९'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        ));
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');

        return mb_substr($s, 0, self::MAX_LENGTH);
    }

    /**
     * The phone digits in the search, or null if it is not a phone search.
     * "+977-984-1234567" and "984 1234567" both become digits only; a leading
     * 977 country code is dropped because numbers are stored without it.
     */
    private function phoneDigits(string $term): ?string
    {
        if (!preg_match('/^[\d\s+\-()]+$/', $term)) return null;

        $digits = preg_replace('/\D/', '', $term);
        if (strlen($digits) > 10 && str_starts_with($digits, '977')) {
            $digits = substr($digits, 3);
        }

        return strlen($digits) >= self::MIN_LENGTH ? $digits : null;
    }

    private function search(User $user, string $term, array $wardIds, ?int $toleId = null): array
    {
        $like   = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
        $digits = $term === '' ? null : $this->phoneDigits($term);

        $paginator = Household::query()
            ->select('households.*')
            ->join('wards', 'wards.id', '=', 'households.ward_id')
            ->leftJoin('settlements', 'settlements.id', '=', 'households.settlement_id')
            ->where('households.status', 'published')
            ->whereIn('households.ward_id', $wardIds)
            ->when($toleId, fn($q) => $q->where('households.settlement_id', $toleId))
            ->when($term !== '', fn($query) => $query->where(function ($q) use ($like, $digits) {
                $q->where('households.unique_code', 'like', $like)
                  ->orWhere('households.respondent_name', 'like', $like)
                  ->orWhereHas('members', fn($m) => $m->where('full_name', 'like', $like));
                if ($digits) {
                    $q->orWhere('households.respondent_contact', 'like', "%{$digits}%");
                }
            }))
            ->with([
                'ward:id,ward_number',
                'settlement:id,name_np',
                'members:id,household_id,full_name,relation_to_head,gender,age,is_household_head,is_deceased,sort_order',
            ])
            ->orderBy('wards.ward_number')
            ->orderBy('settlements.name_np')
            ->orderBy('households.unique_code')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $relations = SurveyOption::asArray('relation_to_head', $user->local_government_id);
        $needle    = mb_strtolower($term);

        $paginator->through(function (Household $hh) use ($relations, $needle, $digits) {
            $head = $hh->members->firstWhere('is_household_head', true);

            return [
                'id'           => $hh->id,
                'unique_code'  => $hh->unique_code,
                'ward_number'  => $hh->ward?->ward_number,
                'tole'         => $hh->settlement?->name_np,
                'head_name'    => $head?->full_name ?: $hh->respondent_name,
                'respondent'   => $hh->respondent_name,
                'phone'        => $hh->respondent_contact,
                'matched_code' => $needle !== '' && str_contains(mb_strtolower((string) $hh->unique_code), $needle),
                'matched_phone'=> $digits !== null && str_contains((string) $hh->respondent_contact, $digits),
                'members'      => $hh->members->map(fn($m) => [
                    'id'          => $m->id,
                    'name'        => $m->full_name,
                    'relation'    => $relations[$m->relation_to_head] ?? $m->relation_to_head,
                    'gender'      => $m->gender,
                    'age'         => $m->age,
                    'is_head'     => (bool) $m->is_household_head,
                    'is_deceased' => (bool) $m->is_deceased,
                    'matched'     => $needle !== '' && $m->full_name !== null
                        && str_contains(mb_strtolower($m->full_name), $needle),
                ])->values(),
            ];
        });

        return InertiaPaginator::format($paginator);
    }

    /** Results show full phone numbers, so who looked up what is kept. */
    private function audit(Request $request, User $user, string $term, ?int $wardId, ?int $toleId, int $total): void
    {
        AuditLog::create([
            'user_id'    => $user->id,
            'event_type' => 'member_search',
            'new_values' => [
                'q'             => $term,
                'ward_id'       => $wardId,
                'settlement_id' => $toleId,
                'page'    => (int) $request->query('page', 1),
                'results' => $total,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
