<template>
  <div class="max-w-4xl mx-auto space-y-4">

    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Link href="/review" class="w-8 h-8 rounded bg-white border border-[#e6e7e9] flex items-center justify-center text-gray-500 hover:bg-gray-50 transition-colors">
          <i class="fa-solid fa-arrow-left text-xs"></i>
        </Link>
        <div>
          <h2 class="text-base font-bold text-gray-800">व्यवसाय समीक्षा अवस्था</h2>
          <p class="text-xs text-gray-500">{{ businesses.meta?.total ?? 0 }} रेकर्ड पेस भएका</p>
        </div>
      </div>
      <span class="px-3 py-1.5 bg-amber-100 text-amber-700 rounded-full text-xs font-semibold">
        <i class="fa-solid fa-clock mr-1"></i>{{ businesses.meta?.total ?? 0 }} बाँकी
      </span>
    </div>

    <!-- Ward filter + Select-all bar -->
    <div class="bg-white border border-[#e6e7e9] rounded-md px-4 py-2.5 flex flex-wrap items-center gap-3">
      <label class="flex items-center gap-2 cursor-pointer select-none shrink-0">
        <input type="checkbox"
          :checked="allPageSelected"
          :indeterminate="someSelected && !allPageSelected"
          @change="toggleSelectAll"
          class="w-4 h-4 rounded border-gray-300 text-[#206bc4] focus:ring-[#206bc4]/30 cursor-pointer"
        />
        <span class="text-xs font-medium text-gray-600">
          {{ selectedIds.size > 0 ? `${selectedIds.size} छानिएको` : 'सबै छान्नुहोस्' }}
        </span>
      </label>
      <div class="h-4 w-px bg-[#e6e7e9] shrink-0"></div>
      <div v-if="wards.length > 1" class="flex items-center gap-2">
        <label class="text-xs text-gray-500 shrink-0">वडा:</label>
        <select v-model="wardFilter" @change="applyWardFilter"
          class="h-8 rounded border border-[#e6e7e9] bg-white px-2 text-xs text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/30 outline-none">
          <option value="">सबै वडा</option>
          <option v-for="w in wards" :key="w.id" :value="w.id">वडा {{ w.ward_number }}</option>
        </select>
        <button v-if="wardFilter" @click="wardFilter=''; applyWardFilter()"
          class="h-8 w-8 flex items-center justify-center rounded border border-[#e6e7e9] text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors">
          <i class="fa-solid fa-xmark text-[11px]"></i>
        </button>
      </div>
    </div>

    <!-- List -->
    <div v-if="businesses.data?.length > 0" class="space-y-3">
      <div v-for="biz in businesses.data" :key="biz.id"
        class="bg-white rounded-md border shadow-sm overflow-hidden transition-colors"
        :class="selectedIds.has(biz.id) ? 'border-[#206bc4]/40 ring-1 ring-[#206bc4]/20' : 'border-[#e6e7e9]'">

        <div class="p-4">
          <div class="flex items-start gap-3">
            <label class="mt-0.5 cursor-pointer shrink-0">
              <input type="checkbox" :checked="selectedIds.has(biz.id)" @change="toggleSelect(biz.id)"
                class="w-4 h-4 rounded border-gray-300 text-[#206bc4] focus:ring-[#206bc4]/30 cursor-pointer" />
            </label>
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                    <span class="font-mono text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded">{{ biz.unique_code }}</span>
                    <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">पेस भएको</span>
                    <span v-if="biz.is_registered" class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">
                      <i class="fa-solid fa-check mr-0.5 text-[10px]"></i>दर्ता भएको
                    </span>
                    <span v-if="biz.completeness" :class="['inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full', completenessClass(biz.completeness.score)]"
                      :title="`${biz.completeness.filled}/${biz.completeness.total} फिल्ड भरिएको`">
                      <i class="fa-solid fa-circle-half-stroke text-[9px]"></i>{{ biz.completeness.score }}%
                    </span>
                  </div>
                  <p class="font-semibold text-gray-900 text-sm">{{ biz.business_name }}</p>
                  <div class="mt-2 grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-1 text-xs text-gray-500">
                    <span v-if="biz.member"><i class="fa-solid fa-user text-gray-300 mr-1"></i>{{ biz.member.full_name }}</span>
                    <span v-if="biz.household"><i class="fa-solid fa-house text-gray-300 mr-1"></i>{{ biz.household.unique_code }}</span>
                    <span><i class="fa-solid fa-location-dot text-gray-300 mr-1"></i>वडा {{ biz.ward?.ward_number ?? '—' }}</span>
                    <span v-if="biz.settlement"><i class="fa-solid fa-house-flag text-gray-300 mr-1"></i>{{ biz.settlement.name_np }}</span>
                    <span v-if="biz.business_type"><i class="fa-solid fa-tag text-gray-300 mr-1"></i>{{ fieldLabel('business_type', biz.business_type) }}</span>
                    <span v-if="biz.business_field"><i class="fa-solid fa-briefcase text-gray-300 mr-1"></i>{{ fieldLabel('business_field', biz.business_field) }}</span>
                    <span v-if="biz.submitted_at"><i class="fa-regular fa-clock text-gray-300 mr-1"></i>{{ biz.submitted_at }}</span>
                  </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <a :href="`/businesses/${biz.id}`" target="_blank"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-[#e6e7e9] text-gray-600 rounded-md text-xs font-medium hover:bg-gray-50 transition-colors">
                    <i class="fa-solid fa-eye"></i><span class="hidden sm:inline">हेर्नुहोस्</span>
                  </a>
                  <button @click="approve(biz)" :disabled="processing[biz.id]"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white rounded-md text-xs font-semibold hover:bg-green-700 disabled:opacity-50 transition-colors">
                    <i class="fa-solid fa-check"></i><span class="hidden sm:inline">अनुमोदन</span>
                  </button>
                  <button @click="togglePanel(biz.id, 'escalate')"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 text-white rounded-md text-xs font-semibold hover:bg-orange-600 transition-colors">
                    <i class="fa-solid fa-arrow-up"></i>
                  </button>
                  <button @click="togglePanel(biz.id, 'reject')"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-red-600 text-white rounded-md text-xs font-semibold hover:bg-red-700 transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Escalate panel -->
        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
          <div v-if="openPanel[biz.id] === 'escalate'" class="border-t border-orange-100 bg-orange-50 px-5 py-4">
            <p class="text-xs font-semibold text-orange-700 mb-2"><i class="fa-solid fa-arrow-up mr-1"></i>नगर प्रशासकलाई escalate गर्नुहोस्</p>
            <textarea v-model="comments[biz.id]" rows="2" placeholder="escalation को कारण (वैकल्पिक)..."
              class="w-full rounded-md border border-orange-200 px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 bg-white resize-none mb-2"></textarea>
            <div class="flex items-center gap-2">
              <button @click="escalate(biz)" :disabled="processing[biz.id]"
                class="px-4 py-1.5 bg-orange-500 text-white rounded-md text-xs font-semibold hover:bg-orange-600 disabled:opacity-50 transition-colors">
                <i class="fa-solid fa-arrow-up mr-1"></i>Escalate गर्नुहोस्
              </button>
              <button @click="closePanel(biz.id)" class="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700 transition-colors">रद्द गर्नुहोस्</button>
            </div>
          </div>
        </Transition>

        <!-- Reject panel -->
        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
          <div v-if="openPanel[biz.id] === 'reject'" class="border-t border-red-100 bg-red-50 px-5 py-4">
            <p class="text-xs font-semibold text-red-700 mb-2"><i class="fa-solid fa-circle-exclamation mr-1"></i>अस्वीकृतिको कारण <span class="text-red-500">*</span></p>
            <div class="flex flex-wrap gap-1.5 mb-2">
              <button v-for="reason in rejectReasons" :key="reason" type="button" @click="comments[biz.id] = reason"
                :class="['text-[11px] px-2.5 py-1 rounded-full border transition-colors', comments[biz.id] === reason ? 'bg-red-600 border-red-600 text-white' : 'bg-white border-red-200 text-red-700 hover:bg-red-100']">
                {{ reason }}
              </button>
            </div>
            <textarea v-model="comments[biz.id]" rows="2" placeholder="अस्वीकृतिको विस्तृत कारण लेख्नुहोस् वा माथिबाट छान्नुस्..."
              class="w-full rounded-md border border-red-200 px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 bg-white resize-none mb-2"></textarea>
            <div class="flex items-center gap-2">
              <button @click="reject(biz)" :disabled="!comments[biz.id] || processing[biz.id]"
                class="px-4 py-1.5 bg-red-600 text-white rounded-md text-xs font-semibold hover:bg-red-700 disabled:opacity-50 transition-colors">
                <i class="fa-solid fa-xmark mr-1"></i>अस्वीकृत गर्नुहोस्
              </button>
              <button @click="closePanel(biz.id)" class="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700 transition-colors">रद्द गर्नुहोस्</button>
            </div>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-16 text-center">
      <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-circle-check text-green-500 text-2xl"></i>
      </div>
      <p class="font-semibold text-gray-700">सबै व्यवसाय समीक्षा भइसकेको छ!</p>
      <p class="text-sm text-gray-400 mt-1">कुनै पेस गरिएको व्यवसाय बाँकी छैन।</p>
    </div>

    <!-- Pagination -->
    <div v-if="businesses.meta?.last_page > 1"
      class="flex items-center justify-between bg-white rounded-md border border-[#e6e7e9] shadow-sm px-5 py-3">
      <span class="text-xs text-gray-500">{{ businesses.meta.from }}–{{ businesses.meta.to }} / {{ businesses.meta.total }}</span>
      <div class="flex items-center gap-1">
        <Link v-if="businesses.links?.prev" :href="businesses.links.prev" class="px-3 py-1.5 rounded border border-[#e6e7e9] text-xs text-gray-600 hover:bg-gray-50 transition-colors">
          <i class="fa-solid fa-chevron-left"></i>
        </Link>
        <span class="px-3 py-1.5 text-xs text-gray-500">{{ businesses.meta.current_page }} / {{ businesses.meta.last_page }}</span>
        <Link v-if="businesses.links?.next" :href="businesses.links.next" class="px-3 py-1.5 rounded border border-[#e6e7e9] text-xs text-gray-600 hover:bg-gray-50 transition-colors">
          <i class="fa-solid fa-chevron-right"></i>
        </Link>
      </div>
    </div>

    <!-- Bulk action toolbar (sticky bottom) -->
    <Teleport to="body">
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 translate-y-4" enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-4">
        <div v-if="selectedIds.size > 0"
          class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-[#1d273b] text-white rounded-xl shadow-2xl flex items-center gap-3 px-5 py-3 min-w-max">
          <span class="flex items-center gap-1.5 text-sm font-semibold">
            <span class="bg-white/15 rounded-lg px-2.5 py-1 text-xs font-bold">{{ selectedIds.size }}</span>
            व्यवसाय छानिएको
          </span>
          <div class="h-5 w-px bg-white/20"></div>
          <button @click="bulkApprove" :disabled="bulkProcessing"
            class="flex items-center gap-1.5 px-4 py-1.5 bg-green-500 hover:bg-green-400 disabled:opacity-50 rounded-lg text-sm font-semibold transition-colors">
            <i class="fa-solid fa-check-double text-xs"></i>सबै अनुमोदन
          </button>
          <button @click="openBulkRejectModal" :disabled="bulkProcessing"
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
        <div v-if="bulkRejectModalOpen" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/50" @click.self="bulkRejectModalOpen = false">
          <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" @click.stop>
            <div class="px-5 py-4 border-b border-[#e6e7e9] flex items-center justify-between">
              <div>
                <h3 class="text-sm font-bold text-gray-900"><i class="fa-solid fa-xmark text-red-500 mr-1.5"></i>{{ selectedIds.size }} व्यवसाय अस्वीकृत गर्नुहोस्</h3>
                <p class="text-xs text-gray-500 mt-0.5">अस्वीकृतिको कारण अनिवार्य छ</p>
              </div>
              <button @click="bulkRejectModalOpen = false" class="text-gray-400 hover:text-gray-600 transition-colors"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="px-5 py-4 space-y-3">
              <div class="flex flex-wrap gap-1.5">
                <button v-for="reason in rejectReasons" :key="reason" type="button" @click="bulkRejectReason = reason"
                  :class="['text-[11px] px-2.5 py-1 rounded-full border transition-colors', bulkRejectReason === reason ? 'bg-red-600 border-red-600 text-white' : 'bg-white border-red-200 text-red-700 hover:bg-red-100']">
                  {{ reason }}
                </button>
              </div>
              <textarea v-model="bulkRejectReason" rows="3" placeholder="अस्वीकृतिको विस्तृत कारण लेख्नुहोस्..."
                class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 focus:border-red-400 resize-none"></textarea>
            </div>
            <div class="px-5 py-4 border-t border-[#e6e7e9] flex items-center justify-end gap-2">
              <button @click="bulkRejectModalOpen = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition-colors">रद्द गर्नुहोस्</button>
              <button @click="confirmBulkReject" :disabled="!bulkRejectReason.trim() || bulkProcessing"
                class="px-5 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 disabled:opacity-50 transition-colors">
                <i class="fa-solid fa-xmark mr-1"></i>{{ selectedIds.size }} वटा अस्वीकृत गर्नुहोस्
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { fieldLabel } from '@/utils/fieldLabels'

const props = defineProps({
  businesses: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  wards:      { type: Array,  default: () => [] },
  filters:    { type: Object, default: () => ({}) },
})

const wardFilter = ref(props.filters.ward_id ?? '')

function applyWardFilter() {
  const params = {}
  if (wardFilter.value) params.ward_id = wardFilter.value
  router.get('/review/businesses', params, { preserveState: true, replace: true })
}

function completenessClass(score) {
  if (score >= 90) return 'bg-green-100 text-green-700'
  if (score >= 75) return 'bg-yellow-100 text-yellow-700'
  if (score >= 50) return 'bg-amber-100 text-amber-700'
  return 'bg-red-100 text-red-700'
}

const rejectReasons = [
  'GPS स्थान भरिएको छैन', 'फोटो नम्बर भरिएको छैन', 'डुप्लिकेट प्रविष्टि',
  'व्यवसायको नाम भरिएको छैन', 'व्यवसायको प्रकार भरिएको छैन',
  'घरधुरी सम्पर्क भरिएको छैन', 'वडा नम्बर मेल खाँदैन', 'अपूर्ण विवरण',
]

const selectedIds     = ref(new Set())
const pageIds         = computed(() => (props.businesses.data ?? []).map(b => b.id))
const allPageSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every(id => selectedIds.value.has(id)))
const someSelected    = computed(() => pageIds.value.some(id => selectedIds.value.has(id)))

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

const openPanel  = ref({})
const comments   = ref({})
const processing = ref({})

function togglePanel(id, type) { openPanel.value[id] = openPanel.value[id] === type ? null : type; if (openPanel.value[id]) comments.value[id] = '' }
function closePanel(id) { openPanel.value[id] = null; comments.value[id] = '' }

function approve(biz) {
  processing.value[biz.id] = true
  router.patch(`/review/businesses/${biz.id}/approve`, {}, { onFinish: () => { processing.value[biz.id] = false } })
}
function reject(biz) {
  if (!comments.value[biz.id]) return
  processing.value[biz.id] = true
  router.patch(`/review/businesses/${biz.id}/reject`, { comment: comments.value[biz.id] }, {
    onFinish: () => { processing.value[biz.id] = false },
  })
}
function escalate(biz) {
  processing.value[biz.id] = true
  router.patch(`/review/businesses/${biz.id}/escalate`, { comment: comments.value[biz.id] ?? '' }, {
    onSuccess: () => closePanel(biz.id), onFinish: () => { processing.value[biz.id] = false },
  })
}

const bulkProcessing      = ref(false)
const bulkRejectModalOpen = ref(false)
const bulkRejectReason    = ref('')

function bulkApprove() {
  if (!confirm(`${selectedIds.value.size} व्यवसाय अनुमोदन गर्ने?`)) return
  bulkProcessing.value = true
  router.post('/review/businesses/bulk-approve', { ids: [...selectedIds.value] }, {
    onFinish: () => { bulkProcessing.value = false; clearSelection() },
  })
}
function openBulkRejectModal() { bulkRejectReason.value = ''; bulkRejectModalOpen.value = true }
function confirmBulkReject() {
  if (!bulkRejectReason.value.trim()) return
  bulkProcessing.value = true
  router.post('/review/businesses/bulk-reject', { ids: [...selectedIds.value], reason: bulkRejectReason.value }, {
    onFinish: () => { bulkProcessing.value = false; bulkRejectModalOpen.value = false; clearSelection() },
  })
}
</script>
