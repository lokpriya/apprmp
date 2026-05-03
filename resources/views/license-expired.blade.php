<!DOCTYPE html>
<html lang="ne">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $lg?->name_np ?? 'स्थानीय सरकार' }} — लाइसेन्स म्याद सकियो</title>
  @vite(['resources/css/app.css'])
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', system-ui, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #0f0f23 0%, #1a1040 50%, #0f0f23 100%);
      overflow: hidden;
    }

    .particles { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
    .particle {
      position: absolute; border-radius: 50%;
      background: rgba(139,92,246,0.08);
      animation: float linear infinite;
    }
    @keyframes float {
      0%   { transform: translateY(110vh); opacity: 0; }
      10%  { opacity: 1; }
      90%  { opacity: 1; }
      100% { transform: translateY(-10vh);  opacity: 0; }
    }

    .card {
      position: relative; z-index: 10;
      width: 100%; max-width: 520px;
      margin: 0 auto; padding: 0 20px;
      text-align: center;
    }

    .logo-wrap {
      width: 72px; height: 72px;
      margin: 0 auto 20px;
      border-radius: 18px;
      background: rgba(255,255,255,0.08);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,0.12);
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
    }
    .logo-wrap img { width: 100%; height: 100%; object-fit: contain; padding: 8px; }

    .shield-ring {
      width: 72px; height: 72px;
      margin: 0 auto 24px;
      border-radius: 50%;
      background: linear-gradient(135deg, rgba(239,68,68,0.2), rgba(139,92,246,0.2));
      border: 1px solid rgba(239,68,68,0.35);
      display: flex; align-items: center; justify-content: center;
    }
    .shield-ring i { font-size: 28px; color: #f87171; }

    .lg-name {
      font-size: 12px; font-weight: 600;
      letter-spacing: 0.1em; text-transform: uppercase;
      color: rgba(255,255,255,0.35);
      margin-bottom: 14px;
    }

    .heading {
      font-size: clamp(20px, 5vw, 30px);
      font-weight: 800;
      color: #fff;
      line-height: 1.25;
      margin-bottom: 10px;
    }
    .heading span { color: #f87171; }

    .divider {
      width: 44px; height: 3px;
      background: linear-gradient(90deg, #ef4444, #8b5cf6);
      border-radius: 2px;
      margin: 0 auto 20px;
    }

    .message {
      font-size: 15px; color: rgba(255,255,255,0.72);
      line-height: 1.7; margin-bottom: 6px;
    }
    .message-sub {
      font-size: 13px; color: rgba(255,255,255,0.4);
      line-height: 1.6; margin-bottom: 32px;
    }

    .login-btn {
      display: inline-flex; align-items: center; gap: 10px;
      padding: 12px 28px;
      background: linear-gradient(135deg, #7c3aed, #6d28d9);
      color: #fff; font-size: 14px; font-weight: 600;
      border-radius: 12px; text-decoration: none;
      border: 1px solid rgba(255,255,255,0.15);
      box-shadow: 0 4px 16px rgba(109,40,217,0.35);
      transition: all 0.2s;
    }
    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(109,40,217,0.45);
    }
    .login-btn i { font-size: 16px; }

    .footer {
      margin-top: 44px;
      font-size: 11px; color: rgba(255,255,255,0.18);
    }
  </style>
</head>
<body>

  <div class="particles" id="particles"></div>

  <div class="card">

    @if($lg?->logo)
      <div class="logo-wrap">
        <img src="{{ Storage::url($lg->logo) }}" alt="{{ $lg->name_en }}" />
      </div>
    @endif

    <div class="shield-ring">
      <i class="fa-solid fa-shield-xmark"></i>
    </div>

    <p class="lg-name">{{ $lg?->name_np ?? $lg?->name_en ?? 'स्थानीय सरकार' }}</p>

    <h1 class="heading">
      लाइसेन्स <span>म्याद</span> सकियो
    </h1>
    <div class="divider"></div>

    <p class="message">यस प्रणालीको लाइसेन्स म्याद समाप्त भएको छ।</p>
    <p class="message-sub">
      प्रणाली पुनः सक्रिय गर्न Super Admin ले लगइन गरी Settings → लाइसेन्स मा नयाँ की प्रविष्ट गर्नुहोस्।
    </p>

    <a href="/login" class="login-btn">
      <i class="fa-solid fa-shield-halved"></i>
      Admin लगइन गर्नुहोस्
    </a>

    <div class="footer">
      &copy; {{ date('Y') }} {{ $lg?->name_en ?? 'Local Government' }} — All Rights Reserved
    </div>

  </div>

  <script>
    const container = document.getElementById('particles');
    for (let i = 0; i < 14; i++) {
      const p = document.createElement('div');
      p.className = 'particle';
      const size = Math.random() * 50 + 15;
      p.style.cssText = `width:${size}px;height:${size}px;left:${Math.random()*100}%;animation-duration:${Math.random()*22+14}s;animation-delay:${Math.random()*12}s;opacity:0;`;
      container.appendChild(p);
    }
  </script>

</body>
</html>
