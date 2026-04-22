<template>
  <div class="flex h-screen overflow-hidden bg-[#f4f6fb]">

    <!-- ═══════════════════════════════════════════════════════════════
         SIDEBAR
    ═══════════════════════════════════════════════════════════════ -->
    <aside
      :class="[
        'flex flex-col transition-all duration-300 ease-in-out shrink-0 z-30',
        collapsed ? 'w-[68px]' : 'w-60',
      ]"
      style="background: linear-gradient(180deg,#182433 0%,#1a2d45 60%,#182433 100%)"
    >
      <!-- Logo / Branding -->
      <div class="flex items-center gap-3 px-4 h-14 border-b border-[#0d1825]/60 shrink-0">
        <div class="w-9 h-9 rounded bg-[#206bc4] flex items-center justify-center text-white shrink-0">
          <i class="fa-solid fa-map-location-dot text-sm"></i>
        </div>
        <div v-if="!collapsed" class="overflow-hidden">
          <p class="text-[13px] font-semibold text-white leading-tight truncate">APPRMP</p>
          <p class="text-[11px] text-white/50 leading-tight truncate">
            {{ page.props.auth?.local_government ?? 'स्थानीय तह' }}
          </p>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 overflow-y-auto overflow-x-hidden py-3 px-2 space-y-0.5">

        <!-- ── Dashboard (pinned top) ── -->
        <template v-if="['municipal_admin','super_admin'].includes(userRole)">
          <a
            href="/admin"
            :class="[
              'flex items-center gap-3 rounded-md px-2.5 py-2.5 mb-2 transition-colors group',
              collapsed ? 'justify-center' : '',
              page.url === '/admin' || page.url === '/admin/'
                ? 'bg-[#206bc4] text-white shadow-md shadow-[#206bc4]/30'
                : 'bg-white/8 text-white hover:bg-white/15',
            ]"
            :title="collapsed ? 'ड्यासबोर्ड' : ''"
          >
            <div :class="[
              'w-9 h-9 rounded flex items-center justify-center shrink-0 transition-colors',
              page.url === '/admin' || page.url === '/admin/'
                ? 'bg-white/20 text-white'
                : 'bg-white/10 text-white/80 group-hover:bg-white/20',
            ]">
              <i class="fa-solid fa-gauge-high text-[13px]"></i>
            </div>
            <span v-if="!collapsed" class="text-[14px] font-semibold truncate leading-none flex-1">ड्यासबोर्ड</span>
          </a>
          <div class="h-px bg-white/10 mb-2"></div>
        </template>

        <!-- ── Data Entry ── -->
        <template v-if="['enumerator','municipal_admin','super_admin'].includes(userRole)">
          <div class="mb-1 mt-2">
            <p v-if="!collapsed" class="text-[10px] font-semibold text-white/35 uppercase tracking-[0.15em] px-2 mb-1">
              Data Entry
            </p>
            <div class="h-px bg-white/10 mb-2"></div>
          </div>
          <NavGroup
            icon="fa-solid fa-house"
            label="घरधुरी विवरण"
            :collapsed="collapsed"
            :children="[
              { href: '/households',        icon: 'fa-solid fa-list',              label: 'घरधुरी सूची' },
              { href: '/households/create', icon: 'fa-solid fa-house-circle-plus', label: 'नयाँ घरधुरी' },
            ]"
          />
          <NavGroup
            icon="fa-solid fa-building"
            label="संस्थागत विवरण"
            :collapsed="collapsed"
            :children="[
              { href: '/institutions',        icon: 'fa-solid fa-list',                     label: 'संस्था सूची' },
              { href: '/institutions/create', icon: 'fa-solid fa-building-circle-check',    label: 'नयाँ संस्था' },
            ]"
          />
          <NavGroup
            icon="fa-solid fa-store"
            label="व्यवसाय विवरण"
            :collapsed="collapsed"
            :children="[
              { href: '/businesses',        icon: 'fa-solid fa-list',        label: 'व्यवसाय सूची' },
              { href: '/businesses/create', icon: 'fa-solid fa-store-arrow-up', label: 'नयाँ व्यवसाय' },
            ]"
          />
        </template>

        <!-- ── Review and Publish ── -->
        <template v-if="['ward_reviewer','municipal_admin','super_admin'].includes(userRole)">
          <div class="mb-1 mt-3">
            <p v-if="!collapsed" class="text-[10px] font-semibold text-white/35 uppercase tracking-[0.15em] px-2 mb-1">
              Review and Publish
            </p>
            <div class="h-px bg-white/10 mb-2"></div>
          </div>
          <NavItem href="/review" icon="fa-solid fa-magnifying-glass-chart" label="समीक्षा अवस्था" :collapsed="collapsed" />
          <template v-if="['municipal_admin','super_admin'].includes(userRole)">
            <NavItem href="/admin/publish-queue" icon="fa-solid fa-paper-plane" label="प्रकाशन अवस्था" :collapsed="collapsed" />
          </template>
        </template>

        <!-- ── Administration ── -->
        <template v-if="['municipal_admin','super_admin'].includes(userRole)">
          <div class="mb-1 mt-3">
            <p v-if="!collapsed" class="text-[10px] font-semibold text-white/35 uppercase tracking-[0.15em] px-2 mb-1">
              Administration
            </p>
            <div class="h-px bg-white/10 mb-2"></div>
          </div>
          <NavItem href="/admin/bulk-import"             icon="fa-solid fa-file-import"                  label="बल्क आयात घर"      :collapsed="collapsed" :external="true" />
          <NavItem href="/admin/institution-bulk-import" icon="fa-solid fa-building-circle-arrow-right"  label="बल्क आयात संस्था"  :collapsed="collapsed" :external="true" />
          <NavItem href="/admin/business-bulk-import"    icon="fa-solid fa-store-slash"                   label="बल्क आयात व्यवसाय" :collapsed="collapsed" :external="true" />
        </template>

        <!-- ── Public Portal ── -->
        <div class="mb-1 mt-3">
          <div class="h-px bg-white/10 mb-2"></div>
        </div>
        <a href="/public" target="_blank"
          :class="['flex items-center gap-2.5 rounded px-2 py-1.5 group transition-colors text-white/65 hover:bg-white/6 hover:text-white/90', collapsed ? 'justify-center' : '']"
          :title="collapsed ? 'सार्वजनिक पोर्टल' : ''">
          <div class="w-8 h-8 rounded flex items-center justify-center shrink-0 bg-white/10 text-white/65 group-hover:bg-white/20 transition-colors">
            <i class="fa-solid fa-globe text-xs"></i>
          </div>
          <span v-if="!collapsed" class="text-[13px] font-medium truncate leading-none">सार्वजनिक पोर्टल</span>
          <i v-if="!collapsed" class="fa-solid fa-arrow-up-right-from-square text-[10px] text-white/35 ml-auto shrink-0"></i>
        </a>

      </nav>

      <!-- User section at bottom -->
      <div class="shrink-0 border-t border-[#0d1825]/60 p-2 space-y-1">

        <!-- Expanded: name + role + logout -->
        <div v-if="!collapsed" class="flex items-center gap-2 rounded bg-white/5 ring-1 ring-white/8 px-3 py-2.5">
          <div class="w-8 h-8 rounded bg-[#206bc4] flex items-center justify-center text-white text-xs font-bold shrink-0 uppercase">
            {{ userInitials }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-white text-[13px] font-semibold truncate leading-tight">{{ page.props.auth?.user?.name ?? '' }}</p>
            <p class="text-white/50 text-[11px] truncate leading-tight capitalize">{{ page.props.auth?.user?.role?.replace(/_/g,' ') ?? '' }}</p>
          </div>
          <button @click="logout"
            class="shrink-0 w-7 h-7 rounded bg-white/10 hover:bg-red-500/30 text-white/65 hover:text-red-300 flex items-center justify-center transition-colors"
            title="साइन आउट">
            <i class="fa-solid fa-right-from-bracket text-xs"></i>
          </button>
        </div>

        <!-- Collapsed: just avatar + logout icon stacked -->
        <template v-else>
          <div class="flex justify-center py-1">
            <div class="w-8 h-8 rounded bg-[#206bc4] flex items-center justify-center text-white text-xs font-bold uppercase">
              {{ userInitials }}
            </div>
          </div>
          <button @click="logout"
            class="w-full flex items-center justify-center bg-white/10 hover:bg-red-500/30 rounded h-8 text-white/65 hover:text-red-300 transition-colors"
            title="साइन आउट">
            <i class="fa-solid fa-right-from-bracket text-xs"></i>
          </button>
        </template>

        <!-- Collapse toggle -->
        <button @click="toggleCollapse"
          class="w-full flex items-center justify-center bg-white/10 hover:bg-white/20 rounded h-8 text-white/65 hover:text-white transition-colors"
          :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'">
          <i :class="['fa-solid text-sm', collapsed ? 'fa-angle-right' : 'fa-angle-left']"></i>
        </button>
      </div>
    </aside>

    <!-- ═══════════════════════════════════════════════════════════════
         MAIN COLUMN
    ═══════════════════════════════════════════════════════════════ -->
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden">

      <!-- HEADER -->
      <header class="bg-white border-b border-[#e6e7e9] h-14 flex items-center px-5 gap-4 shrink-0 z-20">
        <!-- Page Title -->
        <div class="flex-1 min-w-0">
          <h1 class="text-[15px] font-semibold text-[#1d273b] truncate">
            {{ page.props.title ?? '' }}
          </h1>
        </div>

        <!-- Notification bell (admin only — edit requests from ward reviewers) -->
        <div
          v-if="['municipal_admin','super_admin'].includes(userRole)"
          class="relative shrink-0"
        >
          <a
            href="/admin/edit-requests"
            :class="[
              'w-9 h-9 flex items-center justify-center rounded border transition relative',
              'border-[#e6e7e9] text-[#626976] hover:bg-gray-50'
            ]"
            title="सम्पादन अनुरोधहरू"
          >
            <i class="fa-solid fa-bell text-[14px]"></i>
            <span
              v-if="editRequestsCount > 0"
              class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center leading-none"
            >{{ editRequestsCount > 99 ? '99+' : editRequestsCount }}</span>
          </a>
        </div>

        <!-- Gear / Admin menu -->
        <div
          v-if="['municipal_admin','super_admin'].includes(userRole)"
          class="relative shrink-0"
          ref="gearContainer"
        >
          <button
            @click="gearOpen = !gearOpen"
            :class="[
              'w-9 h-9 flex items-center justify-center rounded border transition',
              gearOpen ? 'bg-[#206bc4] border-[#206bc4] text-white' : 'border-[#e6e7e9] text-[#626976] hover:bg-gray-50'
            ]"
            title="प्रशासन सेटिङ्ग"
          >
            <i class="fa-solid fa-gear text-[14px]"></i>
          </button>

          <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 translate-y-1 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-1 scale-95"
          >
            <div
              v-if="gearOpen"
              class="absolute right-0 top-full mt-2 w-52 bg-white rounded-md shadow-lg border border-[#e6e7e9] py-1 z-50"
            >
              <p class="px-4 py-2 text-[10px] font-semibold text-[#9aa0ac] uppercase tracking-wider border-b border-[#e6e7e9]">
                प्रशासन
              </p>
              <a href="/admin/edit-requests"
                class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-[#626976] hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-bell w-4 text-[#9aa0ac] text-center text-xs"></i>
                सम्पादन अनुरोध
                <span v-if="editRequestsCount > 0"
                  class="ml-auto text-[10px] bg-red-500 text-white px-1.5 py-0.5 rounded-full font-bold leading-none">
                  {{ editRequestsCount }}
                </span>
              </a>
              <a href="/admin/users"
                class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-[#626976] hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-users w-4 text-[#9aa0ac] text-center text-xs"></i>
                प्रयोगकर्ता
              </a>
              <a href="/admin/role-permissions"
                class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-[#626976] hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-lock w-4 text-[#9aa0ac] text-center text-xs"></i>
                भूमिका अनुमति
              </a>
              <a href="/admin/settings"
                class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-[#626976] hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-gear w-4 text-[#9aa0ac] text-center text-xs"></i>
                सेटिङ्ग
              </a>
              <a v-if="userRole === 'super_admin'" href="/admin/survey-options"
                class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-[#626976] hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-list-check w-4 text-[#9aa0ac] text-center text-xs"></i>
                सर्वेक्षण विकल्प
              </a>
            </div>
          </Transition>
        </div>

        <!-- Right section: user dropdown -->
        <div class="relative shrink-0" ref="dropdownContainer">
          <button
            @click="dropdownOpen = !dropdownOpen"
            class="flex items-center gap-2 border border-[#e6e7e9] rounded px-3 py-1.5 hover:bg-gray-50 transition"
          >
            <div class="w-7 h-7 rounded bg-[#206bc4] flex items-center justify-center text-white text-xs font-bold uppercase">
              {{ userInitials }}
            </div>
            <div class="hidden sm:block text-left">
              <p class="text-[13px] font-medium text-[#1d273b] leading-tight">{{ page.props.auth?.user?.name ?? '' }}</p>
              <p class="text-[11px] text-[#9aa0ac] leading-tight capitalize">{{ page.props.auth?.user?.role?.replace('_',' ') ?? '' }}</p>
            </div>
            <i class="fa-solid fa-chevron-down text-[#9aa0ac] text-xs ml-1"></i>
          </button>

          <!-- Dropdown -->
          <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 translate-y-1 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-1 scale-95"
          >
            <div
              v-if="dropdownOpen"
              class="absolute right-0 top-full mt-2 w-56 bg-white rounded-md shadow-lg border border-[#e6e7e9] py-1 z-50"
            >
              <!-- User info -->
              <div class="px-4 py-3 border-b border-[#e6e7e9]">
                <p class="text-[13px] font-semibold text-[#1d273b]">{{ page.props.auth?.user?.name ?? '' }}</p>
                <p class="text-[11px] text-[#9aa0ac] mt-0.5">{{ page.props.auth?.user?.email ?? '' }}</p>
                <span class="inline-flex items-center mt-1.5 px-2 py-0.5 rounded text-[11px] font-medium bg-indigo-100 text-indigo-700 capitalize">
                  {{ page.props.auth?.user?.role?.replace(/_/g,' ') ?? '' }}
                </span>
              </div>
              <!-- Profile -->
              <a href="/profile" class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-[#626976] hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-circle-user w-4 text-[#9aa0ac] text-center"></i>
                मेरो प्रोफाइल
              </a>
              <!-- Sign out -->
              <button @click="logout"
                class="w-full flex items-center gap-3 px-4 py-2.5 text-[13px] text-red-600 hover:bg-red-50 transition-colors">
                <i class="fa-solid fa-right-from-bracket w-4 text-center"></i>
                साइन आउट
              </button>
            </div>
          </Transition>
        </div>
      </header>

      <!-- Flash messages -->
      <div v-if="(flash.success && !dismissedFlash.success) || (flash.error && !dismissedFlash.error)"
        class="px-5 pt-4 shrink-0 space-y-2">
        <div
          v-if="flash.success && !dismissedFlash.success"
          class="flex items-start gap-3 bg-green-50 border border-green-200 text-green-800 rounded-md px-4 py-3 text-[13px]"
        >
          <i class="fa-solid fa-circle-check text-green-500 mt-0.5 shrink-0"></i>
          <span>{{ flash.success }}</span>
          <button @click="dismissFlash('success')" class="ml-auto text-green-500 hover:text-green-700 shrink-0">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        <div
          v-if="flash.error && !dismissedFlash.error"
          class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-md px-4 py-3 text-[13px]"
        >
          <i class="fa-solid fa-circle-exclamation text-red-500 mt-0.5 shrink-0"></i>
          <span>{{ flash.error }}</span>
          <button @click="dismissFlash('error')" class="ml-auto text-red-500 hover:text-red-700 shrink-0">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
      </div>

      <!-- Offline banner -->
      <div
        v-if="isOffline"
        class="flex items-center gap-2.5 px-4 py-2 bg-amber-500 text-white text-[13px] font-semibold shrink-0"
      >
        <i class="fa-solid fa-wifi text-white/80 text-sm"></i>
        <span class="flex-1">अफलाइन — इन्टरनेट जडान छैन। डेटा स्वतः सिङ्क हुनेछ।</span>
        <span
          v-if="pendingCount > 0"
          class="bg-white/20 text-white text-[11px] font-bold px-2 py-0.5 rounded-full"
        >{{ pendingCount }} बाँकी</span>
      </div>

      <!-- Pending badge (online but has unsynced items) -->
      <div
        v-if="!isOffline && pendingCount > 0"
        class="flex items-center gap-2.5 px-4 py-2 bg-blue-600 text-white text-[13px] font-semibold shrink-0"
      >
        <i class="fa-solid fa-cloud-arrow-up fa-beat text-white/80 text-sm"></i>
        <span class="flex-1">{{ pendingCount }} रेकर्ड सिङ्क हुँदैछ…</span>
      </div>

      <!-- Sync success toast -->
      <div
        v-if="syncToast"
        class="flex items-center gap-2.5 px-4 py-2 bg-green-600 text-white text-[13px] font-semibold shrink-0"
      >
        <i class="fa-solid fa-circle-check text-white/80 text-sm"></i>
        <span>{{ syncToast }}</span>
      </div>

      <!-- Main content -->
      <main class="flex-1 overflow-y-auto p-5">
        <slot />
      </main>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, h, Transition } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import { useSyncManager } from '@/utils/syncManager'

// ── Offline detection + sync ───────────────────────────────────────
const isOffline  = ref(!navigator.onLine)
const syncToast  = ref('')
let   _syncToastTimer = null

const { pendingCount, refreshCount, drainQueue } = useSyncManager()

async function handleOnline() {
  isOffline.value = false
  const result = await drainQueue()
  if (result?.synced > 0) {
    syncToast.value = `${result.synced} रेकर्ड सफलतापूर्वक सिङ्क भयो।`
    clearTimeout(_syncToastTimer)
    _syncToastTimer = setTimeout(() => { syncToast.value = '' }, 5000)
  }
}
function handleOffline() { isOffline.value = true }

// ── Sub-component: NavItem ─────────────────────────────────────────
// Uses a render function (returned from setup) to avoid template-string
// resolution issues with the Inertia <Link> component.
const NavItem = {
  props: {
    href:     { type: String,  required: true },
    icon:     { type: String,  required: true },
    label:    { type: String,  required: true },
    collapsed:{ type: Boolean, default: false },
    external: { type: Boolean, default: false },
  },
  setup(props) {
    const page = usePage()
    const isActive = computed(() => {
      if (props.external) return false
      const url = page.url
      if (props.href === '/admin') return url === '/admin' || url === '/admin/'
      return url.startsWith(props.href)
    })

    return () => {
      const active = isActive.value
      const tag    = props.external ? 'a' : Link

      const wrapClass = [
        'flex items-center gap-3 rounded px-2.5 py-2 group transition-colors',
        props.collapsed ? 'justify-center' : '',
        active ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/6 hover:text-white/90',
      ]
      const iconBoxClass = [
        'w-9 h-9 rounded flex items-center justify-center shrink-0 transition-colors',
        active ? 'bg-[#206bc4] text-white' : 'bg-white/10 text-white/65 group-hover:bg-white/20',
      ]

      return h(tag, { href: props.href, class: wrapClass, title: props.collapsed ? props.label : '' }, [
        h('div', { class: iconBoxClass }, [
          h('i', { class: [props.icon, 'text-[13px]'] }),
        ]),
        !props.collapsed
          ? h('span', { class: 'text-[14px] font-medium truncate leading-none flex-1' }, props.label)
          : null,
        (!props.collapsed && active)
          ? h('span', { class: 'w-1 h-4 rounded-full bg-[#206bc4] shrink-0' })
          : null,
      ])
    }
  },
}

// ── Sub-component: NavGroup (collapsible on hover) ─────────────────
const NavGroup = {
  props: {
    icon:      { type: String,  required: true },
    label:     { type: String,  required: true },
    collapsed: { type: Boolean, default: false },
    children:  { type: Array,   default: () => [] }, // [{ href, icon, label }]
  },
  setup(props) {
    const page    = usePage()
    const open    = ref(false)
    const groupEl = ref(null)
    let closeTimer = null

    const isChildActive = computed(() =>
      props.children.some(c => c.href !== '/' && page.url.startsWith(c.href))
    )

    function onEnter() { clearTimeout(closeTimer); open.value = true }
    function onLeave() { closeTimer = setTimeout(() => { open.value = false }, 200) }

    return () => {
      const active = isChildActive.value
      const isOpen = open.value

      const parentRowClass = [
        'flex items-center gap-3 rounded px-2.5 py-2 w-full cursor-default transition-colors select-none',
        props.collapsed ? 'justify-center' : '',
        (active || isOpen) ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/6 hover:text-white/90',
      ]
      const iconBoxClass = [
        'w-9 h-9 rounded flex items-center justify-center shrink-0 transition-colors',
        (active || isOpen) ? 'bg-[#206bc4] text-white' : 'bg-white/10 text-white/65',
      ]

      // Inline children for expanded sidebar
      const childEls = props.children.map(c => {
        const cActive = c.href !== '/' && page.url.startsWith(c.href)
        const childIconClass = [
          'w-7 h-7 rounded flex items-center justify-center shrink-0 transition-colors',
          cActive ? 'bg-[#206bc4]/70 text-white' : 'bg-white/10 text-white/55',
        ]
        return h(Link, {
          key: c.href,
          href: c.href,
          class: [
            'flex items-center gap-2.5 py-1.5 rounded text-[13px] transition-colors',
            props.collapsed ? 'justify-center px-2' : 'pl-10 pr-2.5',
            cActive ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/6 hover:text-white/90',
          ],
        }, () => [
          h('div', { class: childIconClass }, [
            h('i', { class: [c.icon, 'text-[11px]'] }),
          ]),
          !props.collapsed ? h('span', { class: 'truncate leading-none' }, c.label) : null,
        ])
      })

      // Fixed flyout panel when collapsed
      const flyout = (isOpen && props.collapsed && groupEl.value)
        ? h('div', {
            class: 'fixed z-[200] pl-2',
            style: {
              top:  groupEl.value.getBoundingClientRect().top + 'px',
              left: '68px',
            },
            onMouseenter: onEnter,
            onMouseleave: onLeave,
          }, [
            h('div', { class: 'bg-[#1a2d45] rounded-md border border-white/10 shadow-xl py-1 min-w-[180px]' }, [
              h('p', { class: 'text-[10px] text-white/40 uppercase tracking-wider px-3 py-1.5 border-b border-white/10' }, props.label),
              ...props.children.map(c => {
                const cActive = c.href !== '/' && page.url.startsWith(c.href)
                return h(Link, {
                  key: c.href,
                  href: c.href,
                  class: [
                    'flex items-center gap-2.5 px-3 py-2 text-[13px] transition-colors',
                    cActive ? 'text-white bg-white/10' : 'text-white/70 hover:bg-white/8 hover:text-white',
                  ],
                }, () => [
                  h('i', { class: [c.icon, 'text-xs w-4 text-center'] }),
                  h('span', {}, c.label),
                ])
              }),
            ]),
          ])
        : null

      return h('div', {
        ref: groupEl,
        onMouseenter: onEnter,
        onMouseleave: onLeave,
      }, [
        // Parent row
        h('div', { class: parentRowClass }, [
          h('div', { class: iconBoxClass }, [
            h('i', { class: [props.icon, 'text-[13px]'] }),
          ]),
          !props.collapsed
            ? h('span', { class: 'text-[14px] font-medium truncate leading-none flex-1 text-left' }, props.label)
            : null,
          !props.collapsed
            ? h('i', {
                class: [
                  'fa-solid fa-chevron-down text-[10px] transition-transform duration-200 shrink-0',
                  isOpen ? 'rotate-180 text-white/70' : 'text-white/30',
                ],
              })
            : null,
        ]),

        // Slide-down children (expanded sidebar)
        !props.collapsed
          ? h(Transition, {
              enterActiveClass:  'transition-all duration-200 ease-out overflow-hidden',
              enterFromClass:    'opacity-0 max-h-0',
              enterToClass:      'opacity-100 max-h-40',
              leaveActiveClass:  'transition-all duration-150 ease-in overflow-hidden',
              leaveFromClass:    'opacity-100 max-h-40',
              leaveToClass:      'opacity-0 max-h-0',
            }, {
              default: () => isOpen
                ? h('div', { class: 'space-y-0.5 mt-0.5 pb-0.5' }, childEls)
                : null,
            })
          : null,

        // Flyout for collapsed sidebar (rendered inline, fixed-positioned)
        flyout,
      ])
    }
  },
}

// ── Page & auth ────────────────────────────────────────────────────
const page = usePage()

const flash = computed(() => ({
  success: page.props.flash?.success ?? null,
  error:   page.props.flash?.error   ?? null,
}))

const dismissedFlash = ref({ success: false, error: false })

// Reset dismissed state on each navigation so new flash messages show
onMounted(() => {
  const stop = router.on('navigate', () => {
    dismissedFlash.value = { success: false, error: false }
  })
  onUnmounted(stop)
})

function dismissFlash(type) {
  dismissedFlash.value[type] = true
}

// ── Sidebar collapse ───────────────────────────────────────────────
const collapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true')

function toggleCollapse() {
  collapsed.value = !collapsed.value
  localStorage.setItem('sidebar_collapsed', String(collapsed.value))
}

// ── Role ───────────────────────────────────────────────────────────
const userRole = computed(() => page.props.auth?.user?.role ?? '')

// ── Edit Requests notification count (admin only) ──────────────────
const editRequestsCount = computed(() => page.props.editRequestsCount ?? 0)

// ── User initials ──────────────────────────────────────────────────
const userInitials = computed(() => {
  const name = page.props.auth?.user?.name ?? ''
  return name
    .split(' ')
    .slice(0, 2)
    .map(w => w[0] ?? '')
    .join('')
    .toUpperCase() || 'U'
})

// ── Dropdowns ──────────────────────────────────────────────────────
const dropdownOpen    = ref(false)
const dropdownContainer = ref(null)
const gearOpen        = ref(false)
const gearContainer   = ref(null)

function handleClickOutside(e) {
  if (dropdownContainer.value && !dropdownContainer.value.contains(e.target)) {
    dropdownOpen.value = false
  }
  if (gearContainer.value && !gearContainer.value.contains(e.target)) {
    gearOpen.value = false
  }
}
onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))

onMounted(() => {
  window.addEventListener('online',  handleOnline)
  window.addEventListener('offline', handleOffline)
  refreshCount()   // show badge count on page load if anything is pending
})
onUnmounted(() => {
  window.removeEventListener('online',  handleOnline)
  window.removeEventListener('offline', handleOffline)
  clearTimeout(_syncToastTimer)
})

// ── Logout ─────────────────────────────────────────────────────────
function logout() {
  dropdownOpen.value = false
  router.post('/logout')
}
</script>
