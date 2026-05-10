<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LocalGovernment;
use App\Models\SurveyOption;
use App\Models\Ward;
use App\Services\GoogleSheetsSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoogleSyncController extends Controller
{
    public function __construct(private GoogleSheetsSyncService $sync) {}

    public function index(): Response
    {
        $lg    = LocalGovernment::getFirstCached();
        $wards = Ward::where('local_government_id', $lg?->id)
            ->orderBy('ward_number')
            ->get(['id', 'ward_number']);

        $config = $lg?->google_sheets_config ?? [];
        $hasHouseholdSheet    = !empty($config['household_sheet_id']);
        $hasInstitutionSheet  = !empty($config['institution_sheet_id']);

        return Inertia::render('Admin/GoogleSync', [
            'wards'               => $wards,
            'hasHouseholdSheet'   => $hasHouseholdSheet,
            'hasInstitutionSheet' => $hasInstitutionSheet,
        ]);
    }

    public function manual(): \Illuminate\View\View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $lg      = LocalGovernment::getFirstCached();
        $options = SurveyOption::allGrouped($lg?->id)->all();
        return view('admin.google-sync-manual', compact('options'));
    }

    public function preview(Request $request): JsonResponse
    {
        set_time_limit(180);
        $request->validate([
            'type'    => ['required', 'in:households,institutions'],
            'ward_id' => ['required', 'integer', 'exists:wards,id'],
        ]);

        $lg       = LocalGovernment::getFirstCached();
        $config   = $lg?->google_sheets_config ?? [];
        $sheetId  = $request->type === 'households'
            ? ($config['household_sheet_id'] ?? null)
            : ($config['institution_sheet_id'] ?? null);

        if (!$sheetId) {
            return response()->json([
                'error' => 'Sheet ID कन्फिगर गरिएको छैन। Settings → Google Sheets मा थप्नुहोस्।',
            ], 422);
        }

        try {
            $diff = $request->type === 'households'
                ? $this->sync->previewHouseholds($sheetId, (int)$request->ward_id, $lg->id)
                : $this->sync->previewInstitutions($sheetId, (int)$request->ward_id, $lg->id);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // Strip internal _related payload before sending to frontend
        $stripInternal = fn(array $r) => array_diff_key($r, ['_related' => 1]);

        $previewRows = array_slice(
            array_merge($diff['new'], $diff['changed'], $diff['locked']),
            0, 200
        );

        // Also strip _related from error rows (their nested 'row' key may carry it)
        $cleanErrors = array_map(function (array $e) use ($stripInternal) {
            if (isset($e['row'])) $e['row'] = $stripInternal($e['row']);
            return $e;
        }, array_slice($diff['errors'], 0, 50));

        return response()->json([
            'counts' => [
                'new'      => count($diff['new']),
                'changed'  => count($diff['changed']),
                'locked'   => count($diff['locked']),
                'upToDate' => count($diff['upToDate']),
                'errors'   => count($diff['errors']),
            ],
            'rows'   => array_map($stripInternal, $previewRows),
            'errors' => $cleanErrors,
            'debug'  => $diff['debug'] ?? null,
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        set_time_limit(180);
        $request->validate([
            'type'         => ['required', 'in:households,institutions'],
            'ward_id'      => ['required', 'integer', 'exists:wards,id'],
            'force_locked' => ['boolean'],
        ]);

        $lg       = LocalGovernment::getFirstCached();
        $config   = $lg?->google_sheets_config ?? [];
        $sheetId  = $request->type === 'households'
            ? ($config['household_sheet_id'] ?? null)
            : ($config['institution_sheet_id'] ?? null);

        if (!$sheetId) {
            return response()->json(['error' => 'Sheet ID कन्फिगर गरिएको छैन।'], 422);
        }

        $forceLocked = (bool)($request->force_locked ?? false);

        try {
            $result = $request->type === 'households'
                ? $this->sync->applyHouseholds($sheetId, (int)$request->ward_id, $lg->id, $forceLocked)
                : $this->sync->applyInstitutions($sheetId, (int)$request->ward_id, $lg->id, $forceLocked);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
