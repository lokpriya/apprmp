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
        class="inline-flex items-center gap-1 text-[12px] bg-[#6366f1]/8 text-[#6366f1]
               border border-[#6366f1]/20 rounded px-2 py-0.5">
        <i class="fa-solid fa-map-pin text-[9px]"></i>
        {{ t('common.ward') }} {{ wardNumber }}
      </div>
    </div>

    <!-- ═══════════════════════════════════════════
         INFRASTRUCTURE — facility checklist
    ═══════════════════════════════════════════ -->
    <template v-if="category === 'infrastructure'">
      <div v-if="facilityData?.length" class="bg-white border border-[#e6e7e9] rounded-xl p-5">
        <h2 class="text-[14px] font-semibold text-[#1d273b] mb-1">
          {{ t('instReports.facilityAvailability') }}
        </h2>
        <p class="text-[12px] text-[#9aa0ac] mb-4">
          {{ t('instReports.outOf', { n: totalInfra }) }}
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <div v-for="row in facilityData" :key="row.field"
            class="flex items-center gap-3 p-3 rounded-lg border border-[#e6e7e9]">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
              :class="row.percentage >= 70 ? 'bg-green-50' : row.percentage >= 40 ? 'bg-amber-50' : 'bg-red-50'">
              <i :class="[facilityIcons[row.field] ?? 'fa-solid fa-check', 'text-[13px]',
                          row.percentage >= 70 ? 'text-green-600' : row.percentage >= 40 ? 'text-amber-600' : 'text-red-500']">
              </i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-[12px] font-medium text-[#1d273b] leading-tight">
                {{ locale === 'ne' ? row.label_np : row.label_en }}
              </div>
              <div class="mt-1 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                <div class="h-1.5 rounded-full transition-all"
                  :class="row.percentage >= 70 ? 'bg-green-500' : row.percentage >= 40 ? 'bg-amber-500' : 'bg-red-400'"
                  :style="`width:${row.percentage}%`">
                </div>
              </div>
              <div class="text-[11px] text-[#9aa0ac] mt-0.5">
                {{ row.count }} — {{ row.percentage }}%
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="conditionFreq?.length" class="bg-white border border-[#e6e7e9] rounded-xl p-5">
        <h2 class="text-[14px] font-semibold text-[#1d273b] mb-4">{{ t('instReports.conditionTitle') }}</h2>
        <div class="space-y-2.5 max-w-lg">
          <div v-for="row in conditionFreq" :key="row.value" class="flex items-center gap-3">
            <div class="text-[12px] text-[#626976] w-28 shrink-0">{{ row.label ?? row.value }}</div>
            <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
              <div class="h-2 rounded-full bg-[#6366f1]" :style="`width:${row.percentage}%`"></div>
            </div>
            <span class="text-[12px] font-semibold text-[#1d273b] w-20 text-right shrink-0">
              {{ row.count }} ({{ row.percentage }}%)
            </span>
          </div>
        </div>
      </div>

      <div v-if="!facilityData?.length && !conditionFreq?.length"
        class="bg-white border border-[#e6e7e9] rounded-xl p-8 text-center text-[#9aa0ac] text-[14px]">
        {{ t('reports.noData') }}
      </div>
    </template>

    <!-- ═══════════════════════════════════════════
         SERVICES
    ═══════════════════════════════════════════ -->
    <template v-else-if="category === 'services'">
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div class="bg-white border border-[#e6e7e9] rounded-lg px-4 py-3.5 border-l-4 border-l-[#6366f1]">
          <span class="text-[12px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac]">
            {{ t('instReports.totalServices') }}
          </span>
          <div class="text-[22px] font-bold text-[#6366f1] mt-1">{{ (totalServices ?? 0).toLocaleString() }}</div>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-lg px-4 py-3.5 border-l-4 border-l-[#0ca678]">
          <span class="text-[12px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac]">
            {{ t('instReports.totalBeneficiaries') }}
          </span>
          <div class="text-[22px] font-bold text-[#0ca678] mt-1">{{ (totalBeneficiaries ?? 0).toLocaleString() }}</div>
        </div>
      </div>

      <div v-if="freeFreq?.length" class="bg-white border border-[#e6e7e9] rounded-xl p-5">
        <h2 class="text-[14px] font-semibold text-[#1d273b] mb-4">{{ t('instReports.freePaidTitle') }}</h2>
        <div class="flex flex-wrap gap-4">
          <div v-for="row in freeFreq" :key="row.value"
            class="flex items-center gap-2.5 px-4 py-2.5 rounded-lg border border-[#e6e7e9]">
            <div class="w-3 h-3 rounded-full"
              :class="row.value === 'free' ? 'bg-green-500' : 'bg-amber-500'"></div>
            <span class="text-[13px] text-[#1d273b]">{{ row.label }}</span>
            <span class="text-[13px] font-bold text-[#1d273b]">{{ row.count }}</span>
            <span class="text-[12px] text-[#9aa0ac]">({{ row.percentage }}%)</span>
          </div>
        </div>
      </div>

      <div v-for="freq in frequencies" :key="freq.id">
        <FrequencyBar :data="freq.data" :title="freq.title" :title-en="freq.title_en" />
      </div>

      <div v-if="!frequencies.length && !freeFreq?.length"
        class="bg-white border border-[#e6e7e9] rounded-xl p-8 text-center text-[#9aa0ac] text-[14px]">
        {{ t('reports.noData') }}
      </div>
    </template>

    <!-- ═══════════════════════════════════════════
         STAFF
    ═══════════════════════════════════════════ -->
    <template v-else-if="category === 'staff'">
      <div v-if="staffSummary" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div v-for="card in staffCards" :key="card.key"
          class="bg-white border border-[#e6e7e9] rounded-lg px-3 py-3 border-l-4"
          :style="`border-left-color:${card.color}`">
          <span class="text-[12px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac]">
            {{ card.label }}
          </span>
          <div class="text-[20px] font-bold mt-0.5" :style="`color:${card.color}`">
            {{ (staffSummary[card.key] ?? 0).toLocaleString() }}
          </div>
        </div>
      </div>

      <div v-if="byCategory?.length" class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-[#e6e7e9]">
          <h2 class="text-[14px] font-semibold text-[#1d273b]">{{ t('instReports.staffByCategory') }}</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-[13px]">
            <thead>
              <tr class="bg-gray-50 border-b border-[#e6e7e9]">
                <th class="text-left px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide w-40">
                  {{ t('instReports.category') }}
                </th>
                <th class="text-right px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">{{ t('instReports.male') }}</th>
                <th class="text-right px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">{{ t('instReports.female') }}</th>
                <th class="text-right px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">{{ t('instReports.permanent') }}</th>
                <th class="text-right px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">{{ t('instReports.contract') }}</th>
                <th class="text-right px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">{{ t('reports.rowTotal') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in byCategory" :key="row.category"
                class="border-b border-[#f0f0f0] hover:bg-gray-50 transition">
                <td class="px-4 py-2.5 font-medium text-[#1d273b]">{{ row.category_lbl || row.category }}</td>
                <td class="px-4 py-2.5 text-right text-[#626976]">{{ row.male.toLocaleString() }}</td>
                <td class="px-4 py-2.5 text-right text-[#626976]">{{ row.female.toLocaleString() }}</td>
                <td class="px-4 py-2.5 text-right text-[#626976]">{{ row.permanent.toLocaleString() }}</td>
                <td class="px-4 py-2.5 text-right text-[#626976]">{{ row.contract.toLocaleString() }}</td>
                <td class="px-4 py-2.5 text-right font-bold text-[#1d273b]">{{ row.total.toLocaleString() }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="bg-gray-50 font-bold">
                <td class="px-4 py-2.5 text-[#1d273b]">{{ t('reports.grandTotal') }}</td>
                <td class="px-4 py-2.5 text-right text-[#6366f1]">{{ staffSummary?.male?.toLocaleString() }}</td>
                <td class="px-4 py-2.5 text-right text-[#6366f1]">{{ staffSummary?.female?.toLocaleString() }}</td>
                <td class="px-4 py-2.5 text-right text-[#6366f1]">{{ staffSummary?.permanent?.toLocaleString() }}</td>
                <td class="px-4 py-2.5 text-right text-[#6366f1]">{{ staffSummary?.contract?.toLocaleString() }}</td>
                <td class="px-4 py-2.5 text-right text-[#6366f1]">{{ staffSummary?.total?.toLocaleString() }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div v-if="!byCategory?.length"
        class="bg-white border border-[#e6e7e9] rounded-xl p-8 text-center text-[#9aa0ac] text-[14px]">
        {{ t('reports.noData') }}
      </div>
    </template>

    <!-- ═══════════════════════════════════════════
         EDUCATION
    ═══════════════════════════════════════════ -->
    <!-- ═══════════════════════════════════════════
         HEALTH
    ═══════════════════════════════════════════ -->
    <template v-else-if="category === 'health'">
      <!-- Summary cards -->
      <div v-if="healthSummary" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div v-for="card in healthCards" :key="card.key"
          class="bg-white border border-[#e6e7e9] rounded-lg px-4 py-3.5 border-l-4"
          :style="`border-left-color:${card.color}`">
          <span class="text-[12px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac]">
            {{ card.label }}
          </span>
          <div class="text-[20px] font-bold mt-0.5" :style="`color:${card.color}`">
            {{ (healthSummary[card.key] ?? 0).toLocaleString() }}
          </div>
        </div>
      </div>

      <!-- Bed breakdown -->
      <div v-if="bedBreakdown" class="bg-white border border-[#e6e7e9] rounded-xl p-5">
        <h2 class="text-[14px] font-semibold text-[#1d273b] mb-4">{{ t('instReports.bedBreakdownTitle') }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
          <div v-for="bed in bedCards" :key="bed.key"
            class="flex flex-col items-center justify-center bg-red-50 rounded-lg py-3 px-2 text-center">
            <span class="text-[12px] font-medium text-red-500 mb-0.5">{{ bed.label }}</span>
            <span class="text-[20px] font-bold text-[#d63939]">{{ (bedBreakdown[bed.key] ?? 0).toLocaleString() }}</span>
          </div>
        </div>
      </div>

      <!-- Service availability -->
      <div v-if="serviceData?.length" class="bg-white border border-[#e6e7e9] rounded-xl p-5">
        <h2 class="text-[14px] font-semibold text-[#1d273b] mb-1">
          {{ t('instReports.serviceAvailability') }}
        </h2>
        <p class="text-[12px] text-[#9aa0ac] mb-4">
          {{ t('instReports.outOf', { n: healthSummary?.total_facilities ?? 0 }) }}
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <div v-for="row in serviceData" :key="row.field"
            class="flex items-center gap-3 p-3 rounded-lg border border-[#e6e7e9]">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
              :class="row.percentage >= 70 ? 'bg-green-50' : row.percentage >= 40 ? 'bg-amber-50' : 'bg-red-50'">
              <i :class="['fa-solid', healthServiceIcons[row.field] ?? 'fa-check', 'text-[13px]',
                          row.percentage >= 70 ? 'text-green-600' : row.percentage >= 40 ? 'text-amber-600' : 'text-red-500']">
              </i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-[12px] font-medium text-[#1d273b] leading-tight">
                {{ locale === 'ne' ? row.label_np : row.label_en }}
              </div>
              <div class="mt-1 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                <div class="h-1.5 rounded-full transition-all"
                  :class="row.percentage >= 70 ? 'bg-green-500' : row.percentage >= 40 ? 'bg-amber-500' : 'bg-red-400'"
                  :style="`width:${row.percentage}%`">
                </div>
              </div>
              <div class="text-[11px] text-[#9aa0ac] mt-0.5">
                {{ row.count }} — {{ row.percentage }}%
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- By health type -->
      <div v-if="byHealthType?.length" class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-[#e6e7e9]">
          <h2 class="text-[14px] font-semibold text-[#1d273b]">{{ t('instReports.byHealthTypeTitle') }}</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-[13px]">
            <thead>
              <tr class="bg-gray-50 border-b border-[#e6e7e9]">
                <th class="text-left px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">
                  {{ locale === 'ne' ? 'प्रकार' : 'Health Type' }}
                </th>
                <th class="text-right px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">
                  {{ locale === 'ne' ? 'संस्था' : 'Facilities' }}
                </th>
                <th class="text-right px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">
                  {{ t('instReports.totalBeds') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in byHealthType" :key="row.type"
                class="border-b border-[#f0f0f0] hover:bg-gray-50 transition">
                <td class="px-4 py-2.5 font-medium text-[#1d273b]">{{ row.type_lbl || row.type }}</td>
                <td class="px-4 py-2.5 text-right text-[#626976]">{{ row.count.toLocaleString() }}</td>
                <td class="px-4 py-2.5 text-right font-bold text-[#d63939]">{{ row.beds.toLocaleString() }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="!serviceData?.length && !byHealthType?.length"
        class="bg-white border border-[#e6e7e9] rounded-xl p-8 text-center text-[#9aa0ac] text-[14px]">
        {{ t('reports.noData') }}
      </div>
    </template>

    <!-- ═══════════════════════════════════════════
         DEFAULT — Cross-tab tables + frequencies
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
        {{ t('instReports.otherCategories') }}
      </p>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <a v-for="cat in otherCategories" :key="cat.href" :href="cat.href"
          class="flex items-center gap-2.5 px-3 py-2.5 bg-white border border-[#e6e7e9]
                 rounded-lg hover:border-[#6366f1]/40 transition no-underline group">
          <span class="text-[18px] shrink-0">{{ cat.icon }}</span>
          <span class="text-[13px] font-medium text-[#1d273b] group-hover:text-[#6366f1] transition truncate">
            {{ t(cat.labelKey) }}
          </span>
          <i class="fa-solid fa-arrow-right text-[10px] text-[#c8ccd4] group-hover:text-[#6366f1] ml-auto transition"></i>
        </a>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed } from 'vue'
import PublicInstitutionReportsLayout from '@/layouts/PublicInstitutionReportsLayout.vue'
import CrossTabTable from '@/components/reports/CrossTabTable.vue'
import FrequencyBar  from '@/components/reports/FrequencyBar.vue'
import { useLocale } from '@/i18n/index.js'

defineOptions({ layout: PublicInstitutionReportsLayout })

const { locale, t } = useLocale()

const props = defineProps({
  lg:                  { type: Object,  default: null },
  category:            { type: String,  default: '' },
  title:               { type: String,  default: '' },
  title_en:            { type: String,  default: '' },
  wards:               { type: Array,   default: () => [] },
  filters:             { type: Object,  default: () => ({}) },
  tables:              { type: Array,   default: () => [] },
  frequencies:         { type: Array,   default: () => [] },
  facilityData:        { type: Array,   default: null },
  conditionFreq:       { type: Array,   default: null },
  totalInfra:          { type: Number,  default: 0 },
  freeFreq:            { type: Array,   default: null },
  totalServices:       { type: Number,  default: 0 },
  totalBeneficiaries:  { type: Number,  default: 0 },
  staffSummary:        { type: Object,  default: null },
  byCategory:          { type: Array,   default: null },
  healthSummary:       { type: Object,  default: null },
  serviceData:         { type: Array,   default: null },
  byHealthType:        { type: Array,   default: null },
  bedBreakdown:        { type: Object,  default: null },
})

const catMetaMap = {
  'by-type':        { icon: '📋', bg: '#eef2ff' },
  'by-ownership':   { icon: '🏢', bg: '#f0fdf4' },
  'infrastructure': { icon: '⚡', bg: '#fefce8' },
  'services':       { icon: '🤝', bg: '#f0f9ff' },
  'staff':          { icon: '👤', bg: '#fdf4ff' },
  'health':         { icon: '🏥', bg: '#fff1f2' },
}

const catMeta = computed(() => catMetaMap[props.category] ?? { icon: '🏛️', bg: '#eef2ff' })

const wardNumber = computed(() =>
  props.wards.find(w => String(w.id) === String(props.filters?.ward_id))?.ward_number ?? props.filters?.ward_id
)

const staffCards = [
  { key: 'total',     label: t('instReports.totalStaff'),   color: '#6366f1' },
  { key: 'male',      label: t('instReports.male'),          color: '#3b82f6' },
  { key: 'female',    label: t('instReports.female'),        color: '#ec4899' },
  { key: 'permanent', label: t('instReports.permanent'),     color: '#0ca678' },
  { key: 'contract',  label: t('instReports.contract'),      color: '#f59f00' },
  { key: 'dalit',     label: t('instReports.dalit'),         color: '#e74c3c' },
  { key: 'janajati',  label: t('instReports.janajati'),      color: '#ae3ec9' },
]

const facilityIcons = {
  has_electricity:    'fa-solid fa-bolt',
  has_water:          'fa-solid fa-droplet',
  has_internet:       'fa-solid fa-wifi',
  has_ramp:           'fa-solid fa-wheelchair',
  has_boundary_wall:  'fa-solid fa-border-all',
  has_toilet:         'fa-solid fa-restroom',
  earthquake_safe:    'fa-solid fa-house-crack',
  evacuation_plan:    'fa-solid fa-person-running',
  fire_extinguisher:  'fa-solid fa-fire-extinguisher',
  solar:              'fa-solid fa-sun',
  generator:          'fa-solid fa-plug',
  has_library:        'fa-solid fa-book',
  has_lab:            'fa-solid fa-flask',
}

const healthCards = [
  { key: 'total_facilities', label: t('instReports.totalFacilities'), color: '#d63939' },
  { key: 'total_beds',       label: t('instReports.totalBeds'),       color: '#f59f00' },
  { key: 'opd_daily',        label: t('instReports.opdDaily'),        color: '#0ca678' },
  { key: 'opd_monthly',      label: t('instReports.opdMonthly'),      color: '#6366f1' },
]

const bedCards = [
  { key: 'male_ward',   label: t('instReports.bedMaleWard') },
  { key: 'female_ward', label: t('instReports.bedFemaleWard') },
  { key: 'children',    label: t('instReports.bedChildren') },
  { key: 'maternity',   label: t('instReports.bedMaternity') },
  { key: 'icu',         label: t('instReports.bedIcu') },
  { key: 'emergency',   label: t('instReports.bedEmergency') },
]

const healthServiceIcons = {
  delivery_service:       'fa-baby',
  emergency_service:      'fa-truck-medical',
  has_lab:                'fa-flask',
  has_xray:               'fa-radiation',
  has_ambulance:          'fa-ambulance',
  vaccine_service:        'fa-syringe',
  family_planning:        'fa-heart',
  has_ultrasound:         'fa-wave-square',
  has_ecg:                'fa-heartbeat',
  has_icu:                'fa-bed-pulse',
  has_operation_theatre:  'fa-scalpel',
  has_blood_bank:         'fa-droplet',
  has_dialysis:           'fa-circle-nodes',
  has_dental:             'fa-tooth',
  has_physiotherapy:      'fa-person-walking',
  has_oxygen_plant:       'fa-wind',
}

const ALL_CATEGORIES = [
  { href: '/public/institution-reports/by-type',        icon: '📋', labelKey: 'instReports.cat.byType',       route: 'by-type' },
  { href: '/public/institution-reports/by-ownership',   icon: '🏢', labelKey: 'instReports.cat.byOwnership',  route: 'by-ownership' },
  { href: '/public/institution-reports/infrastructure', icon: '⚡', labelKey: 'instReports.cat.infrastructure',route: 'infrastructure' },
  { href: '/public/institution-reports/services',       icon: '🤝', labelKey: 'instReports.cat.services',     route: 'services' },
  { href: '/public/institution-reports/staff',          icon: '👤', labelKey: 'instReports.cat.staff',        route: 'staff' },
  { href: '/public/institution-reports/health',         icon: '🏥', labelKey: 'instReports.cat.health',       route: 'health' },
  { href: '/public/institution-reports/school',         icon: '🎓', labelKey: 'instReports.cat.school',       route: 'school' },
]

const otherCategories = computed(() => ALL_CATEGORIES.filter(c => c.route !== props.category))
</script>
