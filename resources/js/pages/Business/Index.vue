<template>
  <div class="space-y-4">

    <!-- Survey freeze banner -->
    <div v-if="surveyFrozen"
      class="flex items-center gap-3 px-4 py-3 bg-orange-50 border border-orange-200 rounded-md">
      <i class="fa-solid fa-lock text-orange-500 shrink-0"></i>
      <div>
        <p class="text-[13px] font-semibold text-orange-700">सर्वेक्षण अवधि बन्द छ</p>
        <p class="text-[11px] text-orange-500 mt-0.5">नयाँ व्यवसाय थप्न वा सम्पादन गर्न सकिँदैन। थप जानकारीका लागि प्रशासकसँग सम्पर्क गर्नुहोस्।</p>
      </div>
    </div>

    <!-- Page header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-[15px] font-semibold text-[#1d273b] flex items-center gap-2">
          <i class="fa-solid fa-store text-orange-600"></i> व्यवसाय सूची
        </h1>
        <p class="text-[11px] text-[#9aa0ac] mt-0.5">
          जम्मा <span class="font-semibold text-[#626976]">{{ businesses.meta?.total ?? 0 }}</span> व्यवसायहरू
        </p>
      </div>
      <a v-if="!surveyFrozen" href="/businesses/create"
        class="inline-flex items-center gap-1.5 h-9 px-4 bg-orange-600 hover:bg-orange-700 text-white rounded text-[13px] font-medium transition">
        <i class="fa-solid fa-plus text-[11px]"></i> नयाँ व्यवसाय
      </a>
      <span v-else
        class="inline-flex items-center gap-1.5 h-9 px-4 bg-gray-200 text-gray-400 rounded text-[13px] font-medium cursor-not-allowed"
        title="सर्वेक्षण अवधि बन्द छ">
        <i class="fa-solid fa-lock text-[11px]"></i> नयाँ व्यवसाय
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
            placeholder="व्यवसाय नाम, कोड वा सदस्य खोज्नुस्..."
            class="w-full rounded-md border border-[#e6e7e9] pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition"
          />
        </div>

        <!-- Ward filter -->
        <select
          v-model="wardFilter"
          @change="applyFilters"
          class="rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition bg-white min-w-[140px]"
        >
          <option value="">सबै वडा</option>
          <option v-for="w in wards" :key="w" :value="w">वडा {{ w }}</option>
        </select>

        <!-- Status filter -->
        <select
          v-model="statusFilter"
          @change="applyFilters"
          class="rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition bg-white min-w-[140px]"
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
                  @change="toggleSelectAll" class="w-4 h-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500 cursor-pointer" />
              </th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">कोड</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">व्यवसाय नाम</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">सदस्य</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">घरधुरी</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">क्षेत्र</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">वडा / टोल</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">अवस्था</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">पूर्णता</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">गणक</th>
              <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">कार्यहरू</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#e6e7e9]">
            <tr
              v-for="biz in businesses.data"
              :key="biz.id"
              class="hover:bg-gray-50 transition-colors"
              :class="selectedIds.has(biz.id) ? 'bg-orange-50/40' : ''"
            >
              <!-- Checkbox -->
              <td class="px-4 py-3">
                <input type="checkbox" :checked="selectedIds.has(biz.id)" @change="toggleSelect(biz.id)"
                  class="w-4 h-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500 cursor-pointer" />
              </td>

              <!-- Code -->
              <td class="px-4 py-3">
                <span class="font-mono text-xs bg-orange-50 text-orange-700 px-2 py-1 rounded">{{ biz.unique_code }}</span>
              </td>

              <!-- Name + type -->
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800">{{ biz.business_name || '—' }}</div>
                <div v-if="biz.business_type" class="text-xs text-gray-400">{{ fieldLabel('business_type', biz.business_type) }}</div>
              </td>

              <!-- Member -->
              <td class="px-4 py-3">
                <span v-if="biz.member" class="text-gray-700 text-xs">{{ biz.member.full_name }}</span>
                <span v-else class="text-gray-400">—</span>
              </td>

              <!-- Household -->
              <td class="px-4 py-3">
                <a
                  v-if="biz.household"
                  :href="`/households/${biz.household.id}`"
                  class="font-mono text-xs text-blue-600 hover:underline"
                >{{ biz.household.unique_code }}</a>
                <span v-else class="text-gray-400">—</span>
              </td>

              <!-- Business field -->
              <td class="px-4 py-3">
                <span v-if="biz.business_field" class="text-xs bg-orange-50 text-orange-700 px-2 py-0.5 rounded-full">
                  {{ fieldLabel('business_field', biz.business_field) }}
                </span>
                <span v-else class="text-gray-400">—</span>
              </td>

              <!-- Ward / settlement -->
              <td class="px-4 py-3">
                <span v-if="biz.ward" class="bg-gray-100 text-gray-700 text-xs px-2 py-0.5 rounded-full">
                  वडा {{ biz.ward.ward_number }}
                </span>
                <div v-if="biz.settlement" class="text-xs text-gray-400 mt-0.5">{{ biz.settlement.name_np }}</div>
              </td>

              <!-- Status -->
              <td class="px-4 py-3">
                <span :class="['text-xs px-2.5 py-1 rounded-full font-medium', statusBadge(biz.status)]">
                  {{ statusLabel(biz.status) }}
                </span>
              </td>

              <!-- Completeness -->
              <td class="px-4 py-3">
                <span v-if="biz.completeness" :class="['inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full', completenessClass(biz.completeness.score)]"
                  :title="`${biz.completeness.filled}/${biz.completeness.total} फिल्ड भरिएको`">
                  {{ biz.completeness.score }}%
                </span>
              </td>

              <!-- Enumerator -->
              <td class="px-4 py-3 text-xs text-gray-500">{{ biz.enumerator?.name || '—' }}</td>

              <!-- Actions -->
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-2">
                  <a
                    :href="`/businesses/${biz.id}`"
                    class="inline-flex items-center gap-1 text-xs text-orange-600 hover:text-orange-800 font-medium px-2 py-1 rounded hover:bg-orange-50 transition-colors"
                    title="हेर्नुस्"
                  >
                    <i class="fa-solid fa-eye"></i>
                  </a>
                  <a
                    v-if="['draft','rejected'].includes(biz.status)"
                    :href="`/businesses/${biz.id}/edit`"
                    class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700 font-medium px-2 py-1 rounded hover:bg-gray-100 transition-colors"
                    title="सम्पादन"
                  >
                    <i class="fa-solid fa-pen"></i>
                  </a>
                  <button
                    v-if="biz.status === 'draft'"
                    @click="submitBusiness(biz.id)"
                    class="inline-flex items-center gap-1 text-xs text-white bg-green-600 hover:bg-green-700 font-medium px-2.5 py-1 rounded transition-colors"
                    :disabled="submitting === biz.id"
                    title="पेस गर्नुस्"
                  >
                    <i v-if="submitting === biz.id" class="fa-solid fa-spinner fa-spin"></i>
                    <i v-else class="fa-solid fa-paper-plane"></i>
                    <span v-if="submitting !== biz.id">पेस</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Empty state -->
      <div v-if="!businesses.data?.length" class="py-16 text-center">
        <i class="fa-solid fa-store-slash text-5xl text-gray-200 mb-4 block"></i>
        <p class="text-gray-500 font-medium">कुनै व्यवसाय फेला परेन</p>
        <p class="text-xs text-gray-400 mt-1">फिल्टर परिवर्तन गर्नुस् वा नयाँ व्यवसाय थप्नुस्</p>
        <a
          href="/businesses/create"
          class="mt-4 inline-flex items-center gap-2 bg-orange-600 text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-orange-700 transition-colors"
        >
          <i class="fa-solid fa-plus"></i> नयाँ व्यवसाय थप्नुस्
        </a>
      </div>
    </div>

    <!-- Bulk toolbar -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-4 opacity-0"
      >
        <div v-if="selectedIds.size > 0"
          class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 bg-gray-900 text-white rounded-xl shadow-2xl px-5 py-3 min-w-[360px]">
          <span class="text-sm font-medium shrink-0">{{ selectedIds.size }} व्यवसाय छानिएको</span>
          <div class="flex-1 flex items-center gap-2 justify-end">
            <button v-if="canBulkSubmit"
              @click="bulkSubmit"
              :disabled="bulkProcessing"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-medium transition disabled:opacity-50">
              <i class="fa-solid fa-paper-plane text-[10px]"></i> पेस गर्नुस्
            </button>
            <button v-if="canBulkApprove"
              @click="bulkApprove"
              :disabled="bulkProcessing"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition disabled:opacity-50">
              <i class="fa-solid fa-check text-[10px]"></i> अनुमोदन
            </button>
            <button v-if="canBulkApprove"
              @click="openRejectModal"
              :disabled="bulkProcessing"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-medium transition disabled:opacity-50">
              <i class="fa-solid fa-xmark text-[10px]"></i> अस्वीकार
            </button>
            <button @click="clearSelection"
              class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded-lg text-xs transition">
              <i class="fa-solid fa-xmark text-[10px]"></i>
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Bulk reject modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div v-if="rejectModalOpen" class="fixed inset-0 z-[60] flex items-center justify-center">
          <div class="absolute inset-0 bg-black/40" @click="rejectModalOpen = false"></div>
          <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">
              <i class="fa-solid fa-xmark-circle text-red-500 mr-1"></i>
              {{ selectedIds.size }} व्यवसाय अस्वीकार गर्नुहोस्
            </h3>
            <div class="flex flex-wrap gap-2 mb-3">
              <button v-for="chip in rejectChips" :key="chip"
                @click="rejectReason = chip"
                :class="['px-2.5 py-1 rounded-full text-xs border transition',
                  rejectReason === chip ? 'bg-red-600 text-white border-red-600' : 'border-gray-300 text-gray-600 hover:border-red-400']">
                {{ chip }}
              </button>
            </div>
            <textarea v-model="rejectReason" rows="3" placeholder="अस्वीकारको कारण (ऐच्छिक)..."
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none resize-none"></textarea>
            <div class="flex justify-end gap-2 mt-4">
              <button @click="rejectModalOpen = false"
                class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">रद्द गर्नुस्</button>
              <button @click="confirmBulkReject" :disabled="bulkProcessing"
                class="px-4 py-2 text-sm text-white bg-red-600 hover:bg-red-700 rounded-lg transition disabled:opacity-50">
                <i v-if="bulkProcessing" class="fa-solid fa-spinner fa-spin mr-1"></i>
                अस्वीकार गर्नुस्
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Pagination -->
    <div v-if="businesses.meta && businesses.meta.last_page > 1" class="flex items-center justify-between">
      <p class="text-xs text-gray-500">
        देखाइएको
        <span class="font-semibold">{{ businesses.meta.from }}</span>–<span class="font-semibold">{{ businesses.meta.to }}</span>
        / <span class="font-semibold">{{ businesses.meta.total }}</span>
      </p>
      <div class="flex items-center gap-1">
        <button
          :disabled="businesses.meta.current_page <= 1"
          @click="goToPage(businesses.meta.current_page - 1)"
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
              page === businesses.meta.current_page
                ? 'bg-orange-600 border-orange-600 text-white'
                : 'border-[#e6e7e9] text-gray-600 hover:bg-gray-50'
            ]"
          >{{ page }}</button>
        </template>

        <button
          :disabled="businesses.meta.current_page >= businesses.meta.last_page"
          @click="goToPage(businesses.meta.current_page + 1)"
          class="rounded-md border border-[#e6e7e9] px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          <i class="fa-solid fa-chevron-right"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { fieldLabel } from '@/utils/fieldLabels'

