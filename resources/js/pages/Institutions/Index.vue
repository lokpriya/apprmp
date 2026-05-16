<template>
  <div class="space-y-4">

    <!-- Survey freeze banner -->
    <div v-if="surveyFrozen"
      class="flex items-center gap-3 px-4 py-3 bg-orange-50 border border-orange-200 rounded-md">
      <i class="fa-solid fa-lock text-orange-500 shrink-0"></i>
      <div>
        <p class="text-[13px] font-semibold text-orange-700">सर्वेक्षण अवधि बन्द छ</p>
        <p class="text-[11px] text-orange-500 mt-0.5">नयाँ संस्था थप्न वा सम्पादन गर्न सकिँदैन। थप जानकारीका लागि प्रशासकसँग सम्पर्क गर्नुहोस्।</p>
      </div>
    </div>

    <!-- Page header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-[15px] font-semibold text-[#1d273b] flex items-center gap-2">
          <i class="fa-solid fa-building text-teal-600"></i> संस्था सूची
        </h1>
        <p class="text-[11px] text-[#9aa0ac] mt-0.5">
          जम्मा <span class="font-semibold text-[#626976]">{{ institutions.meta?.total ?? 0 }}</span> संस्थाहरू
        </p>
      </div>
      <a v-if="!surveyFrozen" href="/institutions/create"
        class="inline-flex items-center gap-1.5 h-9 px-4 bg-[#206bc4] hover:bg-[#1a5ba8] text-white rounded text-[13px] font-medium transition">
        <i class="fa-solid fa-plus text-[11px]"></i> नयाँ संस्था
      </a>
      <span v-else
        class="inline-flex items-center gap-1.5 h-9 px-4 bg-gray-200 text-gray-400 rounded text-[13px] font-medium cursor-not-allowed"
        title="सर्वेक्षण अवधि बन्द छ">
        <i class="fa-solid fa-lock text-[11px]"></i> नयाँ संस्था
      </span>
    </div>

      <!-- Filters -->
      <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-4">
        <div class="flex flex-col sm:flex-row gap-3">
          <!-- Search -->
          <div class="relative flex-1">
            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="नाम वा कोड खोज्नुस्..."
              class="w-full rounded-md border border-[#e6e7e9] pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition"
            />
          </div>

          <!-- Ward filter -->
          <select
            v-model="wardFilter"
            @change="applyFilters"
            class="rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition bg-white min-w-[140px]"
          >
            <option value="">सबै वडा</option>
            <option v-for="w in wards" :key="w.id" :value="w.id">वडा {{ w.ward_number }}</option>
          </select>

          <!-- Status filter -->
          <select
            v-model="statusFilter"
            @change="applyFilters"
            class="rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition bg-white min-w-[140px]"
          >
            <option value="">सबै अवस्था</option>
            <option value="draft">मस्यौदा</option>
            <option value="submitted">पेस भएको</option>
            <option value="ward_approved">वडा अनुमोदित</option>
            <option value="published">प्रकाशित</option>
            <option value="rejected">अस्वीकृत</option>
          </select>

          <!-- Clear -->
          <button
            v-if="hasActiveFilters"
            @click="clearFilters"
            class="rounded-md border border-[#e6e7e9] px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 transition-colors flex items-center gap-1.5"
          >
            <i class="fa-solid fa-xmark"></i> खाली गर्नुस्
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-[#e6e7e9]">
              <tr>
                <th class="px-4 py-3 w-8">
                  <input type="checkbox" :checked="allPageSelected" :indeterminate="someSelected && !allPageSelected"
                    @change="toggleSelectAll" class="w-4 h-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500 cursor-pointer" />
                </th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">कोड</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">नाम</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">प्रकार</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">वडा</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">स्वामित्व</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">अवस्था</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">पूर्णता</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">गणक</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">कार्यहरू</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#e6e7e9]">
              <tr
                v-for="inst in institutions.data"
                :key="inst.id"
                class="hover:bg-gray-50 transition-colors"
                :class="selectedIds.has(inst.id) ? 'bg-teal-50/40' : ''"
              >
                <td class="px-4 py-3">
                  <input type="checkbox" :checked="selectedIds.has(inst.id)" @change="toggleSelect(inst.id)"
                    class="w-4 h-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500 cursor-pointer" />
                </td>
                <td class="px-4 py-3">
                  <span class="font-mono text-xs bg-teal-50 text-teal-700 px-2 py-1 rounded">{{ inst.unique_code }}</span>
                </td>
                <td class="px-4 py-3">
                  <div class="font-medium text-gray-800">{{ inst.name_np || '—' }}</div>
                  <div v-if="inst.name_en" class="text-xs text-gray-400">{{ inst.name_en }}</div>
                </td>
                <td class="px-4 py-3 text-gray-600 text-xs">
                  <span v-if="inst.institutionType" class="bg-teal-50 text-teal-700 px-2 py-0.5 rounded-full">
                    {{ inst.institutionType.name_np }}
                  </span>
                  <span v-else>—</span>
                </td>
                <td class="px-4 py-3 text-gray-600">
                  <span v-if="inst.ward" class="bg-gray-100 text-gray-700 text-xs px-2 py-0.5 rounded-full">
                    वडा {{ inst.ward.ward_number }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-500">{{ fieldLabel('inst_ownership_type', inst.ownership_type) }}</td>
                <td class="px-4 py-3">
                  <span :class="['text-xs px-2.5 py-1 rounded-full font-medium', statusBadge(inst.status)]">
                    {{ statusLabel(inst.status) }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span v-if="inst.completeness" :class="['inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full', completenessClass(inst.completeness.score)]"
                    :title="`${inst.completeness.filled}/${inst.completeness.total} फिल्ड भरिएको`">
                    {{ inst.completeness.score }}%
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-500">{{ inst.enumerator?.name || '—' }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center justify-end gap-2">
                    <!-- View -->
                    <a
                      :href="`/institutions/${inst.id}`"
                      class="inline-flex items-center gap-1 text-xs text-teal-600 hover:text-teal-800 font-medium px-2 py-1 rounded hover:bg-teal-50 transition-colors"
                    >
                      <i class="fa-solid fa-eye"></i>
                    </a>

                    <!-- Edit -->
                    <a
                      v-if="inst.can_edit"
                      :href="`/institutions/${inst.id}/edit`"
                      class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700 font-medium px-2 py-1 rounded hover:bg-gray-100 transition-colors"
                    >
                      <i class="fa-solid fa-pen"></i>
                    </a>

                    <!-- Submit -->
                    <button
                      v-if="inst.can_submit"
                      @click="submitInstitution(inst.id)"
                      class="inline-flex items-center gap-1 text-xs text-white bg-green-600 hover:bg-green-700 font-medium px-2.5 py-1 rounded transition-colors"
                      :disabled="submitting === inst.id"
                    >
                      <i class="fa-solid fa-paper-plane"></i>
                      <span v-if="submitting !== inst.id">पेस</span>
                      <span v-else><i class="fa-solid fa-spinner fa-spin"></i></span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty state -->
        <div v-if="!institutions.data?.length" class="py-16 text-center">
          <i class="fa-solid fa-building-circle-xmark text-5xl text-gray-200 mb-4 block"></i>
          <p class="text-gray-500 font-medium">कुनै संस्था फेला परेन</p>
          <p class="text-xs text-gray-400 mt-1">फिल्टर परिवर्तन गर्नुस् वा नयाँ संस्था थप्नुस्</p>
          <a
            href="/institutions/create"
            class="mt-4 inline-flex items-center gap-2 bg-teal-600 text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-teal-700 transition-colors"
          >
            <i class="fa-solid fa-plus"></i> नयाँ संस्था थप्नुस्
          </a>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="institutions.meta && institutions.meta.last_page > 1" class="flex items-center justify-between">
        <p class="text-xs text-gray-500">
          देखाइएको
          <span class="font-semibold">{{ institutions.meta.from }}</span>–<span class="font-semibold">{{ institutions.meta.to }}</span>
          / <span class="font-semibold">{{ institutions.meta.total }}</span>
        </p>
        <div class="flex items-center gap-1">
          <button
            :disabled="institutions.meta.current_page <= 1"
            @click="goToPage(institutions.meta.current_page - 1)"
            class="rounded-md border border-[#e6e7e9] px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
          >
            <i class="fa-solid fa-chevron-left"></i>
          </button>

          <template v-for="page in visiblePages" :key="page">
            <span v-if="page === '...'" class="px-2 text-gray-400 text-sm">…</span>
            <button
              v-else
              @click="goToPage(page)"
              :class="[
                'rounded-md border px-3 py-2 text-sm font-medium transition-colors',
                page === institutions.meta.current_page
                  ? 'bg-teal-600 border-teal-600 text-white'
                  : 'border-[#e6e7e9] text-gray-600 hover:bg-gray-50'
              ]"
            >{{ page }}</button>
          </template>

          <button
            :disabled="institutions.meta.current_page >= institutions.meta.last_page"
            @click="goToPage(institutions.meta.current_page + 1)"
            class="rounded-md border border-[#e6e7e9] px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
          >
            <i class="fa-solid fa-chevron-right"></i>
          </button>
        </div>
      </div>

    <!-- Bulk toolbar -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 translate-y-4" enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-4">
        <div v-if="selectedIds.size > 0"
          class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-[#1d273b] text-white rounded-xl shadow-2xl flex items-center gap-3 px-5 py-3 min-w-max">
          <span class="flex items-center gap-1.5 text-sm font-semibold">
            <span class="bg-white/15 rounded-lg px-2.5 py-1 text-xs font-bold">{{ selectedIds.size }}</span>
            संस्था छानिएको
          </span>
          <div class="h-5 w-px bg-white/20"></div>
          <button v-if="canBulkSubmit" @click="bulkSubmit" :disabled="bulkProcessing"
            class="flex items-center gap-1.5 px-4 py-1.5 bg-blue-500 hover:bg-blue-400 disabled:opacity-50 rounded-lg text-sm font-semibold transition-colors">
            <i class="fa-solid fa-paper-plane text-xs"></i>सबै पेस
          </button>
          <button v-if="canBulkApprove" @click="bulkApprove" :disabled="bulkProcessing"
            class="flex items-center gap-1.5 px-4 py-1.5 bg-green-500 hover:bg-green-400 disabled:opacity-50 rounded-lg text-sm font-semibold transition-colors">
            <i class="fa-solid fa-check-double text-xs"></i>सबै अनुमोदन
          </button>
          <button @click="openRejectModal" :disabled="bulkProcessing"
            class="flex items-center gap-1.5 px-4 py-1.5 bg-red-500 hover:bg-red-400 disabled:opacity-50 rounded-lg text-sm font-semibold transition-colors">
            <i class="fa-solid fa-xmark text-xs"></i>सबै अस्वीकृत
          </button>
          <button @click="clearSelection" class="flex items-center gap-1 px-2 py-1.5 text-white/60 hover:text-white transition-colors text-xs">
            <i class="fa-solid fa-xmark"></i>हटाउनुहोस्
          </button>
        </div>
      </Transition>
    </Teleport>

    <!-- Bulk Reject Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="rejectModalOpen" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/50" @click.self="rejectModalOpen = false">
          <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" @click.stop>
            <div class="px-5 py-4 border-b border-[#e6e7e9] flex items-center justify-between">
              <h3 class="text-sm font-bold text-gray-900"><i class="fa-solid fa-xmark text-red-500 mr-1.5"></i>{{ selectedIds.size }} संस्था अस्वीकृत</h3>
              <button @click="rejectModalOpen = false" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="px-5 py-4">
              <textarea v-model="rejectReason" rows="3" placeholder="अस्वीकृतिको कारण लेख्नुहोस्..."
                class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 resize-none"></textarea>
            </div>
            <div class="px-5 py-4 border-t border-[#e6e7e9] flex items-center justify-end gap-2">
              <button @click="rejectModalOpen = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">रद्द गर्नुहोस्</button>
              <button @click="confirmBulkReject" :disabled="!rejectReason.trim() || bulkProcessing"
                class="px-5 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 disabled:opacity-50">
                <i class="fa-solid fa-xmark mr-1"></i>{{ selectedIds.size }} वटा अस्वीकृत
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { fieldLabel } from '@/utils/fieldLabels'

const page = usePage()
const surveyFrozen = computed(() => page.props.surveyFrozen && page.props.auth?.user?.role === 'enumerator')

const props = defineProps({
  institutions: { type: Object, default: () => ({ data: [], meta: {}, links: [] }) },
  wards: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({ search: '', ward_id: '', status: '' }) },
})

const searchQuery = ref(props.filters.search || '')
const wardFilter = ref(props.filters.ward_id || '')
const statusFilter = ref(props.filters.status || '')
const submitting = ref(null)

let searchTimeout = null

const hasActiveFilters = computed(() => searchQuery.value || wardFilter.value || statusFilter.value)

// ── helpers ───────────────────────────────────────────────────────────────────

function completenessClass(score) {
  if (score >= 90) return 'bg-green-100 text-green-700'
  if (score >= 75) return 'bg-yellow-100 text-yellow-700'
  if (score >= 50) return 'bg-amber-100 text-amber-700'
  return 'bg-red-100 text-red-700'
}

function statusBadge(s) {
  const map = {
    draft: 'bg-gray-100 text-gray-600',
    submitted: 'bg-yellow-100 text-yellow-700',
    ward_approved: 'bg-blue-100 text-blue-700',
    rejected: 'bg-red-100 text-red-700',
    published: 'bg-green-100 text-green-700',
  }
  return map[s] ?? 'bg-gray-100 text-gray-600'
}

function statusLabel(s) {
  const map = {
    draft: 'मस्यौदा',
    submitted: 'पेस भएको',
    ward_approved: 'वडा अनुमोदित',
    rejected: 'अस्वीकृत',
    published: 'प्रकाशित',
  }
  return map[s] ?? s
}

// ── filters ───────────────────────────────────────────────────────────────────

function applyFilters() {
  router.get(
    '/institutions',
    {
      search: searchQuery.value,
      ward_id: wardFilter.value,
      status: statusFilter.value,
    },
    { preserveState: true, replace: true },
  )
}

watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    applyFilters()
  }, 400)
})

