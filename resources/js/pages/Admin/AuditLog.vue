<template>
  <div class="space-y-4">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-[15px] font-semibold text-[#1d273b]">अडिट लग</h1>
        <p class="text-[11px] text-[#9aa0ac] mt-0.5">Audit Log — who changed what</p>
      </div>
      <span class="text-[12px] text-[#9aa0ac]">{{ logs.total }} प्रविष्टि</span>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-md border border-[#e6e7e9] px-5 py-4">
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <!-- User -->
        <div class="flex flex-col gap-1.5">
          <label class="text-[11px] font-semibold text-[#626976] uppercase tracking-wide">प्रयोगकर्ता</label>
          <select v-model="f.user_id" @change="applyFilters"
            style="color: #1d273b"
            class="h-9 rounded border border-[#e6e7e9] text-[13px] px-3 bg-white w-full">
            <option value="">— सबै —</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <!-- Event type -->
        <div class="flex flex-col gap-1.5">
          <label class="text-[11px] font-semibold text-[#626976] uppercase tracking-wide">घटना प्रकार</label>
          <select v-model="f.event_type" @change="applyFilters"
            style="color: #1d273b"
            class="h-9 rounded border border-[#e6e7e9] text-[13px] px-3 bg-white w-full">
            <option value="">— सबै —</option>
            <option v-for="e in eventTypes" :key="e.value" :value="e.value">{{ e.label }}</option>
          </select>
        </div>
        <!-- Record type -->
        <div class="flex flex-col gap-1.5">
          <label class="text-[11px] font-semibold text-[#626976] uppercase tracking-wide">अभिलेख प्रकार</label>
          <select v-model="f.record_type" @change="applyFilters"
            style="color: #1d273b"
            class="h-9 rounded border border-[#e6e7e9] text-[13px] px-3 bg-white w-full">
            <option value="">— सबै —</option>
            <option v-for="(label, type) in typeLabels" :key="type" :value="type">{{ label }}</option>
          </select>
        </div>
        <!-- Date from -->
        <div class="flex flex-col gap-1.5">
          <label class="text-[11px] font-semibold text-[#626976] uppercase tracking-wide">मिति देखि</label>
          <input type="date" v-model="f.date_from" @change="applyFilters"
            style="color: #1d273b"
            class="h-9 rounded border border-[#e6e7e9] text-[13px] px-3 bg-white w-full" />
        </div>
        <!-- Date to -->
        <div class="flex flex-col gap-1.5">
          <label class="text-[11px] font-semibold text-[#626976] uppercase tracking-wide">मिति सम्म</label>
          <input type="date" v-model="f.date_to" @change="applyFilters"
            style="color: #1d273b"
            class="h-9 rounded border border-[#e6e7e9] text-[13px] px-3 bg-white w-full" />
        </div>
      </div>
      <!-- Active filter chips + clear -->
      <div v-if="hasFilters" class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-[#f0f1f3]">
        <span class="text-[11px] text-[#9aa0ac]">फिल्टर:</span>
        <span v-if="f.user_id"
          class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#206bc4]/10 text-[#206bc4] text-[11px] font-medium">
          <i class="fa-solid fa-user text-[9px]"></i>
          {{ users.find(u => u.id == f.user_id)?.name ?? f.user_id }}
        </span>
        <span v-if="f.event_type"
          class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-purple-100 text-purple-700 text-[11px] font-medium">
          <i class="fa-solid fa-bolt text-[9px]"></i>
          {{ eventTypes.find(e => e.value === f.event_type)?.label ?? f.event_type }}
        </span>
        <span v-if="f.record_type"
          class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-teal-100 text-teal-700 text-[11px] font-medium">
          <i class="fa-solid fa-table text-[9px]"></i>
          {{ typeLabels[f.record_type] ?? f.record_type }}
        </span>
        <span v-if="f.date_from || f.date_to"
          class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 text-[11px] font-medium">
          <i class="fa-solid fa-calendar text-[9px]"></i>
          {{ f.date_from || '…' }} — {{ f.date_to || '…' }}
        </span>
        <button @click="clearFilters"
          class="ml-auto inline-flex items-center gap-1.5 px-3 py-1 rounded-full border border-[#e6e7e9] text-[11px] text-[#9aa0ac] hover:text-red-500 hover:border-red-200 transition-colors">
          <i class="fa-solid fa-xmark"></i> सबै हटाउनुहोस्
        </button>
      </div>
    </div>

    <!-- Log table -->
    <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead class="bg-gray-50 border-b border-[#e6e7e9]">
            <tr>
              <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide w-40">मिति / समय</th>
              <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide">प्रयोगकर्ता</th>
              <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide">घटना</th>
              <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide">अभिलेख</th>
              <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide">परिवर्तन</th>
              <th class="px-4 py-3 text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-wide w-20"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#f0f1f3]">
            <template v-for="log in logs.data" :key="log.id">
              <!-- Main row -->
              <tr class="hover:bg-gray-50 transition-colors" :class="expanded === log.id ? 'bg-blue-50/30' : ''">
                <!-- Date -->
                <td class="px-4 py-3">
                  <p class="text-[12px] font-medium text-[#1d273b]">{{ log.created_at?.slice(0,10) }}</p>
                  <p class="text-[11px] text-[#9aa0ac]">{{ log.created_at?.slice(11,16) }}</p>
                </td>
                <!-- User -->
                <td class="px-4 py-3">
                  <div v-if="log.user" class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded bg-amber-100 text-amber-700 flex items-center justify-center text-[10px] font-bold shrink-0">
                      {{ initials(log.user.name) }}
                    </div>
                    <div>
                      <p class="text-[13px] font-semibold text-[#1d273b] leading-tight">{{ log.user.name }}</p>
                      <p class="text-[10px] text-[#9aa0ac] capitalize">{{ log.user.role?.replace(/_/g,' ') }}</p>
                    </div>
                  </div>
                  <span v-else class="text-[12px] text-[#9aa0ac]">System</span>
                </td>
                <!-- Event -->
                <td class="px-4 py-3">
                  <span :class="eventBadge(log.event_type)">
                    <i :class="eventIcon(log.event_type)" class="text-[9px]"></i>
                    {{ log.event_label }}
                  </span>
                </td>
                <!-- Record -->
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1.5">
                    <span :class="typeBadge(log.auditable_type)" class="text-[10px] font-semibold px-1.5 py-0.5 rounded">
                      {{ log.type_label }}
                    </span>
                    <span v-if="log.unique_code" class="text-[12px] font-mono text-[#626976]">{{ log.unique_code }}</span>
                  </div>
                </td>
                <!-- Change count -->
                <td class="px-4 py-3">
                  <span v-if="diffCount(log) > 0"
                    class="text-[12px] text-[#206bc4] font-semibold">
                    {{ diffCount(log) }} फिल्ड
                  </span>
                  <span v-else class="text-[12px] text-[#9aa0ac]">—</span>
                </td>
                <!-- Expand toggle -->
                <td class="px-4 py-3 text-right">
                  <button v-if="diffCount(log) > 0 || log.new_values"
                    @click="expanded = expanded === log.id ? null : log.id"
                    :class="[
                      'w-7 h-7 rounded flex items-center justify-center text-[11px] transition-colors',
                      expanded === log.id
                        ? 'bg-[#206bc4] text-white'
                        : 'border border-[#e6e7e9] text-[#9aa0ac] hover:bg-gray-100'
                    ]">
                    <i :class="expanded === log.id ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'"></i>
                  </button>
                </td>
              </tr>

              <!-- Expanded diff row -->
              <tr v-if="expanded === log.id" :key="'diff-' + log.id">
                <td colspan="6" class="px-4 pb-4 pt-1 bg-blue-50/20">
                  <div class="rounded-md border border-[#e6e7e9] overflow-hidden">
                    <!-- Diff header -->
                    <div class="flex items-center gap-3 px-4 py-2.5 bg-gray-50 border-b border-[#e6e7e9]">
                      <i class="fa-solid fa-code-compare text-[#206bc4] text-[11px]"></i>
                      <span class="text-[12px] font-semibold text-[#1d273b]">परिवर्तन विवरण</span>
                      <span class="text-[11px] text-[#9aa0ac] ml-auto">{{ log.ip_address }}</span>
                    </div>

                    <!-- No diff (new record or no old values) -->
                    <div v-if="!log.old_values && log.new_values"
                      class="px-4 py-3 text-[12px] text-[#9aa0ac] italic">
                      नयाँ अभिलेख — पुरानो मान उपलब्ध छैन।
                    </div>

                    <!-- Diff table -->
                    <table v-else-if="computedDiff(log).length > 0" class="w-full text-left">
                      <thead class="bg-gray-50/80 border-b border-[#e6e7e9]">
                        <tr>
                          <th class="px-4 py-2 text-[10px] font-semibold text-[#9aa0ac] uppercase tracking-wide w-48">फिल्ड</th>
                          <th class="px-4 py-2 text-[10px] font-semibold text-red-400 uppercase tracking-wide w-1/2">पुरानो मान</th>
                          <th class="px-4 py-2 text-[10px] font-semibold text-green-600 uppercase tracking-wide">नयाँ मान</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-[#f0f1f3]">
                        <tr v-for="change in computedDiff(log)" :key="change.key"
                          class="hover:bg-white transition-colors">
                          <!-- Field name -->
                          <td class="px-4 py-2">
                            <span class="text-[12px] font-medium text-[#626976] font-mono">{{ fieldLabel(change.key) }}</span>
                          </td>
                          <!-- Old value -->
                          <td class="px-4 py-2">
                            <div class="bg-red-50 border border-red-100 rounded px-2 py-1 text-[12px] text-red-700 break-all max-w-xs">
                              {{ display(change.old) }}
                            </div>
                          </td>
                          <!-- New value -->
                          <td class="px-4 py-2">
                            <div class="bg-green-50 border border-green-100 rounded px-2 py-1 text-[12px] text-green-700 break-all max-w-xs">
                              {{ display(change.new) }}
                            </div>
                          </td>
                        </tr>
                      </tbody>
                    </table>

                    <div v-else class="px-4 py-3 text-[12px] text-[#9aa0ac] italic">
                      कुनै क्षेत्र परिवर्तन भेटिएन।
                    </div>
                  </div>
                </td>
              </tr>
            </template>

            <!-- Empty state -->
            <tr v-if="!logs.data?.length">
              <td colspan="6" class="px-5 py-12 text-center">
                <i class="fa-solid fa-clipboard-list text-3xl text-gray-200 block mb-2"></i>
                <p class="text-[13px] text-[#9aa0ac]">कुनै अडिट लग फेला परेन।</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="logs.last_page > 1"
        class="flex items-center justify-between px-4 py-3 border-t border-[#e6e7e9] bg-gray-50">
        <p class="text-[12px] text-[#9aa0ac]">
          {{ logs.from }}–{{ logs.to }} / {{ logs.total }}
        </p>
        <div class="flex gap-1">
          <button v-for="link in logs.links" :key="link.label"
            @click="goToPage(link.url)"
            :disabled="!link.url"
            v-html="link.label"
            :class="[
              'h-8 min-w-[32px] px-2 rounded text-[12px] transition-colors',
              link.active
                ? 'bg-[#206bc4] text-white font-semibold'
                : link.url
                  ? 'border border-[#e6e7e9] text-[#626976] hover:bg-gray-100'
                  : 'text-[#ccc] cursor-default',
            ]"
          ></button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  logs:        { type: Object, default: () => ({ data: [], total: 0 }) },
  filters:     { type: Object, default: () => ({}) },
  users:       { type: Array,  default: () => [] },
  eventTypes:  { type: Array,  default: () => [] },
  typeLabels:  { type: Object, default: () => ({}) },
})

