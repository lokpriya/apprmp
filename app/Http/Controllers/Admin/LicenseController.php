<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LocalGovernment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function generate(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $request->validate(['days' => 'required|integer|min:1|max:3650']);

        $lg     = LocalGovernment::find(auth()->user()->local_government_id) ?? LocalGovernment::first();
        $days   = (int) $request->days;
        $expiry = now()->addDays($days)->format('Y-m-d');
        $payload = "{$lg->id}|{$expiry}";
        $hmac   = hash_hmac('sha256', $payload, config('app.key'));
        $key    = "{$payload}|{$hmac}";

        $lg->update(['licensed_until' => $expiry, 'license_key' => $key]);

        return redirect()->route('admin.settings.index', ['tab' => 'license'])
            ->with('success', "लाइसेन्स उत्पन्न र सक्रिय गरियो। म्याद: {$expiry}")
            ->with('generated_key', $key);
    }

    public function activate(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $request->validate(['license_key' => 'required|string|max:500']);

        $key   = trim($request->license_key);
        $parts = explode('|', $key);

        if (count($parts) !== 3) {
            return back()->withErrors(['license_key' => 'अमान्य लाइसेन्स की ढाँचा।'])->withInput();
        }

        [$lgId, $expiry, $hmac] = $parts;

        $expected = hash_hmac('sha256', "{$lgId}|{$expiry}", config('app.key'));
        if (!hash_equals($expected, $hmac)) {
            return back()->withErrors(['license_key' => 'लाइसेन्स की प्रमाणीकरण असफल — की परिवर्तन भएको छ।'])->withInput();
        }

        $lg = LocalGovernment::find((int) $lgId);
        if (!$lg) {
            return back()->withErrors(['license_key' => 'यो की मान्य स्थानीय सरकारको लागि होइन।'])->withInput();
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) {
            return back()->withErrors(['license_key' => 'अमान्य म्याद मिति ढाँचा।'])->withInput();
        }

        $lg->update([
            'licensed_until' => $expiry,
            'license_key'    => $key,
        ]);

        return redirect()->route('admin.settings.index', ['tab' => 'license'])
            ->with('success', "लाइसेन्स सक्रिय गरियो। म्याद मिति: {$expiry}");
    }
}
