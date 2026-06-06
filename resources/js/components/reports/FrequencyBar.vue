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
        <!-- Export PNG -->
        <button @click="exportPng"
          title="Export PNG"
          class="h-7 w-7 flex items-center justify-center rounded border border-[#e6e7e9]
                 text-[12px] text-[#9aa0ac] hover:text-violet-600 hover:border-violet-300 transition">
          <i class="fa-solid fa-image"></i>
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

    <!-- ── Municipal avg legend (shown when comparison data present) ── -->
    <div v-if="comparison.length" class="px-5 py-1.5 bg-orange-50 border-b border-orange-100
         flex items-center gap-2 text-[11px] text-orange-700">
      <span class="inline-block w-3 h-0.5 bg-orange-400 rounded-full"></span>
      <span class="inline-block w-[2px] h-3 bg-orange-400 rounded-full"></span>
      नगरपालिका औसत (Municipal average)
    </div>

    <!-- ── No data ── -->
    <div v-if="!data.length" class="px-5 py-8 text-center text-[13px] text-[#9aa0ac]">
      <i class="fa-solid fa-inbox text-2xl block mb-2 opacity-40"></i>
      {{ t('reports.noData') }}
    </div>

    <!-- ── Bar rows ── -->
    <div v-else class="divide-y divide-[#f0f1f3]">
      <div v-for="(item, idx) in visibleItems" :key="item.key"
        class="px-5 py-3 flex items-center gap-3 hover:bg-[#fafbfc] transition">

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
              <!-- Delta badge vs municipal average -->
              <span v-if="compDelta(item.key, item.count) !== null"
                class="text-[10px] font-bold px-1 py-0.5 rounded leading-none"
                :class="Number(compDelta(item.key, item.count)) >= 0
                  ? 'text-emerald-600 bg-emerald-50'
                  : 'text-red-500 bg-red-50'"
                :title="`vs municipal average`">
                {{ Number(compDelta(item.key, item.count)) >= 0 ? '↑' : '↓' }}{{ Math.abs(Number(compDelta(item.key, item.count))).toFixed(1) }}%
              </span>
              <span class="text-[12px] text-[#9aa0ac] w-[46px] text-right">{{ pct(item.count) }}</span>
            </div>
          </div>
          <!-- Bar track + comparison marker -->
          <div class="relative">
            <div class="h-[8px] rounded-full bg-[#f0f1f3] overflow-hidden">
              <div class="h-full rounded-full transition-all duration-500"
                :style="`width:${barWidth(item.count)}%; background:${barColor(idx)}`"></div>
            </div>
            <!-- Municipal average tick line -->
            <div v-if="compBarWidthPct(item.key) !== null"
              class="absolute top-0 bottom-0 w-[2px] bg-orange-400 rounded-full pointer-events-none"
              :style="`left:calc(${compBarWidthPct(item.key)}% - 1px)`"></div>
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
  data:       { type: Array,  default: () => [] }, // [{ key, label, count }]
  title:      { type: String, default: '' },
  titleEn:    { type: String, default: '' },
  // Optional municipal-average comparison data — same format as data
  comparison: { type: Array,  default: () => [] }, // [{ key, count }]
})

const INITIAL_LIMIT = 10
const showAll     = ref(false)
const sortByLabel = ref(false)

const COLORS = [
  '#206bc4', '#2fb344', '#f76707', '#ae3ec9', '#d63939',
  '#0ca678', '#f59f00', '#4299e1', '#e64980', '#74c0fc',
]

