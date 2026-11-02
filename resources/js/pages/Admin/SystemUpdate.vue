<template>
  <div class="space-y-4 max-w-4xl">

    <!-- Header -->
    <div>
      <h1 class="text-[15px] font-semibold text-[#1d273b]">प्रणाली अद्यावधिक</h1>
      <p class="text-[11px] text-[#9aa0ac] mt-0.5">System Update — install a signed one-file update package</p>
    </div>

    <!-- Status strip -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div class="bg-white border border-[#e6e7e9] rounded-md px-4 py-3">
        <p class="text-[11px] text-[#9aa0ac] uppercase tracking-wide">हालको संस्करण</p>
        <p class="text-[20px] font-bold text-[#206bc4] mt-0.5">{{ currentVersion }}</p>
      </div>
      <div class="bg-white border border-[#e6e7e9] rounded-md px-4 py-3">
        <p class="text-[11px] text-[#9aa0ac] uppercase tracking-wide">विश्वसनीय साँचो</p>
        <p v-if="keyConfigured" class="text-[13px] font-mono text-[#1d273b] mt-1.5">{{ keyFingerprint }}</p>
        <p v-else class="text-[12px] font-semibold text-red-600 mt-1.5">कन्फिगर गरिएको छैन</p>
      </div>
      <div class="bg-white border border-[#e6e7e9] rounded-md px-4 py-3">
        <p class="text-[11px] text-[#9aa0ac] uppercase tracking-wide">अपलोड सीमा</p>
        <p class="text-[13px] text-[#1d273b] mt-1.5">{{ (uploadLimitKb / 1024).toFixed(1) }} MB</p>
      </div>
    </div>

    <div v-if="!keyConfigured" class="bg-red-50 border border-red-200 rounded-md p-4 text-[12px] text-red-800">
      This site has no trusted update key, so no package can be installed. The key arrives with a full deploy ZIP.
    </div>

    <!-- 1. Upload -->
    <div v-if="keyConfigured && canUpload" class="bg-white border border-[#e6e7e9] rounded-md p-5">
      <h2 class="text-[13px] font-semibold text-[#1d273b] mb-1">१. अद्यावधिक प्याकेज छान्नुहोस्</h2>
      <p class="text-[11px] text-[#9aa0ac] mb-4">
        <code>apprmp-update-x.y.z.zip</code> — the package is checked for a valid signature, matching
        dependencies and a newer version before anything is changed.
      </p>
      <div class="flex flex-wrap items-center gap-3">
        <input ref="fileInput" type="file" accept=".zip" @change="pick"
          class="text-[12px] file:mr-3 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-[#206bc4]/10 file:text-[#206bc4] file:text-[12px] file:font-medium" />
        <button @click="upload" :disabled="!file || busy"
          class="inline-flex items-center gap-2 h-9 px-4 bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-50 disabled:cursor-not-allowed text-white text-[13px] font-medium rounded">
          <i :class="busy ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-shield-halved'" class="text-[11px]"></i>
          {{ busy ? 'जाँच हुँदैछ…' : 'जाँच गर्नुहोस्' }}
        </button>
      </div>
    </div>

    <!-- Error -->
    <div v-if="error" class="bg-red-50 border border-red-200 rounded-md p-4">
      <p class="text-[12px] font-semibold text-red-800"><i class="fa-solid fa-circle-xmark mr-1"></i>{{ error }}</p>
    </div>

    <!-- 2. Verified — confirm -->
    <div v-if="state && state.status === 'verified'" class="bg-white border border-[#e6e7e9] rounded-md p-5">
      <h2 class="text-[13px] font-semibold text-[#1d273b] mb-3">
        <i class="fa-solid fa-circle-check text-green-600 mr-1"></i>
        २. प्याकेज प्रमाणित भयो
      </h2>
      <table class="w-full text-[12px] mb-4">
        <tbody>
          <tr class="border-b border-[#f0f1f3]"><td class="py-1.5 text-[#9aa0ac] w-40">संस्करण</td>
            <td class="py-1.5 font-semibold">{{ state.from_version }} → {{ state.version }}</td></tr>
          <tr class="border-b border-[#f0f1f3]"><td class="py-1.5 text-[#9aa0ac]">बनाइएको</td>
            <td class="py-1.5">{{ state.summary?.built_at }} <span class="text-[#9aa0ac]">· commit {{ state.summary?.commit ?? '—' }}</span></td></tr>
          <tr class="border-b border-[#f0f1f3]"><td class="py-1.5 text-[#9aa0ac]">फाइलहरू</td>
            <td class="py-1.5">{{ state.summary?.files }}</td></tr>
          <tr class="border-b border-[#f0f1f3]"><td class="py-1.5 text-[#9aa0ac]">माइग्रेसन</td>
            <td class="py-1.5">{{ state.summary?.migrations }} shipped — only ones not yet run will run</td></tr>
          <tr><td class="py-1.5 text-[#9aa0ac]">सिडर</td>
            <td class="py-1.5">{{ state.summary?.seeders?.length ? state.summary.seeders.join(', ') : 'none' }}</td></tr>
        </tbody>
      </table>
      <div class="bg-amber-50 border border-amber-200 rounded p-3 text-[11px] text-amber-900 mb-4 space-y-1">
        <p><i class="fa-solid fa-triangle-exclamation mr-1"></i>The public will see the maintenance page for a minute or two. Signed-in users are not affected.</p>
        <p><i class="fa-solid fa-rotate-left mr-1"></i>If any step fails, the files are restored automatically. Database migrations that already ran are not reversed.</p>
      </div>
      <div class="flex gap-2">
        <button @click="applyAll" :disabled="busy"
          class="inline-flex items-center gap-2 h-9 px-4 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white text-[13px] font-medium rounded">
          <i class="fa-solid fa-download text-[11px]"></i> अद्यावधिक गर्नुहोस्
        </button>
        <button @click="discard" :disabled="busy"
          class="h-9 px-4 border border-[#e6e7e9] hover:bg-gray-50 text-[#626976] text-[13px] rounded">
          रद्द गर्नुहोस्
        </button>
      </div>
    </div>

    <!-- 3. Progress -->
    <div v-if="state && state.status !== 'verified' && !isHistory" class="bg-white border border-[#e6e7e9] rounded-md p-5">
      <h2 class="text-[13px] font-semibold text-[#1d273b] mb-3">
        ३. {{ state.from_version }} → {{ state.version }}
      </h2>

      <ol class="space-y-2 mb-4">
        <li v-for="s in steps" :key="s.key" class="flex items-center gap-3 text-[12px]">
          <span class="w-5 text-center">
            <i v-if="stepStatus(s.key) === 'done'"    class="fa-solid fa-circle-check text-green-600"></i>
            <i v-else-if="stepStatus(s.key) === 'running'" class="fa-solid fa-spinner fa-spin text-[#206bc4]"></i>
            <i v-else-if="stepStatus(s.key) === 'failed'"  class="fa-solid fa-circle-xmark text-red-600"></i>
            <i v-else class="fa-regular fa-circle text-[#c5c8cd]"></i>
          </span>
          <span :class="stepStatus(s.key) === 'pending' ? 'text-[#9aa0ac]' : 'text-[#1d273b]'">{{ s.label }}</span>
        </li>
      </ol>

      <div v-if="state.status === 'finished'" class="bg-green-50 border border-green-200 rounded p-3 mb-4">
        <p class="text-[12px] font-semibold text-green-800">
          <i class="fa-solid fa-circle-check mr-1"></i>अद्यावधिक सम्पन्न भयो — now running {{ state.version }}.
        </p>
        <p class="text-[11px] text-green-700 mt-1">Reload the page so your browser picks up the new interface.</p>
      </div>

      <div v-if="state.status === 'rolled_back'" class="bg-amber-50 border border-amber-200 rounded p-3 mb-4">
        <p class="text-[12px] font-semibold text-amber-900">
          <i class="fa-solid fa-rotate-left mr-1"></i>Rolled back to {{ state.from_version }}. The site is running as before.
        </p>
        <p v-if="state.error" class="text-[11px] text-amber-800 mt-1">Cause: {{ state.error }}</p>
      </div>

      <div v-if="state.status === 'failed'" class="bg-red-50 border border-red-200 rounded p-3 mb-4">
        <p class="text-[12px] font-semibold text-red-800">{{ state.error }}</p>
      </div>

      <div class="flex flex-wrap gap-2">
        <button v-if="canResume" @click="applyAll" :disabled="busy"
          class="h-9 px-4 bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-50 text-white text-[13px] rounded">
          <i class="fa-solid fa-play text-[11px] mr-1"></i> जारी राख्नुहोस्
        </button>
        <button v-if="canRollback" @click="rollback" :disabled="busy"
          class="h-9 px-4 border border-red-300 text-red-700 hover:bg-red-50 disabled:opacity-50 text-[13px] rounded">
          <i class="fa-solid fa-rotate-left text-[11px] mr-1"></i> फिर्ता गर्नुहोस् (Rollback)
        </button>
        <button v-if="state.status === 'finished' || state.status === 'rolled_back'" @click="reload"
          class="h-9 px-4 bg-[#206bc4] hover:bg-[#1a5ba8] text-white text-[13px] rounded">
          <i class="fa-solid fa-rotate-right text-[11px] mr-1"></i> पेज रिलोड गर्नुहोस्
        </button>
      </div>

      <details class="mt-4">
        <summary class="text-[11px] text-[#9aa0ac] cursor-pointer">विस्तृत लग ({{ state.log?.length ?? 0 }})</summary>
        <div class="mt-2 bg-[#1d273b] text-[#d1d5db] rounded p-3 text-[11px] font-mono max-h-72 overflow-auto whitespace-pre-wrap">
          <div v-for="(l, i) in state.log" :key="i"><span class="text-[#6b7280]">[{{ l.step }}]</span> {{ l.message }}</div>
        </div>
      </details>
    </div>

    <!-- History -->
    <div class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
      <div class="px-4 py-2.5 border-b border-[#e6e7e9] bg-gray-50/60">
        <p class="text-[11px] font-semibold text-[#626976]">अद्यावधिक इतिहास</p>
      </div>
      <table v-if="history.length" class="w-full text-[12px]">
        <thead class="text-[#9aa0ac] text-left">
          <tr>
            <th class="px-4 py-2 font-medium">मिति</th>
            <th class="px-4 py-2 font-medium">संस्करण</th>
            <th class="px-4 py-2 font-medium">अवस्था</th>
            <th class="px-4 py-2 font-medium">गर्ने</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="h in history" :key="h.id" class="border-t border-[#f0f1f3]">
            <td class="px-4 py-2 text-[#626976]">{{ h.finished_at }}</td>
            <td class="px-4 py-2">{{ h.from_version }} → <b>{{ h.version }}</b></td>
            <td class="px-4 py-2">
              <span :class="statusBadge(h.status)" class="px-2 py-0.5 rounded-full text-[11px] font-medium">{{ statusLabel(h.status) }}</span>
            </td>
            <td class="px-4 py-2 text-[#626976]">{{ h.by ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else class="px-4 py-6 text-center text-[12px] text-[#9aa0ac]">अहिलेसम्म कुनै अद्यावधिक गरिएको छैन।</p>
    </div>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

// Imported, not window.axios: window.axios is set up only by app.js (the Blade
// pages). Inertia pages load inertia.js, so on this page window.axios was
// undefined, the very first upload threw in the browser before any request
// left, and the page reported it as a server problem. Found on rong, 1.8.1→1.8.2.
const http = axios.create({
  headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
})

const props = defineProps({
  currentVersion: { type: String, required: true },
  keyConfigured:  { type: Boolean, required: true },
  keyFingerprint: { type: String, default: null },
  state:          { type: Object, default: null },
  uploadLimitKb:  { type: Number, required: true },
  history:        { type: Array, default: () => [] },
})

const steps = [
  { key: 'prepare', label: 'तयारी — मर्मत मोड र हालका फाइलहरूको ब्याकअप' },
  { key: 'extract', label: 'प्याकेजबाट फाइलहरू निकाल्दै' },
  { key: 'swap',    label: 'नयाँ कोड र स्क्रिप्टहरू स्थापना' },
  { key: 'migrate', label: 'डाटाबेस अद्यावधिक (माइग्रेसन, सिडर)' },
  { key: 'finish',  label: 'सम्पन्न — क्यास सफा, मर्मत मोड बन्द' },
]

// Persisted status → the next step to run.
const NEXT = { verified: 'prepare', prepared: 'extract', extracted: 'swap', swapped: 'migrate', migrated: 'finish' }
const DONE_AFTER = { prepare: 'prepared', extract: 'extracted', swap: 'swapped', migrate: 'migrated', finish: 'finished' }
const ORDER = ['verified', 'prepared', 'extracted', 'swapped', 'migrated', 'finished']

const state   = ref(props.state)
const file    = ref(null)
const busy    = ref(false)
const running = ref(null)
const error   = ref(null)
const fileInput = ref(null)

const canResume   = computed(() => !busy.value && state.value && NEXT[state.value.status] && state.value.status !== 'verified')
// A finished or rolled-back update is history once the page has been reloaded:
// its result is in अद्यावधिक इतिहास, and the upload box must come back. Up to
// 1.9.0 the box stayed hidden after every successful update until the state
// was discarded by hand, which nothing on the page offered (found on rong).
// Right after an update ends in this visit, its result panel is still shown.
const endedHere = ref(false)
const isHistory = computed(() =>
  !!state.value && ['finished', 'rolled_back'].includes(state.value.status) && !endedHere.value)
const canUpload = computed(() => !state.value || isHistory.value)

const canRollback = computed(() => !busy.value && state.value && ['prepared', 'extracted', 'swapped', 'migrated', 'failed'].includes(state.value.status))

function stepStatus(key) {
  const s = state.value
  if (!s) return 'pending'
  if (running.value === key) return 'running'
  if (s.status === 'failed' && s.failed_step === key) return 'failed'
  const reached = ORDER.indexOf(s.status)
  const needed  = ORDER.indexOf(DONE_AFTER[key])
  if (s.status === 'failed' || s.status === 'rolled_back') {
    // Show what completed before things stopped, from the log.
    return (s.log ?? []).some(l => l.step === key) && s.failed_step !== key ? 'done' : 'pending'
  }
  return reached >= needed ? 'done' : 'pending'
}

function pick(e) {
  file.value = e.target.files?.[0] ?? null
  error.value = null
}

// 423 = Laravel asking for the password again (password.confirm timed out).
function handleError(e) {
  if (e?.response?.status === 423) {
    error.value = 'Please confirm your password again — reloading…'
    setTimeout(() => window.location.reload(), 1200)
    return
  }
  if (e?.response?.data?.state) state.value = e.response.data.state
  error.value = e?.response?.data?.message
    ?? e?.response?.data?.errors?.package?.[0]
    ?? describeForeignReply(e)
}

/**
 * A reply with no Laravel message did not come from the application — a host
 * firewall (Imunify360 / ModSecurity), a web-server size limit or a resource
 * kill answered instead, and nothing reaches storage/logs. Show the status and
 * the page's title so the cause can be named rather than guessed.
 */
function describeForeignReply(e) {
  const status = e?.response?.status
  // Not an HTTP failure at all: the page itself threw before or while sending.
  if (!status && !e?.request) {
    return `The page failed before contacting the server: ${e?.message ?? e}. Nothing was changed.`
  }
  if (!status) {
    return 'No reply from the server (connection closed). A hosting firewall or resource limit may have stopped the request.'
  }
  const body  = typeof e.response.data === 'string' ? e.response.data : ''
  const title = body.match(/<title[^>]*>([^<]{1,120})<\/title>/i)?.[1]?.trim()
    ?? body.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 120)
  const hint = {
    403: 'blocked before reaching the application — usually the hosting firewall',
    406: 'blocked by the hosting firewall (ModSecurity)',
    413: 'the upload is larger than the web server allows',
    503: 'the hosting resource limit stopped the request',
    508: 'the hosting resource limit stopped the request',
  }[status]
  return `Server replied ${status}${hint ? ` — ${hint}` : ''}.` + (title ? ` (“${title}”)` : '') +
    ' Nothing was changed.'
}

async function upload() {
  if (!file.value) return
  busy.value = true
  error.value = null
  try {
    const form = new FormData()
    form.append('package', file.value)
    const { data } = await http.post('/admin/system-update/upload', form)
    state.value = data.state
  } catch (e) {
    handleError(e)
  } finally {
    busy.value = false
  }
}

async function runStep(step) {
  running.value = step
  try {
    const { data } = await http.post(`/admin/system-update/step/${step}`)
    state.value = data.state
    return true
  } catch (e) {
    handleError(e)
    return false
  } finally {
    running.value = null
  }
}

// Drive the remaining steps one request at a time; roll back automatically on
// failure, so the site is never left half-updated.
async function applyAll() {
  busy.value = true
  error.value = null
  endedHere.value = true
  try {
    while (state.value && NEXT[state.value.status]) {
      const ok = await runStep(NEXT[state.value.status])
      if (!ok) {
        if (state.value?.status === 'failed') {
          await runStep('rollback')
        }
        break
      }
    }
  } finally {
    busy.value = false
  }
}

async function rollback() {
  if (!confirm('Restore the files from before this update?')) return
  endedHere.value = true
  busy.value = true
  error.value = null
  try {
    await runStep('rollback')
  } finally {
    busy.value = false
  }
}

async function discard() {
  busy.value = true
  try {
    await http.post('/admin/system-update/discard')
    state.value = null
    file.value = null
    if (fileInput.value) fileInput.value.value = ''
  } catch (e) {
    handleError(e)
  } finally {
    busy.value = false
  }
}

function reload() {
  window.location.reload()
}

function statusLabel(s) {
  return { finished: 'सम्पन्न', rolled_back: 'फिर्ता', failed: 'असफल' }[s] ?? s
}

function statusBadge(s) {
  return {
    finished:    'bg-green-100 text-green-700',
    rolled_back: 'bg-amber-100 text-amber-800',
    failed:      'bg-red-100 text-red-700',
  }[s] ?? 'bg-gray-100 text-gray-600'
}
</script>
