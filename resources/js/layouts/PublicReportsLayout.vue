<template>
  <div class="min-h-screen bg-[#f4f6fb] font-sans">

    <!-- ═══════════════════════════════════════════════════════════
         SIDEBAR — fixed, 240px, dark navy
    ═══════════════════════════════════════════════════════════ -->
    <aside class="hidden lg:flex flex-col fixed left-0 top-0 h-screen w-[240px] z-40
                  border-r border-[#0d1825]/60"
           style="background: linear-gradient(180deg,#182433 0%,#1a2d45 60%,#182433 100%)">

      <!-- LG identity -->
      <div class="px-4 py-4 border-b border-white/8 shrink-0">
        <a href="/public" class="flex items-center gap-3 group no-underline">
          <div v-if="lg?.logo_url"
            class="w-9 h-9 rounded-lg shrink-0 overflow-hidden bg-white/10 ring-1 ring-white/15 p-0.5">
            <img :src="lg.logo_url" :alt="lg.name_en" class="w-full h-full object-contain rounded-md">
          </div>
          <div v-else
            class="w-9 h-9 flex items-center justify-center rounded-lg bg-[#206bc4]/80 shrink-0
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

      <!-- ── Nav ── -->
      <nav class="flex-1 px-2 py-3 overflow-y-auto scrollbar-hide">

        <!-- Overview — always visible, top-level -->
        <a href="/public/reports"
          class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-[15px] font-semibold
                 transition-all no-underline mb-3"
          :class="isTopLevel
            ? 'bg-[#206bc4] text-white shadow-lg'
            : 'text-white hover:bg-white/10'">
          <span class="w-8 h-8 flex items-center justify-center rounded-lg text-[18px] shrink-0"
            :style="isTopLevel ? 'background:rgba(255,255,255,0.22)' : 'background:rgba(255,255,255,0.1)'">
            📊
          </span>
          <span class="flex-1">{{ t('reports.cat.overview') }}</span>
          <i v-if="isTopLevel" class="fa-solid fa-chevron-right text-[11px] opacity-60"></i>
        </a>

        <!-- Report Groups -->
        <div v-for="group in GROUPS" :key="group.key" class="mb-1.5">

          <!-- Group header (toggle) -->
          <button
            @click="toggleGroup(group.key)"
            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left
                   transition-all select-none group/hdr sidebar-group-btn"
            :class="hasActiveItem(group) ? 'sidebar-group-active' : 'sidebar-group-idle'">
            <!-- Colored left bar -->
            <span class="w-[3.5px] h-5 rounded-full shrink-0 transition-all duration-300"
              :style="hasActiveItem(group)
                ? `background:${group.color}`
                : `background:${group.color}80`">
            </span>
            <!-- Label -->
            <span class="text-[12px] font-bold uppercase tracking-[0.12em] flex-1 transition-colors"
              :class="hasActiveItem(group) ? 'sidebar-grp-lbl-on' : 'sidebar-grp-lbl-off'">
              {{ t(group.labelKey) }}
            </span>
            <!-- + icon -->
            <span class="w-6 h-6 flex items-center justify-center rounded transition-all duration-250 shrink-0"
              :class="openGroups[group.key] ? 'sidebar-plus-open' : 'sidebar-plus-closed'">
              <i class="fa-solid fa-plus text-[11px] transition-transform duration-250"
                :class="openGroups[group.key] ? 'rotate-45' : ''"></i>
            </span>
          </button>

          <!-- Collapsible items -->
          <Transition name="sl">
            <div v-if="openGroups[group.key]" class="mt-1 space-y-0.5 pl-1.5">
              <a v-for="item in group.items" :key="item.href"
                :href="filteredHref(item.href, item.noFilter)"
                class="flex items-center gap-3 px-2.5 py-2.5 rounded-xl text-[14px]
                       font-medium transition-all no-underline sidebar-nav-item group/item"
                :class="isActive(item) ? 'sidebar-item-active' : 'sidebar-item-idle'">
                <!-- Icon box -->
                <span class="w-8 h-8 flex items-center justify-center rounded-lg text-[16px] shrink-0 transition-all"
                  :style="isActive(item)
                    ? 'background:rgba(255,255,255,0.22)'
                    : `background:${group.color}35`">
                  {{ item.icon }}
                </span>
                <!-- Label -->
                <span class="flex-1 truncate">{{ t(item.labelKey) }}</span>
                <!-- Right chevron -->
                <i class="fa-solid fa-chevron-right shrink-0 transition-all"
                  :class="isActive(item) ? 'sidebar-chev-on' : 'sidebar-chev-off'"></i>
              </a>
            </div>
          </Transition>

        </div>

      </nav>

      <!-- Sidebar footer -->
      <div class="px-2 pb-3 pt-2 border-t border-white/10 shrink-0 space-y-0.5">
        <a v-for="link in footerLinks" :key="link.href" :href="link.href"
          class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-[12.5px] font-medium
                 text-white/50 hover:text-white/85 hover:bg-white/7 transition no-underline">
          <i :class="[link.icon, 'text-[12px] shrink-0 w-5 text-center']"></i>
          <span>{{ link.label }}</span>
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
              <a href="/public" class="text-[#9aa0ac] hover:text-[#206bc4] transition whitespace-nowrap">
                {{ lg?.name_en ?? 'Home' }}
              </a>
              <i class="fa-solid fa-chevron-right text-[9px] text-[#c8ccd4] shrink-0"></i>
              <a href="/public/reports"
                class="text-[#9aa0ac] hover:text-[#206bc4] transition whitespace-nowrap">
                {{ t('nav.reports') }}
              </a>
              <template v-if="activeCategory">
                <i class="fa-solid fa-chevron-right text-[9px] text-[#c8ccd4] shrink-0"></i>
                <span class="font-semibold text-[#1d273b] truncate">{{ t(activeCategory.labelKey) }}</span>
              </template>
            </div>
          </div>

          <!-- Right: parent nav tabs + lang + login -->
          <div class="flex items-center gap-2 shrink-0">
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
            <div class="flex items-center rounded border border-[#e6e7e9] overflow-hidden text-[12px] font-semibold">
              <button @click="setLocale('en')" class="px-2.5 py-1.5 transition"
                :class="locale === 'en' ? 'bg-[#206bc4] text-white' : 'text-[#626976] hover:bg-gray-50'">
                EN
              </button>
              <button @click="setLocale('ne')"
                class="px-2.5 py-1.5 transition border-l border-[#e6e7e9]"
                :class="locale === 'ne' ? 'bg-[#206bc4] text-white' : 'text-[#626976] hover:bg-gray-50'">
                ने
              </button>
            </div>

            <!-- Staff Login -->
            <a href="/login"
              class="flex items-center gap-1.5 h-8 px-3 bg-[#206bc4] hover:bg-[#1a5ba8]
                     text-white rounded text-[13px] font-medium transition">
              <i class="fa-solid fa-right-to-bracket text-[11px]"></i>
              <span class="hidden sm:inline">{{ t('nav.staffLogin') }}</span>
            </a>
          </div>
        </div>
      </header>

      <!-- ── Global Filter Bar ── -->
      <div class="bg-white border-b border-[#e6e7e9] px-4 sm:px-6 py-3">
        <div class="flex flex-wrap items-end gap-3">
          <!-- Ward -->
          <div class="min-w-[180px] flex-1 max-w-[260px]">
            <label class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] mb-1.5">
              {{ t('filter.ward') }}
            </label>
            <select v-model="localFilters.ward_id" @change="onWardChange"
              class="h-9 rounded border border-[#e6e7e9] bg-white px-3 pr-8 text-[13px]
                     text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]
                     transition outline-none w-full">
              <option value="">{{ t('filter.allWards') }}</option>
              <option v-for="w in wards" :key="w.id" :value="String(w.id)">
                {{ t('common.ward') }} {{ w.ward_number }}
              </option>
            </select>
          </div>

          <!-- Tole / Settlement -->
          <div class="min-w-[180px] flex-1 max-w-[260px]">
            <label class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] mb-1.5">
              {{ t('filter.tole') }}
            </label>
            <select v-model="localFilters.settlement_id"
              :disabled="!localFilters.ward_id || !settlements.length"
              class="h-9 rounded border border-[#e6e7e9] bg-white px-3 pr-8 text-[13px]
                     text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]
                     transition outline-none w-full disabled:opacity-40 disabled:cursor-not-allowed">
              <option value="">{{ t('filter.allToles') }}</option>
              <option v-for="s in settlements" :key="s.id" :value="String(s.id)">
                {{ locale === 'ne' ? (s.name_np || s.name_en) : (s.name_en || s.name_np) }}
              </option>
            </select>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2 ml-auto">
            <button @click="applyFilters"
              class="h-9 flex items-center gap-1.5 px-4 bg-[#206bc4] hover:bg-[#1a5ba8]
                     text-white text-[13px] font-medium rounded transition">
              <i class="fa-solid fa-filter text-[10px]"></i>
              {{ t('filter.filter') }}
            </button>
            <button v-if="hasActiveFilters" @click="clearFilters"
              class="h-9 flex items-center gap-1.5 px-3 border border-[#e6e7e9]
                     text-[#626976] text-[13px] rounded hover:bg-gray-50 transition">
              <i class="fa-solid fa-xmark text-[10px]"></i>
              {{ t('filter.clear') }}
            </button>
          </div>
        </div>

        <!-- Active filter chips -->
        <div v-if="hasActiveFilters" class="mt-2.5 flex flex-wrap items-center gap-1.5">
          <span class="text-[11px] text-[#9aa0ac] font-medium">{{ t('filter.showing') }}</span>
          <span v-if="localFilters.ward_id"
            class="inline-flex items-center gap-1 text-[12px] bg-[#206bc4]/8 text-[#206bc4]
                   border border-[#206bc4]/20 rounded px-2 py-0.5">
            <i class="fa-solid fa-map-pin text-[9px]"></i>
            {{ t('common.ward') }} {{ wards.find(w => String(w.id) === localFilters.ward_id)?.ward_number }}
            <button @click="localFilters.ward_id = ''; localFilters.settlement_id = ''; applyFilters()"
              class="ml-0.5 hover:text-red-500 transition">
              <i class="fa-solid fa-xmark text-[9px]"></i>
            </button>
          </span>
          <span v-if="localFilters.settlement_id && settlements.length"
            class="inline-flex items-center gap-1 text-[12px] bg-purple-50 text-purple-600
                   border border-purple-200 rounded px-2 py-0.5">
            <i class="fa-solid fa-location-dot text-[9px]"></i>
            {{ settlementLabel }}
            <button @click="localFilters.settlement_id = ''; applyFilters()"
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
    <Transition name="fade">
      <div v-if="mobileOpen" class="fixed inset-0 z-50 lg:hidden flex">
        <div class="fixed inset-0 bg-black/50" @click="mobileOpen = false"></div>
        <div class="relative w-[260px] h-full flex flex-col border-r border-[#0d1825]/60 z-10
                    overflow-hidden"
             style="background: linear-gradient(180deg,#182433 0%,#1a2d45 60%,#182433 100%)">

          <!-- Mobile header -->
          <div class="px-4 py-3.5 border-b border-white/8 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2.5">
              <div v-if="lg?.logo_url"
                class="w-7 h-7 rounded overflow-hidden bg-white/10 ring-1 ring-white/15 p-0.5 shrink-0">
                <img :src="lg.logo_url" class="w-full h-full object-contain">
              </div>
              <span class="text-[13px] font-semibold text-white truncate">
                {{ lg?.name_np ?? t('nav.reports') }}
              </span>
            </div>
            <button @click="mobileOpen = false" class="text-white/50 hover:text-white transition p-1">
              <i class="fa-solid fa-xmark text-sm"></i>
            </button>
          </div>

          <!-- Mobile nav (mirrors desktop bold style) -->
          <nav class="flex-1 px-2 py-3 overflow-y-auto scrollbar-hide">

            <!-- Overview -->
            <a href="/public/reports" @click="mobileOpen = false"
              class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-[15px] font-semibold
                     transition-all no-underline mb-3"
              :class="isTopLevel
                ? 'bg-[#206bc4] text-white shadow-lg'
                : 'text-white hover:bg-white/10'">
              <span class="w-8 h-8 flex items-center justify-center rounded-lg text-[18px] shrink-0"
                :style="isTopLevel ? 'background:rgba(255,255,255,0.22)' : 'background:rgba(255,255,255,0.1)'">
                📊
              </span>
              <span>{{ t('reports.cat.overview') }}</span>
            </a>

            <!-- Groups -->
            <div v-for="group in GROUPS" :key="group.key" class="mb-1.5">
              <button @click="toggleGroup(group.key)"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left
                       transition-all select-none group/mhdr sidebar-group-btn"
                :class="hasActiveItem(group) ? 'sidebar-group-active' : 'sidebar-group-idle'">
                <span class="w-[3.5px] h-5 rounded-full shrink-0"
                  :style="hasActiveItem(group) ? `background:${group.color}` : `background:${group.color}80`"></span>
                <span class="text-[12px] font-bold uppercase tracking-[0.12em] flex-1"
                  :class="hasActiveItem(group) ? 'sidebar-grp-lbl-on' : 'sidebar-grp-lbl-off'">
                  {{ t(group.labelKey) }}
                </span>
                <span class="w-6 h-6 flex items-center justify-center rounded transition-all shrink-0"
                  :class="openGroups[group.key] ? 'sidebar-plus-open' : 'sidebar-plus-closed'">
                  <i class="fa-solid fa-plus text-[11px] transition-transform duration-250"
                    :class="openGroups[group.key] ? 'rotate-45' : ''"></i>
                </span>
              </button>

              <Transition name="sl">
                <div v-if="openGroups[group.key]" class="mt-1 space-y-0.5 pl-1.5">
                  <a v-for="item in group.items" :key="item.href"
                    :href="filteredHref(item.href, item.noFilter)"
                    @click="mobileOpen = false"
                    class="flex items-center gap-3 px-2.5 py-2.5 rounded-xl text-[14px]
                           font-medium transition-all no-underline sidebar-nav-item group/item"
                    :class="isActive(item) ? 'sidebar-item-active' : 'sidebar-item-idle'">
                    <span class="w-8 h-8 flex items-center justify-center rounded-lg text-[16px] shrink-0"
                      :style="isActive(item) ? 'background:rgba(255,255,255,0.22)' : `background:${group.color}35`">
                      {{ item.icon }}
                    </span>
                    <span class="flex-1 truncate">{{ t(item.labelKey) }}</span>
                    <i class="fa-solid fa-chevron-right shrink-0 transition-all"
                      :class="isActive(item) ? 'sidebar-chev-on' : 'sidebar-chev-off'"></i>
                  </a>
                </div>
              </Transition>
            </div>
          </nav>

          <!-- Mobile footer links -->
          <div class="px-2 pb-3 pt-2 border-t border-white/8 shrink-0 space-y-0.5">
            <a v-for="link in footerLinks" :key="link.href" :href="link.href"
              class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-[12.5px]
                     text-white/50 hover:text-white/80 hover:bg-white/6 transition no-underline">
              <i :class="[link.icon, 'text-[12px] shrink-0 w-4 text-center']"></i>
              <span>{{ link.label }}</span>
            </a>
          </div>
        </div>
      </div>
    </Transition>

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

  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useLocale } from '@/i18n/index.js'
import NoticeTicker from '@/components/NoticeTicker.vue'

const page = usePage()
const { locale, t, setLocale } = useLocale()
const year = new Date().getFullYear()

const lg              = computed(() => page.props.lg)
const wards           = computed(() => page.props.wards       ?? [])
const settlements     = computed(() => page.props.settlements ?? [])
const dataLastUpdated = computed(() => page.props.dataLastUpdated ?? null)
const mobileOpen      = ref(false)
const showBackToTop   = ref(false)

function formatDataDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('ne-NP', { year: 'numeric', month: 'short', day: 'numeric' })
}
function scrollToTop() { window.scrollTo({ top: 0, behavior: 'smooth' }) }

