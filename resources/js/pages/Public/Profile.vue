<template>
  <div>

    <!-- ── Page body ── -->
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 py-6">

      <!-- Page header -->
      <div class="bg-white border border-[#e6e7e9] rounded-md p-4 mb-4">
        <div class="flex items-center gap-4">
          <div v-if="props.lg?.logo_url"
            class="w-12 h-12 rounded border border-[#e6e7e9] p-1 shrink-0 overflow-hidden bg-gray-50">
            <img :src="props.lg.logo_url" :alt="props.lg.name_en" class="w-full h-full object-contain">
          </div>
          <div v-else
            class="w-12 h-12 bg-[#206bc4]/10 rounded flex items-center justify-center text-lg font-bold text-[#206bc4] shrink-0">
            {{ props.lg?.name_en?.charAt(0) ?? 'L' }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.12em] mb-0.5">{{ lgTypeLabel }}</p>
            <h1 class="text-[15px] font-semibold text-[#1d273b] leading-tight">{{ props.lg?.name_en }}</h1>
            <p class="text-[12px] text-[#9aa0ac] mt-0.5 flex items-center gap-1.5 flex-wrap">
              <template v-if="props.lg?.province">
                <i class="fa-solid fa-location-dot text-[10px]"></i>{{ props.lg.province }}
              </template>
              <template v-if="props.lg?.district">
                <span>·</span>{{ props.lg.district }}
              </template>
              <template v-if="props.lg?.established_year">
                <span>·</span>Est. {{ props.lg.established_year }}
              </template>
            </p>
          </div>
          <div v-if="filledSections.length" class="shrink-0 hidden sm:block text-right">
            <p class="text-[22px] font-semibold text-[#1d273b] leading-none">{{ filledSections.length }}</p>
            <p class="text-[11px] text-[#9aa0ac] mt-0.5">{{ t('profile.sectionsAvailable') }}</p>
          </div>
        </div>
      </div>

      <!-- Back link -->
      <div class="mb-4">
        <a href="/public"
          class="inline-flex items-center gap-1.5 text-[13px] text-[#206bc4] hover:text-[#1a5ba8] font-medium transition">
          <i class="fa-solid fa-arrow-left text-[11px]"></i>
          {{ t('profile.backDashboard') }}
        </a>
      </div>

      <!-- Empty state -->
      <div v-if="!filledSections.length"
        class="bg-white rounded-md border border-[#e6e7e9] p-16 text-center text-[#9aa0ac]">
        <i class="fa-solid fa-file-circle-exclamation text-5xl text-gray-200 block mb-4"></i>
        <p class="text-[15px] font-semibold text-[#626976]">{{ t('profile.noContent') }}</p>
        <p class="text-[13px] mt-2">{{ t('profile.noContentDesc') }}</p>
        <a href="/public"
          class="inline-flex items-center gap-1.5 mt-6 h-9 px-4 bg-[#206bc4] text-white rounded text-[13px] font-medium hover:bg-[#1a5ba8] transition">
          <i class="fa-solid fa-arrow-left text-[11px]"></i>{{ t('profile.backDashboard') }}
        </a>
      </div>

      <!-- Sections grid -->
      <template v-else>
        <div class="columns-1 lg:columns-2 gap-4 space-y-4">
          <section v-for="sec in filledSections" :key="sec.key"
            :id="`section-${sec.key}`"
            class="scroll-mt-[100px] break-inside-avoid bg-white rounded-md border border-[#e6e7e9] overflow-hidden mb-4">
            <!-- Section header -->
            <div class="flex items-center gap-3 px-4 py-3 bg-[#206bc4]">
              <div class="w-7 h-7 rounded bg-white/20 flex items-center justify-center shrink-0 text-sm">
                {{ sectionIcons[sec.key] ?? '📄' }}
              </div>
              <h2 class="text-[13px] font-semibold text-white">{{ sec.label }}</h2>
            </div>
            <!-- Section content -->
            <div class="px-4 py-4 text-[13px] text-[#626976] leading-relaxed whitespace-pre-line">
              {{ props.profileContent[sec.key] }}
            </div>
          </section>
        </div>

        <div class="flex justify-center mt-6 pt-5 border-t border-[#e6e7e9]">
          <a href="/public"
            class="inline-flex items-center gap-1.5 h-9 px-5 bg-[#206bc4] hover:bg-[#1a5ba8] text-white rounded text-[13px] font-medium transition">
            <i class="fa-solid fa-arrow-left text-[11px]"></i>{{ t('profile.backDashboard') }}
          </a>
        </div>
      </template>

    </div>
  </div>
</template>

<script setup>
import { computed, watchEffect, onMounted, onUnmounted } from 'vue'
import PublicLayout from '@/layouts/PublicLayout.vue'
import { useLocale } from '@/i18n/index.js'
import { useSidebar } from '@/composables/useSidebar.js'

defineOptions({ layout: PublicLayout })

const { t } = useLocale()
const { setSections, setActive, clearSections } = useSidebar()

const props = defineProps({
  lg:              { type: Object, default: null },
  profileSections: { type: Object, default: () => ({}) },
  profileContent:  { type: Object, default: () => ({}) },
})

const sectionIcons = {
  brief_intro:    '📋', history:        '📜', naming:         '🏷️',
  geography:      '🗺️', geology:        '⛰️', water_system:   '💧',
  climate:        '🌤️', flora:          '🌿', biodiversity:   '🦋',
  social_economy: '🏘️', population:     '👥', culture:        '🎭',
  attractions:    '🌟', political:      '🏛️', tourism:        '✈️',
  regulations:    '📑', challenges:     '⚡', development:    '🚀',
}

const LG_TYPE_LABELS = {
  municipality:      'नगरपालिका परिचय',
  rural_municipality:'गाउँपालिका परिचय',
  sub_metropolitan:  'उपमहानगरपालिका परिचय',
  metropolitan:      'महानगरपालिका परिचय',
}
const lgTypeLabel = computed(() =>
  LG_TYPE_LABELS[props.lg?.type] ?? t('profile.label')
)

const filledSections = computed(() =>
  Object.entries(props.profileSections)
    .filter(([key]) => props.profileContent[key])
    .map(([key, label]) => ({ key, label }))
)

function scrollTo(id) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

watchEffect(() => setSections(
  filledSections.value.map(sec => ({
    id:      `section-${sec.key}`,
    icon:    sectionIcons[sec.key] ?? '📄',
    label:   sec.label,
    onClick: () => scrollTo(`section-${sec.key}`),
  }))
))

let observer = null

onMounted(() => {
  const els = document.querySelectorAll('section[id^="section-"]')
  observer = new IntersectionObserver(
    entries => entries.forEach(e => { if (e.isIntersecting) setActive(e.target.id) }),
    { rootMargin: '-15% 0px -70% 0px' }
  )
  els.forEach(el => observer.observe(el))
})

onUnmounted(() => { observer?.disconnect(); clearSections() })
</script>
