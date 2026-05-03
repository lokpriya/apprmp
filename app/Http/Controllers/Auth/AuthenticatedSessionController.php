<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LocalGovernment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): \Inertia\Response
    {
        $lg = LocalGovernment::getFirstCached();
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
            'lg'     => $lg,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // If 2FA is enabled, park login in session and challenge instead of completing it
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user->hasTwoFactorEnabled()) {
            $remember = $request->boolean('remember');
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->put('login.id', $user->id);
            $request->session()->put('login.remember', $remember);
            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
