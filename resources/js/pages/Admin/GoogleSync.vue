<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  wards:               { type: Array,   default: () => [] },
  hasHouseholdSheet:   { type: Boolean, default: false },
  hasInstitutionSheet: { type: Boolean, default: false },
})

// ── Form state ────────────────────────────────────────────────────────────────
const dataType    = ref('households')
const wardId      = ref('')
const forceLocked = ref(false)

// ── Force-locked confirmation modal ──────────────────────────────────────────
const showForceLockedModal = ref(false)

function handleApplyClick() {
  if (forceLocked.value && counts.value.locked > 0) {
    showForceLockedModal.value = true
  } else {
    doApply()
  }
}

function confirmForceApply() {
  showForceLockedModal.value = false
  doApply()
}

// ── Preview state ─────────────────────────────────────────────────────────────
const previewing  = ref(false)
const applying    = ref(false)
const previewDone  = ref(false)
const applyDone    = ref(false)
const errorMsg     = ref('')
const showCoverage = ref(false)

const counts     = ref({ new: 0, changed: 0, locked: 0, upToDate: 0, errors: 0 })
const rows       = ref([])
const errors     = ref([])
const debug      = ref(null)
const applyResult = ref(null)
const diffKey    = ref(null)

// ── Completeness checks — differ by data type ─────────────────────────────────
// Each entry: [key in _completeness, emoji, Nepali label, is_count (vs boolean)]
const completenessChecks = computed(() =>
  dataType.value === 'households'
    ? [
        ['members',     '👥', 'सदस्य',    true],
        ['housing',     '🏠', 'आवास',     false],
        ['utilities',   '💧', 'उपयोगिता', false],
        ['economic',    '💰', 'आर्थिक',   false],
        ['agriculture', '🌾', 'कृषि',     false],
        ['livestock',   '🐄', 'पशु',      true],
        ['settlement',  '🏘', 'बस्ती',    false],
      ]
    : [
        ['infrastructure', '🏗', 'भवन',    false],
        ['staff',          '👤', 'कर्मचारी', true],
        ['education',      '📚', 'कक्षा',   true],
        ['health',         '🏥', 'स्वास्थ्य', false],
        ['services',       '🛎', 'सेवा',    true],
        ['teachers',       '👨‍🏫', 'शिक्षक', true],
        ['settlement',     '🏘', 'बस्ती',   false],
      ]
)

// ── Computed warning stats ────────────────────────────────────────────────────
const hasSheet = computed(() =>
  dataType.value === 'households' ? props.hasHouseholdSheet : props.hasInstitutionSheet
)
const canPreview = computed(() => wardId.value && hasSheet.value && !previewing.value)
const canApply   = computed(() =>
  previewDone.value &&
  (counts.value.new > 0 || counts.value.changed > 0 || (forceLocked.value && counts.value.locked > 0)) &&
  !applying.value
)

const isHouseholds = computed(() => dataType.value === 'households')

const noMembersRows = computed(() =>
  isHouseholds.value
    ? rows.value.filter(r => (r._completeness?.members ?? 1) === 0).length
    : 0
)
const missingSheetWarnings = computed(() => {
  const boolChecks = isHouseholds.value
    ? [
        { key: 'housing',         label: 'आवास विवरण' },
        { key: 'utilities',       label: 'उपयोगिता विवरण' },
        { key: 'economic',        label: 'आर्थिक विवरण' },
        { key: 'agriculture',     label: 'कृषि विवरण' },
      ]
    : [
        { key: 'infrastructure',  label: 'भवन विवरण' },
      ]
  const warnings = []
  for (const { key, label } of boolChecks) {
    const missing = rows.value.filter(r => !r._completeness?.[key]).length
    if (missing > 0) warnings.push({ key, label, count: missing })
  }
  return warnings
})

function resetState() {
  previewDone.value  = false
  applyDone.value    = false
  errorMsg.value     = ''
  applyResult.value  = null
  rows.value         = []
  errors.value       = []
  debug.value        = null
  counts.value       = { new: 0, changed: 0, locked: 0, upToDate: 0, errors: 0 }
  diffKey.value     = null
}

function extractError(e) {
  const d = e.response?.data
  if (!d) return `नेटवर्क त्रुटि (HTTP ${e.response?.status ?? 'no response'})`
  if (d.error)   return d.error
  if (d.message) return d.message
  if (d.errors)  return Object.values(d.errors).flat().join(' | ')
  return `HTTP ${e.response?.status}: ` + JSON.stringify(d).slice(0, 200)
}

