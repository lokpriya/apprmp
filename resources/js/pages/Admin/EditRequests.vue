<template>
  <div>
    <!-- Page header -->
    <div class="flex items-center gap-3 mb-4">
      <div class="flex items-center gap-2 flex-wrap min-w-0">
        <h1 class="text-[15px] font-semibold text-[#1d273b]">सम्पादन अनुरोधहरू</h1>
        <span v-if="counts.pending > 0"
          class="text-[11px] px-2 py-0.5 rounded-full font-semibold bg-red-100 text-red-700">
          {{ counts.pending }} बाँकी
        </span>
      </div>
    </div>

    <!-- Status tabs -->
    <div class="flex gap-1 mb-4 border-b border-gray-200">
      <a
        v-for="tab in tabs"
        :key="tab.key"
        :href="`/admin/edit-requests?status=${tab.key}`"
        :class="[
          'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
          activeStatus === tab.key
            ? 'border-[#206bc4] text-[#206bc4]'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
        ]"
      >
        {{ tab.label }}
        <span :class="[
          'ml-1.5 text-[11px] px-1.5 py-0.5 rounded-full font-semibold',
          tab.key === 'pending'  ? 'bg-red-100 text-red-700' :
          tab.key === 'approved' ? 'bg-green-100 text-green-700' :
                                   'bg-gray-100 text-gray-500'
        ]">{{ counts[tab.key] ?? 0 }}</span>
      </a>
    </div>

    <!-- Empty state -->
    <div v-if="requests.data.length === 0"
      class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
      <div class="w-14 h-14 rounded-xl bg-gray-50 flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-bell-slash text-gray-300 text-2xl"></i>
      </div>
      <p class="text-gray-500 font-medium">कुनै अनुरोध छैन</p>
      <p class="text-sm text-gray-400 mt-1">
        {{ activeStatus === 'pending' ? 'हाल कुनै सम्पादन अनुरोध प्राप्त भएको छैन।' : 'यस श्रेणीमा कुनै अनुरोध छैन।' }}
      </p>
    </div>

    <!-- Requests list -->
    <div v-else class="space-y-3">
      <div
        v-for="req in requests.data"
        :key="req.id"
        class="bg-white rounded-xl border border-gray-200 shadow-sm p-5"
      >
        <div class="flex items-start gap-4">

          <!-- Type icon -->
          <div :class="['w-10 h-10 rounded-xl flex items-center justify-center shrink-0', typeColor(req.resource_label)]">
            <i :class="['text-sm', typeIcon(req.resource_label)]"></i>
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <span :class="['text-[11px] px-2 py-0.5 rounded-full font-semibold', statusBadge(req.status)]">
                {{ statusLabel(req.status) }}
              </span>
              <span class="text-[13px] font-semibold text-gray-800">{{ req.resource_label }}</span>
              <a :href="req.record_url"
                class="font-mono text-[11px] bg-blue-50 text-blue-600 px-2 py-0.5 rounded hover:bg-blue-100 transition-colors">
                {{ req.record_code }}
              </a>
            </div>

            <p class="text-sm text-gray-700 mb-2">
              <span class="font-medium text-gray-500">कारण:</span> {{ req.reason }}
            </p>

            <div v-if="req.admin_note" class="text-sm text-gray-600 mb-2 bg-gray-50 rounded px-3 py-1.5">
              <span class="font-medium text-gray-500">प्रशासकको टिप्पणी:</span> {{ req.admin_note }}
            </div>

            <div class="flex items-center gap-4 text-[11px] text-gray-400 flex-wrap">
              <span><i class="fa-solid fa-user mr-1"></i>{{ req.requester?.name ?? '—' }}</span>
              <span v-if="req.reviewer"><i class="fa-solid fa-user-check mr-1"></i>{{ req.reviewer.name }}</span>
              <span><i class="fa-solid fa-clock mr-1"></i>{{ formatDate(req.created_at) }}</span>
            </div>
          </div>

          <!-- Actions (pending only) -->
          <div v-if="req.status === 'pending'" class="flex flex-col gap-2 shrink-0">
            <button @click="openAction(req, 'approve')"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-green-500 hover:bg-green-600 text-white text-xs font-medium transition-colors">
              <i class="fa-solid fa-check"></i> स्वीकृत
            </button>
            <button @click="openAction(req, 'reject')"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-red-200 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-medium transition-colors">
              <i class="fa-solid fa-xmark"></i> अस्वीकार
            </button>
            <a :href="req.record_url"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-200 hover:bg-gray-50 text-gray-600 text-xs font-medium transition-colors">
              <i class="fa-solid fa-eye"></i> हेर्नुस्
            </a>
          </div>

          <!-- View link for reviewed -->
          <div v-else class="shrink-0">
            <a :href="req.record_url"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-200 hover:bg-gray-50 text-gray-600 text-xs font-medium transition-colors">
              <i class="fa-solid fa-eye"></i> हेर्नुस्
            </a>
          </div>

        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="requests.last_page > 1" class="flex items-center justify-center gap-2 mt-6">
      <a
        v-for="link in requests.links"
        :key="link.label"
        :href="link.url ?? '#'"
        v-html="link.label"
        :class="[
          'px-3 py-1.5 rounded text-sm transition-colors',
          link.active
            ? 'bg-[#206bc4] text-white font-semibold'
            : link.url
              ? 'border border-gray-200 text-gray-600 hover:bg-gray-50'
              : 'border border-gray-100 text-gray-300 cursor-not-allowed'
        ]"
      />
    </div>
  </div>

  <!-- ── Approve / Reject Modal ────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="actionModal.show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
      @click.self="actionModal.show = false">
      <div class="bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-md mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
          <div :class="['w-10 h-10 rounded-xl flex items-center justify-center shrink-0',
            actionModal.type === 'approve' ? 'bg-green-50' : 'bg-red-50']">
            <i :class="['text-lg', actionModal.type === 'approve' ? 'fa-solid fa-check text-green-500' : 'fa-solid fa-xmark text-red-500']"></i>
          </div>
          <div>
            <h3 class="text-[15px] font-semibold text-gray-900">
              {{ actionModal.type === 'approve' ? 'अनुरोध स्वीकृत गर्नुस्' : 'अनुरोध अस्वीकार गर्नुस्' }}
            </h3>
            <p class="text-[12px] text-gray-400">
              {{ actionModal.req?.resource_label }}: <span class="font-mono">{{ actionModal.req?.record_code }}</span>
            </p>
          </div>
        </div>

        <p class="text-sm text-gray-600 mb-1">
          <span class="font-medium">कारण:</span> {{ actionModal.req?.reason }}
        </p>

        <div class="mt-3">
          <label class="block text-xs font-medium text-gray-500 mb-1">टिप्पणी (वैकल्पिक)</label>
          <textarea
            v-model="actionModal.note"
            rows="3"
            placeholder="प्रशासकीय टिप्पणी..."
            class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-300 resize-none"
          ></textarea>
        </div>

        <div class="flex gap-3 mt-4">
          <button @click="actionModal.show = false"
            class="flex-1 rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
            रद्द गर्नुस्
          </button>
          <button @click="submitAction" :disabled="actionLoading"
            :class="[
              'flex-1 rounded-md px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60',
              actionModal.type === 'approve' ? 'bg-green-500 hover:bg-green-600' : 'bg-red-500 hover:bg-red-600'
            ]">
            <i :class="actionLoading ? 'fa-solid fa-spinner fa-spin mr-1' : (actionModal.type === 'approve' ? 'fa-solid fa-check mr-1' : 'fa-solid fa-xmark mr-1')"></i>
            {{ actionModal.type === 'approve' ? 'स्वीकृत गर्नुस्' : 'अस्वीकार गर्नुस्' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  requests:     { type: Object, required: true },
  activeStatus: { type: String, default: 'pending' },
  counts:       { type: Object, default: () => ({ pending: 0, approved: 0, rejected: 0 }) },
})

