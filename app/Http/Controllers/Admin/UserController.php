<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * May the signed-in admin act on this account at all?
     *
     * Every action below used to take the {user} from the URL as given. A
     * municipal admin could therefore demote, deactivate, delete, re-email or
     * reset the password of the super admin — the one account meant to sit
     * above them. Only a super admin may change a super admin account, and
     * nobody may reach an account in another municipality.
     */
    private function guardTarget(User $user): void
    {
        $actor = auth()->user();

        abort_if($user->isSuperAdmin() && !$actor->isSuperAdmin(), 403,
            'सुपर एडमिनको खाता सुपर एडमिनले मात्र परिवर्तन गर्न सक्छ।');
        abort_if(!$actor->isSuperAdmin()
            && (int) $user->local_government_id !== (int) $actor->local_government_id, 403);
    }

    public function index(Request $request)
    {
        $query = User::with('assignedWards')
            ->where('local_government_id', auth()->user()->local_government_id);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                  ->orWhere('email', 'like', '%'.$request->search.'%');
            });
        }
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $users = $query->orderBy('role')->paginate(20)->through(fn($u) => [
            'id'            => $u->id,
            'name'          => $u->name,
            'email'         => $u->email,
            'role'          => $u->role,
            'phone'         => $u->phone,
            'is_active'     => $u->is_active,
            'assigned_wards'=> $u->assignedWards->pluck('ward_number')->sort()->values(),
            'created_at'    => $u->created_at?->diffForHumans(),
            'last_login_at' => $u->last_login_at?->diffForHumans(),
        ]);

        return Inertia::render('Admin/Users/Index', [
            'users'   => $users,
            'filters' => $request->only(['search', 'role', 'active']),
        ]);
    }

    public function create()
    {
        $wards = Ward::where('local_government_id', auth()->user()->local_government_id)
            ->orderBy('ward_number')
            ->get(['id', 'ward_number', 'ward_chairperson']);

        return Inertia::render('Admin/Users/Form', compact('wards'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:200',
            'email'      => 'required|email|unique:users,email',
            'password'   => ['required', Password::min(8)],
            'role'       => 'required|in:enumerator,ward_reviewer,ward_admin,municipal_admin',
            'phone'      => 'nullable|string|max:15',
            'ward_ids'   => 'nullable|array',
            'ward_ids.*' => 'integer|exists:wards,id',
        ]);

        $user = User::create([
            'name'                => $data['name'],
            'email'               => $data['email'],
            'password'            => Hash::make($data['password']),
            'role'                => $data['role'],
            'phone'               => $data['phone'] ?? null,
            'local_government_id' => auth()->user()->local_government_id,
            'is_active'           => true,
        ]);

        if (!empty($data['ward_ids'])) {
            $user->assignedWards()->attach(
                collect($data['ward_ids'])->mapWithKeys(fn($id) => [
                    $id => ['assigned_at' => now(), 'assigned_by' => auth()->id()]
                ])->toArray()
            );
        }

        return redirect()->route('admin.users.index')->with('success', "User {$user->name} created.");
    }

    public function show(User $user)
    {
        $this->guardTarget($user);
        $user->load('assignedWards');
        $wards = Ward::where('local_government_id', auth()->user()->local_government_id)
            ->orderBy('ward_number')->get(['id', 'ward_number', 'ward_chairperson']);
        $assignedWardIds = $user->assignedWards()->pluck('wards.id')->toArray();

        return Inertia::render('Admin/Users/Form', [
            'user'           => $user->only('id','name','email','role','phone','is_active'),
            'wards'          => $wards,
            'assignedWardIds'=> $assignedWardIds,
        ]);
    }

    public function edit(User $user)
    {
        $this->guardTarget($user);
        $wards = Ward::where('local_government_id', auth()->user()->local_government_id)
            ->orderBy('ward_number')->get(['id', 'ward_number', 'ward_chairperson']);
        $assignedWardIds = $user->assignedWards()->pluck('wards.id')->toArray();

        return Inertia::render('Admin/Users/Form', [
            'user'           => $user->only('id','name','email','role','phone','is_active'),
            'wards'          => $wards,
            'assignedWardIds'=> $assignedWardIds,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->guardTarget($user);

        // A super admin's role is not editable here: the role list deliberately
        // has no super_admin, so saving the form would otherwise demote them.
        $keepsRole = $user->isSuperAdmin();

        $data = $request->validate([
            'name'       => 'required|string|max:200',
            'email'      => "required|email|unique:users,email,{$user->id}",
            'role'       => $keepsRole ? 'nullable' : 'required|in:enumerator,ward_reviewer,ward_admin,municipal_admin',
            'phone'      => 'nullable|string|max:15',
            'ward_ids'   => 'nullable|array',
            'ward_ids.*' => 'integer|exists:wards,id',
            'password'   => ['nullable', Password::min(8)],
        ]);

        $updateData = [
            'name'      => $data['name'],
            'email'     => $data['email'],
            'role'      => $keepsRole ? $user->role : $data['role'],
            'phone'     => $data['phone'] ?? null,
            // Nobody can switch off their own account from this form.
            'is_active' => $user->id === auth()->id() ? true : $request->boolean('is_active'),
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        $user->assignedWards()->sync(
            collect($data['ward_ids'] ?? [])->mapWithKeys(fn($id) => [
                $id => ['assigned_at' => now(), 'assigned_by' => auth()->id()]
            ])->toArray()
        );

        return redirect()->route('admin.users.index')->with('success', "User {$user->name} updated.");
    }

    public function destroy(User $user)
    {
        $this->guardTarget($user);
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', "User {$user->name} removed.");
    }

    public function toggleActive(User $user)
    {
        $this->guardTarget($user);
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }
        $user->update(['is_active' => ! $user->is_active]);
        $status = $user->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "{$user->name} {$status}.");
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->guardTarget($user);
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $user->update(['password' => Hash::make($data['password'])]);
        return back()->with('success', "Password reset for {$user->name}.");
    }

    public function assignments(): \Inertia\Response
    {
        $lgId = auth()->user()->local_government_id;

        $users = User::with('assignedWards')
            ->where('local_government_id', $lgId)
            ->whereIn('role', ['enumerator', 'ward_reviewer', 'ward_admin'])
            ->orderBy('role')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id'                => $u->id,
                'name'              => $u->name,
                'email'             => $u->email,
                'role'              => $u->role,
                'is_active'         => $u->is_active,
                'assigned_ward_ids' => $u->assignedWards->pluck('id')->values()->toArray(),
            ]);

        $wards = Ward::where('local_government_id', $lgId)
            ->orderBy('ward_number')
            ->get(['id', 'ward_number']);

        return Inertia::render('Admin/WardAssignments', compact('users', 'wards'));
    }

    public function assignWards(Request $request, User $user): JsonResponse
    {
        abort_unless($user->local_government_id === auth()->user()->local_government_id, 403);
        abort_unless(in_array($user->role, ['enumerator', 'ward_reviewer', 'ward_admin']), 422);

        $data = $request->validate([
            'ward_ids'   => 'present|array',
            'ward_ids.*' => 'integer|exists:wards,id',
        ]);

        $user->assignedWards()->sync(
            collect($data['ward_ids'])->mapWithKeys(fn($id) => [
                $id => ['assigned_at' => now(), 'assigned_by' => auth()->id()]
            ])->toArray()
        );

        return response()->json([
            'assigned_ward_ids' => $data['ward_ids'],
        ]);
    }
}