const expanded = ref(null)

// ── Filters ───────────────────────────────────────────────────────────────────
const f = ref({
  user_id:     props.filters.user_id     ?? '',
  event_type:  props.filters.event_type  ?? '',
  record_type: props.filters.record_type ?? '',
  date_from:   props.filters.date_from   ?? '',
  date_to:     props.filters.date_to     ?? '',
})

const hasFilters = computed(() => Object.values(f.value).some(Boolean))

function applyFilters() {
  const params = {}
  Object.entries(f.value).forEach(([k, v]) => { if (v) params[k] = v })
  router.get('/admin/audit-logs', params, { preserveState: true, replace: true })
}

function clearFilters() {
  f.value = { user_id: '', event_type: '', record_type: '', date_from: '', date_to: '' }
  router.get('/admin/audit-logs', {}, { preserveState: false })
}

function goToPage(url) {
  if (!url) return
  const params = {}
  const u = new URL(url)
  u.searchParams.forEach((v, k) => { params[k] = v })
  router.get('/admin/audit-logs', params, { preserveState: true })
}

// ── Diff logic ─────────────────────────────────────────────────────────────────

// Fields to skip in the diff — system/workflow fields that aren't meaningful changes
const SKIP = new Set([
  'id', 'created_at', 'updated_at', 'deleted_at',
  'submitted_at', 'ward_reviewed_at', 'ward_reviewer_id', 'ward_review_comment',
  'published_at', 'published_by', 'data_frozen_at',
  'enumerator_id', 'ward_id', 'settlement_id',
  'user_agent',
  // survey_data is handled separately — expanded into sub-fields below
])

