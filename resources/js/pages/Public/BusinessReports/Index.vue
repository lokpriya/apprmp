<template>
  <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-5 space-y-6">

    <!-- ── Page header ── -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-[18px] font-bold text-[#1d273b]">व्यवसाय प्रतिवेदन</h1>
        <p class="text-[12px] text-[#9aa0ac] mt-0.5">Business Reports Overview</p>
      </div>
      <a href="/public/opendata/businesses"
        class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-[12px] font-medium rounded-md transition-colors">
        <i class="fa-solid fa-download text-[11px]"></i> CSV डाउनलोड
      </a>
    </div>

    <!-- ── Hero Stats Bar ── -->
    <div v-if="summary" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <div v-for="card in statCards" :key="card.key"
        class="bg-white border border-[#e6e7e9] rounded-lg px-4 py-3.5
               flex flex-col gap-1 border-l-4"
        :style="`border-left-color:${card.color}`">
        <span class="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac]">
          {{ card.label }}
        </span>
        <span class="text-[22px] font-bold leading-tight" :style="`color:${card.color}`">
          {{ (summary[card.key] ?? 0).toLocaleString() }}
        </span>
      </div>
    </div>

    <!-- ── By Type + By Ward ── -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

      <!-- By Type -->
      <div v-if="byType.length" class="bg-white border border-[#e6e7e9] rounded-xl p-5">
        <h3 class="text-[13px] font-semibold text-[#1d273b] mb-4">
          {{ t('bizReports.byTypeTitle') }}
        </h3>
        <div class="space-y-2.5">
          <div v-for="(row, i) in byType" :key="row.value" class="flex items-center gap-3">
            <div class="w-2.5 h-2.5 rounded-full shrink-0"
              :style="`background:${typeColors[i % typeColors.length]}`"></div>
            <div class="text-[12px] text-[#626976] flex-1 truncate">
              {{ typeLabel(row.value) }}
            </div>
            <div class="w-24 bg-gray-100 rounded-full h-2 overflow-hidden shrink-0">
              <div class="h-2 rounded-full transition-all"
                :style="`width:${maxTypeCount > 0 ? Math.round(row.count / maxTypeCount * 100) : 0}%;background:${typeColors[i % typeColors.length]}`">
              </div>
            </div>
            <span class="text-[12px] font-semibold text-[#1d273b] w-8 text-right shrink-0">
              {{ row.count }}
            </span>
          </div>
        </div>
      </div>

      <!-- By Ward -->
      <div v-if="Object.keys(byWard).length" class="bg-white border border-[#e6e7e9] rounded-xl p-5">
        <h3 class="text-[13px] font-semibold text-[#1d273b] mb-4">{{ t('bizReports.byWardTitle') }}</h3>
        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
          <div v-for="(count, wardNum) in byWard" :key="wardNum"
            class="flex flex-col items-center justify-center bg-orange-50 rounded-lg py-3 px-2">
            <span class="text-[11px] font-medium text-orange-500 mb-0.5">
              {{ t('common.ward') }} {{ wardNum }}
            </span>
            <span class="text-[20px] font-bold text-[#f97316]">{{ count }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Compliance Overview ── -->
    <div class="bg-white border border-[#e6e7e9] rounded-xl p-5">
      <h3 class="text-[13px] font-semibold text-[#1d273b] mb-4">{{ t('bizReports.complianceOverview') }}</h3>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

        <div class="flex items-center gap-3 p-3 rounded-lg border border-[#e6e7e9]">
          <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-circle-check text-green-600 text-[16px]"></i>
          </div>
          <div>
            <div class="text-[11px] text-[#9aa0ac] font-medium">{{ t('bizReports.registered') }}</div>
            <div class="text-[20px] font-bold text-green-600">{{ summary?.registered ?? 0 }}</div>
            <div class="text-[11px] text-[#9aa0ac]">
              {{ regPct }}% {{ t('bizReports.ofTotal') }}
            </div>
          </div>
        </div>

        <div class="flex items-center gap-3 p-3 rounded-lg border border-[#e6e7e9]">
          <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-file-invoice text-blue-600 text-[16px]"></i>
          </div>
          <div>
            <div class="text-[11px] text-[#9aa0ac] font-medium">{{ t('bizReports.hasPanVat') }}</div>
            <div class="text-[20px] font-bold text-blue-600">{{ summary?.has_pan_vat ?? 0 }}</div>
            <div class="text-[11px] text-[#9aa0ac]">
              {{ panPct }}% {{ t('bizReports.ofTotal') }}
            </div>
          </div>
        </div>

        <div class="flex items-center gap-3 p-3 rounded-lg border border-[#e6e7e9]">
          <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-receipt text-amber-600 text-[16px]"></i>
          </div>
          <div>
            <div class="text-[11px] text-[#9aa0ac] font-medium">{{ t('bizReports.paidTax') }}</div>
            <div class="text-[20px] font-bold text-amber-600">{{ summary?.paid_tax ?? 0 }}</div>
            <div class="text-[11px] text-[#9aa0ac]">
              {{ taxPct }}% {{ t('bizReports.ofTotal') }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Report Category Cards ── -->
    <div>
      <h2 class="text-[15px] font-bold text-[#1d273b] mb-3">{{ t('bizReports.allCategories') }}</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <a v-for="cat in categories" :key="cat.href" :href="cat.href"
          class="group bg-white border border-[#e6e7e9] rounded-xl p-5
                 hover:border-[#f97316]/50 hover:shadow-md transition-all no-underline">
          <div class="w-11 h-11 rounded-xl flex items-center justify-center text-2xl mb-4"
            :style="`background:${cat.bg}`">
            {{ cat.icon }}
          </div>
          <h3 class="text-[14px] font-semibold text-[#1d273b] leading-snug mb-1
                     group-hover:text-[#f97316] transition">
            {{ t(cat.labelKey) }}
          </h3>
          <p class="text-[12px] text-[#9aa0ac] leading-relaxed line-clamp-2">
            {{ t(cat.descKey) }}
          </p>
          <div class="mt-4 flex items-center gap-1 text-[12px] font-medium text-[#f97316]
                      opacity-0 group-hover:opacity-100 transition">
            {{ t('reports.viewReport') }}
            <i class="fa-solid fa-arrow-right text-[10px]"></i>
          </div>
        </a>
      </div>
    </div>

    <!-- ── Data note ── -->
    <div class="bg-orange-50 border border-orange-100 rounded-lg px-5 py-4
                flex items-start gap-3 text-[13px] text-[#626976]">
      <i class="fa-solid fa-circle-info text-[#f97316] text-sm mt-0.5 shrink-0"></i>
      <span>{{ t('reports.indexNote') }}</span>
    </div>

  </div>
</template>

<script setup>
import { computed } from 'vue'
import PublicBusinessReportsLayout from '@/layouts/PublicBusinessReportsLayout.vue'
import { useLocale } from '@/i18n/index.js'

defineOptions({ layout: PublicBusinessReportsLayout })

const { locale, t } = useLocale()

const props = defineProps({
  lg:      { type: Object, default: null },
  summary: { type: Object, default: () => ({}) },
  byType:  { type: Array,  default: () => [] },
  byWard:  { type: Object, default: () => ({}) },
  wards:   { type: Array,  default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const typeLabelsMap = {
  Single:        'एकल स्वामित्व / Single',
  Partnership:   'साझेदारी / Partnership',
  PublicCompany: 'सार्वजनिक कम्पनी / Company',
  other:         'अन्य / Other',
}
function typeLabel(val) { return typeLabelsMap[val] ?? val }

const typeColors = ['#f97316', '#6366f1', '#0ca678', '#f59f00', '#e74c3c', '#3b82f6']

const maxTypeCount = computed(() =>
  props.byType.reduce((m, r) => Math.max(m, r.count), 0)
)

const regPct  = computed(() => props.summary?.total_businesses > 0
  ? Math.round((props.summary.registered ?? 0) / props.summary.total_businesses * 100) : 0)
const panPct  = computed(() => props.summary?.total_businesses > 0
  ? Math.round((props.summary.has_pan_vat ?? 0) / props.summary.total_businesses * 100) : 0)
const taxPct  = computed(() => props.summary?.total_businesses > 0
  ? Math.round((props.summary.paid_tax ?? 0) / props.summary.total_businesses * 100) : 0)

const statCards = [
  { key: 'total_businesses', label: t('bizReports.totalBusinesses'), color: '#f97316' },
  { key: 'total_wards',      label: t('bizReports.totalWards'),      color: '#6366f1' },
  { key: 'registered',       label: t('bizReports.registered'),      color: '#0ca678' },
  { key: 'unregistered',     label: t('bizReports.unregistered'),    color: '#e74c3c' },
  { key: 'has_pan_vat',      label: t('bizReports.hasPanVat'),       color: '#3b82f6' },
  { key: 'paid_tax',         label: t('bizReports.paidTax'),         color: '#f59f00' },
]

const categories = [
  { href: '/public/business-reports/by-type',     icon: '🏢', bg: '#fff7ed',
    labelKey: 'bizReports.cat.byType',       descKey: 'bizReports.desc.byType' },
  { href: '/public/business-reports/by-field',    icon: '📦', bg: '#eef2ff',
    labelKey: 'bizReports.cat.byField',      descKey: 'bizReports.desc.byField' },
  { href: '/public/business-reports/by-location', icon: '📍', bg: '#f0fdf4',
    labelKey: 'bizReports.cat.byLocation',   descKey: 'bizReports.desc.byLocation' },
  { href: '/public/business-reports/registration',icon: '📋', bg: '#fefce8',
    labelKey: 'bizReports.cat.registration', descKey: 'bizReports.desc.registration' },
]
</script>