// ── Report groups ─────────────────────────────────────────────────────────────
const GROUPS = [
  {
    key: 'ward',
    labelKey: 'reports.group.ward',
    color: '#4f8ef7',
    items: [
      { href: '/public/reports/ward-summary',    icon: '🗂️', labelKey: 'reports.cat.wardSummary'    },
      { href: '/public/reports/ward-profile',    icon: '📋', labelKey: 'reports.cat.wardProfile'    },
      { href: '/public/reports/development-index', icon: '📈', labelKey: 'reports.cat.developmentIndex' },
    ],
  },
  {
    key: 'population',
    labelKey: 'reports.group.population',
    color: '#22c55e',
    items: [
      { href: '/public/reports/demographics', icon: '👥', labelKey: 'reports.cat.demographics' },
      { href: '/public/reports/age-groups',   icon: '📅', labelKey: 'reports.cat.ageGroups'    },
    ],
  },
  {
    key: 'social',
    labelKey: 'reports.group.social',
    color: '#a855f7',
    items: [
      { href: '/public/reports/education',          icon: '🎓', labelKey: 'reports.cat.education'         },
      { href: '/public/reports/health',             icon: '🏥', labelKey: 'reports.cat.health'            },
      { href: '/public/reports/child-development',  icon: '👶', labelKey: 'reports.cat.childDevelopment'  },
      { href: '/public/reports/migration',          icon: '✈️',  labelKey: 'reports.cat.migration'         },
      { href: '/public/reports/documents',          icon: '🪪', labelKey: 'reports.cat.documents'         },
      { href: '/public/reports/women-empowerment',  icon: '👩', labelKey: 'reports.cat.womenEmpowerment'  },
      { href: '/public/reports/social-security',    icon: '🛡️', labelKey: 'reports.cat.socialSecurity'    },
    ],
  },
  {
    key: 'economic',
    labelKey: 'reports.group.economic',
    color: '#f59e0b',
    items: [
      { href: '/public/reports/employment',      icon: '💼', labelKey: 'reports.cat.employment'      },
      { href: '/public/reports/economics',       icon: '💰', labelKey: 'reports.cat.economics'       },
      { href: '/public/reports/agriculture',     icon: '🌾', labelKey: 'reports.cat.agriculture'     },
      { href: '/public/reports/livestock-detail', icon: '🐄', labelKey: 'reports.cat.livestockDetail' },
      { href: '/public/reports/land-agriculture', icon: '🌱', labelKey: 'reports.cat.landAgriculture' },
    ],
  },
  {
    key: 'living',
    labelKey: 'reports.group.living',
    color: '#06b6d4',
    items: [
      { href: '/public/reports/housing',             icon: '🏠', labelKey: 'reports.cat.housing'            },
      { href: '/public/reports/utilities',           icon: '💧', labelKey: 'reports.cat.utilities'          },
      { href: '/public/reports/disaster',            icon: '🔴', labelKey: 'reports.cat.disaster'           },
      { href: '/public/reports/digital-connectivity', icon: '📱', labelKey: 'reports.cat.digitalConnectivity' },
    ],
  },
  {
    key: 'institutions',
    labelKey: 'reports.group.institutions',
    color: '#f97316',
    items: [
      { href: '/public/institution-reports', icon: '🏫', labelKey: 'reports.cat.institutionReports', noFilter: true },
      { href: '/public/business-reports',    icon: '🏪', labelKey: 'reports.cat.businessReports',    noFilter: true },
    ],
  },
]