// Expand survey_data JSON into flat pseudo-keys for diffing.
// Object sections → survey_data.{section}.{field}
// Scalar / array sections → survey_data.{section}
function flattenSurveyData(sd) {
  const result = {}
  if (!sd || typeof sd !== 'object') return result
  for (const [section, val] of Object.entries(sd)) {
    if (val === null || val === undefined) continue
    if (!Array.isArray(val) && typeof val === 'object') {
      for (const [field, fVal] of Object.entries(val)) {
        result[`survey_data.${section}.${field}`] = fVal
      }
    } else {
      result[`survey_data.${section}`] = val
    }
  }
  return result
}

function computedDiff(log) {
  const oldV = log.old_values ?? {}
  const newV = log.new_values ?? {}
  const keys = new Set([...Object.keys(oldV), ...Object.keys(newV)])
  const changes = []

  for (const k of keys) {
    if (SKIP.has(k)) continue

    if (k === 'survey_data') {
      const oldFlat = flattenSurveyData(oldV[k])
      const newFlat = flattenSurveyData(newV[k])
      const sdKeys = new Set([...Object.keys(oldFlat), ...Object.keys(newFlat)])
      for (const sk of sdKeys) {
        const o = oldFlat[sk]
        const n = newFlat[sk]
        if (JSON.stringify(o) !== JSON.stringify(n)) {
          changes.push({ key: sk, old: o, new: n })
        }
      }
      continue
    }

    const o = oldV[k]
    const n = newV[k]
    if (JSON.stringify(o) !== JSON.stringify(n)) {
      changes.push({ key: k, old: o, new: n })
    }
  }
  return changes
}

