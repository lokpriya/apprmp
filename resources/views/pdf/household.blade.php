@extends('pdf._layout')

@section('doc-title', 'घरधुरी विवरण')
@section('doc-meta')
  कोड: {{ $household->unique_code }} &nbsp;|&nbsp;
  वडा नं: {{ $household->ward->ward_number ?? '—' }} &nbsp;|&nbsp;
  अवस्था: {{ ['draft'=>'मस्यौदा','submitted'=>'पेश गरिएको','ward_approved'=>'वडाद्वारा स्वीकृत','published'=>'प्रकाशित','rejected'=>'अस्वीकृत'][$household->status] ?? $household->status }}
@endsection

@section('content')
@php
  $sd  = $household->survey_data ?? [];
  $soc = $sd['social']          ?? null;
  $hlt = $sd['health']          ?? null;
  $wb  = $sd['wellbeing']       ?? null;
  $dec = $sd['decisions']       ?? null;
  $occ = $sd['occupation']      ?? null;
  $cp  = $sd['cropProduction']  ?? null;
  $lvd = $sd['livestockDetail'] ?? null;
  $bfs = $sd['beeFishSilk']     ?? null;

  // Resolve single survey-option value → Nepali label
  $ol  = fn($cat, $val) => ($val && $opts->get($cat)?->get($val)) ? $opts->get($cat)->get($val) : ($val ?? '—');

  // Resolve array of survey-option values → comma-joined Nepali labels
  $ols = function($cat, $arr) use ($opts) {
    if (!is_array($arr) || !count($arr)) return '—';
    return implode(', ', array_map(fn($v) => ($opts->get($cat)?->get($v) ?? $v), $arr));
  };

  // yes/no/bool → Nepali
  $yn = fn($v) => ($v === 'yes' || $v === true  || $v === 1) ? 'छ'
               : (($v === 'no'  || $v === false || $v === 0) ? 'छैन' : '—');

  // Null-safe display
  $v = fn($x) => ($x !== null && $x !== '' && $x !== []) ? $x : '—';
@endphp

