<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">

      <!-- Page header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-xl font-bold text-[#1d273b]">नागरिक सुझाव</h1>
          <p class="text-sm text-gray-400 mt-0.5">नागरिकहरूबाट प्राप्त तथ्याङ्क सुधारका सुझावहरू</p>
        </div>
        <a :href="exportUrl"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 text-sm text-[#626976]
                 hover:bg-gray-50 hover:text-[#1d273b] transition"
          title="CSV निर्यात">
          <i class="fa-solid fa-file-csv text-green-600"></i>
          <span class="hidden sm:inline">CSV निर्यात</span>
        </a>
      </div>

      <!-- Stats cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div v-for="s in statCards" :key="s.key"
          class="bg-white rounded-xl border border-gray-100 px-4 py-3 cursor-pointer transition hover:shadow-sm"
          :class="filters.status === (s.key === 'all' ? 'all' : s.key) ? 'ring-2 ' + s.ring : ''"
          @click="setStatus(s.key === 'all' ? 'all' : s.key)">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-medium text-gray-500">{{ s.label }}</span>
            <span class="text-lg font-bold" :class="s.color">{{ counts[s.key] ?? 0 }}</span>
          </div>
          <div class="mt-1 h-1 rounded-full" :class="s.bar"></div>
        </div>
      </div>

      <!-- Filters row -->
      <div class="flex flex-wrap gap-3 mb-5">
        <!-- Search -->
        <div class="relative flex-1 min-w-[200px]">
          <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
          <input v-model="localSearch" @keydown.enter="applySearch"
            type="text" placeholder="नाम, फोन, इमेल वा सुझाव खोज्नुहोस्..."
            class="w-full pl-8 pr-3 py-2 text-sm border border-gray-200 rounded-lg
                   focus:outline-none focus:ring-2 focus:ring-amber-300" />
        </div>
        <!-- Category filter -->
        <select v-model="localCategory" @change="applyFilters"
          class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-amber-300">
          <option value="">सबै विषय</option>
          <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
        </select>
        <!-- Ward filter -->
        <select v-model="localWard" @change="applyFilters"
          class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-amber-300">
          <option value="">सबै वडा</option>
          <option v-for="w in availableWards" :key="w" :value="w">वडा नं. {{ w }}</option>
        </select>
        <button v-if="hasFilters" @click="clearFilters"
          class="text-xs text-gray-400 hover:text-gray-600 px-2 flex items-center gap-1">
          <i class="fa-solid fa-xmark"></i> फिल्टर हटाउनुहोस्
        </button>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
        <div v-if="items.data.length === 0" class="py-16 text-center text-gray-400">
          <i class="fa-solid fa-inbox text-4xl mb-3 opacity-25"></i>
          <p class="text-sm font-medium text-gray-500">कुनै सुझाव फेला परेन</p>
          <p v-if="hasFilters" class="text-xs text-gray-400 mt-1">
            सक्रिय फिल्टरमा कुनै रेकर्ड छैन —
            <button @click="clearFilters" class="text-[#206bc4] hover:underline ml-0.5">फिल्टर हटाउनुहोस्</button>
          </p>
          <p v-else-if="props.filters.status && props.filters.status !== 'all'" class="text-xs text-gray-400 mt-1">
            यस स्थितिमा कुनै सुझाव छैन।
          </p>
        </div>
        <table v-else class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide">
                <button @click="toggleSort('created_at')" class="flex items-center gap-1 text-gray-500 hover:text-[#1d273b] transition group">
                  तारिख
                  <span class="flex flex-col leading-[0] ml-0.5">
                    <i class="fa-solid fa-caret-up text-[8px]" :class="sortKey === 'created_at' && sortDir === 'asc' ? 'text-[#206bc4]' : 'text-gray-300 group-hover:text-gray-400'"></i>
                    <i class="fa-solid fa-caret-down text-[8px]" :class="sortKey === 'created_at' && sortDir === 'desc' ? 'text-[#206bc4]' : 'text-gray-300 group-hover:text-gray-400'"></i>
                  </span>
                </button>
              </th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">व्यक्ति</th>
              <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide">
                <button @click="toggleSort('category')" class="flex items-center gap-1 text-gray-500 hover:text-[#1d273b] transition group">
                  विषय
                  <span class="flex flex-col leading-[0] ml-0.5">
                    <i class="fa-solid fa-caret-up text-[8px]" :class="sortKey === 'category' && sortDir === 'asc' ? 'text-[#206bc4]' : 'text-gray-300 group-hover:text-gray-400'"></i>
                    <i class="fa-solid fa-caret-down text-[8px]" :class="sortKey === 'category' && sortDir === 'desc' ? 'text-[#206bc4]' : 'text-gray-300 group-hover:text-gray-400'"></i>
                  </span>
                </button>
              </th>
              <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide hidden md:table-cell">
                <button @click="toggleSort('ward_number')" class="flex items-center gap-1 text-gray-500 hover:text-[#1d273b] transition group">
                  वडा
                  <span class="flex flex-col leading-[0] ml-0.5">
                    <i class="fa-solid fa-caret-up text-[8px]" :class="sortKey === 'ward_number' && sortDir === 'asc' ? 'text-[#206bc4]' : 'text-gray-300 group-hover:text-gray-400'"></i>
                    <i class="fa-solid fa-caret-down text-[8px]" :class="sortKey === 'ward_number' && sortDir === 'desc' ? 'text-[#206bc4]' : 'text-gray-300 group-hover:text-gray-400'"></i>
                  </span>
                </button>
              </th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">सुझाव</th>
              <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide">
                <button @click="toggleSort('status')" class="flex items-center gap-1 text-gray-500 hover:text-[#1d273b] transition group">
                  स्थिति
                  <span class="flex flex-col leading-[0] ml-0.5">
                    <i class="fa-solid fa-caret-up text-[8px]" :class="sortKey === 'status' && sortDir === 'asc' ? 'text-[#206bc4]' : 'text-gray-300 group-hover:text-gray-400'"></i>
                    <i class="fa-solid fa-caret-down text-[8px]" :class="sortKey === 'status' && sortDir === 'desc' ? 'text-[#206bc4]' : 'text-gray-300 group-hover:text-gray-400'"></i>
                  </span>
                </button>
              </th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="item in items.data" :key="item.id"
              class="hover:bg-gray-50/50 transition-colors border-l-[3px]"
              :class="categoryBorder(item.category)">
              <!-- Date -->
              <td class="px-4 py-3 text-xs text-gray-400 whitespace-nowrap">
                {{ formatDate(item.created_at) }}
              </td>
              <!-- Person -->
              <td class="px-4 py-3">
                <div class="font-medium text-[#1d273b] text-xs">{{ item.name || 'अज्ञात' }}</div>
                <div v-if="item.phone" class="text-[11px] text-gray-400">{{ item.phone }}</div>
                <div v-if="item.email" class="text-[11px] text-gray-400 truncate max-w-[120px]">{{ item.email }}</div>
              </td>
              <!-- Category -->
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium"
                  :class="categoryStyle(item.category)">
                  {{ categories[item.category] ?? item.category }}
                </span>
              </td>
              <!-- Ward -->
              <td class="px-4 py-3 text-xs text-gray-500 hidden md:table-cell">
                {{ item.ward_number ? 'वडा ' + item.ward_number : '—' }}
              </td>
              <!-- Message -->
              <td class="px-4 py-3 max-w-[220px]">
                <p class="text-xs text-gray-600 line-clamp-2 leading-relaxed">{{ item.message }}</p>
              </td>
              <!-- Status + reviewer -->
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold"
                  :class="statusStyle(item.status)">
                  {{ statusLabel(item.status) }}
                </span>
                <div v-if="item.reviewer" class="text-[10px] text-gray-400 mt-1 leading-tight">
                  <i class="fa-solid fa-user-check text-[9px] mr-0.5"></i>{{ item.reviewer.name }}
                </div>
              </td>
              <!-- Actions -->
              <td class="px-4 py-3">
                <button @click="openDetail(item)"
                  class="text-[11px] text-indigo-600 hover:text-indigo-800 font-medium whitespace-nowrap">
                  विवरण
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="items.last_page > 1"
          class="flex items-center justify-between px-4 py-3 border-t border-gray-100 text-xs text-gray-500">
          <span>जम्मा {{ items.total }} सुझाव</span>
          <div class="flex gap-1">
            <button v-for="p in pages" :key="p"
              @click="goPage(p)"
              class="w-7 h-7 rounded flex items-center justify-center transition"
              :class="p === items.current_page
                ? 'bg-amber-500 text-white font-bold'
                : 'hover:bg-gray-100 text-gray-600'">
              {{ p }}
            </button>
          </div>
        </div>
      </div>

    <!-- ── Detail Modal ─────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="detail"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="background:rgba(0,0,0,0.4)" @click.self="detail = null">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">

          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="font-bold text-[#1d273b]">सुझाव विवरण</h2>
            <button @click="detail = null" class="text-gray-400 hover:text-gray-600 text-lg">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">
            <!-- Meta row -->
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div>
                <p class="text-xs text-gray-400 mb-0.5">नाम</p>
                <p class="font-medium text-[#1d273b]">{{ detail.name || 'अज्ञात' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 mb-0.5">फोन</p>
                <p class="font-medium text-[#1d273b]">{{ detail.phone || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 mb-0.5">इमेल</p>
                <p class="font-medium text-[#1d273b] truncate">{{ detail.email || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 mb-0.5">वडा</p>
                <p class="font-medium text-[#1d273b]">{{ detail.ward_number ? 'वडा नं. ' + detail.ward_number : '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 mb-0.5">विषय</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium"
                  :class="categoryStyle(detail.category)">
                  {{ categories[detail.category] ?? detail.category }}
                </span>
              </div>
              <div>
                <p class="text-xs text-gray-400 mb-0.5">तारिख</p>
                <p class="font-medium text-[#1d273b]">{{ formatDate(detail.created_at) }}</p>
              </div>
            </div>

            <!-- Message -->
            <div>
              <p class="text-xs text-gray-400 mb-1.5">सुझाव</p>
              <div class="bg-gray-50 rounded-xl px-4 py-3 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">
                {{ detail.message }}
              </div>
            </div>

            <!-- Admin note + status: only if canManage -->
            <template v-if="props.canManage">
              <div>
                <label class="text-xs text-gray-400 mb-1.5 block">प्रशासकीय टिप्पणी</label>
                <textarea v-model="detailNote" rows="3" maxlength="1000"
                  placeholder="आन्तरिक टिप्पणी थप्नुहोस्..."
                  class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm resize-none
                         focus:outline-none focus:ring-2 focus:ring-amber-300"></textarea>
              </div>

              <div>
                <label class="text-xs text-gray-400 mb-1.5 block">स्थिति परिवर्तन गर्नुहोस्</label>
                <div class="flex gap-2">
                  <button v-for="s in statusOptions" :key="s.key"
                    @click="detailStatus = s.key"
                    class="flex-1 py-2 rounded-lg text-xs font-semibold border-2 transition"
                    :class="detailStatus === s.key ? s.active : s.inactive">
                    {{ s.label }}
                  </button>
                </div>
              </div>
            </template>
            <div v-else class="rounded-xl bg-blue-50 border border-blue-100 px-4 py-3 text-xs text-blue-600">
              <i class="fa-solid fa-circle-info mr-1"></i> तपाईंसँग सुझावको स्थिति परिवर्तन गर्ने अनुमति छैन।
            </div>
          </div>

          <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
            <button v-if="props.canManage" @click="saveDetail"
              :disabled="savingDetail"
              class="flex-1 py-2.5 rounded-xl bg-amber-500 text-white text-sm font-semibold
                     hover:bg-amber-400 transition disabled:opacity-60">
              <i v-if="savingDetail" class="fa-solid fa-spinner fa-spin mr-1"></i>
              सुरक्षित गर्नुहोस्
            </button>
            <button v-if="props.canDelete" @click="confirmDelete(detail)"
              class="px-4 py-2.5 rounded-xl border border-red-100 text-red-500 text-sm font-medium
                     hover:bg-red-50 transition">
              <i class="fa-solid fa-trash text-xs"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Delete confirm -->
      <div v-if="deleteTarget"
        class="fixed inset-0 z-[60] flex items-center justify-center p-4"
        style="background:rgba(0,0,0,0.5)">
        <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-sm w-full text-center">
          <div class="w-14 h-14 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-trash text-red-500 text-xl"></i>
          </div>
          <h3 class="font-bold text-[#1d273b] mb-2">सुझाव मेटाउने?</h3>
          <p class="text-sm text-gray-500 mb-5">यो कार्य पूर्ववत हुन सक्दैन।</p>
          <div class="flex gap-3">
            <button @click="deleteTarget = null"
              class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50">
              रद्द गर्नुहोस्
            </button>
            <button @click="doDelete"
              class="flex-1 py-2.5 rounded-xl bg-red-500 text-white text-sm font-semibold hover:bg-red-600 transition">
              मेटाउनुहोस्
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  items:      { type: Object, required: true },
  filters:    { type: Object, default: () => ({}) },
  categories: { type: Object, default: () => ({}) },
  counts:     { type: Object, default: () => ({}) },
  canManage:  { type: Boolean, default: false },
  canDelete:  { type: Boolean, default: false },
})

const sortKey = ref(props.filters.sort ?? 'created_at')
const sortDir = ref(props.filters.dir  ?? 'desc')

const localSearch   = ref(props.filters.search   ?? '')
const localCategory = ref(props.filters.category ?? '')
const localWard     = ref(props.filters.ward      ?? '')

const hasFilters = computed(() =>
  localSearch.value || localCategory.value || localWard.value || (props.filters.status && props.filters.status !== 'all')
)

const exportUrl = computed(() => {
  const p = new URLSearchParams()
  if (props.filters.status && props.filters.status !== 'all') p.set('status', props.filters.status)
  if (props.filters.category) p.set('category', props.filters.category)
  if (props.filters.ward)     p.set('ward', props.filters.ward)
  if (props.filters.search)   p.set('search', props.filters.search)
  if (props.filters.sort)     p.set('sort', props.filters.sort)
  if (props.filters.dir)      p.set('dir', props.filters.dir)
  const qs = p.toString()
  return '/admin/feedback/export' + (qs ? '?' + qs : '')
})

const availableWards = computed(() => {
  const wards = new Set(props.items.data.map(i => i.ward_number).filter(Boolean))
  return [...wards].sort((a, b) => a - b)
})

const pages = computed(() => {
  const total = props.items.last_page
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  const cur = props.items.current_page
  return [...new Set([1, cur - 1, cur, cur + 1, total].filter(p => p >= 1 && p <= total))].sort((a, b) => a - b)
})

// Stats cards
const statCards = [
  { key: 'all',      label: 'जम्मा',    color: 'text-[#1d273b]',  ring: 'ring-gray-300',   bar: 'bg-gray-200' },
  { key: 'new',      label: 'नयाँ',     color: 'text-blue-600',   ring: 'ring-blue-300',   bar: 'bg-blue-400' },
  { key: 'reviewed', label: 'समीक्षित', color: 'text-amber-600',  ring: 'ring-amber-300',  bar: 'bg-amber-400' },
  { key: 'resolved', label: 'समाधान',   color: 'text-green-600',  ring: 'ring-green-300',  bar: 'bg-green-400' },
]

const statusOptions = [
  { key: 'new',      label: 'नयाँ',     active: 'border-blue-500 bg-blue-50 text-blue-700',    inactive: 'border-gray-200 text-gray-500 hover:bg-gray-50' },
  { key: 'reviewed', label: 'समीक्षित', active: 'border-amber-500 bg-amber-50 text-amber-700', inactive: 'border-gray-200 text-gray-500 hover:bg-gray-50' },
  { key: 'resolved', label: 'समाधान',   active: 'border-green-500 bg-green-50 text-green-700', inactive: 'border-gray-200 text-gray-500 hover:bg-gray-50' },
]

// Detail modal
const detail       = ref(null)
const detailNote   = ref('')
const detailStatus = ref('new')
const savingDetail = ref(false)
const deleteTarget = ref(null)

function openDetail(item) {
  detail.value       = item
  detailNote.value   = item.admin_note ?? ''
  detailStatus.value = item.status
}

function saveDetail() {
  if (!detail.value) return
  savingDetail.value = true
  router.patch(`/admin/feedback/${detail.value.id}`, {
    status:     detailStatus.value,
    admin_note: detailNote.value,
  }, {
    preserveScroll: true,
    onSuccess: () => { detail.value = null },
    onFinish:  () => { savingDetail.value = false },
  })
}

function confirmDelete(item) {
  deleteTarget.value = item
}

function doDelete() {
  if (!deleteTarget.value) return
  router.delete(`/admin/feedback/${deleteTarget.value.id}`, {
    preserveScroll: true,
    onSuccess: () => { deleteTarget.value = null; detail.value = null },
  })
}

// Sort
function toggleSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortKey.value = key
    sortDir.value = 'desc'
  }
  router.get('/admin/feedback', { ...props.filters, sort: sortKey.value, dir: sortDir.value }, { preserveScroll: true, replace: true })
}

// Filters
function setStatus(s) {
  router.get('/admin/feedback', { ...props.filters, status: s }, { preserveScroll: true, replace: true })
}

function applySearch() {
  router.get('/admin/feedback', { ...props.filters, search: localSearch.value }, { preserveScroll: true, replace: true })
}

function applyFilters() {
  router.get('/admin/feedback', {
    ...props.filters,
    category: localCategory.value,
    ward:     localWard.value,
    search:   localSearch.value,
  }, { preserveScroll: true, replace: true })
}

function clearFilters() {
  localSearch.value = ''; localCategory.value = ''; localWard.value = ''
  router.get('/admin/feedback', {}, { preserveScroll: true, replace: true })
}

function goPage(p) {
  router.get('/admin/feedback', { ...props.filters, page: p }, { preserveScroll: true })
}

// Helpers
function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('ne-NP', { year: 'numeric', month: 'short', day: 'numeric' })
}

function statusLabel(s) {
  return { new: 'नयाँ', reviewed: 'समीक्षित', resolved: 'समाधान' }[s] ?? s
}

function statusStyle(s) {
  return {
    new:      'bg-blue-100 text-blue-700',
    reviewed: 'bg-amber-100 text-amber-700',
    resolved: 'bg-green-100 text-green-700',
  }[s] ?? 'bg-gray-100 text-gray-600'
}

function categoryStyle(cat) {
  const map = {
    household: 'bg-indigo-50 text-indigo-700', population: 'bg-blue-50 text-blue-700',
    education: 'bg-purple-50 text-purple-700', institution: 'bg-teal-50 text-teal-700',
    business: 'bg-orange-50 text-orange-700', infrastructure: 'bg-rose-50 text-rose-700',
    general: 'bg-gray-100 text-gray-600',
  }
  return map[cat] ?? 'bg-gray-100 text-gray-600'
}

function categoryBorder(cat) {
  const map = {
    household: 'border-indigo-400', population: 'border-blue-400',
    education: 'border-purple-400', institution: 'border-teal-400',
    business: 'border-orange-400', infrastructure: 'border-rose-400',
    general: 'border-gray-300',
  }
  return map[cat] ?? 'border-gray-300'
}
</script>
