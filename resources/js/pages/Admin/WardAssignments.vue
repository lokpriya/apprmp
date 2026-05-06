<template>
  <div class="space-y-4">

    <!-- Page header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-[15px] font-semibold text-[#1d273b]">वडा तोकाई व्यवस्थापन</h1>
        <p class="text-[11px] text-[#9aa0ac] mt-0.5">Ward Assignment Management</p>
      </div>
      <a href="/admin/users"
        class="inline-flex items-center gap-1.5 h-9 px-4 border border-[#e6e7e9] text-[13px] font-medium text-[#626976] rounded hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-users text-[11px]"></i>
        प्रयोगकर्ता सूची
      </a>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
      <div class="bg-white rounded-md border border-[#e6e7e9] px-4 py-3 flex items-center gap-3">
        <div class="w-8 h-8 rounded bg-amber-100 text-amber-600 flex items-center justify-center text-xs shrink-0">
          <i class="fa-solid fa-clipboard-user"></i>
        </div>
        <div>
          <p class="text-xl font-semibold text-[#1d273b] leading-tight">{{ enumerators.length }}</p>
          <p class="text-[11px] text-[#9aa0ac]">गणकहरू</p>
        </div>
      </div>
      <div class="bg-white rounded-md border border-[#e6e7e9] px-4 py-3 flex items-center gap-3">
        <div class="w-8 h-8 rounded bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs shrink-0">
          <i class="fa-solid fa-user-shield"></i>
        </div>
        <div>
          <p class="text-xl font-semibold text-[#1d273b] leading-tight">{{ wardAdmins.length }}</p>
          <p class="text-[11px] text-[#9aa0ac]">वडा प्रशासक</p>
        </div>
      </div>
      <div class="bg-white rounded-md border border-[#e6e7e9] px-4 py-3 flex items-center gap-3">
        <div class="w-8 h-8 rounded bg-blue-100 text-blue-600 flex items-center justify-center text-xs shrink-0">
          <i class="fa-solid fa-eye"></i>
        </div>
        <div>
          <p class="text-xl font-semibold text-[#1d273b] leading-tight">{{ reviewers.length }}</p>
          <p class="text-[11px] text-[#9aa0ac]">समीक्षकहरू</p>
        </div>
      </div>
      <div class="bg-white rounded-md border border-[#e6e7e9] px-4 py-3 flex items-center gap-3">
        <div class="w-8 h-8 rounded bg-[#206bc4]/10 text-[#206bc4] flex items-center justify-center text-xs shrink-0">
          <i class="fa-solid fa-map-pin"></i>
        </div>
        <div>
          <p class="text-xl font-semibold text-[#1d273b] leading-tight">{{ wards.length }}</p>
          <p class="text-[11px] text-[#9aa0ac]">वडाहरू</p>
        </div>
      </div>
      <div class="bg-white rounded-md border border-[#e6e7e9] px-4 py-3 flex items-center gap-3">
        <div class="w-8 h-8 rounded bg-red-100 text-red-500 flex items-center justify-center text-xs shrink-0">
          <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div>
          <p class="text-xl font-semibold text-[#1d273b] leading-tight">{{ unassignedCount }}</p>
          <p class="text-[11px] text-[#9aa0ac]">तोकाई नभएका</p>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-[#e6e7e9]">
      <button
        v-for="tab in tabs" :key="tab.key"
        @click="activeTab = tab.key"
        :class="[
          'px-4 py-2 text-[13px] font-medium border-b-2 -mb-px transition-colors',
          activeTab === tab.key
            ? 'border-[#206bc4] text-[#206bc4]'
            : 'border-transparent text-[#626976] hover:text-[#1d273b]',
        ]">
        {{ tab.label }}
        <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold"
          :class="activeTab === tab.key ? 'bg-[#206bc4]/10 text-[#206bc4]' : 'bg-gray-100 text-[#9aa0ac]'">
          {{ tab.count }}
        </span>
      </button>
    </div>

    <!-- User cards grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
      <div
        v-for="user in activeUsers"
        :key="user.id"
        class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-4 flex flex-col gap-3"
      >
        <!-- User info -->
        <div class="flex items-start gap-3">
          <div :class="['w-10 h-10 rounded-md flex items-center justify-center text-[13px] font-bold shrink-0',
            roleAvatar(user.role)]">
            {{ initials(user.name) }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <p class="text-[13px] font-semibold text-[#1d273b] truncate">{{ user.name }}</p>
              <span v-if="!user.is_active"
                class="shrink-0 text-[10px] px-1.5 py-0.5 rounded bg-red-50 text-red-500 font-medium">निष्क्रिय</span>
            </div>
            <p class="text-[11px] text-[#9aa0ac] truncate">{{ user.email }}</p>
          </div>
        </div>

        <!-- Current ward chips -->
        <div class="min-h-[28px]">
          <div v-if="localAssignments[user.id]?.length" class="flex flex-wrap gap-1">
            <span
              v-for="wid in localAssignments[user.id]"
              :key="wid"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-[#206bc4]/8 text-[#206bc4]">
              <i class="fa-solid fa-map-pin text-[9px]"></i>
              वडा {{ wardLabel(wid) }}
            </span>
          </div>
          <p v-else class="text-[12px] text-[#9aa0ac] italic">कुनै वडा तोकिएको छैन</p>
        </div>

        <!-- Assign button -->
        <button
          @click="openModal(user)"
          class="flex items-center justify-center gap-2 w-full rounded border border-[#206bc4]/30 bg-[#206bc4]/5 hover:bg-[#206bc4]/10 px-3 py-2 text-[12px] font-medium text-[#206bc4] transition-colors"
        >
          <i class="fa-solid fa-pen-to-square text-[11px]"></i>
          वडा तोक्नुहोस् / परिवर्तन गर्नुहोस्
        </button>
      </div>

      <!-- Empty state -->
      <div v-if="!activeUsers.length"
        class="col-span-full bg-white rounded-md border border-[#e6e7e9] py-12 text-center">
        <i class="fa-solid fa-users text-3xl text-gray-200 block mb-2"></i>
        <p class="text-[13px] text-[#9aa0ac]">यस भूमिकामा कुनै प्रयोगकर्ता छैन।</p>
      </div>
    </div>

    <!-- ── Ward Assignment Modal ── -->
    <Teleport to="body">
      <div v-if="modalUser"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
        @click.self="closeModal">
        <div class="bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-sm">
          <!-- Header -->
          <div class="flex items-center gap-3 px-5 py-4 border-b border-[#e6e7e9]">
            <div :class="['w-10 h-10 rounded-md flex items-center justify-center text-[13px] font-bold shrink-0',
              roleAvatar(modalUser.role)]">
              {{ initials(modalUser.name) }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[14px] font-semibold text-[#1d273b] truncate">{{ modalUser.name }}</p>
              <p class="text-[11px] text-[#9aa0ac]">{{ roleLabel(modalUser.role) }}</p>
            </div>
            <button @click="closeModal" class="w-7 h-7 flex items-center justify-center rounded text-[#9aa0ac] hover:bg-gray-100 transition-colors">
              <i class="fa-solid fa-xmark text-[13px]"></i>
            </button>
          </div>

          <!-- Ward checklist -->
          <div class="px-5 py-4">
            <p class="text-[12px] font-semibold text-[#626976] uppercase tracking-wide mb-3">
              वडाहरू छान्नुहोस्
            </p>
            <div class="grid grid-cols-2 gap-2">
              <label
                v-for="ward in wards"
                :key="ward.id"
                :class="[
                  'flex items-center gap-2.5 rounded-md border px-3 py-2.5 cursor-pointer transition-colors select-none',
                  modalWardIds.includes(ward.id)
                    ? 'border-[#206bc4] bg-[#206bc4]/5 text-[#206bc4]'
                    : 'border-[#e6e7e9] text-[#626976] hover:bg-gray-50',
                ]">
                <input
                  type="checkbox"
                  :value="ward.id"
                  v-model="modalWardIds"
                  class="w-3.5 h-3.5 rounded text-[#206bc4] accent-[#206bc4] shrink-0"
                />
                <span class="text-[13px] font-medium">वडा {{ ward.ward_number }}</span>
              </label>
            </div>

            <!-- Select all / none shortcuts -->
            <div class="flex gap-3 mt-3">
              <button @click="modalWardIds = wards.map(w => w.id)"
                class="text-[11px] text-[#206bc4] hover:underline">सबै छान्नुहोस्</button>
              <button @click="modalWardIds = []"
                class="text-[11px] text-[#9aa0ac] hover:underline">सबै हटाउनुहोस्</button>
              <span class="ml-auto text-[11px] text-[#9aa0ac]">{{ modalWardIds.length }} / {{ wards.length }}</span>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 px-5 pb-5">
            <button @click="closeModal"
              class="flex-1 h-9 rounded border border-[#e6e7e9] text-[13px] font-medium text-[#626976] hover:bg-gray-50 transition-colors">
              रद्द
            </button>
            <button @click="saveAssignment" :disabled="saving"
              class="flex-1 h-9 rounded bg-[#206bc4] hover:bg-[#1a5ba8] text-white text-[13px] font-medium transition-colors disabled:opacity-60">
              <i :class="saving ? 'fa-solid fa-spinner fa-spin mr-1' : 'fa-solid fa-floppy-disk mr-1'"></i>
              सुरक्षित
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'

const props = defineProps({
  users: { type: Array, required: true },
  wards: { type: Array, required: true },
})

// ── Local reactive assignment state (updated optimistically on save) ──
const localAssignments = reactive(
  Object.fromEntries(props.users.map(u => [u.id, [...u.assigned_ward_ids]]))
)

// ── Ward lookup map ────────────────────────────────────────────────
const wardMap = computed(() =>
  Object.fromEntries(props.wards.map(w => [w.id, w.ward_number]))
)
function wardLabel(wid) { return wardMap.value[wid] ?? wid }

// ── Tabs ───────────────────────────────────────────────────────────
const enumerators = computed(() => props.users.filter(u => u.role === 'enumerator'))
const reviewers   = computed(() => props.users.filter(u => u.role === 'ward_reviewer'))
const wardAdmins  = computed(() => props.users.filter(u => u.role === 'ward_admin'))
const unassignedCount = computed(() =>
  props.users.filter(u => !localAssignments[u.id]?.length).length
)

const activeTab = ref('enumerator')
const tabs = computed(() => [
  { key: 'enumerator',    label: 'गणकहरू',       count: enumerators.value.length },
  { key: 'ward_admin',    label: 'वडा प्रशासक',  count: wardAdmins.value.length },
  { key: 'ward_reviewer', label: 'समीक्षकहरू',   count: reviewers.value.length },
])
const activeUsers = computed(() =>
  props.users.filter(u => u.role === activeTab.value)
)

// ── Modal ──────────────────────────────────────────────────────────
const modalUser    = ref(null)
const modalWardIds = ref([])
const saving       = ref(false)

function openModal(user) {
  modalUser.value    = user
  modalWardIds.value = [...localAssignments[user.id]]
}
function closeModal() {
  modalUser.value = null
}

async function saveAssignment() {
  if (!modalUser.value) return
  saving.value = true
  const userId = modalUser.value.id
  const csrf   = document.querySelector('meta[name=csrf-token]')?.content ?? ''

  try {
    const res = await fetch(`/admin/users/${userId}/assign-wards`, {
      method:  'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
      body:    JSON.stringify({ ward_ids: modalWardIds.value }),
    })
    if (!res.ok) throw new Error('Server error')

    // Optimistic update
    localAssignments[userId] = [...modalWardIds.value]
    closeModal()
  } catch {
    alert('सुरक्षित गर्न सकिएन। कृपया पुनः प्रयास गर्नुहोस्।')
  } finally {
    saving.value = false
  }
}

// ── Helpers ────────────────────────────────────────────────────────
function initials(name) {
  return (name ?? '?').split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
}
const ROLE_LABELS = { enumerator: 'गणक', ward_admin: 'वडा प्रशासक', ward_reviewer: 'वडा समीक्षक' }
function roleLabel(role) { return ROLE_LABELS[role] ?? role }

const ROLE_AVATAR = {
  enumerator:   'bg-amber-100 text-amber-700',
  ward_admin:   'bg-indigo-100 text-indigo-700',
  ward_reviewer:'bg-blue-100 text-blue-700',
}
function roleAvatar(role) { return ROLE_AVATAR[role] ?? 'bg-gray-100 text-gray-600' }
</script>
