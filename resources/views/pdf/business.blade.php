@extends('pdf._layout')

@section('doc-title', 'व्यवसाय विवरण')
@section('doc-meta')
  कोड: {{ $business->unique_code }} &nbsp;|&nbsp;
  वडा नं: {{ $business->ward->ward_number ?? '—' }} &nbsp;|&nbsp;
  अवस्था: {{ ['draft'=>'मस्यौदा','submitted'=>'पेश गरिएको','ward_approved'=>'वडाद्वारा स्वीकृत','published'=>'प्रकाशित','rejected'=>'अस्वीकृत'][$business->status] ?? $business->status }}
@endsection

@section('content')
@php
  $ol = function($cat, $val) use ($opts) {
    if ($val === null || $val === '') return '—';
    $map = $opts->get($cat);
    return $map?->get(strtolower((string)$val)) ?? $map?->get((string)$val) ?? $val;
  };
  $yn = fn($v) => $v ? 'छ' : 'छैन';
@endphp

{{-- 1. व्यवसाय स्वामी --}}
<div class="section">
  <div class="section-title">१. व्यवसाय स्वामी</div>
  <table class="kv">
    <tr>
      <td class="lbl">सदस्यको नाम</td><td class="val">{{ $business->member?->full_name ?? '—' }}</td>
      <td class="lbl">उमेर</td><td class="val">{{ $business->member?->age ? $business->member->age . ' वर्ष' : '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">बाबुको नाम</td><td class="val">{{ $business->owner_father_name ?? '—' }}</td>
      <td class="lbl">हजुरबाबुको नाम</td><td class="val">{{ $business->owner_grand_father_name ?? '—' }}</td>
    </tr>
    @if($business->household)
    <tr>
      <td class="lbl">घरधुरी कोड</td><td class="val">{{ $business->household->unique_code ?? '—' }}</td>
      <td class="lbl">घरमूली</td><td class="val">{{ $business->household->respondent_name ?? '—' }}</td>
    </tr>
    @endif
  </table>
</div>

{{-- 2. व्यवसाय पहिचान --}}
<div class="section">
  <div class="section-title">२. व्यवसाय पहिचान</div>
  <table class="kv">
    <tr>
      <td class="lbl">व्यवसायको नाम</td><td class="val" colspan="3">{{ $business->business_name ?? '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">व्यवसाय स्थान</td><td class="val">{{ $business->business_location ?? '—' }}</td>
      <td class="lbl">वडा नं</td><td class="val">{{ $business->ward->ward_number ?? '—' }}</td>
    </tr>
    <tr>
      <td class="lbl">व्यवसायको किसिम</td>
      <td class="val">
        @if($business->business_type === 'other')
          {{ $business->business_type_other ?? '—' }}
        @else
          {{ $ol('business_type', $business->business_type) }}
        @endif
      </td>
      <td class="lbl">सम्पर्क नम्बर</td><td class="val">{{ $business->contact_number ?? '—' }}</td>
    </tr>
  </table>
</div>

{{-- 3. व्यवसाय क्षेत्र --}}
<div class="section">
  <div class="section-title">३. व्यवसाय क्षेत्र</div>
  <table class="kv">
    <tr>
      <td class="lbl">व्यवसाय क्षेत्र</td>
      <td class="val">
        @if($business->business_field === 'other')
          {{ $business->business_field_other ?? '—' }}
        @else
          {{ $ol('business_field', $business->business_field) }}
        @endif
      </td>
      <td class="lbl">उप-वर्ग</td><td class="val">{{ $business->business_sub_category ?? '—' }}</td>
    </tr>
  </table>
</div>

{{-- 4. दर्ता तथा कर विवरण --}}
<div class="section">
  <div class="section-title">४. दर्ता तथा कर विवरण</div>
  <table class="kv">
    <tr>
      <td class="lbl">व्यवसाय दर्ता भएको?</td><td class="val">{{ $yn($business->is_registered) }}</td>
      <td class="lbl">PAN/VAT छ?</td>
      <td class="val">
        {{ $yn($business->has_pan_vat) }}
        @if($business->has_pan_vat && $business->pan_vat_number) &nbsp;({{ $business->pan_vat_number }}) @endif
      </td>
    </tr>
    <tr>
      <td class="lbl">व्यवसाय कर तिरेको?</td><td class="val">{{ $yn($business->paid_business_tax) }}</td>
      <td class="lbl">करको रकम</td>
      <td class="val">{{ $business->tax_amount ? 'रु. ' . number_format($business->tax_amount) : '—' }}</td>
    </tr>
    @if($business->place_of_registration && count($business->place_of_registration))
    <tr>
      <td class="lbl">दर्ता स्थान</td>
      <td class="val" colspan="3">{{ implode(', ', $business->place_of_registration) }}</td>
    </tr>
    @endif
    @if($business->reg_no_municipality)
    <tr>
      <td class="lbl">नगर/गाउँपालिका दर्ता नं.</td><td class="val">{{ $business->reg_no_municipality }}</td>
      <td class="lbl"></td><td class="val"></td>
    </tr>
    @endif
    @if($business->reg_no_home_cottage)
    <tr>
      <td class="lbl">घरेलु/कुटीर उद्योग नं.</td><td class="val">{{ $business->reg_no_home_cottage }}</td>
      <td class="lbl"></td><td class="val"></td>
    </tr>
    @endif
    @if($business->reg_no_company_registrar)
    <tr>
      <td class="lbl">कम्पनी रजिस्ट्रार नं.</td><td class="val">{{ $business->reg_no_company_registrar }}</td>
      <td class="lbl"></td><td class="val"></td>
    </tr>
    @endif
    @if($business->reg_no_fcci)
    <tr>
      <td class="lbl">FCCI/FNCII नं.</td><td class="val">{{ $business->reg_no_fcci }}</td>
      <td class="lbl"></td><td class="val"></td>
    </tr>
    @endif
    @if($business->reg_no_govt_office)
    <tr>
      <td class="lbl">सरकारी कार्यालय दर्ता नं.</td><td class="val">{{ $business->reg_no_govt_office }}</td>
      <td class="lbl"></td><td class="val"></td>
    </tr>
    @endif
  </table>
</div>

@endsection
