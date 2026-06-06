<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonateController extends Controller
{
    public function start(Request $request, User $target)
    {
        $admin = $request->user();

        abort_if($request->session()->has('impersonator_id'), 403, 'Already impersonating.');
        abort_if($target->id === $admin->id, 403, 'Cannot impersonate yourself.');
        abort_if(in_array($target->role, ['super_admin', 'municipal_admin']), 403, 'Cannot impersonate admins.');
        abort_if(!$target->is_active, 403, 'Target user is inactive.');

        // super_admin can impersonate any non-admin; municipal_admin only within same LG
        if ($admin->role === 'municipal_admin') {
            abort_if(
                $target->local_government_id !== $admin->local_government_id,
                403,
                'Cannot impersonate users from another local government.'
            );
        }

        AuditLog::create([
            'user_id'        => $admin->id,
            'event_type'     => 'impersonation_start',
            'auditable_type' => User::class,
            'auditable_id'   => $target->id,
            'old_values'     => ['impersonator' => $admin->name, 'impersonator_role' => $admin->role],
            'new_values'     => ['target' => $target->name, 'target_role' => $target->role],
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
        ]);

        // loginUsingId regenerates the session — store impersonator_id AFTER login
        Auth::loginUsingId($target->id);
        $request->session()->put('impersonator_id', $admin->id);

        // Only true admins can access /admin; everyone else goes to /dashboard
        $landingUrl = in_array($target->role, ['super_admin', 'municipal_admin'])
            ? '/admin'
            : '/dashboard';

        return redirect($landingUrl);
    }

    public function stop(Request $request)
    {
        $impersonatorId = $request->session()->get('impersonator_id');
        abort_unless($impersonatorId, 403, 'Not impersonating.');

        $currentUser = $request->user();

        $request->session()->forget('impersonator_id');

        AuditLog::create([
            'user_id'        => $impersonatorId,
            'event_type'     => 'impersonation_stop',
            'auditable_type' => User::class,
            'auditable_id'   => $currentUser?->id,
            'old_values'     => ['was_impersonating' => $currentUser?->name, 'role' => $currentUser?->role],
            'new_values'     => [],
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
        ]);

        Auth::loginUsingId($impersonatorId);

        return redirect('/admin');
    }
}
