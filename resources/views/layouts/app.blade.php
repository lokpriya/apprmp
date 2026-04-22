<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'APPRMP') }}</title>

        <!-- Inter Font -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

        <!-- Tailwind CDN -->
        <script src="https://cdn.tailwindcss.com"></script>

        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

        @livewireStyles

        <style>
            body { font-family: 'Inter', sans-serif; }
            /* Hide sidebar scrollbar */
            .nav-scroll::-webkit-scrollbar { display: none; }
            .nav-scroll { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
    </head>
    <body class="antialiased bg-gray-50">

        {{-- Alpine store for sidebar collapse --}}
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.store('sidebar', {
                    collapsed: localStorage.getItem('apprmp-sidebar') === 'true',
                    toggle() {
                        this.collapsed = !this.collapsed;
                        localStorage.setItem('apprmp-sidebar', this.collapsed);
                    }
                });
            });
        </script>

        <div class="flex h-screen overflow-hidden bg-gray-50">

            {{-- ── SIDEBAR ────────────────────────────────────────────── --}}
            @include('layouts.sidebar')

            {{-- ── RIGHT COLUMN ────────────────────────────────────────── --}}
            <div class="flex flex-col flex-1 min-w-0 h-full">

                {{-- ── TOP BAR ──────────────────────────────────────────── --}}
                <header class="flex items-center gap-4 px-5 h-16 bg-gradient-to-r from-indigo-950 to-indigo-900 border-b border-indigo-800/60 shrink-0 z-20">

                    {{-- Page Title --}}
                    @isset($header)
                    <h2 class="text-sm font-bold text-white leading-none hidden md:block shrink-0">
                        {{ $header }}
                    </h2>
                    <div class="hidden md:block w-px h-6 bg-indigo-700/60 shrink-0"></div>
                    @endisset

                    {{-- Branding --}}
                    <div class="hidden md:flex items-center gap-2.5 shrink-0">
                        <div class="w-8 h-8 bg-indigo-600 rounded-xl flex items-center justify-center shadow shrink-0">
                            <i class="fa-solid fa-map-location-dot text-white text-xs"></i>
                        </div>
                        <div class="leading-none">
                            <p class="font-black text-white text-sm tracking-wide">{{ config('app.name') }}</p>
                            <p class="text-[10px] text-indigo-300/80 mt-0.5">
                                {{ auth()->user()->localGovernment->name_np ?? auth()->user()->localGovernment->name_en ?? 'Local Government' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex-1"></div>

                    {{-- Date --}}
                    <span class="hidden lg:block text-xs text-indigo-300/70">{{ now()->format('D, d M Y') }}</span>

                    <div class="w-px h-6 bg-indigo-700/60 shrink-0"></div>

                    {{-- User Dropdown --}}
                    <div x-data="{ open: false }" class="relative shrink-0">
                        <button @click="open = !open"
                                class="flex items-center gap-2 px-2 py-1.5 rounded-xl hover:bg-white/10 transition-colors group">
                            <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-black text-xs shadow shrink-0">
                                {{ strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}
                            </div>
                            <div class="hidden lg:block text-left">
                                <p class="text-sm font-bold text-white leading-none">{{ auth()->user()->name }}</p>
                                <p class="text-[10px] text-indigo-300/80 mt-0.5">
                                    {{ ucwords(str_replace('_', ' ', auth()->user()->role)) }}
                                </p>
                            </div>
                            <i class="fa-solid fa-angle-down text-xs text-indigo-400 transition-transform duration-200"
                               :class="open ? 'rotate-180' : ''"></i>
                        </button>

                        {{-- Dropdown panel --}}
                        <div x-show="open"
                             @click.outside="open = false"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                             x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                             class="absolute right-0 top-full mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 py-1 z-50 overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 bg-indigo-50">
                                <p class="font-bold text-gray-800 text-sm">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-indigo-600 mt-0.5 font-medium">
                                    {{ ucwords(str_replace('_', ' ', auth()->user()->role)) }}
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ auth()->user()->email }}</p>
                            </div>
                            <a href="{{ route('profile.edit') }}"
                               @click="open = false"
                               class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                                <i class="fa-solid fa-user text-indigo-400 text-sm w-4"></i>
                                My Profile
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                        class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 transition-colors">
                                    <i class="fa-solid fa-right-from-bracket text-sm w-4"></i>
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </header>

                {{-- ── MAIN CONTENT ─────────────────────────────────────── --}}
                <main class="flex-1 overflow-y-auto p-6 min-w-0">

                    {{-- Flash Messages --}}
                    @if(session('success'))
                    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm">
                        <i class="fa-solid fa-circle-check shrink-0"></i>
                        {{ session('success') }}
                    </div>
                    @endif
                    @if(session('error'))
                    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
                        <i class="fa-solid fa-circle-xmark shrink-0"></i>
                        {{ session('error') }}
                    </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

        @livewireScripts

        {{-- ── Nepali BS Calendar Converter ── --}}
        <script>
        window.NepaliBSCalendar = (function () {
            const data = {
                1970:[31,31,32,31,31,30,30,29,30,29,30,30], 1971:[31,31,32,31,32,30,30,29,30,29,30,30],
                1972:[31,32,31,32,31,30,30,30,29,29,30,30], 1973:[30,32,31,32,31,30,30,30,29,30,29,31],
                1974:[31,31,32,31,31,31,30,29,30,29,30,30], 1975:[31,31,32,32,31,30,30,29,30,29,30,30],
                1976:[31,32,31,32,31,30,30,30,29,29,30,31], 1977:[30,32,31,32,31,31,29,30,30,29,29,31],
                1978:[31,31,32,31,31,31,30,29,30,29,30,30], 1979:[31,31,32,32,31,30,30,29,30,29,30,30],
                1980:[31,32,31,32,31,30,30,30,29,29,30,31], 1981:[31,31,31,32,31,31,29,30,30,29,30,30],
                1982:[31,31,32,31,31,31,30,29,30,29,30,30], 1983:[31,31,32,32,31,30,30,29,30,29,30,30],
                1984:[31,32,31,32,31,30,30,30,29,29,30,31], 1985:[31,31,31,32,31,31,29,30,30,29,30,30],
                1986:[31,31,32,31,31,31,30,29,30,29,30,30], 1987:[31,32,31,32,31,30,30,29,30,29,30,30],
                1988:[31,32,31,32,31,30,30,30,29,29,30,31], 1989:[31,31,31,32,31,31,30,29,30,29,30,30],
                1990:[31,31,32,31,31,31,30,29,30,29,30,30], 1991:[31,32,31,32,31,30,30,30,29,29,30,30],
                1992:[31,32,31,32,31,30,30,30,29,30,29,31], 1993:[31,31,31,32,31,31,30,29,30,29,30,30],
                1994:[31,31,32,31,31,31,30,29,30,29,30,30], 1995:[31,32,31,32,31,30,30,30,29,29,30,30],
                1996:[31,32,31,32,31,30,30,30,29,30,29,31], 1997:[31,31,32,31,31,31,30,29,30,29,30,30],
                1998:[31,31,32,31,31,31,30,29,30,29,30,30], 1999:[31,32,31,32,31,30,30,30,29,29,30,31],
                2000:[30,32,31,32,31,30,30,30,29,30,29,31], 2001:[31,31,32,31,31,31,30,29,30,29,30,30],
                2002:[31,31,32,32,31,30,30,29,30,29,30,30], 2003:[31,32,31,32,31,30,30,30,29,29,30,31],
                2004:[30,32,31,32,31,30,30,30,29,30,29,31], 2005:[31,31,32,31,31,31,30,29,30,29,30,30],
                2006:[31,31,32,32,31,30,30,29,30,29,30,30], 2007:[31,32,31,32,31,30,30,30,29,29,30,31],
                2008:[31,31,31,32,31,31,29,30,30,29,29,31], 2009:[31,31,32,31,31,31,30,29,30,29,30,30],
                2010:[31,31,32,32,31,30,30,29,30,29,30,30], 2011:[31,32,31,32,31,30,30,30,29,29,30,31],
                2012:[31,31,31,32,31,31,29,30,30,29,30,30], 2013:[31,31,32,31,31,31,30,29,30,29,30,30],
                2014:[31,31,32,32,31,30,30,29,30,29,30,30], 2015:[31,32,31,32,31,30,30,30,29,29,30,31],
                2016:[31,31,31,32,31,31,29,30,30,29,30,30], 2017:[31,31,32,31,31,31,30,29,30,29,30,30],
                2018:[31,32,31,32,31,30,30,29,30,29,30,30], 2019:[31,32,31,32,31,30,30,30,29,30,29,31],
                2020:[31,31,31,32,31,31,30,29,30,29,30,30], 2021:[31,31,32,31,31,31,30,29,30,29,30,30],
                2022:[31,32,31,32,31,30,30,30,29,29,30,30], 2023:[31,32,31,32,31,30,30,30,29,30,29,31],
                2024:[31,31,31,32,31,31,30,29,30,29,30,30], 2025:[31,31,32,31,31,31,30,29,30,29,30,30],
                2026:[31,32,31,32,31,30,30,30,29,29,30,31], 2027:[30,32,31,32,31,30,30,30,29,30,29,31],
                2028:[31,31,32,31,31,31,30,29,30,29,30,30], 2029:[31,31,32,31,32,30,30,29,30,29,30,30],
                2030:[31,32,31,32,31,30,30,30,29,29,30,31], 2031:[30,32,31,32,31,30,30,30,29,30,29,31],
                2032:[31,31,32,31,31,31,30,29,30,29,30,30], 2033:[31,31,32,32,31,30,30,29,30,29,30,30],
                2034:[31,32,31,32,31,30,30,30,29,29,30,31], 2035:[31,31,31,32,31,31,29,30,30,29,30,30],
                2036:[31,31,32,31,31,31,30,29,30,29,30,30], 2037:[31,32,31,32,31,30,30,30,29,29,30,30],
                2038:[31,32,31,32,31,30,30,30,29,30,29,31], 2039:[31,31,31,32,31,31,30,29,30,29,30,30],
                2040:[31,31,32,31,31,31,30,29,30,29,30,30], 2041:[31,32,31,32,31,30,30,30,29,29,30,31],
                2042:[31,31,31,32,31,31,29,30,30,29,30,30], 2043:[31,31,32,31,31,31,30,29,30,29,30,30],
                2044:[31,32,31,32,31,30,30,29,30,29,30,30], 2045:[31,32,31,32,31,30,30,30,29,29,30,31],
                2046:[31,31,31,32,31,31,30,29,30,29,30,30], 2047:[31,31,32,31,31,31,30,29,30,29,30,30],
                2048:[31,32,31,32,31,30,30,30,29,29,30,30], 2049:[31,32,31,32,31,30,30,30,29,30,29,31],
                2050:[31,31,32,31,31,31,30,29,30,29,30,30], 2051:[31,31,32,31,32,30,30,29,30,29,30,30],
                2052:[31,32,31,32,31,30,30,30,29,29,30,31], 2053:[30,32,31,32,31,30,30,30,29,30,29,31],
                2054:[31,31,32,31,31,31,30,29,30,29,30,30], 2055:[31,31,32,32,31,30,30,29,30,29,30,30],
                2056:[31,32,31,32,31,30,30,30,29,29,30,31], 2057:[30,32,31,32,31,30,30,30,29,30,29,31],
                2058:[31,31,32,31,31,31,30,29,30,29,30,30], 2059:[31,31,32,32,31,30,30,29,30,29,30,30],
                2060:[31,32,31,32,31,30,30,30,29,29,30,31], 2061:[31,31,31,32,31,31,29,30,30,29,30,30],
                2062:[31,31,32,31,31,31,30,29,30,29,30,30], 2063:[31,32,31,32,31,30,30,30,29,29,30,30],
                2064:[31,32,31,32,31,30,30,30,29,30,29,31], 2065:[31,31,32,31,31,31,30,29,30,29,30,30],
                2066:[31,31,32,32,31,30,30,29,30,29,30,30], 2067:[31,32,31,32,31,30,30,30,29,29,30,31],
                2068:[31,31,31,32,31,31,29,30,30,29,30,30], 2069:[31,31,32,31,31,31,30,29,30,29,30,30],
                2070:[31,32,31,32,31,30,30,29,30,29,30,30], 2071:[31,32,31,32,31,30,30,30,29,29,30,31],
                2072:[31,31,31,32,31,31,29,30,30,29,30,30], 2073:[31,31,32,31,31,31,30,29,30,29,30,30],
                2074:[31,32,31,32,31,30,30,30,29,29,30,30], 2075:[31,32,31,32,31,30,30,30,29,30,29,31],
                2076:[31,31,31,32,31,31,30,29,30,29,30,30], 2077:[31,31,32,31,31,31,30,29,30,29,30,30],
                2078:[31,32,31,32,31,30,30,30,29,29,30,31], 2079:[31,31,31,32,31,31,29,30,30,29,30,30],
                2080:[31,32,31,32,31,30,30,29,30,30,29,31], 2081:[31,31,32,31,31,30,30,29,30,29,30,30],
                2082:[31,31,32,32,31,30,30,29,30,29,30,30], 2083:[31,32,31,32,31,30,30,30,29,29,30,31],
                2084:[31,31,31,32,31,31,29,30,30,29,30,30], 2085:[31,31,32,31,31,31,30,29,30,29,30,30],
                2086:[31,32,31,32,31,30,30,30,29,29,30,30], 2087:[31,32,31,32,31,30,30,30,29,30,29,31],
                2088:[31,31,32,31,31,31,30,29,30,29,30,30], 2089:[31,31,32,32,31,30,30,29,30,29,30,30],
                2090:[31,32,31,32,31,30,30,30,29,29,30,31],
            };
            const MONTHS_NE = ['बैशाख','जेठ','असार','साउन','भदौ','असोज','कार्तिक','मंसिर','पुष','माघ','फागुन','चैत'];
            const REF_AD    = new Date(1943, 3, 14);
            const REF_BY    = 2000;

            function daysInMonth(y, m) { return (data[y] || [])[m - 1] || 30; }

            function bsToAd(bsY, bsM, bsD) {
                if (!data[bsY]) return null;
                let days = 0;
                for (let y = REF_BY; y < bsY; y++) {
                    if (!data[y]) return null;
                    days += data[y].reduce((s, d) => s + d, 0);
                }
                for (let m = 1; m < bsM; m++) days += data[bsY][m - 1];
                days += bsD - 1;
                const ad = new Date(REF_AD);
                ad.setDate(ad.getDate() + days);
                return ad;
            }

            function adToBs(adDate) {
                let rem = Math.floor((adDate - REF_AD) / 86400000);
                if (rem < 0) return null;
                let y = REF_BY;
                while (data[y]) {
                    const yd = data[y].reduce((s, d) => s + d, 0);
                    if (rem < yd) break;
                    rem -= yd; y++;
                }
                let m = 1;
                while (m <= 12) { if (rem < data[y][m - 1]) break; rem -= data[y][m - 1]; m++; }
                return { year: y, month: m, day: rem + 1 };
            }

            return { daysInMonth, bsToAd, adToBs, months: MONTHS_NE };
        })();

        window.memberCard = function (idx, initAge, initDob) {
            return {
                age: initAge || 0,
                bsY: 2060, bsM: 1, bsD: 1,
                adDateStr: '', bsDobReady: false,

                get maxBsDay() { return window.NepaliBSCalendar.daysInMonth(this.bsY, this.bsM); },
                get bsDateLabel() {
                    if (!this.bsDobReady) return '';
                    return window.NepaliBSCalendar.months[this.bsM - 1] + ' ' + this.bsD + ', ' + this.bsY + ' वि.सं.';
                },

                init() {
                    if (initDob) {
                        const ad = new Date(initDob);
                        const bs = window.NepaliBSCalendar.adToBs(ad);
                        if (bs) { this.bsY = bs.year; this.bsM = bs.month; this.bsD = bs.day; }
                        this.adDateStr = initDob;
                        this.bsDobReady = true;
                        this.age = this._calcAge(ad);
                    }
                },

                _calcAge(adDate) {
                    const t = new Date();
                    let a = t.getFullYear() - adDate.getFullYear();
                    if (t.getMonth() < adDate.getMonth() || (t.getMonth() === adDate.getMonth() && t.getDate() < adDate.getDate())) a--;
                    return Math.max(0, a);
                },

                convertDob() {
                    if (this.bsD > this.maxBsDay) this.bsD = this.maxBsDay;
                    const ad = window.NepaliBSCalendar.bsToAd(this.bsY, this.bsM, this.bsD);
                    if (!ad) return;
                    const y = ad.getFullYear();
                    const m = String(ad.getMonth() + 1).padStart(2, '0');
                    const d = String(ad.getDate()).padStart(2, '0');
                    this.adDateStr  = y + '-' + m + '-' + d;
                    this.age        = this._calcAge(ad);
                    this.bsDobReady = true;
                    this.$wire.set('members.' + idx + '.date_of_birth', this.adDateStr);
                    this.$wire.set('members.' + idx + '.age', this.age);
                },

                setAgeManual(val) {
                    this.age = parseInt(val) || 0;
                }
            };
        };
        </script>
    </body>
</html>
