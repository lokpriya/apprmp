<template>
  <div class="space-y-5">

    <!-- ══════════════════════════════════════════════════════════════
         SEARCH + FILTER BAR
    ══════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
      <div class="flex flex-wrap gap-2.5 items-center">

        <!-- Search input -->
        <div class="relative flex-1 min-w-[200px]">
          <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="नाम, कोड, वडा वा गणकले खोज्नुहोस्…"
            class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent bg-gray-50 placeholder-gray-400"
          />
        </div>

        <!-- Ward filter -->
        <select
          v-model="filterWard"
          class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-400 min-w-[120px]"
        >
          <option value="">सबै वडा</option>
          <option v-for="w in wardNumbers" :key="w" :value="w">वडा {{ w }}</option>
        </select>

        <!-- Status filter pills -->
        <div class="flex gap-1.5 flex-wrap">
          <button
            v-for="pill in statusPills"
            :key="pill.key"
            @click="filterStatus = filterStatus === pill.key ? '' : pill.key"
            :class="[
              'px-3 py-1.5 rounded-full text-[12px] font-semibold border transition-colors',
              filterStatus === pill.key
                ? pill.activeClass
                : 'border-gray-200 text-gray-500 bg-white hover:bg-gray-50'
            ]"
          >{{ pill.label }}</button>
        </div>

        <!-- Date range -->
        <select
          v-model="filterDate"
          class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-400"
        >
          <option value="all">सबै समय</option>
          <option value="today">आज</option>
          <option value="week">यो हप्ता</option>
        </select>

        <!-- Reset -->
        <button
          v-if="hasFilters"
          @click="resetFilters"
          class="flex items-center gap-1.5 px-3 py-2 text-[12px] font-medium text-red-600 hover:bg-red-50 rounded-lg transition-colors border border-red-200"
        >
          <i class="fa-solid fa-xmark"></i> रिसेट
        </button>

        <!-- Active filter count -->
        <span v-if="hasFilters" class="text-[11px] text-gray-400 ml-auto">
          {{ filteredActivity.length }} गतिविधि देखाइँदैछ
        </span>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         ALERT BANNERS  (only when action is needed)
    ══════════════════════════════════════════════════════════════ -->
    <div
      v-if="stats.submitted_households > 0 || stats.ward_approved_households > 0 || stats.rejected_households > 0"
      class="flex flex-wrap gap-3"
    >
      <!-- Submitted — needs ward review -->
      <a
        v-if="stats.submitted_households > 0"
        href="/review"
        class="flex-1 min-w-[240px] flex items-center gap-3.5 bg-amber-50 border border-amber-200 rounded-xl px-5 py-3.5 hover:bg-amber-100 transition-colors group"
      >
        <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0 group-hover:bg-amber-200 transition-colors">
          <i class="fa-solid fa-clock text-amber-600 text-base"></i>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-bold text-amber-800">{{ formatNum(stats.submitted_households) }} समीक्षा बाँकी</p>
          <p class="text-[12px] text-amber-600 mt-0.5">वडा समीक्षकलाई पेस भएको</p>
        </div>
        <i class="fa-solid fa-arrow-right text-amber-400 group-hover:translate-x-0.5 transition-transform"></i>
      </a>

      <!-- Ward approved — needs admin publish -->
      <a
        v-if="stats.ward_approved_households > 0"
        href="/admin/publish-queue"
        class="flex-1 min-w-[240px] flex items-center gap-3.5 bg-blue-50 border border-blue-200 rounded-xl px-5 py-3.5 hover:bg-blue-100 transition-colors group"
      >
        <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0 group-hover:bg-blue-200 transition-colors">
          <i class="fa-solid fa-paper-plane text-blue-600 text-base"></i>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-bold text-blue-800">{{ formatNum(stats.ward_approved_households) }} प्रकाशन बाँकी</p>
          <p class="text-[12px] text-blue-600 mt-0.5">वडाबाट अनुमोदित — प्रकाशित गर्नुहोस्</p>
        </div>
        <i class="fa-solid fa-arrow-right text-blue-400 group-hover:translate-x-0.5 transition-transform"></i>
      </a>

      <!-- Rejected — needs enumerator action -->
      <div
        v-if="stats.rejected_households > 0"
        class="flex-1 min-w-[240px] flex items-center gap-3.5 bg-red-50 border border-red-200 rounded-xl px-5 py-3.5"
      >
        <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
          <i class="fa-solid fa-triangle-exclamation text-red-600 text-base"></i>
        </div>
        <div>
          <p class="text-sm font-bold text-red-800">{{ formatNum(stats.rejected_households) }} रेकर्ड अस्वीकृत</p>
          <p class="text-[12px] text-red-500 mt-0.5">एनुमेरेटरले पुन: सम्पादन गर्न आवश्यक</p>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         WEEKLY PULSE DIGEST
    ══════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
        <div class="flex items-center gap-2">
          <i class="fa-solid fa-chart-line text-indigo-500 text-[13px]"></i>
          <span class="text-[13px] font-bold text-gray-800">साप्ताहिक पल्स</span>
          <span class="text-[11px] text-gray-400 bg-gray-50 border border-gray-100 px-2 py-0.5 rounded-full">पछिल्लो ७ दिन</span>
        </div>
        <a href="/admin/progress" class="text-[11px] text-indigo-600 hover:underline flex items-center gap-1">
          विस्तृत हेर्नुस् <i class="fa-solid fa-arrow-right text-[9px]"></i>
        </a>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-4 divide-y sm:divide-y-0 divide-x divide-gray-100">
        <!-- Submitted this week -->
        <div class="px-5 py-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-1">पेस गरिएको</p>
          <div class="flex items-baseline gap-2">
            <p class="text-[28px] font-black leading-none text-indigo-600">{{ digest.hh_submitted_week ?? 0 }}</p>
            <span v-if="digest.hh_submitted_delta !== undefined"
              :class="['text-[12px] font-semibold flex items-center gap-0.5',
                digest.hh_submitted_delta > 0 ? 'text-green-600' : digest.hh_submitted_delta < 0 ? 'text-red-500' : 'text-gray-400']">
              <i :class="digest.hh_submitted_delta > 0 ? 'fa-solid fa-arrow-trend-up' :
                         digest.hh_submitted_delta < 0 ? 'fa-solid fa-arrow-trend-down' : 'fa-solid fa-minus'"></i>
              {{ Math.abs(digest.hh_submitted_delta) }}
            </span>
          </div>
          <p class="text-[10px] text-gray-400 mt-1">घरधुरी सर्वेक्षण</p>
        </div>

        <!-- Approved this week -->
        <div class="px-5 py-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-1">अनुमोदित</p>
          <div class="flex items-baseline gap-2">
            <p class="text-[28px] font-black leading-none text-amber-600">{{ digest.hh_approved_week ?? 0 }}</p>
            <span v-if="digest.hh_approved_delta !== undefined"
              :class="['text-[12px] font-semibold flex items-center gap-0.5',
                digest.hh_approved_delta > 0 ? 'text-green-600' : digest.hh_approved_delta < 0 ? 'text-red-500' : 'text-gray-400']">
              <i :class="digest.hh_approved_delta > 0 ? 'fa-solid fa-arrow-trend-up' :
                         digest.hh_approved_delta < 0 ? 'fa-solid fa-arrow-trend-down' : 'fa-solid fa-minus'"></i>
              {{ Math.abs(digest.hh_approved_delta) }}
            </span>
          </div>
          <p class="text-[10px] text-gray-400 mt-1">वडाबाट अनुमोदन</p>
        </div>

        <!-- Published this week -->
        <div class="px-5 py-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-1">प्रकाशित</p>
          <div class="flex items-baseline gap-2">
            <p class="text-[28px] font-black leading-none text-green-600">{{ digest.hh_published_week ?? 0 }}</p>
            <span v-if="digest.hh_published_delta !== undefined"
              :class="['text-[12px] font-semibold flex items-center gap-0.5',
                digest.hh_published_delta > 0 ? 'text-green-600' : digest.hh_published_delta < 0 ? 'text-red-500' : 'text-gray-400']">
              <i :class="digest.hh_published_delta > 0 ? 'fa-solid fa-arrow-trend-up' :
                         digest.hh_published_delta < 0 ? 'fa-solid fa-arrow-trend-down' : 'fa-solid fa-minus'"></i>
              {{ Math.abs(digest.hh_published_delta) }}
            </span>
          </div>
          <p class="text-[10px] text-gray-400 mt-1">सार्वजनिक भएको</p>
        </div>

        <!-- Top spotlight -->
        <div class="px-5 py-4 bg-indigo-50/40">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-400 mb-2">यो हप्ताको</p>
          <div v-if="digest.top_enumerator" class="flex items-center gap-2 mb-1.5">
            <i class="fa-solid fa-star text-amber-400 text-[11px] shrink-0"></i>
            <span class="text-[12px] font-semibold text-gray-800 truncate">{{ digest.top_enumerator }}</span>
            <span class="text-[11px] text-indigo-600 font-bold shrink-0">{{ digest.top_enumerator_count }} पेस</span>
          </div>
          <div v-if="digest.top_ward_number" class="flex items-center gap-2">
            <i class="fa-solid fa-map-pin text-indigo-400 text-[11px] shrink-0"></i>
            <span class="text-[12px] text-gray-600">वडा {{ digest.top_ward_number }}</span>
            <span class="text-[11px] text-indigo-600 font-bold shrink-0">{{ digest.top_ward_count }} पेस</span>
          </div>
          <div v-if="!digest.top_enumerator && !digest.top_ward_number"
            class="text-[11px] text-gray-400 italic">यो हप्ता कुनै गतिविधि छैन</div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         DATA QUALITY BANNER
    ══════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 flex flex-wrap items-center gap-4">

      <!-- Label -->
      <div class="flex items-center gap-2 shrink-0 mr-1">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center"
          :class="hasQualityIssues ? 'bg-amber-100' : 'bg-green-100'">
          <i class="fa-solid text-sm"
            :class="hasQualityIssues ? 'fa-shield-halved text-amber-600' : 'fa-shield-check text-green-600'"></i>
        </div>
        <div>
          <p class="text-[13px] font-bold text-gray-700">डेटा गुणस्तर</p>
          <p class="text-[11px] text-gray-400">{{ hasQualityIssues ? 'ध्यान दिनुपर्ने समस्याहरू' : 'सबै ठीक छ' }}</p>
        </div>
      </div>

      <div class="h-8 w-px bg-gray-100 shrink-0 hidden sm:block"></div>

      <!-- Draft count -->
      <a href="/households?status=draft"
        :class="[
          'flex items-center gap-2.5 px-3.5 py-2 rounded-lg border transition-colors group',
          (stats.draft_households ?? 0) > 0
            ? 'border-slate-200 bg-slate-50 hover:bg-slate-100'
            : 'border-gray-100 bg-gray-50 hover:bg-gray-100'
        ]">
        <div class="w-6 h-6 rounded-md flex items-center justify-center transition-colors"
          :class="(stats.draft_households ?? 0) > 0 ? 'bg-slate-200 group-hover:bg-slate-300' : 'bg-green-100'">
          <i class="text-[10px]"
            :class="(stats.draft_households ?? 0) > 0 ? 'fa-solid fa-pen-to-square text-slate-600' : 'fa-solid fa-check text-green-600'"></i>
        </div>
        <div>
          <p class="text-[18px] font-black leading-none"
            :class="(stats.draft_households ?? 0) > 0 ? 'text-slate-700' : 'text-green-600'">
            {{ formatNum(stats.draft_households ?? 0) }}
          </p>
          <p class="text-[10px] font-medium leading-none mt-0.5"
            :class="(stats.draft_households ?? 0) > 0 ? 'text-slate-500' : 'text-green-500'">
            मस्यौदा बाँकी
          </p>
        </div>
        <i class="fa-solid fa-arrow-right text-[10px] transition-colors ml-0.5"
          :class="(stats.draft_households ?? 0) > 0 ? 'text-slate-300 group-hover:text-slate-500' : 'text-gray-200 group-hover:text-gray-400'"></i>
      </a>

      <!-- Missing GPS count -->
      <a href="/review/households?missing_gps=1"
        :class="[
          'flex items-center gap-2.5 px-3.5 py-2 rounded-lg border transition-colors group',
          (stats.missing_gps_households ?? 0) > 0
            ? 'border-red-200 bg-red-50 hover:bg-red-100'
            : 'border-gray-100 bg-gray-50 hover:bg-gray-100'
        ]">
        <div class="w-6 h-6 rounded-md flex items-center justify-center transition-colors"
          :class="(stats.missing_gps_households ?? 0) > 0 ? 'bg-red-200 group-hover:bg-red-300' : 'bg-green-100'">
          <i class="text-[10px]"
            :class="(stats.missing_gps_households ?? 0) > 0 ? 'fa-solid fa-location-dot text-red-700' : 'fa-solid fa-check text-green-600'"></i>
        </div>
        <div>
          <p class="text-[18px] font-black leading-none"
            :class="(stats.missing_gps_households ?? 0) > 0 ? 'text-red-700' : 'text-green-600'">
            {{ formatNum(stats.missing_gps_households ?? 0) }}
          </p>
          <p class="text-[10px] font-medium leading-none mt-0.5"
            :class="(stats.missing_gps_households ?? 0) > 0 ? 'text-red-500' : 'text-green-500'">
            GPS छैन
          </p>
        </div>
        <i class="fa-solid fa-arrow-right text-[10px] transition-colors ml-0.5"
          :class="(stats.missing_gps_households ?? 0) > 0 ? 'text-red-300 group-hover:text-red-500' : 'text-gray-200 group-hover:text-gray-400'"></i>
      </a>

      <!-- No members count -->
      <a href="/review/households?no_members=1"
        :class="[
          'flex items-center gap-2.5 px-3.5 py-2 rounded-lg border transition-colors group',
          (stats.no_member_households ?? 0) > 0
            ? 'border-orange-200 bg-orange-50 hover:bg-orange-100'
            : 'border-gray-100 bg-gray-50 hover:bg-gray-100'
        ]">
        <div class="w-6 h-6 rounded-md flex items-center justify-center transition-colors"
          :class="(stats.no_member_households ?? 0) > 0 ? 'bg-orange-200 group-hover:bg-orange-300' : 'bg-green-100'">
          <i class="text-[10px]"
            :class="(stats.no_member_households ?? 0) > 0 ? 'fa-solid fa-users text-orange-700' : 'fa-solid fa-check text-green-600'"></i>
        </div>
        <div>
          <p class="text-[18px] font-black leading-none"
            :class="(stats.no_member_households ?? 0) > 0 ? 'text-orange-700' : 'text-green-600'">
            {{ formatNum(stats.no_member_households ?? 0) }}
          </p>
          <p class="text-[10px] font-medium leading-none mt-0.5"
            :class="(stats.no_member_households ?? 0) > 0 ? 'text-orange-500' : 'text-green-500'">
            सदस्य छैन
          </p>
        </div>
        <i class="fa-solid fa-arrow-right text-[10px] transition-colors ml-0.5"
          :class="(stats.no_member_households ?? 0) > 0 ? 'text-orange-300 group-hover:text-orange-500' : 'text-gray-200 group-hover:text-gray-400'"></i>
      </a>

      <!-- Quality score ring -->
      <div class="ml-auto shrink-0 flex items-center gap-2">
        <div class="text-right">
          <p class="text-[11px] text-gray-400">गुणस्तर स्कोर</p>
          <p class="text-[22px] font-black leading-none"
            :class="qualityScore >= 90 ? 'text-green-600' : qualityScore >= 70 ? 'text-amber-600' : 'text-red-600'">
            {{ qualityScore }}%
          </p>
        </div>
        <div class="w-10 h-10 relative">
          <svg class="w-10 h-10 -rotate-90" viewBox="0 0 36 36">
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#f3f4f6" stroke-width="3.5"/>
            <circle cx="18" cy="18" r="15.9" fill="none"
              :stroke="qualityScore >= 90 ? '#22c55e' : qualityScore >= 70 ? '#f59e0b' : '#ef4444'"
              stroke-width="3.5"
              stroke-linecap="round"
              :stroke-dasharray="`${qualityScore} 100`"/>
          </svg>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         KPI CARDS — Row 1: data metrics (4 cards)
    ══════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

      <!-- Households -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 border-l-4 border-l-indigo-500 p-5">
        <div class="flex items-start justify-between mb-4">
          <div>
            <p class="text-[13px] font-medium text-gray-500 uppercase tracking-wide">घरधुरी</p>
            <p class="text-[32px] font-bold text-gray-900 leading-none mt-1">{{ formatNum(stats.total_households) }}</p>
          </div>
          <div class="w-11 h-11 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-house text-indigo-600 text-lg"></i>
          </div>
        </div>
        <div>
          <div class="flex justify-between text-[12px] mb-1.5">
            <span class="text-gray-400">प्रकाशित प्रगति</span>
            <span class="font-bold text-indigo-600">{{ publishedHHPercent }}%</span>
          </div>
          <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
            <div
              class="h-full bg-indigo-500 rounded-full transition-all duration-700"
              :style="{ width: publishedHHPercent + '%' }"
            ></div>
          </div>
          <p class="text-[12px] text-gray-400 mt-2">
            <span class="text-indigo-600 font-semibold">{{ formatNum(stats.published_households) }}</span>
            प्रकाशित ·
            <span class="text-gray-500 font-semibold">{{ formatNum(stats.draft_households) }}</span>
            मस्यौदा
          </p>
        </div>
      </div>

      <!-- Population -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 border-l-4 border-l-blue-500 p-5">
        <div class="flex items-start justify-between mb-4">
          <div>
            <p class="text-[13px] font-medium text-gray-500 uppercase tracking-wide">जनसंख्या</p>
            <p class="text-[32px] font-bold text-gray-900 leading-none mt-1">{{ formatNum(stats.total_population) }}</p>
          </div>
          <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-people-group text-blue-600 text-lg"></i>
          </div>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
          <span class="flex items-center gap-1.5 text-[12px] font-semibold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full">
            <i class="fa-solid fa-mars text-[10px]"></i>
            {{ genderNormalised.male ? Math.round(genderNormalised.male / (genderTotal || 1) * 100) : 0 }}%
          </span>
          <span class="flex items-center gap-1.5 text-[12px] font-semibold text-pink-600 bg-pink-50 px-2.5 py-1 rounded-full">
            <i class="fa-solid fa-venus text-[10px]"></i>
            {{ genderNormalised.female ? Math.round(genderNormalised.female / (genderTotal || 1) * 100) : 0 }}%
          </span>
          <span class="text-[11px] text-gray-400 ml-auto">प्रकाशितबाट</span>
        </div>
      </div>

      <!-- Institutions -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 border-l-4 border-l-teal-500 p-5">
        <div class="flex items-start justify-between mb-4">
          <div>
            <p class="text-[13px] font-medium text-gray-500 uppercase tracking-wide">संस्थाहरू</p>
            <p class="text-[32px] font-bold text-gray-900 leading-none mt-1">{{ formatNum(stats.total_institutions) }}</p>
          </div>
          <div class="w-11 h-11 rounded-xl bg-teal-50 flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-building text-teal-600 text-lg"></i>
          </div>
        </div>
        <div>
          <div class="flex justify-between text-[12px] mb-1.5">
            <span class="text-gray-400">प्रकाशित प्रगति</span>
            <span class="font-bold text-teal-600">{{ publishedInstPercent }}%</span>
          </div>
          <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
            <div
              class="h-full bg-teal-500 rounded-full transition-all duration-700"
              :style="{ width: publishedInstPercent + '%' }"
            ></div>
          </div>
          <p class="text-[12px] text-gray-400 mt-2">
            <span class="text-teal-600 font-semibold">{{ formatNum(stats.published_institutions) }}</span> प्रकाशित ·
            <span class="text-gray-500 font-semibold">{{ formatNum(stats.submitted_institutions) }}</span> पेस
          </p>
        </div>
      </div>

      <!-- Businesses -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 border-l-4 border-l-orange-500 p-5">
        <div class="flex items-start justify-between mb-4">
          <div>
            <p class="text-[13px] font-medium text-gray-500 uppercase tracking-wide">व्यवसाय</p>
            <p class="text-[32px] font-bold text-gray-900 leading-none mt-1">{{ formatNum(stats.total_businesses) }}</p>
          </div>
          <div class="w-11 h-11 rounded-xl bg-orange-50 flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-store text-orange-600 text-lg"></i>
          </div>
        </div>
        <div>
          <div class="flex justify-between text-[12px] mb-1.5">
            <span class="text-gray-400">प्रकाशित प्रगति</span>
            <span class="font-bold text-orange-600">{{ publishedBizPercent }}%</span>
          </div>
          <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
            <div
              class="h-full bg-orange-500 rounded-full transition-all duration-700"
              :style="{ width: publishedBizPercent + '%' }"
            ></div>
          </div>
          <p class="text-[12px] text-gray-400 mt-2">
            <span class="text-orange-600 font-semibold">{{ formatNum(stats.published_businesses) }}</span> प्रकाशित ·
            <span class="text-gray-500 font-semibold">{{ formatNum(stats.submitted_businesses) }}</span> पेस
          </p>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         KPI CARDS — Row 2: workflow action cards (2 cards)
    ══════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

      <!-- Pending Review (clickable → /review) -->
      <a
        href="/review"
        :class="[
          'bg-white rounded-xl shadow-sm border border-gray-200 border-l-4 p-5 block hover:shadow-md transition-shadow group',
          stats.submitted_households > 0 ? 'border-l-amber-500' : 'border-l-green-500'
        ]"
      >
        <div class="flex items-start justify-between mb-4">
          <div>
            <p class="text-[13px] font-medium text-gray-500 uppercase tracking-wide">समीक्षा बाँकी</p>
            <p
              class="text-[32px] font-bold leading-none mt-1"
              :class="stats.submitted_households > 0 ? 'text-amber-600' : 'text-green-600'"
            >{{ formatNum(stats.submitted_households) }}</p>
          </div>
          <div
            class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0"
            :class="stats.submitted_households > 0 ? 'bg-amber-50' : 'bg-green-50'"
          >
            <i :class="['fa-solid text-lg', stats.submitted_households > 0 ? 'fa-clock text-amber-600' : 'fa-circle-check text-green-600']"></i>
          </div>
        </div>
        <p class="text-[12px] text-gray-400">
          वडा समीक्षकलाई पेस भएको
          <i class="fa-solid fa-arrow-right text-gray-300 ml-1.5 group-hover:text-amber-400 transition-colors text-[10px]"></i>
        </p>
      </a>

      <!-- Publish Queue (clickable → /admin/publish-queue) -->
      <a
        href="/admin/publish-queue"
        :class="[
          'bg-white rounded-xl shadow-sm border border-gray-200 border-l-4 p-5 block hover:shadow-md transition-shadow group',
          stats.ward_approved_households > 0 ? 'border-l-blue-500' : 'border-l-green-500'
        ]"
      >
        <div class="flex items-start justify-between mb-4">
          <div>
            <p class="text-[13px] font-medium text-gray-500 uppercase tracking-wide">प्रकाशन बाँकी</p>
            <p
              class="text-[32px] font-bold leading-none mt-1"
              :class="stats.ward_approved_households > 0 ? 'text-blue-600' : 'text-green-600'"
            >{{ formatNum(stats.ward_approved_households) }}</p>
          </div>
          <div
            class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0"
            :class="stats.ward_approved_households > 0 ? 'bg-blue-50' : 'bg-green-50'"
          >
            <i :class="['fa-solid text-lg', stats.ward_approved_households > 0 ? 'fa-paper-plane text-blue-600' : 'fa-circle-check text-green-600']"></i>
          </div>
        </div>
        <p class="text-[12px] text-gray-400">
          वडाबाट अनुमोदित — प्रकाशित गर्नुहोस्
          <i class="fa-solid fa-arrow-right text-gray-300 ml-1.5 group-hover:text-blue-400 transition-colors text-[10px]"></i>
        </p>
      </a>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         WORKFLOW PIPELINE FUNNEL
    ══════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-[15px] font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-arrow-right-arrow-left text-indigo-500"></i>
          सर्वेक्षण कार्यप्रवाह
        </h2>
        <span class="text-[12px] text-gray-400">{{ formatNum(stats.total_households) }} जम्मा घरधुरी</span>
      </div>

      <div class="flex items-stretch gap-1.5 flex-wrap">
        <template v-for="(step, idx) in workflowSteps" :key="step.key">
          <a
            :href="step.href"
            :class="[
              'flex-1 min-w-[110px] rounded-xl px-4 py-4 text-center transition-all hover:shadow-md hover:scale-[1.02] group',
              step.bg
            ]"
          >
            <p class="text-[26px] font-black leading-none" :class="step.textColor">
              {{ formatNum(statusBreakdown[step.key] || 0) }}
            </p>
            <p class="text-[12px] font-semibold mt-1.5" :class="step.labelColor">
              {{ step.label }}
            </p>
            <div class="mt-2 h-1 w-8 mx-auto rounded-full opacity-40 group-hover:opacity-70 transition-opacity" :class="step.accentBar"></div>
          </a>
          <div
            v-if="idx < workflowSteps.length - 1"
            class="flex items-center px-0.5 text-gray-200 flex-shrink-0 self-center"
          >
            <i class="fa-solid fa-chevron-right text-base"></i>
          </div>
        </template>
      </div>

      <!-- Completion rate bar -->
      <div class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-4">
        <div class="flex-1">
          <div class="flex justify-between text-[12px] mb-1.5">
            <span class="text-gray-500 font-medium">समग्र प्रकाशन दर</span>
            <span class="font-bold text-green-600">{{ publishedHHPercent }}%</span>
          </div>
          <div class="w-full h-2.5 bg-gray-100 rounded-full overflow-hidden">
            <div
              class="h-full rounded-full transition-all duration-1000"
              :class="publishedHHPercent >= 80 ? 'bg-green-500' : publishedHHPercent >= 50 ? 'bg-amber-500' : 'bg-indigo-500'"
              :style="{ width: publishedHHPercent + '%' }"
            ></div>
          </div>
        </div>
        <div class="text-right flex-shrink-0">
          <p class="text-[11px] text-gray-400">प्रयोगकर्ता</p>
          <p class="text-sm font-bold text-gray-700">
            {{ formatNum(stats.total_users) }}
            <span class="text-[11px] font-normal text-gray-400">/ {{ stats.active_enumerators }} सक्रिय</span>
          </p>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         CHARTS ROW  — Bar trend + Ward progress bars
    ══════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

      <!-- 7-day Submission Trend -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-[15px] font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-chart-bar text-indigo-500"></i> ७ दिनको पेश्कदम
          </h2>
          <span class="text-[11px] text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full">घरधुरी पेस</span>
        </div>
        <div class="h-56">
          <canvas ref="barCanvas"></canvas>
        </div>
      </div>

      <!-- Ward Progress Bars -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-[15px] font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-map-location-dot text-indigo-500"></i> वडागत प्रगति
          </h2>
          <span class="text-[11px] text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full">प्रकाशन दर</span>
        </div>
        <div class="space-y-3.5 overflow-y-auto max-h-56 pr-1">
          <div v-for="ward in wardSummary" :key="ward.id">
            <div class="flex justify-between items-baseline mb-1.5">
              <a
                :href="`/households?ward_id=${ward.id}`"
                class="text-[13px] font-semibold text-gray-700 hover:text-indigo-600 transition-colors"
              >वडा {{ ward.ward_number }}</a>
              <div class="flex items-center gap-2 text-[12px]">
                <span class="text-gray-400">{{ ward.published_count }}/{{ ward.households_count }}</span>
                <span
                  class="font-bold w-9 text-right"
                  :class="wardCompletionPct(ward) >= 80 ? 'text-green-600' : wardCompletionPct(ward) >= 50 ? 'text-amber-600' : 'text-red-500'"
                >{{ wardCompletionPct(ward) }}%</span>
              </div>
            </div>
            <div class="w-full h-2.5 bg-gray-100 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-700"
                :class="wardCompletionPct(ward) >= 80 ? 'bg-green-500' : wardCompletionPct(ward) >= 50 ? 'bg-amber-400' : 'bg-red-400'"
                :style="{ width: wardCompletionPct(ward) + '%' }"
              ></div>
            </div>
          </div>
          <div v-if="!wardSummary?.length" class="text-center text-gray-400 py-8 text-sm">कुनै डेटा छैन</div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         ACTIVITY FEED  +  LEADERBOARD + INSTITUTION TYPES
    ══════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

      <!-- Recent Activity (2/3 width) -->
      <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
          <h2 class="text-[15px] font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-bolt text-amber-500"></i> हालसालको गतिविधि
          </h2>
          <span class="text-[12px] text-gray-400 bg-gray-50 px-2.5 py-1 rounded-full border border-gray-100">
            {{ filteredActivity.length }} रेकर्ड
          </span>
        </div>

        <div class="flex-1 overflow-y-auto max-h-[400px] divide-y divide-gray-50">
          <a
            v-for="(act, idx) in filteredActivity"
            :key="idx"
            :href="act.url || '#'"
            class="flex items-start gap-3 px-5 py-3.5 hover:bg-gray-50 transition-colors group block"
          >
            <!-- Type icon -->
            <div
              class="w-9 h-9 rounded-lg flex-shrink-0 flex items-center justify-center mt-0.5"
              :class="act.type === 'institution' ? 'bg-teal-50' : 'bg-indigo-50'"
            >
              <i
                :class="[
                  'fa-solid text-sm',
                  act.type === 'institution' ? 'fa-building text-teal-600' : 'fa-house text-indigo-600'
                ]"
              ></i>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-semibold text-gray-900 group-hover:text-indigo-700 transition-colors truncate">
                  {{ act.name }}
                </span>
                <span class="text-[11px] text-gray-400 bg-gray-100 px-2 py-0.5 rounded font-mono flex-shrink-0">
                  {{ act.code }}
                </span>
                <span
                  :class="[
                    'text-[11px] px-2 py-0.5 rounded-full font-semibold flex-shrink-0',
                    statusBadge(act.status)
                  ]"
                >{{ statusLabel(act.status) }}</span>
              </div>
              <div class="flex items-center gap-3 mt-1 text-[12px] text-gray-400 flex-wrap">
                <span v-if="act.ward">
                  <i class="fa-solid fa-map-pin mr-1 text-[10px]"></i>वडा {{ act.ward }}
                </span>
                <span v-if="act.enumerator">
                  <i class="fa-solid fa-user mr-1 text-[10px]"></i>{{ act.enumerator }}
                </span>
                <span>
                  <i class="fa-regular fa-clock mr-1 text-[10px]"></i>{{ timeAgo(act.updated_at) }}
                </span>
              </div>
            </div>

            <!-- Arrow -->
            <i class="fa-solid fa-arrow-up-right-from-square text-[11px] text-gray-300 group-hover:text-indigo-400 transition-colors flex-shrink-0 mt-1.5"></i>
          </a>

          <!-- Empty state -->
          <div v-if="!filteredActivity.length" class="text-center py-12 text-gray-400">
            <i class="fa-solid fa-inbox text-4xl text-gray-200 mb-3 block"></i>
            <p class="text-sm font-medium">कुनै गतिविधि फेला परेन</p>
            <button
              v-if="hasFilters"
              @click="resetFilters"
              class="mt-2 text-[12px] text-indigo-600 hover:underline"
            >फिल्टर हटाउनुहोस्</button>
          </div>
        </div>
      </div>

      <!-- Right column: Leaderboard + Institution Types -->
      <div class="flex flex-col gap-4">

        <!-- Enumerator Leaderboard -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex-1">
          <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-[15px] font-bold text-gray-800 flex items-center gap-2">
              <i class="fa-solid fa-ranking-star text-amber-500"></i> गणक लिडरबोर्ड
            </h2>
          </div>
          <div class="divide-y divide-gray-50">
            <div
              v-for="(e, idx) in enumeratorLeaderboard"
              :key="e.id"
              class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors"
            >
              <!-- Rank badge -->
              <div
                class="w-7 h-7 rounded-lg flex items-center justify-center text-[12px] font-black flex-shrink-0"
                :class="rankClass(idx)"
              >{{ idx + 1 }}</div>

              <!-- Name -->
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate">{{ e.name }}</p>
                <p class="text-[11px] text-gray-400">
                  <span class="text-green-600 font-semibold">{{ e.total_published }}</span> प्रकाशित
                </p>
              </div>

              <!-- Submitted badge -->
              <span class="bg-indigo-50 text-indigo-700 border border-indigo-100 rounded-full text-[11px] font-bold px-2.5 py-0.5">
                {{ e.total_submitted }}
              </span>
            </div>

            <div v-if="!enumeratorLeaderboard?.length" class="text-center py-8 text-gray-400">
              <i class="fa-solid fa-trophy text-2xl text-gray-200 mb-2 block"></i>
              <p class="text-sm">कुनै डेटा छैन</p>
            </div>
          </div>
        </div>

        <!-- Institution Types -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-[15px] font-bold text-gray-800 flex items-center gap-2">
              <i class="fa-solid fa-building-columns text-teal-500"></i> संस्था प्रकार
            </h2>
          </div>
          <div class="px-5 py-3 space-y-2.5 overflow-y-auto max-h-52">
            <div
              v-for="type in institutionTypes"
              :key="type.id"
              class="flex items-center gap-3"
            >
              <span class="flex-1 text-[13px] text-gray-600 truncate">{{ type.name_np }}</span>
              <div class="w-20 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div
                  class="h-full bg-teal-500 rounded-full transition-all duration-700"
                  :style="{ width: instTypeWidth(type.institutions_count) + '%' }"
                ></div>
              </div>
              <span class="text-[12px] font-bold text-teal-700 w-6 text-right">{{ type.institutions_count }}</span>
            </div>
            <div v-if="!institutionTypes?.length" class="text-center py-4 text-gray-400 text-sm">कुनै डेटा छैन</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         ENUMERATOR PROGRESS TABLE
    ══════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center gap-3">
        <h2 class="text-[15px] font-bold text-gray-800 flex items-center gap-2 flex-1">
          <i class="fa-solid fa-users text-indigo-500"></i> गणक प्रगति
          <span class="text-[12px] font-normal text-gray-400 ml-1">{{ filteredEnumerators.length }} गणक</span>
        </h2>

        <!-- Search -->
        <div class="relative w-52">
          <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
          <input
            v-model="enumSearch"
            type="text"
            placeholder="नामले खोज्नुहोस्…"
            class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-gray-50"
          />
        </div>

        <!-- Sort -->
        <select
          v-model="enumSort"
          class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-600 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-400"
        >
          <option value="name">नामअनुसार</option>
          <option value="hh_total">जम्मा घरधुरी</option>
          <option value="hh_published">प्रकाशित</option>
          <option value="hh_submitted">पेस भएको</option>
          <option value="hh_rejected">अस्वीकृत</option>
          <option value="last_active">अन्तिम सक्रिय</option>
        </select>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">गणक</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">वडा</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">जम्मा</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">मस्यौदा</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">पेस</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">प्रकाशित</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">अस्वीकृत</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">संस्था</th>
              <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider min-w-[160px]">प्रगति</th>
              <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">अन्तिम सक्रिय</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr
              v-for="e in filteredEnumerators"
              :key="e.id"
              class="hover:bg-gray-50 transition-colors"
            >
              <!-- Name + active dot -->
              <td class="px-5 py-3">
                <div class="flex items-center gap-2">
                  <span
                    class="w-2 h-2 rounded-full flex-shrink-0"
                    :class="e.is_active ? 'bg-green-400' : 'bg-gray-300'"
                    :title="e.is_active ? 'सक्रिय' : 'निष्क्रिय'"
                  ></span>
                  <a
                    :href="`/admin/users/${e.id}/edit`"
                    class="text-sm font-semibold text-gray-800 hover:text-indigo-600 transition-colors"
                  >{{ e.name }}</a>
                </div>
              </td>

              <!-- Assigned wards -->
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="w in e.wards"
                    :key="w"
                    class="inline-flex items-center text-[10px] font-bold bg-indigo-50 text-indigo-600 border border-indigo-100 px-1.5 py-0.5 rounded"
                  >{{ w }}</span>
                  <span v-if="!e.wards?.length" class="text-[11px] text-gray-300">—</span>
                </div>
              </td>

              <!-- Total -->
              <td class="px-4 py-3 text-right">
                <span class="text-sm font-bold text-gray-700">{{ formatNum(e.hh_total) }}</span>
              </td>

              <!-- Draft -->
              <td class="px-4 py-3 text-right">
                <span :class="e.hh_draft > 0 ? 'text-gray-500 font-semibold' : 'text-gray-300'" class="text-[13px]">
                  {{ e.hh_draft > 0 ? formatNum(e.hh_draft) : '—' }}
                </span>
              </td>

              <!-- Submitted -->
              <td class="px-4 py-3 text-right">
                <span
                  v-if="e.hh_submitted + e.hh_ward_approved > 0"
                  class="inline-flex items-center bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full text-[12px] font-bold"
                >{{ formatNum(e.hh_submitted + e.hh_ward_approved) }}</span>
                <span v-else class="text-gray-300 text-[13px]">—</span>
              </td>

              <!-- Published -->
              <td class="px-4 py-3 text-right">
                <span
                  v-if="e.hh_published > 0"
                  class="inline-flex items-center gap-1 bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-[12px] font-bold"
                >
                  <i class="fa-solid fa-check text-[9px]"></i>{{ formatNum(e.hh_published) }}
                </span>
                <span v-else class="text-gray-300 text-[13px]">—</span>
              </td>

              <!-- Rejected -->
              <td class="px-4 py-3 text-right">
                <span
                  v-if="e.hh_rejected > 0"
                  class="inline-flex items-center gap-1 bg-red-100 text-red-600 px-2 py-0.5 rounded-full text-[12px] font-bold"
                >
                  <i class="fa-solid fa-exclamation text-[9px]"></i>{{ formatNum(e.hh_rejected) }}
                </span>
                <span v-else class="text-gray-300 text-[13px]">—</span>
              </td>

              <!-- Institutions -->
              <td class="px-4 py-3 text-right">
                <span
                  v-if="e.inst_total > 0"
                  class="inline-flex items-center gap-1 bg-teal-50 text-teal-700 border border-teal-100 px-2 py-0.5 rounded-full text-[12px] font-bold"
                >
                  <i class="fa-solid fa-building text-[9px]"></i>{{ formatNum(e.inst_total) }}
                </span>
                <span v-else class="text-gray-300 text-[13px]">—</span>
              </td>

              <!-- Progress bar -->
              <td class="px-5 py-3">
                <div class="flex items-center gap-2">
                  <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden relative" style="min-width:80px">
                    <!-- Published (green) -->
                    <div
                      class="absolute left-0 top-0 h-full bg-green-500 rounded-full transition-all duration-700"
                      :style="{ width: enumPct(e, 'hh_published') + '%' }"
                    ></div>
                    <!-- Submitted overlay (amber, stacked) -->
                    <div
                      class="absolute top-0 h-full bg-amber-400 rounded-full transition-all duration-700"
                      :style="{ left: enumPct(e, 'hh_published') + '%', width: enumPct(e, 'hh_submitted') + '%' }"
                    ></div>
                  </div>
                  <span
                    class="text-[12px] font-bold w-8 text-right flex-shrink-0"
                    :class="enumPct(e, 'hh_published') >= 80 ? 'text-green-600' : enumPct(e, 'hh_published') >= 40 ? 'text-amber-600' : 'text-gray-400'"
                  >{{ enumPct(e, 'hh_published') }}%</span>
                </div>
              </td>

              <!-- Last active -->
              <td class="px-5 py-3 text-[12px] text-gray-400">
                {{ e.last_active_at ? timeAgo(e.last_active_at) : '—' }}
              </td>
            </tr>

            <tr v-if="!filteredEnumerators.length">
              <td colspan="10" class="px-5 py-10 text-center text-gray-400">
                <i class="fa-solid fa-users-slash text-3xl text-gray-200 mb-2 block"></i>
                <p class="text-sm">कुनै गणक फेला परेन</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         WARD SUMMARY TABLE
    ══════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-[15px] font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-table-list text-indigo-500"></i> वडा सारांश
        </h2>
        <span class="text-[12px] text-gray-400">{{ filteredWardSummary.length }} वडा</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">वडा</th>
              <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">घरधुरी</th>
              <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">प्रकाशित</th>
              <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">बाँकी</th>
              <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">अस्वीकृत</th>
              <th class="px-5 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider min-w-[140px]">प्रगति</th>
              <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">संस्था</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr
              v-for="ward in filteredWardSummary"
              :key="ward.id"
              class="hover:bg-gray-50 transition-colors"
              :class="ward.rejected_count > 0 ? 'bg-red-50/40' : ''"
            >
              <!-- Ward badge -->
              <td class="px-5 py-3">
                <a
                  :href="`/households?ward_id=${ward.id}`"
                  class="inline-flex items-center gap-1.5 bg-indigo-50 text-indigo-700 border border-indigo-100 px-2.5 py-1 rounded-lg text-[13px] font-bold hover:bg-indigo-100 transition-colors"
                >
                  <i class="fa-solid fa-map-pin text-[10px]"></i> वडा {{ ward.ward_number }}
                </a>
              </td>

              <!-- Total -->
              <td class="px-5 py-3 text-right text-sm font-bold text-gray-800">
                {{ formatNum(ward.households_count) }}
              </td>

              <!-- Published -->
              <td class="px-5 py-3 text-right">
                <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-[12px] font-bold">
                  <i class="fa-solid fa-check text-[9px]"></i> {{ formatNum(ward.published_count) }}
                </span>
              </td>

              <!-- Pending -->
              <td class="px-5 py-3 text-right">
                <span :class="[
                  'inline-flex items-center px-2 py-0.5 rounded-full text-[12px] font-bold',
                  ward.pending_count > 0 ? 'bg-amber-100 text-amber-700' : 'text-gray-300'
                ]">
                  {{ ward.pending_count > 0 ? formatNum(ward.pending_count) : '—' }}
                </span>
              </td>

              <!-- Rejected -->
              <td class="px-5 py-3 text-right">
                <span :class="[
                  'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[12px] font-bold',
                  ward.rejected_count > 0 ? 'bg-red-100 text-red-600' : 'text-gray-300'
                ]">
                  <i v-if="ward.rejected_count > 0" class="fa-solid fa-exclamation text-[9px]"></i>
                  {{ ward.rejected_count > 0 ? formatNum(ward.rejected_count) : '—' }}
                </span>
              </td>

              <!-- Progress bar -->
              <td class="px-5 py-3">
                <div class="flex items-center gap-2.5">
                  <div class="flex-1 h-2.5 bg-gray-100 rounded-full overflow-hidden">
                    <div
                      class="h-full rounded-full transition-all duration-700"
                      :class="wardCompletionPct(ward) >= 80 ? 'bg-green-500' : wardCompletionPct(ward) >= 50 ? 'bg-amber-400' : 'bg-red-400'"
                      :style="{ width: wardCompletionPct(ward) + '%' }"
                    ></div>
                  </div>
                  <span
                    class="text-[12px] font-bold w-9 text-right flex-shrink-0"
                    :class="wardCompletionPct(ward) >= 80 ? 'text-green-600' : wardCompletionPct(ward) >= 50 ? 'text-amber-600' : 'text-red-500'"
                  >{{ wardCompletionPct(ward) }}%</span>
                </div>
              </td>

              <!-- Institutions -->
              <td class="px-5 py-3 text-right">
                <span class="inline-flex items-center gap-1 text-[12px] font-bold text-teal-700 bg-teal-50 border border-teal-100 px-2 py-0.5 rounded-full">
                  <i class="fa-solid fa-building text-[9px]"></i> {{ formatNum(ward.institutions_count) }}
                </span>
              </td>
            </tr>

            <tr v-if="!filteredWardSummary.length">
              <td colspan="7" class="px-5 py-10 text-center text-gray-400">
                <i class="fa-solid fa-map text-3xl text-gray-200 mb-2 block"></i>
                <p class="text-sm">कुनै वडा डेटा छैन</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
  Chart,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
} from 'chart.js'

