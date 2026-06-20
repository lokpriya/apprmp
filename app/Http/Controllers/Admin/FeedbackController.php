<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicFeedback;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('feedback.view'), 403);

        $status   = $request->input('status', 'all');
        $category = $request->input('category');
        $ward     = $request->input('ward');
        $search   = $request->input('search');
        $sort     = in_array($request->input('sort'), ['created_at', 'category', 'status', 'ward_number']) ? $request->input('sort') : 'created_at';
        $dir      = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        $query = PublicFeedback::with('reviewer:id,name')
            ->orderBy($sort, $dir);

        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($category) {
            $query->where('category', $category);
        }
        if ($ward) {
            $query->where('ward_number', $ward);
        }
        if ($search) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%")
            );
        }

        $items = $query->paginate(20)->withQueryString();

        $user = auth()->user();

        return Inertia::render('Admin/Feedback/Index', [
            'title'      => 'नागरिक सुझाव',
            'items'      => $items,
            'filters'    => compact('status', 'category', 'ward', 'search', 'sort', 'dir'),
            'categories' => PublicFeedback::categories(),
            'counts'     => [
                'all'      => PublicFeedback::count(),
                'new'      => PublicFeedback::where('status', 'new')->count(),
                'reviewed' => PublicFeedback::where('status', 'reviewed')->count(),
                'resolved' => PublicFeedback::where('status', 'resolved')->count(),
            ],
            'canManage' => $user->hasPermission('feedback.manage'),
            'canDelete' => $user->hasPermission('feedback.delete'),
        ]);
    }

    public function update(Request $request, PublicFeedback $feedback)
    {
        abort_unless(auth()->user()->hasPermission('feedback.manage'), 403);

        $data = $request->validate([
            'status'     => 'required|in:new,reviewed,resolved',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $feedback->update([
            'status'      => $data['status'],
            'admin_note'  => $data['admin_note'] ?? $feedback->admin_note,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        \Illuminate\Support\Facades\Cache::forget('public_feedback_new_count');

        return back()->with('success', 'सुझावको स्थिति अद्यावधिक भयो।');
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('feedback.view'), 403);

        $status   = $request->input('status', 'all');
        $category = $request->input('category');
        $ward     = $request->input('ward');
        $search   = $request->input('search');
        $sort     = in_array($request->input('sort'), ['created_at', 'category', 'status', 'ward_number']) ? $request->input('sort') : 'created_at';
        $dir      = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        $query = PublicFeedback::with('reviewer:id,name')->orderBy($sort, $dir);
        if ($status !== 'all') $query->where('status', $status);
        if ($category)         $query->where('category', $category);
        if ($ward)             $query->where('ward_number', $ward);
        if ($search)           $query->where(fn ($q) => $q
            ->where('name', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%")
            ->orWhere('message', 'like', "%{$search}%")
        );

        $rows = $query->get();
        $categories = PublicFeedback::categories();

        $csv = "ID,नाम,फोन,इमेल,वडा,विषय,सुझाव,स्थिति,समीक्षक,तारिख\n";
        foreach ($rows as $r) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', [
                $r->id,
                $r->name,
                $r->phone,
                $r->email,
                $r->ward_number,
                $categories[$r->category] ?? $r->category,
                $r->message,
                match($r->status) { 'new' => 'नयाँ', 'reviewed' => 'समीक्षित', 'resolved' => 'समाधान', default => $r->status },
                $r->reviewer?->name,
                $r->created_at?->format('Y-m-d H:i'),
            ])) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="feedback_' . now()->format('Ymd_His') . '.csv"',
        ]);
    }

    public function destroy(PublicFeedback $feedback)
    {
        abort_unless(auth()->user()->hasPermission('feedback.delete'), 403);

        $feedback->delete();
        \Illuminate\Support\Facades\Cache::forget('public_feedback_new_count');
        return back()->with('success', 'सुझाव मेटाइयो।');
    }
}
