<template>
  <div>

    <!-- ── Page body ── -->
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 py-4">

      <!-- ── Page header + filter ── -->
      <div class="bg-white border border-[#e6e7e9] rounded-md p-4 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-[#f76707]/10 rounded flex items-center justify-center shrink-0">
              <i class="fa-solid fa-house text-[#f76707] text-sm"></i>
            </div>
            <div>
              <h1 class="text-[15px] font-semibold text-[#1d273b] leading-tight">{{ t('housing.heroTitle') }}</h1>
              <p class="text-[12px] text-[#9aa0ac]">
                {{ props.lg?.name_en }}
                <template v-if="selWard"> · {{ t('common.ward') }} {{ props.wards.find(w => w.id == selWard)?.ward_number }}</template>
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <span class="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac]">{{ t('housing.totalHouseholds') }}</span>
            <span class="text-[22px] font-semibold text-[#1d273b] tabular-nums leading-none">{{ fmt(props.totalHouseholds) }}</span>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <select v-model="selWard" @change="applyFilter"
            class="h-9 rounded border border-[#e6e7e9] bg-white px-3 text-[13px] text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] transition outline-none">
            <option value="">{{ t('filter.allWards') }}</option>
            <option v-for="w in props.wards" :key="w.id" :value="w.id">{{ t('common.ward') }} {{ w.ward_number }}</option>
          </select>
          <button v-if="selWard" @click="clearFilter"
            class="h-9 flex items-center gap-1.5 px-3 border border-[#e6e7e9] text-[#626976] text-[13px] rounded hover:bg-gray-50 transition">
            <i class="fa-solid fa-xmark text-[11px]"></i>{{ t('housing.allWards') }}
          </button>
        </div>
      </div>

      <!-- ── Foundation ── -->
      <section id="foundation" class="scroll-mt-[100px] mb-4">
        <HousingSection
          :title="t('housing.foundationTitle')"
          :subtitle="t('housing.foundationSubtitle')"
          icon="fa-solid fa-building-columns"
          icon-bg="bg-blue-100"
          icon-color="text-blue-600"
          :data="props.foundationData"
          chart-type="bar"
          :colors="['#3B82F6','#60A5FA','#93C5FD','#BFDBFE','#DBEAFE','#EFF6FF']"
        />
      </section>

      <!-- ── Roof ── -->
      <section id="roof" class="scroll-mt-[100px] mb-4">
        <HousingSection
          :title="t('housing.roofTitle')"
          :subtitle="t('housing.roofSubtitle')"
          icon="fa-solid fa-house-chimney"
          icon-bg="bg-emerald-100"
          icon-color="text-emerald-600"
          :data="props.roofData"
          chart-type="bar"
          :colors="['#10B981','#34D399','#6EE7B7','#059669','#047857','#A7F3D0']"
        />
      </section>

      <!-- ── Wall ── -->
      <section id="wall" class="scroll-mt-[100px] mb-4">
        <HousingSection
          :title="t('housing.wallTitle')"
          :subtitle="t('housing.wallSubtitle')"
          icon="fa-solid fa-layer-group"
          icon-bg="bg-amber-100"
          icon-color="text-amber-600"
          :data="props.wallData"
          chart-type="bar"
          :colors="['#F59E0B','#FBBF24','#FCD34D','#F97316','#FB923C','#D97706','#B45309','#FDE68A']"
        />
      </section>

      <!-- ── Ownership ── -->
      <section id="ownership" class="scroll-mt-[100px] mb-4">
        <HousingSection
          :title="t('housing.ownershipTitle')"
          :subtitle="t('housing.ownershipSubtitle')"
          icon="fa-solid fa-key"
          icon-bg="bg-purple-100"
          icon-color="text-purple-600"
          :data="props.ownershipData"
          chart-type="doughnut"
          :colors="['#8B5CF6','#A78BFA','#C4B5FD','#7C3AED','#DDD6FE']"
        />
      </section>

      <!-- ── Building Permit ── -->
      <section id="permit" class="scroll-mt-[100px] mb-4">
        <div v-if="props.permitData.length" class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-3 border-b border-[#e6e7e9] flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded bg-rose-100 flex items-center justify-center">
                <i class="fa-solid fa-file-circle-check text-rose-600 text-sm"></i>
              </div>
              <div>
                <p class="text-[13px] font-semibold text-[#1d273b]">{{ t('housing.permitTitle') }}</p>
                <p class="text-[11px] text-[#9aa0ac] mt-0.5">{{ t('housing.permitSubtitle') }}</p>
              </div>
            </div>
            <span class="text-[11px] text-[#9aa0ac] bg-gray-100 px-2 py-1 rounded">
              {{ fmt(props.permitData.reduce((s, r) => s + r.count, 0)) }} {{ t('housing.households') }}
            </span>
          </div>
          <div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-[#e6e7e9]">
            <!-- Doughnut -->
            <div class="p-8 flex items-center justify-center">
              <div style="max-width:280px; width:100%; height:280px;">
                <canvas ref="permitChartEl"></canvas>
              </div>
            </div>
            <!-- Cards + bar -->
            <div class="p-6 flex flex-col gap-4 justify-center">
              <div class="grid grid-cols-2 gap-3">
                <div v-for="row in props.permitData" :key="row.value"
                  class="rounded-md border p-4 text-center"
                  :class="row.value === 'yes' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'">
                  <p class="text-2xl font-semibold tabular-nums" :class="row.value === 'yes' ? 'text-green-700' : 'text-red-600'">
                    {{ fmt(row.count) }}
                  </p>
                  <p class="text-[13px] font-semibold mt-1" :class="row.value === 'yes' ? 'text-green-600' : 'text-red-500'">
                    {{ row.label }}
                  </p>
                  <p class="text-[11px] text-[#9aa0ac] mt-0.5">{{ row.percentage }}%</p>
                </div>
              </div>
              <div>
                <div class="flex justify-between text-[12px] font-semibold mb-1.5">
                  <span class="text-green-600">छ (Yes) — {{ props.permitData.find(r => r.value === 'yes')?.percentage ?? 0 }}%</span>
                  <span class="text-red-500">छैन (No) — {{ props.permitData.find(r => r.value === 'no')?.percentage ?? 0 }}%</span>
                </div>
                <div class="h-2 rounded-full overflow-hidden bg-red-200">
                  <div class="h-full bg-green-500 rounded-full transition-all"
                    :style="`width:${props.permitData.find(r => r.value === 'yes')?.percentage ?? 0}%`"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="bg-white rounded-md border border-[#e6e7e9] px-5 py-12 text-center text-[#9aa0ac]">
          <i class="fa-solid fa-file-circle-question text-3xl mb-3 block text-gray-200"></i>
          <p class="text-[13px]">{{ t('housing.permitNotAvailable') }}</p>
        </div>
      </section>

    </div>
  </div>
</template>

<script setup>
import { ref, watch, watchEffect, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import Chart from 'chart.js/auto'
import PublicLayout from '@/layouts/PublicLayout.vue'
import { useLocale } from '@/i18n/index.js'
import { useSidebar } from '@/composables/useSidebar.js'

defineOptions({ layout: PublicLayout })

const { t } = useLocale()
const { setSections, setActive, clearSections } = useSidebar()

const props = defineProps({
  lg:              { type: Object, default: null },
  wards:           { type: Array,  default: () => [] },
  selectedWardId:  { type: [Number, null], default: null },
  totalHouseholds: { type: Number, default: 0 },
  foundationData:  { type: Array,  default: () => [] },
  roofData:        { type: Array,  default: () => [] },
  wallData:        { type: Array,  default: () => [] },
  ownershipData:   { type: Array,  default: () => [] },
  permitData:      { type: Array,  default: () => [] },
})

const selWard = ref(props.selectedWardId ?? '')
watch(() => props.selectedWardId, v => { selWard.value = v ?? '' })

function applyFilter() {
  const p = {}
  if (selWard.value) p.ward_id = selWard.value
  router.get('/public/housing', p, { preserveState: true, replace: true })
}
function clearFilter() {
  selWard.value = ''
  router.get('/public/housing', {}, { preserveState: true, replace: true })
}

function scrollTo(id) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

watchEffect(() => setSections([
  { id: 'foundation', icon: '🏗️', label: t('housing.sectionNav.foundation'), onClick: () => scrollTo('foundation') },
  { id: 'roof',       icon: '🏠', label: t('housing.sectionNav.roof'),        onClick: () => scrollTo('roof') },
  { id: 'wall',       icon: '🧱', label: t('housing.sectionNav.wall'),        onClick: () => scrollTo('wall') },
  { id: 'ownership',  icon: '🔑', label: t('housing.sectionNav.ownership'),   onClick: () => scrollTo('ownership') },
  { id: 'permit',     icon: '📋', label: t('housing.sectionNav.permit'),      onClick: () => scrollTo('permit') },
]))

let scrollObserver = null

const permitChartEl = ref(null)
let permitChart = null

onMounted(() => {
  const els = document.querySelectorAll('section[id]')
  scrollObserver = new IntersectionObserver(
    entries => entries.forEach(e => { if (e.isIntersecting) setActive(e.target.id) }),
    { rootMargin: '-20% 0px -70% 0px' }
  )
  els.forEach(el => scrollObserver.observe(el))
  initPermitChart()
})

onUnmounted(() => { scrollObserver?.disconnect(); clearSections(); permitChart?.destroy() })

function initPermitChart() {
  if (!permitChartEl.value || !props.permitData.length) return
  permitChart?.destroy()
  permitChart = new Chart(permitChartEl.value, {
    type: 'doughnut',
    data: {
      labels: props.permitData.map(r => r.label),
      datasets: [{ data: props.permitData.map(r => r.count), backgroundColor: ['#10B981', '#EF4444', '#6B7280'], hoverOffset: 4 }],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12 } } },
    },
  })
}