Chart.register(CategoryScale, LinearScale, BarElement, Title, Tooltip)

// ── Props ──────────────────────────────────────────────────────────────────────
const props = defineProps({
  stats:                { type: Object, default: () => ({}) },
  genderBreakdown:      { type: Object, default: () => ({}) },
  statusBreakdown:      { type: Object, default: () => ({}) },
  institutionTypes:     { type: Array,  default: () => [] },
  enumeratorLeaderboard:{ type: Array,  default: () => [] },
  enumeratorProgress:   { type: Array,  default: () => [] },
  trendData:            { type: Object, default: () => ({}) },
  wardSummary:          { type: Array,  default: () => [] },
  recentActivity:       { type: Array,  default: () => [] },
  digest:               { type: Object, default: () => ({}) },
})

// ── Enumerator progress filter/sort ───────────────────────────────────────────
const enumSearch = ref('')
const enumSort   = ref('name')

const filteredEnumerators = computed(() => {
  let list = [...(props.enumeratorProgress || [])]
  const q = enumSearch.value.toLowerCase().trim()
  if (q) list = list.filter(e => e.name.toLowerCase().includes(q))

  list.sort((a, b) => {
    if (enumSort.value === 'name')        return a.name.localeCompare(b.name)
    if (enumSort.value === 'hh_total')    return b.hh_total - a.hh_total
    if (enumSort.value === 'hh_published')return b.hh_published - a.hh_published
    if (enumSort.value === 'hh_submitted')return (b.hh_submitted + b.hh_ward_approved) - (a.hh_submitted + a.hh_ward_approved)
    if (enumSort.value === 'hh_rejected') return b.hh_rejected - a.hh_rejected
    if (enumSort.value === 'last_active') {
      const ta = a.last_active_at ? new Date(a.last_active_at).getTime() : 0
      const tb = b.last_active_at ? new Date(b.last_active_at).getTime() : 0
      return tb - ta
    }
    return 0
  })
  return list
})

