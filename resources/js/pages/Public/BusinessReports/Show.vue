<template>
  <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-5 space-y-5">

    <!-- ── Page Header ── -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex items-center gap-3 min-w-0">
        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-xl shrink-0"
          :style="`background:${catMeta.bg}`">
          {{ catMeta.icon }}
        </div>
        <div class="min-w-0">
          <h1 class="text-[18px] font-bold text-[#1d273b] leading-tight">{{ props.title }}</h1>
          <p class="text-[12px] text-[#9aa0ac] mt-0.5">
            {{ props.title_en }}<span v-if="props.lg"> · {{ props.lg.name_en }}</span>
          </p>
        </div>
      </div>
      <div v-if="filters?.ward_id"
        class="inline-flex items-center gap-1 text-[12px] bg-[#f97316]/8 text-[#f97316]
               border border-[#f97316]/20 rounded px-2 py-0.5">
        <i class="fa-solid fa-map-pin text-[9px]"></i>
        {{ t('common.ward') }} {{ wardNumber }}
      </div>
    </div>

    <!-- ═══════════════════════════════════════════
         REGISTRATION — compliance cards + tables
    ═══════════════════════════════════════════ -->
    <template v-if="category === 'registration'">

      <!-- Compliance summary cards -->
      <div v-if="complianceCards?.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div v-for="card in complianceCards" :key="card.key"
          class="bg-white border border-[#e6e7e9] rounded-lg px-4 py-3.5 border-l-4"
          :style="`border-left-color:${complianceColor(card.key)}`">
          <div class="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac]">
            {{ locale === 'ne' ? card.label_np : card.label_en }}
          </div>
          <div class="text-[22px] font-bold mt-1" :style="`color:${complianceColor(card.key)}`">
            {{ card.count.toLocaleString() }}
          </div>
          <div class="mt-1.5 bg-gray-100 rounded-full h-1.5 overflow-hidden">
            <div class="h-1.5 rounded-full transition-all"
              :style="`width:${card.percentage}%;background:${complianceColor(card.key)}`"></div>
          </div>
          <div class="text-[11px] text-[#9aa0ac] mt-0.5">{{ card.percentage }}%</div>
        </div>
      </div>

      <!-- Cross-tab tables -->
      <template v-if="tables.length">
        <div class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] mt-1">
          {{ t('reports.crossTabs') }}
        </div>
        <CrossTabTable
          v-for="tbl in tables" :key="tbl.id"
          :data="tbl.data" :title="tbl.title" :title-en="tbl.title_en" :chart-type="tbl.chart ?? 'bar'"
        />
      </template>

      <!-- Frequency bars -->
      <template v-if="frequencies.length">
        <div class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] mt-2">
          {{ t('reports.distributions') }}
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <FrequencyBar
            v-for="freq in frequencies" :key="freq.id"
            :data="freq.data" :title="freq.title" :title-en="freq.title_en"
          />
        </div>
      </template>

      <div v-if="!complianceCards?.length && !tables.length && !frequencies.length"
        class="bg-white border border-[#e6e7e9] rounded-xl p-8 text-center text-[#9aa0ac] text-[14px]">
        {{ t('reports.noData') }}
      </div>
    </template>

    <!-- ═══════════════════════════════════════════
         DEFAULT — Cross-tab tables + frequency bars
    ═══════════════════════════════════════════ -->
    <template v-else>
      <template v-if="tables.length">
        <div class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] mt-1">
          {{ t('reports.crossTabs') }}
        </div>
        <CrossTabTable
          v-for="tbl in tables" :key="tbl.id"
          :data="tbl.data" :title="tbl.title" :title-en="tbl.title_en" :chart-type="tbl.chart ?? 'bar'"
        />
      </template>

      <template v-if="frequencies.length">
        <div class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] mt-2">
          {{ t('reports.distributions') }}
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <FrequencyBar
            v-for="freq in frequencies" :key="freq.id"
            :data="freq.data" :title="freq.title" :title-en="freq.title_en"
          />
        </div>
      </template>

      <div v-if="!tables.length && !frequencies.length"
        class="bg-white border border-[#e6e7e9] rounded-xl p-8 text-center text-[#9aa0ac] text-[14px]">
        {{ t('reports.noData') }}
      </div>
    </template>

    <!-- ── Navigate other categories ── -->
    <div class="border-t border-[#e6e7e9] pt-5">
      <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] mb-3">
        {{ t('bizReports.otherCategories') }}
      </p>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <a v-for="cat in otherCategories" :key="cat.href" :href="cat.href"
          class="flex items-center gap-2.5 px-3 py-2.5 bg-white border border-[#e6e7e9]
                 rounded-lg hover:border-[#f97316]/40 transition no-underline group">
          <span class="text-[18px] shrink-0">{{ cat.icon }}</span>
          <span class="text-[13px] font-medium text-[#1d273b] group-hover:text-[#f97316] transition truncate">
            {{ t(cat.labelKey) }}
          </span>
          <i class="fa-solid fa-arrow-right text-[10px] text-[#c8ccd4] group-hover:text-[#f97316] ml-auto transition"></i>
        </a>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed } from 'vue'
