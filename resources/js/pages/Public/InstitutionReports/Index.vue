<template>
  <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-5 space-y-6">

    <!-- ── Page header ── -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-[18px] font-bold text-[#1d273b]">संस्था प्रतिवेदन</h1>
        <p class="text-[12px] text-[#9aa0ac] mt-0.5">Institution Reports Overview</p>
      </div>
      <a href="/public/opendata/institutions"
        class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-[12px] font-medium rounded-md transition-colors">
        <i class="fa-solid fa-download text-[11px]"></i> CSV डाउनलोड
      </a>
    </div>

    <!-- ── Hero Stats Bar ── -->
    <div v-if="summary" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
      <div v-for="card in statCards" :key="card.key"
        class="bg-white border border-[#e6e7e9] rounded-lg px-4 py-3.5
               flex flex-col gap-1 border-l-4"
        :style="`border-left-color:${card.color}`">
        <span class="text-[12px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac]">
          {{ card.label }}
        </span>
        <span class="text-[22px] font-bold leading-tight" :style="`color:${card.color}`">
          {{ (summary[card.key] ?? 0).toLocaleString() }}
        </span>
      </div>
    </div>

    <!-- ── By Type + By Ownership ── -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

      <!-- Institution by Type -->
      <div v-if="byType.length" class="bg-white border border-[#e6e7e9] rounded-xl p-5">
        <h3 class="text-[13px] font-semibold text-[#1d273b] mb-4">
          {{ t('instReports.byTypeTitle') }}
        </h3>
        <div class="space-y-2.5">
          <div v-for="row in byType" :key="row.name_en" class="flex items-center gap-3">
            <div class="text-[12px] text-[#626976] w-40 shrink-0 truncate">
              {{ locale === 'ne' ? (row.name_np || row.name_en) : (row.name_en || row.name_np) }}
            </div>
            <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
              <div class="h-2 rounded-full bg-[#6366f1] transition-all"
                :style="`width:${maxTypeCount > 0 ? Math.round(row.count / maxTypeCount * 100) : 0}%`">
              </div>
            </div>
            <span class="text-[12px] font-semibold text-[#1d273b] w-8 text-right shrink-0">
              {{ row.count }}
            </span>
          </div>
        </div>
      </div>

      <!-- Ownership breakdown -->
      <div v-if="byOwnership.length" class="bg-white border border-[#e6e7e9] rounded-xl p-5">
        <h3 class="text-[13px] font-semibold text-[#1d273b] mb-4">
          {{ t('instReports.byOwnershipTitle') }}
        </h3>
        <div class="space-y-2.5">
          <div v-for="(row, i) in byOwnership" :key="row.value" class="flex items-center gap-3">
            <div class="w-2.5 h-2.5 rounded-full shrink-0"
              :style="`background:${ownerColors[i % ownerColors.length]}`"></div>
            <div class="text-[12px] text-[#626976] flex-1 truncate">
              {{ ownerLabel(row.value) }}
            </div>
            <span class="text-[12px] font-semibold text-[#1d273b] shrink-0">{{ row.count }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Ward Summary ── -->
    <div v-if="Object.keys(byWard).length" class="bg-white border border-[#e6e7e9] rounded-xl p-5">
      <h3 class="text-[13px] font-semibold text-[#1d273b] mb-4">{{ t('instReports.byWardTitle') }}</h3>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
        <div v-for="(count, wardNum) in byWard" :key="wardNum"
          class="flex flex-col items-center justify-center bg-indigo-50 rounded-lg py-3 px-2">
          <span class="text-[11px] font-medium text-indigo-500 mb-0.5">
            {{ t('common.ward') }} {{ wardNum }}
          </span>
          <span class="text-[20px] font-bold text-[#4f46e5]">{{ count }}</span>
        </div>
      </div>
    </div>

    <!-- ── Report Category Cards ── -->
    <div>
      <h2 class="text-[15px] font-bold text-[#1d273b] mb-3">{{ t('instReports.allCategories') }}</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <a v-for="cat in categories" :key="cat.href" :href="cat.href"
          class="group bg-white border border-[#e6e7e9] rounded-xl p-5
                 hover:border-[#6366f1]/50 hover:shadow-md transition-all no-underline">
          <div class="w-11 h-11 rounded-xl flex items-center justify-center text-2xl mb-4"
            :style="`background:${cat.bg}`">
            {{ cat.icon }}
          </div>
          <h3 class="text-[14px] font-semibold text-[#1d273b] leading-snug mb-1
                     group-hover:text-[#6366f1] transition">
            {{ t(cat.labelKey) }}
          </h3>
          <p class="text-[12px] text-[#9aa0ac] leading-relaxed line-clamp-2">
            {{ t(cat.descKey) }}
          </p>
          <div class="mt-4 flex items-center gap-1 text-[12px] font-medium text-[#6366f1]
                      opacity-0 group-hover:opacity-100 transition">
            {{ t('reports.viewReport') }}
            <i class="fa-solid fa-arrow-right text-[10px]"></i>
          </div>
        </a>
      </div>
    </div>

    <!-- ── Data note ── -->
    <div class="bg-[#f0f4fa] rounded-lg px-5 py-4 flex items-start gap-3 text-[13px] text-[#626976]">
      <i class="fa-solid fa-circle-info text-[#6366f1] text-sm mt-0.5 shrink-0"></i>
      <span>{{ t('reports.indexNote') }}</span>
    </div>

  </div>
</template>

<script setup>
import { computed } from 'vue'
import PublicInstitutionReportsLayout from '@/layouts/PublicInstitutionReportsLayout.vue'
import { useLocale } from '@/i18n/index.js'

defineOptions({ layout: PublicInstitutionReportsLayout })

const { locale, t } = useLocale()

const props = defineProps({
  lg:          { type: Object,  default: null },
  summary:     { type: Object,  default: () => ({}) },
  byType:      { type: Array,   default: () => [] },
  byWard:      { type: Object,  default: () => ({}) },
  byOwnership: { type: Array,   default: () => [] },
  wards:       { type: Array,   default: () => [] },
  filters:     { type: Object,  default: () => ({}) },
})

const statCards = [
  { key: 'total_institutions', label: t('instReports.totalInstitutions'), color: '#6366f1' },
  { key: 'total_types',        label: t('instReports.totalTypes'),         color: '#0ca678' },
  { key: 'total_wards',        label: t('instReports.totalWards'),         color: '#f59f00' },
]

const maxTypeCount = computed(() =>
  props.byType.reduce((m, r) => Math.max(m, r.count), 0)
)

const ownerColors = ['#6366f1','#0ca678','#f59f00','#e74c3c','#3498db','#9b59b6','#1abc9c']

const ownershipLabels = {
  govt:        'सरकारी / Government', private: 'निजी / Private',
  community:   'सामुदायिक / Community', cooperative: 'सहकारी / Cooperative',
  ngo:         'गैसस / NGO', joint: 'संयुक्त / Joint', other: 'अन्य / Other',
}

function ownerLabel(val) { return ownershipLabels[val] ?? val }

const categories = [
  { href: '/public/institution-reports/by-type',       icon: '📋', bg: '#eef2ff',
    labelKey: 'instReports.cat.byType',       descKey: 'instReports.desc.byType' },
  { href: '/public/institution-reports/by-ownership',  icon: '🏢', bg: '#f0fdf4',
    labelKey: 'instReports.cat.byOwnership',  descKey: 'instReports.desc.byOwnership' },
  { href: '/public/institution-reports/infrastructure',icon: '⚡', bg: '#fefce8',
    labelKey: 'instReports.cat.infrastructure', descKey: 'instReports.desc.infrastructure' },
  { href: '/public/institution-reports/services',      icon: '🤝', bg: '#f0f9ff',
    labelKey: 'instReports.cat.services',     descKey: 'instReports.desc.services' },
  { href: '/public/institution-reports/staff',         icon: '👤', bg: '#fdf4ff',
    labelKey: 'instReports.cat.staff',        descKey: 'instReports.desc.staff' },
  { href: '/public/institution-reports/health',        icon: '🏥', bg: '#fff1f2',
    labelKey: 'instReports.cat.health',       descKey: 'instReports.desc.health' },
  { href: '/public/institution-reports/school',        icon: '🎓', bg: '#eff6ff',
    labelKey: 'instReports.cat.school',       descKey: 'instReports.desc.school' },
]
</script>