function enumPct(e, field) {
  if (!e.hh_total) return 0
  return Math.round((e[field] / e.hh_total) * 100)
}

// ── Filter state ───────────────────────────────────────────────────────────────
const searchQuery  = ref('')
const filterWard   = ref('')
const filterStatus = ref('')
const filterDate   = ref('all')

const hasFilters = computed(() =>
  !!searchQuery.value || !!filterWard.value || !!filterStatus.value || filterDate.value !== 'all'
)

function resetFilters() {
  searchQuery.value  = ''
  filterWard.value   = ''
  filterStatus.value = ''
  filterDate.value   = 'all'
}

// Status pills config
const statusPills = [
  { key: 'submitted',     label: 'पेस',         activeClass: 'border-amber-400 bg-amber-50 text-amber-700' },
  { key: 'ward_approved', label: 'अनुमोदित',    activeClass: 'border-blue-400 bg-blue-50 text-blue-700' },
  { key: 'published',     label: 'प्रकाशित',    activeClass: 'border-green-400 bg-green-50 text-green-700' },
  { key: 'rejected',      label: 'अस्वीकृत',    activeClass: 'border-red-400 bg-red-50 text-red-700' },
  { key: 'draft',         label: 'मस्यौदा',     activeClass: 'border-gray-400 bg-gray-100 text-gray-700' },
]

