<template>
  <div class="w-full px-4 sm:px-6 py-6 space-y-5">

    <!-- ── Print-only header ────────────────────────────────────────────── -->
    <div class="print-only hidden">
      <div class="flex items-center justify-between border-b-2 border-gray-800 pb-3 mb-4">
        <div>
          <p class="text-xl font-bold text-gray-900">{{ props.lg?.name_np }}</p>
          <p class="text-sm text-gray-500">{{ props.lg?.name_en }}</p>
        </div>
        <div class="text-right">
          <p class="text-base font-bold text-gray-800">सर्वेक्षण राउन्ड तुलना</p>
          <p class="text-xs text-gray-500 mt-0.5">Survey Round Comparison</p>
          <p class="text-xs text-gray-400 mt-0.5">मिति: {{ printDate }}</p>
        </div>
      </div>
    </div>

    <!-- ── Screen header ────────────────────────────────────────────────── -->
    <div class="no-print flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-[18px] font-bold text-[#1d273b]">सर्वेक्षण राउन्ड तुलना</h1>
        <p class="text-[12px] text-[#9aa0ac] mt-0.5">Survey Round Comparison — ward-wise indicators across rounds</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="printReport"
          class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#1d273b] hover:bg-[#2d3a4f] text-white text-[12px] font-medium rounded-md transition-colors">
          <i class="fa-solid fa-print text-[11px]"></i> PDF / प्रिन्ट
        </button>
        <a href="/public/reports"
          class="inline-flex items-center gap-1.5 text-[12px] text-[#206bc4] hover:underline">
          <i class="fa-solid fa-arrow-left text-[10px]"></i> प्रतिवेदन सूची
        </a>
      </div>
    </div>

    <!-- ── No rounds empty state ─────────────────────────────────────────── -->
    <div v-if="!props.rounds.length"
      class="bg-white border border-[#e6e7e9] rounded-xl p-14 text-center shadow-sm">
      <i class="fa-solid fa-flag text-4xl text-gray-200 mb-3 block"></i>
      <p class="text-[14px] font-semibold text-gray-500">कुनै राउन्ड सुरक्षित छैन</p>
      <p class="text-[12px] text-gray-400 mt-1">
        प्रशासन → सेटिङ → सर्वेक्षण राउन्ड मा गएर राउन्ड बन्द गर्नुहोस्।
      </p>
    </div>

    <template v-else>

      <!-- ── Metric selector ───────────────────────────────────────────── -->
      <div class="no-print bg-white border border-[#e6e7e9] rounded-xl px-4 py-3 flex flex-wrap items-center gap-2 shadow-sm">
        <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-[0.12em] mr-1">सूचक:</span>
        <button v-for="m in METRICS" :key="m.key"
          @click="activeMetric = m.key"
          class="px-3 py-1.5 rounded-full text-[12px] font-medium transition-colors"
          :class="activeMetric === m.key
            ? 'bg-[#206bc4] text-white shadow-sm'
            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
          {{ m.label }}
        </button>
      </div>

      <!-- ── Round summary cards ────────────────────────────────────────── -->
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <div v-for="r in props.rounds" :key="r.number"
          class="bg-white border border-[#e6e7e9] rounded-xl p-4 shadow-sm">
          <div class="flex items-start justify-between gap-2 mb-3">
            <div class="min-w-0">
              <p class="text-[13px] font-bold text-[#1d273b] truncate">{{ r.label_np }}</p>
              <p class="text-[11px] text-gray-400 truncate">{{ r.label_en }}</p>
            </div>
            <span class="shrink-0 text-[11px] font-semibold bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">
              राउन्ड {{ r.number }}
            </span>
          </div>
          <div class="grid grid-cols-2 gap-2 text-center">
            <div class="bg-gray-50 rounded-lg py-2">
              <p class="text-[18px] font-bold text-[#1d273b]">{{ r.total_households.toLocaleString() }}</p>
              <p class="text-[10px] text-gray-400">घरधुरी</p>
            </div>
            <div class="bg-indigo-50 rounded-lg py-2">
              <p class="text-[18px] font-bold text-indigo-700">{{ r.total_population.toLocaleString() }}</p>
              <p class="text-[10px] text-indigo-400">जनसंख्या</p>
            </div>
          </div>
          <p class="text-[10px] text-gray-400 mt-2 text-center">{{ formatDate(r.closed_at) }}</p>
        </div>
      </div>

      <!-- ── Comparison table ──────────────────────────────────────────── -->
      <div class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-[#e6e7e9] bg-gray-50/70">
          <p class="text-[13px] font-semibold text-[#1d273b]">
            वडागत तुलना — {{ activeMetricObj.label }}
          </p>
          <p class="text-[11px] text-[#9aa0ac] mt-0.5">
            Ward-wise comparison: {{ activeMetricObj.label_en }}
          </p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-[13px]">
            <thead>
              <tr class="border-b border-[#e6e7e9] bg-gray-50/60">
                <th class="px-4 py-3 text-left font-semibold text-gray-500 text-[11px] uppercase tracking-[0.1em] whitespace-nowrap sticky left-0 bg-gray-50/60">
                  वडा
                </th>
                <th v-for="r in props.rounds" :key="r.number"
                  class="px-4 py-3 text-center font-semibold text-gray-700 text-[12px] whitespace-nowrap min-w-[120px]">
                  {{ r.label_np }}
                  <span class="block text-[10px] text-gray-400 font-normal">{{ r.label_en }}</span>
                </th>
                <th v-if="props.rounds.length > 1"
                  class="px-4 py-3 text-center font-semibold text-gray-500 text-[11px] uppercase tracking-[0.1em] whitespace-nowrap">
                  बदलाव
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(ward, i) in allWards" :key="ward"
                class="border-b border-[#f0f1f3] transition-colors hover:bg-blue-50/20"
                :class="i % 2 === 0 ? 'bg-white' : 'bg-gray-50/40'">
                <td class="px-4 py-2.5 font-semibold text-[#1d273b] whitespace-nowrap sticky left-0"
                  :class="i % 2 === 0 ? 'bg-white' : 'bg-gray-50/40'">
                  वडा {{ ward }}
                </td>
                <td v-for="r in props.rounds" :key="r.number" class="px-4 py-2.5 text-center">
                  <span v-if="isPercent"
                    class="inline-block px-2.5 py-0.5 rounded-full text-[12px] font-bold"
                    :class="pctBadge(wardValue(r, ward))">
                    {{ wardValue(r, ward) }}%
                  </span>
                  <span v-else class="text-[13px] font-semibold text-[#1d273b] tabular-nums">
                    {{ wardValue(r, ward).toLocaleString() }}
                  </span>
                </td>
                <td v-if="props.rounds.length > 1" class="px-4 py-2.5 text-center">
                  <span class="inline-flex items-center gap-1 text-[12px] font-semibold"
                    :class="deltaColor(wardDelta(ward))">
                    <i :class="deltaIcon(wardDelta(ward))" class="text-[10px]"></i>
                    {{ deltaDisplay(wardDelta(ward)) }}
                  </span>
                </td>
              </tr>

              <!-- Municipality total / average row -->
              <tr class="bg-indigo-50/60 border-t-2 border-indigo-100">
                <td class="px-4 py-2.5 font-bold text-[#1d273b] text-[12px] uppercase tracking-[0.08em] sticky left-0 bg-indigo-50/60">
                  जम्मा / औसत
                </td>
                <td v-for="r in props.rounds" :key="r.number" class="px-4 py-2.5 text-center">
                  <span v-if="isPercent"
                    class="inline-block px-2.5 py-0.5 rounded-full text-[12px] font-bold"
                    :class="pctBadge(munValue(r))">
                    {{ munValue(r) }}%
                  </span>
                  <span v-else class="text-[13px] font-bold text-indigo-700 tabular-nums">
                    {{ munValue(r).toLocaleString() }}
                  </span>
                </td>
                <td v-if="props.rounds.length > 1" class="px-4 py-2.5 text-center">
                  <span class="inline-flex items-center gap-1 text-[12px] font-bold"
                    :class="deltaColor(munDelta)">
                    <i :class="deltaIcon(munDelta)" class="text-[10px]"></i>
                    {{ deltaDisplay(munDelta) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Trend bar chart (2+ rounds) ───────────────────────────────── -->
      <div v-if="props.rounds.length > 1"
        class="no-print bg-white border border-[#e6e7e9] rounded-xl overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-[#e6e7e9] bg-gray-50/70">
          <p class="text-[13px] font-semibold text-[#1d273b]">
            प्रवृत्ति — {{ activeMetricObj.label }}
          </p>
          <p class="text-[11px] text-[#9aa0ac] mt-0.5">Trend across rounds by ward</p>
        </div>
        <div class="p-4 chart-container" style="height:280px;">
          <canvas ref="chartEl"></canvas>
        </div>
      </div>

    </template>

    <!-- ── Print footer ──────────────────────────────────────────────────── -->
    <div class="print-only hidden text-center text-[10px] text-gray-400 pt-4 border-t border-gray-200">
      यो प्रतिवेदन {{ props.lg?.name_np }} को डेटा प्रणालीबाट स्वचालित रूपमा तयार गरिएको हो।
    </div>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import PublicReportsLayout from '@/layouts/PublicReportsLayout.vue'

defineOptions({ layout: PublicReportsLayout })

const props = defineProps({
  lg:     { type: Object, default: null },
  rounds: { type: Array,  default: () => [] },
})

// ── Metrics definition ────────────────────────────────────────────────────────
const METRICS = [
  { key: 'households',       label: 'घरधुरी',        label_en: 'Households',     pct: false },
  { key: 'population',       label: 'जनसंख्या',      label_en: 'Population',     pct: false },
  { key: 'literacy_pct',     label: 'साक्षरता %',    label_en: 'Literacy %',     pct: true  },
  { key: 'safe_water_pct',   label: 'खानेपानी %',    label_en: 'Safe Water %',   pct: true  },
  { key: 'toilet_pct',       label: 'शौचालय %',      label_en: 'Toilet %',       pct: true  },
  { key: 'internet_pct',     label: 'इन्टरनेट %',    label_en: 'Internet %',     pct: true  },
  { key: 'bank_account_pct', label: 'बैंक खाता %',   label_en: 'Bank Account %', pct: true  },
]

const activeMetric    = ref('population')
const activeMetricObj = computed(() => METRICS.find(m => m.key === activeMetric.value) ?? METRICS[0])
const isPercent       = computed(() => activeMetricObj.value.pct)

// ── Ward list across all rounds ───────────────────────────────────────────────
const allWards = computed(() => {
  const nums = new Set()
  props.rounds.forEach(r => (r.by_ward ?? []).forEach(w => nums.add(w.ward_number)))
  return [...nums].sort((a, b) => a - b)
})

// ── Value helpers ─────────────────────────────────────────────────────────────
function wardValue(round, wardNum) {
  const w = (round.by_ward ?? []).find(w => w.ward_number === wardNum)
  return w ? Number(w[activeMetric.value] ?? 0) : 0
}

function munValue(round) {
  const wards = round.by_ward ?? []
  if (!isPercent.value) {
    return wards.reduce((s, w) => s + Number(w[activeMetric.value] ?? 0), 0)
  }
  // weighted average weighted by households or population
  const weight = activeMetric.value === 'households' ? 'households' : 'population'
  const num = wards.reduce((s, w) => s + Number(w[activeMetric.value] ?? 0) * Number(w[weight] ?? 0), 0)
  const den = wards.reduce((s, w) => s + Number(w[weight] ?? 0), 0)
  return den > 0 ? Math.round(num / den * 10) / 10 : 0
}

// ── Delta helpers (last round vs first round) ─────────────────────────────────
function wardDelta(wardNum) {
  if (props.rounds.length < 2) return 0
  const first = wardValue(props.rounds[0], wardNum)
  const last  = wardValue(props.rounds[props.rounds.length - 1], wardNum)
  const diff  = last - first
  return isPercent.value ? Math.round(diff * 10) / 10 : diff
}

const munDelta = computed(() => {
  if (props.rounds.length < 2) return 0
  const first = munValue(props.rounds[0])
  const last  = munValue(props.rounds[props.rounds.length - 1])
  const diff  = last - first
  return isPercent.value ? Math.round(diff * 10) / 10 : diff
})

function deltaDisplay(delta) {
  if (delta === 0) return '—'
  return (delta > 0 ? '+' : '') + delta + (isPercent.value ? '%' : '')
}

function deltaIcon(delta) {
  if (delta > 0) return 'fa-solid fa-arrow-trend-up'
  if (delta < 0) return 'fa-solid fa-arrow-trend-down'
  return 'fa-solid fa-minus'
}

function deltaColor(delta) {
  if (delta > 0) return 'text-emerald-600'
  if (delta < 0) return 'text-red-500'
  return 'text-gray-400'
}

// ── Badge colors for percentages ──────────────────────────────────────────────
function pctBadge(p) {
  if (p >= 80) return 'bg-green-100 text-green-700'
  if (p >= 60) return 'bg-teal-100 text-teal-700'
  if (p >= 40) return 'bg-amber-100 text-amber-700'
  if (p > 0)   return 'bg-red-100 text-red-600'
  return 'bg-gray-100 text-gray-400'
}

// ── Chart.js bar chart ────────────────────────────────────────────────────────
const chartEl  = ref(null)
let   chartInst = null

const CHART_COLORS = ['#206bc4', '#22c55e', '#f59e0b', '#f97316', '#a855f7', '#06b6d4']

async function buildChart() {
  if (!chartEl.value || props.rounds.length < 2) return
  const { Chart, CategoryScale, LinearScale, BarElement, Tooltip, Legend } = await import('chart.js')
  Chart.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend)
  if (chartInst) { chartInst.destroy(); chartInst = null }

  const labels   = allWards.value.map(w => `वडा ${w}`)
  const datasets = props.rounds.map((r, idx) => ({
    label:           r.label_np,
    data:            allWards.value.map(w => wardValue(r, w)),
    backgroundColor: CHART_COLORS[idx % CHART_COLORS.length] + '99',
    borderColor:     CHART_COLORS[idx % CHART_COLORS.length],
    borderWidth:     1.5,
    borderRadius:    4,
  }))

  chartInst = new Chart(chartEl.value, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive:          true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top', labels: { font: { size: 12 }, usePointStyle: true } },
        tooltip: {
          callbacks: {
            label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y}${isPercent.value ? '%' : ''}`,
          },
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          max:         isPercent.value ? 100 : undefined,
          ticks:       { font: { size: 11 }, callback: v => v + (isPercent.value ? '%' : '') },
          grid:        { color: '#f0f1f3' },
        },
        x: { ticks: { font: { size: 11 } }, grid: { display: false } },
      },
    },
  })
}

onMounted(()    => nextTick(buildChart))
onUnmounted(()  => { if (chartInst) chartInst.destroy() })
watch(activeMetric, () => nextTick(buildChart))

// ── Utilities ─────────────────────────────────────────────────────────────────
const printDate = new Date().toLocaleDateString('ne-NP', { year: 'numeric', month: 'long', day: 'numeric' })

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('ne-NP', { year: 'numeric', month: 'short', day: 'numeric' })
}

function printReport() { window.print() }
</script>

<style scoped>
@media print {
  .no-print   { display: none !important; }
  .print-only { display: block !important; }
  .chart-container { page-break-inside: avoid; }
}
</style>
