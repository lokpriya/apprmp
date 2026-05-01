<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  wards:               { type: Array,   default: () => [] },
  hasHouseholdSheet:   { type: Boolean, default: false },
  hasInstitutionSheet: { type: Boolean, default: false },
})

// ── Form state ────────────────────────────────────────────────────────────────
const dataType   = ref('households')
const wardId     = ref('')
const forceLocked = ref(false)

// ── Preview state ─────────────────────────────────────────────────────────────
const previewing  = ref(false)
const applying    = ref(false)
const previewDone = ref(false)
const applyDone   = ref(false)
const errorMsg    = ref('')

const counts = ref({ new: 0, changed: 0, locked: 0, upToDate: 0, errors: 0 })
const rows   = ref([])
const errors = ref([])
const applyResult = ref(null)

const hasSheet = computed(() =>
  dataType.value === 'households' ? props.hasHouseholdSheet : props.hasInstitutionSheet
)

const canPreview = computed(() => wardId.value && hasSheet.value && !previewing.value)
const canApply   = computed(() =>
  previewDone.value &&
  (counts.value.new > 0 || counts.value.changed > 0 || (forceLocked.value && counts.value.locked > 0)) &&
  !applying.value
)

function resetState() {
  previewDone.value  = false
  applyDone.value    = false
  errorMsg.value     = ''
  applyResult.value  = null
  rows.value         = []
  errors.value       = []
  counts.value       = { new: 0, changed: 0, locked: 0, upToDate: 0, errors: 0 }
}

async function doPreview() {
  resetState()
  previewing.value = true
  try {
    const res = await axios.post('/admin/google-sync/preview', {
      type:    dataType.value,
      ward_id: wardId.value,
    })
    counts.value     = res.data.counts
    rows.value       = res.data.rows   ?? []
    errors.value     = res.data.errors ?? []
    previewDone.value = true
  } catch (e) {
    errorMsg.value = e.response?.data?.error ?? e.response?.data?.message ?? 'अज्ञात त्रुटि'
  } finally {
    previewing.value = false
  }
}

async function doApply() {
  applying.value = true
  errorMsg.value = ''
  try {
    const res = await axios.post('/admin/google-sync/apply', {
      type:         dataType.value,
      ward_id:      wardId.value,
      force_locked: forceLocked.value,
    })
    applyResult.value = res.data
    applyDone.value   = true
    previewDone.value = false
  } catch (e) {
    errorMsg.value = e.response?.data?.error ?? e.response?.data?.message ?? 'अज्ञात त्रुटि'
  } finally {
    applying.value = false
  }
}