// ── Filtered activity ──────────────────────────────────────────────────────────
const filteredActivity = computed(() => {
  let list = props.recentActivity || []
  const q = searchQuery.value.toLowerCase().trim()

  if (q) {
    list = list.filter(a =>
      (a.name       || '').toLowerCase().includes(q) ||
      (a.code       || '').toLowerCase().includes(q) ||
      String(a.ward || '').includes(q) ||
      (a.enumerator || '').toLowerCase().includes(q)
    )
  }
  if (filterWard.value) {
    list = list.filter(a => String(a.ward) === String(filterWard.value))
  }
  if (filterStatus.value) {
    list = list.filter(a => a.status === filterStatus.value)
  }
  if (filterDate.value !== 'all') {
    const now    = Date.now()
    const cutoff = filterDate.value === 'today'
      ? new Date(new Date().setHours(0, 0, 0, 0)).getTime()
      : now - 7 * 24 * 60 * 60 * 1000
    list = list.filter(a => new Date(a.updated_at).getTime() >= cutoff)
  }
  return list
})

// ── Filtered ward summary ──────────────────────────────────────────────────────
const filteredWardSummary = computed(() => {
  if (!filterWard.value) return props.wardSummary || []
  return (props.wardSummary || []).filter(w => String(w.ward_number) === String(filterWard.value))
})