async function doPreview() {
  resetState()
  previewing.value = true
  try {
    const res = await axios.post('/admin/google-sync/preview', {
      type:    dataType.value,
      ward_id: wardId.value,
    })
    counts.value      = res.data.counts
    rows.value        = res.data.rows     ?? []
    errors.value      = res.data.errors   ?? []
    debug.value       = res.data.debug    ?? null
    diffKey.value     = res.data.diff_key ?? null
    previewDone.value = true
  } catch (e) {
    errorMsg.value = extractError(e)
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
      diff_key:     diffKey.value,
    })
    applyResult.value = res.data
    applyDone.value   = true
    previewDone.value = false
  } catch (e) {
    errorMsg.value = extractError(e)
  } finally {
    applying.value = false
  }
}

function completenessOk(row, key, isCount) {
  if (!row._completeness) return true
  return isCount ? (row._completeness[key] ?? 0) > 0 : !!row._completeness[key]
}
function completenessLabel(row, key, label, isCount) {
  if (!row._completeness) return label
  const v = row._completeness[key]
  return isCount ? `${label}: ${v ?? 0}` : `${label}: ${v ? 'उपलब्ध' : 'उपलब्ध छैन'}`
}

function actionLabel(action) {
  return { new: 'नयाँ', changed: 'परिवर्तित', locked: 'सुरक्षित' }[action] ?? action
}
function actionClass(action) {
  return {
    new:        'bg-green-100 text-green-800',
    changed:    'bg-yellow-100 text-yellow-800',
    locked:     'bg-red-100 text-red-800',
    up_to_date: 'bg-gray-100 text-gray-500',
  }[action] ?? 'bg-gray-100 text-gray-600'
}
</script>

