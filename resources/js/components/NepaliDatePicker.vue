<template>
  <div class="ndp-wrap relative" ref="container">

    <!-- ── Text input ── -->
    <div class="relative">
      <input
        type="text"
        :value="bsDisplay"
        :placeholder="placeholder || 'YYYY-MM-DD (बि.सं.)'"
        readonly
        @click="toggle"
        :class="[
          'w-full rounded-md border px-4 py-2.5 text-[14px] cursor-pointer pr-10',
          'focus:outline-none focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]',
          bsDisplay ? 'text-[#1d273b]' : 'text-[#9aa0ac]',
          inputClass || 'border-[#e6e7e9]',
        ]"
      />
      <button type="button" @click="toggle" tabindex="-1"
        class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9aa0ac] hover:text-[#206bc4] transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
          <line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
      </button>
    </div>

    <!-- AD subtitle -->
    <p v-if="adDisplay" class="text-[11px] text-[#9aa0ac] mt-0.5 pl-0.5">
      <span class="font-medium text-[#206bc4]">ई.सं.</span> {{ adDisplay }}
    </p>

    <!-- ── Dropdown ── -->
    <Teleport to="body">
      <div v-if="open" ref="dropdown"
        class="fixed z-[9999] bg-white rounded-xl shadow-2xl border border-[#e6e7e9] w-72 select-none overflow-hidden"
        :style="dropStyle"
        @mousedown.prevent>

        <!-- ════ HEADER ════ -->
        <div class="flex items-center justify-between px-3 py-2.5 border-b border-[#e6e7e9] bg-[#f8fafc]">
          <button type="button" @click="stepBack"
            class="w-7 h-7 rounded-lg flex items-center justify-center text-[#626976] hover:bg-[#206bc4]/10 hover:text-[#206bc4] transition-colors font-bold text-lg leading-none">
            ‹
          </button>

          <div class="flex items-center gap-1">
            <!-- Month label (day & month modes) -->
            <button v-if="mode !== 'year'" type="button" @click="toggleMode('month')"
              :class="[
                'px-2 py-1 rounded-md text-[13px] font-bold transition-colors',
                mode === 'month'
                  ? 'bg-[#206bc4] text-white'
                  : 'text-[#1d273b] hover:bg-[#206bc4]/10 hover:text-[#206bc4]',
              ]">
              {{ MONTHS[viewMonth] }}
            </button>

            <!-- Year label (all modes) -->
            <button type="button" @click="toggleMode('year')"
              :class="[
                'px-2 py-1 rounded-md text-[13px] font-bold transition-colors',
                mode === 'year'
                  ? 'bg-[#206bc4] text-white'
                  : 'text-[#1d273b] hover:bg-[#206bc4]/10 hover:text-[#206bc4]',
              ]">
              {{ mode === 'year' ? yearRangeLabel : viewYear }}
            </button>
          </div>

          <button type="button" @click="stepForward"
            class="w-7 h-7 rounded-lg flex items-center justify-center text-[#626976] hover:bg-[#206bc4]/10 hover:text-[#206bc4] transition-colors font-bold text-lg leading-none">
            ›
          </button>
        </div>

        <!-- ════ DAY VIEW ════ -->
        <template v-if="mode === 'day'">
          <div class="grid grid-cols-7 text-center px-2 pt-2 pb-0.5">
            <span v-for="h in DAY_HDRS" :key="h" class="text-[11px] font-bold text-[#9aa0ac] py-1">{{ h }}</span>
          </div>
          <div class="grid grid-cols-7 px-2 pb-2">
            <div v-for="i in startOffset" :key="'b'+i" />
            <button
              v-for="d in daysInMonth" :key="d"
              type="button"
              @click="pick(d)"
              :class="[
                'h-8 w-8 mx-auto rounded-full text-[13px] flex items-center justify-center transition-colors',
                isSelected(d)
                  ? 'bg-[#206bc4] text-white font-bold shadow'
                  : isToday(d)
                    ? 'border-2 border-[#206bc4] text-[#206bc4] font-semibold hover:bg-[#206bc4]/10'
                    : 'text-[#1d273b] hover:bg-[#206bc4]/10 hover:text-[#206bc4]',
              ]">{{ d }}</button>
          </div>
          <div class="flex items-center justify-between px-3 py-2 border-t border-[#e6e7e9] bg-[#f8fafc]">
            <button type="button" @click="pickToday"
              class="text-[12px] font-semibold text-[#206bc4] hover:underline">आज</button>
            <button type="button" @click="clear"
              class="text-[12px] font-medium text-[#9aa0ac] hover:text-red-500">मेटाउनुहोस्</button>
          </div>
        </template>

        <!-- ════ MONTH VIEW ════ -->
        <template v-else-if="mode === 'month'">
          <div class="grid grid-cols-3 gap-1.5 p-3">
            <button
              v-for="(m, i) in MONTHS" :key="i"
              type="button"
              @click="pickMonth(i)"
              :class="[
                'py-2.5 rounded-lg text-[13px] font-semibold transition-colors',
                viewMonth === i
                  ? 'bg-[#206bc4] text-white shadow'
                  : (viewYear === todayYear && i === todayMonth)
                    ? 'border-2 border-[#206bc4] text-[#206bc4] hover:bg-[#206bc4]/10'
                    : 'text-[#1d273b] bg-[#f8fafc] hover:bg-[#206bc4]/10 hover:text-[#206bc4]',
              ]">{{ m }}</button>
          </div>
        </template>

        <!-- ════ YEAR VIEW ════ -->
        <template v-else-if="mode === 'year'">
          <div class="grid grid-cols-4 gap-1.5 p-3 max-h-56 overflow-y-auto">
            <button
              v-for="y in YEAR_RANGE" :key="y"
              :ref="el => { if (y === viewYear) selectedYearEl = el }"
              type="button"
              @click="pickYear(y)"
              :class="[
                'py-2 rounded-lg text-[13px] font-semibold transition-colors',
                y === viewYear
                  ? 'bg-[#206bc4] text-white shadow'
                  : y === todayYear
                    ? 'border-2 border-[#206bc4] text-[#206bc4] hover:bg-[#206bc4]/10'
                    : 'text-[#1d273b] bg-[#f8fafc] hover:bg-[#206bc4]/10 hover:text-[#206bc4]',
              ]">{{ y }}</button>
          </div>
        </template>

      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { toGreg, daysInMonth as bsDaysInMonth } from 'bikram-sambat'