function diffCount(log) {
  return computedDiff(log).length
}

// ── Field labels (Nepali) ─────────────────────────────────────────────────────
const FIELD_LABELS = {
  unique_code: 'कोड', respondent_name: 'घरमूलीको नाम', respondent_contact: 'सम्पर्क',
  total_members: 'कुल सदस्य', gps_lat: 'GPS Lat', gps_lng: 'GPS Lng',
  gps_altitude: 'GPS उचाइ', gps_accuracy: 'GPS सटीकता',
  photo_number: 'फोटो नम्बर', status: 'स्थिति', survey_date: 'सर्वेक्षण मिति',
  remarks: 'कैफियत',
  name_np: 'नाम (नेपाली)', name_en: 'नाम (अंग्रेजी)',
  registration_number: 'दर्ता नं.', established_date: 'स्थापना मिति',
  ownership_type: 'स्वामित्व', institution_type_id: 'संस्था प्रकार',
  business_name: 'व्यवसायको नाम', business_type: 'व्यवसाय प्रकार',
  business_field: 'व्यवसाय क्षेत्र', business_location: 'व्यवसाय स्थान',
  is_registered: 'दर्ता भएको', has_pan_vat: 'PAN/VAT', contact_number: 'सम्पर्क',
  tax_amount: 'कर रकम', pan_vat_number: 'PAN/VAT नम्बर',
}

