<template>
  <div class="space-y-5">

    <!-- Page header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-[15px] font-semibold text-[#1d273b]">प्रगति ड्यासबोर्ड</h1>
        <p class="text-[11px] text-[#9aa0ac] mt-0.5">Enumerator Progress Dashboard</p>
      </div>
      <div class="flex items-center gap-2">
        <select v-model="view"
          class="h-9 rounded border border-[#e6e7e9] text-[13px] text-[#626976] px-3 bg-white">
          <option value="wards">वडा दृश्य</option>
          <option value="enumerators">गणक दृश्य</option>
        </select>
      </div>
    </div>

    <!-- ── Summary KPI row ── -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <!-- Total HH -->
      <div class="bg-white rounded-md border border-[#e6e7e9] px-4 py-3">
        <p class="text-[11px] text-[#9aa0ac] mb-1">जम्मा घरधुरी</p>
        <p class="text-2xl font-bold text-[#1d273b]">{{ fmt(summary.total) }}</p>
        <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
          <div class="h-full rounded-full bg-[#206bc4]" :style="{ width: summary.submitted_pct + '%' }"></div>
        </div>
        <p class="text-[10px] text-[#9aa0ac] mt-1">{{ summary.submitted_pct }}% पेश गरिएको</p>
      </div>
      <!-- Published HH -->
      <div class="bg-white rounded-md border border-[#e6e7e9] px-4 py-3">
        <p class="text-[11px] text-[#9aa0ac] mb-1">प्रकाशित घरधुरी</p>
        <p class="text-2xl font-bold text-green-600">{{ fmt(summary.published) }}</p>
        <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
          <div class="h-full rounded-full bg-green-500" :style="{ width: summary.published_pct + '%' }"></div>
        </div>
        <p class="text-[10px] text-[#9aa0ac] mt-1">{{ summary.published_pct }}% प्रकाशित</p>
      </div>
      <!-- Institutions -->
      <div class="bg-white rounded-md border border-[#e6e7e9] px-4 py-3">
        <p class="text-[11px] text-[#9aa0ac] mb-1">संस्थाहरू</p>
        <p class="text-2xl font-bold text-[#1d273b]">{{ fmt(summary.total_inst) }}</p>
        <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
          <div class="h-full rounded-full bg-teal-500"
            :style="{ width: summary.total_inst > 0 ? Math.round(summary.pub_inst / summary.total_inst * 100) + '%' : '0%' }"></div>
        </div>
        <p class="text-[10px] text-[#9aa0ac] mt-1">{{ fmt(summary.pub_inst) }} प्रकाशित</p>
      </div>
      <!-- Businesses -->
      <div class="bg-white rounded-md border border-[#e6e7e9] px-4 py-3">
        <p class="text-[11px] text-[#9aa0ac] mb-1">व्यवसायहरू</p>
        <p class="text-2xl font-bold text-[#1d273b]">{{ fmt(summary.total_biz) }}</p>
        <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
          <div class="h-full rounded-full bg-orange-400"
            :style="{ width: summary.total_biz > 0 ? Math.round(summary.pub_biz / summary.total_biz * 100) + '%' : '0%' }"></div>
        </div>
        <p class="text-[10px] text-[#9aa0ac] mt-1">{{ fmt(summary.pub_biz) }} प्रकाशित</p>
      </div>
    </div>

    <!-- ── Status breakdown chips ── -->
    <div class="flex flex-wrap gap-2">
      <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-medium bg-gray-100 text-[#626976]">
        <span class="w-2 h-2 rounded-full bg-gray-400 shrink-0"></span>
        मस्यौदा {{ fmt(summary.draft) }}
      </span>
      <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-medium bg-amber-50 text-amber-700">
        <span class="w-2 h-2 rounded-full bg-amber-400 shrink-0"></span>
        पेश गरिएको {{ fmt(summary.submitted) }}
      </span>
      <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-medium bg-blue-50 text-blue-700">
        <span class="w-2 h-2 rounded-full bg-[#206bc4] shrink-0"></span>
        वडा स्वीकृत {{ fmt(summary.ward_approved) }}
      </span>
      <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-medium bg-purple-50 text-purple-700">
        <span class="w-2 h-2 rounded-full bg-purple-400 shrink-0"></span>
        एस्केलेट {{ fmt(summary.escalated) }}
      </span>
      <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-medium bg-green-50 text-green-700">
        <span class="w-2 h-2 rounded-full bg-green-500 shrink-0"></span>
        प्रकाशित {{ fmt(summary.published) }}
      </span>
      <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-medium bg-red-50 text-red-600">
        <span class="w-2 h-2 rounded-full bg-red-400 shrink-0"></span>
        अस्वीकृत {{ fmt(summary.rejected) }}
      </span>
    </div>

    <!-- ── 30-day submission chart ── -->
    <div class="bg-white rounded-md border border-[#e6e7e9] p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-[13px] font-semibold text-[#1d273b] flex items-center gap-2">
          <i class="fa-solid fa-chart-bar text-[#206bc4] text-[11px]"></i>
          ३० दिनको पेशकदम (घरधुरी)
        </h2>
        <span class="text-[11px] text-[#9aa0ac]">
          जम्मा: {{ trendCounts.reduce((a, b) => a + b, 0) }} पेश
        </span>
      </div>
      <div class="relative h-44">
        <canvas ref="chartCanvas"></canvas>
      </div>
    </div>

    <!-- ════ WARD VIEW ════ -->
    <template v-if="view === 'wards'">
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
        <div
          v-for="ward in wardProgress" :key="ward.id"
          class="bg-white rounded-md border border-[#e6e7e9] p-4 flex flex-col gap-3"
        >
          <!-- Ward header -->
          <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2">
              <div class="w-9 h-9 rounded-md bg-indigo-100 text-indigo-700 flex items-center justify-center text-[13px] font-bold shrink-0">
                {{ ward.ward_number }}
              </div>
              <div>
                <p class="text-[13px] font-semibold text-[#1d273b]">वडा {{ ward.ward_number }}</p>
                <p class="text-[11px] text-[#9aa0ac]">{{ ward.inst_total }} संस्था</p>
              </div>
            </div>
            <!-- Published pct badge -->
            <span :class="[
              'shrink-0 text-[11px] font-bold px-2 py-0.5 rounded-full',
              wardPublishedPct(ward) >= 80 ? 'bg-green-100 text-green-700'
                : wardPublishedPct(ward) >= 40 ? 'bg-amber-100 text-amber-700'
                : 'bg-gray-100 text-gray-500'
            ]">{{ wardPublishedPct(ward) }}%</span>
          </div>

          <!-- Stacked progress bar -->
          <div class="h-2 rounded-full bg-gray-100 overflow-hidden flex">
            <div class="h-full bg-green-500 transition-all" :style="{ width: wardPct(ward, 'hh_published') + '%' }"></div>
            <div class="h-full bg-[#206bc4] transition-all" :style="{ width: wardPct(ward, 'hh_ward_approved') + '%' }"></div>
            <div class="h-full bg-amber-400 transition-all" :style="{ width: wardPct(ward, 'hh_submitted') + '%' }"></div>
            <div class="h-full bg-red-300 transition-all" :style="{ width: wardPct(ward, 'hh_rejected') + '%' }"></div>
          </div>

          <!-- Count chips -->
          <div class="grid grid-cols-3 gap-1.5 text-center">
            <div class="rounded bg-gray-50 px-2 py-1">
              <p class="text-[11px] text-[#9aa0ac]">जम्मा</p>
              <p class="text-[13px] font-bold text-[#1d273b]">{{ ward.hh_total }}</p>
            </div>
            <div class="rounded bg-amber-50 px-2 py-1">
              <p class="text-[11px] text-amber-500">पेश</p>
              <p class="text-[13px] font-bold text-amber-700">{{ ward.hh_submitted }}</p>
            </div>
            <div class="rounded bg-green-50 px-2 py-1">
              <p class="text-[11px] text-green-500">प्रकाशित</p>
              <p class="text-[13px] font-bold text-green-700">{{ ward.hh_published }}</p>
            </div>
            <div class="rounded bg-blue-50 px-2 py-1">
              <p class="text-[11px] text-blue-500">स्वीकृत</p>
              <p class="text-[13px] font-bold text-[#206bc4]">{{ ward.hh_ward_approved }}</p>
            </div>
            <div class="rounded bg-gray-50 px-2 py-1">
              <p class="text-[11px] text-[#9aa0ac]">मस्यौदा</p>
              <p class="text-[13px] font-bold text-[#1d273b]">{{ ward.hh_draft }}</p>
            </div>
            <div class="rounded bg-red-50 px-2 py-1">
              <p class="text-[11px] text-red-400">अस्वीकृत</p>
              <p class="text-[13px] font-bold text-red-600">{{ ward.hh_rejected }}</p>
            </div>
          </div>

          <!-- Assigned enumerators -->
          <div>
            <p class="text-[10px] font-semibold text-[#9aa0ac] uppercase tracking-wide mb-1.5">तोकिएका गणकहरू</p>
            <div v-if="ward.enumerators.length" class="flex flex-wrap gap-1.5">
              <span
                v-for="e in ward.enumerators" :key="e.id"
                :class="[
                  'inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium',
                  e.is_active ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-400'
                ]"
              >
                <i class="fa-solid fa-clipboard-user text-[9px]"></i>
                {{ e.name }}
                <span v-if="!e.is_active" class="text-[9px]">(निष्क्रिय)</span>
              </span>
            </div>
            <p v-else class="text-[12px] text-red-400 italic flex items-center gap-1">
              <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
              गणक तोकिएको छैन
            </p>
          </div>

          <!-- Last activity -->
          <p class="text-[11px] text-[#9aa0ac]">
            <i class="fa-regular fa-clock text-[10px] mr-1"></i>
            {{ ward.last_active_at ? timeAgo(ward.last_active_at) : 'कुनै गतिविधि छैन' }}
          </p>
        </div>

        <!-- Empty state -->
        <div v-if="!wardProgress.length"
          class="col-span-full bg-white rounded-md border border-[#e6e7e9] py-12 text-center">
          <p class="text-[13px] text-[#9aa0ac]">कुनै वडा डेटा छैन।</p>
        </div>
      </div>
    </template>

    <!-- ════ ENUMERATOR VIEW ════ -->
    <template v-else>
      <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
        <div class="px-5 py-4 border-b border-[#e6e7e9] flex items-center justify-between">
          <h2 class="text-[13px] font-semibold text-[#1d273b] flex items-center gap-2">
            <i class="fa-solid fa-clipboard-user text-amber-500 text-[11px]"></i>
            गणकहरूको प्रगति
          </h2>
          <span class="text-[11px] text-[#9aa0ac]">{{ enumerators.length }} गणक</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="bg-gray-50 border-b border-[#e6e7e9]">
              <tr>
                <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide">गणक</th>
                <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide">तोकिएका वडा</th>
                <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide text-right">मस्यौदा</th>
                <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide text-right">पेश</th>
                <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide text-right">स्वीकृत</th>
                <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide text-right">प्रकाशित</th>
                <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide text-right">अस्वीकृत</th>
                <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide text-right">संस्था</th>
                <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide min-w-[130px]">प्रगति</th>
                <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide">अन्तिम सक्रिय</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#f0f1f3]">
              <tr
                v-for="e in enumerators" :key="e.id"
                class="hover:bg-gray-50 transition-colors"
              >
                <!-- Name -->
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded bg-amber-100 text-amber-700 flex items-center justify-center text-[11px] font-bold shrink-0">
                      {{ initials(e.name) }}
                    </div>
                    <div>
                      <p class="text-[13px] font-semibold text-[#1d273b] leading-tight">{{ e.name }}</p>
                      <span v-if="!e.is_active"
                        class="text-[10px] px-1.5 py-0.5 rounded bg-red-50 text-red-500 font-medium">निष्क्रिय</span>
                    </div>
                  </div>
                </td>
                <!-- Assigned wards -->
                <td class="px-4 py-3">
                  <div v-if="e.assigned_wards.length" class="flex flex-wrap gap-1">
                    <span v-for="w in e.assigned_wards" :key="w"
                      class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 font-medium">
                      वडा {{ w }}
                    </span>
                  </div>
                  <span v-else class="text-[11px] text-red-400 italic">तोकिएको छैन</span>
                </td>
                <!-- Draft -->
                <td class="px-4 py-3 text-right text-[13px] text-[#9aa0ac]">{{ e.hh_draft || '—' }}</td>
                <!-- Submitted -->
                <td class="px-4 py-3 text-right">
                  <span v-if="e.hh_submitted > 0"
                    class="text-[12px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full">
                    {{ e.hh_submitted }}
                  </span>
                  <span v-else class="text-[13px] text-[#9aa0ac]">—</span>
                </td>
                <!-- Ward approved -->
                <td class="px-4 py-3 text-right">
                  <span v-if="e.hh_ward_approved > 0"
                    class="text-[12px] font-bold text-[#206bc4] bg-blue-50 px-2 py-0.5 rounded-full">
                    {{ e.hh_ward_approved }}
                  </span>
                  <span v-else class="text-[13px] text-[#9aa0ac]">—</span>
                </td>
                <!-- Published -->
                <td class="px-4 py-3 text-right">
                  <span v-if="e.hh_published > 0"
                    class="text-[12px] font-bold text-green-700 bg-green-50 px-2 py-0.5 rounded-full">
                    {{ e.hh_published }}
                  </span>
                  <span v-else class="text-[13px] text-[#9aa0ac]">—</span>
                </td>
                <!-- Rejected -->
                <td class="px-4 py-3 text-right">
                  <span v-if="e.hh_rejected > 0"
                    class="text-[12px] font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded-full">
                    {{ e.hh_rejected }}
                  </span>
                  <span v-else class="text-[13px] text-[#9aa0ac]">—</span>
                </td>
                <!-- Institutions -->
                <td class="px-4 py-3 text-right text-[13px] text-[#9aa0ac]">{{ e.inst_total || '—' }}</td>
                <!-- Progress bar -->
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden flex" style="min-width:80px">
                      <div class="h-full bg-green-500" :style="{ width: enumPct(e, 'hh_published') + '%' }"></div>
                      <div class="h-full bg-[#206bc4]" :style="{ width: enumPct(e, 'hh_ward_approved') + '%' }"></div>
                      <div class="h-full bg-amber-400" :style="{ width: enumPct(e, 'hh_submitted') + '%' }"></div>
                    </div>
                    <span class="text-[12px] font-bold w-8 text-right shrink-0"
                      :class="enumPct(e, 'hh_published') >= 80 ? 'text-green-600'
                        : enumPct(e, 'hh_published') >= 40 ? 'text-amber-600' : 'text-[#9aa0ac]'">
                      {{ enumPct(e, 'hh_published') }}%
                    </span>
                  </div>
                </td>
                <!-- Last active -->
                <td class="px-4 py-3 text-[12px] text-[#9aa0ac]">
                  {{ e.last_active_at ? timeAgo(e.last_active_at) : '—' }}
                </td>
              </tr>

              <tr v-if="!enumerators.length">
                <td colspan="10" class="px-5 py-10 text-center text-[#9aa0ac]">
                  <i class="fa-solid fa-users-slash text-3xl text-gray-200 mb-2 block"></i>
                  कुनै गणक छैन
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- Legend -->
    <div class="flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-[#9aa0ac]">
      <span class="flex items-center gap-1.5"><span class="w-3 h-2 rounded-sm bg-green-500 inline-block"></span> प्रकाशित</span>
      <span class="flex items-center gap-1.5"><span class="w-3 h-2 rounded-sm bg-[#206bc4] inline-block"></span> वडा स्वीकृत</span>
      <span class="flex items-center gap-1.5"><span class="w-3 h-2 rounded-sm bg-amber-400 inline-block"></span> पेश गरिएको</span>
      <span class="flex items-center gap-1.5"><span class="w-3 h-2 rounded-sm bg-red-300 inline-block"></span> अस्वीकृत</span>
      <span class="flex items-center gap-1.5"><span class="w-3 h-2 rounded-sm bg-gray-200 inline-block"></span> मस्यौदा</span>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import {
  Chart, CategoryScale, LinearScale, BarElement, Title, Tooltip,
} from 'chart.js'

