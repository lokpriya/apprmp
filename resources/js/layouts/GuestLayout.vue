<template>
  <div class="h-screen overflow-hidden flex flex-col lg:flex-row bg-white">

    <!-- ── Left branding panel (desktop only) ───────────────────────────── -->
    <div class="hidden lg:flex lg:w-[52%] xl:w-[55%] h-screen relative flex-col overflow-hidden"
         style="background: linear-gradient(145deg, #0b1e35 0%, #0f2847 60%, #0d2240 100%);">

      <!-- Decorative arcs -->
      <svg class="absolute inset-0 w-full h-full pointer-events-none" viewBox="0 0 600 700"
           fill="none" preserveAspectRatio="xMidYMid slice">
        <path d="M -80 180 Q 280 -120 680 260" stroke="rgba(255,255,255,0.09)" stroke-width="1.5"/>
        <path d="M -80 350 Q 250  30 680 420" stroke="rgba(255,255,255,0.06)" stroke-width="1.5"/>
        <path d="M  50 700 Q 380 320 700 480" stroke="rgba(255,255,255,0.07)" stroke-width="1"/>
        <path d="M 300 -60 Q 680 200 580 680" stroke="rgba(255,255,255,0.05)" stroke-width="1"/>
      </svg>

      <!-- Content wrapper — no scroll, fills full height -->
      <div class="relative flex flex-col h-full p-10 xl:p-14">

        <!-- Top: Logo + Badge + Headline + Subtitle -->
        <div>
          <!-- LG name -->
          <div class="flex items-center gap-3 mb-10">
            <div class="w-10 h-10 rounded-full bg-[#206bc4]/70 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-map-location-dot text-white text-[17px]"></i>
            </div>
            <div>
              <div class="text-white font-bold text-[14px] leading-tight">{{ lg?.name_np ?? 'Municipal Profile System' }}</div>
              <div class="text-white/50 text-[11px] mt-0.5">{{ lg?.name_en ?? 'APPRMP' }}</div>
            </div>
          </div>

          <!-- Badge -->
          <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-orange-400/60 mb-6">
            <span class="w-1.5 h-1.5 rounded-full bg-orange-400 shrink-0"></span>
            <span class="text-orange-400 text-[11px] font-semibold tracking-widest uppercase">Municipal Analytics Platform</span>
          </div>

          <!-- Headline — two lines -->
          <h2 class="text-white font-extrabold text-[2.3rem] xl:text-[2.6rem] leading-[1.15] mb-5">
            <span v-html="headlineLine1Html"></span><br>
            <span v-html="headlineLine2Html"></span>
          </h2>

          <!-- Subtitle — one line -->
          <p class="text-white/55 text-[13.5px] leading-relaxed">
            {{ subtitle }}
          </p>
        </div>

        <!-- Landmark photo — stretches to fill remaining vertical space -->
        <div v-if="landmarkPhoto" class="flex-1 min-h-0 my-6 rounded-xl overflow-hidden relative">
          <img :src="landmarkPhoto" alt="Landmark" class="w-full h-full object-cover" />
          <div class="absolute inset-0 bg-gradient-to-t from-[#0b1e35]/60 to-transparent"></div>
        </div>
        <!-- Spacer if no photo -->
        <div v-else class="flex-1 min-h-0"></div>

        <!-- Bottom: Stats row -->
        <div class="flex gap-0 divide-x divide-white/10 shrink-0">
          <div v-for="(stat, i) in stats" :key="i" :class="i === 0 ? 'pr-8' : 'px-8'">
            <div class="text-white font-bold text-2xl">{{ stat.value }}</div>
            <div class="text-white/45 text-[11px] mt-0.5 tracking-wide">{{ stat.label }}</div>
          </div>
        </div>

      </div>
    </div>

    <!-- ── Right form panel ──────────────────────────────────────────────── -->
    <div class="flex-1 flex items-center justify-center px-6 py-10 bg-white h-screen overflow-y-auto lg:overflow-hidden">
      <div class="w-full max-w-[360px]">

        <!-- Mobile-only: LG branding strip -->
        <div class="lg:hidden flex items-center justify-center gap-2.5 mb-8 pb-6 border-b border-gray-100">
          <div class="w-8 h-8 rounded-full bg-[#0b1e35] flex items-center justify-center shrink-0">
            <i class="fa-solid fa-map-location-dot text-white text-sm"></i>
          </div>
          <span class="font-bold text-[#0b1e35] text-sm">{{ lg?.name_np ?? 'Municipal Profile System' }}</span>
        </div>

        <slot />
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { escapeHtml } from '@/utils/escapeHtml.js'

const page = usePage()
const lg = computed(() => page.props.lg ?? null)

const branding = computed(() => lg.value?.login_branding ?? {})

const highlightWord = computed(() =>
  (branding.value.highlight_word || 'story').trim()
)

// The headline is admin-entered and rendered with v-html: escape it before
// adding the highlight span, or it can run script on the login page.
function applyHighlight(line) {
  const safe = escapeHtml(line)
  const word = escapeHtml(highlightWord.value)
  if (!word || !safe) return safe
  const re = new RegExp(`(${word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'i')
  return safe.replace(re, '<span class="text-orange-400">$1</span>')
}

const headlineLine1Html = computed(() =>
  applyHighlight(branding.value.headline_line1 || 'Data tells the')
)

const headlineLine2Html = computed(() =>
  applyHighlight(branding.value.headline_line2 || 'story of the development')
)

const subtitle = computed(() =>
  branding.value.subtitle ||
  'Transforming municipal data into actionable insights that drive meaningful development decisions.'
)

const landmarkPhoto = computed(() =>
  branding.value.landmark ? `/storage/${branding.value.landmark}` : null
)

const DEFAULT_STATS = [
  { value: '753+', label: 'Municipalities' },
  { value: '2M+',  label: 'Data Points'   },
  { value: '98%',  label: 'Accuracy'      },
]

const stats = computed(() => {
  const s = branding.value.stats
  if (Array.isArray(s) && s.length) return s
  return DEFAULT_STATS
})
</script>