const page = usePage()
const surveyFrozen = computed(() => page.props.surveyFrozen && page.props.auth?.user?.role === 'enumerator')

const props = defineProps({
  businesses: { type: Object, default: () => ({ data: [], meta: {}, links: [] }) },
  wards:      { type: Array,  default: () => [] },
  filters:    { type: Object, default: () => ({ search: '', ward: '', status: '' }) },
})

const searchQuery  = ref(props.filters.search || '')
const wardFilter   = ref(props.filters.ward   || '')
const statusFilter = ref(props.filters.status || '')
const submitting   = ref(null)

// ── bulk ops state ─────────────────────────────────────────────────────────────
const selectedIds      = ref(new Set())
const bulkProcessing   = ref(false)
const rejectModalOpen  = ref(false)
const rejectReason     = ref('')

const rejectChips = ['अपूर्ण जानकारी', 'गलत वडा', 'नक्कल प्रविष्टि', 'प्रमाण नभएको']

const pageIds = computed(() => (props.businesses.data ?? []).map(b => b.id))
const allPageSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every(id => selectedIds.value.has(id)))
const someSelected    = computed(() => pageIds.value.some(id => selectedIds.value.has(id)))

const canBulkSubmit  = computed(() => page.props.auth?.user?.role !== undefined)
const canBulkApprove = computed(() => {
  const role = page.props.auth?.user?.role
  return ['ward_reviewer', 'ward_admin', 'municipal_admin', 'super_admin'].includes(role)
})