Chart.register(CategoryScale, LinearScale, BarElement, Title, Tooltip)

const props = defineProps({
  summary:      { type: Object, default: () => ({}) },
  trendDays:    { type: Array,  default: () => [] },
  trendCounts:  { type: Array,  default: () => [] },
  wardProgress: { type: Array,  default: () => [] },
  enumerators:  { type: Array,  default: () => [] },
})

const view        = ref('wards')
const chartCanvas = ref(null)
let   chart       = null

// ── Helpers ───────────────────────────────────────────────────────────
function fmt(n) { return (n ?? 0).toLocaleString() }

function initials(name) {
  return (name ?? '?').split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
}

function timeAgo(dateStr) {
  if (!dateStr) return '—'
  const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000)
  if (diff < 60)   return 'अहिले'
  if (diff < 3600) return Math.floor(diff / 60) + ' मिनेट अगाडि'
  if (diff < 86400)return Math.floor(diff / 3600) + ' घण्टा अगाडि'
  return Math.floor(diff / 86400) + ' दिन अगाडि'
}

function wardPublishedPct(ward) {
  if (!ward.hh_total) return 0
  return Math.round(ward.hh_published / ward.hh_total * 100)
}

function wardPct(ward, key) {
  if (!ward.hh_total) return 0
  return Math.round((ward[key] ?? 0) / ward.hh_total * 100)
}

function enumPct(e, key) {
  if (!e.hh_total) return 0
  return Math.round((e[key] ?? 0) / e.hh_total * 100)
}

// ── Chart ─────────────────────────────────────────────────────────────
onMounted(() => {
  if (!chartCanvas.value) return
  chart = new Chart(chartCanvas.value, {
    type: 'bar',
    data: {
      labels:   props.trendDays,
      datasets: [{
        label:           'पेश गरिएका घरधुरी',
        data:            props.trendCounts,
        backgroundColor: 'rgba(32,107,196,0.5)',
        borderColor:     'rgba(32,107,196,0.9)',
        borderWidth:     1,
        borderRadius:    3,
      }],
    },
    options: {
      responsive:          true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { mode: 'index' } },
      scales: {
        x: {
          ticks: { font: { size: 10 }, maxRotation: 45, minRotation: 30, color: '#9aa0ac' },
          grid:  { display: false },
        },
        y: {
          beginAtZero: true,
          ticks: { stepSize: 1, font: { size: 10 }, color: '#9aa0ac' },
          grid:  { color: 'rgba(0,0,0,0.04)' },
        },
      },
    },
  })
})

onUnmounted(() => { chart?.destroy() })
</script>
