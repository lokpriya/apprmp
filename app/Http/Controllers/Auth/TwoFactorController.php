<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    // ── Setup page (forced for admin roles) ──────────────────────────────────

    public function showSetupPage(Request $request): Response
    {
        $user = $request->user();
        $lg = \App\Models\LocalGovernment::getFirstCached();
        return Inertia::render('Auth/TwoFactorSetup', [
            'twoFactorEnabled'  => $user->hasTwoFactorEnabled(),
            'twoFactorRequired' => $user->twoFactorRequiredForRole(),
            'lg' => $lg ? [
                'name_np'        => $lg->name_np,
                'name_en'        => $lg->name_en,
                'logo'           => $lg->logo,
                'login_branding' => $lg->welcome_config['login_branding'] ?? null,
            ] : null,
        ]);
    }

    // ── Setup (from profile) ─────────────────────────────────────────────────

    /** Generate a new secret and QR SVG — does NOT enable 2FA yet */
    public function setup(Request $request): \Illuminate\Http\JsonResponse
    {
        $user   = $request->user();
        $secret = $this->google2fa->generateSecretKey();

        $request->session()->put('2fa.pending_secret', $secret);

        $qrUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        $renderer = new ImageRenderer(new RendererStyle(192), new SvgImageBackEnd());
        $svg      = (new Writer($renderer))->writeString($qrUrl);

        return response()->json([
            'secret' => $secret,
            'qr_svg' => base64_encode($svg),
        ]);
    }

    /** Confirm the code, finalize enable, return recovery codes */
    public function confirm(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['code' => 'required|string|digits:6']);

        $secret = $request->session()->get('2fa.pending_secret');
        if (!$secret) {
            return response()->json(['message' => 'सेटअप सत्र समाप्त भयो। पुनः प्रयास गर्नुहोस्।'], 422);
        }

        $valid = $this->google2fa->verifyKey($secret, $request->code, 4);
        if (!$valid) {
            return response()->json(['errors' => ['code' => 'कोड गलत छ। पुनः प्रयास गर्नुहोस्।']], 422);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $request->user()->update([
            'two_factor_secret'         => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            'two_factor_confirmed_at'   => now(),
        ]);

        $request->session()->forget('2fa.pending_secret');

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }

    /** Disable 2FA — requires current password confirmation */
    public function disable(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['password' => 'required|current_password']);

        $request->user()->update([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ]);

        return response()->json(['message' => '2FA निष्क्रिय गरियो।']);
    }

    /** Regenerate recovery codes */
    public function regenerateCodes(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['password' => 'required|current_password']);

        $recoveryCodes = $this->generateRecoveryCodes();
        $request->user()->update([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }

    // ── Challenge (during login) ──────────────────────────────────────────────

    /** Show the 2FA challenge page */
    public function showChallenge(Request $request): Response|RedirectResponse
    {
        if (!$request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        $lg = \App\Models\LocalGovernment::getFirstCached();
        return Inertia::render('Auth/TwoFactorChallenge', [
            'lg' => $lg ? [
                'name_np'        => $lg->name_np,
                'name_en'        => $lg->name_en,
                'logo'           => $lg->logo,
                'login_branding' => $lg->welcome_config['login_branding'] ?? null,
            ] : null,
        ]);
    }

    /** Verify the challenge code */
    public function verifyChallenge(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|string']);

        $userId = $request->session()->get('login.id');
        if (!$userId) {
            return redirect()->route('login')->withErrors(['code' => 'सत्र समाप्त भयो।']);
        }

        // Expire the 2FA window after 10 minutes
        $parkedAt = $request->session()->get('login.parked_at', 0);
        if (now()->timestamp - $parkedAt > 600) {
            $request->session()->forget(['login.id', 'login.remember', 'login.parked_at']);
            return redirect()->route('login')->withErrors(['code' => 'सत्र समाप्त भयो। पुनः लगइन गर्नुहोस्।']);
        }

        // Brute-force protection: max 5 attempts per 10 minutes per user
        $throttleKey = '2fa.' . $userId;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors(['code' => "धेरै प्रयास भयो। {$seconds} सेकेन्डपछि पुनः प्रयास गर्नुहोस्।"]);
        }

        $user = \App\Models\User::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }

        $code = trim($request->code);

        // Try TOTP code
        if (strlen($code) === 6 && ctype_digit($code)) {
            try {
                $secret = decrypt($user->two_factor_secret);
                if ($this->google2fa->verifyKey($secret, $code, 4)) {
                    return $this->completeLogin($request, $user, $throttleKey);
                }
            } catch (\Exception $e) {
                return redirect()->route('login')
                    ->withErrors(['code' => '2FA कन्फिगरेसन त्रुटि। प्रशासकलाई सम्पर्क गर्नुहोस्।']);
            }
        }

        // Try recovery code
        $recoveryCodes = $user->getRecoveryCodes();
        $normalised     = strtoupper(str_replace('-', '', $code));
        foreach ($recoveryCodes as $i => $rc) {
            if (strtoupper(str_replace('-', '', $rc)) === $normalised) {
                // Burn the used recovery code
                unset($recoveryCodes[$i]);
                $user->update([
                    'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes))),
                ]);
                return $this->completeLogin($request, $user, $throttleKey);
            }
        }

        RateLimiter::hit($throttleKey, 600);
        return back()->withErrors(['code' => 'कोड अमान्य छ। पुनः प्रयास गर्नुहोस्।']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function completeLogin(Request $request, \App\Models\User $user, string $throttleKey): RedirectResponse
    {
        RateLimiter::clear($throttleKey);
        $remember = $request->session()->pull('login.remember', false);
        $request->session()->forget(['login.id', 'login.parked_at']);
        $request->session()->regenerate();

        \Illuminate\Support\Facades\Auth::login($user, $remember);

        return redirect()->intended(route('dashboard'));
    }

    private function generateRecoveryCodes(): array
    {
        return array_map(
            fn () => strtoupper(Str::random(5)) . '-' . strtoupper(Str::random(5)),
            range(1, 8)
        );
    }
}
