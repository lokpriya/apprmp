<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\Institution;
use App\Models\MemberBusiness;
use App\Models\LocalGovernment;
use App\Models\SurveyOption;
use Mpdf\Mpdf;

class PdfController extends Controller
{
    public function household(Household $household)
    {
        $this->authorize('view', $household);

        $household->load([
            'ward.localGovernment', 'settlement', 'enumerator',
            'housing', 'utilities', 'economic', 'agriculture', 'disaster',
            'members', 'livestock',
        ]);

        $lg   = $household->ward?->localGovernment ?? LocalGovernment::getCached(auth()->user()->local_government_id);
        $opts = $this->surveyLabels($lg?->id);
        $html = view('pdf.household', compact('household', 'lg', 'opts'))->render();

        return $this->streamPdf($html, 'household-' . $household->unique_code . '.pdf');
    }

    public function institution(Institution $institution)
    {
        $this->authorize('view', $institution);

        $institution->load([
            'ward.localGovernment', 'settlement', 'institutionType',
            'infrastructure', 'staff', 'services', 'education', 'healthDetail',
        ]);

        $lg   = $institution->ward?->localGovernment ?? LocalGovernment::getCached(auth()->user()->local_government_id);
        $opts = $this->surveyLabels($lg?->id);
        $html = view('pdf.institution', compact('institution', 'lg', 'opts'))->render();

        return $this->streamPdf($html, 'institution-' . $institution->unique_code . '.pdf');
    }

    public function business(MemberBusiness $business)
    {
        $user = auth()->user();
        if (! $user->isAdmin()) {
            abort_unless(in_array($business->ward_id, $user->getAssignedWardIds()), 403);
        }

        $business->load(['ward.localGovernment', 'settlement', 'member', 'household']);
        $lg   = $business->ward?->localGovernment ?? LocalGovernment::find($user->local_government_id);
        $opts = $this->surveyLabels($lg?->id);
        $html = view('pdf.business', compact('business', 'lg', 'opts'))->render();

        return $this->streamPdf($html, 'business-' . $business->unique_code . '.pdf');
    }

    /** Load all survey option labels grouped by category → value → label_ne */
    private function surveyLabels(?int $lgId): \Illuminate\Support\Collection
    {
        return SurveyOption::select('category', 'value', 'label_ne')
            ->where(fn($q) => $q->whereNull('local_government_id')->orWhere('local_government_id', $lgId))
            ->get()
            ->groupBy('category')
            ->map(fn($g) => $g->pluck('label_ne', 'value'));
    }

    private function streamPdf(string $html, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'margin_left'   => 15,
            'margin_right'  => 15,
            'default_font'  => 'freesans',
        ]);

        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont   = true;
        $mpdf->WriteHTML($html);

        return response()->streamDownload(
            fn () => print($mpdf->Output('', 'S')),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