function toggleSelect(id) {
  const s = new Set(selectedIds.value)
  s.has(id) ? s.delete(id) : s.add(id)
  selectedIds.value = s
}

function toggleSelectAll() {
  if (allPageSelected.value) {
    const s = new Set(selectedIds.value)
    pageIds.value.forEach(id => s.delete(id))
    selectedIds.value = s
  } else {
    const s = new Set(selectedIds.value)
    pageIds.value.forEach(id => s.add(id))
    selectedIds.value = s
  }
}

function clearSelection() { selectedIds.value = new Set() }

function bulkSubmit() {
  if (!selectedIds.value.size) return
  bulkProcessing.value = true
  router.post('/businesses/bulk-submit', { ids: [...selectedIds.value] }, {
    onSuccess: () => { selectedIds.value = new Set() },
    onFinish:  () => { bulkProcessing.value = false },
  })
}

function bulkApprove() {
  if (!selectedIds.value.size) return
  bulkProcessing.value = true
  router.post('/businesses/bulk-approve', { ids: [...selectedIds.value] }, {
    onSuccess: () => { selectedIds.value = new Set() },
    onFinish:  () => { bulkProcessing.value = false },
  })
}

function openRejectModal() { rejectReason.value = ''; rejectModalOpen.value = true }

function confirmBulkReject() {
  if (!selectedIds.value.size) return
  bulkProcessing.value = true
  router.post('/businesses/bulk-reject', { ids: [...selectedIds.value], reason: rejectReason.value }, {
    onSuccess: () => { selectedIds.value = new Set(); rejectModalOpen.value = false },
    onFinish:  () => { bulkProcessing.value = false },
  })
}

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
    draft:        'bg-gray-100 text-gray-600',
    submitted:    'bg-yellow-100 text-yellow-700',
    ward_approved:'bg-blue-100 text-blue-700',
    rejected:     'bg-red-100 text-red-700',
    published:    'bg-green-100 text-green-700',
  }
  return map[s] ?? 'bg-gray-100 text-gray-600'
}

