<template>
  <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 py-4">

    <!-- Breadcrumb -->
    <nav class="text-[12px] text-[#9aa0ac] mb-4 flex items-center gap-1.5">
      <a href="/public" class="hover:text-[#206bc4] transition flex items-center gap-1">
        <i class="fa-solid fa-chart-bar text-[10px]"></i>{{ t('ward.breadcrumbStats') }}
      </a>
      <i class="fa-solid fa-chevron-right text-[9px] text-gray-300"></i>
      <span class="text-[#626976] font-medium">{{ t('common.ward') }} {{ props.ward?.ward_number }}</span>
    </nav>

    <!-- Page header -->
    <div class="bg-white border border-[#e6e7e9] rounded-md p-4 mb-4">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-[#206bc4]/10 rounded flex items-center justify-center text-xl font-bold text-[#206bc4] shrink-0">
          {{ props.ward?.ward_number }}
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.12em] mb-0.5">{{ t('ward.profile') }}</p>
          <h1 class="text-[15px] font-semibold text-[#1d273b] leading-tight">{{ t('common.ward') }} {{ props.ward?.ward_number }}</h1>
          <p class="text-[12px] text-[#9aa0ac] mt-0.5">
            {{ props.lg?.name_en }}
            <template v-if="props.lg?.district"> · {{ props.lg.district }}</template>
          </p>
        </div>
        <a href="/public"
          class="shrink-0 flex items-center gap-1.5 h-8 px-3 border border-[#e6e7e9] text-[#626976] text-[13px] rounded hover:bg-gray-50 transition">
          <i class="fa-solid fa-arrow-left text-[11px]"></i>
          <span class="hidden sm:inline">{{ t('ward.allWards') }}</span>
        </a>
      </div>
    </div>

    <!-- Stats cards -->
    <div class="grid grid-cols-3 gap-3 mb-4">
      <div class="bg-white border border-[#e6e7e9] rounded-md p-4 hover:border-[#206bc4]/30 transition-colors">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac] mb-1.5">{{ t('stats.households') }}</p>
            <p class="text-2xl font-semibold text-[#1d273b] leading-none tabular-nums">{{ fmt(props.stats.households) }}</p>
          </div>
          <div class="w-9 h-9 rounded flex items-center justify-center shrink-0 bg-amber-100 text-amber-600">
            <i class="fa-solid fa-house text-base"></i>
          </div>
        </div>
      </div>
      <div class="bg-white border border-[#e6e7e9] rounded-md p-4 hover:border-[#206bc4]/30 transition-colors">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac] mb-1.5">{{ t('stats.population') }}</p>
            <p class="text-2xl font-semibold text-[#1d273b] leading-none tabular-nums">{{ fmt(props.stats.members) }}</p>
          </div>
          <div class="w-9 h-9 rounded flex items-center justify-center shrink-0 bg-indigo-100 text-indigo-600">
            <i class="fa-solid fa-users text-base"></i>
          </div>
        </div>
      </div>
      <div class="bg-white border border-[#e6e7e9] rounded-md p-4 hover:border-[#206bc4]/30 transition-colors">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac] mb-1.5">{{ t('stats.institutions') }}</p>
            <p class="text-2xl font-semibold text-[#1d273b] leading-none tabular-nums">{{ fmt(props.stats.institutions) }}</p>
          </div>
          <div class="w-9 h-9 rounded flex items-center justify-center shrink-0 bg-violet-100 text-violet-600">
            <i class="fa-solid fa-building text-base"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Gender chart + Institutions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">

      <!-- Gender chart -->
      <div v-if="hasGenderData" class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#e6e7e9] flex items-center gap-2">
          <div class="w-8 h-8 rounded bg-violet-100 flex items-center justify-center">
            <i class="fa-solid fa-venus-mars text-violet-600 text-sm"></i>
          </div>
          <p class="text-[13px] font-semibold text-[#1d273b]">{{ t('ward.populationByGender') }}</p>
        </div>
        <div class="p-4">
          <div class="h-48"><canvas ref="genderChartEl"></canvas></div>
          <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="bg-blue-50 rounded-md px-4 py-3 text-center border border-blue-100">
              <p class="text-[22px] font-semibold text-blue-700 tabular-nums leading-none">{{ fmt(props.genderData.male ?? 0) }}</p>
              <p class="text-[11px] text-blue-500 mt-1 font-medium">{{ t('ward.male') }}</p>
            </div>
            <div class="bg-pink-50 rounded-md px-4 py-3 text-center border border-pink-100">
              <p class="text-[22px] font-semibold text-pink-600 tabular-nums leading-none">{{ fmt(props.genderData.female ?? 0) }}</p>
              <p class="text-[11px] text-pink-400 mt-1 font-medium">{{ t('ward.female') }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Institutions list -->
      <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#e6e7e9] flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded bg-purple-100 flex items-center justify-center">
              <i class="fa-solid fa-building text-purple-600 text-sm"></i>
            </div>
            <p class="text-[13px] font-semibold text-[#1d273b]">{{ t('ward.institutions') }}</p>
          </div>
          <span class="text-[11px] text-[#9aa0ac] bg-gray-100 px-2 py-1 rounded">{{ props.institutions.length }}</span>
        </div>

        <div v-if="props.institutions.length" class="divide-y divide-[#e6e7e9] max-h-64 overflow-y-auto">
          <div v-for="inst in props.institutions" :key="inst.id"
            class="flex justify-between items-center py-2.5 px-4 hover:bg-gray-50 transition">
            <span class="text-[13px] font-medium text-[#1d273b] truncate">{{ inst.name_en }}</span>
            <span class="text-[11px] text-[#9aa0ac] shrink-0 ml-2 bg-gray-100 px-2 py-0.5 rounded">{{ inst.type ?? '—' }}</span>
          </div>
        </div>
        <div v-else class="text-center py-10 text-[#9aa0ac]">
          <i class="fa-solid fa-building-circle-xmark text-3xl block mb-2 text-gray-200"></i>
          <p class="text-[13px]">{{ t('ward.noInstitutions') }}</p>
        </div>
      </div>
    </div>

    <!-- Back -->
    <div class="flex justify-center pt-4 border-t border-[#e6e7e9]">
      <a href="/public"
        class="inline-flex items-center gap-1.5 h-9 px-5 bg-[#206bc4] hover:bg-[#1a5ba8] text-white rounded text-[13px] font-medium transition">
        <i class="fa-solid fa-arrow-left text-[11px]"></i>{{ t('ward.backDashboard') }}
      </a>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import Chart from 'chart.js/auto'
import PublicLayout from '@/layouts/PublicLayout.vue'
import { useLocale } from '@/i18n/index.js'

defineOptions({ layout: PublicLayout })

const { t } = useLocale()

const props = defineProps({
  lg:           { type: Object, default: null },
  ward:         { type: Object, default: null },
  stats:        { type: Object, default: () => ({ households: 0, members: 0, institutions: 0 }) },
  genderData:   { type: Object, default: () => ({}) },
  institutions: { type: Array,  default: () => [] },
})

const hasGenderData = computed(() => Object.keys(props.genderData).length > 0)

const genderChartEl = ref(null)
let genderChart = null

onMounted(() => {
  if (!genderChartEl.value || !hasGenderData.value) return
  genderChart = new Chart(genderChartEl.value, {
    type: 'doughnut',
    data: {
      labels: Object.keys(props.genderData).map(k =>
        ({ male: 'पुरुष (Male)', female: 'महिला (Female)', other: 'अन्य (Other)' }[k] ?? k)),
      datasets: [{
        data: Object.values(props.genderData),
        backgroundColor: ['#3B82F6', '#EC4899', '#6B7280'],
        hoverOffset: 4,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12 } } },
    },
  })
})

onUnmounted(() => genderChart?.destroy())

function fmt(n) { return Number(n ?? 0).toLocaleString() }
</script>