function clearFilters() {
  searchQuery.value = ''
  wardFilter.value = ''
  statusFilter.value = ''
  applyFilters()
}

// ── pagination ────────────────────────────────────────────────────────────────

function goToPage(page) {
  router.get(
    '/institutions',
    {
      search: searchQuery.value,
      ward_id: wardFilter.value,
      status: statusFilter.value,
      page,
    },
    { preserveState: true, replace: true },
  )
}

const visiblePages = computed(() => {
  const meta = props.institutions.meta
  if (!meta) return []
  const current = meta.current_page
  const last = meta.last_page
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)

  const pages = []
  if (current <= 4) {
    for (let i = 1; i <= 5; i++) pages.push(i)
    pages.push('...')
    pages.push(last)
  } else if (current >= last - 3) {
    pages.push(1)
    pages.push('...')
    for (let i = last - 4; i <= last; i++) pages.push(i)
  } else {
    pages.push(1)
    pages.push('...')
    for (let i = current - 1; i <= current + 1; i++) pages.push(i)
    pages.push('...')
    pages.push(last)
  }
  return pages
})

// ── actions ───────────────────────────────────────────────────────────────────

function submitInstitution(id) {
  if (!confirm('के तपाईं यो संस्था पेस गर्न निश्चित हुनुहुन्छ?')) return
  submitting.value = id
  router.post(`/institutions/${id}/submit`, {}, {
    onFinish: () => { submitting.value = null },
  })
}

