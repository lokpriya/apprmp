<template>
  <div class="space-y-4">

    <!-- Page header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-[15px] font-semibold text-[#1d273b]">प्रयोगकर्ता व्यवस्थापन</h1>
        <p class="text-[11px] text-[#9aa0ac] mt-0.5">User Access Control</p>
      </div>
      <Link href="/admin/users/create"
        class="inline-flex items-center gap-1.5 h-9 px-4 bg-[#206bc4] hover:bg-[#1a5ba8] text-white text-[13px] font-medium rounded transition-colors">
        <i class="fa-solid fa-plus text-[11px]"></i>
        नयाँ प्रयोगकर्ता
      </Link>
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div v-for="stat in stats" :key="stat.label"
        class="bg-white rounded-md border border-[#e6e7e9] px-4 py-3 flex items-center gap-3">
        <div :class="['w-8 h-8 rounded flex items-center justify-center shrink-0 text-xs', stat.color]">
          <i :class="stat.icon"></i>
        </div>
        <div>
          <p class="text-xl font-semibold text-[#1d273b] leading-tight">{{ stat.count }}</p>
          <p class="text-[11px] text-[#9aa0ac] leading-tight">{{ stat.label }}</p>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-md border border-[#e6e7e9] p-3 flex flex-wrap items-center gap-2">
      <div class="relative flex-1 min-w-[180px]">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[#9aa0ac] text-[11px] pointer-events-none"></i>
        <input v-model="filters.search" @input="debouncedFetch" type="text"
          placeholder="नाम वा इमेलले खोज्नुहोस्..."
          class="w-full pl-8 pr-3 h-9 rounded border border-[#e6e7e9] text-[13px] focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] outline-none" />
      </div>
      <select v-model="filters.role" @change="fetchUsers"
        class="h-9 px-3 rounded border border-[#e6e7e9] text-[13px] text-[#626976] focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] outline-none">
        <option value="">सबै भूमिका</option>
        <option value="super_admin">Super Admin</option>
        <option value="municipal_admin">Municipal Admin</option>
        <option value="ward_reviewer">Ward Reviewer</option>
        <option value="enumerator">Enumerator</option>
      </select>
      <select v-model="filters.active" @change="fetchUsers"
        class="h-9 px-3 rounded border border-[#e6e7e9] text-[13px] text-[#626976] focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] outline-none">
        <option value="">सबै स्थिति</option>
        <option value="1">सक्रिय</option>
        <option value="0">निष्क्रिय</option>
      </select>
      <button v-if="filters.search || filters.role || filters.active !== ''" @click="clearFilters"
        class="h-9 px-3 text-[12px] text-[#9aa0ac] hover:text-[#626976] flex items-center gap-1 rounded border border-[#e6e7e9] hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-xmark text-[10px]"></i> हटाउनुहोस्
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
      <table class="min-w-full">
        <thead>
          <tr class="border-b border-[#e6e7e9] bg-gray-50/60">
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]">प्रयोगकर्ता</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]">भूमिका</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em] hidden lg:table-cell">वडाहरू</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em] hidden md:table-cell">अन्तिम लगइन</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]">स्थिति</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]">कार्य</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[#e6e7e9]">
          <tr v-if="props.users.data.length === 0">
            <td colspan="6" class="px-4 py-12 text-center">
              <i class="fa-solid fa-users text-3xl text-gray-200 block mb-2"></i>
              <p class="text-[13px] text-[#9aa0ac]">कुनै प्रयोगकर्ता फेला परेन</p>
            </td>
          </tr>
          <tr v-for="user in props.users.data" :key="user.id"
            class="hover:bg-[#f4f6fb]/60 transition-colors group">

            <!-- User -->
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-md flex items-center justify-center text-[12px] font-bold shrink-0"
                  :class="roleColor(user.role).avatar">
                  {{ initials(user.name) }}
                </div>
                <div class="min-w-0">
                  <p class="text-[13px] font-semibold text-[#1d273b] truncate">{{ user.name }}</p>
                  <p class="text-[11px] text-[#9aa0ac] truncate">{{ user.email }}</p>
                  <p v-if="user.phone" class="text-[11px] text-[#9aa0ac] truncate">{{ user.phone }}</p>
                </div>
              </div>
            </td>

            <!-- Role -->
            <td class="px-4 py-3">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold"
                :class="roleColor(user.role).badge">
                <i :class="roleColor(user.role).icon + ' text-[9px]'"></i>
                {{ roleLabel(user.role) }}
              </span>
            </td>

            <!-- Wards -->
            <td class="px-4 py-3 hidden lg:table-cell">
              <span v-if="!user.assigned_wards.length" class="text-[12px] text-[#9aa0ac]">—</span>
              <div v-else class="flex flex-wrap gap-1">
                <span v-for="w in user.assigned_wards" :key="w"
                  class="px-1.5 py-0.5 bg-[#206bc4]/8 text-[#206bc4] rounded text-[11px] font-medium">
                  वडा {{ w }}
                </span>
              </div>
            </td>

            <!-- Last login -->
            <td class="px-4 py-3 hidden md:table-cell">
              <span v-if="user.last_login_at" class="text-[12px] text-[#626976]">{{ user.last_login_at }}</span>
              <span v-else class="text-[12px] text-[#9aa0ac]">कहिल्यै होइन</span>
            </td>

            <!-- Status toggle -->
            <td class="px-4 py-3">
              <button
                v-if="user.id !== currentUserId"
                @click="confirmToggle(user)"
                :title="user.is_active ? 'निष्क्रिय गर्नुहोस्' : 'सक्रिय गर्नुहोस्'"
                :class="[
                  'inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-medium transition-colors',
                  user.is_active
                    ? 'bg-green-50 text-green-700 hover:bg-red-50 hover:text-red-600'
                    : 'bg-gray-100 text-[#9aa0ac] hover:bg-green-50 hover:text-green-700',
                ]">
                <i :class="user.is_active ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle-xmark'" class="text-[10px]"></i>
                {{ user.is_active ? 'सक्रिय' : 'निष्क्रिय' }}
              </button>
              <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-green-50 text-green-700">
                <i class="fa-solid fa-circle-check text-[10px]"></i> सक्रिय
              </span>
            </td>

            <!-- Actions -->
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <!-- Edit -->
                <Link :href="`/admin/users/${user.id}/edit`"
                  class="w-8 h-8 flex items-center justify-center rounded border border-[#e6e7e9] text-[#626976] hover:bg-[#206bc4] hover:text-white hover:border-[#206bc4] transition-colors"
                  title="सम्पादन">
                  <i class="fa-solid fa-pen text-[11px]"></i>
                </Link>
                <!-- Reset password -->
                <button v-if="user.id !== currentUserId"
                  @click="openResetPwd(user)"
                  class="w-8 h-8 flex items-center justify-center rounded border border-[#e6e7e9] text-[#626976] hover:bg-amber-500 hover:text-white hover:border-amber-500 transition-colors"
                  title="पासवर्ड रिसेट">
                  <i class="fa-solid fa-key text-[11px]"></i>
                </button>
                <!-- Delete -->
                <button v-if="user.id !== currentUserId"
                  @click="confirmDelete(user)"
                  class="w-8 h-8 flex items-center justify-center rounded border border-[#e6e7e9] text-[#626976] hover:bg-red-500 hover:text-white hover:border-red-500 transition-colors"
                  title="हटाउनुहोस्">
                  <i class="fa-solid fa-trash-can text-[11px]"></i>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="props.users.last_page > 1"
        class="px-4 py-3 border-t border-[#e6e7e9] flex items-center justify-between">
        <span class="text-[12px] text-[#9aa0ac]">
          {{ props.users.from }}–{{ props.users.to }} / {{ props.users.total }} प्रयोगकर्ता
        </span>
        <div class="flex gap-1">
          <button v-for="link in props.users.links" :key="link.label"
            :disabled="!link.url || link.active"
            @click="link.url && router.get(link.url, {}, { preserveState: true })"
            class="min-w-[32px] h-8 px-2 rounded border text-[12px] transition-colors"
            :class="link.active
              ? 'bg-[#206bc4] text-white border-[#206bc4]'
              : link.url
                ? 'border-[#e6e7e9] text-[#626976] hover:bg-gray-50'
                : 'border-[#e6e7e9] text-[#9aa0ac] cursor-not-allowed'"
            v-html="link.label">
          </button>
        </div>
      </div>
    </div>

    <!-- ── Toggle Active Confirm modal ── -->
    <Teleport to="body">
      <div v-if="toggleTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
        <div class="bg-white rounded-md shadow-xl max-w-sm w-full p-6">
          <div :class="['w-12 h-12 rounded-md flex items-center justify-center mx-auto mb-4',
            toggleTarget.is_active ? 'bg-red-50' : 'bg-green-50']">
            <i :class="[toggleTarget.is_active ? 'fa-solid fa-ban text-red-500' : 'fa-solid fa-circle-check text-green-500', 'text-xl']"></i>
          </div>
          <h3 class="text-[15px] font-semibold text-[#1d273b] text-center mb-1">
            {{ toggleTarget.is_active ? 'निष्क्रिय गर्ने?' : 'सक्रिय गर्ने?' }}
          </h3>
          <p class="text-[13px] text-[#626976] text-center mb-5">
            <strong>{{ toggleTarget.name }}</strong> लाई
            {{ toggleTarget.is_active ? 'निष्क्रिय' : 'सक्रिय' }} गरिनेछ।
          </p>
          <div class="flex gap-3">
            <button @click="toggleTarget = null"
              class="flex-1 h-9 rounded border border-[#e6e7e9] text-[13px] font-medium text-[#626976] hover:bg-gray-50 transition-colors">
              रद्द
            </button>
            <button @click="doToggle"
              :class="['flex-1 h-9 rounded text-[13px] font-medium text-white transition-colors',
                toggleTarget.is_active ? 'bg-red-500 hover:bg-red-600' : 'bg-green-600 hover:bg-green-700']">
              {{ toggleTarget.is_active ? 'निष्क्रिय गर्नुहोस्' : 'सक्रिय गर्नुहोस्' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Reset Password modal ── -->
    <Teleport to="body">
      <div v-if="resetTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
        <div class="bg-white rounded-md shadow-xl max-w-sm w-full p-6">
          <div class="w-12 h-12 rounded-md bg-amber-50 flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-key text-amber-500 text-xl"></i>
          </div>
          <h3 class="text-[15px] font-semibold text-[#1d273b] text-center mb-1">पासवर्ड रिसेट</h3>
          <p class="text-[12px] text-[#9aa0ac] text-center mb-5">
            {{ resetTarget.name }} को लागि नयाँ पासवर्ड सेट गर्नुहोस्
          </p>
          <div class="space-y-3">
            <div>
              <label class="block text-[12px] font-medium text-[#626976] mb-1">नयाँ पासवर्ड</label>
              <div class="relative">
                <input v-model="pwdForm.password" :type="showPwd ? 'text' : 'password'"
                  class="w-full h-9 rounded border border-[#e6e7e9] px-3 pr-9 text-[13px] focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] outline-none" />
                <button type="button" @click="showPwd = !showPwd"
                  class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#9aa0ac] hover:text-[#626976]">
                  <i :class="showPwd ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye'" class="text-[12px]"></i>
                </button>
              </div>
            </div>
            <div>
              <label class="block text-[12px] font-medium text-[#626976] mb-1">पुनः पासवर्ड</label>
              <input v-model="pwdForm.password_confirmation" :type="showPwd ? 'text' : 'password'"
                class="w-full h-9 rounded border border-[#e6e7e9] px-3 text-[13px] focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] outline-none" />
            </div>
            <p v-if="pwdError" class="text-[11px] text-red-500">{{ pwdError }}</p>
          </div>
          <div class="flex gap-3 mt-5">
            <button @click="closeResetPwd"
              class="flex-1 h-9 rounded border border-[#e6e7e9] text-[13px] font-medium text-[#626976] hover:bg-gray-50 transition-colors">
              रद्द
            </button>
            <button @click="doResetPwd"
              class="flex-1 h-9 rounded bg-amber-500 hover:bg-amber-600 text-white text-[13px] font-medium transition-colors">
              रिसेट गर्नुहोस्
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Delete confirm modal ── -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
        <div class="bg-white rounded-md shadow-xl max-w-sm w-full p-6">
          <div class="w-12 h-12 rounded-md bg-red-50 flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-triangle-exclamation text-red-500 text-xl"></i>
          </div>
          <h3 class="text-[15px] font-semibold text-[#1d273b] text-center mb-1">प्रयोगकर्ता हटाउने?</h3>
          <p class="text-[13px] text-[#626976] text-center mb-5">
            <strong>{{ deleteTarget.name }}</strong> लाई स्थायी रूपमा हटाइनेछ।
          </p>
          <div class="flex gap-3">
            <button @click="deleteTarget = null"
              class="flex-1 h-9 rounded border border-[#e6e7e9] text-[13px] font-medium text-[#626976] hover:bg-gray-50 transition-colors">
              रद्द
            </button>
            <button @click="doDelete"
              class="flex-1 h-9 rounded bg-red-500 hover:bg-red-600 text-white text-[13px] font-medium transition-colors">
              हटाउनुहोस्
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  users:   { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const page          = usePage()
const currentUserId = page.props.auth?.user?.id

// ── Filters ────────────────────────────────────────────────────────
const filters = reactive({
  search: props.filters.search ?? '',
  role:   props.filters.role   ?? '',
  active: props.filters.active ?? '',
})

let debounceTimer = null
function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchUsers, 400)
}
function fetchUsers() {
  const params = {}
  if (filters.search) params.search = filters.search
  if (filters.role)   params.role   = filters.role
  if (filters.active !== '') params.active = filters.active
  router.get('/admin/users', params, { preserveState: true, replace: true })
}
function clearFilters() {
  filters.search = ''; filters.role = ''; filters.active = ''
  fetchUsers()
}

// ── Stats ──────────────────────────────────────────────────────────
const stats = computed(() => {
  const all    = props.users.total ?? 0
  const data   = props.users.data ?? []
  const active = data.filter(u => u.is_active).length
  const admins = data.filter(u => ['super_admin','municipal_admin'].includes(u.role)).length
  const enums  = data.filter(u => u.role === 'enumerator').length
  return [
    { label: 'जम्मा',      count: all,    icon: 'fa-solid fa-users',       color: 'bg-[#206bc4]/10 text-[#206bc4]' },
    { label: 'सक्रिय',    count: active,  icon: 'fa-solid fa-circle-check', color: 'bg-green-100 text-green-600' },
    { label: 'प्रशासक',  count: admins,   icon: 'fa-solid fa-shield-halved', color: 'bg-purple-100 text-purple-600' },
    { label: 'गणक',       count: enums,   icon: 'fa-solid fa-clipboard-user', color: 'bg-amber-100 text-amber-600' },
  ]
})

// ── Toggle Active ──────────────────────────────────────────────────
const toggleTarget = ref(null)
function confirmToggle(user) { toggleTarget.value = user }
function doToggle() {
  router.patch(`/admin/users/${toggleTarget.value.id}/toggle-active`, {}, {
    onSuccess: () => { toggleTarget.value = null },
  })
}

// ── Reset Password ─────────────────────────────────────────────────
const resetTarget = ref(null)
const pwdForm     = reactive({ password: '', password_confirmation: '' })
const pwdError    = ref('')
const showPwd     = ref(false)

function openResetPwd(user) {
  resetTarget.value = user
  pwdForm.password = ''
  pwdForm.password_confirmation = ''
  pwdError.value = ''
  showPwd.value = false
}
function closeResetPwd() { resetTarget.value = null }
function doResetPwd() {
  pwdError.value = ''
  if (pwdForm.password.length < 8) {
    pwdError.value = 'पासवर्ड कम्तीमा ८ अक्षर हुनुपर्छ।'; return
  }
  if (pwdForm.password !== pwdForm.password_confirmation) {
    pwdError.value = 'पासवर्ड मेल खाएन।'; return
  }
  router.patch(`/admin/users/${resetTarget.value.id}/reset-password`, {
    password: pwdForm.password,
    password_confirmation: pwdForm.password_confirmation,
  }, { onSuccess: closeResetPwd })
}

// ── Delete ─────────────────────────────────────────────────────────
const deleteTarget = ref(null)
function confirmDelete(user) { deleteTarget.value = user }
function doDelete() {
  router.delete(`/admin/users/${deleteTarget.value.id}`, {
    onSuccess: () => { deleteTarget.value = null },
  })
}

// ── Helpers ────────────────────────────────────────────────────────
function initials(name) {
  return (name ?? '?').split(' ').slice(0,2).map(w => w[0]).join('').toUpperCase()
}

const ROLE_META = {
  super_admin:     { badge: 'bg-purple-100 text-purple-700', avatar: 'bg-purple-100 text-purple-700', icon: 'fa-solid fa-crown' },
  municipal_admin: { badge: 'bg-[#206bc4]/10 text-[#206bc4]', avatar: 'bg-[#206bc4]/10 text-[#206bc4]', icon: 'fa-solid fa-shield-halved' },
  ward_reviewer:   { badge: 'bg-blue-100 text-blue-700',    avatar: 'bg-blue-100 text-blue-700',    icon: 'fa-solid fa-eye' },
  enumerator:      { badge: 'bg-amber-100 text-amber-700',  avatar: 'bg-amber-100 text-amber-700',  icon: 'fa-solid fa-clipboard-user' },
}
function roleColor(role) {
  return ROLE_META[role] ?? { badge: 'bg-gray-100 text-[#626976]', avatar: 'bg-gray-100 text-[#626976]', icon: 'fa-solid fa-user' }
}

const ROLE_LABELS = {
  super_admin: 'Super Admin', municipal_admin: 'Municipal Admin',
  ward_reviewer: 'Ward Reviewer', enumerator: 'Enumerator',
}
function roleLabel(role) { return ROLE_LABELS[role] ?? role }
</script>