<template>
  <div class="p-6 max-w-6xl mx-auto">

    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
        <i class="fa-brands fa-google text-blue-600"></i>
        Google Sheets सिंक
      </h1>
      <p class="text-sm text-gray-500 mt-1">
        Google Sheets बाट घरधुरी वा संस्था डेटा वडा अनुसार प्रिभ्यू गरी सिंक गर्नुहोस्।
      </p>
      <a href="/admin/google-sync/manual" target="_blank"
        class="inline-flex items-center gap-1.5 mt-2 text-xs text-[#206bc4] hover:underline">
        <i class="fa-solid fa-book-open text-[11px]"></i>
        Sheet तयारी निर्देशिका हेर्नुहोस् (Manual)
      </a>
    </div>

    <!-- No sheet configured warning -->
    <div v-if="!hasHouseholdSheet && !hasInstitutionSheet"
         class="mb-5 rounded-lg border border-amber-300 bg-amber-50 p-4 flex gap-3">
      <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5"></i>
      <div>
        <p class="font-medium text-amber-800">Sheet ID कन्फिगर गरिएको छैन</p>
        <p class="text-sm text-amber-700 mt-0.5">
          <a href="/admin/settings#google_sheets" class="underline">Settings → Google Sheets</a>
          मा Household र Institution Sheet ID थप्नुहोस्।
        </p>
      </div>
    </div>

    <!-- ── Coverage info accordion ─────────────────────────────────────────── -->
    <div class="mb-5 rounded-xl border border-blue-200 bg-blue-50 overflow-hidden">
      <button @click="showCoverage = !showCoverage"
              class="w-full px-4 py-3 flex items-center justify-between text-sm text-blue-800 font-medium hover:bg-blue-100 transition">
        <span class="flex items-center gap-2">
          <i class="fa-solid fa-circle-info text-blue-500"></i>
          Google Sync ले के-के डेटा समावेश गर्छ? (थिचेर हेर्नुहोस्)
        </span>
        <i :class="showCoverage ? 'fa-chevron-up' : 'fa-chevron-down'"
           class="fa-solid text-blue-400 text-xs"></i>
      </button>
      <div v-if="showCoverage" class="px-4 pb-4 pt-1 border-t border-blue-200">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
          <!-- Synced -->
          <div>
            <p class="text-xs font-bold text-green-700 mb-2 flex items-center gap-1">
              <i class="fa-solid fa-circle-check"></i> Google Sync मा समावेश हुने डेटा
            </p>
            <ul class="text-xs text-gray-700 space-y-1.5">
              <li class="flex gap-2"><span class="text-green-500">✓</span><span><strong>Households</strong> — घरमुली, वडा, बस्ती, GPS, सम्पर्क, सर्वेक्षण मिति</span></li>
              <li class="flex gap-2"><span class="text-green-500">✓</span><span><strong>Members</strong> — सदस्यको नाम, उमेर, लिंग, शिक्षा, पेशा, अपाङ्गता, नागरिकता</span></li>
              <li class="flex gap-2"><span class="text-green-500">✓</span><span><strong>Housing</strong> — घर प्रकार, छत/भित्ता/जग, कोठा, तल्ला, अवस्था</span></li>
              <li class="flex gap-2"><span class="text-green-500">✓</span><span><strong>Utilities</strong> — खानेपानी, शौचालय, बिजुली, ऊर्जा, इन्टरनेट, हात धुने</span></li>
              <li class="flex gap-2"><span class="text-green-500">✓</span><span><strong>Economic</strong> — आम्दानीको स्रोत, आम्दानी वर्ग, ऋण, बैंक खाता, गरिबी कार्ड</span></li>
              <li class="flex gap-2"><span class="text-green-500">✓</span><span><strong>Agriculture</strong> — खेती, जमिन, कृषि प्रकार, मल/विषादी, आम्दानी</span></li>
              <li class="flex gap-2"><span class="text-green-500">✓</span><span><strong>Livestock</strong> — पशुपन्छी प्रकार, संख्या, मौरी/माछापालन</span></li>
            </ul>
          </div>
          <!-- Not synced — needs manual entry -->
          <div>
            <p class="text-xs font-bold text-amber-700 mb-2 flex items-center gap-1">
              <i class="fa-solid fa-pencil"></i> फारममा म्यानुअल रूपमा भर्नु पर्ने डेटा
            </p>
            <ul class="text-xs text-gray-700 space-y-1.5">
              <li class="flex gap-2"><span class="text-amber-500">!</span><span><strong>चरण ४</strong> — निर्णय प्रक्रिया (decision making)</span></li>
              <li class="flex gap-2"><span class="text-amber-500">!</span><span><strong>चरण ६</strong> — खुसी र मेलमिलाप (wellbeing)</span></li>
              <li class="flex gap-2"><span class="text-amber-500">!</span><span><strong>चरण ९</strong> — कृषि उत्पादन विवरण (crop production)</span></li>
              <li class="flex gap-2"><span class="text-amber-500">!</span><span><strong>चरण १३</strong> — प्राकृतिक प्रकोप (disaster/risk)</span></li>
              <li class="flex gap-2"><span class="text-amber-500">!</span><span><strong>चरण १५</strong> — स्वास्थ्य विवरण (health)</span></li>
              <li class="flex gap-2"><span class="text-amber-500">!</span><span>सदस्यको विस्तृत बाल विकास, खोप तथा स्वास्थ्य विवरण</span></li>
            </ul>
            <p class="text-xs text-gray-500 mt-2 bg-white rounded px-2 py-1.5 border border-amber-200">
              <i class="fa-solid fa-circle-info text-amber-400 mr-1"></i>
              सिंक भएपछि फारम खोलेर बाँकी विवरण थप्न सकिन्छ।
            </p>
          </div>
        </div>
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
         :class="applyResult.firstError ? 'border-orange-300 bg-orange-50' : 'border-green-300 bg-green-50'"
         class="mb-5 rounded-lg border p-4">
      <p :class="applyResult.firstError ? 'text-orange-800' : 'text-green-800'" class="font-semibold mb-2">
        <i :class="applyResult.firstError ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-circle-check'" class="mr-1"></i>
        {{ applyResult.firstError ? 'सिंक क्रममा त्रुटि' : 'सिंक सफल भयो!' }}
      </p>
      <div :class="applyResult.firstError ? 'text-orange-700' : 'text-green-700'" class="flex flex-wrap gap-6 text-sm">
        <span><strong>{{ applyResult.imported }}</strong> नयाँ थपियो</span>
        <span><strong>{{ applyResult.updated }}</strong> अपडेट भयो</span>
        <span><strong>{{ applyResult.skipped }}</strong> छोडियो</span>
      </div>
      <p v-if="applyResult.firstError" class="mt-2 text-xs text-orange-600 font-mono bg-orange-100 rounded px-2 py-1">
        {{ applyResult.firstError }}
      </p>
      <p v-if="!applyResult.firstError" class="text-xs mt-2"
         :class="applyResult.firstError ? 'text-orange-600' : 'text-green-600'">
        <i class="fa-solid fa-circle-info mr-1"></i>
        सिंक भएका घरधुरीहरू "Submitted" अवस्थामा छन् — वडा समीक्षकले जाँच गरी प्रकाशित गर्न सक्नुहुन्छ।
      </p>
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

      <!-- Sub-counts info bar (households only) -->
      <div v-if="debug && isHouseholds && debug.sub_counts && (counts.new > 0 || counts.changed > 0 || counts.upToDate > 0)"
           class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2.5 flex flex-wrap gap-5 text-xs text-indigo-800">
        <span><i class="fa-solid fa-users mr-1 text-indigo-400"></i>
          सदस्य पङ्क्ति: <strong>{{ debug.sub_counts.members_in_file }}</strong></span>
        <span><i class="fa-solid fa-cow mr-1 text-indigo-400"></i>
          पशुपन्छी पङ्क्ति: <strong>{{ debug.sub_counts.livestock_in_file }}</strong></span>
        <span><i class="fa-solid fa-house mr-1 text-indigo-400"></i>
          आवास पङ्क्ति: <strong>{{ debug.sub_counts.housing_in_file }}</strong></span>
        <span class="text-indigo-500 italic">
          (सबै शीटहरूको कुल; वडा फिल्टर अघिको)
        </span>
      </div>

      <!-- ── Missing data warnings ──────────────────────────────────────────── -->
      <div v-if="isHouseholds && (noMembersRows > 0 || missingSheetWarnings.length > 0)"
           class="mb-5 rounded-lg border border-amber-300 bg-amber-50 p-4">
        <p class="text-sm font-semibold text-amber-800 mb-2 flex items-center gap-2">
          <i class="fa-solid fa-triangle-exclamation"></i>
          विवरण अपूर्णताको सूचना
        </p>
        <ul class="text-sm text-amber-700 space-y-1">
          <li v-if="noMembersRows > 0" class="flex items-start gap-2">
            <span class="text-red-500 font-bold shrink-0">⚠</span>
            <span>
              <strong>{{ noMembersRows }} घरधुरीमा</strong> Members शीटमा सदस्य विवरण छैन।
              Members शीटमा household_code मिलेको पङ्क्तिहरू थप्नुहोस्।
            </span>
          </li>
          <li v-for="w in missingSheetWarnings" :key="w.key" class="flex items-start gap-2">
            <span class="text-amber-500 shrink-0">•</span>
            <span>
              <strong>{{ w.count }} घरधुरीमा</strong> {{ w.label }} छैन
              ({{ w.key.charAt(0).toUpperCase() + w.key.slice(1) }} शीट खाली वा household_code मिलेन)।
            </span>
          </li>
        </ul>
        <p class="text-xs text-amber-600 mt-2">
          <i class="fa-solid fa-circle-info mr-1"></i>
          अपूर्ण डेटा भएका घरधुरी पनि सिंक हुन्छन् — बाँकी विवरण पछि फारममा थप्न सकिन्छ।
        </p>
      </div>

      <!-- Apply action row -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-5 flex flex-wrap items-center gap-4">
        <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
          <input type="checkbox" v-model="forceLocked" class="rounded text-red-500" />
          <span class="text-gray-700">सुरक्षित रेकर्डहरू पनि ओभरराइट गर्नुहोस्</span>
          <span class="text-xs text-red-500">(सावधान: म्यानुअल सम्पादन मेटिन्छ)</span>
        </label>
        <button @click="handleApplyClick" :disabled="!canApply"
                class="ml-auto px-5 py-2 rounded-lg text-sm font-medium transition
                       bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
          <i v-if="applying" class="fa-solid fa-spinner fa-spin"></i>
          <i v-else class="fa-solid fa-cloud-arrow-down"></i>
          {{ applying ? 'सिंक हुँदैछ…' : 'सिंक लागू गर्नुहोस्' }}
        </button>
      </div>

      <!-- Debug panel — only when all counts are 0 (filter mismatch diagnosis) -->
      <div v-if="debug && counts.new === 0 && counts.changed === 0 && counts.locked === 0 && counts.upToDate === 0 && counts.errors === 0"
           class="mb-5 rounded-lg border border-blue-200 bg-blue-50 p-4 text-xs font-mono space-y-1 text-blue-900">
        <p class="font-semibold text-blue-800 text-sm mb-2">🔍 Debug: कुनै पनि पङ्क्ति मेल खाएन</p>
        <p><span class="text-blue-500">File मा कुल पङ्क्ति:</span> {{ debug.total_rows }}</p>
        <p><span class="text-blue-500">Column names:</span> {{ debug.column_names?.join(', ') || '(none)' }}</p>
        <p><span class="text-blue-500">File मा वडा नम्बरहरू:</span> {{ debug.wards_in_file?.join(', ') || '(कुनै ward_number column छैन)' }}</p>
        <p><span class="text-blue-500">Filter गरिरहेको वडा:</span> {{ debug.filtering_for_ward }}</p>
      </div>

      <!-- ── Preview rows table ─────────────────────────────────────────────── -->
      <div v-if="rows.length" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-5">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
          <h3 class="font-medium text-gray-800 text-sm">
            प्रिभ्यू ({{ rows.length }} पङ्क्ति)
            <span v-if="rows.length === 200" class="text-gray-400 font-normal"> — पहिलो २०० मात्र</span>
          </h3>
          <!-- Completeness legend for households -->
          <div v-if="isHouseholds" class="hidden sm:flex items-center gap-2 text-xs text-gray-500">
            <span>विवरण पूर्णता:</span>
            <span v-for="[,icon,,] in completenessChecks" :key="icon" class="opacity-50 grayscale">{{ icon }}</span>
            <span class="text-gray-400">= खाली</span>
            <span v-for="[,icon,,] in completenessChecks" :key="'ok'+icon">{{ icon }}</span>
            <span class="text-gray-400">= उपलब्ध</span>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-xs">
            <thead class="bg-gray-50 text-gray-500 uppercase">
              <tr>
                <th class="px-3 py-2 text-left">अवस्था</th>
                <th class="px-3 py-2 text-left">Unique Code</th>
                <th v-if="isHouseholds"  class="px-3 py-2 text-left">उत्तरदाता / बस्ती</th>
                <th v-if="!isHouseholds" class="px-3 py-2 text-left">नाम</th>
                <th v-if="isHouseholds"  class="px-3 py-2 text-center">विवरण पूर्णता</th>
                <th class="px-3 py-2 text-left">GPS</th>
                <th class="px-3 py-2 text-left">सर्वेक्षण मिति</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="(row, i) in rows" :key="i"
                  :class="row._action === 'new' ? 'bg-green-50' : row._action === 'changed' ? 'bg-yellow-50' : 'bg-red-50'">

                <!-- Status badge -->
                <td class="px-3 py-2">
                  <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="actionClass(row._action)">
                    {{ actionLabel(row._action) }}
                  </span>
                </td>

                <!-- Code -->
                <td class="px-3 py-2 font-mono text-gray-700">
                  {{ row.unique_code ?? row.household_code ?? row.institution_code ?? '—' }}
                </td>

                <!-- Respondent + settlement (households) or name (institutions) -->
                <td v-if="isHouseholds" class="px-3 py-2 text-gray-700">
                  <div>{{ row.respondent_name ?? '—' }}</div>
                  <div v-if="row.settlement_name" class="text-gray-400 text-[10px] mt-0.5">
                    <i class="fa-solid fa-location-dot text-[9px]"></i>
                    {{ row.settlement_name }}
                  </div>
                </td>
                <td v-if="!isHouseholds" class="px-3 py-2 text-gray-700">
                  {{ row.name_np ?? row.name_en ?? '—' }}
                </td>

                <!-- Completeness icons (households only) -->
                <td v-if="isHouseholds" class="px-2 py-2 text-center">
                  <div class="flex items-center justify-center gap-0.5">
                    <span v-for="[key, icon, label, isCount] in completenessChecks" :key="key"
                          :title="completenessLabel(row, key, label, isCount)"
                          :class="completenessOk(row, key, isCount) ? '' : 'opacity-20 grayscale'"
                          class="text-sm leading-none select-none">{{ icon }}</span>
                  </div>
                  <!-- Red badge if no members -->
                  <div v-if="(row._completeness?.members ?? 1) === 0"
                       class="text-[9px] text-red-600 font-semibold mt-0.5">सदस्य छैन!</div>
                </td>

                <!-- GPS -->
                <td class="px-3 py-2 text-gray-500">
                  <span v-if="row.gps_lat && row.gps_lng">
                    {{ Number(row.gps_lat).toFixed(4) }}, {{ Number(row.gps_lng).toFixed(4) }}
                  </span>
                  <span v-else class="text-gray-300">—</span>
                </td>

                <!-- Survey date -->
                <td class="px-3 py-2 text-gray-500">{{ row.survey_date ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Errors list ─────────────────────────────────────────────────────── -->
      <div v-if="errors.length" class="bg-white rounded-xl shadow-sm border border-orange-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-orange-100 bg-orange-50">
          <h3 class="font-medium text-orange-800 text-sm">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
            {{ errors.length }} त्रुटि पङ्क्तिहरू
            <span class="text-xs font-normal text-orange-600 ml-1">— यी सिंक हुँदैनन्, सुधार गरेर पुनः सिंक गर्नुहोस्</span>
          </h3>
        </div>
        <ul class="divide-y divide-orange-100 text-xs">
          <li v-for="(err, i) in errors" :key="i" class="px-4 py-3">
            <div class="flex items-center gap-2 text-orange-700">
              <span class="font-mono bg-orange-100 px-1.5 py-0.5 rounded text-gray-600">
                {{ err.row?.unique_code ?? err.row?.household_code ?? err.row?.institution_code ?? '?' }}
              </span>
              <span>{{ err.reason }}</span>
            </div>
            <div v-if="err.validation_errors?.length" class="mt-2 ml-2 space-y-1">
              <div v-for="(ve, j) in err.validation_errors" :key="j"
                   class="text-red-700 bg-red-50 rounded px-2 py-1">
                {{ typeof ve === 'string' ? ve : `${ve.field}: "${ve.value}"` }}
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
      <p class="text-sm">वडा छानेर <strong class="text-gray-500">"प्रिभ्यू हेर्नुहोस्"</strong> थिच्नुहोस्</p>
      <p class="text-xs mt-1 text-gray-400">Google Sheets बाट सबै ८ शीटहरूको (घरधुरी) / ७ शीटहरूको (संस्था) डेटा एकैपटक तुलना हुन्छ।</p>
    </div>

  </div>

  <!-- ── Force-locked confirmation modal ──────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="showForceLockedModal"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4"
         @click.self="showForceLockedModal = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">

        <!-- Header -->
        <div class="bg-red-600 px-5 py-4 flex items-center gap-3">
          <i class="fa-solid fa-triangle-exclamation text-white text-lg"></i>
          <h3 class="text-white font-semibold text-[15px]">म्यानुअल सम्पादन ओभरराइट हुनेछ</h3>
        </div>

        <!-- Body -->
        <div class="px-5 py-4 space-y-3">
          <p class="text-[13px] text-gray-700">
            तलका <strong class="text-red-700">{{ counts.locked }} रेकर्डहरू</strong> म्यानुअल रूपमा सम्पादन गरी <em>सुरक्षित</em> गरिएका छन्।
            Google Sheets को डेटाले ती परिवर्तनहरू <strong class="text-red-700">अधिलेखन (overwrite)</strong> गर्नेछ।
          </p>

          <!-- List of locked codes (max 10 shown) -->
          <div class="bg-red-50 border border-red-200 rounded-lg px-3 py-2 max-h-40 overflow-y-auto">
            <p class="text-[11px] font-semibold text-red-600 mb-1">प्रभावित रेकर्डहरू:</p>
            <div v-for="row in rows.filter(r => r._action === 'locked').slice(0, 10)"
                 :key="row.unique_code ?? row.institution_code"
                 class="text-[12px] text-red-800 font-mono py-0.5 border-b border-red-100 last:border-0">
              {{ row.unique_code ?? row.institution_code ?? '—' }}
              <span v-if="row.respondent_name || row.name_np" class="font-sans font-normal text-red-600 ml-2">
                — {{ row.respondent_name || row.name_np }}
              </span>
            </div>
            <p v-if="counts.locked > 10" class="text-[11px] text-red-500 mt-1 italic">
              + {{ counts.locked - 10 }} थप रेकर्डहरू…
            </p>
          </div>

          <p class="text-[12px] text-gray-500">
            <i class="fa-solid fa-circle-info mr-1"></i>
            यो कार्य पूर्ववत गर्न सकिँदैन। जारी राख्नुअघि निश्चित गर्नुहोस्।
          </p>
        </div>

        <!-- Footer -->
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-200 flex items-center justify-end gap-3">
          <button @click="showForceLockedModal = false"
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 transition">
            रद्द गर्नुहोस्
          </button>
          <button @click="confirmForceApply"
                  class="px-5 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation text-xs"></i>
            ओभरराइट गरी अगाडि बढ्नुहोस्
          </button>
        </div>

      </div>
    </div>
  </Teleport>

</template>