{{-- ── 1. आधारभूत जानकारी ──────────────────────────────────────────── --}}
<div class="section">
  <div class="section-title">१. आधारभूत जानकारी</div>
  <table class="kv">
    <tr>
      <td class="lbl">उत्तरदाताको नाम</td><td class="val">{{ $household->respondent_name ?? '—' }}</td>
      <td class="lbl">सम्पर्क नम्बर</td><td class="val">{{ $household->respondent_contact ?? '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">वडा नं</td><td class="val">{{ $household->ward->ward_number ?? '—' }}</td>
      <td class="lbl">बस्ती</td><td class="val">{{ $household->settlement->name_np ?? '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">गणक</td><td class="val">{{ $household->enumerator->name ?? '—' }}</td>
      <td class="lbl">सर्वेक्षण मिति</td><td class="val">{{ $household->survey_date?->format('Y-m-d') ?? '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">GPS (Lat, Lng)</td>
      <td class="val" colspan="3">
        @if($household->gps_lat && $household->gps_lng)
          {{ $household->gps_lat }}, {{ $household->gps_lng }}
          @if($household->gps_accuracy) &nbsp;(सटीकता: {{ $household->gps_accuracy }}m) @endif
          @if($household->gps_altitude) &nbsp;(उचाइ: {{ $household->gps_altitude }}m) @endif
        @else —
        @endif
      </td>
    </tr>
    @if($household->remarks)
    <tr><td class="lbl">कैफियत</td><td class="val" colspan="3">{{ $household->remarks }}</td></tr>
    @endif
  </table>
</div>

{{-- ── 2. आवास विवरण ──────────────────────────────────────────────────── --}}
@if($household->housing)
@php $h = $household->housing; @endphp
<div class="section">
  <div class="section-title">२. आवास विवरण</div>
  <table class="kv">
    <tr>
      <td class="lbl">घरको प्रकार</td><td class="val">{{ $ol('house_type', $h->house_type) }}</td>
      <td class="lbl">छानाको प्रकार</td><td class="val">{{ $ol('roof_type', $h->roof_type) }}</td>
    </tr>
    <tr>
      <td class="lbl">भित्ताको प्रकार</td><td class="val">{{ $ol('wall_type', $h->wall_type) }}</td>
      <td class="lbl">जग प्रकार</td><td class="val">{{ $ol('foundation_type', $h->foundation_type) }}</td>
    </tr>
    <tr>
      <td class="lbl">स्वामित्व</td><td class="val">{{ $ol('ownership_type', $h->ownership_type) }}</td>
      <td class="lbl">घरको अवस्था</td><td class="val">{{ $ol('house_condition', $h->house_condition) }}</td>
    </tr>
    <tr>
      <td class="lbl">कोठा संख्या</td><td class="val">{{ $v($h->total_rooms) }}</td>
      <td class="lbl">तल्ला संख्या</td><td class="val">{{ $v($h->total_floors) }}</td>
    </tr>
    <tr>
      <td class="lbl">जग्गाको प्रकार</td><td class="val">{{ $ol('house_land_type', $h->house_land_type) }}</td>
      <td class="lbl">घरको उद्देश्य</td><td class="val">{{ $ol('house_purpose', $h->house_purpose) }}</td>
    </tr>
    <tr>
      <td class="lbl">निर्माण वर्ष</td><td class="val">{{ $v($h->construction_year) }}</td>
      <td class="lbl">जोखिम क्षेत्र</td><td class="val">{{ $yn($h->is_at_risk) }}</td>
    </tr>
    @if($h->risk_types && count($h->risk_types))
    <tr>
      <td class="lbl">जोखिमका किसिम</td>
      <td class="val" colspan="3">{{ $ols('disaster_type', $h->risk_types) }}</td>
    </tr>
    @endif
  </table>
</div>
@endif

{{-- ── 3. परिवार तथा सामाजिक ──────────────────────────────────────────── --}}
@if($soc)
<div class="section">
  <div class="section-title">३. परिवार तथा सामाजिक</div>
  <table class="kv">
    <tr>
      <td class="lbl">घरमुलीको नाम</td><td class="val">{{ $v($soc['family_head'] ?? null) }}</td>
      <td class="lbl">घरमुलीको सम्पर्क</td><td class="val">{{ $v($soc['contact_no_fam_head'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">पुरुष संख्या</td><td class="val">{{ $v($soc['male_num'] ?? null) }}</td>
      <td class="lbl">महिला संख्या</td><td class="val">{{ $v($soc['female_num'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">अन्य संख्या</td><td class="val">{{ $v($soc['other_num'] ?? null) }}</td>
      <td class="lbl">परिवार प्रकार</td><td class="val">{{ $ol('family_type', $soc['family_type'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">जातजाति</td><td class="val">{{ $ol('ethnicity', $soc['ethnicity'] ?? null) }}</td>
      <td class="lbl">धर्म</td><td class="val">{{ $ol('religion', $soc['religion'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">मातृभाषा</td><td class="val">{{ $ol('mother_tongue', $soc['mother_tongue'] ?? null) }}</td>
      <td class="lbl">सञ्चार भाषा</td><td class="val">{{ $ol('mother_tongue', $soc['com_language'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">मुख्य पेशा</td><td class="val">{{ $ol('occupation_type', $soc['major_family_occupation'] ?? null) }}</td>
      <td class="lbl">बसोबास प्रकार</td><td class="val">{{ $ol('settlement_type', $soc['settlement_type'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">जमिन छ?</td><td class="val">{{ $yn($soc['have_land'] ?? null) }}</td>
      @if(($soc['have_land'] ?? null) === 'yes')
      <td class="lbl">जग्गाको एकाइ</td><td class="val">{{ $ol('land_unit', $soc['land_unit'] ?? null) }}</td>
      @else
      <td class="lbl"></td><td class="val"></td>
      @endif
    </tr>
    @if(($soc['have_land'] ?? null) === 'yes')
    <tr>
      <td class="lbl">खेत</td><td class="val">{{ $v($soc['land_khet'] ?? null) }}</td>
      <td class="lbl">बारी</td><td class="val">{{ $v($soc['land_bari'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">पाखो</td><td class="val">{{ $v($soc['land_pakho'] ?? null) }}</td>
      <td class="lbl"></td><td class="val"></td>
    </tr>
    @endif
    <tr>
      <td class="lbl">ऋण लिएको?</td><td class="val">{{ $yn($soc['bankloan'] ?? null) }}</td>
      <td class="lbl">स्वास्थ्य बिमा</td><td class="val">{{ $yn($soc['health_insurance'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">आप्रवासन</td><td class="val">{{ $yn($soc['migration_last_5years'] ?? null) }}</td>
      <td class="lbl">मृत्यु (यो वर्ष)</td><td class="val">{{ $yn($soc['death'] ?? null) }}</td>
    </tr>
  </table>
</div>
@endif

{{-- ── 4. सरसफाई तथा सुविधा ──────────────────────────────────────────── --}}
@if($household->utilities)
@php $u = $household->utilities; @endphp
<div class="section">
  <div class="section-title">४. सरसफाई तथा सुविधा</div>
  <table class="kv">
    <tr>
      <td class="lbl">पिउने पानीको स्रोत</td><td class="val">{{ $ol('water_source', $u->water_source) }}</td>
      <td class="lbl">पानी दूरी (मिनेट)</td><td class="val">{{ $v($u->water_distance_minutes) }}</td>
    </tr>
    <tr>
      <td class="lbl">शौचालय छ?</td><td class="val">{{ $u->has_toilet ? 'छ' : 'छैन' }}</td>
      <td class="lbl">शौचालयको प्रकार</td><td class="val">{{ $ol('toilet_type', $u->toilet_type) }}</td>
    </tr>
    <tr>
      <td class="lbl">सरसफाई प्रकार</td><td class="val">{{ $ol('sanitation_type', $u->sanitation_type) }}</td>
      <td class="lbl">बिजुलीको स्रोत</td><td class="val">{{ $ol('electricity_source', $u->electricity_source) }}</td>
    </tr>
    <tr>
      <td class="lbl">मासिक एकाइ (kWh)</td><td class="val">{{ $v($u->monthly_electricity_units) }}</td>
      <td class="lbl">वैकल्पिक ऊर्जा</td><td class="val">{{ $yn($u->has_alt_electricity) }}</td>
    </tr>
    <tr>
      <td class="lbl">खाना पकाउने इन्धन</td>
      <td class="val">{{ $ols('cooking_fuel', $u->cooking_fuel_arr) }}</td>
      <td class="lbl">चुलोको प्रकार</td><td class="val">{{ $ol('cooking_stove', $u->cooking_stove) }}</td>
    </tr>
    <tr>
      <td class="lbl">बायोग्यास</td><td class="val">{{ $u->has_biogas ? 'छ' : 'छैन' }}</td>
      <td class="lbl">फोहोर व्यवस्थापन</td><td class="val">{{ $ol('solid_waste_management', $u->solid_waste_management) }}</td>
    </tr>
    <tr>
      <td class="lbl">इन्टरनेट</td><td class="val">{{ $u->has_internet ? 'छ' : 'छैन' }}</td>
      <td class="lbl">इन्टरनेट प्रकार</td><td class="val">{{ $ol('internet_type', $u->internet_type) }}</td>
    </tr>
    <tr>
      <td class="lbl">हात धुने सुविधा</td><td class="val">{{ $ol('handwashing', $u->handwashing) }}</td>
      <td class="lbl">सवारी साधन</td><td class="val">{{ $ol('vehicle_type', $u->vehicle) }}</td>
    </tr>
  </table>
</div>
@endif

{{-- ── 5. स्वास्थ्य तथा खुसी-सुखी जीवन ─────────────────────────────── --}}
@if($hlt || $wb)
<div class="section">
  <div class="section-title">५. स्वास्थ्य तथा खुसी-सुखी जीवन</div>
  @if($hlt)
  <table class="kv">
    <tr>
      <td class="lbl">स्वास्थ्य बिमा</td><td class="val">{{ $yn($hlt['has_health_insurance'] ?? null) }}</td>
      <td class="lbl">बिरामी आवृत्ति</td><td class="val">{{ $ol('illness_frequency', $hlt['illness_frequency'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">उपचार स्थान</td><td class="val">{{ $ol('treatment_place', $hlt['treatment_place'] ?? null) }}</td>
      <td class="lbl">स्वास्थ्य केन्द्र दूरी</td><td class="val">{{ $ol('time_to_health_center', $hlt['time_to_health_center'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">गर्भवती महिला?</td><td class="val">{{ $yn($hlt['has_pregnant_woman'] ?? null) }}</td>
      <td class="lbl">हात धुने जानकारी</td><td class="val">{{ $yn($hlt['aware_handwashing'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">पोषण जानकारी</td><td class="val">{{ $yn($hlt['aware_nutrition'] ?? null) }}</td>
      <td class="lbl">आइरन चक्की जानकारी</td><td class="val">{{ $yn($hlt['aware_iron_capsule'] ?? null) }}</td>
    </tr>
  </table>
  @endif
  @if($wb)
  <div style="margin-top:5pt; font-size:8pt; font-weight:bold; color:#444; border-top:0.5pt solid #ccc; padding-top:4pt;">खुसी-सुखी जीवन</div>
  <table class="kv">
    <tr>
      <td class="lbl">बसोबासमा खुशी?</td><td class="val">{{ $yn($wb['happy_with_location'] ?? null) }}</td>
      <td class="lbl">बसोबास धारणा</td><td class="val">{{ $ol('residence_perception', $wb['residence_perception'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">छिमेकी मिलन</td><td class="val">{{ $ol('neighbor_cooperation', $wb['neighbor_cooperation'] ?? null) }}</td>
      <td class="lbl">पारिवारिक भेलाघर</td><td class="val">{{ $ol('family_gathering_freq', $wb['family_gathering_freq'] ?? null) }}</td>
    </tr>
  </table>
  @endif
</div>
@endif

{{-- ── 6. यातायात तथा संस्था ──────────────────────────────────────────── --}}
@if($dec)
<div class="section">
  <div class="section-title">६. यातायात तथा संस्था</div>
  <table class="kv">
    <tr>
      <td class="lbl">घरायसी काम निर्णय</td><td class="val">{{ $ol('decision_owner', $dec['decisioninHHWork'] ?? null) }}</td>
      <td class="lbl">सम्पत्ति निर्णय</td><td class="val">{{ $ol('decision_owner', $dec['decisioninProperty'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">समिति सहभागिता</td><td class="val">{{ $ol('decision_owner', $dec['decisioninCCommittee'] ?? null) }}</td>
      <td class="lbl">SMC सहभागिता</td><td class="val">{{ $ol('decision_owner', $dec['decisioninSMC'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">व्यापार निर्णय</td><td class="val">{{ $ol('decision_owner', $dec['decisioninBusiness'] ?? null) }}</td>
      <td class="lbl">संस्थामा सहभागी?</td><td class="val">{{ $yn($dec['org_participation'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">बैंक खाता छ?</td><td class="val">{{ $yn($dec['has_bank_account'] ?? null) }}</td>
      <td class="lbl">खाता धनी</td><td class="val">{{ $ol('bank_account_owner', $dec['bank_account_owner'] ?? null) }}</td>
    </tr>
  </table>
</div>
@endif

{{-- ── 7. व्यवसाय तथा रोजगार ──────────────────────────────────────────── --}}
@if($occ)
<div class="section">
  <div class="section-title">७. व्यवसाय तथा रोजगार</div>
  <table class="kv">
    <tr>
      <td class="lbl">भारत विदेश</td><td class="val">{{ $v($occ['foreign_india'] ?? null) }}</td>
      <td class="lbl">खाडी विदेश</td><td class="val">{{ $v($occ['foreign_gulf'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">अन्य विदेश</td><td class="val">{{ $v($occ['foreign_others'] ?? null) }}</td>
      <td class="lbl">सीप तालिम</td><td class="val">{{ $yn($occ['skill_training'] ?? null) }}</td>
    </tr>
    @if(!empty($occ['occupation_rows']) && count($occ['occupation_rows']))
    <tr>
      <td class="lbl">व्यवसाय विवरण</td>
      <td class="val" colspan="3">{{ count($occ['occupation_rows']) }} पङ्क्ति</td>
    </tr>
    @endif
  </table>
</div>
@endif

{{-- ── 8. खेतीपाती ──────────────────────────────────────────────────────── --}}
@if($household->agriculture)
@php $ag = $household->agriculture; @endphp
<div class="section">
  <div class="section-title">८. खेतीपाती</div>
  <table class="kv">
    <tr>
      <td class="lbl">खेती गर्नुहुन्छ?</td><td class="val">{{ $yn($ag->agriculture) }}</td>
      <td class="lbl">जमिन छ?</td><td class="val">{{ $ag->owns_land ? 'छ' : 'छैन' }}</td>
    </tr>
    @if($ag->agri_categories && count($ag->agri_categories))
    <tr>
      <td class="lbl">खेती प्रकार</td>
      <td class="val" colspan="3">{{ $ols('agri_category', $ag->agri_categories) }}</td>
    </tr>
    @endif
    <tr>
      <td class="lbl">जमिन (रोपनी)</td><td class="val">{{ $v($ag->land_area_ropani) }}</td>
      <td class="lbl">सिञ्चित क्षेत्र (रोपनी)</td><td class="val">{{ $v($ag->irrigated_area_ropani) }}</td>
    </tr>
    <tr>
      <td class="lbl">वार्षिक कृषि आम्दानी</td><td class="val">{{ $v($ag->annual_agri_income) }}</td>
      <td class="lbl">रासायनिक मल?</td><td class="val">{{ $yn($ag->use_of_chem_fertilizer) }}</td>
    </tr>
    @if($ag->use_of_chem_fertilizer === 'yes')
    <tr>
      <td class="lbl">मल परिमाण (के.जी.)</td><td class="val">{{ $v($ag->chem_fertilizer_kg) }}</td>
      <td class="lbl">रासायनिक कीटनाशक?</td><td class="val">{{ $yn($ag->use_of_chem_pesticide) }}</td>
    </tr>
    @if($ag->chem_fertilizer_types && count($ag->chem_fertilizer_types))
    <tr>
      <td class="lbl">मलको प्रकार</td>
      <td class="val" colspan="3">{{ $ols('chem_fertilizer_types', $ag->chem_fertilizer_types) }}</td>
    </tr>
    @endif
    @if($ag->fertilizer_sources && count($ag->fertilizer_sources))
    <tr>
      <td class="lbl">मलको स्रोत</td>
      <td class="val" colspan="3">{{ $ols('fertilizer_sources', $ag->fertilizer_sources) }}</td>
    </tr>
    @endif
    @endif
    @if($ag->chem_pesticide_cost)
    <tr>
      <td class="lbl">कीटनाशक लागत</td><td class="val">{{ $v($ag->chem_pesticide_cost) }}</td>
      <td class="lbl"></td><td class="val"></td>
    </tr>
    @endif
    @if($ag->Farm_Challenges && count($ag->Farm_Challenges))
    <tr>
      <td class="lbl">चुनौतीहरू</td>
      <td class="val" colspan="3">{{ $ols('farm_challenge', $ag->Farm_Challenges) }}</td>
    </tr>
    @endif
  </table>
</div>
@endif

{{-- ── 9. कृषि उत्पादन ──────────────────────────────────────────────────── --}}
@php
  $hasCrop = $cp && collect(['food_crops','pulses','oil_seeds','vegetables','cash_crops','fruits','other_crops'])
               ->some(fn($k) => !empty($cp[$k]) && is_array($cp[$k]) && count($cp[$k]));
@endphp
@if($hasCrop)
<div class="section">
  <div class="section-title">९. कृषि उत्पादन</div>
  <table class="kv">
    <tr>
      <td class="lbl">खाद्यान्न बाली</td><td class="val">{{ !empty($cp['food_crops']) ? count($cp['food_crops']) . ' किसिम' : '—' }}</td>
      <td class="lbl">दलहन बाली</td><td class="val">{{ !empty($cp['pulses']) ? count($cp['pulses']) . ' किसिम' : '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">तेलहन बाली</td><td class="val">{{ !empty($cp['oil_seeds']) ? count($cp['oil_seeds']) . ' किसिम' : '—' }}</td>
      <td class="lbl">तरकारी</td><td class="val">{{ !empty($cp['vegetables']) ? count($cp['vegetables']) . ' किसिम' : '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">नगदे बाली</td><td class="val">{{ !empty($cp['cash_crops']) ? count($cp['cash_crops']) . ' किसिम' : '—' }}</td>
      <td class="lbl">फलफूल</td><td class="val">{{ !empty($cp['fruits']) ? count($cp['fruits']) . ' किसिम' : '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">अन्य बाली</td><td class="val">{{ !empty($cp['other_crops']) ? count($cp['other_crops']) . ' किसिम' : '—' }}</td>
      <td class="lbl"></td><td class="val"></td>
    </tr>
  </table>
</div>
@endif

{{-- ── 10. पशुपालन ──────────────────────────────────────────────────────── --}}
@if($lvd)
<div class="section">
  <div class="section-title">१०. पशुपालन</div>
  <table class="kv" style="margin-bottom:5pt">
    <tr>
      <td class="lbl">पशुपालन गर्नुहुन्छ?</td><td class="val">{{ $yn($lvd['has_livestock'] ?? null) }}</td>
      <td class="lbl">AI सेवा</td><td class="val">{{ $yn($lvd['Ai_in_livestock'] ?? null) }}</td>
    </tr>
  </table>
  @if(!empty($lvd['livestock_rows']) && count($lvd['livestock_rows']))
  <table class="tbl" style="margin-bottom:5pt">
    <thead>
      <tr><th>#</th><th>पशु/पक्षी</th><th>परिमाण</th><th>जात</th><th>पालनको तरिका</th></tr>
    </thead>
    <tbody>
      @foreach($lvd['livestock_rows'] as $i => $row)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $ol('livestock_kind', $row['kind'] ?? null) }}</td>
        <td>{{ $row['qty'] ?? '—' }}</td>
        <td>{{ $ol('livestock_breed', $row['breed'] ?? null) }}</td>
        <td>{{ $ol('livestock_rearing_method', $row['rearing_method'] ?? null) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif
  @if(!empty($lvd['product_rows']) && count($lvd['product_rows']))
  <div style="font-size:8pt; font-weight:bold; color:#444; margin-bottom:3pt">पशु उत्पादन</div>
  <table class="tbl">
    <thead>
      <tr><th>#</th><th>उत्पादन</th><th>एकाइ</th><th>उत्पादन</th><th>बिक्री</th><th>प्रति इकाई मूल्य</th></tr>
    </thead>
    <tbody>
      @foreach($lvd['product_rows'] as $i => $row)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $ol('livestock_product', $row['product'] ?? null) }}</td>
        <td>{{ $ol('livestock_product_unit', $row['unit'] ?? null) }}</td>
        <td>{{ $row['qty'] ?? '—' }}</td>
        <td>{{ $row['sale_qty'] ?? '—' }}</td>
        <td>{{ $row['price_per_unit'] ?? '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif
</div>
@endif

{{-- ── 11. मौरी / माछा / रेशम ──────────────────────────────────────────── --}}
@if($bfs && ($bfs['has_bee_fish_silk'] ?? null) === 'yes')
<div class="section">
  <div class="section-title">११. मौरी / माछा / रेशम</div>
  <table class="kv">
    @if(!empty($bfs['bee_fish_silk_select']))
    <tr>
      <td class="lbl">प्रकार</td>
      <td class="val" colspan="3">{{ $ols('bee_fish_silk', $bfs['bee_fish_silk_select']) }}</td>
    </tr>
    @endif
    <tr>
      <td class="lbl">मुढे घार</td><td class="val">{{ $v($bfs['Mudhe_Hive'] ?? null) }}</td>
      <td class="lbl">आधुनिक घार</td><td class="val">{{ $v($bfs['Modern_Hive'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">मह उत्पादन (मुढे)</td><td class="val">{{ $v($bfs['Honey_Production_Mudhe_Hive'] ?? null) }}</td>
      <td class="lbl">मह उत्पादन (आधुनिक)</td><td class="val">{{ $v($bfs['Honey_Production_Modern_Hive'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">मौरी तालिम</td><td class="val">{{ $yn($bfs['Bee_Training'] ?? null) }}</td>
      <td class="lbl">पोखरी क्षेत्र</td><td class="val">{{ $v($bfs['Pond_area'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">माछा उत्पादन</td><td class="val">{{ $v($bfs['fish_prod'] ?? null) }}</td>
      <td class="lbl">माछा बिक्री</td><td class="val">{{ $v($bfs['fish_sales'] ?? null) }}</td>
    </tr>
    <tr>
      <td class="lbl">रेशम (Kg)</td><td class="val">{{ $v($bfs['Sik'] ?? null) }}</td>
      <td class="lbl">रेशम उत्पादन/बिक्री</td><td class="val">{{ $v($bfs['silk_prod'] ?? null) }} / {{ $v($bfs['silk_sales'] ?? null) }}</td>
    </tr>
  </table>
</div>
@endif

{{-- ── 12. आम्दानी तथा खर्च ──────────────────────────────────────────── --}}
@if($household->economic)
@php $e = $household->economic; @endphp
<div class="section">
  <div class="section-title">१२. आम्दानी तथा खर्च</div>
  <table class="kv">
    <tr>
      <td class="lbl">मुख्य आयस्रोत</td><td class="val">{{ $ol('income_source', $e->primary_income_source) }}</td>
      <td class="lbl">वार्षिक आम्दानी</td><td class="val">{{ $v($e->annual_income_range) }}</td>
    </tr>
    <tr>
      <td class="lbl">वार्षिक खर्च</td><td class="val">{{ $v($e->annual_expenditure_range) }}</td>
      <td class="lbl">रेमिट्यान्स</td><td class="val">{{ $v($e->remittance_amount) }}</td>
    </tr>
    @if($e->loan_sources && count($e->loan_sources))
    <tr>
      <td class="lbl">ऋणको स्रोत</td>
      <td class="val" colspan="3">{{ $ols('loan_source', $e->loan_sources) }}</td>
    </tr>
    @endif
    <tr>
      <td class="lbl">जम्मा ऋण</td><td class="val">{{ $v($e->total_loan_amount) }}</td>
      <td class="lbl">बैंक खाता</td><td class="val">{{ $e->has_bank_account ? 'छ' : 'छैन' }}</td>
    </tr>
    <tr>
      <td class="lbl">बिमा</td><td class="val">{{ $e->has_insurance ? 'छ' : 'छैन' }}</td>
      <td class="lbl">गरिबी कार्ड</td><td class="val">{{ $e->poverty_card ? 'छ' : 'छैन' }}</td>
    </tr>
    <tr>
      <td class="lbl">सामाजिक सुरक्षा</td><td class="val">{{ $e->social_security_allowance ? 'छ' : 'छैन' }}</td>
      <td class="lbl">कृषि/पशु आम्दानी</td><td class="val">{{ $v($e->agri_livestock_income) }}</td>
    </tr>
    <tr>
      <td class="lbl">व्यापार आम्दानी</td><td class="val">{{ $v($e->business_industry_income) }}</td>
      <td class="lbl">रोजगार आम्दानी</td><td class="val">{{ $v($e->employment_service_income) }}</td>
    </tr>
    <tr>
      <td class="lbl">विदेश आम्दानी</td><td class="val">{{ $v($e->abroad_job_income) }}</td>
      <td class="lbl">खाना खर्च</td><td class="val">{{ $v($e->food_expense) }}</td>
    </tr>
    <tr>
      <td class="lbl">शिक्षा खर्च</td><td class="val">{{ $v($e->education_expense) }}</td>
      <td class="lbl">उपचार खर्च</td><td class="val">{{ $v($e->treatment_expense) }}</td>
    </tr>
  </table>
</div>
@endif

{{-- ── 13. प्राकृतिक प्रकोप ──────────────────────────────────────────── --}}
@if($household->disaster)
@php $dis = $household->disaster; @endphp
<div class="section">
  <div class="section-title">१३. प्राकृतिक प्रकोप</div>
  <table class="kv">
    @if($dis->risk_types && count($dis->risk_types))
    <tr>
      <td class="lbl">जोखिमका किसिम</td>
      <td class="val" colspan="3">{{ $ols('disaster_type', $dis->risk_types) }}</td>
    </tr>
    @endif
    <tr>
      <td class="lbl">जोखिम न्यूनीकरण योजना</td><td class="val">{{ $ol('risk_reduction_plan', $dis->risk_reduction_plan) }}</td>
      <td class="lbl">पछिल्लो ३ वर्षमा प्रभावित?</td><td class="val">{{ $yn($dis->affected_last_3years) }}</td>
    </tr>
    <tr>
      <td class="lbl">यो वर्ष मृत्यु?</td><td class="val">{{ $yn($dis->has_death_this_year) }}</td>
      @if($dis->death_rows && count($dis->death_rows))
      <td class="lbl">मृत्यु संख्या</td><td class="val">{{ count($dis->death_rows) }}</td>
      @else
      <td class="lbl"></td><td class="val"></td>
      @endif
    </tr>
    <tr>
      <td class="lbl">मानवीय क्षति</td>
      <td class="val">{{ $dis->human_loss_rows && count($dis->human_loss_rows) ? count($dis->human_loss_rows) . ' रेकर्ड' : '—' }}</td>
      <td class="lbl">भौतिक क्षति</td>
      <td class="val">{{ $dis->physical_loss_rows && count($dis->physical_loss_rows) ? count($dis->physical_loss_rows) . ' रेकर्ड' : '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">राहत प्राप्त?</td><td class="val">{{ $yn($dis->received_relief) }}</td>
      <td class="lbl">राहत विवरण</td>
      <td class="val">{{ $dis->relief_rows && count($dis->relief_rows) ? count($dis->relief_rows) . ' रेकर्ड' : '—' }}</td>
    </tr>
  </table>
</div>
@endif

{{-- ── 14. परिवार सदस्यहरू ──────────────────────────────────────────── --}}
@if($household->members && $household->members->count())
<div class="section">
  <div class="section-title">१४. परिवार सदस्यहरू ({{ $household->members->count() }} जना)</div>
  <table class="tbl">
    <thead>
      <tr>
        <th>#</th><th>पूरा नाम</th><th>नाता</th><th>उमेर</th><th>लिङ्ग</th>
        <th>जातजाति</th><th>शिक्षा</th><th>पेशा</th><th>वैवाहिक</th><th>रक्त समूह</th>
      </tr>
    </thead>
    <tbody>
      @foreach($household->members as $i => $m)
      <tr @if($m->is_household_head) style="background:#f0f4ff" @endif>
        <td>{{ $i + 1 }}</td>
        <td>
          {{ $m->full_name ?? '—' }}
          @if($m->is_household_head) <span style="font-size:7pt;color:#4f46e5">(घरमुली)</span> @endif
          @if($m->is_deceased) <span style="font-size:7pt;color:#ef4444">(मृत)</span> @endif
        </td>
        <td>{{ $ol('relation_to_head', $m->relation_to_head) }}</td>
        <td>{{ $m->age ?? '—' }}</td>
        <td>{{ ['male'=>'पुरुष','female'=>'महिला','other'=>'अन्य'][$m->gender] ?? ($m->gender ?? '—') }}</td>
        <td>{{ $ol('ethnicity', $m->ethnicity) }}</td>
        <td>{{ $ol('education_level', $m->education_level) }}</td>
        <td>{{ $ol('occupation_type', $m->occupation_type) }}</td>
        <td>{{ $ol('marital_status', $m->marital_status) }}</td>
        <td>{{ $m->blood_group ?? '—' }}</td>
      </tr>
      @if($m->disability_status || $m->chronic_illness || $m->has_citizenship || $m->has_voters_card || $m->has_national_id_card || $m->has_passport)
      <tr style="background:#fafafa; font-size:7pt; color:#555">
        <td colspan="2" style="text-align:right; color:#888">↳ थप:</td>
        <td colspan="8">
          @if($m->disability_status) <strong>अपाङ्गता:</strong> {{ $ol('disability_type', $m->disability_type) }}&nbsp; @endif
          @if($m->chronic_illness) <strong>दीर्घरोग</strong>&nbsp; @endif
          @if($m->has_citizenship) ✓नागरिकता&nbsp; @endif
          @if($m->has_voters_card) ✓मतदाता&nbsp; @endif
          @if($m->has_national_id_card) ✓परिचयपत्र&nbsp; @endif
          @if($m->has_passport) ✓राहदानी @endif
        </td>
      </tr>
      @endif
      @endforeach
    </tbody>
  </table>
</div>
@endif

@endsection
