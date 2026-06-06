<template>
  <div class="flex h-screen overflow-hidden bg-[#f4f6fb]">

    <!-- ═══════════════════════════════════════════════════════════════
         SIDEBAR
    ═══════════════════════════════════════════════════════════════ -->
    <aside
      :class="[
        'hidden lg:flex flex-col transition-all duration-300 ease-in-out shrink-0 z-30',
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
        <template v-if="['enumerator','ward_admin','municipal_admin','super_admin'].includes(userRole)">
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
        <template v-if="['ward_reviewer','ward_admin','municipal_admin','super_admin'].includes(userRole)">
          <div class="mb-1 mt-3">
            <p v-if="!collapsed" class="text-[10px] font-semibold text-white/35 uppercase tracking-[0.15em] px-2 mb-1">
              Review and Publish
            </p>
            <div class="h-px bg-white/10 mb-2"></div>
          </div>
          <NavItem href="/review" icon="fa-solid fa-magnifying-glass-chart" label="समीक्षा अवस्था" :collapsed="collapsed" />
          <template v-if="['ward_admin','municipal_admin','super_admin'].includes(userRole)">
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
          <NavItem href="/admin/progress"                 icon="fa-solid fa-chart-line"                    label="प्रगति ड्यासबोर्ड" :collapsed="collapsed" />
          <NavItem href="/admin/data-export"              icon="fa-solid fa-file-excel"                    label="तथ्याङ्क निर्यात"   :collapsed="collapsed" />
          <NavItem href="/admin/audit-logs"               icon="fa-solid fa-clock-rotate-left"             label="अडिट लग"           :collapsed="collapsed" />
          <NavItem href="/admin/users"                    icon="fa-solid fa-users"                         label="प्रयोगकर्ता"       :collapsed="collapsed" />
          <NavItem href="/admin/ward-assignments"         icon="fa-solid fa-map-pin"                       label="वडा तोकाई"         :collapsed="collapsed" />
          <NavItem href="/admin/google-sync"              icon="fa-brands fa-google"                       label="Google Sync"       :collapsed="collapsed" />
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
      <header class="bg-white border-b border-[#e6e7e9] h-14 flex items-center px-4 gap-3 shrink-0 z-20">

        <!-- Mobile hamburger -->
        <button @click="mobileNavOpen = true"
          class="lg:hidden w-9 h-9 flex items-center justify-center rounded border border-[#e6e7e9] text-[#626976] hover:bg-gray-50 transition shrink-0">
          <i class="fa-solid fa-bars text-[13px]"></i>
        </button>

        <!-- Page Title -->
        <div class="flex-1 min-w-0">
          <h1 class="text-[15px] font-semibold text-[#1d273b] truncate">
            {{ page.props.title ?? '' }}
          </h1>
        </div>

        <!-- Notification bell (admin + ward_admin — edit requests from ward reviewers) -->
        <div
          v-if="['ward_admin','municipal_admin','super_admin'].includes(userRole)"
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

        <!-- Notification bell (enumerator / ward_reviewer — submission status updates) -->
        <div
          v-if="['enumerator','ward_reviewer'].includes(userRole)"
          class="relative shrink-0"
          ref="notifContainer"
        >
          <button
            @click="notifOpen = !notifOpen"
            :class="[
              'w-9 h-9 flex items-center justify-center rounded border transition relative',
              notifOpen ? 'bg-[#206bc4] border-[#206bc4] text-white' : 'border-[#e6e7e9] text-[#626976] hover:bg-gray-50'
            ]"
            title="सूचनाहरू"
          >
            <i class="fa-solid fa-bell text-[14px]"></i>
            <span
              v-if="notificationsCount > 0"
              class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center leading-none"
            >{{ notificationsCount > 99 ? '99+' : notificationsCount }}</span>
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
              v-if="notifOpen"
              class="absolute right-0 top-full mt-2 w-80 bg-white rounded-md shadow-lg border border-[#e6e7e9] z-50 overflow-hidden"
            >
              <!-- Header -->
              <div class="flex items-center justify-between px-4 py-2.5 border-b border-[#e6e7e9]">
                <p class="text-[12px] font-semibold text-[#1d273b] uppercase tracking-wider">सूचनाहरू</p>
                <button
                  v-if="notificationsCount > 0"
                  @click="markAllRead"
                  class="text-[11px] text-[#206bc4] hover:underline"
                >सबै पढिएको</button>
              </div>
              <!-- List -->
              <div class="max-h-80 overflow-y-auto divide-y divide-[#f0f1f3]">
                <div v-if="recentNotifications.length === 0" class="px-4 py-6 text-center text-[13px] text-[#9aa0ac]">
                  कुनै सूचना छैन।
                </div>
                <div
                  v-for="n in recentNotifications"
                  :key="n.id"
                  :class="['flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors', !n.read_at ? 'bg-blue-50/40' : '']"
                  @click="openNotification(n)"
                >
                  <!-- Icon -->
                  <div :class="['w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-white text-[12px]', notifIconBg(n.data.color)]">
                    <i :class="['fa-solid', 'fa-' + (n.data.icon ?? 'bell')]"></i>
                  </div>
                  <!-- Text -->
                  <div class="flex-1 min-w-0">
                    <p class="text-[13px] font-semibold text-[#1d273b] leading-tight">{{ n.data.title }}</p>
                    <p v-if="n.data.unique_code" class="text-[11px] text-[#9aa0ac] mt-0.5">{{ n.data.unique_code }}</p>
                    <p v-if="n.data.message" class="text-[12px] text-[#626976] mt-0.5 line-clamp-2">{{ n.data.message }}</p>
                    <p class="text-[11px] text-[#bbb] mt-1">{{ n.created_at }}</p>
                  </div>
                  <!-- Unread dot -->
                  <span v-if="!n.read_at" class="w-2 h-2 rounded-full bg-[#206bc4] shrink-0 mt-1.5"></span>
                </div>
              </div>
            </div>
          </Transition>
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
              <a href="/admin/progress"
                class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-[#626976] hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-chart-line w-4 text-[#9aa0ac] text-center text-xs"></i>
                प्रगति ड्यासबोर्ड
              </a>
              <a href="/admin/data-export"
                class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-[#626976] hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-file-excel w-4 text-[#9aa0ac] text-center text-xs"></i>
                तथ्याङ्क निर्यात
              </a>
              <a href="/admin/audit-logs"
                class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-[#626976] hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-clock-rotate-left w-4 text-[#9aa0ac] text-center text-xs"></i>
                अडिट लग
              </a>
              <a href="/admin/users"
                class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-[#626976] hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-users w-4 text-[#9aa0ac] text-center text-xs"></i>
                प्रयोगकर्ता
              </a>
              <a href="/admin/ward-assignments"
                class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-[#626976] hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-map-pin w-4 text-[#9aa0ac] text-center text-xs"></i>
                वडा तोकाई
              </a>
              <a v-if="userRole === 'super_admin'" href="/admin/role-permissions"
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
              <!-- 2FA -->
              <a href="/two-factor/setup" class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-[#626976] hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-shield-halved w-4 text-[#9aa0ac] text-center"></i>
                2FA सुरक्षा
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

      <!-- Flash toasts rendered via Teleport at end of template -->

      <!-- Impersonation banner -->
      <div
        v-if="impersonating"
        class="flex items-center gap-2.5 px-4 py-2 bg-rose-600 text-white text-[13px] font-semibold shrink-0"
      >
        <i class="fa-solid fa-user-secret text-white/80 text-sm"></i>
        <span class="flex-1">
          <strong>{{ impersonating.by_name }}</strong> — तपाईं अहिले
          <strong>{{ impersonating.as_name }}</strong> ({{ impersonating.as_role }}) को रूपमा काम गर्दै हुनुहुन्छ।
        </span>
        <form @submit.prevent="stopImpersonating">
          <button type="submit"
            class="bg-white/20 hover:bg-white/30 text-white text-[11px] font-bold px-3 py-1 rounded-full transition-colors">
            Admin मा फर्कनुहोस्
          </button>
        </form>
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

    <!-- ═══════════════════════════════════════════════════════════════
         MOBILE DRAWER OVERLAY (lg:hidden)
    ═══════════════════════════════════════════════════════════════ -->
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="mobileNavOpen" class="fixed inset-0 z-50 lg:hidden flex">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/50" @click="mobileNavOpen = false"></div>

        <!-- Drawer panel -->
        <div class="relative w-[260px] h-full flex flex-col z-10 overflow-y-auto"
             style="background: linear-gradient(180deg,#182433 0%,#1a2d45 60%,#182433 100%)">

          <!-- Header -->
          <div class="flex items-center justify-between px-4 h-14 border-b border-white/10 shrink-0">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded bg-[#206bc4] flex items-center justify-center text-white shrink-0">
                <i class="fa-solid fa-map-location-dot text-xs"></i>
              </div>
              <div>
                <p class="text-[13px] font-semibold text-white leading-tight">APPRMP</p>
                <p class="text-[11px] text-white/50 leading-tight truncate max-w-[150px]">
                  {{ page.props.auth?.local_government ?? 'स्थानीय तह' }}
                </p>
              </div>
            </div>
            <button @click="mobileNavOpen = false" class="text-white/50 hover:text-white transition">
              <i class="fa-solid fa-xmark text-sm"></i>
            </button>
          </div>

          <!-- Navigation links -->
          <nav class="flex-1 px-2 py-3 space-y-0.5">

            <!-- Admin dashboard -->
            <template v-if="['municipal_admin','super_admin'].includes(userRole)">
              <a href="/admin" @click="mobileNavOpen = false"
                :class="['flex items-center gap-3 px-3 py-2.5 rounded text-[13px] font-medium transition no-underline',
                  page.url === '/admin' ? 'bg-white/12 text-white' : 'text-white/65 hover:bg-white/8 hover:text-white']">
                <i class="fa-solid fa-gauge-high w-4 text-center"></i> ड्यासबोर्ड
              </a>
              <div class="h-px bg-white/10 my-2"></div>
            </template>

            <!-- Data entry -->
            <template v-if="['enumerator','ward_admin','municipal_admin','super_admin'].includes(userRole)">
              <p class="text-[10px] font-semibold text-white/30 uppercase tracking-widest px-3 py-1">Data Entry</p>
              <a href="/households" @click="mobileNavOpen = false"
                :class="['flex items-center gap-3 px-3 py-2.5 rounded text-[13px] font-medium transition no-underline',
                  page.url.startsWith('/households') ? 'bg-white/12 text-white' : 'text-white/65 hover:bg-white/8 hover:text-white']">
                <i class="fa-solid fa-house w-4 text-center"></i> घरधुरी विवरण
              </a>
              <a href="/institutions" @click="mobileNavOpen = false"
                :class="['flex items-center gap-3 px-3 py-2.5 rounded text-[13px] font-medium transition no-underline',
                  page.url.startsWith('/institutions') ? 'bg-white/12 text-white' : 'text-white/65 hover:bg-white/8 hover:text-white']">
                <i class="fa-solid fa-building w-4 text-center"></i> संस्थागत विवरण
              </a>
              <a href="/businesses" @click="mobileNavOpen = false"
                :class="['flex items-center gap-3 px-3 py-2.5 rounded text-[13px] font-medium transition no-underline',
                  page.url.startsWith('/businesses') ? 'bg-white/12 text-white' : 'text-white/65 hover:bg-white/8 hover:text-white']">
                <i class="fa-solid fa-store w-4 text-center"></i> व्यवसाय विवरण
              </a>
              <div class="h-px bg-white/10 my-2"></div>
            </template>

            <!-- Review -->
            <template v-if="['ward_reviewer','ward_admin','municipal_admin','super_admin'].includes(userRole)">
              <p class="text-[10px] font-semibold text-white/30 uppercase tracking-widest px-3 py-1">Review</p>
              <a href="/review" @click="mobileNavOpen = false"
                :class="['flex items-center gap-3 px-3 py-2.5 rounded text-[13px] font-medium transition no-underline',
                  page.url.startsWith('/review') ? 'bg-white/12 text-white' : 'text-white/65 hover:bg-white/8 hover:text-white']">
                <i class="fa-solid fa-magnifying-glass-chart w-4 text-center"></i> समीक्षा अवस्था
              </a>
              <a v-if="['ward_admin','municipal_admin','super_admin'].includes(userRole)" href="/admin/publish-queue" @click="mobileNavOpen = false"
                :class="['flex items-center gap-3 px-3 py-2.5 rounded text-[13px] font-medium transition no-underline',
                  page.url.startsWith('/admin/publish-queue') ? 'bg-white/12 text-white' : 'text-white/65 hover:bg-white/8 hover:text-white']">
                <i class="fa-solid fa-paper-plane w-4 text-center"></i> प्रकाशन अवस्था
              </a>
              <div class="h-px bg-white/10 my-2"></div>
            </template>

            <!-- Admin links -->
            <template v-if="['municipal_admin','super_admin'].includes(userRole)">
              <p class="text-[10px] font-semibold text-white/30 uppercase tracking-widest px-3 py-1">Administration</p>
              <a href="/admin/settings" @click="mobileNavOpen = false"
                class="flex items-center gap-3 px-3 py-2.5 rounded text-[13px] font-medium transition no-underline text-white/65 hover:bg-white/8 hover:text-white">
                <i class="fa-solid fa-gear w-4 text-center"></i> सेटिङ्ग
              </a>
              <a href="/admin/users" @click="mobileNavOpen = false"
                class="flex items-center gap-3 px-3 py-2.5 rounded text-[13px] font-medium transition no-underline text-white/65 hover:bg-white/8 hover:text-white">
                <i class="fa-solid fa-users w-4 text-center"></i> प्रयोगकर्ता
              </a>
              <div class="h-px bg-white/10 my-2"></div>
            </template>

            <!-- Public portal -->
            <a href="/public" target="_blank"
              class="flex items-center gap-3 px-3 py-2.5 rounded text-[13px] font-medium transition no-underline text-white/65 hover:bg-white/8 hover:text-white">
              <i class="fa-solid fa-globe w-4 text-center"></i> सार्वजनिक पोर्टल
              <i class="fa-solid fa-arrow-up-right-from-square text-[10px] ml-auto text-white/30"></i>
            </a>
          </nav>

          <!-- User at bottom -->
          <div class="shrink-0 border-t border-white/10 px-3 py-3">
            <div class="flex items-center gap-2.5 bg-white/5 rounded px-3 py-2.5">
              <div class="w-8 h-8 rounded bg-[#206bc4] flex items-center justify-center text-white text-xs font-bold shrink-0 uppercase">
                {{ userInitials }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-white text-[13px] font-semibold truncate">{{ page.props.auth?.user?.name ?? '' }}</p>
                <p class="text-white/50 text-[11px] truncate capitalize">{{ page.props.auth?.user?.role?.replace(/_/g,' ') ?? '' }}</p>
              </div>
              <button @click="logout"
                class="shrink-0 w-7 h-7 rounded bg-white/10 hover:bg-red-500/30 text-white/65 hover:text-red-300 flex items-center justify-center transition">
                <i class="fa-solid fa-right-from-bracket text-xs"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ═══════════════════════════════════════════════════════════════
         FLOATING ACTION BUTTON — enumerators, mobile only
    ═══════════════════════════════════════════════════════════════ -->
    <div v-if="['enumerator','ward_admin','municipal_admin','super_admin'].includes(userRole)"
         class="fixed bottom-6 right-5 z-40 lg:hidden flex flex-col items-end gap-2">

      <!-- Speed-dial options (shown when fabOpen) -->
      <Transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-2 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 translate-y-2 scale-95"
      >
        <div v-if="fabOpen" class="flex flex-col items-end gap-2 mb-1">
          <a href="/households/create"
            class="flex items-center gap-2.5 bg-white shadow-lg rounded-full pl-3 pr-4 py-2.5 no-underline text-[13px] font-semibold text-[#1d273b] active:scale-95 transition-transform">
            <div class="w-8 h-8 rounded-full bg-[#206bc4] flex items-center justify-center shrink-0">
              <i class="fa-solid fa-house-circle-plus text-white text-xs"></i>
            </div>
            नयाँ घरधुरी
          </a>
          <a href="/institutions/create"
            class="flex items-center gap-2.5 bg-white shadow-lg rounded-full pl-3 pr-4 py-2.5 no-underline text-[13px] font-semibold text-[#1d273b] active:scale-95 transition-transform">
            <div class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-building-circle-check text-white text-xs"></i>
            </div>
            नयाँ संस्था
          </a>
          <a href="/businesses/create"
            class="flex items-center gap-2.5 bg-white shadow-lg rounded-full pl-3 pr-4 py-2.5 no-underline text-[13px] font-semibold text-[#1d273b] active:scale-95 transition-transform">
            <div class="w-8 h-8 rounded-full bg-orange-500 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-store-arrow-up text-white text-xs"></i>
            </div>
            नयाँ व्यवसाय
          </a>
        </div>
      </Transition>

      <!-- Main FAB button -->
      <button @click="fabOpen = !fabOpen"
        class="w-14 h-14 rounded-full shadow-xl flex items-center justify-center transition-all duration-200 active:scale-95"
        :style="fabOpen ? 'background:#ef4444' : 'background:#206bc4'">
        <i :class="['fa-solid text-white text-xl transition-transform duration-200', fabOpen ? 'fa-xmark rotate-90' : 'fa-plus']"></i>
      </button>
    </div>

  </div>

  <!-- ── Floating toast system ──────────────────────────────────────── -->
  <Teleport to="body">
    <div class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none" style="width:340px">

      <!-- Success toast -->
      <Transition
        enter-from-class="translate-x-full opacity-0"
        enter-active-class="transition-all duration-300 ease-out"
        enter-to-class="translate-x-0 opacity-100"
        leave-from-class="translate-x-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-to-class="translate-x-8 opacity-0"
      >
        <div v-if="flash.success && !dismissedFlash.success"
          class="pointer-events-auto relative overflow-hidden bg-white border border-green-200 shadow-lg rounded-xl px-4 py-3.5 flex items-start gap-3">
          <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-green-50 shrink-0">
            <i class="fa-solid fa-circle-check text-green-500 text-sm"></i>
          </span>
          <div class="flex-1 min-w-0 pt-0.5">
            <p class="text-sm font-semibold text-gray-800">सफल भयो!</p>
            <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">{{ flash.success }}</p>
          </div>
          <button @click="dismissFlash('success')"
            class="text-gray-300 hover:text-gray-500 transition-colors shrink-0 mt-0.5">
            <i class="fa-solid fa-xmark text-sm"></i>
          </button>
          <!-- Auto-dismiss progress bar -->
          <div class="absolute bottom-0 left-0 h-0.5 bg-green-400 rounded-full"
            :style="{ width: toastProgress.success + '%', transition: toastActive.success ? 'width 4000ms linear' : 'none' }">
          </div>
        </div>
      </Transition>

      <!-- Error toast (no auto-dismiss — user must close) -->
      <Transition
        enter-from-class="translate-x-full opacity-0"
        enter-active-class="transition-all duration-300 ease-out"
        enter-to-class="translate-x-0 opacity-100"
        leave-from-class="translate-x-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-to-class="translate-x-8 opacity-0"
      >
        <div v-if="flash.error && !dismissedFlash.error"
          class="pointer-events-auto relative overflow-hidden bg-white border border-red-200 shadow-lg rounded-xl px-4 py-3.5 flex items-start gap-3">
          <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 shrink-0">
            <i class="fa-solid fa-circle-exclamation text-red-500 text-sm"></i>
          </span>
          <div class="flex-1 min-w-0 pt-0.5">
            <p class="text-sm font-semibold text-gray-800">त्रुटि भयो</p>
            <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">{{ flash.error }}</p>
          </div>
          <button @click="dismissFlash('error')"
            class="text-gray-300 hover:text-gray-500 transition-colors shrink-0 mt-0.5">
            <i class="fa-solid fa-xmark text-sm"></i>
          </button>
        </div>
      </Transition>

    </div>
  </Teleport>

</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick, h, Transition } from 'vue'
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
const toastProgress  = ref({ success: 100, error: 100 })
const toastActive    = ref({ success: false, error: false })
const _toastTimers   = { success: null, error: null }

function dismissFlash(type) {
  dismissedFlash.value[type] = true
  toastActive.value[type]    = false
  clearTimeout(_toastTimers[type])
}

function _startToast(type, autoDismiss = true) {
  dismissedFlash.value[type] = false
  toastProgress.value[type]  = 100
  toastActive.value[type]    = false
  clearTimeout(_toastTimers[type])

  if (autoDismiss) {
    // Let the DOM render at 100% first, then kick off the 4s transition to 0%
    nextTick(() => {
      toastActive.value[type] = true
      toastProgress.value[type] = 0
      _toastTimers[type] = setTimeout(() => dismissFlash(type), 4100)
    })
  }
}

watch(() => flash.value.success, val => { if (val) _startToast('success', true)  })
watch(() => flash.value.error,   val => { if (val) _startToast('error',   false) })

// Reset dismissed state on each navigation so new flash messages show
onMounted(() => {
  // Fire watches for any flash already present on mount
  if (flash.value.success) _startToast('success', true)
  if (flash.value.error)   _startToast('error', false)

  const stop = router.on('navigate', () => {
    dismissedFlash.value = { success: false, error: false }
  })
  onUnmounted(stop)
})

// ── Mobile nav + FAB ──────────────────────────────────────────────
const mobileNavOpen = ref(false)
const fabOpen       = ref(false)

// ── Sidebar collapse ───────────────────────────────────────────────
const collapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true')

function toggleCollapse() {
  collapsed.value = !collapsed.value
  localStorage.setItem('sidebar_collapsed', String(collapsed.value))
}

// ── Impersonation ──────────────────────────────────────────────────
const impersonating = computed(() => page.props.impersonating ?? null)

function stopImpersonating() {
  router.post('/admin/impersonate/stop')
}

// ── Role ───────────────────────────────────────────────────────────
const userRole = computed(() => page.props.auth?.user?.role ?? '')

// ── Edit Requests notification count (admin only) ──────────────────
const editRequestsCount = computed(() => page.props.editRequestsCount ?? 0)

// ── Submission notifications (enumerator / ward_reviewer) ──────────
const notificationsCount   = computed(() => page.props.notificationsCount ?? 0)
const recentNotifications  = computed(() => page.props.recentNotifications ?? [])
const notifOpen            = ref(false)
const notifContainer       = ref(null)

function notifIconBg(color) {
  const map = { green: 'bg-green-500', red: 'bg-red-500', orange: 'bg-orange-500', blue: 'bg-blue-500' }
  return map[color] ?? 'bg-gray-400'
}

async function openNotification(n) {
  // Mark as read then navigate
  if (!n.read_at) {
    try { await fetch(`/notifications/${n.id}/read`, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' } }) } catch {}
  }
  notifOpen.value = false
  if (n.data.url) router.visit(n.data.url)
}

async function markAllRead() {
  try { await fetch('/notifications/read-all', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' } }) } catch {}
  router.reload({ only: ['notificationsCount', 'recentNotifications'] })
}

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
  if (notifContainer.value && !notifContainer.value.contains(e.target)) {
    notifOpen.value = false
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
async function logout() {
  dropdownOpen.value = false
  const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? ''
  await fetch('/logout', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } }).catch(() => {})
  window.location.href = '/'
}
</script>
