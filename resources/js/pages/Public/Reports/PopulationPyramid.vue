<template>
  <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-6 space-y-5">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-[18px] font-bold text-[#1d273b]">जनसंख्या पिरामिड</h1>
        <p class="text-[12px] text-[#9aa0ac] mt-0.5">Population Pyramid by Age and Gender</p>
      </div>
      <a href="/public/reports" class="inline-flex items-center gap-1.5 text-[12px] text-[#206bc4] hover:underline">
        <i class="fa-solid fa-arrow-left text-[10px]"></i> प्रतिवेदन सूची
      </a>
    </div>

    <!-- Ward filter -->
    <div class="bg-white border border-[#e6e7e9] rounded-md p-3 flex flex-wrap items-end gap-3">
      <div class="flex-1 min-w-[160px]">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">वडा</label>
        <select v-model="selWard" @change="applyFilter"
          class="w-full h-9 rounded border border-[#e6e7e9] bg-white px-3 text-[13px] text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/30 outline-none">
          <option value="">सबै वडा</option>
          <option v-for="w in props.wards" :key="w.id" :value="w.id">वडा {{ w.ward_number }}</option>
        </select>
      </div>
      <button v-if="selWard" @click="selWard=''; applyFilter()"
        class="h-9 px-3 border border-[#e6e7e9] text-[13px] rounded hover:bg-gray-50 transition flex items-center gap-1.5">
        <i class="fa-solid fa-xmark text-[11px]"></i> हटाउनुहोस्
      </button>
    </div>

    <!-- Summary stat cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">जम्मा जनसंख्या</p>
        <p class="text-2xl font-bold text-[#1d273b]">{{ fmt(props.totalMembers) }}</p>
      </div>
      <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">पुरुष</p>
        <p class="text-2xl font-bold text-blue-600">{{ fmt(props.genderData?.male ?? 0) }}</p>
        <p class="text-[11px] text-[#9aa0ac] mt-0.5">{{ malePct }}%</p>
      </div>
      <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">महिला</p>
        <p class="text-2xl font-bold text-pink-500">{{ fmt(props.genderData?.female ?? 0) }}</p>
        <p class="text-[11px] text-[#9aa0ac] mt-0.5">{{ femalePct }}%</p>
      </div>
      <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">लिंग अनुपात</p>
        <p class="text-2xl font-bold text-[#1d273b]">{{ sexRatio }}</p>
        <p class="text-[11px] text-[#9aa0ac] mt-0.5">प्रति १०० महिला</p>
      </div>
    </div>

    <!-- Pyramid chart + table -->
    <div class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
      <div class="px-4 py-3 border-b border-[#e6e7e9] flex items-center justify-between flex-wrap gap-2">
        <div>
          <p class="text-[13px] font-semibold text-[#1d273b]">उमेर समूह अनुसार जनसंख्या</p>
          <p class="text-[11px] text-[#9aa0ac] mt-0.5">५ वर्षे उमेर समूह × लिंग</p>
        </div>
        <div class="flex items-center gap-4 text-[12px] text-[#626976]">
          <span class="flex items-center gap-1.5">
            <span class="inline-block w-3 h-3 rounded-sm bg-blue-500"></span> पुरुष
          </span>
          <span class="flex items-center gap-1.5">
            <span class="inline-block w-3 h-3 rounded-sm bg-pink-400"></span> महिला
          </span>
        </div>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-3">
        <div class="lg:col-span-2 p-4" style="height:520px; min-height:320px;">
          <canvas ref="pyramidEl"></canvas>
        </div>
        <div class="border-t lg:border-t-0 lg:border-l border-[#e6e7e9] overflow-auto" style="max-height:520px;">
          <table class="w-full">
            <thead class="sticky top-0 bg-gray-50 border-b border-[#e6e7e9]">
              <tr>
                <th class="px-3 py-2 text-left text-[10px] font-semibold text-gray-500 uppercase">उमेर</th>
                <th class="px-3 py-2 text-right text-[10px] font-semibold text-blue-500 uppercase">पुरुष</th>
                <th class="px-3 py-2 text-right text-[10px] font-semibold text-pink-400 uppercase">महिला</th>
                <th class="px-3 py-2 text-right text-[10px] font-semibold text-gray-500 uppercase">जम्मा</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in props.pyramidData" :key="row.age_group"
                class="border-b border-[#f3f4f6] hover:bg-gray-50 transition-colors">
                <td class="px-3 py-1.5 text-[12px] font-semibold text-[#1d273b]">{{ row.age_group }}</td>
                <td class="px-3 py-1.5 text-[12px] text-right text-blue-600 tabular-nums">{{ fmt(row.male) }}</td>
                <td class="px-3 py-1.5 text-[12px] text-right text-pink-500 tabular-nums">{{ fmt(row.female) }}</td>
                <td class="px-3 py-1.5 text-[12px] text-right font-medium text-[#1d273b] tabular-nums">{{ fmt(row.male + row.female + (row.other||0)) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-50 border-t border-[#e6e7e9]">
              <tr>
                <td class="px-3 py-2 text-[11px] font-bold text-[#1d273b]">जम्मा</td>
                <td class="px-3 py-2 text-[11px] text-right font-bold text-blue-600 tabular-nums">{{ fmt(totals.male) }}</td>
                <td class="px-3 py-2 text-[11px] text-right font-bold text-pink-500 tabular-nums">{{ fmt(totals.female) }}</td>
                <td class="px-3 py-2 text-[11px] text-right font-bold text-[#1d273b] tabular-nums">{{ fmt(totals.total) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Chart from 'chart.js/auto'
import PublicLayout from '@/layouts/PublicLayout.vue'

defineOptions({ layout: PublicLayout })

const props = defineProps({
  lg:             { type: Object, default: null },
  wards:          { type: Array,  default: () => [] },
  selectedWardId: { type: [Number, null], default: null },
  pyramidData:    { type: Array,  default: () => [] },
  totalMembers:   { type: Number, default: 0 },
  genderData:     { type: Object, default: () => ({}) },
})

const selWard  = ref(props.selectedWardId ?? '')
const pyramidEl = ref(null)
let chart = null

const fmt = n => Number(n ?? 0).toLocaleString()

const totals = computed(() => props.pyramidData.reduce(
  (a, r) => ({ male: a.male + r.male, female: a.female + r.female, total: a.total + r.male + r.female + (r.other ?? 0) }),
  { male: 0, female: 0, total: 0 }
))

const malePct   = computed(() => props.totalMembers > 0 ? Math.round((props.genderData?.male ?? 0) / props.totalMembers * 100) : 0)
const femalePct = computed(() => props.totalMembers > 0 ? Math.round((props.genderData?.female ?? 0) / props.totalMembers * 100) : 0)
const sexRatio  = computed(() => {
  const f = props.genderData?.female ?? 0
  return f > 0 ? Math.round((props.genderData?.male ?? 0) / f * 100) : 0
})

function applyFilter() {
  const p = {}
  if (selWard.value) p.ward_id = selWard.value
  router.get('/public/reports/population-pyramid', p, { preserveState: true, replace: true })
}

function initChart() {
  if (!pyramidEl.value || !props.pyramidData.length) return
  chart?.destroy()
  const maxVal = Math.max(...props.pyramidData.map(r => Math.max(r.male, r.female)), 1)
  chart = new Chart(pyramidEl.value, {
    type: 'bar',
    data: {
      labels: props.pyramidData.map(r => r.age_group),
      datasets: [
        { label: 'पुरुष',  data: props.pyramidData.map(r => -r.male),   backgroundColor: 'rgba(59,130,246,0.82)',  borderRadius: 2, borderSkipped: false },
        { label: 'महिला', data: props.pyramidData.map(r => r.female), backgroundColor: 'rgba(236,72,153,0.78)',  borderRadius: 2, borderSkipped: false },
      ],
    },
    options: {
      indexAxis: 'y', responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${Math.abs(ctx.parsed.x).toLocaleString()}` } },
      },
      scales: {
        x: {
          min: -(maxVal * 1.12), max: maxVal * 1.12,
          ticks: { font: { size: 10 }, callback: v => { const a = Math.abs(v); return a >= 1000 ? (a/1000).toFixed(1)+'k' : a } },
          grid: { color: '#f1f3f5' },
        },
        y: { ticks: { font: { size: 10 } }, grid: { display: false } },
      },
    },
  })
}

onMounted(initChart)
onUnmounted(() => chart?.destroy())
watch(() => props.pyramidData, initChart, { deep: true })
</script>
