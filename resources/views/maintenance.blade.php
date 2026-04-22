<!DOCTYPE html>
<html lang="ne">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $lg?->name_np ?? $lg?->name_en ?? 'स्थानीय सरकार' }} — मर्मत अवस्था</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', system-ui, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
      background: #0f1923;
    }

    /* ── Background ── */
    .bg-layer {
      position: fixed; inset: 0; z-index: 0;
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      @if(!empty($config['bg_image']))
        background-image: url('{{ Storage::url($config['bg_image']) }}');
      @elseif(!empty($config['bg_url']))
        background-image: url('{{ $config['bg_url'] }}');
      @else
        background: linear-gradient(135deg, #0f1923 0%, #1a3a4a 40%, #0d3d3a 100%);
      @endif
    }

    .bg-overlay {
      position: fixed; inset: 0; z-index: 1;
      background: linear-gradient(
        to bottom,
        rgba(10,18,26,0.72) 0%,
        rgba(10,18,26,0.82) 60%,
        rgba(10,18,26,0.92) 100%
      );
    }

    /* ── Animated particles ── */
    .particles { position: fixed; inset: 0; z-index: 2; pointer-events: none; overflow: hidden; }
    .particle {
      position: absolute; border-radius: 50%;
      background: rgba(255,255,255,0.06);
      animation: float linear infinite;
    }

    @keyframes float {
      0%   { transform: translateY(110vh) rotate(0deg);   opacity: 0; }
      10%  { opacity: 1; }
      90%  { opacity: 1; }
      100% { transform: translateY(-10vh) rotate(360deg); opacity: 0; }
    }

    /* ── Content ── */
    .card {
      position: relative; z-index: 10;
      width: 100%; max-width: 560px;
      margin: 0 auto; padding: 0 16px;
      text-align: center;
    }

    /* Logo */
    .logo-wrap {
      width: 80px; height: 80px;
      margin: 0 auto 20px;
      border-radius: 20px;
      background: rgba(255,255,255,0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,0.15);
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
    }
    .logo-wrap img { width: 100%; height: 100%; object-fit: contain; padding: 8px; }
    .logo-initial {
      font-size: 32px; font-weight: 800;
      color: #5eead4;
    }

    /* Gear icon */
    .gear-ring {
      width: 64px; height: 64px;
      margin: 0 auto 24px;
      border-radius: 50%;
      background: linear-gradient(135deg, rgba(20,184,166,0.2), rgba(6,182,212,0.2));
      border: 1px solid rgba(20,184,166,0.3);
      display: flex; align-items: center; justify-content: center;
    }
    .gear-ring i { font-size: 26px; color: #5eead4; animation: spin 6s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* LG name */
    .lg-name {
      font-size: 13px; font-weight: 600;
      letter-spacing: 0.08em; text-transform: uppercase;
      color: rgba(255,255,255,0.45);
      margin-bottom: 12px;
    }

    /* Heading */
    .heading {
      font-size: clamp(22px, 5vw, 32px);
      font-weight: 800;
      color: #ffffff;
      line-height: 1.2;
      margin-bottom: 12px;
    }
    .heading span { color: #5eead4; }

    /* Divider */
    .divider {
      width: 48px; height: 3px;
      background: linear-gradient(90deg, #14b8a6, #06b6d4);
      border-radius: 2px;
      margin: 0 auto 20px;
    }

    /* Message */
    .message {
      font-size: 15px; color: rgba(255,255,255,0.75);
      line-height: 1.7; margin-bottom: 8px;
    }
    .message-sub {
      font-size: 13px; color: rgba(255,255,255,0.45);
      line-height: 1.6; margin-bottom: 28px;
    }

    /* ── Countdown ── */
    .countdown-label {
      font-size: 11px; font-weight: 700; letter-spacing: 0.12em;
      text-transform: uppercase; color: rgba(255,255,255,0.35);
      margin-bottom: 12px;
    }
    .countdown {
      display: flex; justify-content: center; gap: 10px;
      flex-wrap: wrap; margin-bottom: 32px;
    }
    .cd-box {
      min-width: 70px; padding: 14px 10px 10px;
      background: rgba(255,255,255,0.07);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 12px;
      text-align: center;
      transition: transform 0.3s;
    }
    .cd-box:hover { transform: translateY(-2px); }
    .cd-num {
      font-size: 32px; font-weight: 800;
      color: #5eead4; line-height: 1;
      font-variant-numeric: tabular-nums;
    }
    .cd-unit {
      font-size: 10px; font-weight: 600; letter-spacing: 0.08em;
      text-transform: uppercase; color: rgba(255,255,255,0.35);
      margin-top: 4px;
    }
    .cd-expired {
      font-size: 14px; color: #5eead4; font-weight: 600; padding: 16px 0;
    }

    /* ── Power button ── */
    .power-btn {
      display: inline-flex; align-items: center; justify-content: center;
      width: 52px; height: 52px;
      background: #fff;
      border-radius: 14px;
      border: none; text-decoration: none;
      box-shadow: 0 4px 16px rgba(0,0,0,0.18);
      transition: all 0.2s;
      cursor: pointer;
    }
    .power-btn:hover { transform: scale(1.08); box-shadow: 0 6px 24px rgba(239,68,68,0.35); }
    .power-btn i { font-size: 22px; color: #ef4444; }

    /* ── Footer ── */
    .footer {
      margin-top: 40px;
      font-size: 11px; color: rgba(255,255,255,0.2);
    }

    @media (max-width: 480px) {
      .cd-box { min-width: 58px; padding: 10px 8px 8px; }
      .cd-num { font-size: 26px; }
    }
  </style>
</head>
<body>

  <!-- Background -->
  <div class="bg-layer"></div>
  <div class="bg-overlay"></div>

  <!-- Floating particles -->
  <div class="particles" id="particles"></div>

  <!-- Main card -->
  <div class="card">

    {{-- Logo --}}
    @if($lg?->logo)
      <div class="logo-wrap">
        <img src="{{ Storage::url($lg->logo) }}" alt="{{ $lg->name_en }}" />
      </div>
    @endif

    {{-- Gear icon --}}
    <div class="gear-ring">
      <i class="fa-solid fa-gear"></i>
    </div>

    {{-- LG name --}}
    <p class="lg-name">{{ $lg?->name_np ?? $lg?->name_en ?? 'स्थानीय सरकार' }}</p>

    {{-- Heading --}}
    <h1 class="heading">
      साइट <span>मर्मत</span> अवस्थामा छ
    </h1>
    <div class="divider"></div>

    {{-- Message --}}
    @if(!empty($config['message']))
      <p class="message">{{ $config['message'] }}</p>
    @else
      <p class="message">हामी हाम्रो वेबसाइटमा केही सुधार गरिरहेका छौं। छिट्टै फर्किनेछौं।</p>
    @endif
    @if(!empty($config['message_sub']))
      <p class="message-sub">{{ $config['message_sub'] }}</p>
    @endif

    {{-- Countdown --}}
    @if(!empty($config['countdown_at']))
      <p class="countdown-label"><i class="fa-solid fa-clock" style="margin-right:5px"></i>पुनः सक्रिय हुने अनुमानित समय</p>
      <div class="countdown" id="countdown"></div>
      <script>
        const target = new Date("{{ $config['countdown_at'] }}").getTime();
        function pad(n) { return String(n).padStart(2,'0'); }
        function tick() {
          const now  = Date.now();
          const diff = target - now;
          const el   = document.getElementById('countdown');
          if (!el) return;
          if (diff <= 0) {
            el.innerHTML = '<p class="cd-expired"><i class="fa-solid fa-check-circle"></i> छिट्टै खुल्नेछ!</p>';
            return;
          }
          const d = Math.floor(diff / 86400000);
          const h = Math.floor((diff % 86400000) / 3600000);
          const m = Math.floor((diff % 3600000)  / 60000);
          const s = Math.floor((diff % 60000)    / 1000);
          el.innerHTML =
            `<div class="cd-box"><div class="cd-num">${pad(d)}</div><div class="cd-unit">दिन</div></div>` +
            `<div class="cd-box"><div class="cd-num">${pad(h)}</div><div class="cd-unit">घण्टा</div></div>` +
            `<div class="cd-box"><div class="cd-num">${pad(m)}</div><div class="cd-unit">मिनेट</div></div>` +
            `<div class="cd-box"><div class="cd-num">${pad(s)}</div><div class="cd-unit">सेकेण्ड</div></div>`;
        }
        tick();
        setInterval(tick, 1000);
      </script>
    @endif

    {{-- Power button --}}
    <a href="/login" class="power-btn">
      <i class="fa-solid fa-power-off"></i>
    </a>

    <div class="footer">
      &copy; {{ date('Y') }} {{ $lg?->name_en ?? 'Local Government' }} — All Rights Reserved
    </div>
  </div>

  {{-- Particles JS --}}
  <script>
    const container = document.getElementById('particles');
    for (let i = 0; i < 18; i++) {
      const p = document.createElement('div');
      p.className = 'particle';
      const size = Math.random() * 60 + 20;
      p.style.cssText = `
        width:${size}px; height:${size}px;
        left:${Math.random()*100}%;
        animation-duration:${Math.random()*20+15}s;
        animation-delay:${Math.random()*15}s;
        opacity:0;
      `;
      container.appendChild(p);
    }
  </script>

</body>
</html>
