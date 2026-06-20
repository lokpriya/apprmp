<template>
  <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-5 space-y-5">

    <!-- ══════════════════════════════════════════════════════
         KPI Cards Row
    ══════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <div v-for="card in statCards" :key="card.key"
        class="bg-white border border-[#e6e7e9] rounded-xl px-4 py-4 relative overflow-hidden
               hover:shadow-md transition-all">
        <!-- Color accent top bar -->
        <div class="absolute top-0 left-0 right-0 h-[3px] rounded-t-xl"
          :style="`background:${card.color}`"></div>
        <!-- Icon -->
        <div class="w-9 h-9 rounded-lg flex items-center justify-center text-xl mb-3 mt-1"
          :style="`background:${card.color}18`">
          {{ card.icon }}
        </div>
        <!-- Number -->
        <div class="text-[24px] font-bold leading-tight" :style="`color:${card.color}`">
          {{ fmt(props.summary?.[card.key] ?? 0) }}
        </div>
        <!-- Label -->
        <div class="text-[11px] font-semibold text-[#9aa0ac] mt-1 leading-tight">
          {{ card.label }}
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         Charts Row 1: Gender | Age Groups | Ward Population
    ══════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      <!-- Gender Distribution donut -->
      <div class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-[#e6e7e9] flex items-center justify-between">
          <div>
            <h3 class="text-[13px] font-semibold text-[#1d273b]">{{ t('stats.genderBreakdown') }}</h3>
            <p class="text-[11px] text-[#9aa0ac]">Gender Distribution</p>
          </div>
          <a href="/public/reports/demographics"
            class="text-[11px] text-[#206bc4] hover:underline no-underline">→</a>
        </div>
        <div class="px-5 py-4 flex items-center gap-4">
          <!-- Donut -->
          <div class="relative shrink-0">
            <canvas ref="genderCanvas" width="110" height="110"></canvas>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-[13px] font-bold text-[#1d273b]">{{ fmt(props.summary?.total_population ?? 0) }}</span>
              <span class="text-[9px] text-[#9aa0ac]">{{ t('stats.total') }}</span>
            </div>
          </div>
          <!-- Legend -->
          <div class="flex-1 space-y-2">
            <div v-for="g in genderLegend" :key="g.key" class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-1.5 min-w-0">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="`background:${g.color}`"></span>
                <span class="text-[12px] text-[#626976] truncate">{{ g.label }}</span>
              </div>
              <div class="text-right shrink-0">
                <span class="text-[13px] font-bold text-[#1d273b]">{{ fmt(g.count) }}</span>
                <span class="text-[10px] text-[#9aa0ac] ml-1">{{ g.pct }}%</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Age Group Distribution -->
      <div class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-[#e6e7e9] flex items-center justify-between">
          <div>
            <h3 class="text-[13px] font-semibold text-[#1d273b]">{{ t('reports.cat.ageGroups') }}</h3>
            <p class="text-[11px] text-[#9aa0ac]">Population by Age Group</p>
          </div>
          <a href="/public/reports/age-groups"
            class="text-[11px] text-[#206bc4] hover:underline no-underline">→</a>
        </div>
        <div class="px-5 py-3 space-y-1.5 max-h-[230px] overflow-y-auto scrollbar-hide">
          <div v-for="(item, i) in topAge" :key="item.key"
            class="flex items-center gap-2">
            <span class="text-[10px] text-[#9aa0ac] w-4 text-right shrink-0">{{ i + 1 }}</span>
            <span class="text-[12px] text-[#626976] w-16 shrink-0 truncate">{{ item.label }}</span>
            <div class="flex-1 h-[6px] bg-[#f4f6fb] rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all"
                :style="`width:${barPct(item.count, maxAge)}%;background:${ageColor(i)}`"></div>
            </div>
            <span class="text-[11px] font-semibold text-[#1d273b] w-10 text-right shrink-0">
              {{ fmt(item.count) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Ward Population -->
      <div class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-[#e6e7e9] flex items-center justify-between">
          <div>
            <h3 class="text-[13px] font-semibold text-[#1d273b]">{{ t('stats.wardSummary') }}</h3>
            <p class="text-[11px] text-[#9aa0ac]">Population by Ward</p>
          </div>
          <a href="/public/reports/ward-summary"
            class="text-[11px] text-[#206bc4] hover:underline no-underline">→</a>
        </div>
        <div class="px-5 py-3 space-y-1.5 max-h-[230px] overflow-y-auto scrollbar-hide">
          <div v-for="w in sortedWards" :key="w.ward_number" class="flex items-center gap-2">
            <span class="text-[10px] font-semibold text-white bg-[#206bc4] rounded px-1.5 py-0.5 shrink-0">
              {{ w.ward_number }}
            </span>
            <div class="flex-1 h-[6px] bg-[#f4f6fb] rounded-full overflow-hidden">
              <div class="h-full bg-[#206bc4] rounded-full"
                :style="`width:${barPct(w.population, maxWardPop)}%`"></div>
            </div>
            <span class="text-[11px] font-semibold text-[#1d273b] w-10 text-right shrink-0">
              {{ fmt(w.population) }}
            </span>
          </div>
        </div>
      </div>

    </div>

    <!-- ══════════════════════════════════════════════════════
         Charts Row 2: Education | Occupation | Key Indicators
    ══════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      <!-- Education Status donut -->
      <div class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-[#e6e7e9] flex items-center justify-between">
          <div>
            <h3 class="text-[13px] font-semibold text-[#1d273b]">{{ t('reports.cat.education') }}</h3>
            <p class="text-[11px] text-[#9aa0ac]">Education Status</p>
          </div>
          <a href="/public/reports/education"
            class="text-[11px] text-[#206bc4] hover:underline no-underline">→</a>
        </div>
        <div class="px-5 py-4 flex items-center gap-4">
          <div class="relative shrink-0">
            <canvas ref="eduCanvas" width="110" height="110"></canvas>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-[11px] text-[#9aa0ac] leading-none">{{ t('reports.cat.education') }}</span>
            </div>
          </div>
          <div class="flex-1 space-y-1.5 max-h-[130px] overflow-y-auto scrollbar-hide">
            <div v-for="(item, i) in topEdu" :key="item.key" class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-1.5 min-w-0">
                <span class="w-2 h-2 rounded-full shrink-0" :style="`background:${eduColors[i % eduColors.length]}`"></span>
                <span class="text-[11px] text-[#626976] truncate">{{ item.label }}</span>
              </div>
              <span class="text-[11px] font-bold text-[#1d273b] shrink-0">{{ fmt(item.count) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Occupation Breakdown -->
      <div class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-[#e6e7e9] flex items-center justify-between">
          <div>
            <h3 class="text-[13px] font-semibold text-[#1d273b]">{{ t('reports.cat.employment') }}</h3>
            <p class="text-[11px] text-[#9aa0ac]">Population by Occupation</p>
          </div>
          <a href="/public/reports/employment"
            class="text-[11px] text-[#206bc4] hover:underline no-underline">→</a>
        </div>
        <div class="px-4 py-4">
          <div class="grid grid-cols-3 gap-2">
            <div v-for="(occ, i) in topOcc" :key="occ.key"
              class="flex flex-col items-center gap-1.5 p-2 rounded-lg hover:bg-[#f8f9fb] transition">
              <div class="w-9 h-9 rounded-xl flex items-center justify-center text-lg"
                :style="`background:${occColors[i % occColors.length]}20`">
                {{ occIcons[i % occIcons.length] }}
              </div>
              <span class="text-[12px] font-bold text-[#1d273b]">{{ fmt(occ.count) }}</span>
              <span class="text-[10px] text-[#9aa0ac] text-center leading-tight line-clamp-2">{{ occ.label }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Key Indicators -->
      <div class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-[#e6e7e9]">
          <h3 class="text-[13px] font-semibold text-[#1d273b]">{{ t('stats.keyIndicators') }}</h3>
          <p class="text-[11px] text-[#9aa0ac]">Key Indicators</p>
        </div>
        <div class="px-5 py-3 space-y-3">
          <div v-for="kpi in kpiList" :key="kpi.key"
            class="flex items-center justify-between gap-3 py-2 border-b border-[#f4f6fb] last:border-0">
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                :style="`background:${kpi.color}18`">
                <i :class="[kpi.icon, 'text-[13px]']" :style="`color:${kpi.color}`"></i>
              </div>
              <span class="text-[12px] text-[#626976] leading-tight">{{ kpi.label }}</span>
            </div>
            <span class="text-[16px] font-bold shrink-0" :style="`color:${kpi.color}`">
              {{ kpi.value }}
            </span>
          </div>
        </div>
      </div>

    </div>

    <!-- ══════════════════════════════════════════════════════
         All Report Categories Grid
    ══════════════════════════════════════════════════════ -->
    <div>
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-[15px] font-bold text-[#1d273b]">{{ t('reports.allCategories') }}</h2>
        <span class="text-[12px] text-[#9aa0ac]">{{ categories.length }} {{ t('reports.categories') }}</span>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
        <a v-for="cat in categories" :key="cat.href"
          :href="filteredHref(cat.href)"
          class="group bg-white border border-[#e6e7e9] rounded-xl p-4
                 hover:border-[#206bc4]/40 hover:shadow-sm transition-all no-underline">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-xl mb-3"
            :style="`background:${cat.bgRaw}`">
            {{ cat.icon }}
          </div>
          <h3 class="text-[13px] font-semibold text-[#1d273b] leading-snug mb-0.5
                     group-hover:text-[#206bc4] transition line-clamp-2">
            {{ t(cat.labelKey) }}
          </h3>
          <p class="text-[11px] text-[#9aa0ac] leading-relaxed line-clamp-2">
            {{ t(cat.descKey) }}
          </p>
        </a>
      </div>
    </div>

    <!-- Data note -->
    <div class="bg-[#f0f4fa] rounded-lg px-5 py-3.5 flex items-start gap-3 text-[12px] text-[#626976]">
      <i class="fa-solid fa-circle-info text-[#206bc4] text-sm mt-0.5 shrink-0"></i>
      <span>{{ t('reports.indexNote') }}</span>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import Chart from 'chart.js/auto'
import { useLocale } from '@/i18n/index.js'
import PublicReportsLayout from '@/layouts/PublicReportsLayout.vue'

defineOptions({ layout: PublicReportsLayout })

const { t } = useLocale()

const props = defineProps({
  lg:             { type: Object, default: null },
  summary:        { type: Object, default: () => ({}) },
  genderData:     { type: Array,  default: () => [] },
  ageData:        { type: Array,  default: () => [] },
  occupationData: { type: Array,  default: () => [] },
  educationData:  { type: Array,  default: () => [] },
  wardData:       { type: Array,  default: () => [] },
  keyIndicators:  { type: Object, default: () => ({}) },
  wards:          { type: Array,  default: () => [] },
  filters:        { type: Object, default: () => ({}) },
})

const fmt = n => Number(n ?? 0).toLocaleString()

function filteredHref(base) {
  const p = new URLSearchParams()
  if (props.filters?.ward_id)       p.set('ward_id',       props.filters.ward_id)
  if (props.filters?.settlement_id) p.set('settlement_id', props.filters.settlement_id)
  const qs = p.toString()
  return base + (qs ? '?' + qs : '')
}

function barPct(val, max) {
  return max > 0 ? Math.max(4, Math.round(val / max * 100)) : 0
}

// ── Stat cards ─────────────────────────────────────────────────────────────
const statCards = computed(() => [
  { key: 'total_households',   label: t('stats.households'),   color: '#206bc4', icon: '🏠' },
  { key: 'total_population',   label: t('stats.population'),   color: '#1d273b', icon: '👥' },
  { key: 'total_male',         label: t('stats.male'),         color: '#4299e1', icon: '👨' },
  { key: 'total_female',       label: t('stats.female'),       color: '#f76707', icon: '👩' },
  { key: 'total_other',        label: t('reports.otherGender'),color: '#9aa0ac', icon: '⚧' },
  { key: 'total_institutions', label: t('stats.institutions'), color: '#2fb344', icon: '🏫' },
])

// ── Gender donut ────────────────────────────────────────────────────────────
const GENDER_COLORS = { male: '#4299e1', female: '#f76707', other: '#9aa0ac' }

const genderLegend = computed(() => {
  const total = props.summary?.total_population ?? 0
  return props.genderData.map(g => ({
    key:   g.key,
    label: g.label,
    count: g.count,
    color: GENDER_COLORS[g.key] ?? '#c0c0c0',
    pct:   total > 0 ? ((g.count / total) * 100).toFixed(1) : '0.0',
  }))
})

const genderCanvas = ref(null)
let genderChart = null

function buildGenderChart() {
  if (!genderCanvas.value || !props.genderData.length) return
  genderChart?.destroy()
  genderChart = new Chart(genderCanvas.value, {
    type: 'doughnut',
    data: {
      labels: genderLegend.value.map(g => g.label),
      datasets: [{ data: genderLegend.value.map(g => g.count),
        backgroundColor: genderLegend.value.map(g => g.color),
        borderWidth: 2, borderColor: '#ffffff', hoverOffset: 4 }],
    },
    options: {
      cutout: '68%', responsive: false,
      plugins: { legend: { display: false }, tooltip: {
        callbacks: { label: ctx => ` ${ctx.label}: ${fmt(ctx.raw)}` }
      }},
    },
  })
}

// ── Age group ───────────────────────────────────────────────────────────────
const AGE_COLORS = ['#3b82f6','#f59e0b','#10b981','#ef4444','#8b5cf6','#06b6d4','#f97316','#6366f1','#14b8a6','#e11d48','#84cc16']
const ageColor = i => AGE_COLORS[i % AGE_COLORS.length]
const topAge   = computed(() => [...props.ageData].sort((a, b) => b.count - a.count).slice(0, 10))
const maxAge   = computed(() => Math.max(...(props.ageData.map(a => a.count)), 1))

// ── Ward population ─────────────────────────────────────────────────────────
const sortedWards  = computed(() => [...props.wardData].sort((a, b) => a.ward_number - b.ward_number))
const maxWardPop   = computed(() => Math.max(...(props.wardData.map(w => w.population)), 1))

// ── Education donut ─────────────────────────────────────────────────────────
const eduColors = ['#10b981','#3b82f6','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#6366f1','#e11d48']
const topEdu    = computed(() => [...props.educationData].slice(0, 7))

const eduCanvas = ref(null)
let eduChart = null

function buildEduChart() {
  if (!eduCanvas.value || !props.educationData.length) return
  eduChart?.destroy()
  const items = topEdu.value
  eduChart = new Chart(eduCanvas.value, {
    type: 'doughnut',
    data: {
      labels: items.map(e => e.label),
      datasets: [{ data: items.map(e => e.count),
        backgroundColor: items.map((_, i) => eduColors[i % eduColors.length]),
        borderWidth: 2, borderColor: '#ffffff', hoverOffset: 4 }],
    },
    options: {
      cutout: '65%', responsive: false,
      plugins: { legend: { display: false }, tooltip: {
        callbacks: { label: ctx => ` ${ctx.label}: ${fmt(ctx.raw)}` }
      }},
    },
  })
}

// ── Occupation ──────────────────────────────────────────────────────────────
const occColors = ['#10b981','#3b82f6','#f59e0b','#ef4444','#8b5cf6','#06b6d4']
const occIcons  = ['🌾','💼','🏥','📚','⚙️','🏪']
const topOcc    = computed(() => props.occupationData.slice(0, 6))

// ── Key indicators ──────────────────────────────────────────────────────────
const kpiList = computed(() => [
  {
    key: 'sex_ratio', icon: 'fa-solid fa-venus-mars',
    label: t('stats.sexRatio'), color: '#206bc4',
    value: props.keyIndicators?.sex_ratio ?? '—',
  },
  {
    key: 'literacy', icon: 'fa-solid fa-graduation-cap',
    label: t('stats.literacyRate'), color: '#2fb344',
    value: props.keyIndicators?.literacy_rate ? `${props.keyIndicators.literacy_rate}%` : '—',
  },
  {
    key: 'avg_hh', icon: 'fa-solid fa-house-user',
    label: t('stats.avgHhSize'), color: '#f59e0b',
    value: props.keyIndicators?.avg_hh_size ?? '—',
  },
  {
    key: 'wards', icon: 'fa-solid fa-map-pin',
    label: t('common.ward') + ' ' + t('stats.total'),
    color: '#a855f7',
    value: props.summary?.total_wards ?? '—',
  },
])

// ── Category cards ────────────────────────────────────────────────────────────
const categories = [
  { href: '/public/reports/demographics',       icon: '👥', bgRaw: '#eef2f8', labelKey: 'reports.cat.demographics',       descKey: 'reports.desc.demographics'    },
  { href: '/public/reports/age-groups',         icon: '📅', bgRaw: '#ecfdf5', labelKey: 'reports.cat.ageGroups',          descKey: 'reports.desc.ageGroups'       },
  { href: '/public/reports/education',          icon: '🎓', bgRaw: '#f9f0ff', labelKey: 'reports.cat.education',          descKey: 'reports.desc.education'       },
  { href: '/public/reports/health',             icon: '🏥', bgRaw: '#fff5f5', labelKey: 'reports.cat.health',             descKey: 'reports.desc.health'          },
  { href: '/public/reports/employment',         icon: '💼', bgRaw: '#fff4e6', labelKey: 'reports.cat.employment',         descKey: 'reports.desc.employment'      },
  { href: '/public/reports/housing',            icon: '🏠', bgRaw: '#fffbe6', labelKey: 'reports.cat.housing',            descKey: 'reports.desc.housing'         },
  { href: '/public/reports/utilities',          icon: '💧', bgRaw: '#ebf8ff', labelKey: 'reports.cat.utilities',          descKey: 'reports.desc.utilities'       },
  { href: '/public/reports/economics',          icon: '💰', bgRaw: '#f0fff4', labelKey: 'reports.cat.economics',          descKey: 'reports.desc.economics'       },
  { href: '/public/reports/agriculture',        icon: '🌾', bgRaw: '#f4f9f0', labelKey: 'reports.cat.agriculture',        descKey: 'reports.desc.agriculture'     },
  { href: '/public/reports/migration',          icon: '✈️',  bgRaw: '#f0f4ff', labelKey: 'reports.cat.migration',          descKey: 'reports.desc.migration'       },
  { href: '/public/reports/child-development',  icon: '👶', bgRaw: '#ecfdf5', labelKey: 'reports.cat.childDevelopment',   descKey: 'reports.desc.childDevelopment'},
  { href: '/public/reports/documents',          icon: '🪪', bgRaw: '#fff8f0', labelKey: 'reports.cat.documents',          descKey: 'reports.desc.documents'       },
  { href: '/public/reports/disaster',           icon: '🔴', bgRaw: '#fff5f5', labelKey: 'reports.cat.disaster',           descKey: 'reports.desc.disaster'        },
  { href: '/public/reports/women-empowerment',  icon: '👩', bgRaw: '#fce7f3', labelKey: 'reports.cat.womenEmpowerment',   descKey: 'reports.desc.womenEmpowerment'},
  { href: '/public/reports/digital-connectivity',icon:'📱', bgRaw: '#eff6ff', labelKey: 'reports.cat.digitalConnectivity',descKey: 'reports.desc.digitalConnectivity'},
  { href: '/public/reports/social-security',    icon: '🛡️', bgRaw: '#f0fdf4', labelKey: 'reports.cat.socialSecurity',     descKey: 'reports.desc.socialSecurity'  },
  { href: '/public/reports/livestock-detail',   icon: '🐄', bgRaw: '#fefce8', labelKey: 'reports.cat.livestockDetail',    descKey: 'reports.desc.livestockDetail' },
  { href: '/public/reports/land-agriculture',   icon: '🌱', bgRaw: '#f0fdf4', labelKey: 'reports.cat.landAgriculture',    descKey: 'reports.desc.landAgriculture' },
  { href: '/public/reports/development-index',  icon: '📈', bgRaw: '#f0f9ff', labelKey: 'reports.cat.developmentIndex',   descKey: 'reports.desc.developmentIndex'},
]

// ── Mount charts ─────────────────────────────────────────────────────────────
onMounted(() => {
  buildGenderChart()
  buildEduChart()
})

watch(() => props.genderData, buildGenderChart)
watch(() => props.educationData, buildEduChart)
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
