<template>
  <div class="space-y-4">
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
      <a href="/businesses/create"
        class="inline-flex items-center gap-1.5 h-9 px-4 bg-orange-600 hover:bg-orange-700 text-white rounded text-[13px] font-medium transition">
        <i class="fa-solid fa-plus text-[11px]"></i> नयाँ व्यवसाय
      </a>
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
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">कोड</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">व्यवसाय नाम</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">सदस्य</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">घरधुरी</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">क्षेत्र</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">वडा / टोल</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">अवस्था</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">गणक</th>
              <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">कार्यहरू</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#e6e7e9]">
            <tr
              v-for="biz in businesses.data"
              :key="biz.id"
              class="hover:bg-gray-50 transition-colors"
            >
              <!-- Code -->
              <td class="px-4 py-3">
                <span class="font-mono text-xs bg-orange-50 text-orange-700 px-2 py-1 rounded">{{ biz.unique_code }}</span>
              </td>

              <!-- Name + type -->
              <td class="px-4 py-3">
                <div class="font-medium text-gray-800">{{ biz.business_name || '—' }}</div>
                <div v-if="biz.business_type" class="text-xs text-gray-400">{{ biz.business_type }}</div>
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
                  {{ biz.business_field }}
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
import { router } from '@inertiajs/vue3'

const props = defineProps({
  businesses: { type: Object, default: () => ({ data: [], meta: {}, links: [] }) },
  wards:      { type: Array,  default: () => [] },
  filters:    { type: Object, default: () => ({ search: '', ward: '', status: '' }) },
})

const searchQuery  = ref(props.filters.search || '')
const wardFilter   = ref(props.filters.ward   || '')
const statusFilter = ref(props.filters.status || '')
const submitting   = ref(null)

let searchTimeout = null

const hasActiveFilters = computed(() => searchQuery.value || wardFilter.value || statusFilter.value)

// ── helpers ───────────────────────────────────────────────────────────────────

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