// ── Active state ──────────────────────────────────────────────────────────────
function isActive(item) {
  const path = (page.url ?? '').split('?')[0]
  return path === item.href || path.startsWith(item.href + '/')
}

const isTopLevel = computed(() => {
  const path = (page.url ?? '').split('?')[0]
  return path === '/public/reports'
})

function hasActiveItem(group) {
  return group.items.some(item => isActive(item))
}

const activeCategory = computed(() => {
  for (const group of GROUPS) {
    const found = group.items.find(item => isActive(item))
    if (found) return found
  }
  return null
})

// ── Collapsible groups ────────────────────────────────────────────────────────
const openGroups = reactive({})

// Initialise: open the group that contains the current page; others closed
GROUPS.forEach(group => {
  openGroups[group.key] = hasActiveItem(group)
})

function toggleGroup(key) {
  openGroups[key] = !openGroups[key]
}

// Auto-open the relevant group when Inertia navigates
watch(() => page.url, () => {
  GROUPS.forEach(group => {
    if (hasActiveItem(group)) openGroups[group.key] = true
  })
})

// ── Filter-preserving links ───────────────────────────────────────────────────
function filteredHref(href, noFilter = false) {
  if (noFilter) return href
  const p = new URLSearchParams()
  if (localFilters.ward_id)       p.set('ward_id',       localFilters.ward_id)
  if (localFilters.settlement_id) p.set('settlement_id', localFilters.settlement_id)
  const qs = p.toString()
  return qs ? `${href}?${qs}` : href
}