// ── Ward numbers for filter dropdown ──────────────────────────────────────────
const wardNumbers = computed(() =>
  [...new Set((props.wardSummary || []).map(w => w.ward_number))].sort((a, b) => a - b)
)

// ── Workflow steps ─────────────────────────────────────────────────────────────
const workflowSteps = [
  { key: 'draft',         label: 'मस्यौदा',       bg: 'bg-gray-100 hover:bg-gray-200',    textColor: 'text-gray-700',  labelColor: 'text-gray-500',  accentBar: 'bg-gray-400',   href: '/households?status=draft' },
  { key: 'submitted',     label: 'पेस भएको',       bg: 'bg-amber-50 hover:bg-amber-100',   textColor: 'text-amber-700', labelColor: 'text-amber-600', accentBar: 'bg-amber-500',  href: '/households?status=submitted' },
  { key: 'ward_approved', label: 'वडा अनुमोदित',  bg: 'bg-blue-50 hover:bg-blue-100',     textColor: 'text-blue-700',  labelColor: 'text-blue-600',  accentBar: 'bg-blue-500',   href: '/households?status=ward_approved' },
  { key: 'published',     label: 'प्रकाशित',       bg: 'bg-green-50 hover:bg-green-100',   textColor: 'text-green-700', labelColor: 'text-green-600', accentBar: 'bg-green-500',  href: '/households?status=published' },
  { key: 'rejected',      label: 'अस्वीकृत',       bg: 'bg-red-50 hover:bg-red-100',       textColor: 'text-red-600',   labelColor: 'text-red-500',   accentBar: 'bg-red-400',    href: '/households?status=rejected' },
]

