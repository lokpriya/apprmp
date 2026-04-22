<template>
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

      <!-- Sidebar footer — profile CTA -->
      <div class="p-3 border-t border-white/8 shrink-0">
        <a href="/public/profile"
          class="block rounded bg-white/5 hover:bg-white/8 ring-1 ring-white/8
                 px-3 py-2.5 no-underline transition-colors">
          <div class="text-[12px] font-semibold text-white/80">{{ t('profile.label') }}</div>
          <div class="text-[11px] text-white/40 mt-0.5 leading-snug">
            {{ t('dashboard.profileCTADesc') }}
          </div>
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
            <button class="lg:hidden w-8 h-8 flex items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-gray-50 transition shrink-0">
              <i class="fa-solid fa-bars text-[13px]"></i>
            </button>
            <div class="min-w-0">
              <span class="text-[15px] font-semibold text-[#1d273b] truncate">{{ lg?.name_en ?? 'Local Government' }}</span>
              <span v-if="lg?.district" class="text-[12px] text-[#9aa0ac] ml-2">{{ lg.district }}<template v-if="lg.province"> · {{ lg.province }}</template></span>
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

      <!-- Page content -->
      <div class="flex-1 flex flex-col">
        <slot />
      </div>

      <!-- Footer -->
      <footer class="bg-white border-t border-[#e6e7e9] py-4">
        <div class="px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-2 text-[12px] text-[#9aa0ac]">
          <span>{{ t('footer.dataNote') }}</span>
          <span>&copy; {{ year }} {{ lg?.name_en ?? 'Local Government' }}</span>
        </div>
      </footer>
    </div>

  </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useLocale } from '@/i18n/index.js'
import { sidebarSections, sidebarActive } from '@/composables/useSidebar.js'

const page = usePage()
const lg   = computed(() => page.props.lg)
const year = new Date().getFullYear()

defineProps({ profileFilledCount: { type: Number, default: 0 } })

const { locale, t, setLocale } = useLocale()

// All links shown in sidebar
const navLinks = [
  { key: 'home',            href: '/public',                     icon: 'fa-solid fa-house',            dotColor: '#206bc4' },
  { key: 'profile',         href: '/public/profile',             icon: 'fa-solid fa-building-columns', dotColor: '#6366f1' },
  { key: 'representatives', href: '/public/representatives',     icon: 'fa-solid fa-people-group',     dotColor: '#f43f5e' },
  { key: 'employees',       href: '/public/employees',           icon: 'fa-solid fa-id-badge',         dotColor: '#0891b2' },
  { key: 'reports',         href: '/public/reports',             icon: 'fa-solid fa-table-list',       dotColor: '#0ca678' },
  { key: 'map',             href: '/public/map',                 icon: 'fa-solid fa-map-location-dot', dotColor: '#f59f00' },
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
.bg-white\/12              { background-color: rgba(255,255,255,.12);  }
.border-white\/8           { border-color: rgba(255,255,255,.08);      }
.ring-white\/8             { --tw-ring-color: rgba(255,255,255,.08);   }
</style>