// ── Parent nav tabs ───────────────────────────────────────────────────────────
const navLinks = [
  { key: 'home',    href: '/public',          icon: 'fa-solid fa-house'      },
  { key: 'profile', href: '/public/profile',  icon: 'fa-solid fa-building-columns' },
  { key: 'reports', href: '/public/reports',  icon: 'fa-solid fa-table-list' },
]

const footerLinks = [
  { href: '/public',             icon: 'fa-solid fa-house',        label: t('nav.home')    ?? 'Home'    },
  { href: '/public/profile',     icon: 'fa-solid fa-building-columns', label: t('nav.profile') ?? 'Profile' },
  { href: '/public/gallery',     icon: 'fa-solid fa-images',       label: t('nav.gallery') ?? 'Gallery' },
  { href: '/public/downloads',   icon: 'fa-solid fa-download',     label: t('nav.downloads') ?? 'Downloads' },
]

function isCurrentSection(href) {
  const url = page.url ?? ''
  if (href === '/public') return url === '/public' || url.startsWith('/public?')
  return url.startsWith(href)
}

// ── Filters ───────────────────────────────────────────────────────────────────
const currentFilters = computed(() => page.props.filters ?? {})

const localFilters = reactive({
  ward_id:       String(currentFilters.value.ward_id       ?? ''),
  settlement_id: String(currentFilters.value.settlement_id ?? ''),
})

