<template>
  <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-5 space-y-6">

    <!-- ── Hero Stats Bar ── -->
    <div v-if="summary" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <div v-for="card in statCards" :key="card.key"
        class="bg-white border border-[#e6e7e9] rounded-lg px-4 py-3.5
               flex flex-col gap-1 border-l-4"
        :style="`border-left-color:${card.color}`">
        <span class="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac]">
          {{ card.label }}
        </span>
        <span class="text-[22px] font-bold leading-tight"
          :style="`color:${card.color}`">
          {{ (summary[card.key] ?? 0).toLocaleString() }}
        </span>
      </div>
    </div>

    <!-- ── Gender + Age Quick Charts side by side ── -->
    <div v-if="genderData.length || ageData.length"
      class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <FrequencyBar
        v-if="genderData.length"
        :data="genderData"
        :title="t('stats.population') + ' — ' + t('reports.cat.demographics')"
        title-en="Gender Distribution"
      />
      <FrequencyBar
        v-if="ageData.length"
        :data="ageData"
        :title="t('reports.cat.ageGroups')"
        title-en="Population by Age Group"
      />
    </div>

    <!-- ── Report Category Cards ── -->
    <div>
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-[15px] font-bold text-[#1d273b]">{{ t('reports.allCategories') }}</h2>
        <span class="text-[12px] text-[#9aa0ac]">{{ categories.length }} {{ t('reports.categories') }}</span>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <a v-for="cat in categories" :key="cat.href"
          :href="filteredHref(cat.href)"
          class="group bg-white border border-[#e6e7e9] rounded-xl p-5
                 hover:border-[#206bc4]/50 hover:shadow-md transition-all no-underline">

          <!-- Icon circle -->
          <div class="w-11 h-11 rounded-xl flex items-center justify-center text-2xl mb-4"
            :style="`background:${cat.bgRaw}`">
            {{ cat.icon }}
          </div>

          <!-- Title -->
          <h3 class="text-[14px] font-semibold text-[#1d273b] leading-snug mb-1
                     group-hover:text-[#206bc4] transition">
            {{ t(cat.labelKey) }}
          </h3>
          <p class="text-[12px] text-[#9aa0ac] leading-relaxed line-clamp-2">
            {{ t(cat.descKey) }}
          </p>

          <!-- Arrow -->
          <div class="mt-4 flex items-center gap-1 text-[12px] font-medium text-[#206bc4]
                      opacity-0 group-hover:opacity-100 transition">
            {{ t('reports.viewReport') }}
            <i class="fa-solid fa-arrow-right text-[10px]"></i>
          </div>
        </a>
      </div>
    </div>

    <!-- ── Data note ── -->
    <div class="bg-[#f0f4fa] rounded-lg px-5 py-4 flex items-start gap-3 text-[13px] text-[#626976]">
      <i class="fa-solid fa-circle-info text-[#206bc4] text-sm mt-0.5 shrink-0"></i>
      <span>{{ t('reports.indexNote') }}</span>
    </div>

  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useLocale } from '@/i18n/index.js'
import PublicReportsLayout from '@/layouts/PublicReportsLayout.vue'
import FrequencyBar from '@/components/reports/FrequencyBar.vue'

defineOptions({ layout: PublicReportsLayout })

const { t } = useLocale()

const props = defineProps({
  lg:         { type: Object, default: null },
  summary:    { type: Object, default: () => ({}) },
  genderData: { type: Array,  default: () => [] },
  ageData:    { type: Array,  default: () => [] },
  wards:      { type: Array,  default: () => [] },
  filters:    { type: Object, default: () => ({}) },
})

// Carry active ward/settlement filter into category card links
function filteredHref(base) {
  const p = new URLSearchParams()
  if (props.filters?.ward_id)       p.set('ward_id',       props.filters.ward_id)
  if (props.filters?.settlement_id) p.set('settlement_id', props.filters.settlement_id)
  const qs = p.toString()
  return base + (qs ? '?' + qs : '')
}

// ── Stat cards ────────────────────────────────────────────────────────────────
const statCards = computed(() => [
  { key: 'total_households',   label: t('stats.households'),   color: '#206bc4' },
  { key: 'total_population',   label: t('stats.population'),   color: '#1d273b' },
  { key: 'total_male',         label: t('stats.male'),         color: '#4299e1' },
  { key: 'total_female',       label: t('stats.female'),       color: '#f76707' },
  { key: 'total_other',        label: t('reports.otherGender'),color: '#9aa0ac' },
  { key: 'total_institutions', label: t('stats.institutions'), color: '#2fb344' },
])

// ── Category cards ────────────────────────────────────────────────────────────
const categories = [
  {
    href: '/public/reports/demographics',
    icon: '👥', bgRaw: '#eef2f8',
    labelKey: 'reports.cat.demographics',
    descKey:  'reports.desc.demographics',
  },
  {
    href: '/public/reports/age-groups',
    icon: '📅', bgRaw: '#ecfdf5',
    labelKey: 'reports.cat.ageGroups',
    descKey:  'reports.desc.ageGroups',
  },
  {
    href: '/public/reports/education',
    icon: '🎓', bgRaw: '#f9f0ff',
    labelKey: 'reports.cat.education',
    descKey:  'reports.desc.education',
  },
  {
    href: '/public/reports/health',
    icon: '🏥', bgRaw: '#fff5f5',
    labelKey: 'reports.cat.health',
    descKey:  'reports.desc.health',
  },
  {
    href: '/public/reports/employment',
    icon: '💼', bgRaw: '#fff4e6',
    labelKey: 'reports.cat.employment',
    descKey:  'reports.desc.employment',
  },
  {
    href: '/public/reports/housing',
    icon: '🏠', bgRaw: '#fffbe6',
    labelKey: 'reports.cat.housing',
    descKey:  'reports.desc.housing',
  },
  {
    href: '/public/reports/utilities',
    icon: '💧', bgRaw: '#ebf8ff',
    labelKey: 'reports.cat.utilities',
    descKey:  'reports.desc.utilities',
  },
  {
    href: '/public/reports/economics',
    icon: '💰', bgRaw: '#f0fff4',
    labelKey: 'reports.cat.economics',
    descKey:  'reports.desc.economics',
  },
]
</script>