// ── Props / Emits ──────────────────────────────────────────────────────────
const props = defineProps({
  modelValue:  { type: String, default: '' },   // stored as AD "YYYY-MM-DD"
  placeholder: { type: String, default: '' },
  inputClass:  { type: String, default: '' },
  minYear:     { type: Number, default: 1970 }, // BS 1970 = earliest bikram-sambat supports
  maxYear:     { type: Number, default: 2090 },
})
const emit = defineEmits(['update:modelValue'])

// ── Constants ──────────────────────────────────────────────────────────────
const MONTHS   = ['बैशाख','जेठ','असार','साउन','भदौ','असोज','कात्तिक','मंसिर','पुस','माघ','फागुन','चैत']
const DAY_HDRS = ['आ','सो','मं','बु','बि','शु','श']

const YEAR_RANGE = computed(() => {
  const arr = []
  for (let y = props.maxYear; y >= props.minYear; y--) arr.push(y)  // newest first
  return arr
})

// ── State ──────────────────────────────────────────────────────────────────
const open           = ref(false)
const mode           = ref('day')
const container      = ref(null)
const dropdown       = ref(null)
const dropStyle      = ref({})
const selectedYearEl = ref(null)

const viewYear  = ref(2081)
const viewMonth = ref(0)   // 0-indexed (0 = Baisakh)

const yearRangeLabel = computed(() => {
  const decade = Math.floor(viewYear.value / 10) * 10
  return `${decade}–${decade + 9}`
})

// ── Conversion (bikram-sambat uses 1-indexed months) ──────────────────────
// toBik (AD→BS) is broken for 2026+ in bikram-sambat 1.8.0.
// We derive it by scanning BS months using toGreg (BS→AD, which works correctly).
function adToBS(adStr) {
  if (!adStr) return null
  const adDate = new Date(adStr + 'T00:00:00')
  if (isNaN(adDate)) return null
  const adYear = adDate.getFullYear()

  // BS year ≈ AD year + 56 or 57 (try both to cover New Year boundary)
  for (const bsYear of [adYear + 57, adYear + 56, adYear + 55]) {
    // Scan months 12 → 1 (reverse so we find the latest match first)
    for (let mo = 12; mo >= 1; mo--) {
      try {
        const g = toGreg(bsYear, mo, 1)
        const firstAd = new Date(`${g.year}-${String(g.month).padStart(2,'0')}-${String(g.day).padStart(2,'0')}T00:00:00`)
        if (firstAd <= adDate) {
          const dayOffset = Math.round((adDate - firstAd) / 86400000)
          const dim = bsDaysInMonth(bsYear, mo)
          if (dayOffset < dim) {
            return { year: bsYear, month: mo, day: dayOffset + 1 }
          }
        }
      } catch { /* skip invalid combos */ }
    }
  }
  return null
}

function bsToAd(bsYear, bsMonth0, bsDay) {   // bsMonth0 is 0-indexed
  try {
    const ad = toGreg(bsYear, bsMonth0 + 1, bsDay)
    return `${ad.year}-${String(ad.month).padStart(2,'0')}-${String(ad.day).padStart(2,'0')}`
  } catch { return '' }
}

// ── Display ────────────────────────────────────────────────────────────────
const bsDisplay = computed(() => {
  const bs = adToBS(props.modelValue)
  if (!bs) return ''
  return `${bs.year}-${String(bs.month).padStart(2,'0')}-${String(bs.day).padStart(2,'0')}`
})

const adDisplay = computed(() => {
  if (!props.modelValue) return ''
  const d = new Date(props.modelValue + 'T00:00:00')
  if (isNaN(d)) return ''
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
})

