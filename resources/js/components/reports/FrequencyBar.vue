<template>
  <div class="bg-white rounded-lg border border-[#e6e7e9] overflow-hidden">

    <!-- ── Header ── -->
    <div class="px-5 py-3.5 border-b border-[#e6e7e9] flex flex-wrap items-center justify-between gap-3">
      <div class="min-w-0">
        <h3 class="text-[14px] font-semibold text-[#1d273b] leading-snug">{{ title }}</h3>
        <p v-if="titleEn" class="text-[11px] text-[#9aa0ac] mt-0.5">{{ titleEn }}</p>
      </div>
      <div class="flex items-center gap-1.5 shrink-0">
        <!-- Sort: by count / by label -->
        <button @click="sortByLabel = !sortByLabel"
          :title="sortByLabel ? 'Sort by count' : 'Sort by label'"
          class="h-7 px-2 flex items-center gap-1 rounded border text-[11px] font-medium transition"
          :class="sortByLabel
            ? 'border-[#206bc4] bg-[#206bc4]/8 text-[#206bc4]'
            : 'border-[#e6e7e9] text-[#9aa0ac] hover:text-[#206bc4]'">
          <i class="fa-solid fa-arrow-down-a-z text-[10px]"></i>
          A–Z
        </button>
        <!-- Show more toggle -->
        <button v-if="data.length > INITIAL_LIMIT" @click="showAll = !showAll"
          class="h-7 px-2 flex items-center gap-1 rounded border border-[#e6e7e9]
                 text-[11px] text-[#9aa0ac] hover:text-[#206bc4] transition">
          <i :class="showAll ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'" class="text-[9px]"></i>
          {{ showAll ? t('reports.showLess') : `+${data.length - INITIAL_LIMIT}` }}
        </button>
        <!-- Export CSV -->
        <button @click="exportCsv"
          title="Export CSV"
          class="h-7 w-7 flex items-center justify-center rounded border border-[#e6e7e9]
                 text-[12px] text-[#9aa0ac] hover:text-emerald-600 hover:border-emerald-300 transition">
          <i class="fa-solid fa-download"></i>
        </button>
      </div>
    </div>

    <!-- ── No data ── -->
    <div v-if="!data.length" class="px-5 py-8 text-center text-[13px] text-[#9aa0ac]">
      <i class="fa-solid fa-inbox text-2xl block mb-2 opacity-40"></i>
      {{ t('reports.noData') }}
    </div>

    <!-- ── Bar rows ── -->
    <div v-else class="divide-y divide-[#f0f1f3]">
      <div v-for="(item, idx) in visibleItems" :key="item.key"
        class="px-5 py-2.5 flex items-center gap-3 hover:bg-[#fafbfc] transition">

        <!-- Rank -->
        <span class="w-5 text-center text-[11px] font-bold shrink-0"
          :class="idx === 0 ? 'text-amber-500' : idx === 1 ? 'text-slate-400' : idx === 2 ? 'text-amber-700' : 'text-[#c8ccd4]'">
          {{ idx + 1 }}
        </span>

        <!-- Label + bar -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between mb-1 gap-2">
            <span class="text-[13px] font-medium text-[#1d273b] truncate">{{ item.label }}</span>
            <div class="flex items-center gap-2 shrink-0">
              <span class="text-[12px] font-semibold text-[#1d273b]">{{ item.count.toLocaleString() }}</span>
              <span class="text-[11px] text-[#9aa0ac] w-[42px] text-right">{{ pct(item.count) }}</span>
            </div>
          </div>
          <!-- Bar track -->
          <div class="h-[6px] rounded-full bg-[#f0f1f3] overflow-hidden">
            <div class="h-full rounded-full transition-all duration-500"
              :style="`width:${barWidth(item.count)}%; background:${barColor(idx)}`"></div>
          </div>
        </div>
      </div>

      <!-- "Show more" foot row -->
      <div v-if="!showAll && data.length > INITIAL_LIMIT"
        class="px-5 py-2.5 text-center">
        <button @click="showAll = true"
          class="text-[12px] text-[#206bc4] hover:underline">
          {{ t('reports.showMore', { n: data.length - INITIAL_LIMIT }) }}
          <i class="fa-solid fa-chevron-down text-[9px] ml-1"></i>
        </button>
      </div>
    </div>

    <!-- ── Footer total ── -->
    <div v-if="data.length" class="px-5 py-2.5 border-t border-[#f0f1f3] bg-[#f8f9fb]
         flex items-center justify-between text-[12px]">
      <span class="text-[#9aa0ac] font-medium">{{ t('reports.grandTotal') }}</span>
      <span class="font-bold text-[#1d273b]">{{ grandTotal.toLocaleString() }}</span>
    </div>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useLocale } from '@/i18n/index.js'

const { t } = useLocale()

const props = defineProps({
  data:    { type: Array,  default: () => [] }, // [{ key, label, count }]
  title:   { type: String, default: '' },
  titleEn: { type: String, default: '' },
})

const INITIAL_LIMIT = 10
const showAll     = ref(false)
const sortByLabel = ref(false)

const COLORS = [
  '#206bc4', '#2fb344', '#f76707', '#ae3ec9', '#d63939',
  '#0ca678', '#f59f00', '#4299e1', '#e64980', '#74c0fc',
]

// Sorted items
const sortedData = computed(() => {
  const d = [...props.data]
  if (sortByLabel.value) {
    d.sort((a, b) => String(a.label).localeCompare(String(b.label), 'ne'))
  }
  return d // already sorted by count desc from server
})

const visibleItems = computed(() =>
  showAll.value ? sortedData.value : sortedData.value.slice(0, INITIAL_LIMIT)
)

const grandTotal = computed(() =>
  props.data.reduce((s, i) => s + (i.count ?? 0), 0)
)

const maxCount = computed(() =>
  Math.max(...props.data.map(i => i.count ?? 0), 1)
)

function barWidth(count) {
  return ((count / maxCount.value) * 100).toFixed(1)
}

function barColor(idx) {
  return COLORS[idx % COLORS.length]
}

function pct(count) {
  if (!grandTotal.value) return '—'
  return ((count / grandTotal.value) * 100).toFixed(1) + '%'
}

// ── CSV Export ────────────────────────────────────────────────────────────────
function exportCsv() {
  if (!props.data.length) return
  const rows = [['Label', 'Count', 'Percentage']]
  for (const item of props.data) {
    rows.push([`"${item.label}"`, item.count, pct(item.count)])
  }
  rows.push(['"Total"', grandTotal.value, '100%'])

  const blob = new Blob(['\uFEFF' + rows.map(r => r.join(',')).join('\n')],
    { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a   = document.createElement('a')
  a.href     = url
  a.download = (props.title || 'frequency') + '.csv'
  a.click()
  URL.revokeObjectURL(url)
}
</script>
