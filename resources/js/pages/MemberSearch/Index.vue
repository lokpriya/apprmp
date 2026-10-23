<template>
  <div class="max-w-2xl mx-auto space-y-3">

    <!-- Header -->
    <div>
      <h1 class="text-[15px] font-semibold text-[#1d273b] flex items-center gap-2">
        <i class="fa-solid fa-magnifying-glass text-[#206bc4]"></i> सदस्य खोज
      </h1>
      <p class="text-[11px] text-[#9aa0ac] mt-0.5">
        प्रकाशित घरधुरी मात्र ·
        {{ allWards ? 'सबै वडा' : 'तपाईंलाई तोकिएका वडा' }}
      </p>
    </div>

    <!-- Search -->
    <form @submit.prevent="search" class="bg-white border border-[#e6e7e9] rounded-md p-3 space-y-2 sticky top-0 z-10">
      <div class="relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[#9aa0ac] text-sm"></i>
        <input
          ref="input"
          v-model="q"
          type="search"
          inputmode="search"
          enterkeyhint="search"
          autocomplete="off"
          :maxlength="50"
          placeholder="घर नं., घरमुली, मोबाइल वा सदस्यको नाम"
          class="w-full h-12 pl-10 pr-3 border border-[#dadcde] rounded-md text-[15px] focus:outline-none focus:border-[#206bc4] focus:ring-2 focus:ring-[#206bc4]/20"
        />
      </div>
      <!-- Narrow down: ward, then that ward's toles -->
      <div v-if="wards.length > 1 || wardToles.length" class="flex gap-2">
        <select v-if="wards.length > 1" v-model="wardId" @change="toleId = null"
          class="h-11 flex-1 min-w-0 px-2 border border-[#dadcde] rounded-md text-[14px] bg-white">
          <option :value="null">सबै वडा</option>
          <option v-for="w in wards" :key="w.id" :value="w.id">वडा नं. {{ w.ward_number }}</option>
        </select>
        <select v-if="wardToles.length" v-model="toleId"
          class="h-11 flex-1 min-w-0 px-2 border border-[#dadcde] rounded-md text-[14px] bg-white">
          <option :value="null">सबै टोल</option>
          <option v-for="t in wardToles" :key="t.id" :value="t.id">{{ t.name_np }}</option>
        </select>
      </div>
      <button type="submit" :disabled="!canSearch || loading"
        class="h-11 w-full bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-50 text-white rounded-md text-[14px] font-semibold inline-flex items-center justify-center gap-2">
        <i :class="loading ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-magnifying-glass'"></i>
        {{ toleId && !q.trim() ? 'टोलका सबै घरधुरी हेर्नुहोस्' : 'खोज्नुहोस्' }}
      </button>
      <p v-if="q && tooShort" class="text-[11px] text-amber-600">कम्तीमा {{ minLength }} अक्षर लेख्नुहोस्।</p>
    </form>

    <!-- Nothing searched yet -->
    <div v-if="!results" class="text-center py-10 text-[#9aa0ac]">
      <i class="fa-solid fa-users-viewfinder text-3xl mb-2"></i>
      <p class="text-[13px]">घर नं., घरमुलीको नाम, मोबाइल नम्बर वा परिवारको कुनै सदस्यको नामले खोज्नुहोस्।</p>
      <p class="text-[12px] mt-1">वा वडा र टोल छानेर सो टोलका सबै घरधुरी हेर्नुहोस्।</p>
    </div>

    <template v-else>
      <p class="text-[12px] text-[#626976] px-1">
        <span class="font-semibold">{{ results.meta.total }}</span> घरधुरी भेटियो
        <template v-if="results.meta.last_page > 1"> · पृष्ठ {{ results.meta.current_page }}/{{ results.meta.last_page }}</template>
      </p>

      <div v-if="!results.data.length" class="text-center py-10 text-[#9aa0ac] bg-white border border-[#e6e7e9] rounded-md">
        <i class="fa-solid fa-circle-question text-2xl mb-2"></i>
        <p class="text-[13px]">कुनै प्रकाशित घरधुरी भेटिएन।</p>
        <p class="text-[11px] mt-1">ड्राफ्ट वा समीक्षामा रहेका घरधुरी यहाँ देखिँदैनन्।</p>
      </div>

      <!-- Result cards, already in ward → tole → code order -->
      <template v-for="(hh, i) in results.data" :key="hh.id">
        <p v-if="groupLabel(i)" class="text-[11px] font-semibold text-[#626976] uppercase tracking-wide px-1 pt-2">
          {{ groupLabel(i) }}
        </p>

        <div class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
          <div class="p-3 space-y-2">
            <!-- Code + copy -->
            <div class="flex items-center gap-2">
              <span :class="['font-mono text-[15px] font-semibold break-all flex-1',
                             hh.matched_code ? 'text-[#206bc4]' : 'text-[#1d273b]']">
                {{ hh.unique_code }}
              </span>
              <button type="button" @click="copy(hh.unique_code)"
                :class="['shrink-0 h-10 px-3 rounded-md text-[13px] font-semibold inline-flex items-center gap-1.5 transition',
                         copied === hh.unique_code ? 'bg-emerald-600 text-white' : 'bg-[#206bc4]/10 text-[#206bc4] active:bg-[#206bc4]/20']">
                <i :class="copied === hh.unique_code ? 'fa-solid fa-check' : 'fa-regular fa-copy'"></i>
                {{ copied === hh.unique_code ? 'कपी भयो' : 'कपी' }}
              </button>
            </div>

            <!-- Head, phone, place -->
            <div class="text-[13px] text-[#1d273b] space-y-1">
              <p class="flex items-center gap-2">
                <i class="fa-solid fa-user-tie w-4 text-center text-[#9aa0ac]"></i>
                <span class="font-medium">{{ hh.head_name || '—' }}</span>
              </p>
              <p v-if="hh.phone" class="flex items-center gap-2">
                <i class="fa-solid fa-phone w-4 text-center text-[#9aa0ac]"></i>
                <a :href="'tel:' + hh.phone"
                   :class="['font-mono no-underline', hh.matched_phone ? 'text-[#206bc4] font-semibold' : 'text-[#1d273b]']">
                  {{ hh.phone }}
                </a>
              </p>
              <p class="flex items-center gap-2 text-[#626976]">
                <i class="fa-solid fa-location-dot w-4 text-center text-[#9aa0ac]"></i>
                वडा {{ hh.ward_number }}<template v-if="hh.tole"> · {{ hh.tole }}</template>
              </p>
            </div>
          </div>

          <!-- Members -->
          <button type="button" @click="toggle(hh.id)"
            class="w-full flex items-center justify-between px-3 py-2.5 border-t border-[#e6e7e9] bg-[#f9fafb] text-[12px] text-[#626976]">
            <span>
              <i class="fa-solid fa-users mr-1"></i> {{ hh.members.length }} सदस्य
              <template v-if="matchedMembers(hh).length">
                · <span class="text-[#206bc4] font-medium">{{ matchedMembers(hh).map(m => m.name).join(', ') }}</span>
              </template>
            </span>
            <i :class="['fa-solid', open[hh.id] ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
          </button>
          <ul v-if="open[hh.id]" class="divide-y divide-[#f0f1f3]">
            <li v-for="m in hh.members" :key="m.id"
              :class="['px-3 py-2 flex items-center gap-2 text-[13px]', m.matched ? 'bg-[#206bc4]/5' : '']">
              <span class="flex-1 min-w-0">
                <span :class="['block truncate', m.matched ? 'font-semibold text-[#206bc4]' : 'text-[#1d273b]',
                               m.is_deceased ? 'line-through text-[#9aa0ac]' : '']">
                  {{ m.name || '—' }}
                </span>
                <span class="block text-[11px] text-[#9aa0ac]">
                  {{ m.relation || '—' }}<template v-if="m.gender"> · {{ genderLabel[m.gender] ?? m.gender }}</template><template v-if="m.age != null"> · {{ m.age }} वर्ष</template>
                </span>
              </span>
              <span v-if="m.is_head" class="shrink-0 text-[10px] px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 font-semibold">घरमुली</span>
              <span v-if="m.is_deceased" class="shrink-0 text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 font-semibold">मृत</span>
            </li>
          </ul>
        </div>
      </template>

      <!-- Pager -->
      <div v-if="results.meta.last_page > 1" class="flex gap-2 pt-1 pb-6">
        <button type="button" :disabled="!results.links.prev" @click="go(results.links.prev)"
          class="h-11 flex-1 border border-[#dadcde] bg-white rounded-md text-[13px] disabled:opacity-40">
          <i class="fa-solid fa-chevron-left mr-1"></i> अघिल्लो
        </button>
        <button type="button" :disabled="!results.links.next" @click="go(results.links.next)"
          class="h-11 flex-1 border border-[#dadcde] bg-white rounded-md text-[13px] disabled:opacity-40">
          अर्को <i class="fa-solid fa-chevron-right ml-1"></i>
        </button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  results:   { type: Object,  default: null },
  filters:   { type: Object,  default: () => ({ q: '', ward_id: null }) },
  wards:     { type: Array,   default: () => [] },
  toles:     { type: Array,   default: () => [] },
  allWards:  { type: Boolean, default: false },
  minLength: { type: Number,  default: 3 },
})

