<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Settlement;
use App\Models\ShramdaanProject;
use App\Models\User;
use App\Models\Ward;
use App\Services\ShramdaanService;
use App\Support\ShramdaanAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/**
 * shramdaan.manage — the ward's side of the module: its योजना list and its tole
 * coordinators.
 *
 * Everything is bounded by ShramdaanAccess::managedWardIds(): a ward admin works
 * only inside their own wards. A coordinator can hold toles in several wards
 * (a municipal admin may set that up), so a ward admin changing a coordinator's
 * toles touches only the toles in their own wards and leaves the rest alone.
 * Deactivating, resetting passwords and deleting stay with the municipal admin's
 * user management, where the account as a whole is visible.
 */
class ShramdaanManageController extends Controller
{
    public function __construct(private ShramdaanService $service) {}

    /** @return int[] */
    private function wards(): array
    {
        abort_unless(ShramdaanAccess::canUse(auth()->user()), 403);
        $ids = ShramdaanAccess::managedWardIds(auth()->user());
        abort_unless($ids, 403);

        return $ids;
    }

    private function toleOptions(array $wardIds)
    {
        return Settlement::query()
            ->join('wards', 'wards.id', '=', 'settlements.ward_id')
            ->whereIn('settlements.ward_id', $wardIds)
            ->orderBy('wards.ward_number')->orderBy('settlements.name_np')
            ->get(['settlements.id', 'settlements.ward_id', 'settlements.name_np', 'wards.ward_number'])
            ->map(fn($t) => ['id' => (int) $t->id, 'ward_id' => (int) $t->ward_id, 'name' => $t->name_np, 'ward_number' => (int) $t->ward_number]);
    }

    // ── योजना ────────────────────────────────────────────────────────────────

    public function projects(Request $request)
    {
        $wardIds = $this->wards();
        $current = $this->service->currentFiscalYear();
        $fy      = preg_match('#^\d{4}/\d{2}$#', (string) $request->query('fy')) ? $request->query('fy') : $current;

        $projects = ShramdaanProject::with(['ward:id,ward_number', 'settlement:id,name_np'])->withCount('entries')
            ->whereIn('ward_id', $wardIds)->where('fiscal_year', $fy)
            ->orderBy('ward_id')->orderBy('name')->get()
            ->map(fn($p) => [
                'id' => $p->id, 'name' => $p->name, 'is_active' => $p->is_active,
                'ward_number' => $p->ward?->ward_number, 'tole' => $p->settlement?->name_np,
                'entries' => $p->entries_count,
            ]);

        $years = ShramdaanProject::whereIn('ward_id', $wardIds)->distinct()->pluck('fiscal_year')
            ->push($current)->unique()->sortDesc()->values();

        return Inertia::render('Shramdaan/Projects', [
            'projects'    => $projects,
            'wards'       => Ward::whereIn('id', $wardIds)->orderBy('ward_number')->get(['id', 'ward_number']),
            'toles'       => $this->toleOptions($wardIds),
            'fiscalYear'  => $fy,
            'fiscalYears' => $years,
        ]);
    }

    public function storeProject(Request $request): RedirectResponse
    {
        $wardIds = $this->wards();
        $data = $request->validate([
            'ward_id'       => ['required', 'integer', Rule::in($wardIds)],
            'settlement_id' => ['nullable', 'integer', Rule::in($this->toleOptions($wardIds)->where('ward_id', (int) $request->input('ward_id'))->pluck('id')->all())],
            'name'          => ['required', 'string', 'max:250'],
            'fiscal_year'   => ['required', 'regex:#^\d{4}/\d{2}$#'],
        ], ['settlement_id.in' => 'छानिएको टोल यस वडाको होइन।']);

        ShramdaanProject::create($data + ['created_by' => auth()->id(), 'is_active' => true]);

        return back()->with('success', 'योजना थपियो।');
    }

