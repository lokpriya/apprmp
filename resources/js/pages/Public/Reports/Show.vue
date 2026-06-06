<template>
  <div class="w-full px-4 sm:px-6 py-5 space-y-5">

    <!-- ── Page Header ── -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex items-center gap-3 min-w-0">
        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-xl shrink-0"
          :style="`background:${catMeta.bg}`">
          {{ catMeta.icon }}
        </div>
        <div class="min-w-0">
          <h1 class="text-[18px] font-bold text-[#1d273b] leading-tight">
            {{ props.title }}
          </h1>
          <p class="text-[12px] text-[#9aa0ac] mt-0.5">
            {{ props.title_en }}
            <span v-if="props.lg"> · {{ props.lg.name_en }}</span>
          </p>
        </div>
      </div>

      <!-- Download Excel button -->
      <a :href="exportUrl"
        class="inline-flex items-center gap-1.5 h-9 px-4 bg-emerald-600 hover:bg-emerald-700
               text-white text-[13px] font-medium rounded transition shrink-0 no-underline">
        <i class="fa-solid fa-file-excel text-[12px]"></i>
        {{ t('reports.downloadExcel') }}
      </a>

      <!-- Active filter summary chips -->
      <div v-if="hasFilters" class="flex flex-wrap items-center gap-1.5">
        <span class="text-[11px] text-[#9aa0ac] font-medium mr-1">
          {{ t('filter.showing') }}
        </span>
        <span v-if="filters.ward_id"
          class="inline-flex items-center gap-1 text-[12px] bg-[#206bc4]/8 text-[#206bc4]
                 border border-[#206bc4]/20 rounded px-2 py-0.5">
          <i class="fa-solid fa-map-pin text-[9px]"></i>
          {{ t('common.ward') }} {{ wardNumber }}
        </span>
        <span v-if="filters.settlement_id"
          class="inline-flex items-center gap-1 text-[12px] bg-purple-50 text-purple-600
                 border border-purple-200 rounded px-2 py-0.5">
          <i class="fa-solid fa-location-dot text-[9px]"></i>
          {{ t('filter.tole') }}
        </span>
      </div>
    </div>

    <!-- ── Anchor / Section Nav ── -->
    <div v-if="allSections.length > 1"
      class="bg-white border border-[#e6e7e9] rounded-lg px-4 py-2.5
             overflow-x-auto scrollbar-hide">
      <nav class="flex items-center gap-1 whitespace-nowrap min-w-max">
        <span class="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac] mr-2">
          {{ t('reports.jumpTo') }}
        </span>
        <button v-for="sec in allSections" :key="sec.id"
          @click="scrollTo(sec.id)"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded text-[12px]
                 font-medium transition whitespace-nowrap"
          :class="activeSection === sec.id
            ? 'bg-[#206bc4]/10 text-[#206bc4]'
            : 'text-[#626976] hover:text-[#1d273b] hover:bg-gray-50'">
          <span class="text-[13px]">{{ sec.icon }}</span>
          {{ sec.label }}
        </button>
      </nav>
    </div>

    <!-- ── Cross-Tab Tables ── -->
    <template v-if="tables.length">
      <div class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] mt-1">
        {{ t('reports.crossTabs') }}
      </div>
      <div v-for="table in tables" :key="table.id"
        :id="`section-${table.id}`" class="scroll-mt-16">
        <CrossTabTable
          :data="table.data"
          :title="table.title"
          :title-en="table.title_en"
          :chart-type="table.chart ?? 'bar'"
        />
      </div>
    </template>

    <!-- ── Frequency Distributions ── -->
    <template v-if="frequencies.length">
      <div class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] mt-2">
        {{ t('reports.distributions') }}
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div v-for="freq in frequencies" :key="freq.id"
          :id="`section-${freq.id}`" class="scroll-mt-16">
          <FrequencyBar
            :data="freq.data"
            :title="freq.title"
            :title-en="freq.title_en"
          />
        </div>
      </div>
    </template>

    <!-- ── Empty state ── -->
    <div v-if="!tables.length && !frequencies.length"
      class="bg-white rounded-lg border border-[#e6e7e9] px-6 py-16 text-center">
      <i class="fa-solid fa-chart-bar text-4xl text-[#e6e7e9] block mb-3"></i>
      <p class="text-[14px] font-medium text-[#626976]">{{ t('reports.noData') }}</p>
    </div>

    <!-- ── Bottom: navigate other categories ── -->
    <div class="border-t border-[#e6e7e9] pt-5">
      <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] mb-3">
        {{ t('reports.otherCategories') }}
      </p>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
        <a v-for="cat in otherCategories" :key="cat.href"
          :href="filteredHref(cat.href)"
          class="flex items-center gap-2.5 px-3 py-2.5 bg-white border border-[#e6e7e9]
                 rounded-lg hover:border-[#206bc4]/40 hover:bg-[#206bc4]/4 transition
                 no-underline group">
          <span class="text-[18px] shrink-0">{{ cat.icon }}</span>
          <span class="text-[13px] font-medium text-[#1d273b] group-hover:text-[#206bc4]
                       transition truncate">
            {{ t(cat.labelKey) }}
          </span>
          <i class="fa-solid fa-arrow-right text-[10px] text-[#c8ccd4]
                    group-hover:text-[#206bc4] ml-auto transition"></i>
        </a>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useLocale } from '@/i18n/index.js'
import PublicReportsLayout from '@/layouts/PublicReportsLayout.vue'
import CrossTabTable from '@/components/reports/CrossTabTable.vue'
import FrequencyBar  from '@/components/reports/FrequencyBar.vue'

defineOptions({ layout: PublicReportsLayout })

const { t } = useLocale()

const props = defineProps({
  lg:          { type: Object,  default: null },
  category:    { type: String,  default: '' },
  title:       { type: String,  default: '' },
  title_en:    { type: String,  default: '' },
  wards:       { type: Array,   default: () => [] },
  filters:     { type: Object,  default: () => ({}) },
  tables:      { type: Array,   default: () => [] },
  frequencies: { type: Array,   default: () => [] },
})

// Carry active ward/settlement filter into category navigation links
function filteredHref(base) {
  const p = new URLSearchParams()
  if (props.filters?.ward_id)       p.set('ward_id',       props.filters.ward_id)
  if (props.filters?.settlement_id) p.set('settlement_id', props.filters.settlement_id)
  const qs = p.toString()
  return base + (qs ? '?' + qs : '')
}

// ── Category metadata (icon + bg) ─────────────────────────────────────────────
const CAT_META = {
  'demographics':     { icon: '👥', bgRaw: '#eef2f8' },
  'age-groups':       { icon: '📅', bgRaw: '#ecfdf5' },
  'education':        { icon: '🎓', bgRaw: '#f9f0ff' },
  'health':           { icon: '🏥', bgRaw: '#fff5f5' },
  'employment':       { icon: '💼', bgRaw: '#fff4e6' },
  'housing':          { icon: '🏠', bgRaw: '#fffbe6' },
  'utilities':        { icon: '💧', bgRaw: '#ebf8ff' },
  'economics':        { icon: '💰', bgRaw: '#f0fff4' },
  'agriculture':      { icon: '🌾', bgRaw: '#f4f9f0' },
  'migration':        { icon: '✈️',  bgRaw: '#f0f4ff' },
  'child-development':{ icon: '👶', bgRaw: '#ecfdf5' },
  'documents':        { icon: '🪪', bgRaw: '#fff8f0' },
  'disaster':         { icon: '🔴', bgRaw: '#fff5f5' },
}
const catMeta = computed(() => CAT_META[props.category] ?? { icon: '📊', bgRaw: '#f8f9fb' })

// ── Filter helpers ──────────────────────────────────────────────────────────
const hasFilters = computed(() =>
  props.filters.ward_id || props.filters.settlement_id
)

const wardNumber = computed(() =>
  props.wards.find(w => String(w.id) === String(props.filters.ward_id))?.ward_number ?? props.filters.ward_id
)


// ── Export URL ───────────────────────────────────────────────────────────────
const exportUrl = computed(() => {
  const params = new URLSearchParams()
  const f = props.filters ?? {}
  if (f.ward_id)       params.set('ward_id',       f.ward_id)
  if (f.gender)        params.set('gender',         f.gender)
  if (f.age_min)       params.set('age_min',        f.age_min)
  if (f.age_max)       params.set('age_max',        f.age_max)
  if (f.settlement_id) params.set('settlement_id',  f.settlement_id)
  const qs = params.toString()
  return `/public/reports/export/${props.category}${qs ? '?' + qs : ''}`
})

// ── All sections for anchor nav ──────────────────────────────────────────────
const TABLE_ICON  = '⊞'
const FREQ_ICON   = '▦'

const allSections = computed(() => [
  ...props.tables.map(t => ({ id: t.id, label: t.title, icon: TABLE_ICON })),
  ...props.frequencies.map(f => ({ id: f.id, label: f.title, icon: FREQ_ICON })),
])

function scrollTo(id) {
  const el = document.getElementById(`section-${id}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  activeSection.value = id
}

// Active section (intersection observer)
const activeSection = ref(allSections.value[0]?.id ?? '')

let observer = null
onMounted(() => {
  observer = new IntersectionObserver(
    entries => {
      for (const e of entries) {
        if (e.isIntersecting) {
          activeSection.value = e.target.id.replace('section-', '')
        }
      }
    },
    { rootMargin: '-20% 0px -70% 0px', threshold: 0 }
  )
  allSections.value.forEach(sec => {
    const el = document.getElementById(`section-${sec.id}`)
    if (el) observer.observe(el)
  })
})
onUnmounted(() => observer?.disconnect())

// ── Other category links ──────────────────────────────────────────────────────
const ALL_CATEGORIES = [
  { route: 'demographics',     href: '/public/reports/demographics',      icon: '👥', labelKey: 'reports.cat.demographics'    },
  { route: 'age-groups',       href: '/public/reports/age-groups',        icon: '📅', labelKey: 'reports.cat.ageGroups'        },
  { route: 'education',        href: '/public/reports/education',          icon: '🎓', labelKey: 'reports.cat.education'        },
  { route: 'health',           href: '/public/reports/health',             icon: '🏥', labelKey: 'reports.cat.health'           },
  { route: 'employment',       href: '/public/reports/employment',         icon: '💼', labelKey: 'reports.cat.employment'       },
  { route: 'housing',          href: '/public/reports/housing',            icon: '🏠', labelKey: 'reports.cat.housing'          },
  { route: 'utilities',        href: '/public/reports/utilities',          icon: '💧', labelKey: 'reports.cat.utilities'        },
  { route: 'economics',        href: '/public/reports/economics',          icon: '💰', labelKey: 'reports.cat.economics'        },
  { route: 'agriculture',      href: '/public/reports/agriculture',        icon: '🌾', labelKey: 'reports.cat.agriculture'      },
  { route: 'migration',        href: '/public/reports/migration',          icon: '✈️',  labelKey: 'reports.cat.migration'         },
  { route: 'child-development',href: '/public/reports/child-development',  icon: '👶', labelKey: 'reports.cat.childDevelopment' },
  { route: 'documents',        href: '/public/reports/documents',          icon: '🪪', labelKey: 'reports.cat.documents'        },
  { route: 'disaster',         href: '/public/reports/disaster',           icon: '🔴', labelKey: 'reports.cat.disaster'         },
]

const otherCategories = computed(() =>
  ALL_CATEGORIES.filter(c => c.route !== props.category)
)
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
