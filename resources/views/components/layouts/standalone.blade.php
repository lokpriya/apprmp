<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>

    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="antialiased bg-[#f4f6fb] min-h-screen">

    <!-- Top bar -->
    <header class="h-14 bg-[#182433] flex items-center justify-between px-6 shadow-sm">
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}"
               class="w-8 h-8 bg-[#206bc4] rounded flex items-center justify-center shrink-0 hover:bg-[#1a5ba8] transition-colors">
                <i class="fa-solid fa-arrow-left text-white text-xs"></i>
            </a>
            <div>
                <p class="text-[13px] font-semibold text-white leading-none">{{ $title ?? config('app.name') }}</p>
                @if(isset($subtitle))
                <p class="text-[10px] text-white/50 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-[12px] text-white/60">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-white/50 hover:text-white transition-colors text-[12px]">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </button>
            </form>
        </div>
    </header>

    <!-- Flash messages -->
    @if(session('success'))
    <div class="mx-6 mt-4 flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-md text-sm">
        <i class="fa-solid fa-circle-check shrink-0"></i> {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mx-6 mt-4 flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-md text-sm">
        <i class="fa-solid fa-circle-xmark shrink-0"></i> {{ session('error') }}
    </div>
    @endif

    <main class="p-6">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