// ── Bulk selection ───────────────────────────────────────────────────────────
const selectedIds     = ref(new Set())
const bulkProcessing  = ref(false)
const rejectModalOpen = ref(false)
const rejectReason    = ref('')

const pageIds         = computed(() => (props.institutions.data ?? []).map(i => i.id))
const allPageSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every(id => selectedIds.value.has(id)))
const someSelected    = computed(() => pageIds.value.some(id => selectedIds.value.has(id)))
const canBulkSubmit   = computed(() => (props.institutions.data ?? []).some(i => selectedIds.value.has(i.id) && i.can_submit))
const canBulkApprove  = computed(() => (props.institutions.data ?? []).some(i => selectedIds.value.has(i.id) && i.status === 'submitted'))

function toggleSelect(id) {
  const s = new Set(selectedIds.value); s.has(id) ? s.delete(id) : s.add(id); selectedIds.value = s
}
function toggleSelectAll() {
  if (allPageSelected.value) {
    const s = new Set(selectedIds.value); pageIds.value.forEach(id => s.delete(id)); selectedIds.value = s
  } else {
    const s = new Set(selectedIds.value); pageIds.value.forEach(id => s.add(id)); selectedIds.value = s
  }
}
function clearSelection() { selectedIds.value = new Set() }

function bulkSubmit() {
  if (!confirm(`${selectedIds.value.size} संस्था पेस गर्ने?`)) return
  bulkProcessing.value = true
  router.post('/institutions/bulk-submit', { ids: [...selectedIds.value] }, {
    onFinish: () => { bulkProcessing.value = false; clearSelection() },
  })
}
function bulkApprove() {
  if (!confirm(`${selectedIds.value.size} संस्था अनुमोदन गर्ने?`)) return
  bulkProcessing.value = true
  router.post('/institutions/bulk-approve', { ids: [...selectedIds.value] }, {
    onFinish: () => { bulkProcessing.value = false; clearSelection() },
  })
}
function openRejectModal() { rejectReason.value = ''; rejectModalOpen.value = true }
function confirmBulkReject() {
  if (!rejectReason.value.trim()) return
  bulkProcessing.value = true
  router.post('/institutions/bulk-reject', { ids: [...selectedIds.value], reason: rejectReason.value }, {
    onFinish: () => { bulkProcessing.value = false; rejectModalOpen.value = false; clearSelection() },
  })
}
</script>
