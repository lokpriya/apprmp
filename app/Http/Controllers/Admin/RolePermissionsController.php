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
        $saved   = $lg->role_permissions ?? [];
        $defaults = RolePermissions::defaults();

        // Merge saved over defaults so first visit shows sensible defaults
        $rolePerms = [];
        foreach (RolePermissions::roles() as $role) {
            $name = $role['name'];
            $rolePerms[$name] = $saved[$name] ?? $defaults[$name] ?? [];
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
            'role'        => 'required|in:super_admin,municipal_admin,ward_admin,ward_reviewer,enumerator',
            'permissions' => 'present|array',
            'permissions.*' => 'string',
        ]);

        $lg  = $this->lg();
        $all = $lg->role_permissions ?? RolePermissions::defaults();

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
        'enumerator'      => 'Enumerator',
        default           => $role,
    };
}
