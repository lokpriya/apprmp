<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LocalGovernment;
use App\Support\RolePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RolePermissionsController extends Controller
{
    private function lg(): LocalGovernment
    {
        return LocalGovernment::findOrFail(auth()->user()->local_government_id);
    }

    public function index()
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        $lg      = $this->lg();
        // Saved lists over defaults, so the first visit shows sensible defaults
        // and a permission added since the last save shows as it really applies.
        $rolePerms = [];
        foreach (RolePermissions::roles() as $role) {
            $rolePerms[$role['name']] = RolePermissions::effective($role['name'], $lg->role_permissions);
        }

        return Inertia::render('Admin/RolePermissions', [
            'roles'       => RolePermissions::roles(),
            'groups'      => RolePermissions::groups(),
            'rolePerms'   => $rolePerms,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        $request->validate([
            'role'        => 'required|in:super_admin,municipal_admin,ward_admin,ward_reviewer,enumerator,tole_coordinator',
            'permissions' => 'present|array',
            'permissions.*' => 'string',
        ]);

        $lg  = $this->lg();
        // Resolve the existing lists first and apply this edit after: done the
        // other way round, an untick of a newly added permission would be put
        // straight back as an "undecided" default.
        $all = RolePermissions::forSaving($lg->role_permissions ?? RolePermissions::defaults());

        $all[$request->role] = array_values(array_unique($request->permissions));

        $lg->update(['role_permissions' => $all]);

        return back()->with('success', roleLabel($request->role).' अनुमतिहरू सुरक्षित गरियो।');
    }
}

function roleLabel(string $role): string
{
    return match($role) {
        'super_admin'     => 'Super Admin',
        'municipal_admin' => 'Municipal Admin',
        'ward_reviewer'   => 'Ward Reviewer',
        'ward_admin'      => 'Ward Admin',
        'enumerator'      => 'Enumerator',
        'tole_coordinator'=> 'Tole Coordinator',
        default           => $role,
    };
}