function statusLabel(s) {
  const map = {
    draft:        'मस्यौदा',
    submitted:    'पेस भएको',
    ward_approved:'वडा अनुमोदित',
    rejected:     'अस्वीकृत',
    published:    'प्रकाशित',
  }
  return map[s] ?? s
}

// ── filters ───────────────────────────────────────────────────────────────────

function applyFilters() {
  router.get(
    '/businesses',
    { search: searchQuery.value, ward: wardFilter.value, status: statusFilter.value },
    { preserveState: true, replace: true },
  )
}

watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(applyFilters, 400)
})

function clearFilters() {
  searchQuery.value  = ''
  wardFilter.value   = ''
  statusFilter.value = ''
  applyFilters()
}

// ── pagination ────────────────────────────────────────────────────────────────

function goToPage(page) {
  router.get(
    '/businesses',
    { search: searchQuery.value, ward: wardFilter.value, status: statusFilter.value, page },
    { preserveState: true, replace: true },
  )
}

const visiblePages = computed(() => {
  const meta = props.businesses.meta
  if (!meta) return []
  const current = meta.current_page
  const last    = meta.last_page
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

function submitBusiness(id) {
  if (!confirm('के तपाईं यो व्यवसाय पेस गर्न निश्चित हुनुहुन्छ?')) return
  submitting.value = id
  router.post(`/businesses/${id}/submit`, {}, {
    onFinish: () => { submitting.value = null },
  })
}
</script>
