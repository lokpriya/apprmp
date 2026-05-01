<!DOCTYPE html>
<html lang="ne" class="h-full">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>पहुँच प्रमाणीकरण</title>
  @vite(['resources/css/app.css'])
  <style>
    body { font-family: 'Inter Variable', 'Inter', 'Segoe UI', sans-serif; }
    .card-glow { box-shadow: 0 20px 60px rgba(0,0,0,0.18), 0 0 0 1px rgba(255,255,255,0.08); }
    @keyframes fadeIn { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
    .fade-in { animation: fadeIn .45s ease both; }
    @keyframes shake { 0%,100%{transform:translateX(0)} 20%,60%{transform:translateX(-6px)} 40%,80%{transform:translateX(6px)} }
    .shake { animation: shake .4s ease; }
  </style>
</head>
<body class="h-full bg-gradient-to-br from-[#0f1c35] via-[#1a2e4a] to-[#0d2137] flex items-center justify-center px-4">

  {{-- Background pattern --}}
  <div class="absolute inset-0 overflow-hidden pointer-events-none opacity-5">
    <div class="absolute top-10 left-10 w-64 h-64 rounded-full border border-white"></div>
    <div class="absolute bottom-10 right-10 w-96 h-96 rounded-full border border-white"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full border border-white"></div>
  </div>

  <div class="relative w-full max-w-sm fade-in">

    {{-- Card --}}
    <div class="bg-white rounded-2xl card-glow overflow-hidden">

      {{-- Top accent --}}
      <div class="h-1.5 bg-gradient-to-r from-[#206bc4] via-[#4a90d9] to-[#206bc4]"></div>

      <div class="px-8 pt-8 pb-7 text-center">

        {{-- LG Identity --}}
        @if($lg?->logo)
          <img src="{{ asset('storage/'.$lg->logo) }}" alt="Logo" class="w-14 h-14 object-contain mx-auto mb-3 rounded-full ring-2 ring-[#206bc4]/20" />
        @else
          <div class="w-14 h-14 bg-[#206bc4]/10 rounded-full flex items-center justify-center mx-auto mb-3">
            <i class="fa-solid fa-building-columns text-[#206bc4] text-xl"></i>
          </div>
        @endif

        <p class="text-[13px] font-semibold text-[#206bc4] mb-0.5">{{ $lg?->name_np ?? 'स्थानीय तह' }}</p>
        <p class="text-[11px] text-gray-400 mb-5">वस्तुगत विवरण तथा विश्लेषण प्रणाली</p>

        {{-- Lock icon --}}
        <div class="w-12 h-12 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-lock text-amber-500 text-lg"></i>
        </div>

        <h1 class="text-[17px] font-bold text-[#1d273b] mb-2">पहुँच प्रमाणीकरण</h1>
        <p class="text-[12px] text-gray-500 leading-relaxed mb-6">
          यो प्रणाली केवल तथ्याङ्क आवश्यकता भएका व्यक्तिहरूको लागि हो।<br>
          जारी राख्न तलको गणित प्रश्नको उत्तर दिनुहोस्।
        </p>

        {{-- Math problem display --}}
        <div class="bg-[#f0f6ff] rounded-xl px-6 py-4 mb-5 border border-[#d0e4f7]">
          <p class="text-[11px] text-[#206bc4] font-medium mb-2 uppercase tracking-wide">
            <i class="fa-solid fa-calculator mr-1"></i>गणित प्रश्न
          </p>
          <p class="text-3xl font-bold text-[#1d273b] tracking-tight select-none">
            {{ $problem }} = ?
          </p>
        </div>

        {{-- Error --}}
        @if($errors->has('answer'))
          <div id="err-box" class="flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-4 text-left shake">
            <i class="fa-solid fa-circle-exclamation text-red-500 text-xs shrink-0"></i>
            <span class="text-[12px] text-red-600">{{ $errors->first('answer') }}</span>
          </div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ route('public.verify.post') }}" id="verifyForm">
          @csrf
          <div class="relative mb-4">
            <input
              id="answer"
              type="number"
              name="answer"
              autofocus
              autocomplete="off"
              placeholder="उत्तर यहाँ लेख्नुहोस्"
              class="w-full text-center text-lg font-semibold rounded-lg border-2 border-[#d0e4f7] px-4 py-3 focus:border-[#206bc4] focus:ring-2 focus:ring-[#206bc4]/20 outline-none transition-all placeholder:text-gray-300 @error('answer') border-red-300 @enderror"
            />
          </div>

          <button type="submit"
            class="w-full bg-[#206bc4] hover:bg-[#1a5ba8] text-white font-semibold py-3 rounded-lg transition-all text-[14px] flex items-center justify-center gap-2 active:scale-[0.98]"
            id="submitBtn">
            <i class="fa-solid fa-unlock text-[13px]"></i>
            प्रवेश गर्नुहोस्
          </button>
        </form>

      </div>

      {{-- Footer --}}
      <div class="bg-gray-50 border-t border-gray-100 px-8 py-3 text-center">
        <p class="text-[10px] text-gray-400">
          <i class="fa-solid fa-shield-halved mr-1 text-gray-300"></i>
          यो प्रणाली सुरक्षित छ। तपाईंको पहुँच लग गरिएको छ।
        </p>
      </div>

    </div>

    {{-- Tagline --}}
    <p class="text-center text-[11px] text-white/30 mt-5">
      स्थानीय तह वस्तुगत विवरण प्रणाली &mdash; Prayas Nepal
    </p>

  </div>

  <script>
    // Show spinner on submit
    document.getElementById('verifyForm').addEventListener('submit', function () {
      const btn = document.getElementById('submitBtn');
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-[13px]"></i> जाँच गर्दैछ…';
      btn.disabled = true;
    });
    // Shake animation re-trigger on load if error present
    const err = document.getElementById('err-box');
    if (err) {
      void err.offsetWidth; // reflow
      err.classList.add('shake');
    }
  </script>

</body>
</html>