const hasActiveFilters = computed(() =>
  localFilters.ward_id || localFilters.settlement_id
)

const settlementLabel = computed(() => {
  const s = settlements.value.find(s => String(s.id) === localFilters.settlement_id)
  if (!s) return ''
  return locale.value === 'ne' ? (s.name_np || s.name_en) : (s.name_en || s.name_np)
})

function onWardChange() {
  localFilters.settlement_id = ''
  const params = {}
  if (localFilters.ward_id) params.ward_id = localFilters.ward_id
  const basePath = page.url.split('?')[0]
  router.get(basePath, params, { preserveScroll: true, preserveState: false })
}

function applyFilters() {
  const params = {}
  if (localFilters.ward_id)       params.ward_id       = localFilters.ward_id
  if (localFilters.settlement_id) params.settlement_id = localFilters.settlement_id
  const basePath = page.url.split('?')[0]
  router.get(basePath, params, { preserveScroll: false, preserveState: false })
}

function clearFilters() {
  localFilters.ward_id       = ''
  localFilters.settlement_id = ''
  const basePath = page.url.split('?')[0]
  router.get(basePath, {}, { preserveScroll: false, preserveState: false })
}

onMounted(() => {
  const onScroll = () => { showBackToTop.value = window.scrollY > 400 }
  window.addEventListener('scroll', onScroll, { passive: true })
})
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

