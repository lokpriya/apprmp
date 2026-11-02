<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    private const TYPE_LABELS = [
        'App\\Models\\Household'      => 'घरधुरी',
        'App\\Models\\Institution'    => 'संस्था',
        'App\\Models\\MemberBusiness' => 'व्यवसाय',
        'App\\Models\\Ward'           => 'वडा',
        'App\\Models\\User'           => 'प्रयोगकर्ता',
    ];

    private const EVENT_LABELS = [
        'published_edit'      => 'प्रकाशित सम्पादन',
        'created'             => 'सिर्जना',
        'updated'             => 'अद्यावधिक',
        'deleted'             => 'मेटाउने',
        'status_change'       => 'स्थिति परिवर्तन',
        'impersonation_start' => 'प्रयोगकर्ता स्विच (सुरु)',
        'impersonation_stop'  => 'प्रयोगकर्ता स्विच (अन्त्य)',
        'google_sync'         => 'Google Sheets सिंक',
        'member_search'       => 'सदस्य खोज',
        'module_config'       => 'मोड्युल सेटिङ',
    ];

    public function index(Request $request)
    {
        $lgId    = auth()->user()->local_government_id;
        $wardIds = Ward::where('local_government_id', $lgId)->pluck('id')->toArray();

        // Only users belonging to this LG
        $lgUserIds = User::where('local_government_id', $lgId)->pluck('id')->toArray();

        $query = AuditLog::with('user:id,name,role')
            ->whereIn('user_id', $lgUserIds);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }
        if ($request->filled('record_type')) {
            $query->where('auditable_type', $request->record_type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest('created_at')
            ->paginate(25)
            ->through(fn($log) => [
                'id'             => $log->id,
                'user'           => $log->user ? [
                    'id'   => $log->user->id,
                    'name' => $log->user->name,
                    'role' => $log->user->role,
                ] : null,
                'event_type'     => $log->event_type,
                'event_label'    => self::EVENT_LABELS[$log->event_type] ?? $log->event_type,
                'auditable_type' => $log->auditable_type,
                'type_label'     => self::TYPE_LABELS[$log->auditable_type] ?? class_basename($log->auditable_type ?? ''),
                'auditable_id'   => $log->auditable_id,
                'unique_code'    => $log->new_values['unique_code'] ?? $log->old_values['unique_code'] ?? null,
                'old_values'     => $log->old_values,
                'new_values'     => $log->new_values,
                'ip_address'     => $log->ip_address,
                'created_at'     => $log->created_at?->format('Y-m-d H:i:s'),
                'created_at_diff'=> $log->created_at?->diffForHumans(),
            ]);

        // Filter options
        $users = User::where('local_government_id', $lgId)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $eventTypes = AuditLog::whereIn('user_id', $lgUserIds)
            ->distinct()
            ->pluck('event_type')
            ->map(fn($e) => ['value' => $e, 'label' => self::EVENT_LABELS[$e] ?? $e]);

        return Inertia::render('Admin/AuditLog', [
            'title'      => 'अडिट लग',
            'logs'       => $logs,
            'filters'    => $request->only(['user_id', 'event_type', 'record_type', 'date_from', 'date_to']),
            'users'      => $users,
            'eventTypes' => $eventTypes,
            'typeLabels' => self::TYPE_LABELS,
        ]);
    }
}
