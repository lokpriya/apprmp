<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SettlementsTemplateExport;
use App\Exports\WardSettlementsTemplateExport;
use App\Exports\WardsTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\SettlementsImport;
use App\Imports\WardSettlementsImport;
use App\Imports\WardsImport;
use App\Models\District;
use App\Models\LgEmployee;
use App\Models\LgDownloadLink;
use App\Models\LgPhoto;
use App\Models\LgRepresentative;
use App\Models\LocalGovernment;
use App\Models\Province;
use App\Models\Settlement;
use App\Models\SurveyOption;
use App\Models\Ward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class SettingsController extends Controller
{
    private function lg(): LocalGovernment
    {
        return LocalGovernment::with(['district.province'])
            ->findOrFail(auth()->user()->local_government_id);
    }

    // ── Main page ─────────────────────────────────────────────────────────────

    public function index(): \Inertia\Response
    {
        $lg        = $this->lg();
        $provinces = Province::where('code', '!=', 'P1')->orderBy('name_en')->get(['id','name_en','name_np']);
        $districts = District::where('code', '!=', 'ILA')->orderBy('name_en')->get(['id','name_en','name_np','province_id']);
        $wards     = Ward::where('local_government_id', $lg->id)
            ->withCount('settlements')
            ->with('settlements:id,ward_id,name_np,name_en,settlement_type')
            ->orderBy('ward_number')
            ->get();

        $profileSections = LocalGovernment::profileSections();

        $lockedByName = $lg->settings_locked_by
            ? \App\Models\User::find($lg->settings_locked_by)?->name
            : null;

        $representatives = LgRepresentative::where('local_government_id', $lg->id)
            ->with(['ward:id,ward_number', 'addressWard:id,ward_number', 'addressTole:id,name_np'])
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $surveyOptsForRep = SurveyOption::whereIn('category', ['ethnicity', 'education_level'])
            ->where('is_active', true)
            ->orderBy('category')->orderBy('sort_order')
            ->get(['category', 'value', 'label_ne'])
            ->groupBy('category');

        $employees = LgEmployee::where('local_government_id', $lg->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $photos = LgPhoto::where('local_government_id', $lg->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $downloadLinks = LgDownloadLink::where('local_government_id', $lg->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn($d) => array_merge($d->toArray(), [
                'thumbnail_url' => $d->thumbnail ? Storage::url($d->thumbnail) : null,
            ]));

        return Inertia::render('Admin/Settings/Index', [
            'maintenanceConfig'    => $lg->maintenance_config    ?? [],
            'surveyConfig'         => $lg->survey_config         ?? [],
            'googleSheetsConfig'   => $lg->google_sheets_config  ?? [],
            'licenseData'          => [
                'licensed_until'  => $lg->licensed_until?->format('Y-m-d'),
                'is_licensed'     => $lg->isLicensed(),
                'days_remaining'  => $lg->licensed_until
                    ? (int) now()->startOfDay()->diffInDays($lg->licensed_until->startOfDay(), false)
                    : null,
                'license_key'     => $lg->license_key,
            ],
            'lg'             => $lg,
            'provinces'      => $provinces,
            'districts'      => $districts,
            'wards'          => $wards,
            'profileSections'=> $profileSections,
            'tab'            => request('tab', 'general'),
            'isLocked'       => $lg->isSettingsLocked(),
            'lockedAt'       => $lg->settings_locked_at?->toDateTimeString(),
            'lockedByName'   => $lockedByName,
            'isSuperAdmin'   => auth()->user()?->isSuperAdmin() ?? false,
            'welcomeConfig'  => $lg->welcome_config ?? [],
            'loginBranding'  => $lg->welcome_config['login_branding'] ?? [],
            'representatives'    => $representatives,
            'surveyOptsForRep'   => $surveyOptsForRep,
            'employees'          => $employees,
            'photos'             => $photos,
            'downloadLinks'      => $downloadLinks,
            'settlements'        => Settlement::whereHas('ward', fn($q) => $q->where('local_government_id', $lg->id))
                                        ->orderBy('name_np')->get(['id', 'ward_id', 'name_np']),
        ]);
    }

    // ── Lock / Unlock ─────────────────────────────────────────────────────────

    public function lock(): RedirectResponse
    {
        $lg = $this->lg();
        $lg->update([
            'settings_locked_at' => now(),
            'settings_locked_by' => auth()->id(),
        ]);
        return redirect()->route('admin.settings.index')
            ->with('success', 'सेटिङ लक गरियो। अब सम्पादन गर्न Super Admin आवश्यक छ।');
    }

    public function unlock(): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $lg = $this->lg();
        $lg->update([
            'settings_locked_at' => null,
            'settings_locked_by' => null,
        ]);
        return redirect()->route('admin.settings.index')
            ->with('success', 'सेटिङ अनलक गरियो।');
    }

    // ── Guard helper ──────────────────────────────────────────────────────────

    private function abortIfLocked(): void
    {
        if ($this->lg()->isSettingsLocked()) {
            abort(403, 'Settings are locked. Unlock first to make changes.');
        }
    }

    // ── General info ──────────────────────────────────────────────────────────

    public function updateGeneral(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $lg = $this->lg();

        $data = $request->validate([
            'name_np'          => 'required|string|max:200',
            'name_en'          => 'required|string|max:200',
            'type'             => 'required|in:municipality,rural_municipality,sub_metropolitan,metropolitan',
            'district_id'      => 'required|exists:districts,id',
            'ward_count'       => 'required|integer|min:1|max:35',
            'contact_email'    => 'nullable|email|max:100',
            'contact_phone'    => 'nullable|string|max:20',
            'website'          => 'nullable|url|max:200',
            'established_date' => 'nullable|date',
        ]);

        $lg->update($data);
        Cache::forget('active_lg_full');

        return redirect()->route('admin.settings.index', ['tab' => 'general'])
            ->with('success', 'General settings saved.');
    }

    // ── Logo upload ───────────────────────────────────────────────────────────

    public function uploadLogo(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $lg = $this->lg();

        if ($lg->logo && Storage::disk('public')->exists($lg->logo)) {
            Storage::disk('public')->delete($lg->logo);
        }

        $path = $request->file('logo')->store('logos', 'public');
        $lg->update(['logo' => $path]);
        Cache::forget('active_lg_full');

        return redirect()->route('admin.settings.index', ['tab' => 'general'])
            ->with('success', 'Logo updated.');
    }

    // ── Profile narrative content ─────────────────────────────────────────────

    public function updateProfile(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $lg = $this->lg();

        $keys = array_keys(LocalGovernment::profileSections());
        $rules = [];
        foreach ($keys as $key) {
            $rules["profile.$key"] = 'nullable|string|max:10000';
        }

        $request->validate($rules);

        // Laravel returns validated nested arrays as ['profile' => ['key' => 'value']],
        // not as ['profile.key' => 'value'], so read directly from input.
        $profileInput = $request->input('profile', []);
        $content = [];
        foreach ($keys as $key) {
            $value = $profileInput[$key] ?? null;
            $content[$key] = ($value !== '' && $value !== null) ? $value : null;
        }

        $lg->update(['profile_content' => $content]);

        return redirect()->route('admin.settings.index', ['tab' => 'profile'])
            ->with('success', 'Profile content saved.');
    }

    // ── Map configuration ─────────────────────────────────────────────────────

    public function updateMap(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $lg = $this->lg();

        $data = $request->validate([
            'center_lat'           => 'required|numeric|between:-90,90',
            'center_lng'           => 'required|numeric|between:-180,180',
            'zoom'                 => 'required|integer|between:5,18',
            'geojson_url'          => 'nullable|string|max:1000',
            'ward_number_property' => 'nullable|string|max:100',
            'markers'              => 'nullable|array',
            'markers.*.name'       => 'required_with:markers|string|max:200',
            'markers.*.lat'        => 'required_with:markers|numeric|between:-90,90',
            'markers.*.lng'        => 'required_with:markers|numeric|between:-180,180',
            'markers.*.type'       => 'required_with:markers|in:office,school,health,ward,tourism,other',
            'markers.*.popup'      => 'nullable|string|max:500',
        ]);

        $markers = [];
        foreach ($request->input('markers', []) as $m) {
            if (!empty($m['name']) && !empty($m['lat']) && !empty($m['lng'])) {
                $markers[] = [
                    'name'  => $m['name'],
                    'lat'   => (float) $m['lat'],
                    'lng'   => (float) $m['lng'],
                    'type'  => $m['type'] ?? 'other',
                    'popup' => $m['popup'] ?? null,
                ];
            }
        }

        $lg->update([
            'map_config' => [
                'center_lat'           => (float) $data['center_lat'],
                'center_lng'           => (float) $data['center_lng'],
                'zoom'                 => (int)   $data['zoom'],
                'geojson_url'          => $data['geojson_url'] ?? null,
                'ward_number_property' => $data['ward_number_property'] ?? 'WARD_NO',
                'markers'              => $markers,
            ],
        ]);
        Cache::forget('active_lg_full');

        return redirect()->route('admin.settings.index', ['tab' => 'map'])
            ->with('success', 'Map settings saved.');
    }

    public function uploadMapGeojson(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->abortIfLocked();
        $request->validate([
            'geojson' => 'required|file|mimes:json,geojson,txt|max:10240',
        ]);

        $lg   = $this->lg();
        $file = $request->file('geojson');

        // Validate it is actually valid JSON/GeoJSON
        $content = file_get_contents($file->getRealPath());
        $parsed  = json_decode($content, true);
        if (!$parsed || !isset($parsed['type'])) {
            return response()->json(['error' => 'अमान्य GeoJSON फाइल। Valid JSON फाइल मात्र अपलोड गर्नुहोस्।'], 422);
        }

        // Store in public storage and update map_config
        $path     = "map/ward-boundary-{$lg->id}.geojson";
        Storage::disk('public')->put($path, $content);
        $publicUrl = Storage::disk('public')->url($path);

        $cfg = $lg->map_config ?? [];
        $cfg['geojson_url'] = $publicUrl;
        $lg->update(['map_config' => $cfg]);
        Cache::forget('active_lg_full');

        return response()->json(['url' => $publicUrl]);
    }

    public function deleteMapGeojson(): \Illuminate\Http\JsonResponse
    {
        $this->abortIfLocked();
        $lg   = $this->lg();
        $path = "map/ward-boundary-{$lg->id}.geojson";
        Storage::disk('public')->delete($path);

        $cfg = $lg->map_config ?? [];
        $cfg['geojson_url'] = null;
        $lg->update(['map_config' => $cfg]);
        Cache::forget('active_lg_full');

        return response()->json(['ok' => true]);
    }

    // ── Photo directory ───────────────────────────────────────────────────────

    public function updatePhotoDir(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $lg = $this->lg();

        $data = $request->validate([
            'household_base_url'    => 'nullable|string|max:500',
            'institution_base_url'  => 'nullable|string|max:500',
            'gallery_url'           => 'nullable|string|max:500',
        ]);

        $lg->update([
            'photo_dir' => [
                'household_base_url'   => rtrim($data['household_base_url'] ?? '', '/') ?: null,
                'institution_base_url' => rtrim($data['institution_base_url'] ?? '', '/') ?: null,
                'gallery_url'          => rtrim($data['gallery_url'] ?? '', '/') ?: null,
            ],
        ]);

        return redirect()->route('admin.settings.index', ['tab' => 'photo'])
            ->with('success', 'फोटो डाइरेक्टरी सेटिङ सुरक्षित भयो।');
    }

    // ── Welcome config ────────────────────────────────────────────────────────

    public function updateWelcome(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $lg = $this->lg();

        $data = $request->validate([
            'show_banner'                 => 'boolean',
            'message'                     => 'nullable|string|max:200',
            'message_body'                => 'nullable|string|max:2000',
            'chairperson.name'            => 'nullable|string|max:100',
            'chairperson.designation'     => 'nullable|string|max:100',
            'chairperson.contact'         => 'nullable|string|max:30',
            'chairperson.email'           => 'nullable|email|max:100',
            'vice_chairperson.name'       => 'nullable|string|max:100',
            'vice_chairperson.designation'=> 'nullable|string|max:100',
            'vice_chairperson.contact'    => 'nullable|string|max:30',
            'vice_chairperson.email'      => 'nullable|email|max:100',
            'cao.name'                    => 'nullable|string|max:100',
            'cao.designation'             => 'nullable|string|max:100',
            'cao.contact'                 => 'nullable|string|max:30',
            'cao.email'                   => 'nullable|email|max:100',
        ]);

        $existing = $lg->welcome_config ?? [];

        $lg->update([
            'welcome_config' => array_merge($existing, [
                'show_banner'      => (bool) ($data['show_banner'] ?? true),
                'message'          => $data['message']       ?? null,
                'message_body'     => $data['message_body']  ?? null,
                'chairperson'      => array_merge($existing['chairperson']      ?? [], $data['chairperson']      ?? []),
                'vice_chairperson' => array_merge($existing['vice_chairperson'] ?? [], $data['vice_chairperson'] ?? []),
                'cao'              => array_merge($existing['cao']              ?? [], $data['cao']              ?? []),
            ]),
        ]);

        return redirect()->route('admin.settings.index', ['tab' => 'welcome'])
            ->with('success', 'स्वागत सन्देश सेटिङ सुरक्षित भयो।');
    }

    public function uploadMapImage(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $request->validate([
            'map_image' => 'required|file|mimes:jpeg,png,jpg,svg|max:4096',
        ]);

        $lg      = $this->lg();
        $existing = $lg->welcome_config ?? [];
        $oldPath  = $existing['map_image'] ?? null;

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('map_image')->store('lg-profile', 'public');
        $existing['map_image'] = $path;
        $lg->update(['welcome_config' => $existing]);

        return redirect()->route('admin.settings.index', ['tab' => 'welcome'])
            ->with('success', 'नक्सा चित्र अपलोड भयो।');
    }

    public function deleteMapImage(): RedirectResponse
    {
        $this->abortIfLocked();
        $lg      = $this->lg();
        $existing = $lg->welcome_config ?? [];
        $oldPath  = $existing['map_image'] ?? null;

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        unset($existing['map_image']);
        $lg->update(['welcome_config' => $existing]);

        return redirect()->route('admin.settings.index', ['tab' => 'welcome'])
            ->with('success', 'नक्सा चित्र हटाइयो।');
    }

    // ── Login page branding ──────────────────────────────────────────────────

    public function updateLoginBranding(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $data = $request->validate([
            'headline_line1' => 'nullable|string|max:80',
            'headline_line2' => 'nullable|string|max:80',
            'highlight_word' => 'nullable|string|max:40',
            'subtitle'       => 'nullable|string|max:300',
            'stats'          => 'nullable|array|max:3',
            'stats.*.value'  => 'nullable|string|max:20',
            'stats.*.label'  => 'nullable|string|max:40',
        ]);

        $lg       = $this->lg();
        $existing = $lg->welcome_config ?? [];
        $existing['login_branding'] = array_merge(
            $existing['login_branding'] ?? [],
            [
                'headline_line1' => $data['headline_line1'] ?? null,
                'headline_line2' => $data['headline_line2'] ?? null,
                'highlight_word' => $data['highlight_word'] ?? null,
                'subtitle'       => $data['subtitle']       ?? null,
                'stats'          => $data['stats']          ?? null,
            ]
        );
        $lg->update(['welcome_config' => $existing]);

        return redirect()->route('admin.settings.index', ['tab' => 'welcome'])
            ->with('success', 'लगइन पृष्ठ ब्रान्डिङ सुरक्षित भयो।');
    }

    public function uploadLoginLandmark(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $request->validate(['landmark' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096']);

        $lg       = $this->lg();
        $existing = $lg->welcome_config ?? [];
        $oldPath  = $existing['login_branding']['landmark'] ?? null;

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('landmark')->store('lg-profile', 'public');
        $existing['login_branding'] = array_merge($existing['login_branding'] ?? [], ['landmark' => $path]);
        $lg->update(['welcome_config' => $existing]);

        return redirect()->route('admin.settings.index', ['tab' => 'welcome'])
            ->with('success', 'ल्यान्डमार्क फोटो अपलोड भयो।');
    }

    public function deleteLoginLandmark(): RedirectResponse
    {
        $this->abortIfLocked();
        $lg       = $this->lg();
        $existing = $lg->welcome_config ?? [];
        $oldPath  = $existing['login_branding']['landmark'] ?? null;

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        unset($existing['login_branding']['landmark']);
        $lg->update(['welcome_config' => $existing]);

        return redirect()->route('admin.settings.index', ['tab' => 'welcome'])
            ->with('success', 'ल्यान्डमार्क फोटो हटाइयो।');
    }

    public function uploadLeaderPhoto(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $request->validate([
            'role'  => 'required|in:chairperson,vice_chairperson,cao',
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $lg   = $this->lg();
        $role = $request->input('role');

        $existing = $lg->welcome_config ?? [];
        $oldPath  = $existing[$role]['photo'] ?? null;

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('photo')->store('lg-profile', 'public');

        $existing[$role]          = $existing[$role] ?? [];
        $existing[$role]['photo'] = $path;

        $lg->update(['welcome_config' => $existing]);

        return redirect()->route('admin.settings.index', ['tab' => 'welcome'])
            ->with('success', 'फोटो अपलोड भयो।');
    }

    // ── Wards ─────────────────────────────────────────────────────────────────

    public function storeWard(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $lg = $this->lg();

        $currentCount = Ward::where('local_government_id', $lg->id)->count();
        if ($currentCount >= $lg->ward_count) {
            return back()->withErrors([
                'ward_number' => 'थप वडा थप्न मिल्दैन — अनुमत वडा संख्या ('.$lg->ward_count.') पूरा भइसकेको छ। Super Admin लाई सम्पर्क गर्नुहोस्।',
            ]);
        }

        $data = $request->validate([
            'ward_number'      => 'required|integer|min:1|max:35',
            'ward_chairperson' => 'nullable|string|max:100',
            'contact_phone'    => 'nullable|string|max:20',
        ]);

        $exists = Ward::where('local_government_id', $lg->id)
            ->where('ward_number', $data['ward_number'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['ward_number' => 'Ward '.$data['ward_number'].' already exists.'])->with('tab', 'wards');
        }

        Ward::create(array_merge($data, ['local_government_id' => $lg->id]));

        // Keep ward_count in sync
        $lg->increment('ward_count');

        return redirect()->route('admin.settings.index', ['tab' => 'wards'])
            ->with('success', 'Ward '.$data['ward_number'].' added.');
    }

    public function updateWard(Request $request, Ward $ward): RedirectResponse
    {
        $this->abortIfLocked();
        $this->authorizeWard($ward);

        $data = $request->validate([
            'ward_chairperson' => 'nullable|string|max:100',
            'contact_phone'    => 'nullable|string|max:20',
        ]);

        $ward->update($data);

        return redirect()->route('admin.settings.index', ['tab' => 'wards'])
            ->with('success', 'Ward '.$ward->ward_number.' updated.');
    }

    public function destroyWard(Ward $ward): RedirectResponse
    {
        $this->abortIfLocked();
        $this->authorizeWard($ward);

        if ($ward->households()->count() > 0 || $ward->institutions()->count() > 0) {
            return back()->withErrors(['ward' => 'Cannot delete Ward '.$ward->ward_number.' — it has existing records.'])->with('tab', 'wards');
        }

        $lg = $this->lg();
        $ward->settlements()->delete();
        $ward->delete();
        $lg->decrement('ward_count');

        return redirect()->route('admin.settings.index', ['tab' => 'wards'])
            ->with('success', 'Ward deleted.');
    }

    public function downloadWardsTemplate(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $lg = $this->lg();
        return Excel::download(new WardsTemplateExport($lg->id), 'wards-template.xlsx');
    }

    public function downloadWardSettlementsTemplate(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $lg = $this->lg();
        return Excel::download(new WardSettlementsTemplateExport($lg->id), 'wards-and-settlements-template.xlsx');
    }

    public function importWardsAndSettlements(Request $request): RedirectResponse
    {
        $this->abortIfLocked();

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ], [
            'file.required' => 'Excel फाइल आवश्यक छ।',
            'file.mimes'    => 'xlsx वा xls फाइल मात्र मान्य।',
            'file.max'      => 'फाइलको आकार 5MB भन्दा बढी हुनु हुँदैन।',
        ]);

        $lg     = $this->lg();
        $import = new WardSettlementsImport($lg->id);

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'फाइल पढ्न सकिएन: ' . $e->getMessage()]);
        }

        // Sync ward_count if new wards were added beyond current limit
        $actual = Ward::where('local_government_id', $lg->id)->count();
        if ($actual > $lg->ward_count) {
            $lg->update(['ward_count' => $actual]);
        }

        return redirect()
            ->route('admin.settings.index', ['tab' => 'wards'])
            ->with('ward_settlements_import_result', [
                'wards_inserted'  => $import->wardSheet->inserted,
                'wards_updated'   => $import->wardSheet->updated,
                'wards_skipped'   => $import->wardSheet->skipped,
                'settl_inserted'  => $import->settlSheet->inserted,
                'settl_updated'   => $import->settlSheet->updated,
                'settl_skipped'   => $import->settlSheet->skipped,
                'errors'          => array_slice($import->errors(), 0, 50),
            ]);
    }

    public function importWards(Request $request): RedirectResponse
    {
        $this->abortIfLocked();

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ], [
            'file.required' => 'Excel फाइल आवश्यक छ।',
            'file.mimes'    => 'xlsx, xls वा csv फाइल मात्र मान्य।',
            'file.max'      => 'फाइलको आकार 5MB भन्दा बढी हुनु हुँदैन।',
        ]);

        $lg     = $this->lg();
        $import = new WardsImport($lg->id);

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'फाइल पढ्न सकिएन: ' . $e->getMessage()]);
        }

        // Sync ward_count with actual ward count after import
        $actual = Ward::where('local_government_id', $lg->id)->count();
        if ($actual > $lg->ward_count) {
            $lg->update(['ward_count' => $actual]);
        }

        return redirect()
            ->route('admin.settings.index', ['tab' => 'wards'])
            ->with('ward_import_result', [
                'inserted' => $import->inserted,
                'updated'  => $import->updated,
                'skipped'  => $import->skipped,
                'errors'   => array_slice($import->errors, 0, 50),
            ]);
    }

    public function bulkDestroyWards(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $lg = $this->lg();

        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        $wards = Ward::whereIn('id', $ids)
            ->where('local_government_id', $lg->id)
            ->get();

        $skipped  = 0;
        $deleted  = 0;
        foreach ($wards as $ward) {
            if ($ward->households()->count() > 0 || $ward->institutions()->count() > 0) {
                $skipped++;
                continue;
            }
            try {
                // Clean up all child records before deleting the ward
                $ward->settlements()->delete();
                DB::table('user_ward_assignments')->where('ward_id', $ward->id)->delete();
                $ward->delete();
                $deleted++;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() === 'HY000') {
                    DB::reconnect();
                    $ward->settlements()->delete();
                    DB::table('user_ward_assignments')->where('ward_id', $ward->id)->delete();
                    $ward->delete();
                    $deleted++;
                } else {
                    $skipped++;
                }
            }
        }

        // Recalculate ward_count
        $lg->update(['ward_count' => Ward::where('local_government_id', $lg->id)->count()]);

        $msg = "{$deleted} वडा मेटाइयो।";
        if ($skipped) $msg .= " {$skipped} वडा मेटाउन सकिएन (डेटा रेकर्ड छ)।";

        return redirect()->route('admin.settings.index', ['tab' => 'wards'])
            ->with('success', $msg);
    }

    // ── Settlements / Toles ───────────────────────────────────────────────────

    public function storeSettlement(Request $request, Ward $ward): RedirectResponse
    {
        $this->abortIfLocked();
        $this->authorizeWard($ward);

        $data = $request->validate([
            'name_np'         => 'required|string|max:200',
            'name_en'         => 'required|string|max:200',
            'settlement_type' => 'required|in:tole,gau,chowk,basti',
        ]);

        $ward->settlements()->create($data);

        return redirect()->route('admin.settings.index', ['tab' => 'toles', 'ward_id' => $ward->id])
            ->with('success', 'Tole/Settlement added.');
    }

    public function updateSettlement(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->abortIfLocked();
        $this->authorizeWard($settlement->ward);

        $data = $request->validate([
            'name_np'         => 'required|string|max:200',
            'name_en'         => 'required|string|max:200',
            'settlement_type' => 'required|in:tole,gau,chowk,basti',
        ]);

        $settlement->update($data);

        return redirect()->route('admin.settings.index', ['tab' => 'toles', 'ward_id' => $settlement->ward_id])
            ->with('success', 'Settlement updated.');
    }

    public function downloadSettlementsTemplate(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $lg = $this->lg();
        return Excel::download(
            new SettlementsTemplateExport($lg->id),
            'settlements-template.xlsx'
        );
    }

    public function importSettlements(Request $request): RedirectResponse
    {
        $this->abortIfLocked();

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ], [
            'file.required' => 'Excel फाइल आवश्यक छ।',
            'file.mimes'    => 'xlsx, xls वा csv फाइल मात्र मान्य।',
            'file.max'      => 'फाइलको आकार 5MB भन्दा बढी हुनु हुँदैन।',
        ]);

        $lg     = $this->lg();
        $import = new SettlementsImport($lg->id);

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'फाइल पढ्न सकिएन: ' . $e->getMessage()]);
        }

        return redirect()
            ->route('admin.settings.index', ['tab' => 'toles'])
            ->with('settlement_import_result', [
                'inserted' => $import->inserted,
                'updated'  => $import->updated,
                'skipped'  => $import->skipped,
                'errors'   => array_slice($import->errors, 0, 50),
            ]);
    }

    public function bulkDestroySettlements(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $lg = $this->lg();

        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        $deleted = Settlement::whereIn('id', $ids)
            ->whereHas('ward', fn($q) => $q->where('local_government_id', $lg->id))
            ->delete();

        return redirect()->route('admin.settings.index', ['tab' => 'toles'])
            ->with('success', "{$deleted} टोल/बस्ती मेटाइयो।");
    }

    public function destroySettlement(Settlement $settlement): RedirectResponse
    {
        $this->abortIfLocked();
        $this->authorizeWard($settlement->ward);
        $wardId = $settlement->ward_id;
        $settlement->delete();

        return redirect()->route('admin.settings.index', ['tab' => 'toles', 'ward_id' => $wardId])
            ->with('success', 'Settlement deleted.');
    }

    // ── AJAX: Local Governments by District ───────────────────────────────────

    public function apiLocalGovernments(Request $request): JsonResponse
    {
        $districtId = $request->integer('district_id');
        if (! $districtId) {
            return response()->json([]);
        }

        $lgs = LocalGovernment::where('district_id', $districtId)
            ->where('code', 'like', 'NLP%')
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_np', 'type', 'code']);

        return response()->json($lgs);
    }

    // ── Representatives ───────────────────────────────────────────────────────

    public function storeRepresentative(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $lg = $this->lg();

        $data = $request->validate([
            'name'            => 'required|string|max:150',
            'position'        => 'required|string|max:60',
            'ward_id'         => 'nullable|exists:wards,id',
            'birth_place'     => 'nullable|string|max:150',
            'date_of_birth'   => 'nullable|date',
            'ethnicity'       => 'nullable|string|max:100',
            'gender'          => 'nullable|in:male,female,other',
            'education'       => 'nullable|string|max:100',
            'address_ward_id' => 'nullable|exists:wards,id',
            'address_tole_id' => 'nullable|exists:settlements,id',
            'contact'         => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:100',
            'father_name'     => 'nullable|string|max:150',
            'mother_name'     => 'nullable|string|max:150',
            'spouse_name'     => 'nullable|string|max:150',
            'elected_party'   => 'nullable|string|max:150',
            'work_experience' => 'nullable|string|max:2000',
            'sort_order'      => 'nullable|integer|min:0',
        ]);

        $rep = LgRepresentative::create(array_merge($data, [
            'local_government_id' => $lg->id,
            'sort_order'          => $data['sort_order'] ?? LgRepresentative::where('local_government_id', $lg->id)->max('sort_order') + 1,
        ]));

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $request->validate(['photo' => 'image|mimes:jpeg,png,jpg|max:2048']);
            $path = $request->file('photo')->store('lg-representatives', 'public');
            $rep->update(['photo' => $path]);
        }

        return redirect()->route('admin.settings.index', ['tab' => 'representatives'])
            ->with('success', 'जनप्रतिनिधि विवरण सुरक्षित भयो।');
    }

    public function updateRepresentative(Request $request, LgRepresentative $representative): RedirectResponse
    {
        $this->abortIfLocked();
        $this->authorizeRepresentative($representative);

        $data = $request->validate([
            'name'            => 'required|string|max:150',
            'position'        => 'required|string|max:60',
            'ward_id'         => 'nullable|exists:wards,id',
            'birth_place'     => 'nullable|string|max:150',
            'date_of_birth'   => 'nullable|date',
            'ethnicity'       => 'nullable|string|max:100',
            'gender'          => 'nullable|in:male,female,other',
            'education'       => 'nullable|string|max:100',
            'address_ward_id' => 'nullable|exists:wards,id',
            'address_tole_id' => 'nullable|exists:settlements,id',
            'contact'         => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:100',
            'father_name'     => 'nullable|string|max:150',
            'mother_name'     => 'nullable|string|max:150',
            'spouse_name'     => 'nullable|string|max:150',
            'elected_party'   => 'nullable|string|max:150',
            'work_experience' => 'nullable|string|max:2000',
            'sort_order'      => 'nullable|integer|min:0',
        ]);

        $representative->update($data);

        if ($request->hasFile('photo')) {
            $request->validate(['photo' => 'image|mimes:jpeg,png,jpg|max:2048']);
            if ($representative->photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($representative->photo)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($representative->photo);
            }
            $path = $request->file('photo')->store('lg-representatives', 'public');
            $representative->update(['photo' => $path]);
        }

        return redirect()->route('admin.settings.index', ['tab' => 'representatives'])
            ->with('success', 'जनप्रतिनिधि विवरण अद्यावधिक भयो।');
    }

    public function destroyRepresentative(LgRepresentative $representative): RedirectResponse
    {
        $this->abortIfLocked();
        $this->authorizeRepresentative($representative);

        if ($representative->photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($representative->photo)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($representative->photo);
        }

        $representative->delete();

        return redirect()->route('admin.settings.index', ['tab' => 'representatives'])
            ->with('success', 'जनप्रतिनिधि रेकर्ड मेटाइयो।');
    }

    private function authorizeRepresentative(LgRepresentative $rep): void
    {
        if ($rep->local_government_id !== auth()->user()->local_government_id) {
            abort(403);
        }
    }

    // ── Employees ─────────────────────────────────────────────────────────────

    private function employeeFields(): array
    {
        return [
            'name'              => 'required|string|max:150',
            'position'          => 'nullable|string|max:150',
            'department'        => 'nullable|string|max:150',
            'entry_date'        => 'nullable|date',
            'date_of_birth'     => 'nullable|date',
            'birth_place'       => 'nullable|string|max:150',
            'ethnicity'         => 'nullable|string|max:100',
            'gender'            => 'nullable|in:male,female,other',
            'education'         => 'nullable|string|max:100',
            'permanent_address' => 'nullable|string|max:255',
            'contact'           => 'nullable|string|max:30',
            'email'             => 'nullable|email|max:100',
            'facebook'          => 'nullable|string|max:255',
            'instagram'         => 'nullable|string|max:255',
            'tiktok'            => 'nullable|string|max:255',
            'twitter_x'         => 'nullable|string|max:255',
            'father_name'       => 'nullable|string|max:150',
            'mother_name'       => 'nullable|string|max:150',
            'spouse_name'       => 'nullable|string|max:150',
            'work_experience'   => 'nullable|string|max:2000',
            'sort_order'        => 'nullable|integer|min:0',
        ];
    }

    public function storeEmployee(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $lg   = $this->lg();
        $data = $request->validate($this->employeeFields());

        $emp = LgEmployee::create(array_merge($data, [
            'local_government_id' => $lg->id,
            'sort_order'          => $data['sort_order'] ?? LgEmployee::where('local_government_id', $lg->id)->max('sort_order') + 1,
        ]));

        if ($request->hasFile('photo')) {
            $request->validate(['photo' => 'image|mimes:jpeg,png,jpg|max:2048']);
            $emp->update(['photo' => $request->file('photo')->store('lg-employees', 'public')]);
        }

        return redirect()->route('admin.settings.index', ['tab' => 'employees'])
            ->with('success', 'कर्मचारी विवरण सुरक्षित भयो।');
    }

    public function updateEmployee(Request $request, LgEmployee $employee): RedirectResponse
    {
        $this->abortIfLocked();
        $this->authorizeEmployee($employee);
        $data = $request->validate($this->employeeFields());

        $employee->update($data);

        if ($request->hasFile('photo')) {
            $request->validate(['photo' => 'image|mimes:jpeg,png,jpg|max:2048']);
            if ($employee->photo && Storage::disk('public')->exists($employee->photo)) {
                Storage::disk('public')->delete($employee->photo);
            }
            $employee->update(['photo' => $request->file('photo')->store('lg-employees', 'public')]);
        }

        return redirect()->route('admin.settings.index', ['tab' => 'employees'])
            ->with('success', 'कर्मचारी विवरण अद्यावधिक भयो।');
    }

    public function destroyEmployee(LgEmployee $employee): RedirectResponse
    {
        $this->abortIfLocked();
        $this->authorizeEmployee($employee);

        if ($employee->photo && Storage::disk('public')->exists($employee->photo)) {
            Storage::disk('public')->delete($employee->photo);
        }
        $employee->delete();

        return redirect()->route('admin.settings.index', ['tab' => 'employees'])
            ->with('success', 'कर्मचारी रेकर्ड मेटाइयो।');
    }

    private function authorizeEmployee(LgEmployee $emp): void
    {
        if ($emp->local_government_id !== auth()->user()->local_government_id) {
            abort(403);
        }
    }

    // ── Photo Gallery ──────────────────────────────────────────────────────────

    public function storePhoto(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $lg = $this->lg();

        $data = $request->validate([
            'photo_no'     => 'required|string|max:100',
            'category'     => 'nullable|string|max:100',
            'title'        => 'nullable|string|max:255',
            'subtitle'     => 'nullable|string|max:255',
            'gps_location' => 'nullable|string|max:150',
            'description'  => 'nullable|string|max:2000',
            'photo_date'   => 'nullable|date',
            'photo_credit' => 'nullable|string|max:150',
            'sort_order'   => 'nullable|integer|min:0',
        ]);

        LgPhoto::create(array_merge($data, [
            'local_government_id' => $lg->id,
            'sort_order'          => $data['sort_order'] ?? LgPhoto::where('local_government_id', $lg->id)->max('sort_order') + 1,
        ]));

        return redirect()->route('admin.settings.index', ['tab' => 'gallery'])
            ->with('success', 'फोटो ग्यालरी थपियो।');
    }

    public function updatePhoto(Request $request, LgPhoto $lgPhoto): RedirectResponse
    {
        $this->abortIfLocked();
        $this->authorizePhoto($lgPhoto);

        $data = $request->validate([
            'photo_no'     => 'required|string|max:100',
            'category'     => 'nullable|string|max:100',
            'title'        => 'nullable|string|max:255',
            'subtitle'     => 'nullable|string|max:255',
            'gps_location' => 'nullable|string|max:150',
            'description'  => 'nullable|string|max:2000',
            'photo_date'   => 'nullable|date',
            'photo_credit' => 'nullable|string|max:150',
            'sort_order'   => 'nullable|integer|min:0',
        ]);

        $lgPhoto->update($data);

        return redirect()->route('admin.settings.index', ['tab' => 'gallery'])
            ->with('success', 'फोटो विवरण अद्यावधिक भयो।');
    }

    public function destroyPhoto(LgPhoto $lgPhoto): RedirectResponse
    {
        $this->abortIfLocked();
        $this->authorizePhoto($lgPhoto);
        $lgPhoto->delete();

        return redirect()->route('admin.settings.index', ['tab' => 'gallery'])
            ->with('success', 'फोटो मेटाइयो।');
    }

    private function authorizePhoto(LgPhoto $p): void
    {
        if ($p->local_government_id !== auth()->user()->local_government_id) {
            abort(403);
        }
    }

    // ── Maintenance Mode ──────────────────────────────────────────────────────

    public function updateMaintenance(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $lg = $this->lg();

        $data = $request->validate([
            'is_active'    => 'boolean',
            'message'      => 'nullable|string|max:500',
            'message_sub'  => 'nullable|string|max:300',
            'countdown_at' => 'nullable|date',
            'bg_type'      => 'nullable|in:upload,url,gradient',
            'bg_url'       => 'nullable|string|max:1000',
        ]);

        $existing = $lg->maintenance_config ?? [];

        $lg->update([
            'maintenance_config' => array_merge($existing, [
                'is_active'    => (bool) ($data['is_active'] ?? false),
                'message'      => $data['message']      ?? null,
                'message_sub'  => $data['message_sub']  ?? null,
                'countdown_at' => $data['countdown_at'] ?? null,
                'bg_type'      => $data['bg_type']      ?? ($existing['bg_type'] ?? 'gradient'),
                'bg_url'       => $data['bg_url']       ?? null,
            ]),
        ]);

        $status = ($data['is_active'] ?? false) ? 'मर्मत अवस्था सक्रिय गरियो।' : 'मर्मत अवस्था निष्क्रिय गरियो।';
        return redirect()->route('admin.settings.index', ['tab' => 'maintenance'])
            ->with('success', $status);
    }

    public function uploadMaintenanceBg(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $request->validate([
            'bg_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $lg       = $this->lg();
        $existing = $lg->maintenance_config ?? [];
        $oldPath  = $existing['bg_image'] ?? null;

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('bg_image')->store('maintenance', 'public');
        $lg->update([
            'maintenance_config' => array_merge($existing, [
                'bg_image' => $path,
                'bg_type'  => 'upload',
            ]),
        ]);

        return redirect()->route('admin.settings.index', ['tab' => 'maintenance'])
            ->with('success', 'पृष्ठभूमि फोटो अपलोड भयो।');
    }

    public function deleteMaintenanceBg(): RedirectResponse
    {
        $this->abortIfLocked();
        $lg       = $this->lg();
        $existing = $lg->maintenance_config ?? [];
        $oldPath  = $existing['bg_image'] ?? null;

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        unset($existing['bg_image']);
        $existing['bg_type'] = 'gradient';
        $lg->update(['maintenance_config' => $existing]);

        return redirect()->route('admin.settings.index', ['tab' => 'maintenance'])
            ->with('success', 'पृष्ठभूमि फोटो हटाइयो।');
    }

    // ── Download Links ────────────────────────────────────────────────────────

    public function storeDownloadLink(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $lg = $this->lg();

        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string|max:2000',
            'url'          => 'required|string|max:1000',
            'thumbnail'    => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'created_date' => 'nullable|date',
            'sort_order'   => 'nullable|integer|min:0',
        ]);

        $link = LgDownloadLink::create(array_merge(
            \Illuminate\Support\Arr::except($data, ['thumbnail']),
            [
                'local_government_id' => $lg->id,
                'sort_order'          => $data['sort_order'] ?? LgDownloadLink::where('local_government_id', $lg->id)->max('sort_order') + 1,
            ]
        ));

        if ($request->hasFile('thumbnail')) {
            $link->update(['thumbnail' => $request->file('thumbnail')->store('lg-downloads', 'public')]);
        }

        return redirect()->route('admin.settings.index', ['tab' => 'downloads'])
            ->with('success', 'डाउनलोड लिंक थपियो।');
    }

    public function updateDownloadLink(Request $request, LgDownloadLink $downloadLink): RedirectResponse
    {
        $this->abortIfLocked();
        $this->authorizeDownloadLink($downloadLink);

        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string|max:2000',
            'url'          => 'required|string|max:1000',
            'thumbnail'    => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'created_date' => 'nullable|date',
            'sort_order'   => 'nullable|integer|min:0',
        ]);

        $downloadLink->update(\Illuminate\Support\Arr::except($data, ['thumbnail']));

        if ($request->hasFile('thumbnail')) {
            if ($downloadLink->thumbnail && Storage::disk('public')->exists($downloadLink->thumbnail)) {
                Storage::disk('public')->delete($downloadLink->thumbnail);
            }
            $downloadLink->update(['thumbnail' => $request->file('thumbnail')->store('lg-downloads', 'public')]);
        }

        return redirect()->route('admin.settings.index', ['tab' => 'downloads'])
            ->with('success', 'डाउनलोड लिंक अद्यावधिक भयो।');
    }

    public function destroyDownloadLink(LgDownloadLink $downloadLink): RedirectResponse
    {
        $this->abortIfLocked();
        $this->authorizeDownloadLink($downloadLink);

        if ($downloadLink->thumbnail && Storage::disk('public')->exists($downloadLink->thumbnail)) {
            Storage::disk('public')->delete($downloadLink->thumbnail);
        }
        $downloadLink->delete();

        return redirect()->route('admin.settings.index', ['tab' => 'downloads'])
            ->with('success', 'डाउनलोड लिंक मेटाइयो।');
    }

    private function authorizeDownloadLink(LgDownloadLink $link): void
    {
        if ($link->local_government_id !== auth()->user()->local_government_id) {
            abort(403);
        }
    }

    // ── Survey Period ─────────────────────────────────────────────────────────

    public function saveSurveyPeriod(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $lg = $this->lg();

        $data = $request->validate([
            'start_date'     => 'nullable|date_format:Y-m-d',
            'end_date'       => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'manual_freeze'  => 'boolean',
            'freeze_message' => 'nullable|string|max:300',
        ]);

        $lg->update(['survey_config' => array_merge($lg->survey_config ?? [], [
            'start_date'     => $data['start_date']     ?? null,
            'end_date'       => $data['end_date']       ?? null,
            'manual_freeze'  => (bool) ($data['manual_freeze'] ?? false),
            'freeze_message' => $data['freeze_message'] ?? null,
        ])]);

        return redirect()->route('admin.settings.index', ['tab' => 'survey_period'])
            ->with('success', 'सर्वेक्षण अवधि सेटिङ सुरक्षित गरियो।');
    }

    // ── Google Sheets Config ──────────────────────────────────────────────────

    public function saveGoogleSheets(Request $request): RedirectResponse
    {
        $this->abortIfLocked();
        $lg = $this->lg();

        $data = $request->validate([
            'household_sheet_id'   => 'nullable|string|max:200',
            'institution_sheet_id' => 'nullable|string|max:200',
            'business_sheet_id'    => 'nullable|string|max:200',
        ]);

        $lg->update(['google_sheets_config' => array_filter($data, fn($v) => $v !== null)]);

        return redirect()->route('admin.settings.index', ['tab' => 'google_sheets'])
            ->with('success', 'Google Sheets सेटिङ सुरक्षित भयो।');
    }

    public function testGoogleSheet(Request $request): \Illuminate\Http\JsonResponse
    {
        $data     = $request->validate(['sheet_id' => 'required|string|max:200']);
        $sheetId  = trim($data['sheet_id']);
        $url      = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=xlsx";

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->head($url);
            if ($response->successful() || $response->status() === 302) {
                return response()->json(['ok' => true, 'message' => 'Sheet पहुँचयोग्य छ।']);
            }
            return response()->json(['ok' => false, 'message' => 'Sheet पहुँच गर्न सकिएन (HTTP '.$response->status().'). Sheet "Anyone with link can view" मा सेट गर्नुभएको छ?']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'जडान भएन: ' . $e->getMessage()]);
        }
    }

    // ── Settings Export / Import ──────────────────────────────────────────────

    public function exportSettings(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $lg    = $this->lg();
        $wards = Ward::where('local_government_id', $lg->id)
            ->with('settlements')
            ->orderBy('ward_number')
            ->get();

        $cleanWelcome = collect($lg->welcome_config ?? [])->except(['map_image'])->map(function ($val, $key) {
            if (in_array($key, ['chairperson', 'vice_chairperson', 'cao']) && is_array($val)) {
                return collect($val)->except(['photo'])->toArray();
            }
            return $val;
        })->toArray();

        $payload = [
            'version'     => '1.1',
            'app'         => 'apprmp',
            'exported_at' => now()->toIso8601String(),
            'general' => [
                'name_np'          => $lg->name_np,
                'name_en'          => $lg->name_en,
                'type'             => $lg->type,
                'contact_email'    => $lg->contact_email,
                'contact_phone'    => $lg->contact_phone,
                'website'          => $lg->website,
                'established_date' => $lg->established_date?->format('Y-m-d'),
            ],
            'map_config'         => $lg->map_config         ?? [],
            'maintenance_config' => collect($lg->maintenance_config ?? [])->except(['bg_image'])->toArray(),
            'survey_config'      => $lg->survey_config      ?? [],
            'welcome_config'     => $cleanWelcome,
            'profile_content'    => $lg->profile_content    ?? [],
            'photo_dir'          => $lg->photo_dir          ?? [],
            'wards' => $wards->map(fn($w) => [
                'ward_number'      => $w->ward_number,
                'ward_chairperson' => $w->ward_chairperson,
                'contact_phone'    => $w->contact_phone,
                'settlements'      => $w->settlements->map(fn($s) => [
                    'name_np'         => $s->name_np,
                    'name_en'         => $s->name_en,
                    'settlement_type' => $s->settlement_type,
                ])->values()->toArray(),
            ])->toArray(),
            'survey_options' => SurveyOption::where('local_government_id', $lg->id)
                ->orderBy('category')->orderBy('sort_order')
                ->get(['category', 'value', 'label_ne', 'label_en', 'is_active', 'sort_order'])
                ->toArray(),
            'download_links' => LgDownloadLink::where('local_government_id', $lg->id)
                ->orderBy('sort_order')
                ->get(['title', 'description', 'url', 'created_date', 'sort_order'])
                ->toArray(),
            'representatives' => LgRepresentative::where('local_government_id', $lg->id)
                ->orderBy('sort_order')->get()
                ->map(fn($r) => collect($r->toArray())->except(['id', 'local_government_id', 'photo', 'created_at', 'updated_at'])->toArray())
                ->toArray(),
            'employees' => LgEmployee::where('local_government_id', $lg->id)
                ->orderBy('sort_order')->get()
                ->map(fn($e) => collect($e->toArray())->except(['id', 'local_government_id', 'photo', 'created_at', 'updated_at'])->toArray())
                ->toArray(),
            'google_sheets_config' => $lg->google_sheets_config ?? [],
            'gallery' => LgPhoto::where('local_government_id', $lg->id)
                ->orderBy('sort_order')->get()
                ->map(fn($p) => collect($p->toArray())->except(['id', 'local_government_id', 'photo', 'created_at', 'updated_at'])->toArray())
                ->toArray(),
        ];

        $filename = \Illuminate\Support\Str::slug($lg->name_en ?? 'settings') . '-settings-' . now()->format('Ymd-His') . '.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function importSettings(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $request->validate([
            'file'       => 'required|file|max:2048',
            'sections'   => 'required|array|min:1',
            'sections.*' => 'string|in:general,map_config,maintenance_config,survey_config,welcome_config,profile_content,photo_dir,wards,survey_options,download_links,representatives,employees,google_sheets_config,gallery',
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());
        $data    = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($data['version'])) {
            return back()->withErrors(['file' => 'अमान्य JSON फाइल। सही सेटिङ निर्यात फाइल अपलोड गर्नुहोस्।']);
        }

        $lg       = $this->lg();
        $sections = $request->input('sections', []);
        $applied  = [];

        if (in_array('general', $sections) && !empty($data['general'])) {
            $g = $data['general'];
            $update = array_filter([
                'name_np'          => $g['name_np']          ?? null,
                'name_en'          => $g['name_en']          ?? null,
                'type'             => $g['type']             ?? null,
                'contact_email'    => $g['contact_email']    ?? null,
                'contact_phone'    => $g['contact_phone']    ?? null,
                'website'          => $g['website']          ?? null,
                'established_date' => $g['established_date'] ?? null,
            ], fn($v) => $v !== null);
            if ($update) $lg->update($update);
            $applied[] = 'सामान्य विवरण';
        }

        foreach (['map_config', 'maintenance_config', 'survey_config', 'profile_content', 'photo_dir'] as $field) {
            if (in_array($field, $sections) && array_key_exists($field, $data)) {
                $lg->update([$field => $data[$field]]);
                $applied[] = $field;
            }
        }

        if (in_array('welcome_config', $sections) && array_key_exists('welcome_config', $data)) {
            $existing = $lg->welcome_config ?? [];
            $imported = $data['welcome_config'];
            // Preserve existing uploaded photo paths
            if (!empty($existing['map_image'])) $imported['map_image'] = $existing['map_image'];
            foreach (['chairperson', 'vice_chairperson', 'cao'] as $role) {
                if (!empty($existing[$role]['photo'])) {
                    $imported[$role] = array_merge($imported[$role] ?? [], ['photo' => $existing[$role]['photo']]);
                }
            }
            $lg->update(['welcome_config' => $imported]);
            $applied[] = 'स्वागत सन्देश';
        }

        if (in_array('wards', $sections) && !empty($data['wards'])) {
            foreach ($data['wards'] as $wData) {
                $ward = Ward::firstOrCreate(
                    ['local_government_id' => $lg->id, 'ward_number' => $wData['ward_number']],
                    ['ward_chairperson' => $wData['ward_chairperson'] ?? null, 'contact_phone' => $wData['contact_phone'] ?? null]
                );
                if (!$ward->wasRecentlyCreated) {
                    $ward->update([
                        'ward_chairperson' => $wData['ward_chairperson'] ?? $ward->ward_chairperson,
                        'contact_phone'    => $wData['contact_phone']    ?? $ward->contact_phone,
                    ]);
                }
                foreach ($wData['settlements'] ?? [] as $sData) {
                    \App\Models\Settlement::firstOrCreate(
                        ['ward_id' => $ward->id, 'name_np' => $sData['name_np']],
                        ['name_en' => $sData['name_en'] ?? $sData['name_np'], 'settlement_type' => $sData['settlement_type'] ?? 'tole']
                    );
                }
            }
            $actual = Ward::where('local_government_id', $lg->id)->count();
            if ($actual > $lg->ward_count) $lg->update(['ward_count' => $actual]);
            $applied[] = 'वडाहरू';
        }

        if (in_array('survey_options', $sections) && !empty($data['survey_options'])) {
            foreach ($data['survey_options'] as $opt) {
                SurveyOption::updateOrCreate(
                    ['local_government_id' => $lg->id, 'category' => $opt['category'], 'value' => $opt['value']],
                    ['label_ne' => $opt['label_ne'] ?? null, 'label_en' => $opt['label_en'] ?? null, 'is_active' => $opt['is_active'] ?? true, 'sort_order' => $opt['sort_order'] ?? 0]
                );
            }
            $applied[] = 'सर्वेक्षण विकल्पहरू';
        }

        if (in_array('download_links', $sections) && array_key_exists('download_links', $data)) {
            LgDownloadLink::where('local_government_id', $lg->id)->whereNull('thumbnail')->delete();
            foreach ($data['download_links'] as $i => $lData) {
                LgDownloadLink::create([
                    'local_government_id' => $lg->id,
                    'title'               => $lData['title'] ?? '',
                    'description'         => $lData['description'] ?? null,
                    'url'                 => $lData['url'] ?? '',
                    'created_date'        => $lData['created_date'] ?? null,
                    'sort_order'          => $lData['sort_order'] ?? $i,
                ]);
            }
            $applied[] = 'डाउनलोड लिंक';
        }

        if (in_array('representatives', $sections) && array_key_exists('representatives', $data)) {
            LgRepresentative::where('local_government_id', $lg->id)->whereNull('photo')->delete();
            foreach ($data['representatives'] as $i => $rData) {
                LgRepresentative::create(array_merge(
                    collect($rData)->except(['id', 'local_government_id', 'photo'])->toArray(),
                    ['local_government_id' => $lg->id, 'sort_order' => $rData['sort_order'] ?? $i]
                ));
            }
            $applied[] = 'जनप्रतिनिधि';
        }

        if (in_array('employees', $sections) && array_key_exists('employees', $data)) {
            LgEmployee::where('local_government_id', $lg->id)->whereNull('photo')->delete();
            foreach ($data['employees'] as $i => $eData) {
                LgEmployee::create(array_merge(
                    collect($eData)->except(['id', 'local_government_id', 'photo'])->toArray(),
                    ['local_government_id' => $lg->id, 'sort_order' => $eData['sort_order'] ?? $i]
                ));
            }
            $applied[] = 'कर्मचारी';
        }

        if (in_array('google_sheets_config', $sections) && array_key_exists('google_sheets_config', $data)) {
            $lg->update(['google_sheets_config' => $data['google_sheets_config']]);
            $applied[] = 'Google Sheets कन्फिग';
        }

        if (in_array('gallery', $sections) && array_key_exists('gallery', $data)) {
            // Only delete photo-less records so existing uploaded photos are preserved
            LgPhoto::where('local_government_id', $lg->id)->whereNull('photo')->delete();
            foreach ($data['gallery'] as $i => $pData) {
                LgPhoto::create(array_merge(
                    collect($pData)->except(['id', 'local_government_id', 'photo'])->toArray(),
                    ['local_government_id' => $lg->id, 'sort_order' => $pData['sort_order'] ?? $i]
                ));
            }
            $applied[] = 'फोटो ग्यालरी (मेटाडेटा)';
        }

        $msg = count($applied)
            ? 'सेटिङ सफलतापूर्वक आयात गरियो: ' . implode(', ', $applied) . '।'
            : 'कुनै परिवर्तन लागू गरिएन।';

        return redirect()->route('admin.settings.index', ['tab' => 'sync'])->with('success', $msg);
    }

    // ── Authorization helper ──────────────────────────────────────────────────

    private function authorizeWard(Ward $ward): void
    {
        if ($ward->local_government_id !== auth()->user()->local_government_id) {
            abort(403);
        }
    }
}
