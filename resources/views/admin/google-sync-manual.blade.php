<!DOCTYPE html>
<html lang="ne">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Google Sheets Sync — तयारी निर्देशिका</title>
<style>
  /* ── Base ── */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', 'Noto Sans Devanagari', Arial, sans-serif;
    font-size: 12px; line-height: 1.6; color: #1e293b;
    background: #f8fafc; padding: 24px;
  }
  a { color: #2563eb; text-decoration: none; }

  /* ── Layout ── */
  .page-wrap  { max-width: 1000px; margin: 0 auto; }

  /* ── Cover ── */
  .cover {
    background: #182433; color: #fff;
    border-radius: 10px; padding: 36px 40px 28px;
    margin-bottom: 28px;
  }
  .cover h1   { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
  .cover h1 span { font-size: 14px; font-weight: 400; opacity: .7; display: block; margin-top: 2px; }
  .cover-meta { margin-top: 16px; display: flex; flex-wrap: wrap; gap: 10px; }
  .cover-meta .badge {
    background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2);
    color: #cbd5e1; font-size: 11px; padding: 3px 10px; border-radius: 20px;
  }
  .print-btn {
    display: inline-flex; align-items: center; gap: 6px;
    background: #2563eb; color: #fff; border: none; border-radius: 6px;
    padding: 8px 16px; font-size: 12px; font-weight: 600; cursor: pointer;
    margin-top: 14px;
  }
  .print-btn:hover { background: #1d4ed8; }

  /* ── Section headers ── */
  .section { margin-bottom: 28px; }
  .section-title {
    display: flex; align-items: center; gap: 8px;
    font-size: 14px; font-weight: 700; color: #fff;
    padding: 8px 14px; border-radius: 6px; margin-bottom: 12px;
  }
  .section-title .en { font-size: 11px; font-weight: 400; opacity: .8; margin-left: 4px; }

  /* ── Callout boxes ── */
  .callout {
    border-left: 4px solid; padding: 10px 14px;
    border-radius: 0 6px 6px 0; margin-bottom: 12px; font-size: 11.5px;
  }
  .callout.blue  { background: #eff6ff; border-color: #2563eb; color: #1e40af; }
  .callout.green { background: #f0fdf4; border-color: #16a34a; color: #14532d; }
  .callout.red   { background: #fef2f2; border-color: #dc2626; color: #7f1d1d; }
  .callout.amber { background: #fffbeb; border-color: #d97706; color: #78350f; }
  .callout strong { display: block; margin-bottom: 3px; }

  /* ── Steps ── */
  .steps { counter-reset: step; list-style: none; padding: 0; }
  .steps li {
    counter-increment: step; display: flex; gap: 12px;
    margin-bottom: 10px; align-items: flex-start;
  }
  .steps li::before {
    content: counter(step); min-width: 24px; height: 24px;
    background: #2563eb; color: #fff; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 11px; flex-shrink: 0; margin-top: 1px;
  }

  /* ── Sheet cards ── */
  .sheet-grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
  .sheet-card { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
  .sheet-card-header {
    padding: 8px 14px; font-weight: 700; font-size: 12px;
    display: flex; align-items: center; gap: 8px;
  }
  .sheet-card-header .tab-name {
    font-family: monospace; background: rgba(0,0,0,.15);
    padding: 2px 8px; border-radius: 4px; font-size: 11px;
  }
  .sheet-card-header .subtitle { font-size: 10px; font-weight: 400; opacity: .8; }
  .sheet-card-body { padding: 10px 14px; background: #fff; }

  /* ── Column tables ── */
  .col-table { width: 100%; border-collapse: collapse; font-size: 11px; }
  .col-table th {
    background: #f1f5f9; color: #475569; font-weight: 600; font-size: 10px;
    text-transform: uppercase; padding: 5px 8px; text-align: left;
    border-bottom: 1px solid #e2e8f0;
  }
  .col-table td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
  .col-table tr:last-child td { border-bottom: none; }
  .col-table tr:hover td { background: #f8fafc; }
  .col-table .col-name { font-family: monospace; color: #2563eb; font-size: 10.5px; white-space: nowrap; }
  .col-table .col-req  { text-align: center; }
  .req-dot  { color: #dc2626; font-size: 14px; line-height: 1; }
  .opt-dot  { color: #94a3b8; font-size: 14px; line-height: 1; }
  .val-hint { color: #64748b; font-size: 10.5px; }
  .val-hint code { background: #f1f5f9; padding: 0 3px; border-radius: 3px; font-size: 10px; color: #334155; }

  /* ── Errors table ── */
  .err-table { width: 100%; border-collapse: collapse; font-size: 11px; }
  .err-table th {
    background: #fef2f2; color: #7f1d1d; font-weight: 600; font-size: 10px;
    text-transform: uppercase; padding: 5px 8px; border-bottom: 1px solid #fecaca;
  }
  .err-table td { padding: 6px 8px; border-bottom: 1px solid #fef2f2; vertical-align: top; }
  .err-table tr:last-child td { border-bottom: none; }
  .err-code { font-family: monospace; background: #fef2f2; color: #dc2626; padding: 1px 6px; border-radius: 3px; font-size: 10.5px; white-space: nowrap; }

  /* ── Process steps ── */
  .process { display: flex; gap: 0; margin: 12px 0; }
  .process-step {
    flex: 1; text-align: center; padding: 12px 8px;
    background: #eff6ff; border: 1px solid #bfdbfe;
    position: relative; font-size: 11px;
  }
  .process-step:not(:last-child)::after {
    content: '▶'; position: absolute; right: -10px; top: 50%;
    transform: translateY(-50%); color: #2563eb; font-size: 14px; z-index: 1;
  }
  .process-step .ps-num { font-size: 18px; display: block; margin-bottom: 4px; }
  .process-step .ps-ne  { font-weight: 700; color: #1e40af; }
  .process-step .ps-en  { color: #60a5fa; font-size: 10px; }
  .process-step:first-child { border-radius: 6px 0 0 6px; }
  .process-step:last-child  { border-radius: 0 6px 6px 0; }

  /* ── Colour key ── */
  .colour-key { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
  .ck-item { display: flex; align-items: center; gap: 5px; font-size: 11px; }
  .ck-dot  { width: 12px; height: 12px; border-radius: 3px; flex-shrink: 0; }

  /* ── Footer ── */
  .manual-footer {
    margin-top: 32px; padding-top: 14px; border-top: 1px solid #e2e8f0;
    text-align: center; font-size: 10.5px; color: #94a3b8;
  }

  /* ── Print styles ── */
  @media print {
    body { background: #fff; padding: 0; font-size: 11px; }
    .no-print { display: none !important; }
    .sheet-card, .callout, .section { break-inside: avoid; }
    .section-title { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .sheet-card-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .col-table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    a { color: inherit; }
    .cover { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .print-btn { display: none; }
  }
</style>
</head>
<body>
@php
/**
 * Render option values as <code> pills with Nepali label.
 * Falls back to $fallback HTML if the category has no DB options.
 */
$optVals = function(string $cat, string $fallback = '', int $max = 10) use ($options): string {
    $opts = $options[$cat] ?? [];
    if (empty($opts)) return $fallback ?: '<span style="color:#9ca3af">—</span>';
    $items = [];
    $i = 0;
    foreach ($opts as $value => $labelNe) {
        if ($i >= $max) {
            $items[] = '<span style="color:#9ca3af;font-size:9px;">… +' . (count($opts) - $max) . ' more</span>';
            break;
        }
        $items[] = '<code>' . e($value) . '</code>'
                 . '<span style="color:#6b7280;font-size:9.5px;margin-left:1px;">' . e($labelNe) . '</span>';
        $i++;
    }
    return implode(' <span style="color:#cbd5e1">/</span> ', $items);
};
@endphp
<div class="page-wrap">

  {{-- ══ COVER ══ --}}
  <div class="cover">
    <h1>
      Google Sheets Sync — तयारी निर्देशिका
      <span>Google Sheets Sync Preparation Manual</span>
    </h1>
    <div class="cover-meta">
      <span class="badge">📋 घरधुरी सिंक — ८ शिट</span>
      <span class="badge">🏢 संस्था सिंक — ७ शिट</span>
      <span class="badge">Version 1.7</span>
    </div>
    <button class="print-btn no-print" onclick="window.print()">
      🖨 PDF/Print गर्नुहोस्
    </button>
  </div>

  {{-- ══ SECTION 1: PREREQUISITES ══ --}}
  <div class="section">
    <div class="section-title" style="background:#1e3a5f;">
      ১. पूर्वतयारी <span class="en">Prerequisites</span>
    </div>

    <div class="callout blue">
      <strong>Google Sheet सार्वजनिक बनाउनुहोस् / Make Sheet Publicly Readable</strong>
      Google Sheets → Share → "Anyone with the link" → <strong>Viewer</strong>।<br>
      यदि Sheet private छ भने सिंक गर्दा त्रुटि आउँछ।<br>
      <em>If the sheet is private, the sync will fail with an HTTP 403 error.</em>
    </div>

    <div class="callout blue">
      <strong>Sheet ID कन्फिगर गर्नुहोस् / Configure Sheet ID in Settings</strong>
      Admin → Settings → Google Sheets ट्याब → Household Sheet ID / Institution Sheet ID थप्नुहोस्।<br>
      Sheet URL बाट ID निकाल्नुहोस्: <code style="background:#dbeafe;padding:1px 5px;border-radius:3px;font-size:10.5px;">https://docs.google.com/spreadsheets/d/<strong>SHEET_ID_HERE</strong>/edit</code>
    </div>

    <div class="callout amber">
      <strong>Ward नम्बर मिल्नु पर्छ / Ward Number Must Match Exactly</strong>
      Sheet को <code>ward_number</code> कलम र प्रणालीमा दर्ता वडा नम्बर ठीक मिल्नु पर्छ।<br>
      <em>The ward_number column must exactly match the ward registered in the system.</em>
    </div>

    <div class="callout green">
      <strong>इन्टरनेट जडान / Internet Connection</strong>
      सिंक गर्दा प्रणालीले Google Sheet सिधै डाउनलोड गर्छ — स्थिर इन्टरनेट आवश्यक छ।<br>
      <em>The system downloads the sheet directly — a stable internet connection is required.</em>
    </div>
  </div>

  {{-- ══ SECTION 2: HOW SYNC WORKS ══ --}}
  <div class="section">
    <div class="section-title" style="background:#1e3a5f;">
      २. सिंक कसरी काम गर्छ? <span class="en">How Does Sync Work?</span>
    </div>

    <div class="process">
      <div class="process-step">
        <span class="ps-num">📥</span>
        <div class="ps-ne">डाउनलोड</div>
        <div class="ps-en">Download Sheet</div>
      </div>
      <div class="process-step">
        <span class="ps-num">🔍</span>
        <div class="ps-ne">Preview</div>
        <div class="ps-en">Compare with DB</div>
      </div>
      <div class="process-step">
        <span class="ps-num">✅</span>
        <div class="ps-ne">जाँच गर्नुहोस्</div>
        <div class="ps-en">Check completeness</div>
      </div>
      <div class="process-step">
        <span class="ps-num">💾</span>
        <div class="ps-ne">Apply गर्नुहोस्</div>
        <div class="ps-en">Save to Database</div>
      </div>
    </div>

    <div class="callout blue">
      <strong>Preview मोड — डेटा बदलिँदैन / Preview Mode — No Data Changed</strong>
      Preview बटन थिच्दा नयाँ, परिवर्तित र त्रुटि भएका row हेर्न सकिन्छ। केही सेभ हुँदैन।<br>
      <em>Clicking Preview shows new, changed, and error rows — nothing is saved yet.</em>
    </div>

    <div class="callout amber">
      <strong>Locked Records — मनुअल सम्पादन गरिएका रेकर्डहरू</strong>
      कसैले प्रणालीमा सिधै सम्पादन गरेको रेकर्ड "Locked" हुन्छ — सिंकले सो रेकर्ड छोड्छ।<br>
      जबरजस्ती अपडेट गर्न "Force sync locked records" सक्रिय गर्नुहोस्।<br>
      <em>Records edited directly in the system are "locked" and skipped during sync. Enable force sync to override.</em>
    </div>
  </div>

  {{-- ══ SECTION 3: HOUSEHOLD SHEETS ══ --}}
  <div class="section">
    <div class="section-title" style="background:#1e40af;">
      ३. घरधुरी — Sheet संरचना <span class="en">Household Sheet Structure (8 Tabs)</span>
    </div>

    <div class="callout blue">
      एउटा Excel फाइलमा तलका <strong>८ ट्याब (Sheet)</strong> हुनु पर्छ। ट्याबको नाम ठीक यस्तै हुनु पर्छ।<br>
      <em>The Excel file must have exactly these <strong>8 tab names</strong>. Tab names are case-sensitive.</em>
    </div>

    <div class="colour-key">
      <div class="ck-item"><div class="ck-dot" style="background:#dc2626;"></div> आवश्यक / Required</div>
      <div class="ck-item"><div class="ck-dot" style="background:#94a3b8;"></div> ऐच्छिक / Optional</div>
      <div class="ck-item"><div class="ck-dot" style="background:#2563eb;"></div> Column Name (exact spelling)</div>
    </div>

    <div class="sheet-grid">

      {{-- Tab 1: Households --}}
      <div class="sheet-card">
        <div class="sheet-card-header" style="background:#1e40af;color:#fff;">
          <span class="tab-name">Households</span>
          <span>मुख्य घरधुरी विवरण <span class="subtitle">— Main Household Info</span></span>
        </div>
        <div class="sheet-card-body">
          <table class="col-table">
            <thead><tr><th>Column Name</th><th class="col-req">चाहिन्छ?</th><th>विवरण / Description</th></tr></thead>
            <tbody>
              <tr><td class="col-name">household_code</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">Auto-generated if blank (e.g. <code>HH-W01-00001</code>). Update existing records using this code.</span></td></tr>
              <tr><td class="col-name">ward_number</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">वडा नम्बर — must match system exactly (e.g. <code>1</code>)</span></td></tr>
              <tr><td class="col-name">settlement_name</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">टोल/बस्तीको नाम — auto-created if not found</span></td></tr>
              <tr><td class="col-name">respondent_name</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">घरमुलीको नाम</span></td></tr>
              <tr><td class="col-name">respondent_contact</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">सम्पर्क नम्बर</span></td></tr>
              <tr><td class="col-name">total_members</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">कुल परिवार सदस्य संख्या</span></td></tr>
              <tr><td class="col-name">gps_lat</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">GPS Latitude (Nepal: <code>26.0–30.5</code>)</span></td></tr>
              <tr><td class="col-name">gps_lng</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">GPS Longitude (Nepal: <code>80.0–88.5</code>)</span></td></tr>
              <tr><td class="col-name">family_head</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">परिवारको मूलीको पूरा नाम</span></td></tr>
              <tr><td class="col-name">head_gender</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint"><code>male</code> / <code>female</code> / <code>other</code></span></td></tr>
              <tr><td class="col-name">family_type</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>nuclear</code> / <code>joint</code> / <code>single</code> / <code>other</code></span></td></tr>
              <tr><td class="col-name">survey_date</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">सर्वेक्षण मिति — format: <code>YYYY-MM-DD</code></span></td></tr>
              <tr><td class="col-name">photo_number</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">फोटो फाइलको नाम (extension बिना, e.g. <code>00247</code>)</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Tab 2: Members --}}
      <div class="sheet-card">
        <div class="sheet-card-header" style="background:#065f46;color:#fff;">
          <span class="tab-name">Members</span>
          <span>परिवार सदस्यहरू <span class="subtitle">— one row per member</span></span>
        </div>
        <div class="sheet-card-body">
          <div class="callout green" style="margin-bottom:8px;font-size:11px;">
            एउटा घरधुरीका सबै सदस्यहरू यही शिटमा राख्नुहोस् — प्रत्येक सदस्य एउटा पङ्क्तिमा।<br>
            <em>All members of a household go in this sheet — one row per member.</em>
          </div>
          <table class="col-table">
            <thead><tr><th>Column Name</th><th class="col-req">चाहिन्छ?</th><th>विवरण / Description</th></tr></thead>
            <tbody>
              <tr><td class="col-name">household_code</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">Households शिटसँग मिल्नु पर्छ</span></td></tr>
              <tr><td class="col-name">full_name</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">सदस्यको पूरा नाम — used to match existing members</span></td></tr>
              <tr><td class="col-name">gender</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint"><code>male</code> / <code>female</code> / <code>other</code></span></td></tr>
              <tr><td class="col-name">age</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">उमेर (वर्षमा)</span></td></tr>
              <tr><td class="col-name">date_of_birth</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">जन्म मिति — <code>YYYY-MM-DD</code></span></td></tr>
              <tr><td class="col-name">relation_to_head</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('relation_to_head', '<code>self</code> / <code>wife</code> / <code>husband</code> / <code>son</code> / <code>daughter</code> / <code>other</code>') !!}</span></td></tr>
              <tr><td class="col-name">ethnicity</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('ethnicity', '<code>brahmin</code> / <code>chhetri</code> / <code>magar</code> / <code>tamang</code> / <code>newar</code> / <code>other</code>') !!}</span></td></tr>
              <tr><td class="col-name">religion</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('religion', '<code>hindu</code> / <code>buddhist</code> / <code>muslim</code> / <code>christian</code> / <code>kirant</code>') !!}</span></td></tr>
              <tr><td class="col-name">education_level</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('education_level', '<code>illiterate</code> / <code>primary</code> / <code>slc</code> / <code>bachelor</code> / <code>master</code>') !!}</span></td></tr>
              <tr><td class="col-name">occupation_type</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('occupation_type', '<code>agriculture</code> / <code>business</code> / <code>service_govt</code> / <code>student</code> / <code>other</code>') !!}</span></td></tr>
              <tr><td class="col-name">marital_status</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('marital_status', '<code>unmarried</code> / <code>married</code> / <code>divorced</code> / <code>widowed</code>') !!}</span></td></tr>
              <tr><td class="col-name">disability_status</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>1</code>=छ / <code>0</code>=छैन</span></td></tr>
              <tr><td class="col-name">has_citizenship</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>1</code>=छ / <code>0</code>=छैन</span></td></tr>
              <tr><td class="col-name">is_household_head</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>1</code>=घरमुली / <code>0</code>=अन्य (एक परिवारमा एकजना मात्र)</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Tab 3: Housing --}}
      <div class="sheet-card">
        <div class="sheet-card-header" style="background:#6d28d9;color:#fff;">
          <span class="tab-name">Housing</span>
          <span>आवास विवरण <span class="subtitle">— one row per household</span></span>
        </div>
        <div class="sheet-card-body">
          <table class="col-table">
            <thead><tr><th>Column Name</th><th class="col-req">चाहिन्छ?</th><th>विवरण / Description</th></tr></thead>
            <tbody>
              <tr><td class="col-name">household_code</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">Households शिटसँग मिल्नु पर्छ</span></td></tr>
              <tr><td class="col-name">house_type</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('house_type', '<code>rcc_building</code> / <code>wooden</code> / <code>mud_house</code> / <code>temporary</code>') !!}</span></td></tr>
              <tr><td class="col-name">roof_type</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('roof_type', '<code>rcc</code> / <code>tin</code> / <code>slate</code> / <code>thatch</code>') !!}</span></td></tr>
              <tr><td class="col-name">wall_type</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('wall_type', '<code>brick_cement</code> / <code>stone_cement</code> / <code>wood</code> / <code>bamboo</code>') !!}</span></td></tr>
              <tr><td class="col-name">foundation_type</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('foundation_type', '<code>rcc</code> / <code>brick_cement</code> / <code>stone_cement</code> / <code>stone_mud</code>') !!}</span></td></tr>
              <tr><td class="col-name">ownership_type</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('ownership_type', '<code>owned</code> / <code>rented</code> / <code>institutional</code>') !!}</span></td></tr>
              <tr><td class="col-name">total_rooms</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">कोठाहरूको संख्या</span></td></tr>
              <tr><td class="col-name">house_condition</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('house_condition', '<code>good</code> / <code>average</code> / <code>poor</code> / <code>damaged</code>') !!}</span></td></tr>
              <tr><td class="col-name">land_measurement_unit</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('land_measurement_unit', '<code>ropani</code> / <code>ana</code> / <code>bigha</code> / <code>kattha</code> / <code>dhur</code>') !!}</span></td></tr>
              <tr><td class="col-name">house_land_type</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('house_land_type', '<code>registered</code> / <code>unregistered</code> / <code>leased</code> / <code>guthi</code>') !!}</span></td></tr>
              <tr><td class="col-name">has_building_permit</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>1</code>=छ / <code>0</code>=छैन</span></td></tr>
              <tr><td class="col-name">construction_year</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">निर्माण वर्ष (e.g. <code>2010</code>)</span></td></tr>
              <tr><td class="col-name">has_land_in_municipality</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>1</code>=पालिकामा जग्गा छ / <code>0</code>=छैन — details in LandParcels sheet</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Tab 4: Utilities --}}
      <div class="sheet-card">
        <div class="sheet-card-header" style="background:#0369a1;color:#fff;">
          <span class="tab-name">Utilities</span>
          <span>सुविधाहरू <span class="subtitle">— one row per household</span></span>
        </div>
        <div class="sheet-card-body">
          <table class="col-table">
            <thead><tr><th>Column Name</th><th class="col-req">चाहिन्छ?</th><th>विवरण / Description</th></tr></thead>
            <tbody>
              <tr><td class="col-name">household_code</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">Households शिटसँग मिल्नु पर्छ</span></td></tr>
              <tr><td class="col-name">water_source</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('water_source', '<code>tap_water</code> / <code>tube_well</code> / <code>river</code> / <code>spring</code> / <code>well</code>') !!}</span></td></tr>
              <tr><td class="col-name">has_toilet</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>1</code>=छ / <code>0</code>=छैन</span></td></tr>
              <tr><td class="col-name">toilet_type</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('toilet_type', '<code>flush</code> / <code>pit</code> / <code>open_field</code> / <code>none</code>') !!}</span></td></tr>
              <tr><td class="col-name">electricity_source</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('electricity_source', '<code>grid</code> / <code>solar</code> / <code>generator</code> / <code>none</code>') !!}</span></td></tr>
              <tr><td class="col-name">cooking_fuel</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('cooking_fuel', '<code>lpg</code> / <code>firewood</code> / <code>biogas</code> / <code>electric</code>') !!}</span></td></tr>
              <tr><td class="col-name">has_internet</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>1</code>=छ / <code>0</code>=छैन</span></td></tr>
              <tr><td class="col-name">handwashing</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('handwashing', '<code>soap_water</code> / <code>water_only</code> / <code>none</code>') !!}</span></td></tr>
              <tr><td class="col-name">has_own_vehicle</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>yes</code> / <code>no</code></span></td></tr>
              <tr><td class="col-name">vehicles_bike</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">मोटरसाइकल/स्कुटर संख्या</span></td></tr>
              <tr><td class="col-name">vehicles_car</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">कार/जिप संख्या</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Tab 5: Economic --}}
      <div class="sheet-card">
        <div class="sheet-card-header" style="background:#92400e;color:#fff;">
          <span class="tab-name">Economic</span>
          <span>आर्थिक विवरण <span class="subtitle">— one row per household</span></span>
        </div>
        <div class="sheet-card-body">
          <table class="col-table">
            <thead><tr><th>Column Name</th><th class="col-req">चाहिन्छ?</th><th>विवरण / Description</th></tr></thead>
            <tbody>
              <tr><td class="col-name">household_code</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">Households शिटसँग मिल्नु पर्छ</span></td></tr>
              <tr><td class="col-name">primary_income_source</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('primary_income_source', '<code>agriculture</code> / <code>business</code> / <code>service</code> / <code>remittance</code> / <code>daily_wage</code>') !!}</span></td></tr>
              <tr><td class="col-name">annual_income_range</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('annual_income_range', '<code>below_50000</code> / <code>50000_100000</code> / <code>100000_200000</code> / <code>above_500000</code>') !!}</span></td></tr>
              <tr><td class="col-name">has_bank_account</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>1</code>=छ / <code>0</code>=छैन</span></td></tr>
              <tr><td class="col-name">poverty_card</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>1</code>=छ / <code>0</code>=छैन (गरिबी परिचय पत्र)</span></td></tr>
              <tr><td class="col-name">has_foreign_employment</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>1</code>=छ / <code>0</code>=छैन</span></td></tr>
              <tr><td class="col-name">remittance_amount</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">वार्षिक रेमिट्यान्स रु. (नभए <code>0</code>)</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Tab 6: Agriculture --}}
      <div class="sheet-card">
        <div class="sheet-card-header" style="background:#166534;color:#fff;">
          <span class="tab-name">Agriculture</span>
          <span>कृषि विवरण <span class="subtitle">— one row per household</span></span>
        </div>
        <div class="sheet-card-body">
          <table class="col-table">
            <thead><tr><th>Column Name</th><th class="col-req">चाहिन्छ?</th><th>विवरण / Description</th></tr></thead>
            <tbody>
              <tr><td class="col-name">household_code</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">Households शिटसँग मिल्नु पर्छ</span></td></tr>
              <tr><td class="col-name">agriculture</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>1</code>=कृषि गर्छ / <code>0</code>=गर्दैन</span></td></tr>
              <tr><td class="col-name">owns_land</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>1</code>=जग्गा छ / <code>0</code>=छैन</span></td></tr>
              <tr><td class="col-name">land_area_ropani</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">जम्मा जग्गा क्षेत्रफल (रोपनीमा)</span></td></tr>
              <tr><td class="col-name">agri_categories</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">अल्पविराम (comma) ले छुट्याएर: <code>food_crops,vegetables,cash_crops</code></span></td></tr>
              <tr><td class="col-name">annual_agri_income</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">वार्षिक कृषि आय रु.</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Tab 7: Livestock --}}
      <div class="sheet-card">
        <div class="sheet-card-header" style="background:#92400e;color:#fff;">
          <span class="tab-name">Livestock</span>
          <span>पशुपालन <span class="subtitle">— one row per livestock type per household</span></span>
        </div>
        <div class="sheet-card-body">
          <div class="callout amber" style="margin-bottom:8px;font-size:11px;">
            एउटा घरधुरीमा एक प्रकारको पशु एउटा पङ्क्तिमा। मौरीपालन र माछापालन पनि यही शिटमा।<br>
            <em>One row per livestock type. Beekeeping and fishery data also goes here.</em>
          </div>
          <table class="col-table">
            <thead><tr><th>Column Name</th><th class="col-req">चाहिन्छ?</th><th>विवरण / Description</th></tr></thead>
            <tbody>
              <tr><td class="col-name">household_code</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">Households शिटसँग मिल्नु पर्छ</span></td></tr>
              <tr><td class="col-name">livestock_type</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">{!! $optVals('livestock_kind', '<code>cow</code> / <code>buffalo</code> / <code>goat</code> / <code>sheep</code> / <code>pig</code> / <code>poultry</code> / <code>yak</code> / <code>other</code>') !!}</span></td></tr>
              <tr><td class="col-name">count</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">पशुको संख्या</span></td></tr>
              <tr><td class="col-name">vet_service_access</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>1</code>=पशु सेवा छ / <code>0</code>=छैन</span></td></tr>
              <tr><td class="col-name">has_beekeeping</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>1</code>=मौरीपालन छ (घरधुरीमा एकपटक मात्र भर्नुस्)</span></td></tr>
              <tr><td class="col-name">has_fishery</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>1</code>=माछापालन छ (घरधुरीमा एकपटक मात्र भर्नुस्)</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Tab 8: LandParcels --}}
      <div class="sheet-card">
        <div class="sheet-card-header" style="background:#b45309;color:#fff;">
          <span class="tab-name">LandParcels</span>
          <span>जग्गा पार्सेल विवरण <span class="subtitle">— one row per parcel per household</span></span>
        </div>
        <div class="sheet-card-body">
          <div class="callout amber" style="margin-bottom:8px;font-size:11px;">
            पालिकाभित्र घरधुरीको प्रत्येक जग्गा खण्डको एउटा पङ्क्ति। Housing शिटमा <code>has_land_in_municipality=1</code> भर्नुहोस् र यहाँ विवरण थप्नुहोस्।<br>
            <em>One row per land parcel. Set <code>has_land_in_municipality=1</code> in the Housing sheet, then list parcels here.</em>
          </div>
          <table class="col-table">
            <thead><tr><th>Column Name</th><th class="col-req">चाहिन्छ?</th><th>विवरण / Description</th></tr></thead>
            <tbody>
              <tr><td class="col-name">household_code</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">Households शिटसँग मिल्नु पर्छ</span></td></tr>
              <tr><td class="col-name">land_use_type</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">{!! $optVals('land_use_type', '<code>khet</code> / <code>bari</code> / <code>pakho</code> / <code>residential</code> / <code>industrial</code>') !!}</span></td></tr>
              <tr><td class="col-name">land_unit</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">{!! $optVals('land_measurement_unit', '<code>ropani</code> / <code>ana</code> / <code>bigha</code> / <code>kattha</code> / <code>dhur</code>') !!}</span></td></tr>
              <tr><td class="col-name">quantity</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">परिमाण — दशमलव मान्य (e.g. <code>2.5</code>)</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>{{-- end sheet-grid --}}
  </div>

  {{-- ══ SECTION 4: INSTITUTION SHEETS ══ --}}
  <div class="section">
    <div class="section-title" style="background:#3730a3;">
      ४. संस्था — Sheet संरचना <span class="en">Institution Sheet Structure (7 Tabs)</span>
    </div>

    <div class="callout blue">
      संस्था Excel फाइलमा पनि <strong>७ ट्याब</strong> हुनु पर्छ। संस्था प्रकार अनुसार सबै ट्याब नभरे पनि हुन्छ।<br>
      <em>Institution file also needs 7 tabs. Not all tabs need data — only fill what applies to the institution type.</em>
    </div>

    <div class="sheet-grid">

      {{-- Tab 1: Institutions --}}
      <div class="sheet-card">
        <div class="sheet-card-header" style="background:#1e40af;color:#fff;">
          <span class="tab-name">Institutions</span>
          <span>मुख्य संस्था विवरण <span class="subtitle">— Main Institution Info</span></span>
        </div>
        <div class="sheet-card-body">
          <table class="col-table">
            <thead><tr><th>Column Name</th><th class="col-req">चाहिन्छ?</th><th>विवरण / Description</th></tr></thead>
            <tbody>
              <tr><td class="col-name">institution_code</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">Auto-generated if blank (e.g. <code>IN-0001-W01-00001</code>)</span></td></tr>
              <tr><td class="col-name">institution_type</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint"><code>school</code> / <code>health_post</code> / <code>hospital</code> / <code>ngo</code> / <code>cooperative</code> / <code>bank</code></span></td></tr>
              <tr><td class="col-name">ward_number</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">वडा नम्बर</span></td></tr>
              <tr><td class="col-name">name_np</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">संस्थाको नेपाली नाम</span></td></tr>
              <tr><td class="col-name">name_en</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">संस्थाको अंग्रेजी नाम</span></td></tr>
              <tr><td class="col-name">ownership_type</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>govt</code> / <code>private</code> / <code>community</code> / <code>ngo</code> / <code>cooperative</code></span></td></tr>
              <tr><td class="col-name">settlement_name</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">टोल/बस्तीको नाम</span></td></tr>
              <tr><td class="col-name">survey_date</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">सर्वेक्षण मिति — <code>YYYY-MM-DD</code></span></td></tr>
              <tr><td class="col-name">gps_lat / gps_lng</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">GPS निर्देशाङ्क</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Tab 2: Infrastructure --}}
      <div class="sheet-card">
        <div class="sheet-card-header" style="background:#6d28d9;color:#fff;">
          <span class="tab-name">Infrastructure</span>
          <span>भौतिक संरचना <span class="subtitle">— one row per institution</span></span>
        </div>
        <div class="sheet-card-body">
          <table class="col-table">
            <thead><tr><th>Column Name</th><th class="col-req">चाहिन्छ?</th><th>विवरण / Description</th></tr></thead>
            <tbody>
              <tr><td class="col-name">institution_code</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">Institutions शिटसँग मिल्नु पर्छ</span></td></tr>
              <tr><td class="col-name">building_type</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>concrete</code> / <code>tin</code> / <code>bamboo</code> / <code>brick</code> / <code>stone</code> / <code>wood</code></span></td></tr>
              <tr><td class="col-name">building_condition</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>good</code> / <code>average</code> / <code>poor</code> / <code>dilapidated</code></span></td></tr>
              <tr><td class="col-name">total_rooms</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">कुल कोठा संख्या</span></td></tr>
              <tr><td class="col-name">has_boundary_wall</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>yes</code> / <code>no</code></span></td></tr>
              <tr><td class="col-name">drinking_water</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>yes</code> / <code>no</code></span></td></tr>
              <tr><td class="col-name">electricity</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>yes</code> / <code>no</code></span></td></tr>
              <tr><td class="col-name">earthquake_safe</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>yes</code> / <code>no</code></span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Tab 3: Staff --}}
      <div class="sheet-card">
        <div class="sheet-card-header" style="background:#065f46;color:#fff;">
          <span class="tab-name">Staff</span>
          <span>कर्मचारीहरू <span class="subtitle">— one row per staff category per institution</span></span>
        </div>
        <div class="sheet-card-body">
          <table class="col-table">
            <thead><tr><th>Column Name</th><th class="col-req">चाहिन्छ?</th><th>विवरण / Description</th></tr></thead>
            <tbody>
              <tr><td class="col-name">institution_code</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">Institutions शिटसँग मिल्नु पर्छ</span></td></tr>
              <tr><td class="col-name">staff_category</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint"><code>teacher</code> / <code>health_worker</code> / <code>admin</code> / <code>support</code> / <code>security</code></span></td></tr>
              <tr><td class="col-name">male_count</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">पुरुष संख्या</span></td></tr>
              <tr><td class="col-name">female_count</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">महिला संख्या</span></td></tr>
              <tr><td class="col-name">permanent_count</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">स्थायी संख्या</span></td></tr>
              <tr><td class="col-name">contract_count</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">करार संख्या</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Tab 4: Education --}}
      <div class="sheet-card">
        <div class="sheet-card-header" style="background:#92400e;color:#fff;">
          <span class="tab-name">Education</span>
          <span>शैक्षिक विवरण <span class="subtitle">— schools only, one row per grade</span></span>
        </div>
        <div class="sheet-card-body">
          <table class="col-table">
            <thead><tr><th>Column Name</th><th class="col-req">चाहिन्छ?</th><th>विवरण / Description</th></tr></thead>
            <tbody>
              <tr><td class="col-name">institution_code</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">Institutions शिटसँग मिल्नु पर्छ</span></td></tr>
              <tr><td class="col-name">grade</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint"><code>ecd</code> / <code>grade_1</code> / <code>grade_2</code> … <code>grade_12</code></span></td></tr>
              <tr><td class="col-name">male_students</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">छात्र संख्या</span></td></tr>
              <tr><td class="col-name">female_students</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">छात्रा संख्या</span></td></tr>
              <tr><td class="col-name">dropout_count</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">विद्यालय छाड्नेहरू संख्या</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Tab 5: Health --}}
      <div class="sheet-card">
        <div class="sheet-card-header" style="background:#9f1239;color:#fff;">
          <span class="tab-name">Health</span>
          <span>स्वास्थ्य विवरण <span class="subtitle">— health institutions only, one row per institution</span></span>
        </div>
        <div class="sheet-card-body">
          <table class="col-table">
            <thead><tr><th>Column Name</th><th class="col-req">चाहिन्छ?</th><th>विवरण / Description</th></tr></thead>
            <tbody>
              <tr><td class="col-name">institution_code</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">Institutions शिटसँग मिल्नु पर्छ</span></td></tr>
              <tr><td class="col-name">bed_count</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">शैयाहरूको संख्या</span></td></tr>
              <tr><td class="col-name">delivery_service</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>yes</code> / <code>no</code> — प्रसूति सेवा</span></td></tr>
              <tr><td class="col-name">emergency_service</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>yes</code> / <code>no</code> — आपतकालीन सेवा</span></td></tr>
              <tr><td class="col-name">monthly_outpatient_avg</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">मासिक औसत OPD संख्या</span></td></tr>
              <tr><td class="col-name">vaccine_service</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>yes</code> / <code>no</code> — खोप सेवा</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Tab 6: Services --}}
      <div class="sheet-card">
        <div class="sheet-card-header" style="background:#0369a1;color:#fff;">
          <span class="tab-name">Services</span>
          <span>सेवाहरू <span class="subtitle">— one row per service per institution</span></span>
        </div>
        <div class="sheet-card-body">
          <table class="col-table">
            <thead><tr><th>Column Name</th><th class="col-req">चाहिन्छ?</th><th>विवरण / Description</th></tr></thead>
            <tbody>
              <tr><td class="col-name">institution_code</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">Institutions शिटसँग मिल्नु पर्छ</span></td></tr>
              <tr><td class="col-name">service_name</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">सेवाको नाम (e.g. <code>Primary Education</code>)</span></td></tr>
              <tr><td class="col-name">service_type</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>education</code> / <code>health</code> / <code>financial</code> / <code>social</code> / <code>agriculture</code></span></td></tr>
              <tr><td class="col-name">is_free</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>yes</code>=निःशुल्क / <code>no</code>=शुल्क लाग्छ</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Tab 7: Teachers --}}
      <div class="sheet-card">
        <div class="sheet-card-header" style="background:#3730a3;color:#fff;">
          <span class="tab-name">Teachers</span>
          <span>शिक्षकहरू <span class="subtitle">— schools only, one row per teacher</span></span>
        </div>
        <div class="sheet-card-body">
          <table class="col-table">
            <thead><tr><th>Column Name</th><th class="col-req">चाहिन्छ?</th><th>विवरण / Description</th></tr></thead>
            <tbody>
              <tr><td class="col-name">institution_code</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">Institutions शिटसँग मिल्नु पर्छ</span></td></tr>
              <tr><td class="col-name">full_name</td><td class="col-req"><span class="req-dot">●</span></td><td><span class="val-hint">शिक्षकको पूरा नाम</span></td></tr>
              <tr><td class="col-name">gender</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>male</code> / <code>female</code> / <code>other</code></span></td></tr>
              <tr><td class="col-name">category</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>permanent</code> / <code>temporary</code> / <code>contract</code> / <code>relief</code></span></td></tr>
              <tr><td class="col-name">qualification</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint"><code>bachelor</code> / <code>master</code> / <code>phd</code></span></td></tr>
              <tr><td class="col-name">subjects</td><td class="col-req"><span class="opt-dot">○</span></td><td><span class="val-hint">pipe-separated: <code>math|science|nepali</code></span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>{{-- end sheet-grid --}}
  </div>

  {{-- ══ SECTION 5: COMMON ERRORS ══ --}}
  <div class="section">
    <div class="section-title" style="background:#7f1d1d;">
      ५. सामान्य त्रुटिहरू र समाधान <span class="en">Common Errors &amp; Fixes</span>
    </div>
    <table class="err-table">
      <thead>
        <tr>
          <th style="width:35%">त्रुटि सन्देश / Error Message</th>
          <th style="width:35%">कारण / Cause</th>
          <th>समाधान / Fix</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><span class="err-code">Sheet ID कन्फिगर गरिएको छैन</span></td>
          <td>Settings मा Sheet ID थपिएको छैन</td>
          <td>Admin → Settings → Google Sheets ट्याब → Sheet ID थप्नुहोस्</td>
        </tr>
        <tr>
          <td><span class="err-code">HTTP 403 / Sheet डाउनलोड हुन सकेन</span></td>
          <td>Google Sheet private छ</td>
          <td>Sheet → Share → "Anyone with the link" → Viewer</td>
        </tr>
        <tr>
          <td><span class="err-code">unique_code / household_code खाली छ</span></td>
          <td>A column मा code नभएको row</td>
          <td>खाली row हटाउनुहोस् वा auto-generate गर्न column खाली राख्नुहोस्</td>
        </tr>
        <tr>
          <td><span class="err-code">ward_number मिलेन / 0 records found</span></td>
          <td>Sheet को ward_number र system को वडा नम्बर फरक</td>
          <td>Sheet को ward_number कलम जाँच्नुहोस् — संख्याको ढाँचा मिल्नु पर्छ</td>
        </tr>
        <tr>
          <td><span class="err-code">survey_date अमान्य</span></td>
          <td>मिति गलत ढाँचामा भरिएको</td>
          <td>मिति ढाँचा: <code>YYYY-MM-DD</code> (e.g. <code>2024-05-10</code>)</td>
        </tr>
        <tr>
          <td><span class="err-code">GPS अमान्य / GPS null</span></td>
          <td>Nepal को सीमाबाहिरको GPS वा अक्षर भरिएको</td>
          <td>Latitude: <code>26–30.5</code>, Longitude: <code>80–88.5</code> मात्र मान्य</td>
        </tr>
        <tr>
          <td><span class="err-code">cURL error 60: SSL certificate</span></td>
          <td>Local/dev environment मा SSL certificate मान्य छैन</td>
          <td>Production server मा सिंक गर्नुहोस् — local मा SSL issue सामान्य हो</td>
        </tr>
        <tr>
          <td><span class="err-code">Maximum execution time exceeded</span></td>
          <td>Sheet धेरै ठूलो / server slow</td>
          <td>एक पटकमा एउटा वडाको डेटा मात्र सिंक गर्नुहोस्</td>
        </tr>
        <tr>
          <td><span class="err-code">head_gender आवश्यक छ</span></td>
          <td>Households शिटमा head_gender खाली</td>
          <td><code>male</code> / <code>female</code> / <code>other</code> भर्नुहोस्</td>
        </tr>
      </tbody>
    </table>
  </div>

  {{-- ══ SECTION 6: STEP BY STEP ══ --}}
  <div class="section">
    <div class="section-title" style="background:#1e3a5f;">
      ६. चरण-दर-चरण सिंक प्रक्रिया <span class="en">Step-by-Step Sync Process</span>
    </div>

    <ol class="steps">
      <li>
        <div>
          <strong>Google Sheet तयार गर्नुहोस् / Prepare Google Sheet</strong><br>
          <span class="val-hint">सबै ८ ट्याब (घरधुरी) / ७ ट्याब (संस्था) सही नामले बनाउनुहोस्। row 1 = column headers, row 2 = hints (optional), row 3 देखि डेटा।</span>
        </div>
      </li>
      <li>
        <div>
          <strong>Sheet "Anyone with link" मा share गर्नुहोस् / Share the Sheet</strong><br>
          <span class="val-hint">Share → Change to "Anyone with the link" → Viewer।</span>
        </div>
      </li>
      <li>
        <div>
          <strong>Settings मा Sheet ID राख्नुहोस् / Add Sheet ID in Settings</strong><br>
          <span class="val-hint">Admin → Settings → Google Sheets → Household Sheet ID वा Institution Sheet ID भर्नुहोस्।</span>
        </div>
      </li>
      <li>
        <div>
          <strong>Google Sync पृष्ठ खोल्नुहोस् / Open Google Sync Page</strong><br>
          <span class="val-hint">Admin → Google Sync → Data Type छान्नुहोस् (घरधुरी वा संस्था) → वडा छान्नुहोस्।</span>
        </div>
      </li>
      <li>
        <div>
          <strong>"Preview" थिच्नुहोस् / Click Preview</strong><br>
          <span class="val-hint">नयाँ, परिवर्तित र त्रुटि भएका row देखाउँछ। पूर्णता बैज (completeness badges) जाँच्नुहोस्।</span>
        </div>
      </li>
      <li>
        <div>
          <strong>त्रुटिहरू जाँच्नुहोस् र Sheet सच्याउनुहोस् / Review Errors &amp; Fix Sheet</strong><br>
          <span class="val-hint">त्रुटि भएका row को कारण हेर्नुहोस् → Google Sheet सच्याउनुहोस् → फेरि Preview गर्नुहोस्।</span>
        </div>
      </li>
      <li>
        <div>
          <strong>"Apply Sync" थिच्नुहोस् / Click Apply Sync</strong><br>
          <span class="val-hint">सबै नयाँ र परिवर्तित row प्रणालीमा सुरक्षित हुन्छन्। Status: <strong>Submitted</strong> सेट हुन्छ।</span>
        </div>
      </li>
    </ol>

    <div class="callout red" style="margin-top:14px;">
      <strong>⚠ महत्त्वपूर्ण / Important</strong>
      Apply पछि रेकर्डहरू "Submitted" अवस्थामा जान्छन् — Ward Reviewer ले जाँच गरी Approve गर्नु पर्छ।<br>
      <em>After applying, records are in "Submitted" state — they must be reviewed and approved by the Ward Reviewer.</em>
    </div>
  </div>

  {{-- ══ FOOTER ══ --}}
  <div class="manual-footer">
    स्थानीय तह वस्तुगत विवरण तथा विश्लेषण प्रणाली — Google Sync Manual v1.7 — {{ date('Y-m-d') }}<br>
    Admin → Google Sync: <strong>{{ url('/admin/google-sync') }}</strong>
  </div>

</div>
</body>
</html>