// ── Sorted items ──────────────────────────────────────────────────────────────
const sortedData = computed(() => {
  const d = [...props.data]
  if (sortByLabel.value) {
    d.sort((a, b) => String(a.label).localeCompare(String(b.label), 'ne'))
  }
  return d
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

// ── Comparison (municipal average) ───────────────────────────────────────────
const compTotal    = computed(() => props.comparison.reduce((s, i) => s + (i.count ?? 0), 0))
const compMaxCount = computed(() => Math.max(...props.comparison.map(i => i.count ?? 0), 1))
const compMap      = computed(() => Object.fromEntries(props.comparison.map(i => [i.key, i.count ?? 0])))

// Position of the comparison tick as % of bar track width (uses same relative scale as the main bars)
function compBarWidthPct(key) {
  if (!props.comparison.length) return null
  const cnt = compMap.value[key] ?? null
  if (cnt === null) return null
  return ((cnt / compMaxCount.value) * 100).toFixed(1)
}

// Percentage-point difference between ward share and municipal share
function compDelta(key, count) {
  if (!props.comparison.length || !compTotal.value || !grandTotal.value) return null
  const wardShare = count / grandTotal.value
  const compShare = (compMap.value[key] ?? 0) / compTotal.value
  return ((wardShare - compShare) * 100).toFixed(1)
}

// ── PNG Export ────────────────────────────────────────────────────────────────
function exportPng() {
  if (!props.data.length) return

  const DPR    = Math.min(window.devicePixelRatio || 1, 2)
  const W      = 640
  const ROW_H  = 42
  const PAD_T  = 60
  const PAD_B  = 36
  const PAD_L  = 16
  const PAD_R  = 16
  const RANK_W = 20
  const LBL_W  = 160
  const META_W = 90
  const BAR_X  = PAD_L + RANK_W + 8 + LBL_W + 8
  const BAR_W  = W - BAR_X - META_W - PAD_R
  const items  = sortedData.value
  const H      = PAD_T + items.length * ROW_H + PAD_B

  const canvas = document.createElement('canvas')
  canvas.width  = W * DPR
  canvas.height = H * DPR
  const ctx    = canvas.getContext('2d')
  ctx.scale(DPR, DPR)

  // Background
  ctx.fillStyle = '#ffffff'
  ctx.fillRect(0, 0, W, H)

  // Title
  ctx.fillStyle = '#1d273b'
  ctx.font      = 'bold 14px sans-serif'
  ctx.fillText(props.title || '', PAD_L, 22)
  if (props.titleEn) {
    ctx.fillStyle = '#9aa0ac'
    ctx.font      = '11px sans-serif'
    ctx.fillText(props.titleEn, PAD_L, 38)
  }

  // Header divider
  ctx.strokeStyle = '#e6e7e9'
  ctx.lineWidth   = 1
  ctx.beginPath(); ctx.moveTo(0, PAD_T - 6); ctx.lineTo(W, PAD_T - 6); ctx.stroke()

  // Bar rows
  items.forEach((item, idx) => {
    const y = PAD_T + idx * ROW_H

    // Rank badge colour
    ctx.fillStyle = idx === 0 ? '#f59f00' : idx === 1 ? '#adb5bd' : idx === 2 ? '#c36b00' : '#c8ccd4'
    ctx.font      = 'bold 10px sans-serif'
    ctx.textAlign = 'center'
    ctx.fillText(String(idx + 1), PAD_L + RANK_W / 2, y + 22)
    ctx.textAlign = 'left'

    // Label (truncate to fit)
    ctx.fillStyle = '#1d273b'
    ctx.font      = '12px sans-serif'
    let lbl = String(item.label)
    while (ctx.measureText(lbl).width > LBL_W - 4 && lbl.length > 3) lbl = lbl.slice(0, -1)
    if (lbl !== String(item.label)) lbl += '…'
    ctx.fillText(lbl, PAD_L + RANK_W + 8, y + 20)

    // Bar background
    ctx.fillStyle = '#f0f1f3'
    ctx.fillRect(BAR_X, y + 15, BAR_W, 8)

    // Bar fill
    const bw = (item.count / maxCount.value) * BAR_W
    ctx.fillStyle = barColor(idx)
    ctx.fillRect(BAR_X, y + 15, bw, 8)

    // Comparison tick
    const cPct = compBarWidthPct(item.key)
    if (cPct !== null) {
      const tx = BAR_X + (Number(cPct) / 100) * BAR_W
      ctx.fillStyle   = '#f76707'
      ctx.fillRect(tx - 1, y + 13, 2, 12)
    }

    // Row divider
    if (idx < items.length - 1) {
      ctx.strokeStyle = '#f0f1f3'
      ctx.lineWidth   = 1
      ctx.beginPath(); ctx.moveTo(0, y + ROW_H); ctx.lineTo(W, y + ROW_H); ctx.stroke()
    }

    // Count + pct (right side)
    ctx.fillStyle = '#1d273b'
    ctx.font      = 'bold 11px sans-serif'
    ctx.textAlign = 'right'
    ctx.fillText(item.count.toLocaleString(), W - PAD_R, y + 18)
    ctx.fillStyle = '#9aa0ac'
    ctx.font      = '10px sans-serif'
    ctx.fillText(pct(item.count), W - PAD_R, y + 30)
    ctx.textAlign = 'left'
  })

  // Footer
  ctx.fillStyle = '#f8f9fb'
  ctx.fillRect(0, H - PAD_B, W, PAD_B)
  ctx.strokeStyle = '#f0f1f3'
  ctx.lineWidth   = 1
  ctx.beginPath(); ctx.moveTo(0, H - PAD_B); ctx.lineTo(W, H - PAD_B); ctx.stroke()
  ctx.fillStyle = '#9aa0ac'
  ctx.font      = '11px sans-serif'
  ctx.fillText(t('reports.grandTotal'), PAD_L, H - 10)
  ctx.fillStyle = '#1d273b'
  ctx.font      = 'bold 12px sans-serif'
  ctx.textAlign = 'right'
  ctx.fillText(grandTotal.value.toLocaleString(), W - PAD_R, H - 10)

  canvas.toBlob(blob => {
    const url = URL.createObjectURL(blob)
    const a   = document.createElement('a')
    a.href     = url
    a.download = (props.title || 'frequency') + '.png'
    a.click()
    URL.revokeObjectURL(url)
  })
}

// ── CSV Export ────────────────────────────────────────────────────────────────
function exportCsv() {
  if (!props.data.length) return
  const rows = [['Label', 'Count', 'Percentage']]
  for (const item of props.data) {
    rows.push([`"${item.label}"`, item.count, pct(item.count)])
  }
  rows.push(['"Total"', grandTotal.value, '100%'])

  const blob = new Blob(['﻿' + rows.map(r => r.join(',')).join('\n')],
    { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a   = document.createElement('a')
  a.href     = url
  a.download = (props.title || 'frequency') + '.csv'
  a.click()
  URL.revokeObjectURL(url)
}
</script>
