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
            'ward.localGovernment', 'settlement',
            'infrastructure', 'staff', 'services', 'education', 'healthDetail',
        ]);

        $lg   = $institution->ward?->localGovernment ?? LocalGovernment::getCached(auth()->user()->local_government_id);
        $opts = $this->surveyLabels($lg?->id);
        $html = view('pdf.institution', compact('institution', 'lg', 'opts'))->render();

        return $this->streamPdf($html, 'institution-' . $institution->unique_code . '.pdf');
    }

    public function business(MemberBusiness $business)
    {
        $this->authorize('view', $business);

        $business->load(['ward.localGovernment', 'settlement', 'member', 'household']);
        $lg   = $business->ward?->localGovernment ?? LocalGovernment::find($user->local_government_id);
        $opts = $this->surveyLabels($lg?->id);
        $html = view('pdf.business', compact('business', 'lg', 'opts'))->render();

        return $this->streamPdf($html, 'business-' . $business->unique_code . '.pdf');
    }

    /** Load all survey option labels grouped by category → lowercase_value → label_ne */
    private function surveyLabels(?int $lgId): \Illuminate\Support\Collection
    {
        $opts = SurveyOption::select('category', 'value', 'label_ne')
            ->where(fn($q) => $q->whereNull('local_government_id')->orWhere('local_government_id', $lgId))
            ->get()
            ->groupBy('category')
            ->map(fn($g) => $g->mapWithKeys(fn($o) => [strtolower($o->value) => $o->label_ne]));

        // Kobo raw value fallbacks for old imported data
        $koboFallback = [
            'occupation_type' => [
                'agricultureandanimalhusbandry' => 'कृषि तथा पशुपालन',
                'business' => 'व्यापार/व्यवसाय', 'governmentemployee' => 'सरकारी जागिर',
                'employmentinprivatesector' => 'निजी क्षेत्र जागिर', 'foreignemployment' => 'वैदेशिक रोजगार',
                'dailywage' => 'दैनिक मजदुरी', 'selfemployment' => 'स्वरोजगार',
                'supportfamilyjobs' => 'घरेलु काम', 'student' => 'विद्यार्थी',
                'unemployed' => 'बेरोजगार', 'other' => 'अन्य',
            ],
            'relation_to_head' => [
                'headofhouse_self' => 'घरमुली आफै', 'husband' => 'श्रीमान', 'wife' => 'श्रीमती',
                'daughterinlaw' => 'बुहारी', 'soninlaw' => 'ज्वाइँ',
                'grandson' => 'नाति', 'granddaughter' => 'नातिनी',
            ],
            'marital_status' => [
                'singlemarried' => 'विवाहित', 'multimarried' => 'बहु विवाह',
                'remarried' => 'पुनः विवाह', 'widow' => 'विधुर/विधवा', 'seperated' => 'अलग',
            ],
            'education_level' => [
                'illitrate' => 'निरक्षर (पढ्न लेख्न नसक्ने)', 'literate' => 'साक्षर (पढ्न लेख्न सक्ने)',
                'basiceducation1_5' => 'आधारभूत (कक्षा १-५)', 'basiceducation6_8' => 'आधारभूत (कक्षा ६-८)',
                'secondaryschool' => 'माध्यमिक (कक्षा ९-१०)', 'slc' => 'SLC/SEE',
                'intermediate' => '+२/प्रवीणता', 'mphil' => 'एमफिल', 'phd' => 'पिएचडी', 'other' => 'अन्य',
            ],
            'mother_tongue' => [
                'bantawa' => 'बान्तवा (राई)', 'nepali' => 'नेपाली', 'limbu' => 'लिम्बु',
                'maithili' => 'मैथली', 'bhojpuri' => 'भोजपुरी', 'hindi' => 'हिन्दी',
                'tamang' => 'तामांग', 'nepalbhasa' => 'नेपाल भाषा', 'newar' => 'नेपाल भाषा (नेवारी)',
                'magar' => 'मगर', 'gurung' => 'गुरुङ', 'rai' => 'राई', 'sherpa' => 'शेर्पा', 'other' => 'अन्य',
            ],
            'ethnicity' => [
                'brahmin' => 'ब्राह्मण', 'chettri' => 'क्षेत्री', 'rai' => 'राई', 'limbu' => 'लिम्बु',
                'magar' => 'मगर', 'tamang' => 'तामांग', 'gurung' => 'गुरुङ', 'newar' => 'नेवार',
                'thakuri' => 'ठकुरी', 'tharu' => 'थारु', 'kaami' => 'कामी/बिश्वकर्मा',
                'damai' => 'दमाई', 'sarki' => 'सार्की', 'yadav' => 'यादव',
                'musalmaan' => 'मुसलमान', 'sherpa' => 'शेर्पा', 'other' => 'अन्य',
            ],
            'religion' => [
                'hindu' => 'हिन्दू', 'buddhist' => 'बौद्ध', 'kirant' => 'किराँत',
                'christian' => 'क्रिस्चियन', 'muslim' => 'मुस्लिम',
                'prakriti' => 'प्रकृति', 'athiest' => 'नास्तिक', 'bone' => 'बोन', 'other' => 'अन्य',
            ],
            'water_source' => [
                'tap_house' => 'धाराको पानी (घरभित्र)', 'tap_public' => 'धाराको पानी (बाहिर)',
                'tubewell' => 'ट्युबवेल/हाते धारा', 'covered_well' => 'ढाकिएको कुवा',
                'open_well' => 'खुला कुवा', 'jaruwa' => 'मूलको पानी/जरुवा',
                'bottledwater' => 'जार/बोतलको पानी', 'river' => 'नदी/खोला',
            ],
            'toilet_type' => [
                'standard_septictank' => 'फ्लस (सेप्टिक ट्याङ्क)',
                'standard_publicdrainage' => 'फ्लस (सार्वजनिक ढल)',
                'normal' => 'साधारण शौचालय', 'no_toilet' => 'शौचालय छैन',
            ],
            'electricity_source' => [
                'natonalelectricgrid' => 'राष्ट्रिय ग्रिड', 'microhydro' => 'लघु जलविद्युत',
                'kerosene' => 'मट्टितेल', 'solarenergy' => 'सोलार',
            ],
            'cooking_fuel' => ['lp_gas' => 'ग्याँस (LPG)', 'guitha' => 'गुइँठा'],
            'roof_type' => [
                'straw' => 'फुस/खर', 'cgi_sheet' => 'जस्ता', 'tiles_stone' => 'टायल/ढुंगा',
                'wood' => 'काठ', 'mud' => 'माटो',
            ],
            'wall_type' => [
                'mud_and_stone' => 'माटो र ढुंगा/इट्टा', 'cement_and_stone' => 'सिमेन्ट र ढुंगा/इट्टा',
                'center_bera' => 'सेन्टर बेरा',
            ],
            'primary_income_source' => ['selfemployment' => 'स्वरोजगार', 'other_income' => 'अन्य'],
            'income_source'         => ['selfemployment' => 'स्वरोजगार', 'other_income' => 'अन्य'],
            'disability_type' => [
                'blind' => 'दृष्टिसम्बन्धी', 'deaf' => 'श्रवणसम्बन्धी',
                'speech' => 'स्वर बोलाई', 'multiple_disability' => 'बहुअपाङ्गता',
            ],
        ];

        foreach ($koboFallback as $cat => $map) {
            $existing = $opts->get($cat) ?? collect();
            foreach ($map as $k => $label) {
                if (!$existing->has($k)) $existing->put($k, $label);
            }
            $opts->put($cat, $existing);
        }

        return $opts;
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
