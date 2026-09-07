@extends('pdf._layout')

@section('doc-title', 'संस्था विवरण')
@section('doc-meta')
  कोड: {{ $institution->unique_code }} &nbsp;|&nbsp;
  वडा नं: {{ $institution->ward->ward_number ?? '—' }} &nbsp;|&nbsp;
  अवस्था: {{ ['draft'=>'मस्यौदा','submitted'=>'पेश गरिएको','ward_approved'=>'वडाद्वारा स्वीकृत','published'=>'प्रकाशित','rejected'=>'अस्वीकृत'][$institution->status] ?? $institution->status }}
@endsection

@section('content')
@php
  $ol = function($cat, $val) use ($opts) {
    if ($val === null || $val === '') return '—';
    $map = $opts->get($cat);
    return $map?->get(strtolower((string)$val)) ?? $map?->get((string)$val) ?? $val;
  };
  $yn  = fn($v) => $v ? 'छ' : 'छैन';
  $num = fn($v) => $v !== null ? $v : '—';
@endphp

{{-- 1. Basic Info --}}
<div class="section">
  <div class="section-title">१. आधारभूत जानकारी</div>
  <table class="kv">
    <tr>
      <td class="lbl">संस्थाको नाम (नेपाली)</td><td class="val" colspan="3">{{ $institution->name_np ?? '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">संस्थाको नाम (अंग्रेजी)</td><td class="val" colspan="3">{{ $institution->name_en ?? '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">संस्थाको प्रकार</td><td class="val">{{ \App\Models\SurveyOption::where('category','institution_type')->where('value',$institution->institution_type)->value('label_ne') ?? $institution->institution_type ?? '—' }}</td>
      <td class="lbl">स्वामित्व</td><td class="val">{{ $ol('ownership_type', $institution->ownership_type) }}</td>
    </tr>
    <tr>
      <td class="lbl">वडा नं</td><td class="val">{{ $institution->ward->ward_number ?? '—' }}</td>
      <td class="lbl">बस्ती</td><td class="val">{{ $institution->settlement?->name_np ?? '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">दर्ता नम्बर</td><td class="val">{{ $institution->registration_number ?? '—' }}</td>
      <td class="lbl">स्थापना मिति</td><td class="val">{{ $institution->established_date?->format('Y-m-d') ?? '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">उत्तरदाताको नाम</td><td class="val">{{ $institution->respondent_name ?? '—' }}</td>
      <td class="lbl">सम्पर्क नम्बर</td><td class="val">{{ $institution->respondent_contact ?? '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">सर्वेक्षण मिति</td>
      <td class="val">{{ $institution->survey_date?->format('Y-m-d') ?? '—' }}</td>
      <td class="lbl">GPS (Lat, Lng)</td>
      <td class="val">
        @if($institution->gps_lat && $institution->gps_lng)
          {{ $institution->gps_lat }}, {{ $institution->gps_lng }}
          @if($institution->gps_altitude) &nbsp;(उचाइ: {{ $institution->gps_altitude }}m) @endif
        @else —
        @endif
      </td>
    </tr>
    @if($institution->remarks)
    <tr><td class="lbl">कैफियत</td><td class="val" colspan="3">{{ $institution->remarks }}</td></tr>
    @endif
  </table>
</div>

{{-- 2. Infrastructure --}}
@if($institution->infrastructure)
@php $inf = $institution->infrastructure; @endphp
<div class="section">
  <div class="section-title">२. भौतिक पूर्वाधार</div>

  {{-- Buildings table --}}
  @if($inf->buildings && count($inf->buildings))
  <table class="tbl" style="margin-bottom:6pt">
    <thead>
      <tr><th>भवनको प्रकार</th><th>भवन संख्या</th><th>कोठा संख्या</th></tr>
    </thead>
    <tbody>
      @foreach($inf->buildings as $b)
      <tr>
        <td>{{ $ol('inst_building_type', $b['building_type'] ?? null) }}</td>
        <td>{{ $b['building_count'] ?? '—' }}</td>
        <td>{{ $b['room_count'] ?? '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  <table class="kv">
    <tr>
      <td class="lbl">कुल क्षेत्रफल (वर्गमि.)</td><td class="val">{{ $num($inf->total_area) }}</td>
      <td class="lbl">भवनको अवस्था</td><td class="val">{{ $ol('condition', $inf->condition) }}</td>
    </tr>
    @if($inf->total_classrooms)
    <tr>
      <td class="lbl">कक्षाकोठा</td><td class="val">{{ $inf->total_classrooms }}</td>
      <td class="lbl"></td><td class="val"></td>
    </tr>
    @endif
    <tr>
      <td class="lbl">विद्युत</td><td class="val">{{ $yn($inf->has_electricity) }}</td>
      <td class="lbl">खानेपानी</td><td class="val">{{ $yn($inf->has_water) }}</td>
    </tr>
    <tr>
      <td class="lbl">इन्टरनेट</td><td class="val">{{ $yn($inf->has_internet) }}</td>
      <td class="lbl">शौचालय</td><td class="val">{{ $yn($inf->has_toilet) }}</td>
    </tr>
    @if($inf->has_toilet && ($inf->male_toilet || $inf->female_toilet))
    <tr>
      <td class="lbl">पुरुष शौचालय</td><td class="val">{{ $num($inf->male_toilet) }}</td>
      <td class="lbl">महिला शौचालय</td><td class="val">{{ $num($inf->female_toilet) }}</td>
    </tr>
    @endif
    <tr>
      <td class="lbl">Ramp/ढलान</td><td class="val">{{ $yn($inf->has_ramp) }}</td>
      <td class="lbl">सीमाना पर्खाल</td><td class="val">{{ $yn($inf->has_boundary_wall) }}</td>
    </tr>
    @if($inf->has_library !== null || $inf->has_lab !== null)
    <tr>
      <td class="lbl">पुस्तकालय</td><td class="val">{{ $yn($inf->has_library) }}</td>
      <td class="lbl">प्रयोगशाला</td><td class="val">{{ $yn($inf->has_lab) }}</td>
    </tr>
    @endif
    <tr>
      <td class="lbl">सौर्य ऊर्जा</td><td class="val">{{ $yn($inf->solar) }}</td>
      <td class="lbl">जेनेरेटर</td><td class="val">{{ $yn($inf->generator) }}</td>
    </tr>
    <tr>
      <td class="lbl">आगो निभाउने यन्त्र</td><td class="val">{{ $yn($inf->fire_extinguisher) }}</td>
      <td class="lbl">भूकम्प सुरक्षित</td><td class="val">{{ $yn($inf->earthquake_safe) }}</td>
    </tr>
  </table>
</div>
@endif

{{-- 3. Staff --}}
@if($institution->staff && $institution->staff->count())
<div class="section">
  <div class="section-title">३. कर्मचारी विवरण</div>
  <table class="tbl">
    <thead>
      <tr><th>वर्ग</th><th>पुरुष</th><th>महिला</th><th>जम्मा</th><th>स्थायी</th><th>करार</th><th>दलित</th><th>जनजाति</th></tr>
    </thead>
    <tbody>
      @foreach($institution->staff as $st)
      <tr>
        <td>{{ $ol('staff_category', $st->staff_category) }}</td>
        <td>{{ $st->male_count ?? 0 }}</td>
        <td>{{ $st->female_count ?? 0 }}</td>
        <td>{{ ($st->male_count ?? 0) + ($st->female_count ?? 0) }}</td>
        <td>{{ $st->permanent_count ?? 0 }}</td>
        <td>{{ $st->contract_count ?? 0 }}</td>
        <td>{{ $st->dalit_count ?? 0 }}</td>
        <td>{{ $st->janajati_count ?? 0 }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- 4. Services --}}
@if($institution->services && $institution->services->count())
<div class="section">
  <div class="section-title">४. सेवाहरू</div>
  <table class="tbl">
    <thead>
      <tr><th>सेवाको प्रकार</th><th>विवरण</th></tr>
    </thead>
    <tbody>
      @foreach($institution->services as $svc)
      <tr>
        <td>{{ $ol('inst_service_type', $svc->service_type) }}</td>
        <td>{{ $svc->description ?? '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- 5. Education (school only) --}}
@if($institution->education && $institution->education->count())
<div class="section">
  <div class="section-title">५. शिक्षा विवरण</div>
  <table class="tbl">
    <thead>
      <tr><th>कक्षा</th><th>छात्र</th><th>छात्रा</th><th>जम्मा</th></tr>
    </thead>
    <tbody>
      @foreach($institution->education as $edu)
      <tr>
        <td>{{ $ol('inst_grade', $edu->grade) }}</td>
        <td>{{ $edu->male_students ?? 0 }}</td>
        <td>{{ $edu->female_students ?? 0 }}</td>
        <td>{{ ($edu->male_students ?? 0) + ($edu->female_students ?? 0) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- 6. Health Detail --}}
@if($institution->healthDetail)
@php $hd = $institution->healthDetail; @endphp
<div class="section">
  <div class="section-title">६. स्वास्थ्य विवरण</div>
  <table class="kv">
    <tr>
      <td class="lbl">शय्या संख्या</td><td class="val">{{ $num($hd->bed_count) }}</td>
      <td class="lbl">OPD (दैनिक)</td><td class="val">{{ $num($hd->opd_daily) }}</td>
    </tr>
    <tr>
      <td class="lbl">एम्बुलेन्स</td><td class="val">{{ $yn($hd->has_ambulance) }}</td>
      <td class="lbl">प्रसूति सेवा</td><td class="val">{{ $yn($hd->delivery_service) }}</td>
    </tr>
  </table>
</div>
@endif

@endsection
