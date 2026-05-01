<!DOCTYPE html>
<html lang="ne">
<head>
<meta charset="utf-8">
<style>
  body        { font-family: 'NotoSansDevanagari', sans-serif; font-size: 10.5pt; color: #1a1a1a; margin: 0; }
  h1          { font-size: 14pt; margin: 0; }
  h2          { font-size: 10pt; margin: 0 0 2pt; }
  .header     { text-align: center; border-bottom: 2.5pt solid #1d4ed8; padding-bottom: 8pt; margin-bottom: 12pt; }
  .lg-name    { font-size: 15pt; font-weight: bold; color: #1e3a8a; }
  .doc-title  { font-size: 13pt; font-weight: bold; color: #1d4ed8; margin-top: 4pt; }
  .doc-meta   { font-size: 9pt; color: #555; margin-top: 3pt; }
  .section        { margin-bottom: 12pt; }
  .section-title  { font-size: 10.5pt; font-weight: bold; background: #1d4ed8; color: #fff; padding: 3pt 7pt; margin-bottom: 0; }
  table       { width: 100%; border-collapse: collapse; }
  .kv td      { padding: 3.5pt 7pt; vertical-align: top; border-bottom: 0.5pt solid #e5e7eb; }
  .kv td.lbl  { font-weight: bold; width: 22%; color: #374151; background: #f8fafc; }
  .kv td.val  { color: #111; }
  .tbl th     { background: #1d4ed8; color: #fff; padding: 3.5pt 6pt; text-align: left; font-size: 9.5pt; }
  .tbl td     { padding: 3pt 6pt; border-bottom: 0.5pt solid #e5e7eb; font-size: 9.5pt; }
  .tbl tr:nth-child(even) td { background: #f8fafc; }
  .badge      { display: inline-block; padding: 1pt 5pt; border-radius: 3pt; font-size: 8.5pt; }
  .badge-blue { background: #dbeafe; color: #1e40af; }
  .badge-green{ background: #dcfce7; color: #166534; }
  .footer     { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 8pt; color: #9ca3af; border-top: 0.5pt solid #e5e7eb; padding-top: 3pt; }
  .row2col    { width: 100%; }
  .row2col td { width: 50%; vertical-align: top; padding-right: 8pt; }
</style>
</head>
<body>

{{-- Header --}}
<div class="header">
  <div class="lg-name">{{ $lg->name_np ?? $lg->name_en }}</div>
  <div class="doc-title">@yield('doc-title')</div>
  <div class="doc-meta">@yield('doc-meta')</div>
</div>

@yield('content')

<div class="footer">
  मुद्रण मिति: {{ now()->timezone('Asia/Kathmandu')->format('Y-m-d H:i') }} &nbsp;|&nbsp;
  {{ $lg->name_np ?? $lg->name_en }}
</div>

</body>
</html>
