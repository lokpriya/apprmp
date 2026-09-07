<!DOCTYPE html>
<html lang="ne">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>नगरपालिका प्रशासक निर्देशिका — User Manual</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', 'Noto Sans Devanagari', Arial, sans-serif;
    font-size: 12px; line-height: 1.65; color: #1e293b;
    background: #f8fafc; padding: 24px;
  }
  a { color: #2563eb; text-decoration: none; }

  .page-wrap  { max-width: 1000px; margin: 0 auto; }

  /* ── Cover ── */
  .cover {
    background: linear-gradient(135deg, #182433 0%, #1e3a5f 100%);
    color: #fff; border-radius: 10px; padding: 36px 40px 28px;
    margin-bottom: 28px;
  }
  .cover h1   { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
  .cover h1 span { font-size: 14px; font-weight: 400; opacity: .7; display: block; margin-top: 3px; }
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

  /* ── TOC ── */
  .toc { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px; margin-bottom: 28px; }
  .toc h2 { font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 10px; }
  .toc ol { padding-left: 20px; }
  .toc li { font-size: 12px; color: #334155; margin-bottom: 4px; }
  .toc a { color: #2563eb; }
  .toc a:hover { text-decoration: underline; }

  /* ── Section headers ── */
  .section { margin-bottom: 28px; }
  .section-title {
    display: flex; align-items: center; gap: 8px;
    font-size: 14px; font-weight: 700; color: #fff;
    padding: 9px 14px; border-radius: 6px; margin-bottom: 14px;
    scroll-margin-top: 20px;
  }
  .section-title .en { font-size: 11px; font-weight: 400; opacity: .75; margin-left: 4px; }
  .section-title .num {
    background: rgba(255,255,255,.25); min-width: 24px; height: 24px;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; flex-shrink: 0;
  }

  /* ── Sub-section ── */
  .subsection { margin-bottom: 16px; }
  .subsection-title {
    font-size: 12px; font-weight: 700; color: #1e40af;
    border-left: 3px solid #2563eb; padding-left: 8px; margin-bottom: 8px;
  }

  /* ── Callout boxes ── */
  .callout {
    border-left: 4px solid; padding: 10px 14px;
    border-radius: 0 6px 6px 0; margin-bottom: 12px; font-size: 11.5px;
  }
  .callout.blue  { background: #eff6ff; border-color: #2563eb; color: #1e40af; }
  .callout.green { background: #f0fdf4; border-color: #16a34a; color: #14532d; }
  .callout.red   { background: #fef2f2; border-color: #dc2626; color: #7f1d1d; }
  .callout.amber { background: #fffbeb; border-color: #d97706; color: #78350f; }
  .callout.gray  { background: #f8fafc; border-color: #94a3b8; color: #334155; }
  .callout strong { display: block; margin-bottom: 3px; }

  /* ── Steps ── */
  .steps { counter-reset: step; list-style: none; padding: 0; }
  .steps li {
    counter-increment: step; display: flex; gap: 12px;
    margin-bottom: 9px; align-items: flex-start;
  }
  .steps li::before {
    content: counter(step); min-width: 24px; height: 24px;
    background: #2563eb; color: #fff; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 11px; flex-shrink: 0; margin-top: 1px;
  }

  /* ── Bullet list ── */
  .bul { list-style: none; padding: 0; }
  .bul li { padding-left: 16px; position: relative; margin-bottom: 5px; font-size: 11.5px; }
  .bul li::before { content: '•'; position: absolute; left: 4px; color: #2563eb; font-weight: 700; }

  /* ── Info table ── */
  .info-table { width: 100%; border-collapse: collapse; font-size: 11.5px; margin-bottom: 12px; }
  .info-table th {
    background: #f1f5f9; color: #475569; font-weight: 600; font-size: 10.5px;
    text-transform: uppercase; padding: 6px 10px; text-align: left;
    border-bottom: 1px solid #e2e8f0;
  }
  .info-table td { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
  .info-table tr:last-child td { border-bottom: none; }
  .info-table tr:hover td { background: #f8fafc; }
  .info-table .label-col { font-weight: 600; color: #334155; white-space: nowrap; width: 30%; }
  code { background: #f1f5f9; padding: 1px 5px; border-radius: 3px; font-size: 10.5px; color: #334155; font-family: monospace; }

  /* ── Role badges ── */
  .role-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
  .role-card { border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; }
  .role-card-header { padding: 7px 12px; font-weight: 700; font-size: 11.5px; color: #fff; }
  .role-card-body { padding: 8px 12px; font-size: 11px; color: #334155; background: #fff; }
  .role-card-body li { margin-bottom: 3px; list-style: disc; margin-left: 14px; }

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

  /* ── Status badges ── */
  .status-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; align-items: flex-start; }
  .status-badge {
    padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
    display: inline-flex; align-items: center; gap: 4px;
  }
  .s-draft    { background: #f1f5f9; color: #475569; }
  .s-sub      { background: #fef9c3; color: #854d0e; }
  .s-approved { background: #e0f2fe; color: #0369a1; }
  .s-pub      { background: #dcfce7; color: #166534; }
  .s-rejected { background: #fee2e2; color: #991b1b; }

  /* ── Settings tab list ── */
  .tab-list { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; }
  .tab-item { border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; background: #fff; }
  .tab-item .tab-name { font-weight: 700; font-size: 11.5px; color: #1d273b; }
  .tab-item .tab-desc { font-size: 11px; color: #64748b; margin-top: 2px; }

  /* ── Footer ── */
  .manual-footer {
    margin-top: 32px; padding-top: 14px; border-top: 1px solid #e2e8f0;
    text-align: center; font-size: 10.5px; color: #94a3b8;
  }

  /* ── Print styles ── */
  @media print {
    body { background: #fff; padding: 0; font-size: 11px; }
    .no-print { display: none !important; }
    .section, .callout, .role-card, .tab-item { break-inside: avoid; }
    .section-title { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .role-card-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .status-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .cover { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .print-btn { display: none; }
    a { color: inherit; }
  }
</style>
</head>
<body>
<div class="page-wrap">

  {{-- ══ COVER ══ --}}
  <div class="cover">
    <h1>
      नगरपालिका प्रशासक निर्देशिका
      <span>तपाईंको दैनिक काममा साथ दिने सहायक पुस्तिका · Municipal Admin User Manual</span>
    </h1>
    <div class="cover-meta">
      <span class="badge">👤 भूमिका: नगरपालिका प्रशासक</span>
      <span class="badge">🏛️ स्थानीय तह वस्तुगत विवरण तथा विश्लेषण प्रणाली</span>
      <span class="badge">📅 संस्करण {{ date('Y-m') }}</span>
    </div>
    <div class="no-print" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
      <button class="print-btn" onclick="window.print()">
        🖨 PDF सुरक्षित गर्नुहोस्
      </button>
      <button class="print-btn" style="background:#217346;" onclick="downloadDoc()">
        📄 Word फाइल डाउनलोड
      </button>
    </div>
  </div>

  {{-- ══ TABLE OF CONTENTS ══ --}}
  <div class="toc no-print">
    <h2>विषय सूची — Contents</h2>
    <ol>
      <li><a href="#s1">परिचय तथा भूमिकाहरू — Overview &amp; Roles</a></li>
      <li><a href="#s2">लगइन तथा सुरक्षा — Login &amp; Security</a></li>
      <li><a href="#s3">ड्यासबोर्ड — Dashboard</a></li>
      <li><a href="#s4">घरधुरी व्यवस्थापन — Household Management</a></li>
      <li><a href="#s5">संस्था व्यवस्थापन — Institution Management</a></li>
      <li><a href="#s6">व्यवसाय व्यवस्थापन — Business Management</a></li>
      <li><a href="#s7">समीक्षा तथा प्रकाशन — Review &amp; Publish</a></li>
      <li><a href="#s8">बल्क इम्पोर्ट — Bulk Import</a></li>
      <li><a href="#s9">गूगल सिंक — Google Sheets Sync</a></li>
      <li><a href="#s10">सेटिङ — Settings</a></li>
      <li><a href="#s11">प्रयोगकर्ता व्यवस्थापन — User Management</a></li>
      <li><a href="#s12">सार्वजनिक प्रतिवेदन — Public Reports</a></li>
      <li><a href="#s13">लाइसेन्स तथा मर्मत मोड — License &amp; Maintenance</a></li>
      <li><a href="#s14">निर्यात तथा डाउनलोड सुविधाहरू — Export &amp; Download</a></li>
    </ol>
  </div>

  {{-- ══ SECTION 1: OVERVIEW & ROLES ══ --}}
  <div class="section" id="s1">
    <div class="section-title" style="background:#1e3a5f;">
      <span class="num">१</span>
      परिचय तथा भूमिकाहरू
      <span class="en">Overview &amp; Roles</span>
    </div>

    <div class="callout blue">
      <strong>सुरुमा एउटा कुरा बुझौं — यो प्रणाली किन बनाइएको हो?</strong>
      तपाईंको नगरपालिकाको हरेक घरधुरी, विद्यालय, स्वास्थ्य चौकी र व्यवसायको तथ्याङ्क एकै ठाउँमा राख्न, जाँच्न र सर्वसाधारणसामु पारदर्शी रूपमा राख्नका लागि यो प्रणाली बनाइएको हो।
      सर्वेक्षणकर्ताले घर-घर गएर डेटा भर्छन्, वडा समीक्षकले त्यसलाई जाँच्छन्, र अन्तमा तपाईंले प्रकाशित गर्नुहुन्छ — त्यसपछि मात्र त्यो डेटा सार्वजनिक प्रतिवेदनमा देखिन्छ।<br>
      <em>This system brings all household, institution, and business data together in one place — collected by enumerators, checked by ward reviewers, and published by you. Only published data reaches the public.</em>
    </div>

    <div class="subsection">
      <div class="subsection-title">को कसले के गर्छ? / Who Does What?</div>
      <div class="role-grid">
        <div class="role-card">
          <div class="role-card-header" style="background:#1e40af;">municipal_admin — नगर प्रशासक (तपाईं)</div>
          <div class="role-card-body">
            <ul>
              <li>आफ्नो नगरपालिकाभित्र सबै काम गर्न सक्नुहुन्छ</li>
              <li>टीम (प्रयोगकर्ता) थप्ने र व्यवस्थापन गर्ने</li>
              <li>सेटिङ, सार्वजनिक सामग्री र तस्वीरहरू व्यवस्थापन</li>
              <li>डेटा प्रकाशित गर्ने र बल्क कार्यहरू गर्ने</li>
            </ul>
          </div>
        </div>
        <div class="role-card">
          <div class="role-card-header" style="background:#0369a1;">ward_reviewer — वडा समीक्षक</div>
          <div class="role-card-body">
            <ul>
              <li>आफूलाई तोकिएका वडाका रेकर्ड मात्र देख्छन्</li>
              <li>सर्वेक्षणकर्ताको काम जाँचेर स्वीकृत वा फिर्ता गर्छन्</li>
              <li>गल्ती भेटिएमा प्रकाशित रेकर्डमा सम्पादन अनुरोध पठाउन सक्छन्</li>
            </ul>
          </div>
        </div>
        <div class="role-card">
          <div class="role-card-header" style="background:#166534;">enumerator — सर्वेक्षणकर्ता</div>
          <div class="role-card-body">
            <ul>
              <li>घर-घर गएर घरधुरी, संस्था र व्यवसाय दर्ता गर्छन्</li>
              <li>Draft सम्पादन गरेर वडा समीक्षककहाँ पेश गर्छन्</li>
              <li>तोकिएको वडामा मात्र काम गर्न पाउँछन्</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="subsection">
      <div class="subsection-title">डेटा कसरी अघि बढ्छ? / The Data Journey</div>
      <div class="process">
        <div class="process-step">
          <span class="ps-num">✏️</span>
          <div class="ps-ne">Draft</div>
          <div class="ps-en">Enumerator fills form</div>
        </div>
        <div class="process-step">
          <span class="ps-num">📤</span>
          <div class="ps-ne">Submitted</div>
          <div class="ps-en">Sent for ward review</div>
        </div>
        <div class="process-step">
          <span class="ps-num">✅</span>
          <div class="ps-ne">Approved</div>
          <div class="ps-en">Ward reviewer okays it</div>
        </div>
        <div class="process-step">
          <span class="ps-num">🌐</span>
          <div class="ps-ne">Published</div>
          <div class="ps-en">You publish — now it's live!</div>
        </div>
      </div>
      <div class="callout gray">
        रेकर्डको रङ हेरेर कहाँ छ थाहा पाउन सकिन्छ। सूचीमा सबैतिर यी रङ-संकेत प्रयोग हुन्छन्:<br><br>
        <div class="status-row">
          <span class="status-badge s-draft">● Draft — भरिँदैछ</span>
          <span class="status-badge s-sub">● Submitted — समीक्षा पर्खिरहेको</span>
          <span class="status-badge s-approved">● Ward Approved — प्रकाशन पर्खिरहेको</span>
          <span class="status-badge s-pub">● Published — सार्वजनिक भइसक्यो</span>
          <span class="status-badge s-rejected">● Rejected — फिर्ता पठाइएको</span>
        </div>
      </div>
    </div>

  </div>

  {{-- ══ SECTION 2: LOGIN & SECURITY ══ --}}
  <div class="section" id="s2">
    <div class="section-title" style="background:#1e3a5f;">
      <span class="num">२</span>
      लगइन तथा सुरक्षा
      <span class="en">Login &amp; Security</span>
    </div>

    <div class="subsection">
      <div class="subsection-title">पहिलो पटक लगइन गर्दा / Your First Login</div>
      <ol class="steps">
        <li>ब्राउजरमा <code>/login</code> खोल्नुहोस् र प्राप्त इमेल र अस्थायी पासवर्ड हाल्नुहोस्।</li>
        <li>भित्र पसेपछि <strong>पहिलो काम पासवर्ड बदल्ने</strong> हो — माथि दायाँ आफ्नो नाममा क्लिक → Profile → Change Password। अस्थायी पासवर्ड लामो समय नराख्नुहोस्।</li>
        <li>प्रणालीले Two-Factor Authentication (2FA) माग्छ — आफ्नो फोनमा Google Authenticator वा Authy एप खोलेर QR Code स्क्यान गर्नुहोस्। एकचोटि सेटअप भयो भने पछि सहज हुन्छ।</li>
      </ol>
    </div>

    <div class="subsection">
      <div class="subsection-title">२FA — दोहोरो सुरक्षा</div>
      <div class="callout amber">
        <strong>यो झन्झट होइन, सुरक्षा हो!</strong>
        Admin खाताको लागि 2FA अनिवार्य छ। पहिलो पटक QR Code स्क्यान गर्दा <strong>८ Recovery Code</strong> पनि देखाइन्छ — ती कागजमा लेखेर वा सुरक्षित ठाउँमा राख्नुहोस्। फोन हरायो भने वा एप डिलिट भयो भने यिनै Code प्रयोग गरेर फिर्ता लगइन गर्न सकिन्छ।<br>
        <em>2FA is required for admins. Those 8 recovery codes are your lifeline if you ever lose your phone — keep them safe.</em>
      </div>
    </div>

    <div class="subsection">
      <div class="subsection-title">Impersonation — अर्को प्रयोगकर्ताको आँखाले हेर्न</div>
      <div class="callout blue">
        <strong>समस्या छ? समीक्षकले के देख्छन् हेर्नुस्।</strong>
        कुनै प्रयोगकर्ताले "मलाई यो देखिएन" भने तपाईं आफैं उनकै अनुभव गर्न सक्नुहुन्छ — Gear → प्रयोगकर्ता → "Impersonate" बटन। काम सकिएपछि माथि देखिने <strong>"Stop Impersonating"</strong> बटन थिच्नुहोस् र तपाईंको आफ्नै खातामा फर्कनुहोस्।<br>
        <em>Use this to see exactly what another user sees. Click "Stop Impersonating" to return to your own account.</em>
      </div>
      <div class="callout red">
        <strong>ध्यान दिनुहोस् — Impersonation Stop गरेपछि पनि उनको Session चलिरहन सक्छ!</strong>
        तपाईं फर्किसक्नुभयो भनेर उनको Session स्वतः बन्द हुँदैन। उनलाई आफैं लगआउट गर्न भन्नुहोस् — वा लाइसेन्स म्याद सकिएको अवस्थामा अड्किएको छ भने उनलाई "लगआउट गरी Admin लगइन गर्नुहोस्" बटन थिच्न भन्नुहोस्।<br>
        <em>Their session doesn't end when you stop impersonating. Ask them to log out, or they can use the logout button on the expired-license page.</em>
      </div>
    </div>

    <div class="subsection">
      <div class="subsection-title">सुरक्षाका केही राम्रा बानीहरू</div>
      <ul class="bul">
        <li>अफिस कम्प्युटरमा काम सकिएपछि लगआउट गर्नु नबिर्सनुहोस् — अरूले खाता प्रयोग गर्न सक्छन्।</li>
        <li>पासवर्ड ३ महिनामा एकपटक बदल्नु राम्रो बानी हो।</li>
        <li>Recovery Code आफ्नो डायरीमा राख्नुहोस् — डिजिटल फाइलमा मात्र भर नराख्नुहोस्।</li>
        <li>कसैले पासवर्ड सोधे नदिनुहोस् — यो प्रणालीमा कोहीले पनि तपाईंको पासवर्ड कहिल्यै सोध्दैन।</li>
      </ul>
    </div>
  </div>

  {{-- ══ SECTION 3: DASHBOARD ══ --}}
  <div class="section" id="s3">
    <div class="section-title" style="background:#1e40af;">
      <span class="num">३</span>
      ड्यासबोर्ड
      <span class="en">Dashboard</span>
    </div>

    <div class="callout blue">
      लगइन गरेपछि तपाईं सिधै Admin Dashboard (<code>/admin</code>) मा पुग्नुहुन्छ। यो तपाईंको दैनिक काम सुरु हुने ठाउँ हो — एकैनजरमा सबै अवस्था थाहा पाउन सकिन्छ।<br>
      <em>This is your home base — a quick glance tells you where things stand across the whole municipality.</em>
    </div>

    <div class="subsection">
      <div class="subsection-title">माथिका संख्याहरूले के भन्छन्?</div>
      <table class="info-table">
        <thead><tr><th>कार्ड</th><th>यसको अर्थ</th></tr></thead>
        <tbody>
          <tr><td class="label-col">🏠 घरधुरी</td><td>अहिलेसम्म दर्ता भएका कुल घरधुरी — Draft देखि Published सम्म सबै गनिन्छन्।</td></tr>
          <tr><td class="label-col">👥 जनसंख्या</td><td>प्रकाशित घरधुरीका सदस्यहरूको जम्मा संख्या — यो नम्बर सार्वजनिक पेजमा पनि देखिन्छ।</td></tr>
          <tr><td class="label-col">🏢 संस्थाहरू</td><td>विद्यालय, स्वास्थ्यपोस्ट, सहकारी लगायत दर्ता भएका कुल संस्था।</td></tr>
          <tr><td class="label-col">💼 व्यवसाय</td><td>दर्ता भएका कुल व्यवसाय।</td></tr>
          <tr><td class="label-col">⏳ समीक्षा पर्खिरहेको</td><td>सर्वेक्षणकर्ताले पेश गरेका तर वडा समीक्षकको स्वीकृति नपाएका रेकर्ड। यो संख्या बढी भयो भने वडा समीक्षकलाई सम्झाउनुहोस्।</td></tr>
          <tr><td class="label-col">📋 प्रकाशन पर्खिरहेको</td><td>वडा समीक्षकले पास गरेका तर तपाईंले प्रकाशित गर्न बाँकी रेकर्ड — यो तपाईंको काम हो!</td></tr>
        </tbody>
      </table>
    </div>

    <div class="subsection">
      <div class="subsection-title">कहाँ जाने? / Getting Around</div>
      <ul class="bul">
        <li><strong>घरधुरी</strong> — घरधुरीको सूची हेर्ने, नयाँ थप्ने, वडा वा Status अनुसार खोज्ने।</li>
        <li><strong>संस्था</strong> — विद्यालय, स्वास्थ्यपोस्ट आदि संस्थाहरूको सूची र थप।</li>
        <li><strong>समीक्षा</strong> — पेश गरिएका रेकर्डहरू जाँच्ने र स्वीकृत वा फिर्ता गर्ने ठाउँ।</li>
        <li><strong>प्रकाशन</strong> — स्वीकृत रेकर्डहरू सार्वजनिक गर्ने ठाउँ।</li>
        <li><strong>⚙ Gear (माथि दायाँ)</strong> — प्रयोगकर्ता, सेटिङ, सम्पादन अनुरोध, अडिट लग र अरू उपकरणहरू।</li>
      </ul>
    </div>
  </div>

  {{-- ══ SECTION 4: HOUSEHOLDS ══ --}}
  <div class="section" id="s4">
    <div class="section-title" style="background:#1d4ed8;">
      <span class="num">४</span>
      घरधुरी व्यवस्थापन
      <span class="en">Household Management</span>
    </div>

    <div class="subsection">
      <div class="subsection-title">नयाँ घरधुरी थप्दा / Adding a Household</div>
      <ol class="steps">
        <li>साइडबारमा <strong>घरधुरी</strong> → <strong>नयाँ घरधुरी</strong> थिच्नुहोस्। फारम खुल्छ — तपाईंको ब्राउजर बन्द भयो वा नेट गयो भनेर चिन्ता नगर्नुहोस्, प्रत्येक चरण स्वतः सुरक्षित हुँदै जान्छ।</li>
        <li>११ चरण भर्नुहोस् — सबै भर्नु अनिवार्य छैन, तर जति बढी जानकारी, त्यति राम्रो विश्लेषण।</li>
        <li>अन्तिम चरणमा <strong>सबमिट</strong> थिच्नुहोस् — रेकर्ड वडा समीक्षककहाँ पुग्छ।</li>
      </ol>
      <div class="callout amber">
        <strong>GPS नभए नक्सामा देखिँदैन — यो एउटा सानो काम ठूलो फरक पार्छ।</strong>
        सर्वेक्षणकर्तालाई फिल्डमा GPS कैद गर्न भन्नुहोस्। भएन भने म्यानुअली Latitude/Longitude भर्न सकिन्छ (नेपाल: Lat 26–30.5, Lng 80–88.5)। GPS भयो भने सार्वजनिक नक्सामा वडाको choropleth chart पनि बन्छ।<br>
        <em>GPS data makes the ward map come alive. Ask enumerators to capture it in the field — it takes 3 seconds.</em>
      </div>
    </div>

    <div class="subsection">
      <div class="subsection-title">फारममा के-के छ? / The 11-Step Form at a Glance</div>
      <table class="info-table">
        <thead><tr><th>चरण</th><th>विषय</th><th>भरिने जानकारी</th></tr></thead>
        <tbody>
          <tr><td>१</td><td>घर विवरण</td><td>वडा, टोल, GPS, घरको प्रकार र निर्माण सामग्री</td></tr>
          <tr><td>२</td><td>परिवार तथा सामाजिक</td><td>घरमुली, जाति, भाषा, ऋण, मृत्यु, बसाइँसराइको इतिहास</td></tr>
          <tr><td>३</td><td>सरसफाई तथा सुविधा</td><td>खानेपानी, शौचालय, बिजुली, खाना पकाउने इन्धन, इन्टरनेट</td></tr>
          <tr><td>४</td><td>यातायात र निर्णय</td><td>सवारी साधन, घरायसी निर्णय महिला वा पुरुष कसले गर्छन्</td></tr>
          <tr><td>५</td><td>खेतीपाती</td><td>बाली प्रकार, मल प्रयोग, चुनौतीहरू</td></tr>
          <tr><td>६</td><td>कृषि उत्पादन</td><td>१६ बाली — क्षेत्रफल, उत्पादन, बिक्री र बीउको विवरण</td></tr>
          <tr><td>७</td><td>पशुपालन</td><td>पशु प्रकार, संख्या, पशु बीमा र पशु सेवाको पहुँच</td></tr>
          <tr><td>८</td><td>मौरी/माछा/रेशम</td><td>मौरीपालन, माछापालन — घार संख्या, उत्पादन र आय</td></tr>
          <tr><td>९</td><td>आम्दानी र खर्च</td><td>आय स्रोत, वार्षिक आय/खर्च, ऋण, बैंक खाता</td></tr>
          <tr><td>१०</td><td>प्राकृतिक प्रकोप</td><td>जोखिम, पहिले घटेका विपद्, तयारी र क्षति विवरण</td></tr>
          <tr><td>११</td><td>परिवार सदस्य</td><td>प्रत्येक सदस्यको पूरा विवरण — उमेर, शिक्षा, पेशा, कागजात</td></tr>
        </tbody>
      </table>
    </div>

    <div class="subsection">
      <div class="subsection-title">खोज्ने र फिल्टर गर्ने</div>
      <ul class="bul">
        <li>घरमुलीको नाम, Household Code वा टोलको नाम टाइप गरेर खोज्न सकिन्छ।</li>
        <li>वडा नम्बर, Status (Draft/Submitted/Published...) र टोल अनुसार फिल्टर गर्न सकिन्छ।</li>
        <li>प्रत्येक रेकर्डमा View 👁, Edit ✏️ र Delete 🗑 विकल्प छ।</li>
      </ul>
    </div>

    <div class="subsection">
      <div class="subsection-title">एकैपटक धेरै रेकर्ड पास गर्ने / Bulk Actions</div>
      <div class="callout green">
        <strong>समय बचाउने उपाय — Bulk Select गर्नुहोस्!</strong>
        सूचीको बायाँ तर्फ चेकबक्स छन् — एउटा-एउटा हैन, धेरैलाई छानेर एकैपटक Submit / Approve / Reject गर्न सकिन्छ। सर्वेक्षण सकिएपछि सयौं रेकर्ड एकैसाथ प्रकाशनमा पठाउन यो बहुत उपयोगी छ।<br>
        <em>Use the checkboxes to select many records and bulk-submit, approve, or reject them all at once — a huge time-saver after a field campaign.</em>
      </div>
    </div>

    <div class="subsection">
      <div class="subsection-title">व्यक्तिगत सदस्य प्रोफाइल / Individual Member Profile</div>
      <ul class="bul">
        <li>घरधुरीको विवरण पेजमा सदस्यको नाममा क्लिक गर्नुहोस् — एउटा सफा Resume-style पेज खुल्छ।</li>
        <li>त्यहाँ सदस्यको सम्पूर्ण विवरण — पेशा, स्वास्थ्य, कागजातको स्थिति — सबै एकै ठाउँमा।</li>
        <li>Print बटन थिचेर PDF को रूपमा छाप्न वा सुरक्षित गर्न सकिन्छ।</li>
      </ul>
    </div>

    <div class="subsection">
      <div class="subsection-title">घरधुरीको PDF बनाउने / Household PDF Export</div>
      <ul class="bul">
        <li>घरधुरीको विवरण पेज खोल्नुहोस् → माथि दायाँ <strong>PDF</strong> बटन थिच्नुहोस्।</li>
        <li>सर्भरले सबै १४ खण्ड सहितको PDF तयार गर्छ र डाउनलोड हुन्छ।</li>
      </ul>
    </div>
  </div>

  {{-- ══ SECTION 5: INSTITUTIONS ══ --}}
  <div class="section" id="s5">
    <div class="section-title" style="background:#3730a3;">
      <span class="num">५</span>
      संस्था व्यवस्थापन
      <span class="en">Institution Management</span>
    </div>

    <div class="subsection">
      <div class="subsection-title">कुन-कुन संस्था दर्ता हुन्छन्?</div>
      <ul class="bul">
        <li>विद्यालय र क्याम्पस (School/College)</li>
        <li>स्वास्थ्य चौकी र अस्पताल (Health Post / Hospital)</li>
        <li>सहकारी संस्था र बैंक</li>
        <li>गैरसरकारी संस्था (NGO/INGO)</li>
        <li>अन्य सामुदायिक संस्था</li>
      </ul>
    </div>

    <div class="subsection">
      <div class="subsection-title">संस्था थप्दा / Adding an Institution</div>
      <ol class="steps">
        <li>साइडबारमा <strong>संस्था</strong> → <strong>नयाँ संस्था</strong> थिच्नुहोस्।</li>
        <li>पहिले संस्था प्रकार छान्नुहोस् — प्रकार अनुसार फारम बदलिन्छ। विद्यालयको लागि कक्षा र विद्यार्थी खण्ड देखिन्छ, स्वास्थ्यको लागि OPD र शैया संख्या देखिन्छ — भरिने कुरा आफैं मिल्दो हुन्छ।</li>
        <li>८ चरणमा भौतिक संरचना, कर्मचारी र सेवाहरूको विवरण भर्नुहोस्।</li>
      </ol>
    </div>

    <div class="callout blue">
      <strong>एउटै संस्था दोहोरो दर्ता हुन सक्छ — प्रणालीले सतर्क गराउँछ।</strong>
      दर्ता नम्बर, GPS स्थान वा नामले मिल्ने संस्था पहिले नै भेटिएमा प्रणालीले तपाईंलाई Warning देखाउँछ। साँच्चिकै नयाँ संस्था हो भने "Override" थिच्नुहोस् — होइन भने पुरानो रेकर्ड अपडेट गर्नुहोस्।<br>
      <em>The system warns you if it finds a matching institution already registered. Override only if you're sure it's a genuinely new record.</em>
    </div>
  </div>

  {{-- ══ SECTION 6: BUSINESSES ══ --}}
  <div class="section" id="s6">
    <div class="section-title" style="background:#92400e;">
      <span class="num">६</span>
      व्यवसाय व्यवस्थापन
      <span class="en">Business Management</span>
    </div>

    <div class="callout gray">
      <strong>व्यवसाय सीधै थपिँदैन — पहिले घरधुरी र सदस्य चाहिन्छ।</strong>
      यो प्रणालीमा व्यवसाय एउटा परिवार सदस्यसँग जोडिएको हुन्छ। त्यसैले थप्ने बाटो यो हो: घरधुरी खोल्नुहोस् → सम्बन्धित सदस्यको नाममा जानुहोस् → "व्यवसाय थप्नुहोस्" बटन थिच्नुहोस्।
      व्यवसायको unique code यस्तो देखिन्छ: <code>BZ-XXXX-WXX-XXXXX</code><br>
      <em>Businesses are always tied to a household member — navigate there first, then add the business from the member's profile.</em>
    </div>

    <div class="subsection">
      <div class="subsection-title">व्यवसाय समीक्षा गर्ने र प्रकाशित गर्ने</div>
      <ul class="bul">
        <li>घरधुरी जस्तै व्यवसायको पनि आफ्नै समीक्षा र प्रकाशन प्रक्रिया छ।</li>
        <li>पेश गरिएका व्यवसाय हेर्न: साइडबारमा <strong>समीक्षा → व्यवसाय</strong> ट्याब खोल्नुहोस्।</li>
        <li>प्रकाशन गर्न: <strong>प्रकाशन Queue → व्यवसाय</strong> ट्याब — वडाले पास गरेका व्यवसाय यहाँ आउँछन्।</li>
      </ul>
    </div>
  </div>

  {{-- ══ SECTION 7: REVIEW & PUBLISH ══ --}}
  <div class="section" id="s7">
    <div class="section-title" style="background:#0f766e;">
      <span class="num">७</span>
      समीक्षा तथा प्रकाशन
      <span class="en">Review &amp; Publish — The Heart of the System</span>
    </div>

    <div class="subsection">
      <div class="subsection-title">समीक्षा Queue — वडा समीक्षकको ठाउँ</div>
      <div class="callout blue">
        <strong>URL: <code>/admin/review</code></strong><br>
        सर्वेक्षणकर्ताले पेश गरेका सबै रेकर्ड यहाँ आउँछन् — घरधुरी, संस्था र व्यवसाय अलग-अलग ट्याबमा। वडा समीक्षकले प्रत्येक रेकर्ड हेरेर "स्वीकृत" वा "फिर्ता" गर्छन्। तपाईंले पनि जरुरी परे यहाँबाट सिधै काम गर्न सक्नुहुन्छ।<br>
        <em>This is where submitted records land. Ward reviewers approve or return them; you can act here too when needed.</em>
      </div>
    </div>

    <div class="subsection">
      <div class="subsection-title">प्रकाशन Queue — तपाईंको सबैभन्दा महत्त्वपूर्ण काम</div>
      <div class="callout green">
        <strong>URL: <code>/admin/publish-queue</code></strong><br>
        वडा समीक्षकले पास गरेका रेकर्ड यहाँ जम्मा हुन्छन् — सार्वजनिक हुन बस तपाईंको एउटै क्लिक चाहिन्छ। "Publish" थिच्नेबित्तिकै त्यो रेकर्ड सार्वजनिक प्रतिवेदन र विश्लेषणमा देखिन थाल्छ।
        Draft, Submitted वा Approved रेकर्ड कहिल्यै सार्वजनिक पेजमा देखिँदैन — केवल Published मात्र।<br>
        <em>One click here makes the data visible to the public. Draft, Submitted, or Approved data never reaches the public.</em>
      </div>
    </div>

    <div class="subsection">
      <div class="subsection-title">सम्पादन अनुरोध — प्रकाशित रेकर्डमा गल्ती भेटिएमा</div>
      <div class="callout amber">
        <strong>URL: <code>/admin/edit-requests</code> · माथि Bell 🔔 आइकनमा लाल नम्बर देखिन्छ</strong><br>
        प्रकाशित रेकर्डमा सम्पादन गर्न सीधै मिल्दैन — वडा समीक्षकले "सम्पादन अनुरोध" पठाउँछन् र तपाईंले निर्णय गर्नुहुन्छ।
        <strong>स्वीकृत गर्नुभयो</strong> भने सम्बन्धित सर्वेक्षणकर्ताले त्यो रेकर्ड सम्पादन गर्न पाउँछ।
        <strong>अस्वीकार गर्नुभयो</strong> भने रेकर्ड यथावत रहन्छ।<br>
        <em>Published records are locked — reviewers send edit requests, and you decide whether to unlock them.</em>
      </div>
    </div>
  </div>

  {{-- ══ SECTION 8: BULK IMPORT ══ --}}
  <div class="section" id="s8">
    <div class="section-title" style="background:#1e40af;">
      <span class="num">८</span>
      बल्क इम्पोर्ट
      <span class="en">Bulk Import — Load Data from Excel</span>
    </div>

    <div class="callout blue">
      एक-एकवटा फारम भर्न समय लाग्छ — Bulk Import ले एकैपटक सयौं रेकर्ड Excel बाट लोड गर्छ। यो सुविधा सर्वेक्षण सकिएपछि ठूलो मात्रामा डेटा प्रविष्ट गर्न अत्यन्त उपयोगी छ।<br>
      <em>Instead of entering one record at a time, upload hundreds at once from a filled Excel template.</em>
    </div>

    <table class="info-table">
      <thead><tr><th>के import गर्ने?</th><th>कहाँ जाने?</th></tr></thead>
      <tbody>
        <tr><td class="label-col">🏠 घरधुरी</td><td><code>/admin/bulk-import</code> — नीलो पेज</td></tr>
        <tr><td class="label-col">🏢 संस्था</td><td><code>/admin/institution-bulk-import</code> — Indigo पेज</td></tr>
        <tr><td class="label-col">💼 व्यवसाय</td><td><code>/admin/business-bulk-import</code> — सुन्तला पेज</td></tr>
      </tbody>
    </table>

    <div class="subsection">
      <div class="subsection-title">कसरी गर्ने?</div>
      <ol class="steps">
        <li><strong>Template डाउनलोड</strong> गर्नुहोस् — पहिलो पङ्क्ति कलम हेडर हो, दोस्रो पङ्क्ति उदाहरण हो (मेट्नु पर्दैन, प्रणालीले बेवास्ता गर्छ), तेस्रो पङ्क्तिदेखि आफ्नो डेटा भर्नुहोस्।</li>
        <li>Code कलम (जस्तै household_code) खाली राख्नुहोस् — प्रणालीले आफैं unique code बनाउँछ।</li>
        <li>Excel फाइल अपलोड गर्नुहोस् — Import Background मा हुन्छ, तपाईं अर्को काम गर्न सक्नुहुन्छ।</li>
        <li>केही सेकेन्डपछि <strong>Status</strong> देखिन्छ — कति सफल भए, कति Skip भए र कतिमा त्रुटि छ।</li>
      </ol>
      <div class="callout amber">
        <strong>त्रुटि भए पनि निराश नहुनुहोस्!</strong>
        Error भएका रेकर्ड Skip हुन्छन् तर बाँकी Import चलिरहन्छ। त्रुटिको सूची हेरेर Excel मा सुधार गरेर फेरि Import गर्न सकिन्छ। त्रुटि Log मा अधिकतम २०० लाइन मात्र देखिन्छ — भित्रभित्रै थप हुन सक्छ।<br>
        <em>Errors are skipped but the rest continues. Review the error log, fix the Excel, and re-import just the problem rows.</em>
      </div>
    </div>
  </div>

  {{-- ══ SECTION 9: GOOGLE SYNC ══ --}}
  <div class="section" id="s9">
    <div class="section-title" style="background:#1e3a5f;">
      <span class="num">९</span>
      गूगल सिंक
      <span class="en">Google Sheets Sync</span>
    </div>

    <div class="callout blue">
      KoboCollect वा ODK बाट सङ्कलित डेटा Google Sheets मार्फत सिधै प्रणालीमा ल्याउन "Google Sync" प्रयोग गर्नुहोस् — Excel अपलोड गर्नु पर्दैन।<br>
      URL: <code>/admin/google-sync</code>
    </div>

    <div class="subsection">
      <div class="subsection-title">सुरु गर्नु अघि यी काम गर्नुहोस्</div>
      <ul class="bul">
        <li>Google Sheet <strong>Public (Viewer)</strong> बनाउनुहोस् — Sheet खोल्नुहोस् → Share → "Anyone with the link" → Viewer। Private Sheet भयो भने सिंक गर्दा त्रुटि आउँछ।</li>
        <li>Settings → Google Sheets ट्याब → Sheet ID राख्नुहोस्। Sheet को URL मा <code>spreadsheets/d/<strong>यो भाग</strong>/edit</code> नै Sheet ID हो।</li>
        <li>Sheet का Tab नाम ठीक यस्तै हुनुपर्छ: <code>Households</code>, <code>Members</code>, <code>Housing</code>, <code>Utilities</code>, <code>Economic</code>, <code>Agriculture</code>, <code>Livestock</code>, <code>LandParcels</code> — सानो-ठूलो अक्षर फरक पर्छ।</li>
      </ul>
    </div>

    <div class="subsection">
      <div class="subsection-title">Preview → Apply — दुई चरणमा काम सकिन्छ</div>
      <ol class="steps">
        <li><strong>Preview</strong> थिच्नुहोस् (~१२ सेकेन्ड) — Sheet डाउनलोड हुन्छ र नयाँ, परिवर्तित र त्रुटि रेकर्डको सूची देखिन्छ। यस चरणमा DB मा केही फेरिँदैन — हेर्ने मात्र हो।</li>
        <li>Preview नतिजा ध्यानपूर्वक हेर्नुहोस् — Warning हेर्नुहोस्, Error भए कारण बुझ्नुहोस्।</li>
        <li><strong>Apply</strong> थिच्नुहोस् (~२० सेकेन्ड) — सबै नयाँ र परिवर्तित रेकर्ड DB मा सुरक्षित हुन्छन्।</li>
      </ol>
      <div class="callout amber">
        <strong>Locked Records — प्रणालीले सम्पादन गरिएका रेकर्ड जोगाउँछ</strong>
        कसैले प्रणालीमा सीधै सम्पादन गरेको रेकर्ड "Locked" हुन्छ — सिंकले त्यस्ता रेकर्डलाई छुँदैन, त्यसमाथि पुरानो Sheet डेटाले override गर्दैन। जबरजस्ती अपडेट गर्नु छ भने "Force sync locked records" checkbox खोल्नुहोस् — सोचेरै गर्नुहोस्।<br>
        <em>Locked means "manually edited here — don't overwrite." Force-sync only when you're sure the sheet data is more accurate.</em>
      </div>
    </div>

    <div class="callout gray">
      <strong>Google Sheet को विस्तृत Structure बुझ्न</strong>
      कुन-कुन Tab बनाउने, कुन Column राख्ने, कुन Value राख्ने — सबै विवरण छुट्टै निर्देशिकामा छ: <a href="/admin/google-sync/manual" target="_blank">Google Sync Manual खोल्नुहोस्</a>
    </div>
  </div>

  {{-- ══ SECTION 10: SETTINGS ══ --}}
  <div class="section" id="s10">
    <div class="section-title" style="background:#064e3b;">
      <span class="num">१०</span>
      सेटिङ — तपाईंको नगरपालिकाको पहिचान यहीँ बन्छ
      <span class="en">Settings</span>
    </div>

    <div class="callout blue">
      Gear Icon → <strong>सेटिङ्ग</strong> (<code>/admin/settings</code>) — यहाँ सबै ट्याब भेटिन्छन्। नयाँ नगरपालिकामा प्रणाली राख्दा सबैभन्दा पहिले यहाँ आउनुहोस्।
    </div>

    <div class="tab-list">
      <div class="tab-item">
        <div class="tab-name">🏛️ सामान्य (General)</div>
        <div class="tab-desc">नगरपालिकाको नाम, प्रकार, लोगो, ठेगाना र वेबसाइट — यो नभरे सार्वजनिक पेजमा नाम देखिँदैन।</div>
      </div>
      <div class="tab-item">
        <div class="tab-name">📅 सर्वेक्षण अवधि</div>
        <div class="tab-desc">सुरू र अन्त्य मिति तोक्नुहोस् — यो अवधिभित्र मात्र सर्वेक्षणकर्ताले नयाँ रेकर्ड थप्न पाउँछन्।</div>
      </div>
      <div class="tab-item">
        <div class="tab-name">🗺️ वडाहरू (Wards)</div>
        <div class="tab-desc">वडा र टोल (Settlement) थप्नुहोस् — वडाध्यक्षको नाम पनि यहाँ राख्न सकिन्छ।</div>
      </div>
      <div class="tab-item">
        <div class="tab-name">📊 Google Sheets</div>
        <div class="tab-desc">Google Sync को लागि Household र Institution Sheet ID राख्नुहोस्।</div>
      </div>
      <div class="tab-item">
        <div class="tab-name">👥 जनप्रतिनिधि</div>
        <div class="tab-desc">नगरप्रमुख, उपप्रमुख, वडाध्यक्ष र सदस्यहरूको फोटो र जानकारी — सार्वजनिक पेजमा देखिन्छ।</div>
      </div>
      <div class="tab-item">
        <div class="tab-name">📸 फोटो ग्यालरी</div>
        <div class="tab-desc">नगरपालिकाका तस्वीरहरू श्रेणी अनुसार अपलोड गर्नुहोस् — सार्वजनिक Gallery मा देखिन्छन्।</div>
      </div>
      <div class="tab-item">
        <div class="tab-name">📥 डाउनलोड लिंक</div>
        <div class="tab-desc">वार्षिक प्रतिवेदन, योजना वा अन्य PDF को URL राख्नुहोस् — सर्वसाधारणले /public/downloads बाट डाउनलोड गर्न पाउँछन्।</div>
      </div>
      <div class="tab-item">
        <div class="tab-name">💬 स्वागत सन्देश</div>
        <div class="tab-desc">सार्वजनिक ड्यासबोर्डको माथिल्लो भागमा Banner सन्देश देखाउन — विशेष कार्यक्रम वा सूचनाको लागि उपयोगी।</div>
      </div>
      <div class="tab-item">
        <div class="tab-name">🗺️ नक्सा (Map)</div>
        <div class="tab-desc">वडा सीमा भएको GeoJSON फाइलको URL राख्नुहोस् — सार्वजनिक ड्यासबोर्डको नक्सा यहीँबाट आउँछ।</div>
      </div>
      <div class="tab-item">
        <div class="tab-name">🔐 Settings Lock</div>
        <div class="tab-desc">सेटिङ लक गर्नुहोस् ताकि कोही अनजानमा परिवर्तन नगरोस् — Unlock गर्न Prayas Nepal लाई सम्पर्क गर्नुहोस्।</div>
      </div>
      <div class="tab-item">
        <div class="tab-name">🔧 मर्मत मोड</div>
        <div class="tab-desc">Maintenance चाल्दा सर्वसाधारणले Countdown सहितको सन्देश देख्छन् — तपाईंको काममा बाधा पर्दैन।</div>
      </div>
      <div class="tab-item">
        <div class="tab-name">📤 Export / Import</div>
        <div class="tab-desc">सेटिङ JSON मा निर्यात गर्नुहोस् र नयाँ नगरपालिकामा आयात गर्नुहोस् — सबै फेरि टाइप गर्नु पर्दैन।</div>
      </div>
    </div>

    <div class="callout amber">
      <strong>नगरपालिकाको प्रकार ठीक राख्नुहोस् — सानो कुरा, ठूलो असर!</strong>
      General Settings मा <strong>प्रकार</strong> गलत भयो भने सार्वजनिक पेजमा "नगरपालिका परिचय" लेखिन्छ भले पनि तपाईं गाउँपालिका हुनुहोस्। प्रकार सही राख्नुहोस् — नगरपालिका / गाउँपालिका / उपमहानगरपालिका / महानगरपालिका।<br>
      <em>The type field controls the heading shown on your public profile page — make sure it's set correctly.</em>
    </div>
  </div>

  {{-- ══ SECTION 11: USER MANAGEMENT ══ --}}
  <div class="section" id="s11">
    <div class="section-title" style="background:#1e3a5f;">
      <span class="num">११</span>
      प्रयोगकर्ता व्यवस्थापन — टीम बनाउने ठाउँ
      <span class="en">User Management</span>
    </div>

    <div class="callout blue">
      Gear Icon → <strong>प्रयोगकर्ता</strong> (<code>/admin/users</code>) — यहाँ तपाईंको सर्वेक्षण टीम बन्छ।
    </div>

    <div class="subsection">
      <div class="subsection-title">नयाँ टीम सदस्य थप्दा</div>
      <ol class="steps">
        <li>Gear → <strong>प्रयोगकर्ता</strong> → <strong>नयाँ प्रयोगकर्ता</strong> बटन थिच्नुहोस्।</li>
        <li>नाम, इमेल ठेगाना र भूमिका (Role) छान्नुहोस् — कुन वडामा खटाउने त्यहीँ अनुसार Role दिनुहोस्।</li>
        <li>अस्थायी पासवर्ड दिनुहोस् — सुरक्षित रूपले त्यो व्यक्तिलाई सुनाउनुहोस्। उनले पहिलो पटक लगइन गर्दा पासवर्ड बदल्न प्रणालीले नै भन्छ।</li>
      </ol>
    </div>

    <div class="subsection">
      <div class="subsection-title">वडा तोकाई — यो नगरे उनले केही देख्दैनन्!</div>
      <div class="callout amber">
        <strong>URL: <code>/admin/ward-assignments</code> (Gear → वडा तोकाई)</strong><br>
        ward_reviewer र enumerator को लागि वडा तोक्नु अनिवार्य छ। वडा नतोकेसम्म उनीहरूले लगइन गर्दा कुनै घरधुरी, संस्था वा समीक्षा देखिँदैन — खाली पेज मात्र देखिन्छ। एउटा समीक्षकलाई एक वा बढी वडा तोक्न सकिन्छ।<br>
        <em>Without a ward assignment, a reviewer or enumerator sees nothing at all after login. Always assign wards right after creating their account.</em>
      </div>
    </div>

  </div>

  {{-- ══ SECTION 12: PUBLIC REPORTS ══ --}}
  <div class="section" id="s12">
    <div class="section-title" style="background:#065f46;">
      <span class="num">१२</span>
      सार्वजनिक प्रतिवेदन — सर्वसाधारणले के देख्छन्?
      <span class="en">Public Reports &amp; Analytics</span>
    </div>

    <div class="callout green">
      <strong>यी सबै पेज लगइन बिना नै खुल्छन् — जो कोहीले हेर्न सक्छन्।</strong>
      तपाईंले प्रकाशित गर्नुभएको डेटा मात्र यहाँ देखिन्छ। Draft, Submitted वा Approved रेकर्ड कहिल्यै सार्वजनिक हुँदैनन् — त्यसैले प्रकाशन गर्नु अघि राम्ररी जाँच्नुहोस्।<br>
      <em>All pages below are public — no login needed. Only Published data is ever shown here.</em>
    </div>

    <table class="info-table">
      <thead><tr><th>पेज</th><th>URL</th><th>के देखिन्छ?</th></tr></thead>
      <tbody>
        <tr><td class="label-col">मुख्य ड्यासबोर्ड</td><td><code>/public</code></td><td>जनसंख्या, जाति, धर्म, मातृभाषाका चार्ट र वडा नक्सा — पहिलो आउने पेज</td></tr>
        <tr><td class="label-col">नगरपालिका परिचय</td><td><code>/public/profile</code></td><td>इतिहास, भूगोल, संस्कृति — Settings मा भरिएका १८ खण्डको पाठ</td></tr>
        <tr><td class="label-col">जनसंख्या र आवास</td><td><code>/public/housing</code></td><td>घर, शौचालय, बिजुली, आम्दानी, प्रकोप — विस्तृत तथ्याङ्क</td></tr>
        <tr><td class="label-col">क्रस-ट्याब प्रतिवेदन</td><td><code>/public/cross-tab</code></td><td>जाति × शिक्षा, पेशा × आय जस्ता क्रस विश्लेषण — ९ श्रेणी</td></tr>
        <tr><td class="label-col">संस्थागत प्रतिवेदन</td><td><code>/public/institution-reports/</code></td><td>विद्यालय, स्वास्थ्य, सहकारी, बैंक, NGO छुट्टा-छुट्टै पेज</td></tr>
        <tr><td class="label-col">व्यवसाय प्रतिवेदन</td><td><code>/public/business-reports/</code></td><td>व्यवसाय प्रकार, वडागत वितरण, रोजगारी — ५ पेज</td></tr>
        <tr><td class="label-col">फोटो ग्यालरी</td><td><code>/public/gallery</code></td><td>Settings मा अपलोड गरिएका तस्वीरहरू — श्रेणी अनुसार छान्न सकिन्छ</td></tr>
        <tr><td class="label-col">डाउनलोड</td><td><code>/public/downloads</code></td><td>Settings → डाउनलोड लिंक मा थपिएका PDF/DOCX फाइलहरू</td></tr>
      </tbody>
    </table>

    <div class="callout amber">
      <strong>भर्खरै Publish गर्नुभयो तर सार्वजनिक पेजमा देखिएन?</strong>
      ड्यासबोर्ड र Housing Report को तथ्याङ्क ३० मिनेटसम्म Cache हुन्छ — त्यसैले फरक देखिन थोरै समय लाग्छ। पर्खन नभई तुरुन्त चाहिएमा Gear → सेटिङ → "Cache Clear" गर्नुहोस्।<br>
      <em>Analytics are cached for 30 minutes — that's normal. Clear cache from Settings if you need to see changes immediately.</em>
    </div>
  </div>

  {{-- ══ SECTION 13: LICENSE & MAINTENANCE ══ --}}
  <div class="section" id="s13">
    <div class="section-title" style="background:#7f1d1d;">
      <span class="num">१३</span>
      लाइसेन्स तथा मर्मत मोड
      <span class="en">License &amp; Maintenance Mode</span>
    </div>

    <div class="subsection">
      <div class="subsection-title">लाइसेन्स म्याद सकिएमा के हुन्छ?</div>
      <div class="callout red">
        <strong>लाइसेन्स म्याद सकिएमा प्रणाली अस्थायी रूपमा बन्द हुन्छ — तुरुन्त नवीकरणको लागि सम्पर्क गर्नुहोस्:</strong>
        📧 <strong>admin@prayasnepal.com</strong> — Prayas Nepal, System Provider<br>
        <em>When the license expires, the system temporarily locks. Contact the provider immediately to renew.</em>
      </div>
      <div class="callout amber">
        <strong>Reviewer लाइसेन्स पेजमा अड्किएमा?</strong>
        यो कहिलेकाहीँ Impersonation पछि हुन सक्छ। समाधान सरल छ — उनीहरूलाई "लगआउट गरी Admin लगइन गर्नुहोस्" बटन थिच्न भन्नुहोस् (त्यो पेजमै देखिन्छ)।
        यदि ती बटन देखिएन भने तपाईंले phpMyAdmin मा गएर sessions Table मा <code>TRUNCATE TABLE sessions;</code> चलाउनुस् — सबैको session reset हुन्छ।<br>
        <em>A simple "logout" button on the expired page solves this. Or truncate the sessions table as a last resort.</em>
      </div>
    </div>

    <div class="subsection">
      <div class="subsection-title">मर्मत मोड — सर्भर काम छ, सर्वसाधारणलाई थाहा दिऊँ</div>
      <div class="callout blue">
        <strong>Settings → मर्मत मोड ट्याब</strong><br>
        Server Update, Data Migration वा कुनै काम गर्दा Maintenance Mode चालु गर्नुहोस् — सर्वसाधारणले Countdown Timer सहितको सन्देश देख्छन्, तपाईंको Admin काम भने सामान्य रहन्छ।
        Background को रूपमा फोटो, URL वा रङको Gradient छान्न सकिन्छ। काम सकिएपछि Mode बन्द गर्न नबिर्सनुहोस्!<br>
        <em>Admins aren't affected by maintenance mode — only the public sees the message. Remember to turn it off when done.</em>
      </div>
    </div>

    <div class="subsection">
      <div class="subsection-title">कुनै समस्या परे सम्पर्क गर्नुहोस्</div>
      <table class="info-table">
        <tbody>
          <tr><td class="label-col">📧 Email</td><td>admin@prayasnepal.com</td></tr>
          <tr><td class="label-col">🌐 Website</td><td>prayasnepal.com</td></tr>
          <tr><td class="label-col">🖥 प्रणाली</td><td>स्थानीय तह वस्तुगत विवरण तथा विश्लेषण प्रणाली v2.0+</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  {{-- ══ SECTION 14: EXPORT & DOWNLOAD ══ --}}
  <div class="section" id="s14">
    <div class="section-title" style="background:#065f46;">
      <span class="num">१४</span>
      निर्यात तथा डाउनलोड सुविधाहरू
      <span class="en">Export &amp; Download Features</span>
    </div>

    <div class="callout green">
      <strong>जम्मा ४ प्रकारका Export सुविधाहरू छन् — कहाँबाट के निकाल्न सकिन्छ भनेर एकचोटि बुझिसकेपछि काम धेरै सजिलो हुन्छ।</strong>
      एउटा रेकर्डको PDF, पूरै डेटाबेसको Excel, Chart को PNG र CSV — सबै भित्रबाटै मिल्छ।<br>
      <em>There are 4 types of exports: individual record PDF/Print, full Excel data export, chart PNG images, and chart CSV data.</em>
    </div>

    {{-- 14.1 Individual Record PDF & Print --}}
    <div class="subsection">
      <div class="subsection-title">व्यक्तिगत अभिलेख — PDF र प्रिन्ट</div>
      <p style="font-size:11.5px;margin-bottom:10px;">घरधुरी, संस्था, व्यवसाय र सदस्यको विवरण पेज खोल्नुहोस् — दायाँ Sidebar मा Print र PDF बटन देखिन्छन्।</p>
      <table class="info-table">
        <thead>
          <tr style="background:#f0fdf4;">
            <th style="padding:7px 10px;text-align:left;font-size:11px;font-weight:700;color:#14532d;border-bottom:1px solid #e2e8f0;">अभिलेख प्रकार</th>
            <th style="padding:7px 10px;text-align:left;font-size:11px;font-weight:700;color:#14532d;border-bottom:1px solid #e2e8f0;">Print बटन</th>
            <th style="padding:7px 10px;text-align:left;font-size:11px;font-weight:700;color:#14532d;border-bottom:1px solid #e2e8f0;">PDF बटन</th>
            <th style="padding:7px 10px;text-align:left;font-size:11px;font-weight:700;color:#14532d;border-bottom:1px solid #e2e8f0;">सामग्री</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="padding:7px 10px;font-weight:600;">🏠 घरधुरी</td>
            <td style="padding:7px 10px;">✅ बैजनी रङ</td>
            <td style="padding:7px 10px;">✅ रातो रङ (<code>/households/{id}/pdf</code>)</td>
            <td style="padding:7px 10px;">१४ खण्डको पूर्ण विवरण — परिवार, सम्पत्ति, कृषि, आर्थिक, माइग्रेसन लगायत</td>
          </tr>
          <tr style="background:#f8fafc;">
            <td style="padding:7px 10px;font-weight:600;">🏫 संस्था</td>
            <td style="padding:7px 10px;">✅ हरियो (Teal) रङ</td>
            <td style="padding:7px 10px;">✅ रातो रङ (<code>/institutions/{id}/pdf</code>)</td>
            <td style="padding:7px 10px;">संस्थाको ६ खण्ड — प्रकार, सम्पर्क, कर्मचारी, भौतिक संरचना लगायत</td>
          </tr>
          <tr>
            <td style="padding:7px 10px;font-weight:600;">🏪 व्यवसाय</td>
            <td style="padding:7px 10px;">✅ सुन्तला (Orange) रङ</td>
            <td style="padding:7px 10px;">✅ रातो रङ (<code>/businesses/{id}/pdf</code>)</td>
            <td style="padding:7px 10px;">व्यवसायको ४ खण्ड — दर्ता, सम्पर्क, कर्मचारी, सञ्चालन विवरण</td>
          </tr>
          <tr style="background:#f8fafc;">
            <td style="padding:7px 10px;font-weight:600;">👤 सदस्य</td>
            <td style="padding:7px 10px;">✅ Hero बारमा</td>
            <td style="padding:7px 10px;">✅ Hero बारमा (PDF बटनले Print dialog खोल्छ)</td>
            <td style="padding:7px 10px;">व्यक्तिगत Resume-शैलीको विवरण — शिक्षा, स्वास्थ्य, रोजगार, आय, बसाइसराइ</td>
          </tr>
        </tbody>
      </table>
      <div class="callout amber">
        <strong>PDF कसरी सुरक्षित गर्ने?</strong>
        "PDF डाउनलोड" बटनले नयाँ ट्याबमा PDF खोल्छ। त्यहाँ ब्राउजरको Print (Ctrl+P) थिचेर <strong>Destination → "Save as PDF"</strong> छानेर सुरक्षित गर्नुहोस्।
        घरधुरी, संस्था र व्यवसायका PDF Server-Side (mPDF) बाट बन्छन् भने सदस्यको PDF Browser Print बाट बन्छ।<br>
        <em>Click "PDF डाउनलोड" → new tab opens → Ctrl+P → Save as PDF. HH/Institution/Business use server-side mPDF; Member uses browser print.</em>
      </div>
    </div>

    {{-- 14.2 Full Data Export (Excel) --}}
    <div class="subsection">
      <div class="subsection-title">पूर्ण तथ्याङ्क निर्यात — Excel Multi-Sheet Export</div>
      <div class="callout blue">
        <strong>साइडबार → Administration → तथ्याङ्क निर्यात</strong><br>
        सम्पूर्ण डेटाबेस एउटा Excel फाइलमा निकाल्न यो सुविधा प्रयोग गर्नुहोस् — अनुसन्धान, तथ्याङ्क विश्लेषण वा अर्को प्रणालीमा पठाउन उपयोगी।<br>
        <em>Export the full database to a multi-sheet Excel file from Sidebar → Administration → तथ्याङ्क निर्यात.</em>
      </div>
      <p style="font-size:11.5px;font-weight:600;margin-bottom:6px;">३ चरणमा Excel फाइल तयार पार्नुहोस्:</p>
      <ol class="steps">
        <li><strong>वडा दायरा छान्नुहोस्</strong> — "सम्पूर्ण नगर/गाउँपालिका" वा कुनै एउटा वडा मात्र।</li>
        <li><strong>स्थिति फिल्टर छान्नुहोस्</strong> — प्रकाशित मात्र / वडा स्वीकृत र माथि / मस्यौदा बाहेक सबै / सबै स्थिति।</li>
        <li><strong>Excel पाना (Sheets) छान्नुहोस्</strong> — सारांश पाना सधैं आउँछ। थप पाना आफ्नो आवश्यकता अनुसार छान्नुहोस्।</li>
      </ol>
      <table class="info-table" style="margin-top:8px;">
        <thead>
          <tr style="background:#eff6ff;">
            <th style="padding:7px 10px;text-align:left;font-size:11px;font-weight:700;color:#1e40af;border-bottom:1px solid #e2e8f0;">Excel पाना</th>
            <th style="padding:7px 10px;text-align:left;font-size:11px;font-weight:700;color:#1e40af;border-bottom:1px solid #e2e8f0;">के हुन्छ?</th>
            <th style="padding:7px 10px;text-align:center;font-size:11px;font-weight:700;color:#1e40af;border-bottom:1px solid #e2e8f0;">सधैं आउँछ?</th>
          </tr>
        </thead>
        <tbody>
          <tr><td style="padding:7px 10px;font-weight:600;">सारांश पाना</td><td style="padding:7px 10px;">वडागत स्थिति विश्लेषण — प्रत्येक वडामा कति Draft, Submitted, Approved, Published</td><td style="text-align:center;">✅ हो</td></tr>
          <tr style="background:#f8fafc;"><td style="padding:7px 10px;font-weight:600;">घरधुरी</td><td style="padding:7px 10px;">मुख्य घरधुरी विवरण — unique_code, वडा, सम्पर्क, आधारभूत तथ्याङ्क</td><td style="text-align:center;">छनोट</td></tr>
          <tr><td style="padding:7px 10px;font-weight:600;">सदस्यहरू</td><td style="padding:7px 10px;">परिवार सदस्यहरूको विवरण — नाम, उमेर, शिक्षा, रोजगार, स्वास्थ्य</td><td style="text-align:center;">छनोट</td></tr>
          <tr style="background:#f8fafc;"><td style="padding:7px 10px;font-weight:600;">संस्थाहरू</td><td style="padding:7px 10px;">संस्थागत अभिलेख — विद्यालय, स्वास्थ्य, धार्मिक, वित्तीय संस्था</td><td style="text-align:center;">छनोट</td></tr>
          <tr><td style="padding:7px 10px;font-weight:600;">व्यवसायहरू</td><td style="padding:7px 10px;">व्यवसाय अभिलेख — दर्ता, सम्पर्क, कर्मचारी संख्या</td><td style="text-align:center;">छनोट</td></tr>
        </tbody>
      </table>
      <div class="callout gray">
        <strong>स्थिति फिल्टरको अर्थ</strong><br>
        • <strong>प्रकाशित मात्र</strong> — अन्तिम प्रकाशित अभिलेख (सबैभन्दा साफ डेटा)<br>
        • <strong>वडा स्वीकृत र माथि</strong> — Ward Approved + Published<br>
        • <strong>मस्यौदा बाहेक सबै</strong> — Submitted, Approved, Published (Draft बाहेक)<br>
        • <strong>सबै स्थिति</strong> — मस्यौदा सहित सबै अभिलेख<br>
        <em>For clean data for external sharing, choose "प्रकाशित मात्र". For internal review, "सबै स्थिति" gives full picture.</em>
      </div>
    </div>

    {{-- 14.3 Chart PNG & CSV Export --}}
    <div class="subsection">
      <div class="subsection-title">Chart र तालिका — PNG र CSV निर्यात</div>
      <div class="callout blue">
        <strong>सार्वजनिक प्रतिवेदन पेजका सबै Chart मा Export बटन हुन्छन्।</strong>
        Chart को दायाँ माथि दुईवटा सानो Icon देखिन्छन् — एउटाले PNG Image र अर्कोले CSV Data निकाल्छ।<br>
        <em>Every chart on the public reports pages has two export icons — one for PNG image, one for CSV data.</em>
      </div>
      <table class="info-table">
        <thead>
          <tr style="background:#f8fafc;">
            <th style="padding:7px 10px;text-align:left;font-size:11px;font-weight:700;color:#475569;border-bottom:1px solid #e2e8f0;">Export प्रकार</th>
            <th style="padding:7px 10px;text-align:left;font-size:11px;font-weight:700;color:#475569;border-bottom:1px solid #e2e8f0;">Icon</th>
            <th style="padding:7px 10px;text-align:left;font-size:11px;font-weight:700;color:#475569;border-bottom:1px solid #e2e8f0;">के हुन्छ?</th>
            <th style="padding:7px 10px;text-align:left;font-size:11px;font-weight:700;color:#475569;border-bottom:1px solid #e2e8f0;">कहाँ काम लाग्छ?</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="padding:7px 10px;font-weight:600;">PNG Image</td>
            <td style="padding:7px 10px;"><i class="fa-solid fa-image" style="color:#7c3aed;"></i> बैजनी Icon</td>
            <td style="padding:7px 10px;">Chart को High-Quality तस्वीर डाउनलोड हुन्छ</td>
            <td style="padding:7px 10px;">Presentation, प्रतिवेदन, सोसल मिडियामा राख्न</td>
          </tr>
          <tr style="background:#f8fafc;">
            <td style="padding:7px 10px;font-weight:600;">CSV Data</td>
            <td style="padding:7px 10px;"><i class="fa-solid fa-download" style="color:#16a34a;"></i> हरियो Icon</td>
            <td style="padding:7px 10px;">Chart पछाडिको संख्यात्मक डेटा CSV मा डाउनलोड</td>
            <td style="padding:7px 10px;">Excel मा थप विश्लेषण गर्न वा अन्य ठाउँमा प्रयोग</td>
          </tr>
        </tbody>
      </table>
      <div class="callout gray">
        <strong>कुन Chart मा PNG Export छ?</strong><br>
        • <strong>Frequency Bar Chart</strong> (सबैभन्दा सामान्य Chart) — PNG र CSV दुवै<br>
        • <strong>Cross-Tab Chart</strong> (दुई चर बीचको सम्बन्ध) — Chart देखाउँदा मात्र PNG; CSV सधैं उपलब्ध<br>
        Cross-Tab Table मोडमा (Chart बन्द गर्दा) PNG बटन देखिँदैन — PNG को लागि पहिले Chart मोड ON गर्नुहोस्।<br>
        <em>PNG export is available on all Frequency Bar charts and on Cross-Tab charts when chart view is active.</em>
      </div>
    </div>

    {{-- 14.4 Settings JSON Export/Import --}}
    <div class="subsection">
      <div class="subsection-title">सेटिङ JSON निर्यात र आयात — नयाँ नगरपालिका छिटो सेटअप गर्न</div>
      <div class="callout blue">
        <strong>Settings → आयात/निर्यात ट्याब</strong><br>
        एउटा नगरपालिकाको सम्पूर्ण सेटिङ (वडाहरू, टोल, प्रतिनिधि, प्रोफाइल, डाउनलोड लिंक, Google Sheets कन्फिग लगायत) JSON फाइलमा निकाल्न र अर्को नगरपालिकामा लगाउन सकिन्छ। नयाँ deployment को समयमा यो अत्यन्त उपयोगी हुन्छ।<br>
        <em>Export all settings as a JSON file and import into another municipality — great for quick new deployment setup.</em>
      </div>
      <p style="font-size:11.5px;font-weight:600;margin-bottom:6px;">निर्यात गर्दा (Export) — १४ खण्ड उपलब्ध छन्:</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 16px;margin-bottom:12px;">
        <div style="font-size:11px;color:#334155;">• सामान्य विवरण</div>
        <div style="font-size:11px;color:#334155;">• नक्सा सेटिङ</div>
        <div style="font-size:11px;color:#334155;">• मर्मत अवस्था</div>
        <div style="font-size:11px;color:#334155;">• सर्वेक्षण अवधि</div>
        <div style="font-size:11px;color:#334155;">• स्वागत सन्देश</div>
        <div style="font-size:11px;color:#334155;">• प्रोफाइल सामग्री</div>
        <div style="font-size:11px;color:#334155;">• फोटो डाइरेक्टरी</div>
        <div style="font-size:11px;color:#334155;">• वडा र टोल</div>
        <div style="font-size:11px;color:#334155;">• सर्वेक्षण विकल्प</div>
        <div style="font-size:11px;color:#334155;">• डाउनलोड लिंक</div>
        <div style="font-size:11px;color:#334155;">• जनप्रतिनिधि</div>
        <div style="font-size:11px;color:#334155;">• कर्मचारी</div>
        <div style="font-size:11px;color:#334155;">• Google Sheets कन्फिग</div>
        <div style="font-size:11px;color:#334155;">• फोटो ग्यालरी (metadata)</div>
      </div>
      <div class="callout amber">
        <strong>आयात (Import) गर्दा ध्यान दिनुहोस्:</strong>
        Import गर्दा जुन-जुन खण्ड छान्नुहुन्छ, त्यो खण्डको पुरानो डेटा Replace हुन्छ। वडा र टोल Upsert हुन्छन् (नयाँ थपिन्छ, भएका अपडेट हुन्छन्)। Import गर्नु अघि पहिले नै Export गरेर Backup राख्नुहोस्।<br>
        <em>Import replaces selected sections. Wards/toles are upserted (add new, update existing). Export a backup before importing.</em>
      </div>
    </div>

    {{-- 14.5 Quick reference --}}
    <div class="subsection">
      <div class="subsection-title">छिटो सन्दर्भ — कहाँ के Export गर्ने?</div>
      <table class="info-table">
        <thead>
          <tr style="background:#f8fafc;">
            <th style="padding:7px 10px;text-align:left;font-size:11px;font-weight:700;border-bottom:1px solid #e2e8f0;">म के चाहन्छु?</th>
            <th style="padding:7px 10px;text-align:left;font-size:11px;font-weight:700;border-bottom:1px solid #e2e8f0;">कहाँ जाने?</th>
            <th style="padding:7px 10px;text-align:left;font-size:11px;font-weight:700;border-bottom:1px solid #e2e8f0;">Format</th>
          </tr>
        </thead>
        <tbody>
          <tr><td style="padding:6px 10px;">एउटा घरधुरीको विस्तृत PDF</td><td style="padding:6px 10px;">घरधुरी विवरण → PDF डाउनलोड बटन</td><td style="padding:6px 10px;">PDF (mPDF)</td></tr>
          <tr style="background:#f8fafc;"><td style="padding:6px 10px;">एउटा संस्थाको विस्तृत PDF</td><td style="padding:6px 10px;">संस्था विवरण → PDF डाउनलोड बटन</td><td style="padding:6px 10px;">PDF (mPDF)</td></tr>
          <tr><td style="padding:6px 10px;">एउटा सदस्यको Resume-PDF</td><td style="padding:6px 10px;">सदस्य विवरण → PDF बटन (Hero मा)</td><td style="padding:6px 10px;">Browser Print to PDF</td></tr>
          <tr style="background:#f8fafc;"><td style="padding:6px 10px;">पूरै नगरपालिकाको Excel डेटा</td><td style="padding:6px 10px;">Sidebar → Administration → तथ्याङ्क निर्यात</td><td style="padding:6px 10px;">Excel (.xlsx) Multi-Sheet</td></tr>
          <tr><td style="padding:6px 10px;">Chart को तस्वीर (Presentation को लागि)</td><td style="padding:6px 10px;">Chart माथि बैजनी Image Icon</td><td style="padding:6px 10px;">PNG</td></tr>
          <tr style="background:#f8fafc;"><td style="padding:6px 10px;">Chart को संख्यात्मक डेटा</td><td style="padding:6px 10px;">Chart माथि हरियो Download Icon</td><td style="padding:6px 10px;">CSV</td></tr>
          <tr><td style="padding:6px 10px;">सबै सेटिङ Backup/Transfer गर्न</td><td style="padding:6px 10px;">Settings → आयात/निर्यात → Export</td><td style="padding:6px 10px;">JSON</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="manual-footer">
    यो निर्देशिका तपाईंको काम सजिलो होस् भनेर बनाइएको हो — कुनै प्रश्न, सुझाव वा समस्या परे सँगसँगै समाधान गरौँला।<br>
    <em>This manual was made to make your work easier — we're here whenever you need help.</em><br><br>
    स्थानीय तह वस्तुगत विवरण तथा विश्लेषण प्रणाली — Prayas Nepal | {{ date('Y') }}<br>
    <small>यो निर्देशिका PDF मा सुरक्षित गर्न माथिको Print बटन थिच्नुहोस् · <em>Click the Print button above to save as PDF</em></small>
  </div>

</div>

<script>
function downloadDoc() {
    const style = document.querySelector('style') ? document.querySelector('style').innerHTML : ''
    // Clone page-wrap, remove no-print elements
    const wrap = document.querySelector('.page-wrap').cloneNode(true)
    wrap.querySelectorAll('.no-print').forEach(el => el.remove())

    const html = [
        "<html xmlns:o='urn:schemas-microsoft-com:office:office'",
        "xmlns:w='urn:schemas-microsoft-com:office:word'",
        "xmlns='http://www.w3.org/TR/REC-html40'>",
        "<head><meta charset='UTF-8'>",
        "<style>",
        style,
        "body { font-family: 'Segoe UI', Arial, sans-serif; }",
        "@page { margin: 2cm; }",
        "</style></head>",
        "<body>", wrap.innerHTML, "</body></html>"
    ].join('\n')

    const blob = new Blob(['﻿', html], { type: 'application/msword' })
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    a.href     = url
    a.download = 'प्रयोगकर्ता-निर्देशिका.doc'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
}
</script>
</body>
</html>
