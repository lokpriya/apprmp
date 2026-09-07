<template>
  <div class="w-full px-4 sm:px-6 py-6 space-y-5">

    <!-- ── Screen header ────────────────────────────────────────────────── -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-[18px] font-bold text-[#1d273b]">वडा तुलना</h1>
        <p class="text-[12px] text-[#9aa0ac] mt-0.5">Ward Comparison — compare any two wards side by side</p>
      </div>
      <a href="/public/reports/ward-summary"
        class="inline-flex items-center gap-1.5 text-[12px] text-[#206bc4] hover:underline">
        <i class="fa-solid fa-arrow-left text-[10px]"></i> वडागत सारांश
      </a>
    </div>

    <!-- ── Ward picker ──────────────────────────────────────────────────── -->
    <div class="bg-white border border-[#e6e7e9] rounded-xl px-5 py-4 shadow-sm">
      <p class="text-[12px] font-semibold text-gray-500 uppercase tracking-[0.1em] mb-3">तुलना गर्न वडा छान्नुहोस्</p>
      <div class="flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-[140px]">
          <label class="block text-[11px] text-gray-400 mb-1">वडा क (A)</label>
          <select v-model="wardAId"
            class="w-full h-10 rounded-lg border border-[#e6e7e9] bg-white px-3 text-[13px] text-[#1d273b] outline-none focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
            <option value="">— वडा छान्नुहोस् —</option>
            <option v-for="w in props.wardSummary" :key="w.ward_id" :value="w.ward_id">
              वडा {{ w.ward_number }}
            </option>
          </select>
        </div>
        <div class="flex items-end pb-1">
          <span class="text-[18px] font-bold text-gray-300">⚖️</span>
        </div>
        <div class="flex-1 min-w-[140px]">
          <label class="block text-[11px] text-gray-400 mb-1">वडा ख (B)</label>
          <select v-model="wardBId"
            class="w-full h-10 rounded-lg border border-[#e6e7e9] bg-white px-3 text-[13px] text-[#1d273b] outline-none focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
            <option value="">— वडा छान्नुहोस् —</option>
            <option v-for="w in props.wardSummary" :key="w.ward_id" :value="w.ward_id">
              वडा {{ w.ward_number }}
            </option>
          </select>
        </div>
        <button v-if="wardAId && wardBId" @click="swapWards"
          class="h-10 px-3 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition text-[12px]"
          title="अदलबदल">
          <i class="fa-solid fa-arrows-left-right"></i>
        </button>
      </div>
    </div>

    <!-- ── Empty / not-yet-selected state ───────────────────────────────── -->
    <div v-if="!wardA || !wardB"
      class="bg-white border border-dashed border-[#c8ccd4] rounded-xl p-14 text-center">
      <p class="text-[28px] mb-2">⚖️</p>
      <p class="text-[14px] font-semibold text-gray-500">माथि दुईवटा वडा छान्नुहोस्</p>
      <p class="text-[12px] text-gray-400 mt-1">Select two wards to compare side by side</p>
    </div>

    <template v-else>

      <!-- ── Same ward warning ─────────────────────────────────────────── -->
      <div v-if="wardAId === wardBId"
        class="flex items-center gap-2 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-[13px] text-amber-700">
        <i class="fa-solid fa-triangle-exclamation shrink-0"></i>
        एउटै वडा दुईपटक छनोट गरिएको छ — फरक वडा छान्नुहोस्।
      </div>

      <!-- ── At-a-glance stat cards ────────────────────────────────────── -->
      <div class="grid grid-cols-2 gap-3 sm:gap-4">
        <!-- Ward A card -->
        <div class="bg-white border-2 border-[#206bc4]/30 rounded-xl p-4 shadow-sm">
          <div class="flex items-center gap-2 mb-3">
            <div class="w-8 h-8 rounded-lg bg-[#206bc4] flex items-center justify-center shrink-0">
              <span class="text-white font-bold text-[13px]">{{ wardA.ward_number }}</span>
            </div>
            <div>
              <p class="text-[13px] font-bold text-[#1d273b]">वडा {{ wardA.ward_number }}</p>
              <p class="text-[10px] text-gray-400">Ward A</p>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div v-for="stat in quickStats(wardA)" :key="stat.label"
              class="bg-gray-50 rounded-lg px-2.5 py-2 text-center">
              <p class="text-[16px] sm:text-[20px] font-bold text-[#1d273b] tabular-nums">{{ stat.value }}</p>
              <p class="text-[10px] text-gray-400 mt-0.5">{{ stat.label }}</p>
            </div>
          </div>
        </div>

        <!-- Ward B card -->
        <div class="bg-white border-2 border-emerald-300/60 rounded-xl p-4 shadow-sm">
          <div class="flex items-center gap-2 mb-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center shrink-0">
              <span class="text-white font-bold text-[13px]">{{ wardB.ward_number }}</span>
            </div>
            <div>
              <p class="text-[13px] font-bold text-[#1d273b]">वडा {{ wardB.ward_number }}</p>
              <p class="text-[10px] text-gray-400">Ward B</p>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div v-for="stat in quickStats(wardB)" :key="stat.label"
              class="bg-gray-50 rounded-lg px-2.5 py-2 text-center">
              <p class="text-[16px] sm:text-[20px] font-bold text-[#1d273b] tabular-nums">{{ stat.value }}</p>
              <p class="text-[10px] text-gray-400 mt-0.5">{{ stat.label }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Indicator comparison rows ─────────────────────────────────── -->
      <div class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-[#e6e7e9] bg-gray-50/70">
          <p class="text-[13px] font-semibold text-[#1d273b]">सूचक तुलना</p>
          <p class="text-[11px] text-[#9aa0ac] mt-0.5">Indicator comparison — blue bar = Ward A, green bar = Ward B</p>
        </div>
        <div class="divide-y divide-[#f0f1f3]">
          <div v-for="ind in INDICATORS" :key="ind.key" class="px-5 py-4">
            <div class="flex items-center justify-between mb-1.5">
              <span class="text-[12px] font-semibold text-gray-600">{{ ind.label }}</span>
              <span class="text-[11px] text-gray-400">{{ ind.label_en }}</span>
            </div>

            <!-- Ward A bar -->
            <div class="flex items-center gap-3 mb-2">
              <span class="w-14 text-[11px] font-bold text-[#206bc4] text-right shrink-0">
                {{ formatVal(ind, wardA) }}{{ ind.pct ? '%' : '' }}
              </span>
              <div class="flex-1 bg-gray-100 rounded-full h-3 overflow-hidden">
                <div class="h-3 rounded-full transition-all duration-500"
                  style="background:#206bc4"
                  :style="{ width: barWidth(ind, wardA) + '%' }"></div>
              </div>
              <span class="w-12 text-[10px] text-gray-400 shrink-0">वडा {{ wardA.ward_number }}</span>
            </div>

            <!-- Ward B bar -->
            <div class="flex items-center gap-3">
              <span class="w-14 text-[11px] font-bold text-emerald-600 text-right shrink-0">
                {{ formatVal(ind, wardB) }}{{ ind.pct ? '%' : '' }}
              </span>
              <div class="flex-1 bg-gray-100 rounded-full h-3 overflow-hidden">
                <div class="h-3 rounded-full transition-all duration-500"
                  style="background:#16a34a"
                  :style="{ width: barWidth(ind, wardB) + '%' }"></div>
              </div>
              <span class="w-12 text-[10px] text-gray-400 shrink-0">वडा {{ wardB.ward_number }}</span>
            </div>

            <!-- Winner badge -->
            <div class="mt-1.5 flex items-center gap-1.5">
              <template v-if="formatVal(ind, wardA) !== formatVal(ind, wardB)">
                <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold"
                  :class="winner(ind) === 'A'
                    ? 'bg-blue-100 text-blue-700'
                    : 'bg-emerald-100 text-emerald-700'">
                  वडा {{ winner(ind) === 'A' ? wardA.ward_number : wardB.ward_number }}
                  <i class="fa-solid fa-trophy text-[9px] ml-0.5"></i>
                </span>
                <span class="text-[10px] text-gray-400">
                  {{ winner(ind) === 'A'
                    ? `+${Math.abs(formatVal(ind, wardA) - formatVal(ind, wardB))}${ind.pct?'%':''} अग्रणी`
                    : `+${Math.abs(formatVal(ind, wardB) - formatVal(ind, wardA))}${ind.pct?'%':''} अग्रणी` }}
                </span>
              </template>
              <span v-else class="text-[10px] text-gray-400">बराबर</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Radar chart ────────────────────────────────────────────────── -->
      <div class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-[#e6e7e9] bg-gray-50/70">
          <p class="text-[13px] font-semibold text-[#1d273b]">
            राडार चार्ट — वडा {{ wardA.ward_number }} vs वडा {{ wardB.ward_number }}
          </p>
          <p class="text-[11px] text-[#9aa0ac] mt-0.5">Radar comparison of percentage indicators</p>
        </div>
        <div class="p-4 flex justify-center">
          <div style="max-width:420px; width:100%; height:320px;">
            <canvas ref="radarEl"></canvas>
          </div>
        </div>
      </div>

      <!-- ── Summary verdict ────────────────────────────────────────────── -->
      <div class="bg-white border border-[#e6e7e9] rounded-xl px-5 py-4 shadow-sm">
        <p class="text-[13px] font-semibold text-[#1d273b] mb-2">
          <i class="fa-solid fa-scale-balanced text-[#206bc4] mr-1.5"></i>
          समग्र तुलना
        </p>
        <div class="flex items-center gap-4">
          <div class="text-center flex-1 py-3 rounded-xl border"
            :class="scoreA > scoreB ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-100'">
            <p class="text-[22px] font-bold" :class="scoreA > scoreB ? 'text-[#206bc4]' : 'text-gray-500'">
              {{ scoreA }}/{{ INDICATORS.length }}
            </p>
            <p class="text-[11px] text-gray-500 mt-0.5">वडा {{ wardA.ward_number }} ले माथि</p>
          </div>
          <div class="text-[11px] text-gray-400 text-center">
            <p>सूचकहरूमा</p>
            <p class="font-semibold text-gray-600">{{ INDICATORS.length - scoreA - scoreB }}</p>
            <p>बराबर</p>
          </div>
          <div class="text-center flex-1 py-3 rounded-xl border"
            :class="scoreB > scoreA ? 'bg-emerald-50 border-emerald-200' : 'bg-gray-50 border-gray-100'">
            <p class="text-[22px] font-bold" :class="scoreB > scoreA ? 'text-emerald-600' : 'text-gray-500'">
              {{ scoreB }}/{{ INDICATORS.length }}
            </p>
            <p class="text-[11px] text-gray-500 mt-0.5">वडा {{ wardB.ward_number }} ले माथि</p>
          </div>
        </div>
      </div>

    </template>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import PublicReportsLayout from '@/layouts/PublicReportsLayout.vue'

defineOptions({ layout: PublicReportsLayout })

const props = defineProps({
  lg:          { type: Object, default: null },
  wardSummary: { type: Array,  default: () => [] },
  wards:       { type: Array,  default: () => [] },
})

// ── Ward selection ────────────────────────────────────────────────────────────
const wardAId = ref(props.wardSummary[0]?.ward_id ?? '')
const wardBId = ref(props.wardSummary[1]?.ward_id ?? '')

const wardA = computed(() => props.wardSummary.find(w => w.ward_id == wardAId.value) ?? null)
const wardB = computed(() => props.wardSummary.find(w => w.ward_id == wardBId.value) ?? null)

function swapWards() {
  const tmp  = wardAId.value
  wardAId.value = wardBId.value
  wardBId.value = tmp
}

// ── Indicators ────────────────────────────────────────────────────────────────
const INDICATORS = [
  { key: 'litPct',       label: 'साक्षरता %',    label_en: 'Literacy %',     pct: true,  max: 100 },
  { key: 'waterPct',     label: 'सुरक्षित पानी %', label_en: 'Safe Water %', pct: true,  max: 100 },
  { key: 'toiletPct',    label: 'शौचालय %',       label_en: 'Toilet %',      pct: true,  max: 100 },
  { key: 'internetPct',  label: 'इन्टरनेट %',     label_en: 'Internet %',    pct: true,  max: 100 },
  { key: 'bankPct',      label: 'बैंक खाता %',    label_en: 'Bank Account %', pct: true, max: 100 },
  { key: 'households',   label: 'घरधुरी',          label_en: 'Households',    pct: false, max: null },
  { key: 'total',        label: 'जनसंख्या',        label_en: 'Population',    pct: false, max: null },
]

function indVal(ward, key) {
  if (!ward) return 0
  switch (key) {
    case 'litPct':      return ward.literate_total > 0 ? Math.round(ward.literate / ward.literate_total * 100) : 0
    case 'waterPct':    return ward.utils_total   > 0 ? Math.round(ward.safe_water / ward.utils_total   * 100) : 0
    case 'toiletPct':   return ward.utils_total   > 0 ? Math.round(ward.toilet     / ward.utils_total   * 100) : 0
    case 'internetPct': return ward.utils_total   > 0 ? Math.round(ward.internet   / ward.utils_total   * 100) : 0
    case 'bankPct':     return ward.econ_total    > 0 ? Math.round(ward.bank       / ward.econ_total    * 100) : 0
    case 'households':  return ward.households ?? 0
    case 'total':       return ward.total       ?? 0
    default:            return 0
  }
}

function formatVal(ind, ward) {
  return indVal(ward, ind.key)
}

function barWidth(ind, ward) {
  const val  = indVal(ward, ind.key)
  const valA = wardA.value ? indVal(wardA.value, ind.key) : 0
  const valB = wardB.value ? indVal(wardB.value, ind.key) : 0
  const max  = ind.max ?? Math.max(valA, valB, 1)
  return Math.min(Math.round(val / max * 100), 100)
}

function winner(ind) {
  const a = indVal(wardA.value, ind.key)
  const b = indVal(wardB.value, ind.key)
  return a > b ? 'A' : b > a ? 'B' : 'tie'
}

// ── Score ──────────────────────────────────────────────────────────────────────
const scoreA = computed(() => INDICATORS.filter(i => winner(i) === 'A').length)
const scoreB = computed(() => INDICATORS.filter(i => winner(i) === 'B').length)

// ── Quick stats ────────────────────────────────────────────────────────────────
function quickStats(ward) {
  if (!ward) return []
  return [
    { label: 'घरधुरी',    value: ward.households.toLocaleString() },
    { label: 'जनसंख्या',  value: (ward.total ?? 0).toLocaleString() },
    { label: 'साक्षरता', value: indVal(ward, 'litPct') + '%' },
    { label: 'बैंक खाता', value: indVal(ward, 'bankPct') + '%' },
  ]
}

// ── Radar chart ───────────────────────────────────────────────────────────────
const radarEl  = ref(null)
let   radarInst = null

const PCT_INDICATORS = INDICATORS.filter(i => i.pct)

async function buildRadar() {
  if (!radarEl.value || !wardA.value || !wardB.value) return
  const { Chart, RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend } = await import('chart.js')
  Chart.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend)
  if (radarInst) { radarInst.destroy(); radarInst = null }

  const labels = PCT_INDICATORS.map(i => i.label)
  const dataA  = PCT_INDICATORS.map(i => indVal(wardA.value, i.key))
  const dataB  = PCT_INDICATORS.map(i => indVal(wardB.value, i.key))

  radarInst = new Chart(radarEl.value, {
    type: 'radar',
    data: {
      labels,
      datasets: [
        {
          label:           `वडा ${wardA.value.ward_number}`,
          data:            dataA,
          backgroundColor: 'rgba(32,107,196,0.15)',
          borderColor:     '#206bc4',
          borderWidth:     2,
          pointBackgroundColor: '#206bc4',
          pointRadius:     4,
        },
        {
          label:           `वडा ${wardB.value.ward_number}`,
          data:            dataB,
          backgroundColor: 'rgba(22,163,74,0.12)',
          borderColor:     '#16a34a',
          borderWidth:     2,
          pointBackgroundColor: '#16a34a',
          pointRadius:     4,
        },
      ],
    },
    options: {
      responsive:          true,
      maintainAspectRatio: false,
      plugins: {
        legend:  { position: 'top', labels: { font: { size: 12 }, usePointStyle: true } },
        tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${ctx.parsed.r}%` } },
      },
      scales: {
        r: {
          min:      0,
          max:      100,
          ticks:    { stepSize: 20, font: { size: 10 }, backdropPadding: 2 },
          grid:     { color: '#e6e7e9' },
          angleLines: { color: '#e6e7e9' },
          pointLabels: { font: { size: 11 } },
        },
      },
    },
  })
}

onMounted(()   => nextTick(buildRadar))
onUnmounted(() => { if (radarInst) radarInst.destroy() })
watch([wardAId, wardBId], () => nextTick(buildRadar))
</script>