// Labels for flattened survey_data pseudo-fields
const SURVEY_DATA_LABELS = {
  // ── location (step 1)
  'survey_data.location.house_type':        'घरको बनावट',
  'survey_data.location.house_purpose':     'घरको उद्देश्य',
  'survey_data.location.roof_type':         'छानोको प्रकार',
  'survey_data.location.wall_type':         'भित्ताको प्रकार',
  'survey_data.location.foundation_type':   'जगको प्रकार',
  'survey_data.location.ownership_type':    'स्वामित्वको किसिम',
  'survey_data.location.house_condition':   'घरको अवस्था',
  'survey_data.location.land_use_type':     'जग्गा उपयोग',
  'survey_data.location.house_land_type':   'जग्गाको किसिम',
  'survey_data.location.location_house_built': 'घर बनेको ठाउँ',
  // ── social (step 2)
  'survey_data.social.ethnicity':           'जाति/समुदाय',
  'survey_data.social.religion':            'धर्म',
  'survey_data.social.language':            'मातृभाषा',
  'survey_data.social.family_type':         'परिवारको किसिम',
  'survey_data.social.major_occupation':    'मुख्य पेशा',
  'survey_data.social.settlement_type':     'बसोबासको प्रकार',
  'survey_data.social.migration_purpose':   'बसाइसराइको उद्देश्य',
  // ── wellbeing (step 3)
  'survey_data.wellbeing.water_source':         'खानेपानी स्रोत',
  'survey_data.wellbeing.toilet_type':          'शौचालयको किसिम',
  'survey_data.wellbeing.sanitation_type':      'सरसफाइको प्रकार',
  'survey_data.wellbeing.handwashing':          'हात धुने सुविधा',
  'survey_data.wellbeing.electricity_source':   'विद्युतको स्रोत',
  'survey_data.wellbeing.alt_electricity_source': 'बैकल्पिक विद्युत',
  'survey_data.wellbeing.cooking_fuel':         'खाना पकाउने इन्धन',
  'survey_data.wellbeing.cooking_stove':        'चुलोको प्रकार',
  'survey_data.wellbeing.firewood_source':      'दाउराको स्रोत',
  'survey_data.wellbeing.waste_management':     'फोहोर व्यवस्थापन',
  'survey_data.wellbeing.internet_type':        'इन्टरनेट',
  'survey_data.wellbeing.facilities':           'सुविधाहरू',
  // ── health (step 3)
  'survey_data.health.nutrition_diet':      'पोषण तथा आहार',
  'survey_data.health.treatment_place':     'उपचार स्थान',
  // ── decisions (step 4)
  'survey_data.decisions.vehicle_type':       'सवारी साधन',
  'survey_data.decisions.decision_owner':     'निर्णय स्वामित्व',
  'survey_data.decisions.residence_perception': 'बसोबास दृष्टिकोण',
  'survey_data.decisions.family_gathering_freq': 'परिवार भेटघाट',
  'survey_data.decisions.happiness_activities': 'खुसीका क्रियाकलाप',
  'survey_data.decisions.neighbor_cooperation': 'छिमेकी सहकार्य',
  'survey_data.decisions.loan_source':        'ऋणको स्रोत',
  'survey_data.decisions.loan_purpose':       'ऋणको उद्देश्य',
  'survey_data.decisions.org_type':           'संस्थाको प्रकार',
  'survey_data.decisions.income_source':      'आम्दानीको स्रोत',
  'survey_data.decisions.expense_category':   'खर्च शिर्षक',
  // ── occupation (step 5)
  'survey_data.occupation.technical_skill':   'प्राविधिक सीप',
  'survey_data.occupation.occupation_sector': 'पेशागत क्षेत्र',
  // ── land
  'survey_data.hasLandInMunicipality': 'पालिकाभित्र जग्गा',
  'survey_data.landParcels':           'जग्गाको विवरण',
  // ── agriculture (step 8–9)
  'survey_data.cropProduction':        'कृषि उत्पादन',
  // ── livestock (step 10)
  'survey_data.livestockDetail':       'पशुपालन विवरण',
  // ── bee / fish / silk (step 11)
  'survey_data.beeFishSilk':           'मौरी/माछा/रेशम',
}

function fieldLabel(key) {
  return SURVEY_DATA_LABELS[key] ?? FIELD_LABELS[key] ?? key
}

// ── Value display ─────────────────────────────────────────────────────────────
function display(val) {
  if (val === null || val === undefined) return '(खाली)'
  if (typeof val === 'boolean') return val ? 'छ' : 'छैन'
  if (Array.isArray(val)) {
    if (val.length === 0) return '(खाली सूची)'
    const s = JSON.stringify(val)
    return s.length > 200 ? s.slice(0, 200) + '…' : s
  }
  if (typeof val === 'object') {
    const s = JSON.stringify(val)
    return s.length > 200 ? s.slice(0, 200) + '…' : s
  }
  return String(val)
}

// ── Badge helpers ─────────────────────────────────────────────────────────────
function eventBadge(type) {
  const base = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold'
  return {
    'published_edit': base + ' bg-purple-100 text-purple-700',
    'created':        base + ' bg-green-100 text-green-700',
    'updated':        base + ' bg-amber-100 text-amber-700',
    'deleted':        base + ' bg-red-100 text-red-600',
    'status_change':  base + ' bg-blue-100 text-[#206bc4]',
  }[type] ?? (base + ' bg-gray-100 text-[#626976]')
}

function eventIcon(type) {
  return {
    'published_edit': 'fa-solid fa-pen-nib',
    'created':        'fa-solid fa-plus',
    'updated':        'fa-solid fa-pencil',
    'deleted':        'fa-solid fa-trash',
    'status_change':  'fa-solid fa-arrows-rotate',
  }[type] ?? 'fa-solid fa-circle-dot'
}

function typeBadge(type) {
  return {
    'App\\Models\\Household':      'bg-indigo-100 text-indigo-700',
    'App\\Models\\Institution':    'bg-teal-100 text-teal-700',
    'App\\Models\\MemberBusiness': 'bg-orange-100 text-orange-700',
  }[type] ?? 'bg-gray-100 text-gray-600'
}

function initials(name) {
  return (name ?? '?').split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
}
</script>
