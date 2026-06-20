<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\PublicFeedback;
use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class FeedbackController extends Controller
{
    public function create()
    {
        // Signed timing token: sha256(timestamp + APP_KEY), valid for 30 min
        $ts    = time();
        $token = $ts . '.' . hash_hmac('sha256', (string) $ts, config('app.key'));

        $wards = Ward::orderBy('ward_number')->pluck('ward_number')->toArray();

        return Inertia::render('Public/Feedback', [
            'formToken'  => $token,
            'categories' => PublicFeedback::categories(),
            'wards'      => $wards,
        ]);
    }

    public function store(Request $request)
    {
        // ── Honeypot check ──────────────────────────────────────────
        if ($request->input('website') !== null && $request->input('website') !== '') {
            return back()->with('success', 'धन्यवाद! तपाईंको सुझाव प्राप्त भयो।');
        }

        // ── Timing check (min 4 seconds) ────────────────────────────
        $token = $request->input('form_token', '');
        [$ts, $sig] = array_pad(explode('.', $token, 2), 2, '');
        $expectedSig = hash_hmac('sha256', $ts, config('app.key'));
        $elapsed     = time() - (int) $ts;

        if (!hash_equals($expectedSig, $sig ?? '') || $elapsed < 4 || $elapsed > 1800) {
            return back()->withErrors(['form_token' => 'अनुरोध मान्य छैन। पुनः प्रयास गर्नुहोस्।']);
        }

        // ── Validate fields ─────────────────────────────────────────
        $data = $request->validate([
            'name'        => 'nullable|string|max:100',
            'phone'       => 'nullable|string|max:20|regex:/^[0-9+\-\s]{7,15}$/',
            'email'       => 'nullable|email|max:150',
            'ward_number' => 'nullable|integer|min:1|max:50',
            'category'    => 'required|string|in:' . implode(',', array_keys(PublicFeedback::categories())),
            'message'     => 'required|string|min:10|max:2000',
        ], [
            'message.required' => 'सुझाव अनिवार्य छ।',
            'message.min'      => 'सुझाव कम्तीमा १० वर्णको हुनुपर्छ।',
            'category.required'=> 'विषय छान्नु अनिवार्य छ।',
            'phone.regex'      => 'फोन नम्बर मान्य छैन।',
        ]);

        // At least one contact field required
        if (empty($data['phone']) && empty($data['email'])) {
            return back()->withErrors(['phone' => 'फोन वा इमेल मध्ये एउटा अनिवार्य छ।'])->withInput();
        }

        PublicFeedback::create([
            ...$data,
            'ip_address' => $request->ip(),
            'form_token' => substr($token, 0, 100),
        ]);

        // Bust admin badge cache
        \Illuminate\Support\Facades\Cache::forget('public_feedback_new_count');

        return back()->with('feedbackSuccess', true);
    }
}
