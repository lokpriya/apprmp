<template>
  <div class="bg-white rounded-lg border border-[#e6e7e9] overflow-hidden">

    <!-- ── Header ── -->
    <div class="px-5 py-3.5 border-b border-[#e6e7e9] flex flex-wrap items-center justify-between gap-3">
      <div class="min-w-0">
        <h3 class="text-[14px] font-semibold text-[#1d273b] leading-snug">{{ title }}</h3>
        <p v-if="titleEn" class="text-[11px] text-[#9aa0ac] mt-0.5">{{ titleEn }}</p>
      </div>
      <div class="flex items-center gap-1.5 shrink-0">
        <!-- Toggle chart -->
        <button @click="chartVisible = !chartVisible"
          :title="chartVisible ? 'Hide chart' : 'Show chart'"
          class="h-7 w-7 flex items-center justify-center rounded border text-[12px] transition"
          :class="chartVisible
            ? 'border-[#206bc4] bg-[#206bc4]/8 text-[#206bc4]'
            : 'border-[#e6e7e9] text-[#9aa0ac] hover:text-[#206bc4]'">
          <i class="fa-solid fa-chart-bar"></i>
        </button>
        <!-- Toggle % -->
        <button @click="showPct = !showPct"
          :title="showPct ? 'Show counts' : 'Show percentages'"
          class="h-7 px-2.5 flex items-center rounded border text-[12px] font-semibold transition"
          :class="showPct
            ? 'border-[#206bc4] bg-[#206bc4]/8 text-[#206bc4]'
            : 'border-[#e6e7e9] text-[#9aa0ac] hover:text-[#206bc4]'">
          %
        </button>
        <!-- Heatmap toggle -->
        <button @click="heatmap = !heatmap"
          title="Toggle heatmap"
          class="h-7 w-7 flex items-center justify-center rounded border text-[12px] transition"
          :class="heatmap
            ? 'border-orange-400 bg-orange-50 text-orange-500'
            : 'border-[#e6e7e9] text-[#9aa0ac] hover:text-orange-400'">
          <i class="fa-solid fa-fire-flame-simple"></i>
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

    <!-- ── Bar Chart ── -->
    <div v-if="chartVisible && hasData" class="px-5 pt-4 pb-2 border-b border-[#f0f1f3]">
      <div v-if="chartType === 'pyramid'" class="relative" style="height:300px;">
        <canvas ref="chartCanvas"></canvas>
      </div>
      <div v-else class="relative" style="height:220px;">
        <canvas ref="chartCanvas"></canvas>
      </div>
    </div>

    <!-- ── No data ── -->
    <div v-if="!hasData" class="px-5 py-10 text-center text-[13px] text-[#9aa0ac]">
      <i class="fa-solid fa-inbox text-2xl block mb-2 opacity-40"></i>
      {{ t('reports.noData') }}
    </div>

    <!-- ── Table ── -->
    <div v-else class="overflow-x-auto">
      <table class="w-full text-[13px] border-collapse">
        <thead>
          <tr class="bg-[#f8f9fb]">
            <!-- Row label header -->
            <th class="sticky left-0 z-10 bg-[#f8f9fb] text-left px-4 py-2.5
                       text-[11px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac]
                       border-b border-r border-[#e6e7e9] min-w-[140px]">
              {{ rowLabel }}
            </th>
            <!-- Column headers -->
            <th v-for="col in data.cols" :key="col"
              @click="toggleSort(col)"
              class="px-3 py-2.5 text-center text-[11px] font-semibold uppercase tracking-[0.08em]
                     border-b border-r border-[#e6e7e9] cursor-pointer whitespace-nowrap
                     select-none hover:bg-[#eef2f8] transition min-w-[72px]"
              :class="sortCol === col ? 'text-[#206bc4] bg-[#eef2f8]' : 'text-[#626976]'">
              <span class="inline-flex items-center gap-1">
                {{ data.col_labels[col] ?? col }}
                <i v-if="sortCol === col"
                  :class="sortDir === 'desc'
                    ? 'fa-solid fa-sort-down text-[10px]'
                    : 'fa-solid fa-sort-up text-[10px]'"
                  class="text-[#206bc4]"></i>
                <i v-else class="fa-solid fa-sort text-[9px] opacity-30"></i>
              </span>
            </th>
            <!-- Total header -->
            <th class="px-3 py-2.5 text-center text-[11px] font-semibold uppercase
                       tracking-[0.08em] text-[#9aa0ac] border-b border-[#e6e7e9]
                       whitespace-nowrap min-w-[72px]"
              @click="toggleSort('__total__')"
              :class="sortCol === '__total__' ? 'text-[#206bc4] bg-[#eef2f8] cursor-pointer' : 'cursor-pointer hover:bg-[#eef2f8]'">
              <span class="inline-flex items-center gap-1">
                {{ t('reports.rowTotal') }}
                <i v-if="sortCol === '__total__'"
                  :class="sortDir === 'desc' ? 'fa-solid fa-sort-down text-[10px]' : 'fa-solid fa-sort-up text-[10px]'"
                  class="text-[#206bc4]"></i>
              </span>
            </th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="(row, idx) in sortedRows" :key="row"
            class="border-b border-[#f0f1f3] hover:bg-[#fafbfc] transition group">
            <!-- Row label -->
            <td class="sticky left-0 z-10 bg-white group-hover:bg-[#fafbfc]
                       px-4 py-2 font-medium text-[#1d273b] border-r border-[#e6e7e9]
                       whitespace-nowrap transition">
              {{ data.row_labels[row] ?? row }}
            </td>
            <!-- Data cells -->
            <td v-for="col in data.cols" :key="col"
              class="px-3 py-2 text-center border-r border-[#f0f1f3] transition"
              :style="heatmap ? heatStyle(row, col) : ''">
              <span :class="cellClass(row, col)">
                {{ displayCell(row, col) }}
              </span>
            </td>
            <!-- Row total -->
            <td class="px-3 py-2 text-center font-semibold text-[#1d273b]
                       bg-[#f8f9fb] border-l border-[#e6e7e9]">
              {{ showPct
                  ? pctFmt(data.row_totals[row], data.grand_total)
                  : fmt(data.row_totals[row]) }}
            </td>
          </tr>
        </tbody>

        <tfoot>
          <tr class="bg-[#f0f4fa] font-semibold border-t-2 border-[#dde3ed]">
            <td class="sticky left-0 z-10 bg-[#f0f4fa] px-4 py-2.5
                       text-[12px] font-bold text-[#1d273b] border-r border-[#dde3ed]
                       uppercase tracking-[0.06em]">
              {{ t('reports.grandTotal') }}
            </td>
            <td v-for="col in data.cols" :key="col"
              class="px-3 py-2.5 text-center text-[12px] font-bold text-[#1d273b]
                     border-r border-[#dde3ed]">
              {{ showPct
                  ? pctFmt(data.col_totals[col], data.grand_total)
                  : fmt(data.col_totals[col]) }}
            </td>
            <td class="px-3 py-2.5 text-center text-[12px] font-bold text-[#206bc4]">
              {{ fmt(data.grand_total) }}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useLocale } from '@/i18n/index.js'
import Chart from 'chart.js/auto'

const { t } = useLocale()

const props = defineProps({
  data: {
    type: Object,
    default: () => ({
      rows: [], row_labels: {}, cols: [], col_labels: {},
      matrix: {}, row_totals: {}, col_totals: {}, grand_total: 0,
    }),
  },
  title:     { type: String, default: '' },
  titleEn:   { type: String, default: '' },
  rowLabel:  { type: String, default: '' },
  chartType: { type: String, default: 'bar' }, // 'bar' | 'pyramid'
})

// ── UI state ──────────────────────────────────────────────────────────────────
const chartVisible = ref(true)
const showPct      = ref(false)
const heatmap      = ref(false)
const sortCol      = ref(null)      // null = original order
const sortDir      = ref('desc')    // 'asc' | 'desc'

const chartCanvas  = ref(null)
let   chartInstance = null

// ── Derived ───────────────────────────────────────────────────────────────────
const hasData = computed(() =>
  props.data.rows?.length > 0 && props.data.cols?.length > 0
)

// Sorted rows
const sortedRows = computed(() => {
  if (!hasData.value) return []
  const rows = [...props.data.rows]
  if (!sortCol.value) return rows

  return rows.sort((a, b) => {
    const va = sortCol.value === '__total__'
      ? (props.data.row_totals[a] ?? 0)
      : (props.data.matrix[a]?.[sortCol.value] ?? 0)
    const vb = sortCol.value === '__total__'
      ? (props.data.row_totals[b] ?? 0)
      : (props.data.matrix[b]?.[sortCol.value] ?? 0)
    return sortDir.value === 'desc' ? vb - va : va - vb
  })
})

// Column max per col (for heatmap)
const colMax = computed(() => {
  const maxes = {}
  for (const col of (props.data.cols ?? [])) {
    maxes[col] = Math.max(...(props.data.rows ?? []).map(r => props.data.matrix[r]?.[col] ?? 0), 1)
  }
  return maxes
})

// ── Sort toggle ───────────────────────────────────────────────────────────────
function toggleSort(col) {
  if (sortCol.value === col) {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortCol.value = col
    sortDir.value = 'desc'
  }
}

// ── Cell display ──────────────────────────────────────────────────────────────
function fmt(n) {
  if (n == null || n === '') return '—'
  return Number(n).toLocaleString()
}

function pctFmt(val, total) {
  if (!total) return '—'
  return ((val / total) * 100).toFixed(1) + '%'
}

function displayCell(row, col) {
  const v = props.data.matrix[row]?.[col] ?? 0
  if (showPct.value) return pctFmt(v, props.data.row_totals[row])
  return v ? fmt(v) : '—'
}

function cellClass(row, col) {
  const v = props.data.matrix[row]?.[col] ?? 0
  if (!v) return 'text-[#c8ccd4]'
  return 'text-[#1d273b]'
}

function heatStyle(row, col) {
  const v   = props.data.matrix[row]?.[col] ?? 0
  const max = colMax.value[col] ?? 1
  const pct = max > 0 ? v / max : 0
  // Interpolate: 0 = white, 1 = #206bc4 at 35% opacity
  const alpha = (pct * 0.35).toFixed(3)
  return `background-color: rgba(32,107,196,${alpha})`
}

// ── Chart ─────────────────────────────────────────────────────────────────────
function buildChartData() {
  if (!hasData.value) return null

  const PALETTE = [
    '#206bc4', '#f76707', '#2fb344', '#ae3ec9', '#d63939',
    '#0ca678', '#f59f00', '#4299e1', '#e64980', '#74c0fc',
    '#66d9e8', '#a9e34b',
  ]

  if (props.chartType === 'pyramid') {
    // Population pyramid: assume cols = ['male','female','other'] or similar
    // Rows = age groups; col male goes left (negative), female right (positive)
    const labels     = sortedRows.value.map(r => props.data.row_labels[r] ?? r)
    const maleCol    = props.data.cols.find(c => c === 'male') ?? props.data.cols[0]
    const femaleCol  = props.data.cols.find(c => c === 'female') ?? props.data.cols[1]
    const maleData   = sortedRows.value.map(r => -(props.data.matrix[r]?.[maleCol] ?? 0))
    const femaleData = sortedRows.value.map(r => (props.data.matrix[r]?.[femaleCol] ?? 0))
    return {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: props.data.col_labels[maleCol] ?? maleCol,   data: maleData,   backgroundColor: '#206bc4bb', borderRadius: 2 },
          { label: props.data.col_labels[femaleCol] ?? femaleCol, data: femaleData, backgroundColor: '#f76707bb', borderRadius: 2 },
        ],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: {
            stacked: false,
            ticks: {
              callback: v => Math.abs(v).toLocaleString(),
              font: { size: 10 },
            },
            grid: { color: '#f0f1f3' },
          },
          y: {
            ticks: { font: { size: 10 } },
            grid: { display: false },
          },
        },
        plugins: {
          legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 10 } },
          tooltip: {
            callbacks: {
              label: ctx => `${ctx.dataset.label}: ${Math.abs(ctx.parsed.x).toLocaleString()}`,
            },
          },
        },
      },
    }
  }

  // Default: grouped bar chart — cols on X, rows as datasets
  // Limit to first 12 rows for readability
  const visibleRows = sortedRows.value.slice(0, 12)
  return {
    type: 'bar',
    data: {
      labels: props.data.cols.map(c => props.data.col_labels[c] ?? c),
      datasets: visibleRows.map((row, i) => ({
        label:           props.data.row_labels[row] ?? row,
        data:            props.data.cols.map(col => props.data.matrix[row]?.[col] ?? 0),
        backgroundColor: PALETTE[i % PALETTE.length] + 'cc',
        borderColor:     PALETTE[i % PALETTE.length],
        borderWidth:     1,
        borderRadius:    3,
      })),
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        x: {
          ticks: { font: { size: 10 }, maxRotation: 45 },
          grid:  { color: '#f0f1f3' },
        },
        y: {
          ticks: { font: { size: 10 }, callback: v => v.toLocaleString() },
          grid:  { color: '#f0f1f3' },
        },
      },
      plugins: {
        legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 10 } },
        tooltip: {
          callbacks: { label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y.toLocaleString()}` },
        },
      },
    },
  }
}

async function renderChart() {
  await nextTick()
  if (!chartCanvas.value) return

  if (chartInstance) {
    chartInstance.destroy()
    chartInstance = null
  }

  const cfg = buildChartData()
  if (!cfg) return

  chartInstance = new Chart(chartCanvas.value, cfg)
}

// Re-render chart when data or visibility changes
watch(() => [props.data, chartVisible.value, sortedRows.value], () => {
  if (chartVisible.value) renderChart()
}, { deep: true })

onMounted(() => {
  if (chartVisible.value && hasData.value) renderChart()
})

onUnmounted(() => {
  chartInstance?.destroy()
})

// ── CSV Export ────────────────────────────────────────────────────────────────
function exportCsv() {
  if (!hasData.value) return

  const rows  = []
  const hdrs  = ['', ...props.data.cols.map(c => props.data.col_labels[c] ?? c), 'Total']
  rows.push(hdrs.join(','))

  for (const row of props.data.rows) {
    const cells = [
      `"${props.data.row_labels[row] ?? row}"`,
      ...props.data.cols.map(col => props.data.matrix[row]?.[col] ?? 0),
      props.data.row_totals[row] ?? 0,
    ]
    rows.push(cells.join(','))
  }

  // Totals row
  rows.push([
    '"Total"',
    ...props.data.cols.map(col => props.data.col_totals[col] ?? 0),
    props.data.grand_total,
  ].join(','))

  const blob = new Blob(['\uFEFF' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = (props.title || 'crosstab') + '.csv'
  a.click()
  URL.revokeObjectURL(url)
}
</script>