/* ── Sidebar group collapse animation ────────────────────────────────────── */
.sl-enter-active {
  transition: max-height 0.22s ease, opacity 0.18s ease;
  overflow: hidden;
  max-height: 500px;
}
.sl-leave-active {
  transition: max-height 0.18s ease, opacity 0.12s ease;
  overflow: hidden;
}
.sl-enter-from, .sl-leave-to   { max-height: 0; opacity: 0; }
.sl-enter-to,   .sl-leave-from { max-height: 500px; opacity: 1; }

/* ── Mobile overlay ───────────────────────────────────────────────────────── */
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }

/* ── Sidebar background utilities ────────────────────────────────────────── */
.bg-white\/8  { background-color: rgba(255,255,255,.08); }
.bg-white\/10 { background-color: rgba(255,255,255,.10); }
.border-white\/8  { border-color: rgba(255,255,255,.08); }
.border-white\/10 { border-color: rgba(255,255,255,.10); }

/* ── Group header states ──────────────────────────────────────────────────── */
/* bg for active group header */
.sidebar-group-active { background-color: rgba(255,255,255,.09); }
/* bg on hover for idle group header */
.sidebar-group-idle:hover { background-color: rgba(255,255,255,.06); }

/* Group label colors — explicit hex, not opacity fractions */
.sidebar-grp-lbl-on  { color: #e8f0fb; }           /* active group: bright */
.sidebar-grp-lbl-off { color: #7a9cbf; }            /* idle group: readable mid-blue */
.sidebar-group-btn:hover .sidebar-grp-lbl-off { color: #a8c4e0; }

/* ── + button states ─────────────────────────────────────────────────────── */
.sidebar-plus-open   { background-color: rgba(255,255,255,.14); color: #d0e4f8; }
.sidebar-plus-closed { color: #5a80a8; }
.sidebar-group-btn:hover .sidebar-plus-closed {
  background-color: rgba(255,255,255,.08);
  color: #90b8d8;
}

/* ── Nav item states ─────────────────────────────────────────────────────── */
/* Active item (blue bg) */
.sidebar-item-active { background-color: #206bc4; color: #ffffff; box-shadow: 0 2px 8px rgba(32,107,196,.4); }

/* Idle item — KEY FIX: explicit light color, no opacity fraction */
.sidebar-item-idle { color: #c0d4ec; }
.sidebar-item-idle:hover { background-color: rgba(255,255,255,.09); color: #e8f2ff; }

/* Chevron on active vs idle */
.sidebar-chev-on  { font-size: 10px; opacity: .65; }
.sidebar-chev-off { font-size: 9px;  opacity: 0; }
.sidebar-nav-item:hover .sidebar-chev-off { opacity: .35; }
</style>
