<template>
  <div class="min-h-screen bg-[#f4f6fb] font-sans">

    <!-- ═══════════════════════════════════════════════════════════
         SIDEBAR — fixed, 240px, same dark navy as PublicLayout
    ═══════════════════════════════════════════════════════════ -->
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
            class="w-9 h-9 flex items-center justify-center rounded-lg bg-[#6366f1]/80 shrink-0
                   text-sm font-bold text-white ring-1 ring-white/10">
            {{ lg?.name_en?.charAt(0) ?? 'L' }}
          </div>
          <div class="min-w-0">
            <div class="text-[13px] font-semibold text-white leading-tight truncate
                        group-hover:text-blue-200 transition">
              {{ lg?.name_en ?? 'Local Government' }}
            </div>
            <div class="text-[11px] text-white/50 truncate mt-0.5">
              {{ lg?.name_np ?? 'स्थानीय सरकार' }}
            </div>
          </div>
        </a>
      </div>

      <!-- Section label -->
      <div class="px-4 pt-4 pb-1 shrink-0">
        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-white/35">
          {{ t('instReports.sidebarLabel') }}
        </p>
      </div>

      <!-- Report category nav -->
      <nav class="flex-1 px-2 pb-2 overflow-y-auto scrollbar-hide space-y-0.5">
        <a v-for="cat in reportCategories" :key="cat.href"
          :href="cat.href"
          class="w-full flex items-center gap-2.5 px-3 py-2 rounded text-left
                 text-[13px] transition-colors no-underline"
          :class="isActive(cat)
            ? 'bg-white/12 text-white ring-1 ring-white/10'
            : 'text-white/65 hover:bg-white/6 hover:text-white/90'">
          <span class="shrink-0 w-5 text-center leading-none text-[14px]">{{ cat.icon }}</span>
          <span class="truncate flex-1 font-medium">{{ t(cat.labelKey) }}</span>
          <div v-if="isActive(cat)"
            class="w-1 h-4 rounded-full shrink-0 bg-[#6366f1]"></div>
        </a>
      </nav>

      <!-- Sidebar footer — back to main nav -->
      <div class="p-3 border-t border-white/8 shrink-0 space-y-1.5">
        <a v-for="link in mainNavLinks" :key="link.href" :href="link.href"
          class="flex items-center gap-2 px-3 py-1.5 rounded text-[12px]
                 text-white/45 hover:text-white/75 hover:bg-white/6 transition no-underline">
          <i :class="[link.icon, 'text-[11px] shrink-0 w-4 text-center']"></i>
          <span>{{ t(`nav.${link.key}`) }}</span>
        </a>
      </div>
    </aside>

    <!-- ═══════════════════════════════════════════════════════════
         MAIN AREA — offset by sidebar on lg+
    ═══════════════════════════════════════════════════════════ -->
    <div class="lg:pl-[240px] flex flex-col min-h-screen">

      <!-- ── Topbar ── -->
      <header class="sticky top-0 z-30 bg-white border-b border-[#e6e7e9] h-14 flex items-center">
        <div class="flex items-center justify-between w-full px-4 sm:px-6 gap-4">

          <!-- Left: mobile menu + breadcrumb -->
          <div class="flex items-center gap-3 min-w-0">
            <button @click="mobileOpen = !mobileOpen"
              class="lg:hidden w-8 h-8 flex items-center justify-center rounded
                     border border-gray-200 text-gray-500 hover:bg-gray-50 transition shrink-0">
              <i class="fa-solid fa-bars text-[13px]"></i>
            </button>
            <div class="flex items-center gap-1.5 min-w-0 text-[13px]">
              <a href="/public" class="text-[#9aa0ac] hover:text-[#6366f1] transition whitespace-nowrap">
                {{ lg?.name_en ?? 'Home' }}
              </a>
              <i class="fa-solid fa-chevron-right text-[9px] text-[#c8ccd4] shrink-0"></i>
              <a href="/public/institution-reports"
                class="text-[#9aa0ac] hover:text-[#6366f1] transition whitespace-nowrap">
                {{ t('nav.instReports') }}
              </a>
              <template v-if="activeCategory">
                <i class="fa-solid fa-chevron-right text-[9px] text-[#c8ccd4] shrink-0"></i>
                <span class="font-semibold text-[#1d273b] truncate">{{ t(activeCategory.labelKey) }}</span>
              </template>
            </div>
          </div>

          <!-- Right: parent nav tabs + lang + login -->
          <div class="flex items-center gap-2 shrink-0">

            <!-- Parent nav tabs (xl+) -->
            <nav class="hidden xl:flex items-center gap-0.5 bg-gray-100 rounded p-0.5">
              <a v-for="link in navLinks" :key="link.href" :href="link.href"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded text-[13px]
                       font-medium transition whitespace-nowrap"
                :class="isCurrentSection(link.href)
                  ? 'bg-white text-[#1d273b] shadow-sm'
                  : 'text-[#626976] hover:text-[#1d273b]'">
                <i :class="[link.icon, 'text-[11px]']"></i>
                {{ t(`nav.${link.key}`) }}
              </a>
            </nav>

            <!-- Lang toggle -->
            <div class="flex items-center rounded border border-[#e6e7e9] overflow-hidden
                        text-[12px] font-semibold">
              <button @click="setLocale('en')" class="px-2.5 py-1.5 transition"
                :class="locale === 'en' ? 'bg-[#6366f1] text-white' : 'text-[#626976] hover:bg-gray-50'">
                EN
              </button>
              <button @click="setLocale('ne')"
                class="px-2.5 py-1.5 transition border-l border-[#e6e7e9]"
                :class="locale === 'ne' ? 'bg-[#6366f1] text-white' : 'text-[#626976] hover:bg-gray-50'">
                ने
              </button>
            </div>

            <!-- Staff Login -->
            <a href="/login"
              class="flex items-center gap-1.5 h-8 px-3 bg-[#6366f1] hover:bg-[#4f46e5]
                     text-white rounded text-[13px] font-medium transition">
              <i class="fa-solid fa-right-to-bracket text-[11px]"></i>
              <span class="hidden sm:inline">{{ t('nav.staffLogin') }}</span>
            </a>
          </div>
        </div>
      </header>

      <!-- ── Ward Filter Bar ── -->
      <div class="bg-white border-b border-[#e6e7e9] px-4 sm:px-6 py-3">
        <div class="flex flex-wrap items-end gap-3">

          <!-- Ward -->
          <div class="min-w-[180px] flex-1 max-w-[260px]">
            <label class="block text-[10px] font-semibold uppercase tracking-[0.12em]
                          text-[#9aa0ac] mb-1.5">
              {{ t('filter.ward') }}
            </label>
            <select v-model="localFilters.ward_id"
              class="h-9 rounded border border-[#e6e7e9] bg-white px-3 pr-8 text-[13px]
                     text-[#1d273b] focus:ring-2 focus:ring-[#6366f1]/25 focus:border-[#6366f1]
                     transition outline-none w-full">
              <option value="">{{ t('filter.allWards') }}</option>
              <option v-for="w in wards" :key="w.id" :value="String(w.id)">
                {{ t('common.ward') }} {{ w.ward_number }}
              </option>
            </select>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2 ml-auto">
            <button @click="applyFilters"
              class="h-9 flex items-center gap-1.5 px-4 bg-[#6366f1] hover:bg-[#4f46e5]
                     text-white text-[13px] font-medium rounded transition">
              <i class="fa-solid fa-filter text-[10px]"></i>
              {{ t('filter.filter') }}
            </button>
            <button v-if="localFilters.ward_id" @click="clearFilters"
              class="h-9 flex items-center gap-1.5 px-3 border border-[#e6e7e9]
                     text-[#626976] text-[13px] rounded hover:bg-gray-50 transition">
              <i class="fa-solid fa-xmark text-[10px]"></i>
              {{ t('filter.clear') }}
            </button>
          </div>
        </div>

        <!-- Active filter chips -->
        <div v-if="localFilters.ward_id" class="mt-2.5 flex flex-wrap items-center gap-1.5">
          <span class="text-[11px] text-[#9aa0ac] font-medium">{{ t('filter.showing') }}</span>
          <span class="inline-flex items-center gap-1 text-[12px] bg-[#6366f1]/8 text-[#6366f1]
                 border border-[#6366f1]/20 rounded px-2 py-0.5">
            <i class="fa-solid fa-map-pin text-[9px]"></i>
            {{ t('common.ward') }} {{ wards.find(w => String(w.id) === localFilters.ward_id)?.ward_number }}
            <button @click="localFilters.ward_id = ''; applyFilters()"
              class="ml-0.5 hover:text-red-500 transition">
              <i class="fa-solid fa-xmark text-[9px]"></i>
            </button>
          </span>
        </div>
      </div>

      <!-- ── Page content ── -->
      <div class="flex-1">
        <slot />
      </div>

      <!-- ── Footer ── -->
      <footer class="bg-white border-t border-[#e6e7e9] py-4">
        <div class="px-4 sm:px-6 flex flex-col sm:flex-row items-center
                    justify-between gap-2 text-[12px] text-[#9aa0ac]">
          <span>{{ t('footer.dataNote') }}</span>
          <span>&copy; {{ year }} {{ lg?.name_en ?? 'Local Government' }}</span>
        </div>
      </footer>
    </div>

    <!-- ── Mobile sidebar overlay ── -->
    <transition name="fade">
      <div v-if="mobileOpen" class="fixed inset-0 z-50 lg:hidden flex">
        <div class="fixed inset-0 bg-black/50" @click="mobileOpen = false"></div>
        <div class="relative w-[240px] h-full flex flex-col border-r border-[#0d1825]/60 z-10"
             style="background: linear-gradient(180deg,#182433 0%,#1a2d45 60%,#182433 100%)">
          <div class="px-4 py-4 border-b border-white/8 flex items-center justify-between">
            <span class="text-[13px] font-semibold text-white">{{ t('nav.instReports') }}</span>
            <button @click="mobileOpen = false" class="text-white/50 hover:text-white transition">
              <i class="fa-solid fa-xmark text-sm"></i>
            </button>
          </div>
          <nav class="flex-1 px-2 pt-2 overflow-y-auto space-y-0.5">
            <a v-for="cat in reportCategories" :key="cat.href"
              :href="cat.href"
              class="flex items-center gap-2.5 px-3 py-2 rounded text-[13px]
                     transition-colors no-underline"
              :class="isActive(cat)
                ? 'bg-white/12 text-white'
                : 'text-white/65 hover:bg-white/6 hover:text-white/90'">
              <span class="shrink-0 w-5 text-center text-[14px]">{{ cat.icon }}</span>
              <span class="font-medium">{{ t(cat.labelKey) }}</span>
            </a>
          </nav>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useLocale } from '@/i18n/index.js'

const page = usePage()
const { locale, t, setLocale } = useLocale()
const year = new Date().getFullYear()

const lg    = computed(() => page.props.lg)
const wards = computed(() => page.props.wards ?? [])
const mobileOpen = ref(false)

const reportCategories = [
  { href: '/public/institution-reports',              icon: '🏛️', labelKey: 'instReports.cat.overview'     },
  { href: '/public/institution-reports/by-type',      icon: '📋', labelKey: 'instReports.cat.byType'       },
  { href: '/public/institution-reports/by-ownership', icon: '🏢', labelKey: 'instReports.cat.byOwnership'  },
  { href: '/public/institution-reports/infrastructure',icon: '⚡', labelKey: 'instReports.cat.infrastructure'},
  { href: '/public/institution-reports/services',     icon: '🤝', labelKey: 'instReports.cat.services'     },
  { href: '/public/institution-reports/staff',        icon: '👤', labelKey: 'instReports.cat.staff'        },
]

function isActive(cat) {
  const url = page.url ?? ''
  if (cat.href === '/public/institution-reports') {
    return url === '/public/institution-reports' || url.startsWith('/public/institution-reports?')
  }
  return url.startsWith(cat.href)
}

const activeCategory = computed(() =>
  reportCategories.find(cat => isActive(cat)) ?? null
)

const navLinks = [
  { key: 'home',        href: '/public',                       icon: 'fa-solid fa-house' },
  { key: 'profile',     href: '/public/profile',               icon: 'fa-solid fa-building-columns' },
  { key: 'reports',     href: '/public/reports',               icon: 'fa-solid fa-table-list' },
  { key: 'map',         href: '/public/map',                   icon: 'fa-solid fa-map-location-dot' },
  { key: 'instReports', href: '/public/institution-reports',   icon: 'fa-solid fa-landmark' },
]

const mainNavLinks = navLinks.filter(l => l.key !== 'instReports')

function isCurrentSection(href) {
  const url = page.url ?? ''
  if (href === '/public') return url === '/public' || url.startsWith('/public?')
  return url.startsWith(href)
}

const currentFilters = computed(() => page.props.filters ?? {})

const localFilters = reactive({
  ward_id: String(currentFilters.value.ward_id ?? ''),
})

function applyFilters() {
  const params = {}
  if (localFilters.ward_id) params.ward_id = localFilters.ward_id
  const basePath = page.url.split('?')[0]
  router.get(basePath, params, { preserveScroll: false, preserveState: false })
}

function clearFilters() {
  localFilters.ward_id = ''
  const basePath = page.url.split('?')[0]
  router.get(basePath, {}, { preserveScroll: false, preserveState: false })
}
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.hover\:bg-white\/6:hover  { background-color: rgba(255,255,255,.06);  }
.bg-white\/12              { background-color: rgba(255,255,255,.12);  }
.border-white\/8           { border-color: rgba(255,255,255,.08);      }
.ring-white\/10            { --tw-ring-color: rgba(255,255,255,.10);   }
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }
</style>