const input   = ref(null)
const q       = ref(props.filters.q || '')
const wardId  = ref(props.filters.ward_id || null)
const toleId  = ref(props.filters.settlement_id || null)
const loading = ref(false)
const copied  = ref(null)
const open    = reactive({})

const genderLabel = { male: 'पुरुष', female: 'महिला', other: 'अन्य' }

const tooShort = computed(() => q.value.trim().length < props.minLength)

/** Toles of the chosen ward only; none until a ward is chosen. */
const wardToles = computed(() => wardId.value ? props.toles.filter(t => Number(t.ward_id) === Number(wardId.value)) : [])

// An empty box is fine once a tole is chosen: it lists that tole.
const canSearch = computed(() => !tooShort.value || (toleId.value && !q.value.trim()))

onMounted(() => { if (!props.results) input.value?.focus() })

function visit(url, data) {
  router.get(url, data, {
    preserveState: true,
    onStart:  () => { loading.value = true },
    onFinish: () => { loading.value = false },
    onSuccess: () => {
      // Open the cards whose match is a member, so the matched name is in view.
      Object.keys(open).forEach(k => delete open[k])
      for (const hh of props.results?.data ?? []) {
        if (hh.members.some(m => m.matched) && hh.members.length <= 12) open[hh.id] = true
      }
      window.scrollTo({ top: 0 })
    },
  })
}