function actionLabel(action) {
  return { new: 'नयाँ', changed: 'परिवर्तित', locked: 'सुरक्षित' }[action] ?? action
}
function actionClass(action) {
  return {
    new:      'bg-green-100 text-green-800',
    changed:  'bg-yellow-100 text-yellow-800',
    locked:   'bg-red-100 text-red-800',
    up_to_date: 'bg-gray-100 text-gray-500',
  }[action] ?? 'bg-gray-100 text-gray-600'
}
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">

    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
        <i class="fa-brands fa-google text-blue-600"></i>
        Google Sheets सिंक
      </h1>
      <p class="text-sm text-gray-500 mt-1">
        Google Sheets बाट घरधुरी वा संस्था डेटा वडा अनुसार प्रिभ्यू गरी सिंक गर्नुहोस्।
      </p>
    </div>

    <!-- No sheet configured warning -->
    <div v-if="!hasHouseholdSheet && !hasInstitutionSheet"
         class="mb-5 rounded-lg border border-amber-300 bg-amber-50 p-4 flex gap-3">
      <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5"></i>
      <div>
        <p class="font-medium text-amber-800">Sheet ID कन्फिगर गरिएको छैन</p>
        <p class="text-sm text-amber-700 mt-0.5">
          <a href="/admin/settings#google_sheets" class="underline">Settings → Google Sheets</a> मा Household र Institution Sheet ID थप्नुहोस्।
        </p>
      </div>
    </div>

    <!-- Controls card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-5">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">

        <!-- Data type -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">डेटा प्रकार</label>
          <div class="flex gap-3">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="dataType" value="households" @change="resetState"
                     class="text-blue-600" />
              <span class="text-sm">
                घरधुरी
                <span v-if="!hasHouseholdSheet" class="text-red-500 text-xs">(ID छैन)</span>
              </span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="dataType" value="institutions" @change="resetState"
                     class="text-blue-600" />
              <span class="text-sm">
                संस्था
                <span v-if="!hasInstitutionSheet" class="text-red-500 text-xs">(ID छैन)</span>
              </span>
            </label>
          </div>
        </div>

        <!-- Ward picker -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">वडा छान्नुहोस्</label>
          <select v-model="wardId" @change="resetState"
                  class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:ring-blue-500 focus:border-blue-500">
            <option value="">-- वडा छान्नुहोस् --</option>
            <option v-for="w in wards" :key="w.id" :value="w.id">
              वडा {{ w.ward_number }}
            </option>
          </select>
        </div>

        <!-- Preview button -->
        <div>
          <button @click="doPreview" :disabled="!canPreview"
                  class="w-full px-4 py-2 rounded-lg text-sm font-medium transition
                         bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
            <i v-if="previewing" class="fa-solid fa-spinner fa-spin"></i>
            <i v-else class="fa-solid fa-magnifying-glass"></i>
            {{ previewing ? 'लोड हुँदैछ…' : 'प्रिभ्यू हेर्नुहोस्' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-if="errorMsg" class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 flex gap-2 text-sm text-red-700">
      <i class="fa-solid fa-circle-xmark mt-0.5"></i>
      <span>{{ errorMsg }}</span>
    </div>

    <!-- Apply result -->
    <div v-if="applyDone && applyResult"
         class="mb-5 rounded-lg border border-green-300 bg-green-50 p-4">
      <p class="font-semibold text-green-800 mb-2">
        <i class="fa-solid fa-circle-check mr-1"></i> सिंक सफल भयो!
      </p>
      <div class="flex gap-6 text-sm text-green-700">
        <span><strong>{{ applyResult.imported }}</strong> नयाँ थपियो</span>
        <span><strong>{{ applyResult.updated }}</strong> अपडेट भयो</span>
        <span><strong>{{ applyResult.skipped }}</strong> छोडियो</span>
      </div>
    </div>

    <!-- Preview summary + table -->
    <template v-if="previewDone">

      <!-- Summary cards -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">
        <div class="rounded-lg bg-green-50 border border-green-200 p-3 text-center">
          <p class="text-2xl font-bold text-green-700">{{ counts.new }}</p>
          <p class="text-xs text-green-600 mt-0.5">नयाँ</p>
        </div>
        <div class="rounded-lg bg-yellow-50 border border-yellow-200 p-3 text-center">
          <p class="text-2xl font-bold text-yellow-700">{{ counts.changed }}</p>
          <p class="text-xs text-yellow-600 mt-0.5">परिवर्तित</p>
        </div>
        <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-center">
          <p class="text-2xl font-bold text-red-700">{{ counts.locked }}</p>
          <p class="text-xs text-red-600 mt-0.5">सुरक्षित (म्यानुअल)</p>
        </div>
        <div class="rounded-lg bg-gray-50 border border-gray-200 p-3 text-center">
          <p class="text-2xl font-bold text-gray-600">{{ counts.upToDate }}</p>
          <p class="text-xs text-gray-500 mt-0.5">अद्यावधिक</p>
        </div>
        <div class="rounded-lg bg-orange-50 border border-orange-200 p-3 text-center">
          <p class="text-2xl font-bold text-orange-700">{{ counts.errors }}</p>
          <p class="text-xs text-orange-600 mt-0.5">त्रुटि</p>
        </div>
      </div>

      <!-- Apply action -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-5 flex flex-wrap items-center gap-4">
        <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
          <input type="checkbox" v-model="forceLocked" class="rounded text-red-500" />
          <span class="text-gray-700">
            सुरक्षित रेकर्डहरू पनि ओभरराइट गर्नुहोस्
          </span>
          <span class="text-xs text-red-500">(सावधान: म्यानुअल सम्पादन मेटिन्छ)</span>
        </label>
        <button @click="doApply" :disabled="!canApply"
                class="ml-auto px-5 py-2 rounded-lg text-sm font-medium transition
                       bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
          <i v-if="applying" class="fa-solid fa-spinner fa-spin"></i>
          <i v-else class="fa-solid fa-cloud-arrow-down"></i>
          {{ applying ? 'सिंक हुँदैछ…' : 'सिंक लागू गर्नुहोस्' }}
        </button>
      </div>

      <!-- Preview rows table -->
      <div v-if="rows.length" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-5">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
          <h3 class="font-medium text-gray-800 text-sm">
            प्रिभ्यू ({{ rows.length }} पङ्क्ति)
            <span v-if="rows.length === 200" class="text-gray-400 font-normal"> — पहिलो २०० मात्र देखाइएको</span>
          </h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-xs">
            <thead class="bg-gray-50 text-gray-500 uppercase">
              <tr>
                <th class="px-3 py-2 text-left">अवस्था</th>
                <th class="px-3 py-2 text-left">Unique Code</th>
                <th v-if="dataType === 'households'" class="px-3 py-2 text-left">उत्तरदाता</th>
                <th v-if="dataType === 'institutions'" class="px-3 py-2 text-left">नाम</th>
                <th class="px-3 py-2 text-left">GPS</th>
                <th class="px-3 py-2 text-left">सर्वेक्षण मिति</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="(row, i) in rows" :key="i"
                  :class="row._action === 'new' ? 'bg-green-50' : row._action === 'changed' ? 'bg-yellow-50' : 'bg-red-50'">
                <td class="px-3 py-2">
                  <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="actionClass(row._action)">
                    {{ actionLabel(row._action) }}
                  </span>
                </td>
                <td class="px-3 py-2 font-mono text-gray-700">{{ row.unique_code ?? row.household_code ?? row.institution_code ?? '—' }}</td>
                <td v-if="dataType === 'households'" class="px-3 py-2 text-gray-700">{{ row.respondent_name ?? '—' }}</td>
                <td v-if="dataType === 'institutions'" class="px-3 py-2 text-gray-700">{{ row.name_np ?? row.name_en ?? '—' }}</td>
                <td class="px-3 py-2 text-gray-500">
                  <span v-if="row.gps_lat && row.gps_lng">{{ Number(row.gps_lat).toFixed(4) }}, {{ Number(row.gps_lng).toFixed(4) }}</span>
                  <span v-else>—</span>
                </td>
                <td class="px-3 py-2 text-gray-500">{{ row.survey_date ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Errors list -->
      <div v-if="errors.length" class="bg-white rounded-xl shadow-sm border border-orange-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-orange-100 bg-orange-50">
          <h3 class="font-medium text-orange-800 text-sm">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
            {{ errors.length }} त्रुटि पङ्क्तिहरू
          </h3>
        </div>
        <ul class="divide-y divide-orange-100 text-xs">
          <li v-for="(err, i) in errors" :key="i" class="px-4 py-3">
            <!-- Row identifier + reason -->
            <div class="flex items-center gap-2 text-orange-700">
              <span class="font-mono bg-orange-100 px-1.5 py-0.5 rounded text-gray-600">
                {{ err.row?.unique_code ?? err.row?.household_code ?? err.row?.institution_code ?? '?' }}
              </span>
              <span>{{ err.reason }}</span>
            </div>
            <!-- Field-level validation errors -->
            <div v-if="err.validation_errors?.length" class="mt-2 ml-2 space-y-1">
              <div v-for="(ve, j) in err.validation_errors" :key="j"
                   class="flex flex-wrap items-center gap-1.5 text-red-700">
                <span class="font-mono bg-red-100 px-1.5 py-0.5 rounded">{{ ve.field }}</span>
                <span class="text-gray-400">:</span>
                <span class="bg-red-100 px-1.5 py-0.5 rounded font-medium">"{{ ve.value }}"</span>
                <span class="text-gray-500">— अमान्य मान (पङ्क्ति {{ ve.row }})</span>
              </div>
            </div>
          </li>
        </ul>
      </div>

    </template>

    <!-- Empty state before preview -->
    <div v-else-if="!errorMsg && !previewing"
         class="text-center py-16 text-gray-400">
      <i class="fa-brands fa-google text-5xl mb-3 block text-gray-300"></i>
      <p class="text-sm">वडा छानेर "प्रिभ्यू हेर्नुहोस्" थिच्नुहोस्</p>
    </div>

  </div>
</template>
