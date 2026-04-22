<?php

namespace App\Http\Middleware;

use App\Models\EditRequest;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'inertia';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => fn () => $request->user() ? [
                'user' => [
                    'id'    => $request->user()->id,
                    'name'  => $request->user()->name,
                    'email' => $request->user()->email,
                    'role'  => $request->user()->role,
                ],
                'local_government' => $request->user()->localGovernment?->name_np,
                'lg' => $request->user()->localGovernment?->only('id', 'name_np', 'name_en', 'photo_dir'),
            ] : null,
            'editRequestsCount' => fn () => $request->user()?->isAdmin()
                ? EditRequest::where('status', 'pending')->count()
                : 0,
            'flash' => [
                'success'                  => fn () => $request->session()->get('success'),
                'error'                    => fn () => $request->session()->get('error'),
                'import_result'            => fn () => $request->session()->get('import_result'),
                'settlement_import_result'        => fn () => $request->session()->get('settlement_import_result'),
                'ward_import_result'              => fn () => $request->session()->get('ward_import_result'),
                'ward_settlements_import_result'  => fn () => $request->session()->get('ward_settlements_import_result'),
            ],
        ];
    }
}