// ── Calendar grid ──────────────────────────────────────────────────────────
const daysInMonth = computed(() => {
  try { return bsDaysInMonth(viewYear.value, viewMonth.value + 1) }
  catch { return 30 }
})

const startOffset = computed(() => {
  const adStr = bsToAd(viewYear.value, viewMonth.value, 1)
  if (!adStr) return 0
  return new Date(adStr + 'T00:00:00').getDay()   // 0=Sun … 6=Sat
})

// ── Today ─────────────────────────────────────────────────────────────────
function todayAdStr() {
  const t = new Date()
  return `${t.getFullYear()}-${String(t.getMonth()+1).padStart(2,'0')}-${String(t.getDate()).padStart(2,'0')}`
}

// Derive today's BS by converting today's AD string (avoids direct toBik call)
const _todayBs   = adToBS(todayAdStr()) ?? { year: 2082, month: 1, day: 1 }
const todayYear  = _todayBs.year
const todayMonth = _todayBs.month - 1  // 0-indexed

function isToday(d) {
  // Compare via AD to avoid BS→AD library round-trip error
  return bsToAd(viewYear.value, viewMonth.value, d) === todayAdStr()
}

function isSelected(d) {
  const bs = adToBS(props.modelValue)
  if (!bs) return false
  return bs.year === viewYear.value && (bs.month - 1) === viewMonth.value && bs.day === d
}

// ── Navigation ─────────────────────────────────────────────────────────────
function stepBack() {
  if (mode.value === 'day') {
    let m = viewMonth.value - 1, y = viewYear.value
    if (m < 0) { m = 11; y-- }
    if (y >= props.minYear) { viewYear.value = y; viewMonth.value = m }
  } else if (mode.value === 'month') {
    if (viewYear.value > props.minYear) viewYear.value--
  } else {
    const target = Math.max(props.minYear, viewYear.value - 10)
    viewYear.value = target
    nextTick(scrollToYear)
  }
}

function stepForward() {
  if (mode.value === 'day') {
    let m = viewMonth.value + 1, y = viewYear.value
    if (m > 11) { m = 0; y++ }
    if (y <= props.maxYear) { viewYear.value = y; viewMonth.value = m }
  } else if (mode.value === 'month') {
    if (viewYear.value < props.maxYear) viewYear.value++
  } else {
    const target = Math.min(props.maxYear, viewYear.value + 10)
    viewYear.value = target
    nextTick(scrollToYear)
  }
}

// ── Mode switching ─────────────────────────────────────────────────────────
function toggleMode(target) {
  mode.value = mode.value === target ? 'day' : target
  if (mode.value === 'year') nextTick(scrollToYear)
}

function pickMonth(i) {
  viewMonth.value = i
  mode.value = 'day'
}

function pickYear(y) {
  viewYear.value = y
  mode.value = 'month'
}

function scrollToYear() {
  const el = Array.isArray(selectedYearEl.value)
    ? selectedYearEl.value[0] : selectedYearEl.value
  el?.scrollIntoView?.({ block: 'center', behavior: 'smooth' })
}

// ── Pick / Today / Clear ───────────────────────────────────────────────────
function pick(day) {
  emit('update:modelValue', bsToAd(viewYear.value, viewMonth.value, day))
  open.value = false
}

function pickToday() {
  const adStr = todayAdStr()   // use AD directly — no BS round-trip
  emit('update:modelValue', adStr)
  const bs = adToBS(adStr)
  if (bs) { viewYear.value = bs.year; viewMonth.value = bs.month - 1 }
  open.value = false
}

function clear() {
  emit('update:modelValue', '')
  open.value = false
}

// ── Open / Position ────────────────────────────────────────────────────────
function toggle() {
  if (open.value) { open.value = false; return }

  const bs = adToBS(props.modelValue)
  viewYear.value  = bs ? bs.year       : todayYear
  viewMonth.value = bs ? bs.month - 1  : todayMonth
  mode.value = 'day'

  open.value = true
  setTimeout(positionDropdown, 0)
}

function positionDropdown() {
  if (!container.value) return
  const rect  = container.value.getBoundingClientRect()
  const ddH   = 350
  const top   = rect.bottom + window.scrollY
  const left  = rect.left   + window.scrollX

  if ((window.innerHeight - rect.bottom) < ddH && rect.top > ddH) {
    dropStyle.value = { top: `${rect.top + window.scrollY - ddH - 4}px`, left: `${left}px`, width: `${Math.max(rect.width, 288)}px` }
  } else {
    dropStyle.value = { top: `${top + 4}px`, left: `${left}px`, width: `${Math.max(rect.width, 288)}px` }
  }
}

function onClickOutside(e) {
  if (!open.value) return
  if (container.value?.contains(e.target) || dropdown.value?.contains(e.target)) return
  open.value = false
}

onMounted(() => {
  document.addEventListener('mousedown', onClickOutside)
  window.addEventListener('scroll', () => { if (open.value) positionDropdown() }, true)
  window.addEventListener('resize', positionDropdown)
})
onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onClickOutside)
  window.removeEventListener('scroll', positionDropdown, true)
  window.removeEventListener('resize', positionDropdown)
})
</script>