function search() {
  if (!canSearch.value) return
  input.value?.blur()   // closes the phone keyboard so the results are visible
  const data = { q: q.value.trim() }
  if (wardId.value) data.ward_id = wardId.value
  if (toleId.value) data.settlement_id = toleId.value
  visit('/member-search', data)
}

function go(url) { if (url) visit(url, {}) }

function toggle(id) { open[id] = !open[id] }

function matchedMembers(hh) { return hh.members.filter(m => m.matched) }

/** A heading whenever the ward or tole changes from the previous card. */
function groupLabel(i) {
  const list = props.results.data
  const cur = list[i], prev = list[i - 1]
  if (prev && prev.ward_number === cur.ward_number && prev.tole === cur.tole) return null
  return `वडा ${cur.ward_number}` + (cur.tole ? ` · ${cur.tole}` : ' · टोल नखुलेको')
}

async function copy(text) {
  try {
    await navigator.clipboard.writeText(text)
  } catch {
    // navigator.clipboard needs HTTPS and permission; fall back to the old way.
    const ta = document.createElement('textarea')
    ta.value = text
    ta.setAttribute('readonly', '')
    ta.style.position = 'fixed'
    ta.style.opacity = '0'
    document.body.appendChild(ta)
    ta.select()
    document.execCommand('copy')
    document.body.removeChild(ta)
  }
  copied.value = text
  setTimeout(() => { if (copied.value === text) copied.value = null }, 2000)
}
</script>
