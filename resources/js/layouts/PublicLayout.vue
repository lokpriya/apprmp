<template>
  <!-- Skip-to-content for keyboard users -->
  <a href="#main-content"
    class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[9999]
           focus:px-4 focus:py-2 focus:bg-[#206bc4] focus:text-white focus:rounded-lg
           focus:text-sm focus:font-semibold focus:shadow-lg focus:outline-none">
    मुख्य सामग्रीमा जानुहोस् (Skip to main content)
  </a>
  <div class="min-h-screen bg-[#f4f6fb] font-sans">

    <!-- ═══════════════════════════════════════════════
         SIDEBAR — fixed, 240px, dark navy gradient
    ═══════════════════════════════════════════════ -->
    <aside class="hidden lg:flex flex-col fixed left-0 top-0 h-screen w-[240px] z-40
                  border-r border-[#0d1825]/60"
           style="background: linear-gradient(180deg,#182433 0%,#1a2d45 60%,#182433 100%)">

      <!-- LG identity -->
      <div class="px-4 py-4 border-b border-white/8 shrink-0">
        <a href="/public" class="flex items-center gap-3 group">
          <div v-if="lg?.logo_url"
            class="w-9 h-9 rounded-lg shrink-0 overflow-hidden bg-white/10 ring-1 ring-white/15 p-0.5">
            <img :src="lg.logo_url" :alt="lg.name_en" class="w-full h-full object-contain rounded-md">
          </div>
          <div v-else
            class="w-9 h-9 flex items-center justify-center rounded-lg bg-[#206bc4]/80 shrink-0 text-sm font-bold text-white ring-1 ring-white/10">
            {{ lg?.name_en?.charAt(0) ?? 'L' }}
          </div>
          <div class="min-w-0">
            <div class="text-[13px] font-semibold text-white leading-tight truncate group-hover:text-blue-200 transition">
              {{ lg?.name_en ?? 'Local Government' }}
            </div>
            <div class="text-[11px] text-white/50 truncate mt-0.5">
              {{ lg?.name_np ?? 'स्थानीय सरकार' }}
            </div>
          </div>
        </a>
      </div>

      <!-- ── Main section nav (always visible) ── -->
      <nav class="px-2 pt-3 pb-1 shrink-0 space-y-0.5">
        <a v-for="link in navLinks" :key="link.href" :href="link.href"
          class="flex items-center gap-2.5 px-3 py-2 rounded text-[13px] font-medium
                 transition-colors no-underline"
          :class="isCurrentPath(link.href)
            ? 'bg-white/12 text-white'
            : 'text-white/55 hover:bg-white/6 hover:text-white/85'">
          <i :class="[link.icon, 'text-[13px] shrink-0 w-5 text-center']"></i>
          <span class="truncate flex-1">{{ t(`nav.${link.key}`) }}</span>
          <div v-if="isCurrentPath(link.href)"
            class="w-1 h-4 rounded-full shrink-0"
            :style="`background:${link.dotColor}`"></div>
        </a>
      </nav>

      <!-- ── Divider + in-page sections ── -->
      <div v-if="sidebarSections.length" class="mx-3 my-1 border-t border-white/8 shrink-0"></div>

      <div v-if="sidebarSections.length" class="px-4 pt-1 pb-1 shrink-0">
        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-white/30">
          {{ t('nav.' + (activeNavLink?.key ?? 'statistics')) }}
        </p>
      </div>

      <nav class="flex-1 px-2 pb-2 overflow-y-auto scrollbar-hide space-y-0.5">
        <button v-for="sec in sidebarSections" :key="sec.id"
          @click="sec.onClick()"
          class="w-full flex items-center gap-2.5 px-3 py-2 rounded text-left text-[13px] transition-colors"
          :class="sidebarActive === sec.id
            ? 'bg-white/10 text-white'
            : 'text-white/55 hover:bg-white/6 hover:text-white/85'">
          <span class="text-sm shrink-0 w-5 text-center leading-none opacity-70">{{ sec.icon }}</span>
          <span class="truncate flex-1 font-medium">{{ sec.label }}</span>
          <div v-if="sidebarActive === sec.id"
            class="w-1 h-4 rounded-full shrink-0"
            :style="`background:${activeNavLink?.dotColor ?? '#206bc4'}`"></div>
        </button>
      </nav>

      <!-- Sidebar footer — Feedback CTA -->
      <div class="p-3 border-t border-white/8 shrink-0">
        <a href="/public/feedback"
          class="group flex items-center gap-2.5 rounded-xl no-underline transition-all duration-200
                 px-3 py-3 min-h-[44px] hover:bg-white/8"
          style="background:rgba(32,107,196,0.18)">
          <!-- People + star icon -->
          <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors
                      bg-[#206bc4] group-hover:bg-[#1a5aab]">
            <span class="relative flex items-center justify-center w-full h-full">
              <i class="fa-solid fa-user-group text-white text-[13px]"></i>
              <i class="fa-solid fa-star text-yellow-300 text-[7px] absolute -top-0.5 right-1.5"></i>
            </span>
          </div>
          <div class="min-w-0">
            <div class="text-[12px] font-bold text-white leading-tight">सुझाव दिनुहोस्</div>
            <div class="text-[10px] text-white/55 mt-0.5 truncate">नागरिक प्रतिक्रिया</div>
          </div>
          <i class="fa-solid fa-chevron-right text-white/30 text-[9px] ml-auto
                    group-hover:text-white/60 group-hover:translate-x-0.5 transition-all duration-200"></i>
        </a>
      </div>
    </aside>

    <!-- ═══════════════════════════════════════════════
         MAIN AREA — offset by sidebar on lg+
    ═══════════════════════════════════════════════ -->
    <div class="lg:pl-[240px] flex flex-col min-h-screen">

      <!-- ── Topbar ── -->
      <header class="sticky top-0 z-30 bg-white border-b border-[#e6e7e9] h-14 flex items-center">
        <div class="flex items-center justify-between w-full px-4 sm:px-6 gap-4">

          <!-- Left: mobile menu + LG name -->
          <div class="flex items-center gap-3 min-w-0">
            <button @click="mobileOpen = !mobileOpen"
              class="lg:hidden w-8 h-8 flex items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-gray-50 transition shrink-0">
              <i class="fa-solid fa-bars text-[13px]"></i>
            </button>
            <div class="min-w-0">
              <div class="text-[14px] font-semibold text-[#1d273b] truncate leading-tight">{{ lg?.name_en ?? 'Local Government' }}</div>
              <div v-if="lg?.district" class="hidden sm:block text-[11px] text-[#9aa0ac] truncate mt-0.5">
                {{ lg.district }}<template v-if="lg.province"> · {{ lg.province }}</template>
              </div>
            </div>
          </div>

          <!-- Right: parent tabs + lang + login -->
          <div class="flex items-center gap-2 shrink-0">

            <!-- Parent nav tabs (xl+) -->
            <nav class="hidden xl:flex items-center gap-0.5 bg-gray-100 rounded p-0.5">
              <a v-for="link in topNavLinks" :key="link.href" :href="link.href"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded text-[13px] font-medium transition whitespace-nowrap"
                :class="isCurrentPath(link.href)
                  ? 'bg-white text-[#1d273b] shadow-sm'
                  : 'text-[#626976] hover:text-[#1d273b]'">
                <i :class="[link.icon, 'text-[11px]']"></i>
                {{ t(`nav.${link.key}`) }}
              </a>
            </nav>

            <!-- Lang toggle -->
            <div class="flex items-center rounded border border-[#e6e7e9] overflow-hidden text-[12px] font-semibold">
              <button @click="setLocale('en')" class="px-2.5 py-1.5 transition"
                :class="locale === 'en' ? 'bg-[#206bc4] text-white' : 'text-[#626976] hover:bg-gray-50'">EN</button>
              <button @click="setLocale('ne')" class="px-2.5 py-1.5 transition border-l border-[#e6e7e9]"
                :class="locale === 'ne' ? 'bg-[#206bc4] text-white' : 'text-[#626976] hover:bg-gray-50'">ने</button>
            </div>

            <!-- Staff Login -->
            <a href="/login"
              class="flex items-center gap-1.5 h-8 px-3 bg-[#206bc4] hover:bg-[#1a5ba8] text-white rounded text-[13px] font-medium transition">
              <i class="fa-solid fa-right-to-bracket text-[11px]"></i>
              <span class="hidden sm:inline">{{ t('nav.staffLogin') }}</span>
              <span class="sm:hidden">{{ t('nav.login') }}</span>
            </a>
          </div>
        </div>
      </header>

      <!-- PWA install banner -->
      <div v-if="showInstallBanner"
           class="flex items-center justify-between gap-3 px-4 py-2.5 text-[12px]"
           style="background:#182433; color:#fff">
        <div class="flex items-center gap-2 min-w-0">
          <i class="fa-solid fa-mobile-screen-button text-blue-300 shrink-0"></i>
          <span class="truncate">ऑफलाइन पनि चल्छ — एप इन्स्टल गर्नुहोस्</span>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <button @click="installPWA"
            class="px-3 py-1 rounded text-[11px] font-semibold transition"
            style="background:#206bc4">
            Install
          </button>
          <button @click="dismissBanner" style="color:rgba(255,255,255,.5)"
            class="hover:text-white transition">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
      </div>

      <!-- Page content -->
      <div id="main-content" class="flex-1 flex flex-col">
        <slot />
      </div>

      <!-- Footer -->
      <footer class="bg-white border-t border-[#e6e7e9] py-4">
        <div class="px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-2 text-[12px] text-[#9aa0ac]">
          <span>{{ t('footer.dataNote') }}</span>
          <div class="flex items-center gap-3">
            <span v-if="dataLastUpdated" class="flex items-center gap-1">
              <i class="fa-solid fa-calendar-check text-[10px]"></i>
              तथ्याङ्क: {{ formatDataDate(dataLastUpdated) }}
            </span>
            <span>&copy; {{ year }} {{ lg?.name_en ?? 'Local Government' }}</span>
          </div>
        </div>
      </footer>
    </div>

    <!-- ── Mobile sidebar overlay ── -->
    <transition name="fade">
      <div v-if="mobileOpen" class="fixed inset-0 z-50 lg:hidden flex">
        <div class="fixed inset-0 bg-black/50" @click="mobileOpen = false"></div>
        <div class="relative w-[240px] h-full flex flex-col border-r border-[#0d1825]/60 z-10"
             style="background: linear-gradient(180deg,#182433 0%,#1a2d45 60%,#182433 100%)">

          <!-- LG identity -->
          <div class="px-4 py-4 border-b border-white/8 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
              <div v-if="lg?.logo_url"
                class="w-8 h-8 rounded-lg shrink-0 overflow-hidden bg-white/10 ring-1 ring-white/15 p-0.5">
                <img :src="lg.logo_url" :alt="lg.name_en" class="w-full h-full object-contain rounded-md">
              </div>
              <div v-else
                class="w-8 h-8 flex items-center justify-center rounded-lg bg-[#206bc4]/80 shrink-0 text-sm font-bold text-white">
                {{ lg?.name_en?.charAt(0) ?? 'L' }}
              </div>
              <span class="text-[13px] font-semibold text-white truncate">{{ lg?.name_np ?? lg?.name_en ?? 'स्थानीय सरकार' }}</span>
            </div>
            <button @click="mobileOpen = false" class="text-white/50 hover:text-white transition shrink-0 ml-2">
              <i class="fa-solid fa-xmark text-sm"></i>
            </button>
          </div>

          <!-- Nav links -->
          <nav class="flex-1 px-2 pt-2 pb-2 overflow-y-auto space-y-0.5">
            <a v-for="link in navLinks" :key="link.href" :href="link.href"
              @click="mobileOpen = false"
              class="flex items-center gap-2.5 px-3 py-2 rounded text-[13px] font-medium
                     transition-colors no-underline"
              :class="isCurrentPath(link.href)
                ? 'bg-white/12 text-white'
                : 'text-white/65 hover:bg-white/6 hover:text-white/90'">
              <i :class="[link.icon, 'text-[13px] shrink-0 w-5 text-center']"></i>
              <span class="truncate flex-1">{{ t(`nav.${link.key}`) }}</span>
              <div v-if="isCurrentPath(link.href)"
                class="w-1 h-4 rounded-full shrink-0"
                :style="`background:${link.dotColor}`"></div>
            </a>
          </nav>

          <!-- Footer link — Feedback CTA -->
          <div class="p-3 border-t border-white/8 shrink-0">
            <a href="/public/feedback"
              class="group flex items-center gap-2.5 rounded-xl no-underline transition-all duration-200
                     px-3 py-2.5 hover:bg-white/8"
              style="background:rgba(32,107,196,0.18)">
              <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 bg-[#206bc4]">
                <span class="relative flex items-center justify-center w-full h-full">
                  <i class="fa-solid fa-user-group text-white text-[13px]"></i>
                  <i class="fa-solid fa-star text-yellow-300 text-[7px] absolute -top-0.5 right-1.5"></i>
                </span>
              </div>
              <div class="min-w-0">
                <div class="text-[12px] font-bold text-white leading-tight">सुझाव दिनुहोस्</div>
                <div class="text-[10px] text-white/55 mt-0.5 truncate">नागरिक प्रतिक्रिया</div>
              </div>
              <i class="fa-solid fa-chevron-right text-white/30 text-[9px] ml-auto"></i>
            </a>
          </div>
        </div>
      </div>
    </transition>

    <!-- ── Back-to-top button ── -->
    <Transition enter-from-class="opacity-0 scale-90" enter-active-class="transition duration-200"
                leave-active-class="transition duration-150" leave-to-class="opacity-0 scale-90">
      <button v-if="showBackToTop" @click="scrollToTop"
        class="fixed bottom-14 right-4 z-[990] w-10 h-10 rounded-full bg-[#206bc4] text-white shadow-lg
               flex items-center justify-center hover:bg-[#1a5ba8] transition-colors"
        aria-label="माथि जानुहोस्">
        <i class="fa-solid fa-chevron-up text-sm"></i>
      </button>
    </Transition>

    <!-- ── Notice ticker bar ── -->
    <NoticeTicker />

    <!-- ── Flash toasts ── -->
    <Teleport to="body">
      <div class="fixed bottom-16 right-4 z-[9999] flex flex-col gap-2 pointer-events-none" style="max-width:360px">

        <!-- Success toast -->
        <Transition enter-from-class="opacity-0 translate-y-2 scale-95"
                    enter-active-class="transition duration-200 ease-out"
                    leave-active-class="transition duration-150 ease-in"
                    leave-to-class="opacity-0 translate-y-1 scale-95">
          <div v-if="pubFlash.success && !dismissedPubFlash.success"
            class="pointer-events-auto relative bg-white rounded-xl border border-gray-100 shadow-xl px-4 py-3 flex items-start gap-3 overflow-hidden">
            <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-circle-check text-green-600 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-[#1d273b] leading-snug">सफल!</p>
              <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">{{ pubFlash.success }}</p>
            </div>
            <button @click="dismissPubFlash('success')" class="text-gray-300 hover:text-gray-500 transition shrink-0 mt-0.5">
              <i class="fa-solid fa-xmark text-xs"></i>
            </button>
            <div class="absolute bottom-0 left-0 h-[3px] rounded-b-xl bg-green-500"
              :style="{ width: pubToastProgress.success + '%', transition: pubToastActive.success ? 'width 4000ms linear' : 'none' }"></div>
          </div>
        </Transition>

        <!-- Error toast -->
        <Transition enter-from-class="opacity-0 translate-y-2 scale-95"
                    enter-active-class="transition duration-200 ease-out"
                    leave-active-class="transition duration-150 ease-in"
                    leave-to-class="opacity-0 translate-y-1 scale-95">
          <div v-if="pubFlash.error && !dismissedPubFlash.error"
            class="pointer-events-auto bg-white rounded-xl border border-gray-100 shadow-xl px-4 py-3 flex items-start gap-3">
            <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-circle-exclamation text-red-500 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-[#1d273b] leading-snug">त्रुटि</p>
              <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">{{ pubFlash.error }}</p>
            </div>
            <button @click="dismissPubFlash('error')" class="text-gray-300 hover:text-gray-500 transition shrink-0 mt-0.5">
              <i class="fa-solid fa-xmark text-xs"></i>
            </button>
          </div>
        </Transition>

      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useLocale } from '@/i18n/index.js'
import { sidebarSections, sidebarActive } from '@/composables/useSidebar.js'
import NoticeTicker from '@/components/NoticeTicker.vue'

const page            = usePage()
const lg              = computed(() => page.props.lg)
const dataLastUpdated = computed(() => page.props.dataLastUpdated ?? null)
const year            = new Date().getFullYear()
const mobileOpen      = ref(false)
const showBackToTop   = ref(false)

function formatDataDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('ne-NP', { year: 'numeric', month: 'short', day: 'numeric' })
}
function scrollToTop() { window.scrollTo({ top: 0, behavior: 'smooth' }) }

// PWA install prompt
const deferredPrompt    = ref(null)
const showInstallBanner = ref(false)

onMounted(() => {
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault()
    deferredPrompt.value = e
    showInstallBanner.value = true
  })
  window.addEventListener('appinstalled', () => {
    showInstallBanner.value = false
    deferredPrompt.value = null
  })
  const onScroll = () => { showBackToTop.value = window.scrollY > 400 }
  window.addEventListener('scroll', onScroll, { passive: true })
})

async function installPWA() {
  if (!deferredPrompt.value) return
  deferredPrompt.value.prompt()
  await deferredPrompt.value.userChoice
  deferredPrompt.value = null
  showInstallBanner.value = false
}

function dismissBanner() {
  showInstallBanner.value = false
}

// ── Public flash toasts ──────────────────────────────────────────────────────
const pubFlash         = computed(() => ({
  success: page.props.flash?.success ?? null,
  error:   page.props.flash?.error   ?? null,
}))
const dismissedPubFlash = ref({ success: false, error: false })
const pubToastProgress  = ref({ success: 100, error: 100 })
const pubToastActive    = ref({ success: false, error: false })
const _pubToastTimers   = { success: null, error: null }

function dismissPubFlash(type) {
  dismissedPubFlash.value[type] = true
  pubToastActive.value[type]    = false
  clearTimeout(_pubToastTimers[type])
}

function triggerPubToast(type) {
  dismissedPubFlash.value[type] = false
  pubToastProgress.value[type]  = 100
  pubToastActive.value[type]    = false
  clearTimeout(_pubToastTimers[type])
  requestAnimationFrame(() => {
    pubToastActive.value[type]   = true
    pubToastProgress.value[type] = 0
    _pubToastTimers[type] = setTimeout(() => dismissPubFlash(type), 4200)
  })
}

watch(() => pubFlash.value.success, v => v && triggerPubToast('success'))
watch(() => pubFlash.value.error,   v => v && triggerPubToast('error'))

defineProps({ profileFilledCount: { type: Number, default: 0 } })

const { locale, t, setLocale } = useLocale()

// All links shown in sidebar
const navLinks = [
  { key: 'home',            href: '/public',                     icon: 'fa-solid fa-house',            dotColor: '#206bc4' },
  { key: 'profile',         href: '/public/profile',             icon: 'fa-solid fa-building-columns', dotColor: '#6366f1' },
  { key: 'representatives', href: '/public/representatives',     icon: 'fa-solid fa-people-group',     dotColor: '#f43f5e' },
  { key: 'employees',       href: '/public/employees',           icon: 'fa-solid fa-id-badge',         dotColor: '#0891b2' },
  { key: 'reports',         href: '/public/reports',                          icon: 'fa-solid fa-table-list',       dotColor: '#0ca678' },
  { key: 'pyramid',         href: '/public/reports/population-pyramid',       icon: 'fa-solid fa-chart-bar',        dotColor: '#6366f1' },
  { key: 'map',             href: '/public/map',                              icon: 'fa-solid fa-map-location-dot', dotColor: '#f59f00' },
  { key: 'instReports',     href: '/public/institution-reports', icon: 'fa-solid fa-landmark',         dotColor: '#a855f7' },
  { key: 'instMap',         href: '/public/institution-map',     icon: 'fa-solid fa-map-pin',          dotColor: '#e64980' },
  { key: 'bizReports',      href: '/public/business-reports',    icon: 'fa-solid fa-store',            dotColor: '#f97316' },
  { key: 'gallery',         href: '/public/gallery',             icon: 'fa-solid fa-images',           dotColor: '#d97706' },
  { key: 'downloads',       href: '/public/downloads',           icon: 'fa-solid fa-file-arrow-down',  dotColor: '#0d9488' },
]

// Subset shown in the top horizontal tab bar (keep concise)
const topNavLinks = navLinks.filter(l =>
  !['representatives', 'employees', 'gallery', 'downloads', 'bizReports'].includes(l.key)
)

function isCurrentPath(href) {
  const url = page.url
  if (href === '/public') return url === '/public' || url.startsWith('/public?') || url === '/public/'
  return url.startsWith(href)
}

const activeNavLink = computed(() => navLinks.find(l => isCurrentPath(l.href)) ?? navLinks[0])
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.hover\:bg-white\/6:hover  { background-color: rgba(255,255,255,.06);  }
.hover\:bg-white\/8:hover  { background-color: rgba(255,255,255,.08);  }
.bg-white\/5               { background-color: rgba(255,255,255,.05);  }
.bg-white\/12              { background-color: rgba(255,255,255,.12);  }
.border-white\/8           { border-color: rgba(255,255,255,.08);      }
.ring-white\/8             { --tw-ring-color: rgba(255,255,255,.08);   }
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }
</style>
