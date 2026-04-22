<template>
  <div class="space-y-4">

    <!-- Page header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-[15px] font-semibold text-[#1d273b]">भूमिका अनुमति व्यवस्थापन</h1>
        <p class="text-[11px] text-[#9aa0ac] mt-0.5">Role Permissions — configure what each role can access</p>
      </div>
      <button
        @click="save"
        :disabled="!isDirty || saving"
        class="inline-flex items-center gap-2 h-9 px-4 bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-50 disabled:cursor-not-allowed text-white text-[13px] font-medium rounded transition-colors">
        <i :class="saving ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-floppy-disk'" class="text-[11px]"></i>
        {{ saving ? 'सुरक्षित हुँदैछ...' : (activeRole ? currentRoleLabel + ' सुरक्षित गर्नुहोस्' : 'सुरक्षित गर्नुहोस्') }}
      </button>
    </div>

    <div class="flex gap-4 items-start">

      <!-- Role tabs (left column) -->
      <div class="w-52 shrink-0 bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
        <div class="px-3 py-2.5 border-b border-[#e6e7e9] bg-gray-50/60">
          <p class="text-[10px] font-semibold text-[#9aa0ac] uppercase tracking-[0.12em]">भूमिकाहरू</p>
        </div>
        <div class="p-1.5 space-y-0.5">
          <button
            v-for="role in props.roles" :key="role.name"
            @click="selectRole(role.name)"
            :class="[
              'w-full text-left px-3 py-2.5 rounded flex items-center gap-2.5 transition-colors text-[13px] font-medium',
              activeRole === role.name
                ? 'bg-[#206bc4] text-white shadow-sm'
                : 'text-[#626976] hover:bg-gray-50',
            ]">
            <i :class="[role.icon, 'text-[12px] w-4 text-center shrink-0']"></i>
            <span class="flex-1 truncate">{{ role.label }}</span>
            <span v-if="isDirtyRole(role.name)"
              :class="['w-1.5 h-1.5 rounded-full shrink-0', activeRole === role.name ? 'bg-yellow-300' : 'bg-[#206bc4]']"
              title="Unsaved changes" />
          </button>
        </div>
      </div>

      <!-- Permission matrix (right column) -->
      <div class="flex-1 min-w-0 space-y-3">

        <!-- Role summary bar -->
        <div v-if="activeRole" class="bg-white rounded-md border border-[#e6e7e9] px-4 py-3 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-md bg-[#206bc4] flex items-center justify-center shrink-0">
              <i :class="[currentRoleIcon, 'text-white text-[13px]']"></i>
            </div>
            <div>
              <p class="text-[14px] font-semibold text-[#1d273b]">{{ currentRoleLabel }}</p>
              <p class="text-[11px] text-[#9aa0ac]">
                {{ grantedCount }} / {{ totalPermCount }} अनुमति दिइएको
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button @click="grantAll"
              class="h-8 px-3 text-[12px] font-medium text-[#206bc4] border border-[#206bc4]/30 rounded hover:bg-[#206bc4]/8 transition-colors">
              सबै दिनुहोस्
            </button>
            <button @click="revokeAll"
              class="h-8 px-3 text-[12px] font-medium text-red-600 border border-red-200 rounded hover:bg-red-50 transition-colors">
              सबै हटाउनुहोस्
            </button>
          </div>
        </div>

        <!-- Permission groups -->
        <div v-for="group in props.groups" :key="group.key"
          class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">

          <!-- Group header -->
          <div class="px-4 py-2.5 border-b border-[#e6e7e9] bg-gray-50/60 flex items-center justify-between">
            <span class="text-[13px] font-semibold text-[#1d273b] flex items-center gap-2">
              <i :class="[group.icon, 'text-[#206bc4] text-[11px]']"></i>
              {{ group.label }}
            </span>
            <div class="flex items-center gap-3">
              <span class="text-[11px] text-[#9aa0ac]">
                {{ groupGrantedCount(group) }} / {{ Object.keys(group.perms).length }}
              </span>
              <!-- Group toggle checkbox -->
              <button @click="toggleGroup(group)"
                :class="[
                  'w-5 h-5 rounded border-2 flex items-center justify-center transition-colors shrink-0',
                  isGroupAll(group)
                    ? 'bg-[#206bc4] border-[#206bc4]'
                    : isGroupPartial(group)
                      ? 'bg-[#206bc4]/20 border-[#206bc4]'
                      : 'border-[#e6e7e9] hover:border-[#206bc4]',
                ]">
                <i v-if="isGroupAll(group)" class="fa-solid fa-check text-white text-[9px]"></i>
                <i v-else-if="isGroupPartial(group)" class="fa-solid fa-minus text-[#206bc4] text-[9px]"></i>
              </button>
            </div>
          </div>

          <!-- Permission rows -->
          <div class="divide-y divide-[#e6e7e9]">
            <div
              v-for="(meta, perm) in group.perms" :key="perm"
              class="flex items-center justify-between px-4 py-2.5 hover:bg-[#f4f6fb]/60 transition-colors">
              <div>
                <p class="text-[13px] font-medium text-[#1d273b]">{{ meta.label }}</p>
                <p class="text-[11px] text-[#9aa0ac] font-mono">{{ perm }}</p>
              </div>
              <!-- Toggle switch -->
              <button @click="togglePerm(perm)"
                :class="[
                  'relative inline-flex h-5 w-9 items-center rounded-full transition-colors shrink-0',
                  hasPerm(perm) ? 'bg-[#206bc4]' : 'bg-gray-200',
                ]">
                <span :class="[
                  'inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform',
                  hasPerm(perm) ? 'translate-x-[18px]' : 'translate-x-[3px]',
                ]" />
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  roles:     { type: Array, required: true },
  groups:    { type: Array, required: true },
  rolePerms: { type: Object, required: true },
})