// ── Helper functions ───────────────────────────────────────────────────────────
function formatNum(n) {
  if (n === null || n === undefined) return '0'
  return Number(n).toLocaleString('ne-NP')
}

function wardCompletionPct(ward) {
  const total = ward.households_count || 0
  if (!total) return 0
  return Math.round((ward.published_count / total) * 100)
}

function statusBadge(s) {
  const map = {
    draft:         'bg-gray-100 text-gray-600',
    submitted:     'bg-amber-100 text-amber-700',
    ward_approved: 'bg-blue-100 text-blue-700',
    rejected:      'bg-red-100 text-red-700',
    published:     'bg-green-100 text-green-700',
  }
  return map[s] ?? 'bg-gray-100 text-gray-600'
}

function statusLabel(s) {
  const map = {
    draft:         'मस्यौदा',
    submitted:     'पेस भएको',
    ward_approved: 'वडा अनुमोदित',
    rejected:      'अस्वीकृत',
    published:     'प्रकाशित',
  }
  return map[s] ?? s
}

function timeAgo(dateStr) {
  if (!dateStr) return ''
  const diffSec = Math.floor((Date.now() - new Date(dateStr)) / 1000)
  if (diffSec < 60)   return 'भर्खरै'
  if (diffSec < 3600) return `${Math.floor(diffSec / 60)} मिनेट अघि`
  if (diffSec < 86400)return `${Math.floor(diffSec / 3600)} घण्टा अघि`
  const diffDay = Math.floor(diffSec / 86400)
  if (diffDay < 7)    return `${diffDay} दिन अघि`
  return new Date(dateStr).toLocaleDateString('ne-NP')
}