watch(() => props.permitData, initPermitChart, { deep: true })
function fmt(n) { return Number(n ?? 0).toLocaleString() }
</script>

<!-- HousingSection sub-component -->
<script>
import { ref, onMounted, onUnmounted, watch, h } from 'vue'
import Chart from 'chart.js/auto'

export const HousingSection = {
  props: {
    title:     String,
    subtitle:  String,
    icon:      String,
    iconBg:    String,
    iconColor: String,
    data:      { type: Array, default: () => [] },
    chartType: { type: String, default: 'bar' },
    colors:    { type: Array,  default: () => [] },
  },
  setup(props) {
    const canvasEl = ref(null)
    let chart = null

    function init() {
      if (!canvasEl.value || !props.data.length) return
      chart?.destroy()
      const isDough = props.chartType === 'doughnut'
      chart = new Chart(canvasEl.value, {
        type: isDough ? 'doughnut' : 'bar',
        data: {
          labels: props.data.map(r => r.label),
          datasets: [{
            label: 'Households',
            data: props.data.map(r => r.count),
            backgroundColor: props.colors,
            borderRadius: isDough ? 0 : 5,
            borderSkipped: false,
            hoverOffset: isDough ? 4 : 0,
          }],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          indexAxis: isDough ? undefined : 'y',
          plugins: {
            legend: isDough
              ? { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12 } }
              : { display: false },
          },
          scales: isDough ? {} : { x: { beginAtZero: true, grid: { color: '#f3f4f6' } } },
        },
      })
    }

    onMounted(init)
    onUnmounted(() => chart?.destroy())
    watch(() => props.data, init, { deep: true })

    return () => {
      if (!props.data.length) {
        return h('div', { class: 'bg-white rounded-md border border-[#e6e7e9] px-5 py-12 text-center text-[#9aa0ac]' }, [
          h('i', { class: `${props.icon} text-3xl text-gray-200 block mb-3` }),
          h('p', { class: 'text-[13px]' }, 'Data not yet available.'),
        ])
      }

      const isDough = props.chartType === 'doughnut'
      const total   = props.data.reduce((s, r) => s + r.count, 0)

      return h('div', { class: 'bg-white rounded-md border border-[#e6e7e9] overflow-hidden' }, [
        // Header
        h('div', { class: 'px-4 py-3 border-b border-[#e6e7e9] flex items-center justify-between' }, [
          h('div', { class: 'flex items-center gap-2' }, [
            h('div', { class: `w-8 h-8 rounded flex items-center justify-center ${props.iconBg}` }, [
              h('i', { class: `${props.icon} ${props.iconColor} text-sm` }),
            ]),
            h('div', {}, [
              h('p', { class: 'text-[13px] font-semibold text-[#1d273b]' }, props.title),
              h('p', { class: 'text-[11px] text-[#9aa0ac] mt-0.5' }, props.subtitle),
            ]),
          ]),
          h('span', { class: 'text-[11px] text-[#9aa0ac] bg-gray-100 px-2 py-1 rounded' }, `${total.toLocaleString()} घरधुरी`),
        ]),
        // Chart + table
        h('div', { class: 'grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-[#e6e7e9]' }, [
          h('div', { class: isDough ? 'p-8 flex items-center justify-center' : 'p-5' }, [
            h('div', { style: isDough ? 'max-width:280px;width:100%;height:280px' : 'height:240px;width:100%' }, [
              h('canvas', { ref: canvasEl }),
            ]),
          ]),
          h('div', { class: 'overflow-x-auto' }, [
            h('table', { class: 'w-full' }, [
              h('thead', { class: 'border-b border-[#e6e7e9]' }, [
                h('tr', {}, [
                  h('th', { class: 'px-4 py-2.5 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]' }, 'Type'),
                  h('th', { class: 'px-4 py-2.5 text-right text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]' }, 'Count'),
                  h('th', { class: 'px-4 py-2.5 text-right text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]' }, '%'),
                  h('th', { class: 'px-4 py-2.5 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em] w-28' }, 'Share'),
                ]),
              ]),
              h('tbody', { class: 'divide-y divide-[#e6e7e9]' },
                props.data.map(row => h('tr', { key: row.value, class: 'hover:bg-gray-50 transition-colors' }, [
                  h('td', { class: 'px-4 py-2.5 text-[13px] font-medium text-[#1d273b]' }, row.label),
                  h('td', { class: 'px-4 py-2.5 text-right text-[13px] font-semibold text-[#1d273b] tabular-nums' }, row.count.toLocaleString()),
                  h('td', { class: 'px-4 py-2.5 text-right text-[13px] text-[#626976] tabular-nums' }, `${row.percentage}%`),
                  h('td', { class: 'px-4 py-2.5' }, [
                    h('div', { class: 'h-1.5 rounded-full bg-gray-100 overflow-hidden' }, [
                      h('div', { class: 'h-full rounded-full bg-[#206bc4] transition-all', style: `width:${row.percentage}%` }),
                    ]),
                  ]),
                ]))
              ),
            ]),
          ]),
        ]),
      ])
    }
  },
}
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