// ── State ──────────────────────────────────────────────────────────
const activeRole = ref(props.roles[0]?.name ?? null)
const saving     = ref(false)

// Local editable copy: { roleName: Set<perm> }
const localPerms = reactive({})
// Original snapshot for dirty detection
const origPerms  = reactive({})

props.roles.forEach(r => {
  localPerms[r.name] = new Set(props.rolePerms[r.name] ?? [])
  origPerms[r.name]  = new Set(props.rolePerms[r.name] ?? [])
})

// ── Computed ───────────────────────────────────────────────────────
const currentRoleLabel = computed(() =>
  props.roles.find(r => r.name === activeRole.value)?.label ?? ''
)
const currentRoleIcon = computed(() =>
  props.roles.find(r => r.name === activeRole.value)?.icon ?? 'fa-solid fa-user'
)

const totalPermCount = computed(() =>
  props.groups.reduce((a, g) => a + Object.keys(g.perms).length, 0)
)

const grantedCount = computed(() =>
  activeRole.value ? (localPerms[activeRole.value]?.size ?? 0) : 0
)

const isDirty = computed(() =>
  props.roles.some(r => isDirtyRole(r.name))
)

// ── Methods ────────────────────────────────────────────────────────
function selectRole(name) { activeRole.value = name }

function isDirtyRole(name) {
  const loc = localPerms[name]
  const org = origPerms[name]
  if (!loc || !org || loc.size !== org.size) return true
  for (const p of loc) if (!org.has(p)) return true
  return false
}

function hasPerm(perm) {
  return localPerms[activeRole.value]?.has(perm) ?? false
}

function togglePerm(perm) {
  const set = localPerms[activeRole.value]
  if (!set) return
  set.has(perm) ? set.delete(perm) : set.add(perm)
  // Trigger reactivity
  localPerms[activeRole.value] = new Set(set)
}

function groupGrantedCount(group) {
  const set = localPerms[activeRole.value]
  if (!set) return 0
  return Object.keys(group.perms).filter(p => set.has(p)).length
}

function isGroupAll(group) {
  return Object.keys(group.perms).every(p => hasPerm(p))
}

function isGroupPartial(group) {
  const keys = Object.keys(group.perms)
  const count = keys.filter(p => hasPerm(p)).length
  return count > 0 && count < keys.length
}

function toggleGroup(group) {
  const allChecked = isGroupAll(group)
  const set = localPerms[activeRole.value]
  if (!set) return
  Object.keys(group.perms).forEach(p => allChecked ? set.delete(p) : set.add(p))
  localPerms[activeRole.value] = new Set(set)
}

function grantAll() {
  const all = new Set()
  props.groups.forEach(g => Object.keys(g.perms).forEach(p => all.add(p)))
  localPerms[activeRole.value] = all
}

function revokeAll() {
  localPerms[activeRole.value] = new Set()
}

function save() {
  if (!activeRole.value || saving.value) return
  saving.value = true
  router.patch('/admin/role-permissions', {
    role:        activeRole.value,
    permissions: [...(localPerms[activeRole.value] ?? [])],
  }, {
    onSuccess: () => {
      // Update snapshot
      origPerms[activeRole.value] = new Set(localPerms[activeRole.value])
      saving.value = false
    },
    onError: () => { saving.value = false },
  })
}
</script>