const tabs = [
  { key: 'pending',  label: 'बाँकी' },
  { key: 'approved', label: 'स्वीकृत' },
  { key: 'rejected', label: 'अस्वीकार' },
  { key: 'all',      label: 'सबै' },
]

// ── Action modal ──────────────────────────────────────────────────────────────
const actionLoading = ref(false)
const actionModal = reactive({
  show: false,
  type: 'approve', // 'approve' | 'reject'
  req:  null,
  note: '',
})

function openAction(req, type) {
  actionModal.req  = req
  actionModal.type = type
  actionModal.note = ''
  actionModal.show = true
}

function submitAction() {
  if (!actionModal.req) return
  actionLoading.value = true
  const url = `/admin/edit-requests/${actionModal.req.id}/${actionModal.type}`
  router.patch(url, { admin_note: actionModal.note }, {
    onSuccess: () => { actionModal.show = false },
    onFinish:  () => { actionLoading.value = false },
  })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function statusBadge(status) {
  return status === 'pending'  ? 'bg-amber-100 text-amber-700'  :
         status === 'approved' ? 'bg-green-100 text-green-700'  :
                                 'bg-red-100 text-red-700'
}
function statusLabel(status) {
  return status === 'pending'  ? 'बाँकी'    :
         status === 'approved' ? 'स्वीकृत'  :
                                 'अस्वीकार'
}
function typeIcon(label) {
  return label === 'घरधुरी' ? 'fa-solid fa-house text-indigo-500'    :
         label === 'संस्था'  ? 'fa-solid fa-building text-teal-500'   :
                               'fa-solid fa-store text-orange-500'
}
function typeColor(label) {
  return label === 'घरधुरी' ? 'bg-indigo-50' :
         label === 'संस्था'  ? 'bg-teal-50'   :
                               'bg-orange-50'
}
function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('ne-NP', { dateStyle: 'medium', timeStyle: 'short' })
}
</script>
