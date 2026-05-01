<?php

namespace App\Http\Controllers;

use App\Models\FieldVerification;
use App\Models\Household;
use App\Models\Institution;
use App\Models\MemberBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FieldVerificationController extends Controller
{
    public function saveHousehold(Request $request, Household $household): RedirectResponse
    {
        $this->authorizeVerifier($household->ward_id);
        $this->save($request, $household);
        return back()->with('success', 'क्षेत्र प्रमाणीकरण सुरक्षित भयो।');
    }

    public function saveInstitution(Request $request, Institution $institution): RedirectResponse
    {
        $this->authorizeVerifier($institution->ward_id);
        $this->save($request, $institution);
        return back()->with('success', 'क्षेत्र प्रमाणीकरण सुरक्षित भयो।');
    }

    public function saveBusiness(Request $request, MemberBusiness $business): RedirectResponse
    {
        $this->authorizeVerifier($business->ward_id);
        $this->save($request, $business);
        return back()->with('success', 'क्षेत्र प्रमाणीकरण सुरक्षित भयो।');
    }

    private function save(Request $request, Model $record): void
    {
        $data = $request->validate([
            'visited_at'  => 'required|date',
            'is_verified' => 'required|boolean',
            'notes'       => 'nullable|string|max:2000',
        ]);

        $data['verifier_id'] = auth()->id();

        FieldVerification::updateOrCreate(
            ['verifiable_type' => get_class($record), 'verifiable_id' => $record->id],
            $data
        );
    }

    private function authorizeVerifier(int $wardId): void
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isReviewer(), 403);

        if (!$user->isAdmin()) {
            $wardIds = $user->assignedWards()->pluck('wards.id')->toArray();
            abort_unless(in_array($wardId, $wardIds), 403);
        }
    }
}