function rankClass(idx) {
  if (idx === 0) return 'bg-yellow-100 text-yellow-700'
  if (idx === 1) return 'bg-gray-100 text-gray-500'
  if (idx === 2) return 'bg-orange-100 text-orange-600'
  return 'bg-gray-50 text-gray-400'
}

const maxInstCount = computed(() =>
  Math.max(...(props.institutionTypes || []).map(t => t.institutions_count), 1)
)
function instTypeWidth(count) {
  return Math.round((count / maxInstCount.value) * 100)
}

// ── Data quality ───────────────────────────────────────────────────────────────
const hasQualityIssues = computed(() =>
  (props.stats.draft_households       ?? 0) > 0 ||
  (props.stats.missing_gps_households ?? 0) > 0 ||
  (props.stats.no_member_households   ?? 0) > 0
)

const qualityScore = computed(() => {
  const total = props.stats.total_households || 0
  if (!total) return 100
  const issues = Math.min(
    (props.stats.missing_gps_households ?? 0) +
    (props.stats.no_member_households   ?? 0) +
    (props.stats.draft_households       ?? 0),
    total
  )
  return Math.max(0, Math.round((1 - issues / total) * 100))
})

// ── Computed stats ─────────────────────────────────────────────────────────────
const publishedHHPercent = computed(() => {
  const total = props.stats.total_households || 0
  if (!total) return 0
  return Math.round((props.stats.published_households / total) * 100)
})