    /** Rename or switch a project on/off. Entries keep the name they were recorded with. */
    public function updateProject(Request $request, ShramdaanProject $project): RedirectResponse
    {
        abort_unless(in_array((int) $project->ward_id, $this->wards(), true), 404);
        $data = $request->validate([
            'name'      => ['sometimes', 'required', 'string', 'max:250'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $project->update($data);

        return back()->with('success', 'योजना अद्यावधिक गरियो।');
    }

    // ── Tole coordinators ────────────────────────────────────────────────────

    public function coordinators()
    {
        $wardIds = $this->wards();
        $toles   = $this->toleOptions($wardIds);
        $myToles = $toles->pluck('id')->all();

        // Coordinators who hold a tole here, plus any who hold none yet (just
        // created, waiting for toles) — never coordinators working only elsewhere.
        $users = User::where('local_government_id', auth()->user()->local_government_id)
            ->where('role', 'tole_coordinator')
            ->where(fn($q) => $q->whereHas('assignedSettlements', fn($s) => $s->whereIn('settlements.id', $myToles))
                                ->orWhereDoesntHave('assignedSettlements'))
            ->with('assignedSettlements:id,name_np')
            ->orderBy('name')->get()
            ->map(fn(User $u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'phone'       => $u->phone,
                'is_active'   => (bool) $u->is_active,
                'my_tole_ids' => $u->assignedSettlements->pluck('id')->map(fn($i) => (int) $i)->intersect($myToles)->values(),
                'other_toles' => $u->assignedSettlements->whereNotIn('id', $myToles)->pluck('name_np')->values(),
            ]);

        return Inertia::render('Shramdaan/Coordinators', [
            'coordinators' => $users,
            'toles'        => $toles,
        ]);
    }

    public function storeCoordinator(Request $request): RedirectResponse
    {
        $myToles = $this->toleOptions($this->wards())->pluck('id')->all();
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:200'],
            'email'            => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'            => ['nullable', 'string', 'max:15'],
            'password'         => ['required', Password::min(8)],
            'settlement_ids'   => ['required', 'array', 'min:1'],
            'settlement_ids.*' => ['integer', Rule::in($myToles)],
        ], ['settlement_ids.required' => 'कम्तीमा एउटा टोल छान्नुहोस्।', 'settlement_ids.*.in' => 'छानिएको टोल तपाईंको वडाको होइन।']);

        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']), 'role' => 'tole_coordinator',
            'local_government_id' => auth()->user()->local_government_id, 'is_active' => true,
        ]);
        $this->attachToles($user, $data['settlement_ids']);
        $this->audit('created', $user, ['toles' => $data['settlement_ids']]);

        return back()->with('success', "टोल संयोजक {$user->name} थपियो।");
    }

    /** Replace the coordinator's toles within this admin's wards; toles elsewhere are kept. */
    public function updateCoordinatorToles(Request $request, User $user): RedirectResponse
    {
        $myToles = $this->toleOptions($this->wards())->pluck('id')->all();
        abort_unless($user->role === 'tole_coordinator'
            && (int) $user->local_government_id === (int) auth()->user()->local_government_id, 404);

        $data = $request->validate([
            'settlement_ids'   => ['present', 'array'],
            'settlement_ids.*' => ['integer', Rule::in($myToles)],
        ]);

        $kept = $user->assignedSettlements()->pluck('settlements.id')->map(fn($i) => (int) $i)
            ->reject(fn($id) => in_array($id, $myToles, true))->all();

        $user->assignedSettlements()->sync(array_fill_keys($kept, []) + $this->pivot($data['settlement_ids']));
        $this->audit('updated', $user, ['toles' => $data['settlement_ids']]);

        return back()->with('success', "{$user->name} को टोल अद्यावधिक गरियो।");
    }

    private function attachToles(User $user, array $ids): void
    {
        $user->assignedSettlements()->attach($this->pivot($ids));
    }

    private function pivot(array $ids): array
    {
        return collect($ids)->mapWithKeys(fn($id) => [(int) $id => ['assigned_at' => now(), 'assigned_by' => auth()->id()]])->all();
    }

    private function audit(string $event, User $user, array $values): void
    {
        AuditLog::create([
            'user_id'        => auth()->id(),
            'event_type'     => $event,
            'auditable_type' => User::class,
            'auditable_id'   => $user->id,
            'new_values'     => $values + ['role' => 'tole_coordinator'],
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
        ]);
    }
}
