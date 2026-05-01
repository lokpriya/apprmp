<template>
  <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-6 space-y-5">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-[18px] font-bold text-[#1d273b]">वडागत सारांश</h1>
        <p class="text-[12px] text-[#9aa0ac] mt-0.5">Ward-wise Population & Household Summary</p>
      </div>
      <a href="/public/reports" class="inline-flex items-center gap-1.5 text-[12px] text-[#206bc4] hover:underline">
        <i class="fa-solid fa-arrow-left text-[10px]"></i> प्रतिवेदन सूची
      </a>
    </div>

    <!-- Totals strip -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">जम्मा घरधुरी</p>
        <p class="text-2xl font-bold text-amber-600">{{ fmt(totalHH) }}</p>
      </div>
      <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">जम्मा जनसंख्या</p>
        <p class="text-2xl font-bold text-[#1d273b]">{{ fmt(totalPop) }}</p>
      </div>
      <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">पुरुष / महिला</p>
        <p class="text-2xl font-bold text-[#1d273b]">
          <span class="text-blue-600">{{ fmt(totalMale) }}</span>
          <span class="text-gray-300 mx-1">/</span>
          <span class="text-pink-500">{{ fmt(totalFemale) }}</span>
        </p>
      </div>
      <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">जम्मा संस्थाहरू</p>
        <p class="text-2xl font-bold text-violet-600">{{ fmt(totalInstitutions) }}</p>
      </div>
    </div>

    <!-- Ward summary table -->
    <div class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-[#e6e7e9]">
        <div>
          <p class="text-[13px] font-semibold text-[#1d273b]">वडागत विवरण</p>
          <p class="text-[11px] text-[#9aa0ac] mt-0.5">घरधुरी, जनसंख्या र संस्था</p>
        </div>
        <span class="text-[11px] text-[#9aa0ac] bg-gray-100 px-2 py-1 rounded">{{ props.wardSummary.length }} वडा</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="border-b border-[#e6e7e9] bg-gray-50">
            <tr>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">वडा</th>
              <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">घरधुरी</th>
              <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-blue-500">पुरुष</th>
              <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-pink-400">महिला</th>
              <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">जम्मा</th>
              <th class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] text-center">लिंग अनुपात</th>
              <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-violet-500">संस्था</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ws in props.wardSummary" :key="ws.ward_id"
              class="border-b border-[#e6e7e9] hover:bg-gray-50 transition-colors">
              <td class="px-4 py-2.5">
                <a :href="`/public?ward_id=${ws.ward_id}`" class="flex items-center gap-2.5 group">
                  <span class="w-7 h-7 rounded flex items-center justify-center text-[12px] font-bold shrink-0 bg-gray-100 text-[#626976] group-hover:bg-[#206bc4]/10 group-hover:text-[#206bc4] transition">
                    {{ ws.ward_number }}
                  </span>
                  <span class="text-[13px] text-[#1d273b] group-hover:text-[#206bc4] transition">वडा {{ ws.ward_number }}</span>
                </a>
              </td>
              <td class="px-4 py-2.5 text-right text-[13px] text-[#1d273b] tabular-nums">{{ fmt(ws.households) }}</td>
              <td class="px-4 py-2.5 text-right text-[13px] text-blue-600 tabular-nums">{{ fmt(ws.male) }}</td>
              <td class="px-4 py-2.5 text-right text-[13px] text-pink-500 tabular-nums">{{ fmt(ws.female) }}</td>
              <td class="px-4 py-2.5 text-right text-[13px] font-medium text-[#1d273b] tabular-nums">{{ fmt(ws.total) }}</td>
              <td class="px-4 py-2.5">
                <div class="flex items-center gap-1.5 justify-center">
                  <span class="text-[11px] text-blue-500 w-6 text-right tabular-nums">{{ malePct(ws) }}%</span>
                  <div class="w-16 h-1.5 rounded-full overflow-hidden bg-pink-100">
                    <div class="h-full bg-blue-400 rounded-full" :style="`width:${malePct(ws)}%`"></div>
                  </div>
                  <span class="text-[11px] text-pink-400 w-6 tabular-nums">{{ 100 - malePct(ws) }}%</span>
                </div>
              </td>
              <td class="px-4 py-2.5 text-right text-[13px] text-violet-600 tabular-nums">{{ fmt(ws.institutions) }}</td>
            </tr>
          </tbody>
          <tfoot class="bg-gray-50 border-t border-[#e6e7e9]">
            <tr>
              <td class="px-4 py-2.5 text-[13px] font-bold text-[#1d273b]">जम्मा</td>
              <td class="px-4 py-2.5 text-right text-[13px] font-bold text-[#1d273b] tabular-nums">{{ fmt(totalHH) }}</td>
              <td class="px-4 py-2.5 text-right text-[13px] font-bold text-blue-600 tabular-nums">{{ fmt(totalMale) }}</td>
              <td class="px-4 py-2.5 text-right text-[13px] font-bold text-pink-500 tabular-nums">{{ fmt(totalFemale) }}</td>
              <td class="px-4 py-2.5 text-right text-[13px] font-bold text-[#1d273b] tabular-nums">{{ fmt(totalPop) }}</td>
              <td></td>
              <td class="px-4 py-2.5 text-right text-[13px] font-bold text-violet-600 tabular-nums">{{ fmt(totalInstitutions) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Household bar chart -->
    <div v-if="props.wardSummary.length" class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
      <div class="px-4 py-3 border-b border-[#e6e7e9]">
        <p class="text-[13px] font-semibold text-[#1d273b]">वडा अनुसार घरधुरी र जनसंख्या</p>
      </div>
      <div class="p-4" style="height:280px;">
        <canvas ref="barEl"></canvas>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import Chart from 'chart.js/auto'
import PublicLayout from '@/layouts/PublicLayout.vue'

defineOptions({ layout: PublicLayout })

const props = defineProps({
  lg:          { type: Object, default: null },
  wardSummary: { type: Array,  default: () => [] },
})

const barEl = ref(null)
let chart = null

const fmt     = n => Number(n ?? 0).toLocaleString()
const malePct = ws => ws.total > 0 ? Math.round(ws.male / ws.total * 100) : 50

const totalHH           = computed(() => props.wardSummary.reduce((s, w) => s + w.households,   0))
const totalPop          = computed(() => props.wardSummary.reduce((s, w) => s + w.total,          0))
const totalMale         = computed(() => props.wardSummary.reduce((s, w) => s + w.male,           0))
const totalFemale       = computed(() => props.wardSummary.reduce((s, w) => s + w.female,         0))
const totalInstitutions = computed(() => props.wardSummary.reduce((s, w) => s + w.institutions,   0))

onMounted(() => {
  if (!barEl.value || !props.wardSummary.length) return
  chart = new Chart(barEl.value, {
    type: 'bar',
    data: {
      labels: props.wardSummary.map(w => `वडा ${w.ward_number}`),
      datasets: [
        { label: 'घरधुरी', data: props.wardSummary.map(w => w.households), backgroundColor: '#F59E0B', borderRadius: 4 },
        { label: 'पुरुष',  data: props.wardSummary.map(w => w.male),       backgroundColor: '#3B82F6', borderRadius: 4 },
        { label: 'महिला', data: props.wardSummary.map(w => w.female),     backgroundColor: '#EC4899', borderRadius: 4 },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
      scales: { y: { beginAtZero: true, grid: { color: '#f3f4f6' } }, x: { grid: { display: false } } },
    },
  })
})

onUnmounted(() => chart?.destroy())
</script>