const publishedInstPercent = computed(() => {
  const total = props.stats.total_institutions || 0
  if (!total) return 0
  return Math.round((props.stats.published_institutions / total) * 100)
})

const publishedBizPercent = computed(() => {
  const total = props.stats.total_businesses || 0
  if (!total) return 0
  return Math.round((props.stats.published_businesses / total) * 100)
})


const genderNormalised = computed(() => {
  const g = props.genderBreakdown || {}
  return {
    male:   g['पुरुष'] ?? g['male']   ?? 0,
    female: g['महिला'] ?? g['female'] ?? 0,
    other:  g['अन्य']  ?? g['other']  ?? 0,
  }
})

const genderTotal = computed(() =>
  Object.values(genderNormalised.value).reduce((a, b) => a + b, 0)
)

// ── Chart ──────────────────────────────────────────────────────────────────────
const barCanvas = ref(null)
let barChart = null

onMounted(() => {
  if (!barCanvas.value) return

  const td     = props.trendData || {}
  const labels = Object.keys(td).map(d => {
    const dt = new Date(d)
    return `${dt.getMonth() + 1}/${dt.getDate()}`
  })
  const data = Object.values(td)
  const maxVal = Math.max(...data, 1)

  barChart = new Chart(barCanvas.value, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'पेश भएको',
        data,
        backgroundColor: data.map(v =>
          v === maxVal ? 'rgba(99,102,241,0.9)' : 'rgba(99,102,241,0.45)'
        ),
        borderColor:     'rgba(99,102,241,1)',
        borderWidth:     0,
        borderRadius:    6,
        borderSkipped:   false,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1e293b',
          titleColor: '#94a3b8',
          bodyColor: '#f8fafc',
          padding: 10,
          cornerRadius: 8,
          callbacks: {
            label: ctx => `  ${ctx.parsed.y} घरधुरी`,
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          border: { display: false },
          ticks: { font: { size: 12, family: 'Inter' }, color: '#9ca3af' },
        },
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false },
          border: { display: false },
          ticks: { font: { size: 12, family: 'Inter' }, color: '#9ca3af', stepSize: 1 },
        },
      },
    },
  })
})

onUnmounted(() => { barChart?.destroy() })
</script>