import PublicBusinessReportsLayout from '@/layouts/PublicBusinessReportsLayout.vue'
import CrossTabTable from '@/components/reports/CrossTabTable.vue'
import FrequencyBar  from '@/components/reports/FrequencyBar.vue'
import { useLocale } from '@/i18n/index.js'

defineOptions({ layout: PublicBusinessReportsLayout })

const { locale, t } = useLocale()

const props = defineProps({
  lg:              { type: Object, default: null },
  category:        { type: String, default: '' },
  title:           { type: String, default: '' },
  title_en:        { type: String, default: '' },
  wards:           { type: Array,  default: () => [] },
  filters:         { type: Object, default: () => ({}) },
  tables:          { type: Array,  default: () => [] },
  frequencies:     { type: Array,  default: () => [] },
  complianceCards: { type: Array,  default: null },
  totalBusinesses: { type: Number, default: 0 },
})

const catMetaMap = {
  'by-type':     { icon: '🏢', bg: '#fff7ed' },
  'by-field':    { icon: '📦', bg: '#eef2ff' },
  'by-location': { icon: '📍', bg: '#f0fdf4' },
  'registration':{ icon: '📋', bg: '#fefce8' },
}

const catMeta = computed(() => catMetaMap[props.category] ?? { icon: '🏪', bg: '#fff7ed' })

const wardNumber = computed(() =>
  props.wards.find(w => String(w.id) === String(props.filters?.ward_id))?.ward_number ?? props.filters?.ward_id
)

const complianceColorMap = {
  registered:      '#0ca678',
  unregistered:    '#e74c3c',
  has_pan_vat:     '#3b82f6',
  paid_tax:        '#f59f00',
  fully_compliant: '#6366f1',
}
function complianceColor(key) { return complianceColorMap[key] ?? '#9aa0ac' }

const ALL_CATEGORIES = [
  { href: '/public/business-reports',             icon: '🏪', labelKey: 'bizReports.cat.overview',    route: 'overview' },
  { href: '/public/business-reports/by-type',     icon: '🏢', labelKey: 'bizReports.cat.byType',      route: 'by-type' },
  { href: '/public/business-reports/by-field',    icon: '📦', labelKey: 'bizReports.cat.byField',     route: 'by-field' },
  { href: '/public/business-reports/by-location', icon: '📍', labelKey: 'bizReports.cat.byLocation',  route: 'by-location' },
  { href: '/public/business-reports/registration',icon: '📋', labelKey: 'bizReports.cat.registration',route: 'registration' },
]

const otherCategories = computed(() => ALL_CATEGORIES.filter(c => c.route !== props.category))
</script>
