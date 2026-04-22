<template>
  <div>

    <!-- ── Page body ── -->
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 py-4">

      <!-- ══ WELCOME BANNER ══ -->
      <Transition name="banner-slide">
        <div v-if="showBanner" class="mb-4">
          <!-- Close button row -->
          <div class="flex justify-end mb-1.5">
            <button @click="dismissBanner"
              class="flex items-center gap-1.5 text-xs text-gray-400 hover:text-gray-600 transition-colors px-2 py-1 rounded hover:bg-gray-100">
              <i class="fa-solid fa-xmark"></i>
              बन्द गर्नुहोस्
            </button>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

            <!-- Welcome message card -->
            <div class="lg:col-span-5 bg-white border border-[#e6e7e9] rounded-md p-6 flex flex-col">
              <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-2">स्वागत सन्देश</p>
              <h2 class="text-2xl font-bold text-gray-900 leading-tight mb-3">
                {{ props.welcomeConfig.message || 'हार्दिक स्वागत छ' }}
              </h2>
              <p class="text-sm text-gray-600 leading-relaxed flex-1">
                {{ props.welcomeConfig.message_body || '' }}
              </p>
              <a v-if="props.lg" href="/public/profile"
                class="inline-flex items-center gap-1.5 mt-4 self-start px-4 py-2 bg-[#206bc4] hover:bg-[#1a5ba8] text-white rounded text-sm font-medium transition-colors">
                थप जानकारी →
              </a>
            </div>

            <!-- Map image -->
            <div class="lg:col-span-4 bg-white border border-[#e6e7e9] rounded-md p-4 flex flex-col">
              <p class="text-xs font-semibold text-gray-500 flex items-center gap-1.5 mb-3">
                <i class="fa-solid fa-location-dot text-[#7b1f1f]"></i> स्थान नक्सा
              </p>
              <div class="flex-1 flex items-center justify-center bg-[#fdf5f5] rounded border border-dashed border-[#e6c5c5] min-h-[140px]">
                <img v-if="props.welcomeConfig?.map_image"
                  :src="`/storage/${props.welcomeConfig.map_image}`"
                  class="max-h-48 max-w-full object-contain p-2" />
                <div v-else class="text-center py-4">
                  <i class="fa-solid fa-map text-[#c0a0a0] text-3xl mb-2 block"></i>
                  <p class="text-xs text-gray-400">नक्सा चित्र उपलब्ध छैन</p>
                </div>
              </div>
            </div>

            <!-- Leadership cards -->
            <div class="lg:col-span-3 flex flex-col gap-3">
              <template v-for="(leader, role) in {
                chairperson:      { label: 'अध्यक्ष',                    defaultEmail: 'chair@mun.gov.np' },
                vice_chairperson: { label: 'उपाध्यक्ष',                   defaultEmail: 'vice@mun.gov.np' },
                cao:              { label: 'प्रमुख प्रशासकीय अधिकृत',    defaultEmail: 'cao@mun.gov.np' },
              }" :key="role">
                <div v-if="props.welcomeConfig[role]?.name || props.welcomeConfig[role]?.designation"
                  class="bg-white border border-[#e6e7e9] rounded-md px-4 py-3 flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-gray-100 overflow-hidden shrink-0 flex items-center justify-center">
                    <img v-if="props.welcomeConfig[role]?.photo"
                      :src="`/storage/${props.welcomeConfig[role].photo}`"
                      class="w-full h-full object-cover" />
                    <i v-else class="fa-solid fa-user text-gray-300 text-lg"></i>
                  </div>
                  <div class="min-w-0">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide truncate">
                      {{ props.welcomeConfig[role]?.designation || leader.label }}
                    </p>
                    <p class="text-sm font-semibold text-gray-800 truncate">
                      {{ props.welcomeConfig[role]?.name || 'श्री/श्रीमती ———' }}
                    </p>
                    <div class="flex items-center gap-2 mt-0.5">
                      <span v-if="props.welcomeConfig[role]?.contact"
                        class="text-[11px] text-gray-500 flex items-center gap-1">
                        <i class="fa-solid fa-phone text-[9px] text-[#7b1f1f]"></i>
                        {{ props.welcomeConfig[role].contact }}
                      </span>
                      <span v-if="props.welcomeConfig[role]?.email"
                        class="text-[11px] text-gray-400 flex items-center gap-1 truncate">
                        <i class="fa-solid fa-envelope text-[9px]"></i>
                        {{ props.welcomeConfig[role].email }}
                      </span>
                    </div>
                  </div>
                </div>
              </template>
            </div>

          </div>
        </div>
      </Transition>

      <!-- ══ FILTER BAR ══ -->
      <div class="bg-white border border-[#e6e7e9] rounded-md p-4 mb-4">
        <div class="flex flex-wrap items-end gap-3">
          <!-- Ward -->
          <div class="flex-1 min-w-[160px]">
            <label class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] mb-1">
              {{ t('filter.ward') }}
            </label>
            <select v-model="selWard" @change="onWardChange"
              class="w-full h-9 rounded border border-[#e6e7e9] bg-white px-3 text-[13px] text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] transition outline-none">
              <option value="">{{ t('filter.allWards') }}</option>
              <option v-for="w in props.wards" :key="w.id" :value="w.id">{{ t('common.ward') }} {{ w.ward_number }}</option>
            </select>
          </div>
          <!-- Settlement -->
          <div class="flex-1 min-w-[160px]">
            <label class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] mb-1">
              {{ t('filter.tole') }}
            </label>
            <select v-model="selSettlement" @change="applyFilter"
              :disabled="!selWard || !props.settlements.length"
              class="w-full h-9 rounded border border-[#e6e7e9] bg-white px-3 text-[13px] text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] transition outline-none"
              :class="(!selWard || !props.settlements.length) ? 'opacity-40 cursor-not-allowed' : ''">
              <option value="">{{ t('filter.allToles') }}</option>
              <option v-for="s in props.settlements" :key="s.id" :value="s.id">{{ locale === 'ne' ? (s.name_np || s.name_en) : (s.name_en || s.name_np) }}</option>
            </select>
          </div>
          <!-- Buttons -->
          <div class="flex items-center gap-2">
            <button @click="applyFilter"
              class="h-9 flex items-center gap-1.5 px-4 bg-[#206bc4] hover:bg-[#1a5ba8] text-white text-[13px] font-medium rounded transition">
              <i class="fa-solid fa-filter text-[11px]"></i>{{ t('filter.filter') }}
            </button>
            <button v-if="isFiltered" @click="clearFilter"
              class="h-9 flex items-center gap-1.5 px-3 border border-[#e6e7e9] text-[#626976] text-[13px] rounded hover:bg-gray-50 transition">
              <i class="fa-solid fa-xmark text-[11px]"></i>{{ t('filter.clear') }}
            </button>
          </div>
        </div>
        <!-- Active filter badge -->
        <div v-if="isFiltered" class="mt-3 flex flex-wrap items-center gap-2">
          <span class="inline-flex items-center gap-1.5 text-[12px] text-[#206bc4] bg-[#206bc4]/8 border border-[#206bc4]/20 px-2.5 py-1 rounded">
            <i class="fa-solid fa-circle-info text-[10px]"></i>
            {{ t('filter.showing') }}
            <strong v-if="selWard">{{ t('common.ward') }} {{ props.wards.find(w => w.id == selWard)?.ward_number }}</strong>
            <template v-if="selSettlement && props.settlements.length">
              <span class="opacity-50">›</span>
              <strong>{{ locale === 'ne' ? (props.settlements.find(s => s.id == selSettlement)?.name_np || props.settlements.find(s => s.id == selSettlement)?.name_en) : (props.settlements.find(s => s.id == selSettlement)?.name_en || props.settlements.find(s => s.id == selSettlement)?.name_np) }}</strong>
            </template>
          </span>
        </div>
      </div>

      <!-- ══ STATS CARDS ══ -->
      <section id="analytics" class="scroll-mt-[100px] mb-4">
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 mb-4">
          <div v-for="card in statCards" :key="card.key"
            class="bg-white border border-[#e6e7e9] rounded-md p-4 hover:border-[#206bc4]/30 transition-colors">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac] mb-1.5">{{ card.label }}</p>
                <p class="text-2xl font-semibold leading-none" :class="card.valClass || 'text-[#1d273b]'">
                  {{ fmt(card.value) }}
                </p>
              </div>
              <div class="flex w-9 h-9 items-center justify-center rounded shrink-0 mt-0.5" :class="card.iconClass">
                <i :class="[card.icon, 'text-base']"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- ── MAP ── -->
        <div v-if="props.lg?.map_config !== undefined" class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden mb-4">
          <div class="flex items-center justify-between px-4 py-3 border-b border-[#e6e7e9]">
            <div>
              <p class="text-[13px] font-semibold text-[#1d273b]">{{ t('dashboard.locationMap') }}</p>
              <p class="text-[12px] text-[#9aa0ac] mt-0.5">{{ props.lg?.name_en }}</p>
            </div>
            <span class="text-[11px] text-[#9aa0ac] bg-gray-100 px-2 py-1 rounded">OpenStreetMap</span>
          </div>
          <div ref="mapEl" style="height: 400px; width: 100%;"></div>
        </div>

        <!-- ── CHARTS ROW ── -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 mb-4">
          <!-- Gender -->
          <div class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e6e7e9]">
              <p class="text-[13px] font-semibold text-[#1d273b]">{{ t('dashboard.genderChart') }}</p>
              <p class="text-[11px] text-[#9aa0ac] mt-0.5">{{ t('stats.male') }} · {{ t('stats.female') }}</p>
            </div>
            <div class="p-4 h-52"><canvas ref="genderChartEl"></canvas></div>
          </div>
          <!-- Age -->
          <div class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e6e7e9]">
              <p class="text-[13px] font-semibold text-[#1d273b]">{{ t('dashboard.ageChart') }}</p>
              <p class="text-[11px] text-[#9aa0ac] mt-0.5">{{ t('stats.population') }}</p>
            </div>
            <div class="p-4 h-52"><canvas ref="ageChartEl"></canvas></div>
          </div>
          <!-- Institutions -->
          <div v-if="hasInstitutions" class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e6e7e9]">
              <p class="text-[13px] font-semibold text-[#1d273b]">{{ t('dashboard.institutionChart') }}</p>
              <p class="text-[11px] text-[#9aa0ac] mt-0.5">{{ t('stats.institutions') }}</p>
            </div>
            <div class="p-4 h-52"><canvas ref="institutionChartEl"></canvas></div>
          </div>
        </div>
      </section>

      <!-- ══ WARD SUMMARY ══ -->
      <section id="wards" class="scroll-mt-[100px] mb-4">
        <div class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
          <div class="flex items-center justify-between px-4 py-3 border-b border-[#e6e7e9]">
            <div>
              <p class="text-[13px] font-semibold text-[#1d273b]">{{ t('dashboard.wardSummaryNp') }}</p>
              <p class="text-[11px] text-[#9aa0ac] mt-0.5">{{ t('dashboard.wardSummarySubtitle') }}</p>
            </div>
            <span class="text-[11px] text-[#9aa0ac] bg-gray-100 px-2 py-1 rounded">
              {{ props.wardSummary.length }} {{ t('stats.wards') }}
            </span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="border-b border-[#e6e7e9]">
                <tr>
                  <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-[#9aa0ac]">{{ t('dashboard.wardCol') }}</th>
                  <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-[#9aa0ac]">{{ t('stats.households') }}</th>
                  <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-blue-500">{{ t('stats.male') }}</th>
                  <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-pink-400">{{ t('stats.female') }}</th>
                  <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.08em] text-[#9aa0ac]">{{ t('stats.population') }}</th>
                  <th class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#9aa0ac] text-center">{{ t('dashboard.genderRatio') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="ws in props.wardSummary" :key="ws.ward_id"
                  class="border-b border-[#e6e7e9] hover:bg-gray-50 transition-colors"
                  :class="props.selectedWardId == ws.ward_id ? 'bg-[#206bc4]/5' : ''">
                  <td class="px-4 py-2.5">
                    <a :href="`/public?ward_id=${ws.ward_id}`" class="flex items-center gap-2.5 group">
                      <span class="w-7 h-7 rounded flex items-center justify-center text-[12px] font-bold shrink-0 transition"
                        :class="props.selectedWardId == ws.ward_id
                          ? 'bg-[#206bc4] text-white'
                          : 'bg-gray-100 text-[#626976] group-hover:bg-[#206bc4]/10 group-hover:text-[#206bc4]'">
                        {{ ws.ward_number }}
                      </span>
                      <span class="text-[13px] text-[#1d273b] group-hover:text-[#206bc4] transition">
                        {{ t('common.ward') }} {{ ws.ward_number }}
                      </span>
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
                </tr>
              </tbody>
              <tfoot class="bg-gray-50 border-t border-[#e6e7e9]">
                <tr>
                  <td class="px-4 py-2.5 text-[13px] font-semibold text-[#1d273b]">{{ t('dashboard.total') }}</td>
                  <td class="px-4 py-2.5 text-right text-[13px] font-semibold text-[#1d273b] tabular-nums">{{ fmt(sum('households')) }}</td>
                  <td class="px-4 py-2.5 text-right text-[13px] font-semibold text-blue-600 tabular-nums">{{ fmt(sum('male')) }}</td>
                  <td class="px-4 py-2.5 text-right text-[13px] font-semibold text-pink-500 tabular-nums">{{ fmt(sum('female')) }}</td>
                  <td class="px-4 py-2.5 text-right text-[13px] font-semibold text-[#1d273b] tabular-nums">{{ fmt(sum('total')) }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </section>

      <!-- ══ ETHNICITY ══ -->
      <section v-if="hasEthnicity" id="ethnicity" class="scroll-mt-[100px] mb-8">
        <DemographicSection
          :title="t('dashboard.ethnicityTitle')"
          :subtitle="t('dashboard.ethnicitySubtitle')"
          icon="fa-solid fa-people-group"
          icon-bg="bg-amber-100"
          icon-color="text-amber-600"
          :data="props.ethnicityData"
          chart-type="bar"
          :colors="AMBER"
          accent="text-amber-600"
          bar-bg="bg-amber-400"
        />
      </section>

      <!-- ══ RELIGION ══ -->
      <section v-if="hasReligion" id="religion" class="scroll-mt-[100px] mb-8">
        <DemographicSection
          :title="t('dashboard.religionTitle')"
          :subtitle="t('dashboard.religionSubtitle')"
          icon="fa-solid fa-place-of-worship"
          icon-bg="bg-purple-100"
          icon-color="text-purple-600"
          :data="props.religionData"
          chart-type="doughnut"
          :colors="PURPLE"
          accent="text-purple-600"
          bar-bg="bg-purple-400"
        />
      </section>

      <!-- ══ MOTHER TONGUE ══ -->
      <section v-if="hasMotherTongue" id="mother-tongue" class="scroll-mt-[100px] mb-8">
        <DemographicSection
          :title="t('dashboard.motherTongueTitle')"
          :subtitle="t('dashboard.motherTongueSubtitle')"
          icon="fa-solid fa-language"
          icon-bg="bg-teal-100"
          icon-color="text-teal-600"
          :data="props.motherTongueData"
          chart-type="bar"
          :colors="TEAL"
          accent="text-teal-600"
          bar-bg="bg-teal-400"
        />
      </section>

      <!-- ══ PROFILE CTA ══ -->
      <div v-if="props.profileFilledCount > 0"
        class="relative overflow-hidden bg-[#206bc4] rounded-md p-4 flex items-center justify-between gap-4 mb-4">
        <div class="text-white min-w-0">
          <p class="text-[14px] font-semibold leading-tight">{{ t('dashboard.profileCTA') }}</p>
          <p class="text-blue-200 text-[12px] mt-0.5">
            {{ t('dashboard.profileCTADesc') }} —
            <span class="font-semibold text-white">{{ props.profileFilledCount }} {{ t('dashboard.sectionsAvailable') }}</span>
          </p>
        </div>
        <a href="/public/profile"
          class="shrink-0 flex items-center gap-1.5 h-8 px-4 bg-white text-[#206bc4] rounded text-[13px] font-semibold hover:bg-blue-50 transition whitespace-nowrap">
          {{ t('dashboard.viewProfile') }}
          <i class="fa-solid fa-arrow-right text-[11px]"></i>
        </a>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import Chart from 'chart.js/auto'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import PublicLayout from '@/layouts/PublicLayout.vue'
import { useLocale } from '@/i18n/index.js'
import { useSidebar } from '@/composables/useSidebar.js'

import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon   from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({ iconRetinaUrl: markerIcon2x, iconUrl: markerIcon, shadowUrl: markerShadow })

defineOptions({ layout: PublicLayout })

const { locale, t } = useLocale()
const { clearSections } = useSidebar()

const props = defineProps({
  lg:                   { type: Object,  default: null },
  wards:                { type: Array,   default: () => [] },
  settlements:          { type: Array,   default: () => [] },
  stats:                { type: Object,  default: () => ({}) },
  genderData:           { type: Object,  default: () => ({}) },
  ageData:              { type: Object,  default: () => ({}) },
  ethnicityData:        { type: Object,  default: () => ({}) },
  religionData:         { type: Object,  default: () => ({}) },
  motherTongueData:     { type: Object,  default: () => ({}) },
  institutionData:      { type: Object,  default: () => ({}) },
  wardSummary:          { type: Array,   default: () => [] },
  selectedWardId:       { type: [Number, null], default: null },
  selectedSettlementId: { type: [Number, null], default: null },
  profileFilledCount:   { type: Number,  default: 0 },
  welcomeConfig:        { type: Object,  default: () => ({}) },
})

// ── Welcome banner dismiss (session-only, resets on every page load) ─
const bannerDismissed = ref(false)
function dismissBanner() {
  bannerDismissed.value = true
}
const showBanner = computed(() =>
  !bannerDismissed.value &&
  props.welcomeConfig?.show_banner !== false &&
  (props.welcomeConfig?.message || props.welcomeConfig?.chairperson?.name)
)

// ── Colour palettes ──────────────────────────────────────────────────
const AMBER  = ['#F59E0B','#FBBF24','#FCD34D','#F97316','#FB923C','#FDE68A','#FDBA74','#FED7AA','#EF4444','#F87171','#FCA5A5','#FEE2E2']
const PURPLE = ['#7C3AED','#8B5CF6','#A78BFA','#C4B5FD','#6D28D9','#DDD6FE','#4C1D95','#EDE9FE']
const TEAL   = ['#0D9488','#14B8A6','#2DD4BF','#5EEAD4','#0F766E','#99F6E4','#134E4A','#CCFBF1']

// ── Helpers ──────────────────────────────────────────────────────────
const fmt      = n => Number(n ?? 0).toLocaleString()
const malePct  = ws => ws.total > 0 ? Math.round(ws.male / ws.total * 100) : 50
const sum      = key => props.wardSummary.reduce((s, w) => s + (w[key] ?? 0), 0)

const hasInstitutions = computed(() => Object.keys(props.institutionData).length > 0)
const hasEthnicity    = computed(() => Object.keys(props.ethnicityData).length > 0)
const hasReligion     = computed(() => Object.keys(props.religionData).length > 0)
const hasMotherTongue = computed(() => Object.keys(props.motherTongueData).length > 0)

onMounted(() => {
  initAllCharts()
})

onUnmounted(() => {
  clearSections()
  genderChart?.destroy()
  ageChart?.destroy()
  institutionChart?.destroy()
  leafletMap?.remove()
})

// ── Filter state ─────────────────────────────────────────────────────
const selWard       = ref(props.selectedWardId ?? '')
const selSettlement = ref(props.selectedSettlementId ?? '')
const isFiltered    = computed(() => !!selWard.value || !!selSettlement.value)

watch(() => props.selectedWardId,       v => { selWard.value       = v ?? '' })
watch(() => props.selectedSettlementId, v => { selSettlement.value = v ?? '' })

function onWardChange() {
  selSettlement.value = ''
  applyFilter()
}
function applyFilter() {
  const p = {}
  if (selWard.value)       p.ward_id       = selWard.value
  if (selSettlement.value) p.settlement_id = selSettlement.value
  router.get('/public', p, { preserveState: true, replace: true })
}
function clearFilter() {
  selWard.value = ''; selSettlement.value = ''
  router.get('/public', {}, { preserveState: true, replace: true })
}

// ── Stat cards ───────────────────────────────────────────────────────
const statCards = computed(() => [
  { key: 'households',   value: props.stats.total_households ?? 0,   label: t('stats.households'),   icon: 'fa-solid fa-house',    iconClass: 'bg-amber-100 text-amber-600',    valClass: '' },
  { key: 'members',      value: props.stats.total_members ?? 0,      label: t('stats.population'),   icon: 'fa-solid fa-users',    iconClass: 'bg-indigo-100 text-indigo-600',  valClass: '' },
  { key: 'male',         value: props.genderData?.male ?? 0,         label: t('stats.male'),         icon: 'fa-solid fa-mars',     iconClass: 'bg-blue-100 text-blue-700',      valClass: 'text-blue-700' },
  { key: 'female',       value: props.genderData?.female ?? 0,       label: t('stats.female'),       icon: 'fa-solid fa-venus',    iconClass: 'bg-pink-100 text-pink-600',      valClass: 'text-pink-600' },
  { key: 'institutions', value: props.stats.total_institutions ?? 0, label: t('stats.institutions'), icon: 'fa-solid fa-building', iconClass: 'bg-violet-100 text-violet-600',  valClass: '' },
  { key: 'wards',        value: props.stats.total_wards ?? 0,        label: t('stats.wards'),        icon: 'fa-solid fa-map',      iconClass: 'bg-emerald-100 text-emerald-600', valClass: '' },
])

// ── Map ──────────────────────────────────────────────────────────────
const mapEl = ref(null)
let leafletMap = null

function initMap() {
  if (!mapEl.value || !props.lg) return
  leafletMap?.remove()
  const cfg  = props.lg.map_config ?? {}
  const lat  = cfg.center_lat ?? 27.7
  const lng  = cfg.center_lng ?? 85.3
  leafletMap = L.map(mapEl.value, { scrollWheelZoom: false }).setView([lat, lng], cfg.zoom ?? 13)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(leafletMap)

  const colors = { office: '#1D4ED8', school: '#7C3AED', health: '#059669', ward: '#D97706', tourism: '#DB2777', other: '#6B7280' }
  const makeIcon = type => {
    const c = colors[type] ?? '#6B7280'
    return L.divIcon({
      html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 36" width="24" height="36"><path fill="${c}" stroke="#fff" stroke-width="1.5" d="M12 0C5.4 0 0 5.4 0 12c0 9 12 24 12 24S24 21 24 12C24 5.4 18.6 0 12 0z"/><circle fill="#fff" cx="12" cy="12" r="5"/></svg>`,
      className: '', iconSize: [24, 36], iconAnchor: [12, 36], popupAnchor: [0, -36],
    })
  }

  const markers = cfg.markers ?? []
  if (!markers.length) {
    L.marker([lat, lng], { icon: makeIcon('office') }).addTo(leafletMap)
      .bindPopup(`<b>${props.lg.name_en}</b>`)
  } else {
    markers.forEach(m => L.marker([m.lat, m.lng], { icon: makeIcon(m.type ?? 'other') })
      .addTo(leafletMap).bindPopup(`<b>${m.name}</b>${m.popup ? `<br><small>${m.popup}</small>` : ''}`))
  }
  leafletMap.on('click', () => leafletMap.scrollWheelZoom.enable())
  leafletMap.on('mouseout', () => leafletMap.scrollWheelZoom.disable())
}

// ── Charts ───────────────────────────────────────────────────────────
const genderChartEl      = ref(null)
const ageChartEl         = ref(null)
const institutionChartEl = ref(null)
let genderChart = null, ageChart = null, institutionChart = null

const chartOpts = (extra = {}) => ({
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  ...extra,
})

function initGenderChart() {
  if (!genderChartEl.value || !Object.keys(props.genderData).length) return
  genderChart?.destroy()
  genderChart = new Chart(genderChartEl.value, {
    type: 'doughnut',
    data: {
      labels: Object.keys(props.genderData).map(k => ({ male: 'पुरुष (Male)', female: 'महिला (Female)', other: 'अन्य (Other)' }[k] ?? k)),
      datasets: [{ data: Object.values(props.genderData), backgroundColor: ['#3B82F6', '#EC4899', '#6B7280'], hoverOffset: 4 }],
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } },
  })
}

function initAgeChart() {
  if (!ageChartEl.value || !Object.keys(props.ageData).length) return
  ageChart?.destroy()
  ageChart = new Chart(ageChartEl.value, {
    type: 'bar',
    data: {
      labels: Object.keys(props.ageData),
      datasets: [{ data: Object.values(props.ageData), backgroundColor: '#6366F1', borderRadius: 5, borderSkipped: false }],
    },
    options: chartOpts({ scales: { y: { beginAtZero: true, grid: { color: '#f3f4f6' } } } }),
  })
}

function initInstitutionChart() {
  if (!institutionChartEl.value || !Object.keys(props.institutionData).length) return
  institutionChart?.destroy()
  institutionChart = new Chart(institutionChartEl.value, {
    type: 'bar',
    data: {
      labels: Object.keys(props.institutionData),
      datasets: [{ data: Object.values(props.institutionData), backgroundColor: '#10B981', borderRadius: 5, borderSkipped: false }],
    },
    options: chartOpts({ indexAxis: 'y', scales: { x: { beginAtZero: true, grid: { color: '#f3f4f6' } } } }),
  })
}

function initAllCharts() {
  initGenderChart(); initAgeChart(); initInstitutionChart(); initMap()
}

watch(() => [props.genderData, props.ageData, props.institutionData], initAllCharts, { deep: true })
</script>

<!-- ─────────────────────────────────────────────────────────────────
     DemographicSection — render-function sub-component
───────────────────────────────────────────────────────────────────── -->
<script>
import { ref, onMounted, onUnmounted, watch, h } from 'vue'
import Chart from 'chart.js/auto'

export const DemographicSection = {
  props: {
    title:     String,
    subtitle:  String,
    icon:      String,
    iconBg:    String,
    iconColor: String,
    data:      { type: Object, default: () => ({}) },
    chartType: { type: String, default: 'bar' },
    colors:    { type: Array,  default: () => [] },
    accent:    String,
    barBg:     String,
  },
  setup(props) {
    const canvasEl = ref(null)
    let chart = null

    function init() {
      if (!canvasEl.value || !Object.keys(props.data).length) return
      chart?.destroy()
      const isDough = props.chartType === 'doughnut'
      const labels  = Object.keys(props.data).map(l => l.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' '))

      chart = new Chart(canvasEl.value, {
        type: isDough ? 'doughnut' : 'bar',
        data: {
          labels,
          datasets: [{
            label: 'Population',
            data: Object.values(props.data),
            backgroundColor: props.colors,
            borderRadius: isDough ? 0 : 4,
            borderSkipped: false,
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
      const entries  = Object.entries(props.data)
      const total    = entries.reduce((s, [, v]) => s + v, 0)
      const isDough  = props.chartType === 'doughnut'
      const chartH   = isDough ? 'height:300px' : `height:${Math.min(48 + entries.length * 30, 420)}px`

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
          h('span', { class: 'text-[11px] text-[#9aa0ac] bg-gray-100 px-2 py-1 rounded' }, `${entries.length} groups`),
        ]),
        // Body: chart + table
        h('div', { class: 'grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-[#e6e7e9]' }, [
          // Chart
          h('div', { class: isDough ? 'p-8 flex items-center justify-center' : 'p-5' }, [
            h('div', { style: `${chartH}; width:100%${isDough ? '; max-width:320px' : ''}` }, [
              h('canvas', { ref: canvasEl }),
            ]),
          ]),
          // Table
          h('div', { class: 'overflow-x-auto' }, [
            h('table', { class: 'w-full' }, [
              h('thead', { class: 'border-b border-[#e6e7e9]' }, [
                h('tr', {}, [
                  h('th', { class: 'px-4 py-2.5 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em] w-7' }, '#'),
                  h('th', { class: 'px-4 py-2.5 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]' }, 'नाम'),
                  h('th', { class: 'px-4 py-2.5 text-right text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]' }, 'जनसंख्या'),
                  h('th', { class: 'px-4 py-2.5 text-right text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]' }, '%'),
                ]),
              ]),
              h('tbody', { class: 'divide-y divide-[#e6e7e9]' },
                entries.map(([label, count], i) => {
                  const pct  = total > 0 ? (count / total * 100).toFixed(1) : '0.0'
                  const disp = label.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')
                  return h('tr', { key: label, class: 'hover:bg-gray-50 transition-colors' }, [
                    h('td', { class: 'px-4 py-2.5 text-[#9aa0ac] text-[13px] tabular-nums' }, i + 1),
                    h('td', { class: 'px-4 py-2.5' }, [
                      h('div', { class: 'text-[13px] font-medium text-[#1d273b]' }, disp),
                      h('div', { class: 'mt-1.5 h-1.5 rounded-full bg-gray-100 overflow-hidden w-full' }, [
                        h('div', { class: `h-full rounded-full ${props.barBg}`, style: `width:${pct}%` }),
                      ]),
                    ]),
                    h('td', { class: 'px-4 py-2.5 text-right text-[13px] font-semibold text-[#1d273b] tabular-nums' }, Number(count).toLocaleString()),
                    h('td', { class: `px-4 py-2.5 text-right text-[13px] font-semibold tabular-nums ${props.accent}` }, `${pct}%`),
                  ])
                })
              ),
              h('tfoot', { class: 'bg-gray-50 border-t border-[#e6e7e9]' }, [
                h('tr', {}, [
                  h('td', { class: 'px-4 py-2.5 text-[13px] font-semibold text-[#1d273b]', colspan: 2 }, 'जम्मा / Total'),
                  h('td', { class: 'px-4 py-2.5 text-right text-[13px] font-semibold text-[#1d273b] tabular-nums' }, total.toLocaleString()),
                  h('td', { class: 'px-4 py-2.5 text-right text-[13px] font-semibold text-[#9aa0ac]' }, '100%'),
                ]),
              ]),
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

.banner-slide-enter-active,
.banner-slide-leave-active { transition: all 0.35s ease; overflow: hidden; }
.banner-slide-enter-from,
.banner-slide-leave-to    { opacity: 0; max-height: 0; margin-bottom: 0; }
.banner-slide-enter-to,
.banner-slide-leave-from  { opacity: 1; max-height: 800px; }
</style>
