<template>
  <div class="space-y-5">

    <!-- ── Duplicate Warning Modal ── -->
    <Teleport to="body">
      <div v-if="duplicateMatches" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden">
          <div class="bg-amber-500 px-5 py-4 flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-white text-lg"></i>
            <h2 class="text-[16px] font-bold text-white">सम्भावित डुप्लिकेट पाइयो!</h2>
          </div>
          <div class="px-5 py-4 space-y-3 max-h-72 overflow-y-auto">
            <p class="text-[13px] text-[#626976]">
              तलका अभिलेखहरूसँग मिल्दो घरधुरी पहिलेनै दर्ता भएको देखिन्छ। जाँच गर्नुहोस्:
            </p>
            <div v-for="m in duplicateMatches" :key="m.id"
              class="border border-amber-200 bg-amber-50 rounded-lg px-4 py-3 space-y-1">
              <div class="flex items-center justify-between gap-2">
                <span class="text-[13px] font-semibold text-[#1d273b]">{{ m.unique_code }}</span>
                <span class="text-[11px] px-2 py-0.5 rounded-full font-medium"
                  :class="{
                    'bg-green-100 text-green-700': m.status === 'published',
                    'bg-yellow-100 text-yellow-700': m.status === 'submitted' || m.status === 'ward_approved',
                    'bg-gray-100 text-gray-600': m.status === 'draft',
                  }">
                  {{ m.status }}
                </span>
              </div>
              <div class="text-[12px] text-[#626976]">
                उत्तरदाता: {{ m.respondent_name || '—' }}
                <span v-if="m.ward_number"> · वडा {{ m.ward_number }}</span>
              </div>
              <div class="flex flex-wrap gap-1 mt-1">
                <span v-for="r in m.reasons" :key="r"
                  class="text-[11px] bg-amber-100 text-amber-800 border border-amber-200 rounded px-1.5 py-0.5">
                  {{ r }}
                </span>
              </div>
              <a :href="m.edit_url" target="_blank"
                class="inline-flex items-center gap-1 text-[12px] text-blue-600 hover:underline mt-1">
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                हेर्नुहोस्
              </a>
            </div>
          </div>
          <div class="px-5 py-4 border-t border-[#e6e7e9] flex justify-end gap-3 bg-gray-50">
            <button @click="dismissDuplicate"
              class="px-4 py-2 rounded border border-[#e6e7e9] text-[13px] text-[#626976] hover:bg-gray-100 transition">
              फर्कनुहोस् (जाँच गर्नुहोस्)
            </button>
            <button @click="confirmDuplicate"
              class="px-4 py-2 rounded bg-amber-500 hover:bg-amber-600 text-white text-[13px] font-semibold transition">
              <i class="fa-solid fa-circle-check mr-1"></i>
              डुप्लिकेट होइन — सुरक्षित गर्नुहोस्
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Page heading — 2 columns: left=title, right=progress -->
    <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm px-5 py-4 flex items-center gap-6">

      <!-- Left: icon + title -->
      <div class="flex items-center gap-3 flex-1 min-w-0">
        <div class="w-11 h-11 rounded-md bg-[#206bc4] flex items-center justify-center text-white shrink-0 shadow-md shadow-[#206bc4]/25">
          <i class="fa-solid fa-house text-sm"></i>
        </div>
        <div class="min-w-0">
          <h2 class="text-[15px] font-semibold text-[#1d273b] truncate">{{ pageTitle }}</h2>
          <p class="text-[11px] text-[#9aa0ac]">
            {{ isEdit ? 'विद्यमान घरधुरी अद्यावधिक गर्नुहोस्' : 'नयाँ घरधुरी विवरण भर्नुहोस्' }}
          </p>
        </div>
      </div>

      <!-- Divider -->
      <div class="w-px h-10 bg-[#e6e7e9] shrink-0"></div>

      <!-- Right: data fill progress -->
      <div class="w-52 shrink-0">
        <div class="flex items-center justify-between mb-1.5">
          <div class="flex items-center gap-1.5">
            <i class="fa-solid fa-chart-simple text-[10px] text-gray-400"></i>
            <span class="text-[11px] font-semibold text-gray-500">फारम भरिएको</span>
          </div>
          <span :class="['text-[13px] font-bold', fillProgress >= 85 ? 'text-green-600' : fillProgress >= 60 ? 'text-[#206bc4]' : fillProgress >= 30 ? 'text-amber-500' : 'text-red-500']">
            {{ fillProgress }}%
          </span>
        </div>
        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
          <div
            :class="['h-full rounded-full transition-all duration-500 bg-gradient-to-r', fillColor]"
            :style="{ width: fillProgress + '%' }"
          ></div>
        </div>
        <p class="text-[10px] text-gray-400 mt-1">
          {{ fillProgress < 30 ? 'सुरु गर्नुहोस्' : fillProgress < 60 ? 'राम्रो प्रगति' : fillProgress < 85 ? 'लगभग पूर्ण' : 'लगभग पूर्ण ✓' }}
           — Data Fill Progress
        </p>
      </div>

    </div>

    <!-- Step indicator -->
    <div class="bg-white rounded-md border border-[#e6e7e9] p-4">
      <!-- Edit-mode free-jump hint -->
      <div v-if="isEdit" class="flex items-center gap-2 mb-3 px-1">
        <i class="fa-solid fa-bolt text-[10px] text-amber-500"></i>
        <span class="text-[11px] text-gray-400">सम्पादन मोड — जुनसुकै चरणमा सिधै जानुहोस्</span>
      </div>
      <div class="flex items-center gap-1 overflow-x-auto">
        <template v-for="(stepLabel, idx) in stepLabels" :key="idx">
          <div
            @click="goToStep(idx + 1)"
            :class="[
              'flex items-center gap-1.5 shrink-0 group transition-opacity',
              isEdit || idx + 1 <= step ? 'cursor-pointer' : 'cursor-default opacity-50',
            ]"
          >
            <div class="relative">
              <div :class="[
                'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all',
                idx + 1 === step
                  ? 'bg-[#206bc4] text-white shadow-md shadow-[#206bc4]/20'
                  : idx + 1 < step
                    ? 'bg-[#206bc4]/10 text-[#206bc4]'
                    : isEdit
                      ? 'bg-gray-100 text-gray-500 group-hover:bg-[#206bc4]/10 group-hover:text-[#206bc4]'
                      : 'bg-gray-100 text-gray-400',
              ]">
                <i v-if="idx + 1 < step && !stepsWithErrors.has(idx + 1)" class="fa-solid fa-check text-[10px]"></i>
                <i v-else-if="idx + 1 < step && stepsWithErrors.has(idx + 1)" class="fa-solid fa-exclamation text-[10px]"></i>
                <span v-else>{{ idx + 1 }}</span>
              </div>
              <span v-if="stepsWithErrors.has(idx + 1)"
                class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white">
              </span>
            </div>
            <span :class="[
              'text-xs font-medium hidden sm:block transition-colors',
              idx + 1 === step
                ? 'text-[#206bc4]'
                : idx + 1 < step
                  ? 'text-[#206bc4]'
                  : isEdit
                    ? 'text-gray-400 group-hover:text-[#206bc4]'
                    : 'text-gray-400',
            ]">{{ stepLabel }}</span>
          </div>
          <div v-if="idx < stepLabels.length - 1" class="flex-1 min-w-[8px] h-px bg-gray-200 shrink-0"></div>
        </template>
      </div>
    </div>

    <!-- Draft restore banner (shows on load when local draft is detected) -->
    <div v-if="showDraftBanner" class="bg-amber-50 border border-amber-300 rounded-md px-4 py-3 flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2.5">
        <i class="fa-solid fa-clock-rotate-left text-amber-500 text-base"></i>
        <div>
          <p class="text-sm font-semibold text-amber-800">अघिल्लो ड्राफ्ट फेला पर्यो</p>
          <p v-if="draftSavedAt" class="text-xs text-amber-600 mt-0.5">अन्तिम स्वतः सुरक्षित: {{ draftSavedAt }}</p>
        </div>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <button type="button" @click="loadDraft"
          class="flex items-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-md px-3 py-1.5 text-xs font-medium transition-colors">
          <i class="fa-solid fa-rotate-right text-[10px]"></i>
          ड्राफ्ट लोड गर्नुहोस्
        </button>
        <button type="button" @click="dismissDraftBanner"
          class="flex items-center gap-1.5 border border-amber-300 text-amber-700 rounded-md px-3 py-1.5 text-xs font-medium hover:bg-amber-100 transition-colors">
          <i class="fa-solid fa-xmark text-[10px]"></i>
          पछि गर्छु
        </button>
      </div>
    </div>

    <!-- Offline queue toast -->
    <div
      v-if="offlineToast"
      class="flex items-center gap-2.5 bg-blue-600 text-white text-sm font-medium px-4 py-3 rounded-md shadow-md"
    >
      <i class="fa-solid fa-cloud-arrow-up text-white/80"></i>
      <span>{{ offlineToast }}</span>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 1: घर विवरण
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 1" class="rounded-md border border-[#e6e7e9] overflow-hidden">
      <div class="bg-[#206bc4] px-5 py-3 flex items-center gap-2">
        <i class="fa-solid fa-house text-white/80 text-sm"></i>
        <h3 class="text-[14px] font-semibold text-white tracking-wide">घर विवरण</h3>
      </div>

      <!-- Section A: सर्वेक्षण जानकारी -->
      <div class="bg-blue-50/60 border-b border-blue-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-blue-100 bg-blue-100/60">
          <i class="fa-solid fa-clipboard-list text-blue-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-blue-800 uppercase tracking-wider">सर्वेक्षण जानकारी <span class="normal-case font-normal text-blue-500">— Survey Information</span></h4>
        </div>
        <div class="p-4 grid grid-cols-2 gap-x-5 gap-y-3">
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">वडा नं. <span class="text-red-500">*</span></label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Ward No.</span>
            <select v-model="form.ward_id" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- वडा छान्नुहोस् --</option>
              <option v-for="w in wards" :key="w.id" :value="w.id">वडा {{ w.ward_number }}{{ w.name ? ' — ' + w.name : '' }}</option>
            </select>
            <p v-if="form.errors.ward_id" class="text-[11px] text-red-500">{{ form.errors.ward_id }}</p>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">टोल / बस्ती</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Settlement / Tole</span>
            <select v-model="form.settlement_id" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- टोल छान्नुहोस् --</option>
              <option v-for="s in filteredSettlements" :key="s.id" :value="s.id">{{ s.name_np || s.name_en || s.name }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">उत्तरदाताको नाम</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Respondent Name</span>
            <input v-model="form.respondent_name" type="text" placeholder="पूरा नाम" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">सम्पर्क नं.</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Contact No.</span>
            <input v-model="form.respondent_contact" type="text" placeholder="फोन नम्बर" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1 col-span-2">
            <label class="text-[12px] font-semibold text-[#626976]">सर्वेक्षण मिति</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Survey Date</span>
            <NepaliDatePicker v-model="form.survey_date" placeholder="सर्वेक्षण मिति (बि.सं.)" />
          </div>
        </div>
      </div>

      <!-- Section B: GPS स्थान -->
      <div class="bg-slate-50/70 border-b border-slate-200">
        <div class="flex items-center justify-between px-5 py-2.5 border-b border-slate-200 bg-slate-100/60">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-location-dot text-slate-600 text-xs"></i>
            <h4 class="text-[12px] font-bold text-slate-800 uppercase tracking-wider">GPS स्थान</h4>
          </div>
          <button type="button" @click="getGPS" :disabled="gpsLoading"
            class="flex items-center gap-1.5 bg-[#206bc4] hover:bg-[#1a5ba8] text-white rounded px-3 py-1 text-xs font-semibold transition-colors disabled:opacity-60">
            <i :class="['fa-solid text-[10px]', gpsLoading ? 'fa-spinner fa-spin' : 'fa-crosshairs']"></i>
            GPS प्राप्त गर्नुहोस्
          </button>
        </div>
        <div class="p-4 grid grid-cols-4 gap-3">
          <div class="flex flex-col gap-1">
            <label class="text-[11px] font-medium text-[#9aa0ac]">Latitude</label>
            <input v-model="form.gps_lat" type="number" step="any" placeholder="27.xxxx" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[11px] font-medium text-[#9aa0ac]">Longitude</label>
            <input v-model="form.gps_lng" type="number" step="any" placeholder="84.xxxx" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[11px] font-medium text-[#9aa0ac]">Accuracy (m)</label>
            <input v-model="form.gps_accuracy" type="number" step="any" placeholder="±m" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[11px] font-medium text-[#9aa0ac]">Altitude (m)</label>
            <input v-model="form.gps_altitude" type="number" step="any" placeholder="masl" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
          </div>
        </div>
        <!-- GPS boundary alert -->
        <div v-if="gpsBoundaryResult && gpsBoundaryResult.status !== 'inside'"
          class="mx-4 mb-3 flex items-start gap-2 rounded px-3 py-2 text-[12px]"
          :class="{
            'bg-amber-50 border border-amber-200 text-amber-800':  gpsBoundaryResult.status === 'near',
            'bg-orange-50 border border-orange-200 text-orange-800': gpsBoundaryResult.status === 'warn',
            'bg-red-50 border border-red-200 text-red-800':        gpsBoundaryResult.status === 'far',
          }">
          <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0 text-[11px]"></i>
          <span>
            <template v-if="gpsBoundaryResult.status === 'near'">
              GPS वडा सीमाबाट लगभग <b>{{ gpsBoundaryResult.distanceM }}m</b> बाहिर छ — GPS accuracy को कारण हुन सक्छ। सुरक्षित गर्न मिल्छ।
            </template>
            <template v-else-if="gpsBoundaryResult.status === 'warn'">
              GPS वडा सीमाबाट <b>{{ gpsBoundaryResult.distanceM }}m</b> बाहिर छ — कृपया स्थान जाँच गर्नुहोस्। सुरक्षित गर्न मिल्छ।
            </template>
            <template v-else>
              GPS वडा सीमाबाट <b>{{ gpsBoundaryResult.distanceM }}m</b> टाढा छ — वडा गलत भएको हुन सक्छ। कृपया जाँच गर्नुहोस्।
            </template>
          </span>
        </div>
        <p v-if="gpsError" class="text-[11px] text-red-500 px-5 pb-3">{{ gpsError }}</p>
      </div>

      <!-- Section C: फोटो नम्बर -->
      <div class="bg-violet-50/50 border-b border-violet-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-violet-100 bg-violet-100/60">
          <i class="fa-solid fa-camera text-violet-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-violet-800 uppercase tracking-wider">फोटो नम्बर <span class="normal-case font-normal text-violet-500">— Photo Number</span></h4>
        </div>
        <div class="p-4">
          <div class="flex flex-col gap-1 max-w-xs">
            <label class="text-[11px] font-medium text-[#9aa0ac]">फोटो नम्बर (Photo Number)</label>
            <input v-model="form.photo_number" type="text" placeholder="जस्तै: 00247"
              class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] font-mono bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
            <p class="text-[11px] text-gray-400">फोटो फाइलको नाम (बिना .png)</p>
          </div>
        </div>
      </div>

      <!-- Section E: घरको बनावट -->
      <div class="bg-emerald-50/50 border-b border-emerald-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-emerald-100 bg-emerald-100/60">
          <i class="fa-solid fa-building text-emerald-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-emerald-800 uppercase tracking-wider">घरको बनावट <span class="normal-case font-normal text-emerald-500">— House Structure</span></h4>
        </div>
        <div class="p-4 grid grid-cols-2 gap-x-5 gap-y-3">
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">घरको प्रकार</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">House Type</span>
            <select v-model="form.housing.house_type" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.house_type ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">छानाको प्रकार</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Roof Type</span>
            <select v-model="form.housing.roof_type" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.roof_type ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">पर्खालको प्रकार</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Wall Type</span>
            <select v-model="form.housing.wall_type" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.wall_type ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">जगको प्रकार</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Foundation Type</span>
            <select v-model="form.housing.foundation_type" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.foundation_type ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">स्वामित्वको प्रकार</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Ownership Type</span>
            <select v-model="form.housing.ownership_type" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.ownership_type ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">घरको अवस्था</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">House Condition</span>
            <select v-model="form.housing.house_condition" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.house_condition ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">जम्मा कोठा</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Total Rooms</span>
            <input v-model="form.housing.total_rooms" type="number" min="0" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">तला संख्या</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">No. of Floors</span>
            <input v-model="form.housing.total_floors" type="number" min="1" placeholder="1" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
          </div>
        </div>
      </div>

      <!-- Section D: जग्गा तथा पहुँच -->
      <div class="bg-amber-50/40">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-amber-100 bg-amber-100/60">
          <i class="fa-solid fa-map text-amber-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-amber-800 uppercase tracking-wider">जग्गा तथा पहुँच <span class="normal-case font-normal text-amber-500">— Land & Accessibility</span></h4>
        </div>
        <div class="p-4 grid grid-cols-2 gap-x-5 gap-y-3">
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">जग्गाको किसिम</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Land Type</span>
            <select v-model="form.housing.house_land_type" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.house_land_type ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">घरको उद्देश्य</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">House Purpose</span>
            <select v-model="form.housing.house_purpose" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.house_purpose ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1 col-span-2">
            <label class="text-[12px] font-semibold text-[#626976]">जोखिमको हिसाबले घर बनेको ठाउँको अवस्था</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Risk Location of House</span>
            <select v-model="form.housing.location_house_built" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.location_house_built ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">मुख्य सडक पुग्न (मिनेट)</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Time to Main Road (min)</span>
            <input v-model="form.housing.distance_to_mainroad" type="number" min="0" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">विद्यालय पुग्न (मिनेट)</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Time to School (min)</span>
            <input v-model="form.housing.distance_to_school" type="number" min="0" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 2: परिवार तथा सामाजिक
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 2" class="rounded-md border border-[#e6e7e9] overflow-hidden">

      <!-- Step header -->
      <div class="bg-[#206bc4] px-5 py-3 flex items-center gap-2">
        <i class="fa-solid fa-people-group text-white/80 text-sm"></i>
        <h3 class="text-[14px] font-semibold text-white tracking-wide">परिवार तथा सामाजिक विवरण</h3>
      </div>

      <!-- ── Section A: परिवारको मूल जानकारी ─────────────────────── -->
      <div class="bg-blue-50/60 border-b border-blue-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-blue-100 bg-blue-100/60">
          <i class="fa-solid fa-house-user text-blue-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-blue-800 uppercase tracking-wider">परिवारको मूल जानकारी <span class="normal-case font-normal text-blue-500">— Basic Family Info</span></h4>
        </div>
        <div class="p-4 grid grid-cols-2 gap-x-5 gap-y-3">
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">घरमूलीको नाम <span class="text-red-500">*</span></label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Head of Household</span>
            <input v-model="form.social.family_head" type="text" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">घरमूलीको सम्पर्क नम्बर</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Head's Contact Number</span>
            <input v-model="form.social.contact_no_fam_head" type="text" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
          </div>
          <!-- ── Gender counts — highlighted when redirected from Step 14 ── -->
          <div :id="highlightGenderCounts ? 'gender-count-anchor' : undefined"
            class="col-span-2 rounded-lg transition-all duration-500"
            :class="highlightGenderCounts ? 'ring-2 ring-amber-400 bg-amber-50 p-3 -mx-1' : ''">

            <!-- Banner shown only when redirected -->
            <div v-if="highlightGenderCounts"
              class="mb-3 flex items-start gap-2 bg-amber-100 border border-amber-300 rounded-md px-3 py-2 text-[12px] text-amber-800">
              <i class="fa-solid fa-arrow-pointer text-amber-500 mt-0.5 flex-shrink-0"></i>
              <span>
                <strong>यहाँ संख्या बढाउनुहोस्।</strong>
                नयाँ सदस्यको लिंग अनुसार तलको संख्यामा <strong>+१</strong> गर्नुहोस्, त्यसपछि चरण १४ मा फर्केर नयाँ सदस्यको विवरण भर्नुहोस्।
              </span>
            </div>

            <div class="grid grid-cols-3 gap-x-5 gap-y-3">
              <div class="flex flex-col gap-1">
                <label class="text-[12px] font-semibold" :class="highlightGenderCounts ? 'text-amber-700' : 'text-[#626976]'">पुरुष संख्या</label>
                <span class="text-[11px] text-gray-400 -mt-0.5">No. of Males</span>
                <input v-model="form.social.male_num" type="number" min="0" placeholder="०"
                  :class="highlightGenderCounts
                    ? 'w-full rounded border-2 border-amber-400 px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-amber-300 focus:border-amber-500'
                    : 'w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]'" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[12px] font-semibold" :class="highlightGenderCounts ? 'text-amber-700' : 'text-[#626976]'">महिला संख्या</label>
                <span class="text-[11px] text-gray-400 -mt-0.5">No. of Females</span>
                <input v-model="form.social.female_num" type="number" min="0" placeholder="०"
                  :class="highlightGenderCounts
                    ? 'w-full rounded border-2 border-amber-400 px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-amber-300 focus:border-amber-500'
                    : 'w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]'" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[12px] font-semibold" :class="highlightGenderCounts ? 'text-amber-700' : 'text-[#626976]'">अन्य लिंग संख्या</label>
                <span class="text-[11px] text-gray-400 -mt-0.5">Other Gender Count</span>
                <input v-model="form.social.other_num" type="number" min="0" placeholder="०"
                  :class="highlightGenderCounts
                    ? 'w-full rounded border-2 border-amber-400 px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-amber-300 focus:border-amber-500'
                    : 'w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]'" />
              </div>
            </div>

            <!-- Return to Step 14 button shown only when redirected and count was increased -->
            <div v-if="highlightGenderCounts && totalMembersFromStep2 > form.members.length"
              class="mt-3 flex items-center gap-3">
              <button type="button"
                @click="highlightGenderCounts = false; step = 14; scrollTop()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-[12px] font-semibold rounded-lg hover:bg-indigo-700 transition">
                <i class="fa-solid fa-arrow-right text-xs"></i>
                चरण १४ मा फर्कनुहोस् — सदस्य थप्नुहोस्
              </button>
              <span class="text-[11px] text-green-700 font-medium">
                <i class="fa-solid fa-check text-xs mr-1"></i>
                {{ totalMembersFromStep2 - form.members.length }} नयाँ सदस्य थप्न सकिन्छ
              </span>
            </div>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">परिवारको प्रकार</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Family Type</span>
            <select v-model="form.social.family_type" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.family_type ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- ── Section B: जातीयता, भाषा तथा पेशा ───────────────────── -->
      <div class="bg-emerald-50/50 border-b border-emerald-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-emerald-100 bg-emerald-100/60">
          <i class="fa-solid fa-id-card text-emerald-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-emerald-800 uppercase tracking-wider">जातीयता, भाषा तथा पेशा <span class="normal-case font-normal text-emerald-500">— Ethnicity, Language & Occupation</span></h4>
        </div>
        <div class="p-4 grid grid-cols-2 gap-x-5 gap-y-3">
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">जातीयता / समुदाय</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Ethnicity / Community</span>
            <select v-model="form.social.ethnicity" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.ethnicity ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">धर्म</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Religion</span>
            <select v-model="form.social.religion" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.religion ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">मातृ भाषा</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Mother Tongue</span>
            <select v-model="form.social.mother_tongue" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.language ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">बोलीचालीको मुख्य भाषा</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Main Communication Language</span>
            <select v-model="form.social.com_language" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.language ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1 col-span-2">
            <label class="text-[12px] font-semibold text-[#626976]">परिवारको मुख्य पेशा</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Major Family Occupation</span>
            <select v-model="form.social.major_family_occupation" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.major_occupation ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- ── Section C: बसोबास तथा बसाइसराइ ──────────────────────── -->
      <div class="bg-violet-50/50 border-b border-violet-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-violet-100 bg-violet-100/60">
          <i class="fa-solid fa-map-location-dot text-violet-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-violet-800 uppercase tracking-wider">बसोबास तथा बसाइसराइ <span class="normal-case font-normal text-violet-500">— Residence & Migration</span></h4>
        </div>
        <div class="p-4 space-y-3">
          <div class="grid grid-cols-2 gap-x-5 gap-y-3">
            <div class="flex flex-col gap-1 col-span-2">
              <label class="text-[12px] font-semibold text-[#626976]">स्थायी बासिन्दा हो या अस्थायी?</label>
              <span class="text-[11px] text-gray-400 -mt-0.5">Permanent or Temporary Resident?</span>
              <select v-model="form.social.settlement_type" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                <option value="">-- छान्नुहोस् --</option>
                <option v-for="opt in (surveyOptions.settlement_type ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
          </div>

          <!-- Migration toggle -->
          <div class="flex items-center justify-between px-3 py-2.5 bg-white rounded border border-[#e6e7e9]">
            <div>
              <p class="text-[13px] font-medium text-gray-700">तपाइँहरु पछिल्लो पाँच वर्षमा बसाइ सरि आएको हो?</p>
              <p class="text-[11px] text-gray-400">Migrated to this place in the last 5 years?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
              <button type="button" @click="form.social.migration_last_5years = 'yes'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.social.migration_last_5years === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">हो</button>
              <button type="button" @click="form.social.migration_last_5years = 'no'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.social.migration_last_5years === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">होइन</button>
            </div>
          </div>

          <!-- Conditional migration detail -->
          <div v-if="form.social.migration_last_5years === 'yes'" class="border border-violet-200 rounded-md overflow-hidden">
            <div class="bg-violet-100/70 px-4 py-2 text-[11px] font-bold text-violet-700 uppercase tracking-wider">बसाइसराइको विवरण</div>
            <div class="p-4 space-y-3">
              <!-- Registered toggle -->
              <div class="flex items-center justify-between px-3 py-2.5 bg-white rounded border border-[#e6e7e9]">
                <div>
                  <p class="text-[13px] font-medium text-gray-700">पालिकामा बसाइसराइ दर्ता गर्नुभएको छ?</p>
                  <p class="text-[11px] text-gray-400">Migration Registered in Municipality?</p>
                </div>
                <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
                  <button type="button" @click="form.social.migration_registered = 'yes'"
                    :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.social.migration_registered === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
                  <button type="button" @click="form.social.migration_registered = 'no'"
                    :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.social.migration_registered === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
                </div>
              </div>
              <!-- Purpose -->
              <div class="flex flex-col gap-1">
                <label class="text-[12px] font-semibold text-[#626976]">बसाइ सर्नुको प्रयोजन</label>
                <span class="text-[11px] text-gray-400 -mt-0.5">Purpose of Migration</span>
                <select v-model="form.social.migration_purpose" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                  <option value="">-- छान्नुहोस् --</option>
                  <option v-for="opt in (surveyOptions.migration_purpose ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
              <!-- Origin address -->
              <div>
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2">कहाँबाट बसाइ सरि यहाँ आउनुभएको हो?</p>
                <div class="grid grid-cols-3 gap-3">
                  <div class="flex flex-col gap-1">
                    <label class="text-[12px] font-medium text-[#9aa0ac]">प्रदेश</label>
                    <select v-model="form.social.migration_from_province" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                      <option value="">-- प्रदेश --</option>
                      <option v-for="p in provinceList" :key="p" :value="p">{{ p }}</option>
                    </select>
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="text-[12px] font-medium text-[#9aa0ac]">जिल्ला</label>
                    <select v-model="form.social.migration_from_district" :disabled="!form.social.migration_from_province" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4] disabled:opacity-50 disabled:cursor-not-allowed">
                      <option value="">-- जिल्ला --</option>
                      <option v-for="d in migFromDistrictList" :key="d" :value="d">{{ d }}</option>
                    </select>
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="text-[12px] font-medium text-[#9aa0ac]">स्थानीय तह</label>
                    <select v-model="form.social.migration_from_local_gov" :disabled="!form.social.migration_from_district" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4] disabled:opacity-50 disabled:cursor-not-allowed">
                      <option value="">-- पालिका --</option>
                      <option v-for="lg in migFromLocalGovList" :key="lg" :value="lg">{{ lg }}</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Section D: थप जानकारी (Toggles) ──────────────────────── -->
      <div class="bg-amber-50/40">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-amber-100 bg-amber-100/60">
          <i class="fa-solid fa-circle-question text-amber-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-amber-800 uppercase tracking-wider">थप जानकारी <span class="normal-case font-normal text-amber-500">— Additional Information</span></h4>
        </div>
        <div class="p-4 space-y-2">

          <!-- Row 1: Senior citizens (odd = white bg) -->
          <div class="rounded-md border border-[#e6e7e9] overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-white">
              <div>
                <p class="text-[13px] font-medium text-gray-700">जेष्ठ नागरिकहरु बृद्धाश्रममा बस्नुभएको छ?</p>
                <p class="text-[11px] text-gray-400">Senior Citizens Living in Old Age Home?</p>
              </div>
              <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
                <button type="button" @click="form.social.senior_in_old_age_home = 'yes'"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.social.senior_in_old_age_home === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
                <button type="button" @click="form.social.senior_in_old_age_home = 'no'"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.social.senior_in_old_age_home === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
              </div>
            </div>
            <div v-if="form.social.senior_in_old_age_home === 'yes'" class="border-t border-[#e6e7e9] bg-blue-50/40 p-4 space-y-3">
              <div v-for="(senior, idx) in form.social.seniors_list" :key="idx"
                class="grid grid-cols-3 gap-3 p-3 bg-white border border-blue-100 rounded-md relative">
                <button type="button" @click="removeSenior(idx)"
                  class="absolute top-2 right-2 w-5 h-5 flex items-center justify-center rounded-full bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 transition-colors">
                  <i class="fa-solid fa-xmark text-[10px]"></i>
                </button>
                <div class="flex flex-col gap-1">
                  <label class="text-[11px] font-medium text-[#9aa0ac]">नाम</label>
                  <input v-model="senior.name" type="text" placeholder="पूरा नाम"
                    class="w-full rounded border border-[#e6e7e9] px-2.5 py-1.5 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-[11px] font-medium text-[#9aa0ac]">उमेर</label>
                  <input v-model="senior.age" type="number" min="60" placeholder="उमेर"
                    class="w-full rounded border border-[#e6e7e9] px-2.5 py-1.5 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-[11px] font-medium text-[#9aa0ac]">लिंग</label>
                  <select v-model="senior.gender" class="w-full rounded border border-[#e6e7e9] px-2.5 py-1.5 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                    <option value="">-- छान्नुहोस् --</option>
                    <option value="male">पुरुष</option>
                    <option value="female">महिला</option>
                    <option value="other">अन्य</option>
                  </select>
                </div>
              </div>
              <button type="button" @click="addSenior"
                class="flex items-center gap-1.5 text-[#206bc4] text-xs font-semibold hover:text-indigo-700 transition-colors">
                <i class="fa-solid fa-plus text-[10px]"></i> व्यक्ति थप्नुहोस्
              </button>
            </div>
          </div>

          <!-- Row 2: Land (even = gray-50 bg) -->
          <div class="rounded-md border border-[#e6e7e9] overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50">
              <div>
                <p class="text-[13px] font-medium text-gray-700">पालिकामा यस परिवारको जग्गा छ कि छैन?</p>
                <p class="text-[11px] text-gray-400">Does the family have land in the municipality?</p>
              </div>
              <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
                <button type="button" @click="form.social.have_land = 'yes'"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.social.have_land === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
                <button type="button" @click="form.social.have_land = 'no'"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.social.have_land === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
              </div>
            </div>
            <div v-if="form.social.have_land === 'yes'" class="border-t border-[#e6e7e9] bg-green-50/40 p-4 space-y-3">
              <div class="flex flex-col gap-1">
                <label class="text-[12px] font-semibold text-[#626976]">जग्गाको नाप एकाइ</label>
                <span class="text-[11px] text-gray-400 -mt-0.5">Land Measurement Unit</span>
                <select v-model="form.social.land_unit" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                  <option value="">-- छान्नुहोस् --</option>
                  <option v-for="opt in (surveyOptions.land_measurement_unit ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
              <div class="grid grid-cols-3 gap-3">
                <div class="flex flex-col gap-1">
                  <label class="text-[12px] font-semibold text-[#626976]">खेत (क्षेत्रफल)</label>
                  <span class="text-[11px] text-gray-400 -mt-0.5">Irrigated Land (Area)</span>
                  <input type="number" min="0" step="0.01" v-model="form.social.land_khet" placeholder="०"
                    class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-[12px] font-semibold text-[#626976]">बारी (क्षेत्रफल)</label>
                  <span class="text-[11px] text-gray-400 -mt-0.5">Upland Farm (Area)</span>
                  <input type="number" min="0" step="0.01" v-model="form.social.land_bari" placeholder="०"
                    class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-[12px] font-semibold text-[#626976]">पाखो (क्षेत्रफल)</label>
                  <span class="text-[11px] text-gray-400 -mt-0.5">Sloped Land (Area)</span>
                  <input type="number" min="0" step="0.01" v-model="form.social.land_pakho" placeholder="०"
                    class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
                </div>
              </div>
            </div>
          </div>

          <!-- Row 3: Loan (odd = white bg) -->
          <div class="rounded-md border border-[#e6e7e9] overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-white">
              <div>
                <p class="text-[13px] font-medium text-gray-700">कर्जा / ऋण छ?</p>
                <p class="text-[11px] text-gray-400">Any Loan / Debt?</p>
              </div>
              <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
                <button type="button" @click="form.social.bankloan = 'yes'"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.social.bankloan === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
                <button type="button" @click="form.social.bankloan = 'no'"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.social.bankloan === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
              </div>
            </div>
            <div v-if="form.social.bankloan === 'yes'" class="border-t border-[#e6e7e9] bg-orange-50/40 p-4 space-y-4">
              <div>
                <label class="block text-[12px] font-semibold text-[#626976]">ऋणको स्रोत <span class="text-gray-400 font-normal">(बहु छनोट)</span></label>
                <p class="text-[11px] text-gray-400 mb-2">Loan Source (Multiple Select)</p>
                <div class="grid grid-cols-3 gap-x-4 gap-y-1.5">
                  <label v-for="opt in (surveyOptions.loan_source ?? [])" :key="opt.value"
                    class="flex items-center gap-2 cursor-pointer text-[13px] text-gray-700 hover:text-[#206bc4]">
                    <input type="checkbox" :value="opt.value" v-model="form.social.loansource" class="rounded border-gray-300 text-[#206bc4]" />
                    {{ opt.label }}
                  </label>
                  <p v-if="!(surveyOptions.loan_source ?? []).length" class="text-xs text-gray-400">विकल्प उपलब्ध छैन</p>
                </div>
              </div>
              <div class="border-t border-orange-100 pt-4">
                <label class="block text-[12px] font-semibold text-[#626976]">ऋणको उद्देश्य <span class="text-gray-400 font-normal">(बहु छनोट)</span></label>
                <p class="text-[11px] text-gray-400 mb-2">Loan Purpose (Multiple Select)</p>
                <div class="grid grid-cols-3 gap-x-4 gap-y-1.5">
                  <label v-for="opt in (surveyOptions.loan_purpose ?? [])" :key="opt.value"
                    class="flex items-center gap-2 cursor-pointer text-[13px] text-gray-700 hover:text-[#206bc4]">
                    <input type="checkbox" :value="opt.value" v-model="form.social.loanpurpose" class="rounded border-gray-300 text-[#206bc4]" />
                    {{ opt.label }}
                  </label>
                  <p v-if="!(surveyOptions.loan_purpose ?? []).length" class="text-xs text-gray-400">विकल्प उपलब्ध छैन</p>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 3: सरसफाई तथा सुविधा
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 3" class="rounded-md border border-[#e6e7e9] overflow-hidden">
      <div class="bg-teal-600 px-5 py-3 flex items-center gap-2">
        <i class="fa-solid fa-faucet text-white/80 text-sm"></i>
        <h3 class="text-[14px] font-semibold text-white tracking-wide">सरसफाई तथा सुविधा</h3>
      </div>

      <!-- Section A: ऊर्जा तथा पानी -->
      <div class="bg-teal-50/60 border-b border-teal-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-teal-100 bg-teal-100/60">
          <i class="fa-solid fa-droplet text-teal-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-teal-800 uppercase tracking-wider">पानी, ऊर्जा तथा फोहोर <span class="normal-case font-normal text-teal-500">— Water, Energy & Waste</span></h4>
        </div>
        <div class="p-4 space-y-3">

          <!-- Row 1: water source + time (grid) -->
          <div class="grid grid-cols-2 gap-x-5 gap-y-3">
            <div class="flex flex-col gap-1">
              <label class="text-[12px] font-semibold text-[#626976]">पानीको स्रोत</label>
              <span class="text-[11px] text-gray-400 -mt-0.5">Water Source</span>
              <select v-model="form.utilities.water_source" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                <option value="">-- छान्नुहोस् --</option>
                <option v-for="opt in (surveyOptions.water_source ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[12px] font-semibold text-[#626976]">पानी ल्याउन लाग्ने समय (मिनेट)</label>
              <span class="text-[11px] text-gray-400 -mt-0.5">Time to Fetch Water (min)</span>
              <input v-model="form.utilities.water_distance_minutes" type="number" min="0" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
            </div>
          </div>

          <!-- Firewood toggle -->
          <div class="rounded border border-[#e6e7e9] overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-white">
              <div>
                <p class="text-[13px] font-medium text-gray-700">खाना पकाउने इन्धनमा दाउराको प्रयोग गर्नु हुन्छ?</p>
                <p class="text-[11px] text-gray-400">Use Firewood as Cooking Fuel?</p>
              </div>
              <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
                <button type="button" @click="form.utilities.uses_firewood = 'yes'"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.utilities.uses_firewood === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
                <button type="button" @click="form.utilities.uses_firewood = 'no'; form.utilities.firewood_source = []"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.utilities.uses_firewood === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
              </div>
            </div>
            <div v-if="form.utilities.uses_firewood === 'yes'" class="border-t border-teal-100 bg-teal-50/40 p-4">
              <label class="text-[12px] font-semibold text-[#626976]">दाउराको स्रोत कहाँबाट ल्याउनु हुन्छ?</label>
              <p class="text-[11px] text-gray-400 mb-3">Source of Firewood (Multiple Select)</p>
              <div class="grid grid-cols-3 gap-x-4 gap-y-1.5">
                <label v-for="opt in (surveyOptions.firewood_source ?? [])" :key="opt.value"
                  class="flex items-center gap-2 cursor-pointer text-[13px] text-gray-700 hover:text-[#206bc4]">
                  <input type="checkbox" :value="opt.value" v-model="form.utilities.firewood_source" class="rounded border-gray-300 text-[#206bc4]" />
                  {{ opt.label }}
                </label>
              </div>
            </div>
          </div>

          <!-- Cooking stove -->
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">खाना पकाउन प्रयोग हुने चुलोको किसिम? <span class="text-gray-400 font-normal">(मुख्य रुपमा)</span></label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Most Used Cooking Stove</span>
            <select v-model="form.utilities.cooking_stove" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.cooking_stove ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>

          <!-- Other cooking fuels (multi-select) -->
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">अन्य खाना पकाउने इन्धनहरु <span class="text-gray-400 font-normal">(बहु छनोट)</span></label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Other Cooking Fuels (Multiple Select)</span>
            <div class="grid grid-cols-3 gap-x-4 gap-y-1.5 mt-1">
              <label v-for="opt in (surveyOptions.cooking_fuel ?? [])" :key="opt.value"
                class="flex items-center gap-2 cursor-pointer text-[13px] text-gray-700 hover:text-[#206bc4]">
                <input type="checkbox" :value="opt.value" v-model="form.utilities.cooking_fuel_arr" class="rounded border-gray-300 text-[#206bc4]" />
                {{ opt.label }}
              </label>
            </div>
          </div>

          <!-- Electricity source -->
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">बिजुलीको स्रोत</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Electricity Source</span>
            <select v-model="form.utilities.electricity_source" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.electricity_source ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>

          <!-- Alternative electricity toggle -->
          <div class="rounded border border-[#e6e7e9] overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-white">
              <div>
                <p class="text-[13px] font-medium text-gray-700">घरमा बत्तिको अन्य/बैकल्पिक स्रोत के-के रहेको छ?</p>
                <p class="text-[11px] text-gray-400">Alternative Source of Electricity?</p>
              </div>
              <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
                <button type="button" @click="form.utilities.has_alt_electricity = 'yes'"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.utilities.has_alt_electricity === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
                <button type="button" @click="form.utilities.has_alt_electricity = 'no'; form.utilities.alt_electricity_sources = []"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.utilities.has_alt_electricity === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
              </div>
            </div>
            <div v-if="form.utilities.has_alt_electricity === 'yes'" class="border-t border-teal-100 bg-teal-50/40 p-4">
              <label class="text-[12px] font-semibold text-[#626976]">बत्तिको बैकल्पिक स्रोत छान्नुहोस्</label>
              <p class="text-[11px] text-gray-400 mb-3">Select Alternative Source of Electricity (Multiple)</p>
              <div class="grid grid-cols-2 gap-x-4 gap-y-1.5">
                <label v-for="opt in (surveyOptions.alt_electricity_source ?? [])" :key="opt.value"
                  class="flex items-center gap-2 cursor-pointer text-[13px] text-gray-700 hover:text-[#206bc4]">
                  <input type="checkbox" :value="opt.value" v-model="form.utilities.alt_electricity_sources" class="rounded border-gray-300 text-[#206bc4]" />
                  {{ opt.label }}
                </label>
              </div>
            </div>
          </div>

          <!-- Waste management -->
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">फोहोर व्यवस्थापन</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Waste Management</span>
            <select v-model="form.utilities.solid_waste_management" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.waste_management ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>

        </div>
      </div>

      <!-- Section B: शौचालय -->
      <div class="bg-slate-50/60">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-slate-200 bg-slate-100/60">
          <i class="fa-solid fa-toilet text-slate-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-slate-800 uppercase tracking-wider">शौचालय <span class="normal-case font-normal text-slate-500">— Toilet / Sanitation</span></h4>
        </div>
        <div class="p-4 space-y-3">
          <div class="flex items-center justify-between px-3 py-2.5 bg-white rounded border border-[#e6e7e9]">
            <div>
              <p class="text-[13px] font-medium text-gray-700">शौचालय छ?</p>
              <p class="text-[11px] text-gray-400">Has Toilet?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
              <button type="button" @click="form.utilities.has_toilet = true"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.utilities.has_toilet === true ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
              <button type="button" @click="form.utilities.has_toilet = false"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.utilities.has_toilet === false ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
            </div>
          </div>
          <div v-if="form.utilities.has_toilet" class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">शौचालयको प्रकार</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Toilet Type</span>
            <select v-model="form.utilities.toilet_type" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.toilet_type ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">हात धुने सुविधा</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Handwashing Facility</span>
            <select v-model="form.utilities.handwashing" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.handwashing ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Section C: स्वास्थ्य ज्ञान तथा सेवा -->
      <div class="bg-emerald-50/50 border-b border-emerald-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-emerald-100 bg-emerald-100/60">
          <i class="fa-solid fa-heart-pulse text-emerald-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-emerald-800 uppercase tracking-wider">स्वास्थ्य ज्ञान तथा सेवा पहुँच <span class="normal-case font-normal text-emerald-500">— Health Knowledge & Access</span></h4>
        </div>
        <div class="p-4 space-y-2">

          <!-- Row 1: Handwashing awareness (odd=white) -->
          <div class="flex items-center justify-between px-4 py-2.5 bg-white rounded border border-[#e6e7e9]">
            <div>
              <p class="text-[13px] font-medium text-gray-700">हात धुने तरिकाबारे ज्ञान छ?</p>
              <p class="text-[11px] text-gray-400">Aware About Handwashing?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
              <button type="button" @click="form.health.aware_handwashing = 'yes'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.health.aware_handwashing === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
              <button type="button" @click="form.health.aware_handwashing = 'no'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.health.aware_handwashing === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
            </div>
          </div>

          <!-- Row 2: Illness frequency + treatment place (even=gray) -->
          <div class="bg-gray-50 rounded border border-[#e6e7e9] p-3 grid grid-cols-2 gap-x-4 gap-y-3">
            <div class="flex flex-col gap-1">
              <label class="text-[12px] font-semibold text-[#626976]">बर्षमा कतिपटक बिरामी हुने?</label>
              <p class="text-[11px] text-gray-400 -mt-0.5">Frequent Illness in the family?</p>
              <select v-model="form.health.illness_frequency" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                <option value="">-- छान्नुहोस् --</option>
                <option value="rarely">विरलै (वर्षमा १ पटक भन्दा कम)</option>
                <option value="1_2">१–२ पटक</option>
                <option value="3_5">३–५ पटक</option>
                <option value="6_plus">६ पटक वा बढी</option>
              </select>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[12px] font-semibold text-[#626976]">बिरामी हुँदा पहिलो उपचार कहाँ?</label>
              <p class="text-[11px] text-gray-400 -mt-0.5">Where do you go for treatment?</p>
              <select v-model="form.health.treatment_place" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                <option value="">-- छान्नुहोस् --</option>
                <option v-for="opt in (surveyOptions.treatment_place ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div class="flex flex-col gap-1 col-span-2">
              <label class="text-[12px] font-semibold text-[#626976]">नजिकको स्वास्थ्य संस्थामा पुग्न लाग्ने समय (मिनेट)</label>
              <p class="text-[11px] text-gray-400 -mt-0.5">Time to reach nearest health institution (minutes)</p>
              <input v-model="form.health.time_to_health_center" type="number" min="0" placeholder="0"
                class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
            </div>
          </div>

          <!-- Row 3: Nutrition awareness (odd=white) -->
          <div class="flex items-center justify-between px-4 py-2.5 bg-white rounded border border-[#e6e7e9]">
            <div>
              <p class="text-[13px] font-medium text-gray-700">पोषण र सन्तुलन भोजनबारे ज्ञान छ?</p>
              <p class="text-[11px] text-gray-400">Aware about Nutrition and Balance Diet?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
              <button type="button" @click="form.health.aware_nutrition = 'yes'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.health.aware_nutrition === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
              <button type="button" @click="form.health.aware_nutrition = 'no'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.health.aware_nutrition === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
            </div>
          </div>

          <!-- Row 4: Pregnant nutrition (even=gray) -->
          <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 rounded border border-[#e6e7e9]">
            <div>
              <p class="text-[13px] font-medium text-gray-700">गर्भवती महिलालाई थप पौष्टिक आहारबारे जानकारी छ?</p>
              <p class="text-[11px] text-gray-400">Additional Nutrition for Pregnant Women?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
              <button type="button" @click="form.health.aware_pregnant_nutrition = 'yes'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.health.aware_pregnant_nutrition === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
              <button type="button" @click="form.health.aware_pregnant_nutrition = 'no'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.health.aware_pregnant_nutrition === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
            </div>
          </div>

          <!-- Row 5: Iron capsule (odd=white) -->
          <div class="flex items-center justify-between px-4 py-2.5 bg-white rounded border border-[#e6e7e9]">
            <div>
              <p class="text-[13px] font-medium text-gray-700">गर्भवती महिलालाई आइरन चक्कीबारे जानकारी छ?</p>
              <p class="text-[11px] text-gray-400">Iron Capsule and Health Check-up for Pregnant Women?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
              <button type="button" @click="form.health.aware_iron_capsule = 'yes'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.health.aware_iron_capsule === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
              <button type="button" @click="form.health.aware_iron_capsule = 'no'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.health.aware_iron_capsule === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
            </div>
          </div>

          <!-- Row 6: Pregnant woman at home (even=gray) with conditional details -->
          <div class="rounded border border-[#e6e7e9] overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50">
              <div>
                <p class="text-[13px] font-medium text-gray-700">घरमा हाल कोही गर्भवती महिला हुनुहुन्छ?</p>
                <p class="text-[11px] text-gray-400">Any pregnant women at home?</p>
              </div>
              <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
                <button type="button" @click="form.health.has_pregnant_woman = 'yes'"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.health.has_pregnant_woman === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
                <button type="button" @click="form.health.has_pregnant_woman = 'no'; form.health.pregnancy_months = ''; form.health.pregnant_age = ''"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.health.has_pregnant_woman === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
              </div>
            </div>
            <!-- Conditional: pregnancy details -->
            <div v-if="form.health.has_pregnant_woman === 'yes'" class="border-t border-emerald-100 bg-emerald-50/40 p-4 grid grid-cols-2 gap-x-5 gap-y-3">
              <div class="flex flex-col gap-1">
                <label class="text-[12px] font-semibold text-[#626976]">कति महिनाको गर्भवती?</label>
                <p class="text-[11px] text-gray-400 -mt-0.5">Month of Pregnancy</p>
                <input v-model="form.health.pregnancy_months" type="number" min="1" max="9" placeholder="१–९"
                  class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[12px] font-semibold text-[#626976]">गर्भवती महिलाको उमेर</label>
                <p class="text-[11px] text-gray-400 -mt-0.5">Age of Pregnant Woman</p>
                <input v-model="form.health.pregnant_age" type="number" min="10" max="60" placeholder="उमेर"
                  class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
              </div>
            </div>
          </div>

          <!-- Row 7: Health insurance (odd=white) -->
          <div class="flex items-center justify-between px-4 py-2.5 bg-white rounded border border-[#e6e7e9]">
            <div>
              <p class="text-[13px] font-medium text-gray-700">परिवारको सरकारी स्वास्थ्य विमा गर्नुभएको छ?</p>
              <p class="text-[11px] text-gray-400">Government Health Insurance for Family?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
              <button type="button" @click="form.health.has_health_insurance = 'yes'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.health.has_health_insurance === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
              <button type="button" @click="form.health.has_health_insurance = 'no'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.health.has_health_insurance === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 4: घरपरिवारको निर्णय
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 4" class="rounded-md border border-[#e6e7e9] overflow-hidden">
      <div class="bg-indigo-600 px-5 py-3 flex items-center gap-2">
        <i class="fa-solid fa-scale-balanced text-white/80 text-sm"></i>
        <h3 class="text-[14px] font-semibold text-white tracking-wide">घरपरिवारको निर्णय प्रक्रिया</h3>
      </div>

      <!-- Section A: Decision makers -->
      <div class="bg-indigo-50/50 border-b border-indigo-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-indigo-100 bg-indigo-100/60">
          <i class="fa-solid fa-people-arrows text-indigo-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-indigo-800 uppercase tracking-wider">निर्णय गर्ने व्यक्ति <span class="normal-case font-normal text-indigo-500">— Who Makes Decisions?</span></h4>
        </div>
        <div class="p-4 grid grid-cols-2 gap-x-5 gap-y-3">
          <div v-for="decision in decisionFields" :key="decision.key" class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">{{ decision.label }}</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">{{ decision.en }}</span>
            <select v-model="form.decisions[decision.key]" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.decision_owner ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Section B: Organisation & Bank -->
      <div class="bg-violet-50/40">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-violet-100 bg-violet-100/60">
          <i class="fa-solid fa-building-columns text-violet-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-violet-800 uppercase tracking-wider">संस्था तथा बैंक सहभागिता <span class="normal-case font-normal text-violet-500">— Organisation & Bank Participation</span></h4>
        </div>
        <div class="p-4 space-y-2">

          <!-- Org participation -->
          <div class="rounded border border-[#e6e7e9] overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-white">
              <div>
                <p class="text-[13px] font-medium text-gray-700">परिवारको सदस्य कुनै संघ/संस्था/समितिमा सहभागी हुनुहुन्छ?</p>
                <p class="text-[11px] text-gray-400">Members' Participation in Committee or Organization?</p>
              </div>
              <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
                <button type="button" @click="form.decisions.org_participation = 'yes'"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.decisions.org_participation === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
                <button type="button" @click="form.decisions.org_participation = 'no'; form.decisions.org_participation_types = []"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.decisions.org_participation === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
              </div>
            </div>
            <div v-if="form.decisions.org_participation === 'yes'" class="border-t border-violet-100 bg-violet-50/40 p-4">
              <label class="text-[12px] font-semibold text-[#626976]">सम्लग्नता भएको संघ/संस्था/समिति छान्नुहोस्</label>
              <p class="text-[11px] text-gray-400 mb-3">Select the Affiliated Organization (Multiple)</p>
              <div class="grid grid-cols-3 gap-x-4 gap-y-1.5">
                <label v-for="opt in (surveyOptions.org_type ?? [])" :key="opt.value"
                  class="flex items-center gap-2 cursor-pointer text-[13px] text-gray-700 hover:text-[#206bc4]">
                  <input type="checkbox" :value="opt.value" v-model="form.decisions.org_participation_types"
                    class="rounded border-gray-300 text-[#206bc4]" />
                  {{ opt.label }}
                </label>
                <p v-if="!(surveyOptions.org_type ?? []).length" class="col-span-3 text-xs text-gray-400">विकल्प उपलब्ध छैन</p>
              </div>
            </div>
          </div>

          <!-- Bank account toggle + conditional owner -->
          <div class="rounded border border-[#e6e7e9] overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50">
              <div>
                <p class="text-[13px] font-medium text-gray-700">परिवारको कुनै बैंक खाता छ?</p>
                <p class="text-[11px] text-gray-400">Does the family have a Bank Account?</p>
              </div>
              <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
                <button type="button" @click="form.decisions.has_bank_account = 'yes'"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.decisions.has_bank_account === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
                <button type="button" @click="form.decisions.has_bank_account = 'no'; form.decisions.bank_account_owner = ''"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.decisions.has_bank_account === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
              </div>
            </div>
            <div v-if="form.decisions.has_bank_account === 'yes'" class="border-t border-[#e6e7e9] bg-violet-50/40 p-4">
              <div class="flex flex-col gap-1">
                <label class="text-[12px] font-semibold text-[#626976]">बैंक खाता कसको नाममा छ?</label>
                <span class="text-[11px] text-gray-400 -mt-0.5">Who Owns the Bank Account?</span>
                <div class="flex gap-3 mt-1">
                  <button v-for="opt in (surveyOptions.decision_owner ?? [])" :key="opt.value"
                    type="button" @click="form.decisions.bank_account_owner = opt.value"
                    :class="['flex-1 py-2 text-[13px] font-semibold rounded border transition-colors', form.decisions.bank_account_owner === opt.value ? 'bg-[#206bc4] text-white border-[#206bc4]' : 'bg-white text-gray-600 border-[#e6e7e9] hover:border-[#206bc4]/40']">
                    {{ opt.label }}
                  </button>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 5: घरेलु सुविधाहरू
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 5" class="rounded-md border border-[#e6e7e9] overflow-hidden">
      <div class="bg-cyan-600 px-5 py-3 flex items-center gap-2">
        <i class="fa-solid fa-house-laptop text-white/80 text-sm"></i>
        <h3 class="text-[14px] font-semibold text-white tracking-wide">घरेलु सुविधाहरू</h3>
      </div>

      <!-- Section A: उपलब्ध सुविधाहरू -->
      <div class="bg-cyan-50/50 border-b border-cyan-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-cyan-100 bg-cyan-100/60">
          <i class="fa-solid fa-tv text-cyan-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-cyan-800 uppercase tracking-wider">उपलब्ध सुविधाहरू <span class="normal-case font-normal text-cyan-500">— Available Facilities</span> <span class="font-normal normal-case text-cyan-600">(बहु छनोट)</span></h4>
        </div>
        <div class="p-4">
          <div class="grid grid-cols-3 gap-2">
            <label v-for="fac in facilityOptions" :key="fac.value"
              class="flex items-center gap-2 px-3 py-2 rounded border cursor-pointer transition-colors text-[13px]"
              :class="form.utilities.access_to_facilities.includes(fac.value)
                ? 'border-[#206bc4] bg-[#206bc4]/8 text-[#206bc4] font-medium'
                : 'border-[#e6e7e9] hover:border-[#206bc4]/40 text-gray-700 bg-white'">
              <input type="checkbox" :value="fac.value" v-model="form.utilities.access_to_facilities" class="rounded text-[#206bc4]" />
              {{ fac.label }}
            </label>
          </div>
        </div>
      </div>

      <!-- Section B: इन्टरनेट -->
      <div class="bg-blue-50/40 border-b border-blue-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-blue-100 bg-blue-100/60">
          <i class="fa-solid fa-wifi text-blue-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-blue-800 uppercase tracking-wider">इन्टरनेट <span class="normal-case font-normal text-blue-500">— Internet Access</span></h4>
        </div>
        <div class="p-4 space-y-3">
          <div class="flex items-center justify-between px-3 py-2.5 bg-white rounded border border-[#e6e7e9]">
            <div>
              <p class="text-[13px] font-medium text-gray-700">इन्टरनेट सुविधा छ?</p>
              <p class="text-[11px] text-gray-400">Has Internet Access?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
              <button type="button" @click="form.utilities.has_internet = true"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.utilities.has_internet === true ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
              <button type="button" @click="form.utilities.has_internet = false"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.utilities.has_internet === false ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
            </div>
          </div>
          <div v-if="form.utilities.has_internet" class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">इन्टरनेटको प्रकार</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">Internet Type</span>
            <select v-model="form.utilities.internet_type" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.internet_type ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Section C: सवारी साधन -->
      <div class="bg-amber-50/40">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-amber-100 bg-amber-100/60">
          <i class="fa-solid fa-motorcycle text-amber-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-amber-800 uppercase tracking-wider">सवारी साधन <span class="normal-case font-normal text-amber-500">— Vehicles</span></h4>
        </div>
        <div class="p-4 space-y-3">
          <div class="flex items-center justify-between px-3 py-2.5 bg-white rounded border border-[#e6e7e9]">
            <div>
              <p class="text-[13px] font-medium text-gray-700">सवारी साधन छ?</p>
              <p class="text-[11px] text-gray-400">Has Vehicle?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
              <button type="button" @click="form.utilities.vehicle = 'Yes'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.utilities.vehicle === 'Yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
              <button type="button" @click="form.utilities.vehicle = 'No'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.utilities.vehicle === 'No' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
            </div>
          </div>
          <div v-if="form.utilities.vehicle === 'Yes'" class="space-y-2 mt-1">
            <!-- Vehicle rows -->
            <div v-for="(veh, idx) in form.utilities.vehicles" :key="idx"
              class="grid grid-cols-[1fr_140px_32px] gap-2 items-end p-3 bg-white rounded border border-[#e6e7e9]">
              <div class="flex flex-col gap-1">
                <label class="text-[12px] font-semibold text-[#626976]">सवारीको किसिम</label>
                <span class="text-[11px] text-gray-400 -mt-0.5">Type of Vehicle</span>
                <select v-model="veh.type" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                  <option value="">-- छान्नुहोस् --</option>
                  <option v-for="opt in (surveyOptions.vehicle_type ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[12px] font-semibold text-[#626976]">संख्या</label>
                <span class="text-[11px] text-gray-400 -mt-0.5">No. of Vehicles</span>
                <input v-model="veh.count" type="number" min="1" placeholder="0"
                  class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
              </div>
              <button type="button" @click="removeVehicle(idx)"
                class="w-8 h-8 flex items-center justify-center rounded-full bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 transition-colors mb-0.5">
                <i class="fa-solid fa-xmark text-[10px]"></i>
              </button>
            </div>
            <!-- Add row button -->
            <button type="button" @click="addVehicle"
              class="flex items-center gap-1.5 text-[#206bc4] text-xs font-semibold hover:text-indigo-700 transition-colors">
              <i class="fa-solid fa-plus text-[10px]"></i> सवारी थप्नुहोस् <span class="font-normal text-gray-400">(Add Vehicle)</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 6: पारिवारिक खुसी र मेलमिलाप
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 6" class="rounded-md border border-[#e6e7e9] overflow-hidden">
      <div class="bg-rose-500 px-5 py-3 flex items-center gap-2">
        <i class="fa-solid fa-heart text-white/80 text-sm"></i>
        <h3 class="text-[14px] font-semibold text-white tracking-wide">पारिवारिक खुसी र मेलमिलाप</h3>
      </div>

      <!-- Section A: बसोबास सन्तुष्टि -->
      <div class="bg-rose-50/50 border-b border-rose-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-rose-100 bg-rose-100/60">
          <i class="fa-solid fa-house-circle-check text-rose-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-rose-800 uppercase tracking-wider">बसोबास सन्तुष्टि <span class="normal-case font-normal text-rose-500">— Residence Satisfaction</span></h4>
        </div>
        <div class="p-4 space-y-2">

          <!-- Happy with location -->
          <div class="flex items-center justify-between px-4 py-2.5 bg-white rounded border border-[#e6e7e9]">
            <div>
              <p class="text-[13px] font-medium text-gray-700">के तपाईं बसेको ठाउँसँग खुसी हुनुहुन्छ?</p>
              <p class="text-[11px] text-gray-400">Are you happy with where you live?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
              <button type="button" @click="form.wellbeing.happy_with_location = 'yes'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.wellbeing.happy_with_location === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">खुसी छौं</button>
              <button type="button" @click="form.wellbeing.happy_with_location = 'no'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.wellbeing.happy_with_location === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">खुसी छैनौं</button>
            </div>
          </div>

          <!-- Residence perception -->
          <div class="flex flex-col gap-1 px-4 py-3 bg-gray-50 rounded border border-[#e6e7e9]">
            <label class="text-[12px] font-semibold text-[#626976]">तपाईं बसेको ठाउँ कस्तो लाग्छ?</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">How do you feel about the place you live?</span>
            <select v-model="form.wellbeing.residence_perception" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.residence_perception ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>

        </div>
      </div>

      <!-- Section B: पारिवारिक खुसी -->
      <div class="bg-amber-50/40 border-b border-amber-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-amber-100 bg-amber-100/60">
          <i class="fa-solid fa-face-smile text-amber-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-amber-800 uppercase tracking-wider">पारिवारिक खुसी <span class="normal-case font-normal text-amber-500">— Family Happiness</span></h4>
        </div>
        <div class="p-4 space-y-3">

          <!-- Family gathering frequency -->
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">खुशीको लागि परिवारलाई कति पटक भेला गर्नुहुन्छ?</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">How often do you have family get-togethers?</span>
            <select v-model="form.wellbeing.family_gathering_freq" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.family_gathering_freq ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>

          <!-- Happiness activities (multi-select) -->
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">परिवारका सदस्यहरूले खुशीको लागि के गर्छन्? <span class="text-gray-400 font-normal">(बहु छनोट)</span></label>
            <span class="text-[11px] text-gray-400 -mt-0.5">What do family members do for happiness?</span>
            <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 mt-1">
              <label v-for="opt in (surveyOptions.happiness_activities ?? [])" :key="opt.value"
                class="flex items-center gap-2 cursor-pointer text-[13px] text-gray-700 hover:text-[#206bc4]">
                <input type="checkbox" :value="opt.value" v-model="form.wellbeing.happiness_activities" class="rounded border-gray-300 text-[#206bc4]" />
                {{ opt.label }}
              </label>
            </div>
          </div>

        </div>
      </div>

      <!-- Section C: छिमेकी सम्बन्ध -->
      <div class="bg-emerald-50/40">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-emerald-100 bg-emerald-100/60">
          <i class="fa-solid fa-people-group text-emerald-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-emerald-800 uppercase tracking-wider">छिमेकी सम्बन्ध <span class="normal-case font-normal text-emerald-500">— Neighborhood Relations</span></h4>
        </div>
        <div class="p-4 space-y-2">

          <!-- Neighbors nice -->
          <div class="flex items-center justify-between px-4 py-2.5 bg-white rounded border border-[#e6e7e9]">
            <div>
              <p class="text-[13px] font-medium text-gray-700">के तपाईंलाई लाग्छ कि छिमेकीहरू राम्रा मानिसहरू हुन्?</p>
              <p class="text-[11px] text-gray-400">Do you think your neighbors are nice people?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
              <button type="button" @click="form.wellbeing.neighbors_nice = 'yes'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.wellbeing.neighbors_nice === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">हुन्</button>
              <button type="button" @click="form.wellbeing.neighbors_nice = 'no'; form.wellbeing.neighbor_cooperation = ''"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.wellbeing.neighbors_nice === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">होइनन्</button>
              <button type="button" @click="form.wellbeing.neighbors_nice = 'neutral'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.wellbeing.neighbors_nice === 'neutral' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">त्यस्तो केही लाग्दैन</button>
            </div>
          </div>

          <!-- Neighbor cooperation — only show when 'yes' or 'neutral' -->
          <div v-if="form.wellbeing.neighbors_nice === 'yes' || form.wellbeing.neighbors_nice === 'neutral'"
            class="flex flex-col gap-1 px-4 py-3 bg-gray-50 rounded border border-[#e6e7e9]">
            <label class="text-[12px] font-semibold text-[#626976]">छिमेकीहरूबीच कतिको सहाकार्य हुन्छ?</label>
            <span class="text-[11px] text-gray-400 -mt-0.5">How much cooperation is there between neighbors?</span>
            <select v-model="form.wellbeing.neighbor_cooperation" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="opt in (surveyOptions.neighbor_cooperation ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>

        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 7: परिवारको पेशा
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 7" class="rounded-md border border-[#e6e7e9] overflow-hidden">
      <div class="bg-violet-600 px-5 py-3 flex items-center gap-2">
        <i class="fa-solid fa-briefcase text-white/80 text-sm"></i>
        <h3 class="text-[14px] font-semibold text-white tracking-wide">परिवारको पेशा</h3>
      </div>

      <!-- Section A: पेशागत वर्गीकरण -->
      <div class="bg-violet-50/50 border-b border-violet-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-violet-100 bg-violet-100/60">
          <i class="fa-solid fa-chart-pie text-violet-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-violet-800 uppercase tracking-wider">पेशागत वर्गीकरण <span class="font-normal normal-case text-violet-600">— पेशा अनुसार सदस्य संख्या</span></h4>
        </div>
        <div class="p-4 space-y-2">
          <!-- existing rows -->
          <div v-for="(row, idx) in form.occupation.occupation_rows" :key="idx"
            class="flex items-center gap-2">
            <select v-model="row.sector"
              class="flex-1 rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
              <option value="">— पेशा छान्नुहोस् —</option>
              <option v-for="opt in (surveyOptions.occupation_sector ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <input v-model="row.count" type="number" min="0" placeholder="संख्या"
              class="w-24 rounded border border-[#e6e7e9] px-3 py-2 text-[13px] text-center bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
            <button type="button" @click="removeOccupationRow(idx)"
              class="w-8 h-8 flex items-center justify-center rounded border border-red-200 text-red-500 hover:bg-red-50 shrink-0">
              <i class="fa-solid fa-times text-xs"></i>
            </button>
          </div>
          <!-- add button -->
          <button type="button" @click="addOccupationRow"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded border border-dashed border-violet-300 text-violet-700 text-[12px] font-semibold hover:bg-violet-50 transition-colors">
            <i class="fa-solid fa-plus text-xs"></i> पेशा थप्नुहोस्
          </button>
        </div>
      </div>

      <!-- Section B: वैदेशिक रोजगार (conditional) -->
      <div v-if="form.occupation.occupation_rows.some(r => r.sector === 'foreign')" class="bg-sky-50/50 border-b border-sky-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-sky-100 bg-sky-100/60">
          <i class="fa-solid fa-plane text-sky-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-sky-800 uppercase tracking-wider">वैदेशिक रोजगार विवरण</h4>
        </div>
        <div class="p-4 grid grid-cols-3 gap-x-5 gap-y-3">
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">भारत</label>
            <input v-model="form.occupation.foreign_india" type="number" min="0" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">खाडी मुलुक</label>
            <input v-model="form.occupation.foreign_gulf" type="number" min="0" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[12px] font-semibold text-[#626976]">अन्य देश</label>
            <input v-model="form.occupation.foreign_others" type="number" min="0" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
          </div>
        </div>
      </div>

      <!-- Section C: सीप तालिम -->
      <div class="bg-amber-50/40">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-amber-100 bg-amber-100/60">
          <i class="fa-solid fa-graduation-cap text-amber-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-amber-800 uppercase tracking-wider">सीप तालिम <span class="normal-case font-normal text-amber-500">— Skill Training</span></h4>
        </div>
        <div class="p-4 space-y-3">

          <!-- Toggle -->
          <div class="flex items-center justify-between px-3 py-2.5 bg-white rounded border border-[#e6e7e9]">
            <div>
              <p class="text-[13px] font-medium text-gray-700">सीप/व्यावसायिक तालिम प्राप्त सदस्य छन्?</p>
              <p class="text-[11px] text-gray-400">Any member with vocational/skill training?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
              <button type="button" @click="form.occupation.skill_training = 'Yes'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.occupation.skill_training === 'Yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
              <button type="button" @click="form.occupation.skill_training = 'No'; form.occupation.skill_rows = []"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.occupation.skill_training === 'No' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
            </div>
          </div>

          <!-- Skill Add+ rows -->
          <div v-if="form.occupation.skill_training === 'Yes'" class="space-y-2">
            <div v-for="(row, idx) in form.occupation.skill_rows" :key="idx"
              class="flex items-center gap-2">
              <select v-model="row.skill"
                class="flex-1 rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                <option value="">— सीपको किसिम —</option>
                <option v-for="opt in (surveyOptions.technical_skill ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
              <input v-model="row.count" type="number" min="0" placeholder="संख्या"
                class="w-24 rounded border border-[#e6e7e9] px-3 py-2 text-[13px] text-center bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
              <button type="button" @click="removeSkillRow(idx)"
                class="w-8 h-8 flex items-center justify-center rounded border border-red-200 text-red-500 hover:bg-red-50 shrink-0">
                <i class="fa-solid fa-times text-xs"></i>
              </button>
            </div>
            <button type="button" @click="addSkillRow"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded border border-dashed border-amber-300 text-amber-700 text-[12px] font-semibold hover:bg-amber-50 transition-colors">
              <i class="fa-solid fa-plus text-xs"></i> सीप थप्नुहोस्
            </button>
          </div>

        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 8: खेतीपाती
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 8" class="rounded-md border border-[#e6e7e9] overflow-hidden">
      <div class="bg-green-600 px-5 py-3 flex items-center gap-2">
        <i class="fa-solid fa-seedling text-white/80 text-sm"></i>
        <h3 class="text-[14px] font-semibold text-white tracking-wide">खेतीपाती</h3>
      </div>

      <!-- Agriculture toggle -->
      <div class="bg-green-50/40 border-b border-green-100 p-4">
        <div class="flex items-center justify-between px-3 py-2.5 bg-white rounded border border-[#e6e7e9]">
          <div>
            <p class="text-[13px] font-medium text-gray-700">परिवारले कुनै किसिमको खेतीपाती गर्ने गरेको छ ?</p>
            <p class="text-[11px] text-gray-400">Does this family have agriculture occupation?</p>
          </div>
          <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
            <button type="button" @click="form.agriculture.agriculture = 'yes'"
              :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.agriculture.agriculture === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
            <button type="button" @click="form.agriculture.agriculture = 'no'; form.agriculture.agri_categories = []"
              :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.agriculture.agriculture === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
          </div>
        </div>
      </div>

      <template v-if="form.agriculture.agriculture === 'yes'">

        <!-- Section A: मुख्य कृषि शिर्षकहरु -->
        <div class="bg-green-50/50 border-b border-green-100">
          <div class="flex items-center gap-2 px-5 py-2.5 border-b border-green-100 bg-green-100/60">
            <i class="fa-solid fa-leaf text-green-600 text-xs"></i>
            <h4 class="text-[12px] font-bold text-green-800 uppercase tracking-wider">मुख्य कृषि शिर्षकहरु <span class="font-normal normal-case text-green-600">— Agricultural Categories</span></h4>
          </div>
          <div class="p-4 grid grid-cols-2 gap-2">
            <label v-for="opt in (surveyOptions.agri_category ?? [])" :key="opt.value"
              class="flex items-center gap-2 px-3 py-2 rounded border cursor-pointer transition-colors text-[13px]"
              :class="form.agriculture.agri_categories.includes(opt.value)
                ? 'border-green-500 bg-green-50 text-green-700 font-medium'
                : 'border-[#e6e7e9] hover:border-green-300 text-gray-700 bg-white'">
              <input type="checkbox" :value="opt.value" v-model="form.agriculture.agri_categories" class="rounded text-green-600" />
              {{ opt.label_ne ?? opt.label }}
            </label>
          </div>
        </div>

        <!-- Section B: रासायनिक मल -->
        <div class="bg-emerald-50/40 border-b border-emerald-100">
          <div class="flex items-center gap-2 px-5 py-2.5 border-b border-emerald-100 bg-emerald-100/60">
            <i class="fa-solid fa-flask text-emerald-600 text-xs"></i>
            <h4 class="text-[12px] font-bold text-emerald-800 uppercase tracking-wider">रासायनिक मल <span class="font-normal normal-case text-emerald-600">— Chemical Fertilizer</span></h4>
          </div>
          <div class="p-4 space-y-4">

            <!-- Toggle -->
            <div class="flex items-center justify-between px-3 py-2.5 bg-white rounded border border-[#e6e7e9]">
              <div>
                <p class="text-[13px] font-medium text-gray-700">रासायनिक मलको प्रयोग गर्नु हुन्छ कि हुदैन ?</p>
                <p class="text-[11px] text-gray-400">Does this family use chemical fertilizer?</p>
              </div>
              <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
                <button type="button" @click="form.agriculture.use_of_chem_fertilizer = 'yes'"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.agriculture.use_of_chem_fertilizer === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
                <button type="button" @click="form.agriculture.use_of_chem_fertilizer = 'no'; form.agriculture.chem_fertilizer_types = []; form.agriculture.chem_fertilizer_kg = ''; form.agriculture.fertilizer_sources = []"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.agriculture.use_of_chem_fertilizer === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
              </div>
            </div>

            <template v-if="form.agriculture.use_of_chem_fertilizer === 'yes'">
              <!-- Fertilizer types -->
              <div class="space-y-1.5">
                <p class="text-[12px] font-semibold text-[#626976]">कुन कुन रासायनिक मल प्रयोग गर्ने गरेको छ ? <span class="font-normal text-gray-400">— Which chemical fertilizer do you use?</span></p>
                <div class="grid grid-cols-3 gap-2">
                  <label v-for="opt in (surveyOptions.chem_fertilizer ?? [])" :key="opt.value"
                    class="flex items-center gap-2 px-3 py-2 rounded border cursor-pointer transition-colors text-[13px]"
                    :class="form.agriculture.chem_fertilizer_types.includes(opt.value)
                      ? 'border-emerald-500 bg-emerald-50 text-emerald-700 font-medium'
                      : 'border-[#e6e7e9] hover:border-emerald-300 text-gray-700 bg-white'">
                    <input type="checkbox" :value="opt.value" v-model="form.agriculture.chem_fertilizer_types" class="rounded text-emerald-600" />
                    {{ opt.label_ne ?? opt.label }}
                  </label>
                </div>
              </div>

              <!-- KG amount -->
              <div class="flex flex-col gap-1">
                <label class="text-[12px] font-semibold text-[#626976]">बार्षिक कति केजी रासायनिक मल प्रयोग गर्नु हुन्छ ? <span class="font-normal text-gray-400">— Annual usage in KG</span></label>
                <div class="relative w-48">
                  <input v-model="form.agriculture.chem_fertilizer_kg" type="number" min="0" placeholder="0"
                    class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4] pr-10" />
                  <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-gray-400 font-medium">KG</span>
                </div>
              </div>

              <!-- Fertilizer source -->
              <div class="space-y-1.5">
                <p class="text-[12px] font-semibold text-[#626976]">कुन ठाउबाट रासायनिक मल खरिद गर्नु हुन्छ ? <span class="font-normal text-gray-400">— Where do you buy fertilizer?</span></p>
                <div class="grid grid-cols-3 gap-2">
                  <label v-for="opt in (surveyOptions.fertilizer_source ?? [])" :key="opt.value"
                    class="flex items-center gap-2 px-3 py-2 rounded border cursor-pointer transition-colors text-[13px]"
                    :class="form.agriculture.fertilizer_sources.includes(opt.value)
                      ? 'border-emerald-500 bg-emerald-50 text-emerald-700 font-medium'
                      : 'border-[#e6e7e9] hover:border-emerald-300 text-gray-700 bg-white'">
                    <input type="checkbox" :value="opt.value" v-model="form.agriculture.fertilizer_sources" class="rounded text-emerald-600" />
                    {{ opt.label_ne ?? opt.label }}
                  </label>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Section C: रासायनिक बिषादी -->
        <div class="bg-orange-50/40 border-b border-orange-100">
          <div class="flex items-center gap-2 px-5 py-2.5 border-b border-orange-100 bg-orange-100/60">
            <i class="fa-solid fa-bug-slash text-orange-600 text-xs"></i>
            <h4 class="text-[12px] font-bold text-orange-800 uppercase tracking-wider">रासायनिक बिषादी <span class="font-normal normal-case text-orange-600">— Chemical Pesticide</span></h4>
          </div>
          <div class="p-4 space-y-4">

            <!-- Toggle -->
            <div class="flex items-center justify-between px-3 py-2.5 bg-white rounded border border-[#e6e7e9]">
              <div>
                <p class="text-[13px] font-medium text-gray-700">रासायनिक बिषादी प्रयोग गर्ने गर्नु भएको छ ?</p>
                <p class="text-[11px] text-gray-400">Do you use chemical pesticide in the crops?</p>
              </div>
              <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
                <button type="button" @click="form.agriculture.use_of_chem_pesticide = 'yes'"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.agriculture.use_of_chem_pesticide === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
                <button type="button" @click="form.agriculture.use_of_chem_pesticide = 'no'; form.agriculture.chem_pesticide_types = []; form.agriculture.chem_pesticide_cost = ''"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.agriculture.use_of_chem_pesticide === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
              </div>
            </div>

            <template v-if="form.agriculture.use_of_chem_pesticide === 'yes'">
              <!-- Pesticide types -->
              <div class="space-y-1.5">
                <p class="text-[12px] font-semibold text-[#626976]">कुन कुन रासायनिक बिषादी प्रयोग गर्नु हुन्छ ? <span class="font-normal text-gray-400">— Which chemical pesticide do you use?</span></p>
                <div class="grid grid-cols-2 gap-2">
                  <label v-for="opt in (surveyOptions.chem_pesticide ?? [])" :key="opt.value"
                    class="flex items-center gap-2 px-3 py-2 rounded border cursor-pointer transition-colors text-[13px]"
                    :class="form.agriculture.chem_pesticide_types.includes(opt.value)
                      ? 'border-orange-500 bg-orange-50 text-orange-700 font-medium'
                      : 'border-[#e6e7e9] hover:border-orange-300 text-gray-700 bg-white'">
                    <input type="checkbox" :value="opt.value" v-model="form.agriculture.chem_pesticide_types" class="rounded text-orange-600" />
                    {{ opt.label_ne ?? opt.label }}
                  </label>
                </div>
              </div>

              <!-- Cost amount -->
              <div class="flex flex-col gap-1">
                <label class="text-[12px] font-semibold text-[#626976]">बार्षिक कति मुल्य बराबरको रासायनिक बिषादी प्रयोग गर्नु हुन्छ ? <span class="font-normal text-gray-400">— Annual pesticide expenditure</span></label>
                <div class="relative w-48">
                  <input v-model="form.agriculture.chem_pesticide_cost" type="number" min="0" placeholder="0"
                    class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4] pr-14" />
                  <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] text-gray-400 font-medium">रु.</span>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Section D: कृषिका चुनौती (unchanged) -->
        <div class="bg-amber-50/40">
          <div class="flex items-center gap-2 px-5 py-2.5 border-b border-amber-100 bg-amber-100/60">
            <i class="fa-solid fa-triangle-exclamation text-amber-600 text-xs"></i>
            <h4 class="text-[12px] font-bold text-amber-800 uppercase tracking-wider">कृषिका चुनौती <span class="font-normal normal-case text-amber-600">(बहु छनोट)</span></h4>
          </div>
          <div class="p-4 grid grid-cols-3 gap-2">
            <label v-for="ch in farmChallengeOptions" :key="ch.value"
              class="flex items-center gap-2 px-3 py-2 rounded border cursor-pointer transition-colors text-[13px]"
              :class="form.agriculture.Farm_Challenges.includes(ch.value)
                ? 'border-amber-500 bg-amber-50 text-amber-700 font-medium'
                : 'border-[#e6e7e9] hover:border-amber-300 text-gray-700 bg-white'">
              <input type="checkbox" :value="ch.value" v-model="form.agriculture.Farm_Challenges" class="rounded text-amber-600" />
              {{ ch.label }}
            </label>
          </div>
        </div>

      </template>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 9: कृषि उत्पादन
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 9" class="rounded-md border border-[#e6e7e9] overflow-hidden">
      <div class="bg-lime-600 px-5 py-3 flex items-center gap-2">
        <i class="fa-solid fa-wheat-awn text-white/80 text-sm"></i>
        <h3 class="text-[14px] font-semibold text-white tracking-wide">कृषि उत्पादन</h3>
      </div>

      <!-- No categories selected warning -->
      <div v-if="!form.agriculture.agri_categories.length" class="p-8 text-center text-sm text-gray-400 border-b border-[#e6e7e9]">
        <i class="fa-solid fa-circle-info text-gray-300 text-2xl mb-2 block"></i>
        चरण ८ मा कृषि श्रेणी छान्नुहोस् — Step 8 मा कुनै कृषि श्रेणी छानिएको छैन
      </div>

      <!-- Dynamic sections per selected agri_category -->
      <template v-for="cat in agriCategoryDefs" :key="cat.key">
        <div v-if="form.agriculture.agri_categories.includes(cat.key)"
          class="border-b border-[#e6e7e9] last:border-b-0">
          <div :class="['flex items-center justify-between px-5 py-2.5 border-b border-[#e6e7e9]', cat.headerBg]">
            <div class="flex items-center gap-2">
              <i :class="[cat.icon, cat.iconColor, 'text-xs']"></i>
              <h4 class="text-[12px] font-bold uppercase tracking-wider" :class="cat.titleColor">
                {{ cat.label }} <span class="font-normal normal-case opacity-70">— {{ cat.labelEn }}</span>
              </h4>
            </div>
            <button type="button" @click="addCropRow(cat.key)"
              :class="['flex items-center gap-1.5 px-3 py-1 rounded border border-dashed text-[11px] font-semibold transition-colors', cat.addBtnClass]">
              <i class="fa-solid fa-plus text-xs"></i> बाली थप्नुहोस्
            </button>
          </div>

          <div :class="[cat.bodyBg, 'p-3']">
            <!-- Empty state -->
            <div v-if="!form.cropProduction[cat.key].length"
              class="text-center text-[12px] text-gray-400 py-4 border border-dashed border-gray-200 rounded">
              बाली थप्न माथिको बटन थिच्नुहोस्
            </div>

            <!-- Table -->
            <div v-else class="overflow-x-auto rounded border border-gray-200">
              <table class="w-full text-[12px]">
                <thead>
                  <tr :class="cat.theadBg">
                    <th class="text-left font-semibold px-2 py-2 text-[11px] uppercase tracking-wide min-w-[140px]">बालीको नाम<br><span class="font-normal normal-case opacity-70">Crop Name</span></th>
                    <th class="text-left font-semibold px-2 py-2 text-[11px] uppercase tracking-wide min-w-[110px]">नाप एकाइ<br><span class="font-normal normal-case opacity-70">Land Unit</span></th>
                    <th class="text-left font-semibold px-2 py-2 text-[11px] uppercase tracking-wide min-w-[90px]">क्षेत्रफल<br><span class="font-normal normal-case opacity-70">Land Area</span></th>
                    <th class="text-left font-semibold px-2 py-2 text-[11px] uppercase tracking-wide min-w-[110px]">उत्पादन एकाइ<br><span class="font-normal normal-case opacity-70">Prod. Unit</span></th>
                    <th class="text-left font-semibold px-2 py-2 text-[11px] uppercase tracking-wide min-w-[80px]">उत्पादन<br><span class="font-normal normal-case opacity-70">Prod. Qty</span></th>
                    <th class="text-left font-semibold px-2 py-2 text-[11px] uppercase tracking-wide min-w-[80px]">बिक्री<br><span class="font-normal normal-case opacity-70">Sale Qty</span></th>
                    <th class="text-left font-semibold px-2 py-2 text-[11px] uppercase tracking-wide min-w-[90px]">प्रति एकाइ मुल्य<br><span class="font-normal normal-case opacity-70">Per Unit Price</span></th>
                    <th class="w-8 px-2 py-2"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="(row, idx) in form.cropProduction[cat.key]" :key="idx"
                    :class="idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'">
                    <!-- Crop name dropdown -->
                    <td class="px-2 py-1.5">
                      <select v-model="row.crop"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                        <option value="">— छान्नुहोस् —</option>
                        <option v-for="opt in (surveyOptions[cat.cropCategory] ?? [])" :key="opt.value" :value="opt.value">
                          {{ opt.label_ne ?? opt.label }}
                        </option>
                      </select>
                    </td>
                    <!-- Land unit -->
                    <td class="px-2 py-1.5">
                      <select v-model="row.land_unit"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                        <option value="">— एकाइ —</option>
                        <option v-for="opt in (surveyOptions.land_measurement_unit ?? [])" :key="opt.value" :value="opt.value">
                          {{ opt.label_ne ?? opt.label }}
                        </option>
                      </select>
                    </td>
                    <!-- Land area -->
                    <td class="px-2 py-1.5">
                      <input v-model="row.land_area" type="number" min="0" step="0.01" placeholder="0"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
                    </td>
                    <!-- Production unit -->
                    <td class="px-2 py-1.5">
                      <select v-model="row.prod_unit"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                        <option value="">— एकाइ —</option>
                        <option v-for="opt in (surveyOptions.agri_production_unit ?? [])" :key="opt.value" :value="opt.value">
                          {{ opt.label_ne ?? opt.label }}
                        </option>
                      </select>
                    </td>
                    <!-- Production qty -->
                    <td class="px-2 py-1.5">
                      <input v-model="row.prod_qty" type="number" min="0" step="0.01" placeholder="0"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
                    </td>
                    <!-- Sale qty -->
                    <td class="px-2 py-1.5">
                      <input v-model="row.sale_qty" type="number" min="0" step="0.01" placeholder="0"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
                    </td>
                    <!-- Per unit price -->
                    <td class="px-2 py-1.5">
                      <input v-model="row.price_per_unit" type="number" min="0" step="0.01" placeholder="0"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
                    </td>
                    <!-- Remove -->
                    <td class="px-2 py-1.5 text-center">
                      <button type="button" @click="removeCropRow(cat.key, idx)"
                        class="text-red-400 hover:text-red-600 transition-colors">
                        <i class="fa-solid fa-times text-xs"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 10: पशुपालन
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 10" class="rounded-md border border-[#e6e7e9] overflow-hidden">
      <div class="bg-yellow-600 px-5 py-3 flex items-center gap-2">
        <i class="fa-solid fa-cow text-white/80 text-sm"></i>
        <h3 class="text-[14px] font-semibold text-white tracking-wide">पशुपालन</h3>
      </div>

      <!-- Main toggle -->
      <div class="bg-yellow-50/40 border-b border-yellow-100 p-4">
        <div class="flex items-center justify-between px-3 py-2.5 bg-white rounded border border-[#e6e7e9]">
          <div>
            <p class="text-[13px] font-medium text-gray-700">परिवारले पशुपन्छी पालन गरेको छ ?</p>
            <p class="text-[11px] text-gray-400">Does the family have livestock and domestic birds?</p>
          </div>
          <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
            <button type="button" @click="form.livestockDetail.has_livestock = 'yes'"
              :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.livestockDetail.has_livestock === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
            <button type="button" @click="form.livestockDetail.has_livestock = 'no'; form.livestockDetail.livestock_rows = []; form.livestockDetail.product_rows = []"
              :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.livestockDetail.has_livestock === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
          </div>
        </div>
      </div>

      <template v-if="form.livestockDetail.has_livestock === 'yes'">

        <!-- Section A: पशुपन्छी संख्या तथा परिमाण -->
        <div class="bg-yellow-50/50 border-b border-yellow-100">
          <div class="flex items-center justify-between px-5 py-2.5 border-b border-yellow-100 bg-yellow-100/60">
            <div class="flex items-center gap-2">
              <i class="fa-solid fa-horse text-yellow-700 text-xs"></i>
              <h4 class="text-[12px] font-bold text-yellow-800 uppercase tracking-wider">पशुपन्छी संख्या तथा परिमाण <span class="font-normal normal-case text-yellow-600">— Livestock Count & Details</span></h4>
            </div>
            <button type="button" @click="addLivestockRow"
              class="flex items-center gap-1.5 px-3 py-1 rounded border border-dashed border-yellow-400 text-yellow-700 text-[11px] font-semibold hover:bg-yellow-50 transition-colors">
              <i class="fa-solid fa-plus text-xs"></i> पशु/पन्छी थप्नुहोस्
            </button>
          </div>
          <div class="p-3">
            <div v-if="!form.livestockDetail.livestock_rows.length"
              class="text-center text-[12px] text-gray-400 py-4 border border-dashed border-gray-200 rounded">
              पशु/पन्छी थप्न माथिको बटन थिच्नुहोस्
            </div>
            <div v-else class="overflow-x-auto rounded border border-yellow-200">
              <table class="w-full text-[12px]">
                <thead>
                  <tr class="bg-yellow-100/80 text-yellow-800">
                    <th class="text-left font-semibold px-3 py-2 text-[11px] uppercase min-w-[160px]">पशुपन्छीको किसिम<br><span class="font-normal normal-case opacity-70">Livestock Kind</span></th>
                    <th class="text-left font-semibold px-3 py-2 text-[11px] uppercase min-w-[90px]">परिमाण<br><span class="font-normal normal-case opacity-70">Quantity</span></th>
                    <th class="text-left font-semibold px-3 py-2 text-[11px] uppercase min-w-[150px]">पशुपन्छी जात<br><span class="font-normal normal-case opacity-70">Breed</span></th>
                    <th class="text-left font-semibold px-3 py-2 text-[11px] uppercase min-w-[180px]">पालनको तरिका<br><span class="font-normal normal-case opacity-70">Rearing Method</span></th>
                    <th class="w-8 px-3 py-2"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-yellow-50">
                  <tr v-for="(row, idx) in form.livestockDetail.livestock_rows" :key="idx"
                    :class="idx % 2 === 0 ? 'bg-white' : 'bg-yellow-50/30'">
                    <td class="px-3 py-1.5">
                      <select v-model="row.kind"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                        <option value="">— छान्नुहोस् —</option>
                        <option v-for="opt in (surveyOptions.livestock_kind ?? [])" :key="opt.value" :value="opt.value">
                          {{ opt.label_ne ?? opt.label }}
                        </option>
                      </select>
                    </td>
                    <td class="px-3 py-1.5">
                      <input v-model="row.qty" type="number" min="0" placeholder="0"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
                    </td>
                    <td class="px-3 py-1.5">
                      <select v-model="row.breed"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                        <option value="">— छान्नुहोस् —</option>
                        <option v-for="opt in (surveyOptions.livestock_breed ?? [])" :key="opt.value" :value="opt.value">
                          {{ opt.label_ne ?? opt.label }}
                        </option>
                      </select>
                    </td>
                    <td class="px-3 py-1.5">
                      <select v-model="row.rearing_method"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                        <option value="">— छान्नुहोस् —</option>
                        <option v-for="opt in (surveyOptions.livestock_rearing_method ?? [])" :key="opt.value" :value="opt.value">
                          {{ opt.label_ne ?? opt.label }}
                        </option>
                      </select>
                    </td>
                    <td class="px-3 py-1.5 text-center">
                      <button type="button" @click="removeLivestockRow(idx)" class="text-red-400 hover:text-red-600 transition-colors">
                        <i class="fa-solid fa-times text-xs"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Section B: पशुपन्छी जन्य उत्पादन -->
        <div class="bg-amber-50/40 border-b border-amber-100">
          <div class="flex items-center justify-between px-5 py-2.5 border-b border-amber-100 bg-amber-100/60">
            <div class="flex items-center gap-2">
              <i class="fa-solid fa-jar text-amber-600 text-xs"></i>
              <h4 class="text-[12px] font-bold text-amber-800 uppercase tracking-wider">पशुपन्छी जन्य उत्पादन <span class="font-normal normal-case text-amber-600">— Livestock Products</span></h4>
            </div>
            <button type="button" @click="addProductRow"
              class="flex items-center gap-1.5 px-3 py-1 rounded border border-dashed border-amber-400 text-amber-700 text-[11px] font-semibold hover:bg-amber-50 transition-colors">
              <i class="fa-solid fa-plus text-xs"></i> उत्पादन थप्नुहोस्
            </button>
          </div>
          <div class="p-3">
            <div v-if="!form.livestockDetail.product_rows.length"
              class="text-center text-[12px] text-gray-400 py-4 border border-dashed border-gray-200 rounded">
              उत्पादन थप्न माथिको बटन थिच्नुहोस्
            </div>
            <div v-else class="overflow-x-auto rounded border border-amber-200">
              <table class="w-full text-[12px]">
                <thead>
                  <tr class="bg-amber-100/80 text-amber-800">
                    <th class="text-left font-semibold px-3 py-2 text-[11px] uppercase min-w-[140px]">उत्पादनको किसिम<br><span class="font-normal normal-case opacity-70">Product Type</span></th>
                    <th class="text-left font-semibold px-3 py-2 text-[11px] uppercase min-w-[110px]">उत्पादन एकाइ<br><span class="font-normal normal-case opacity-70">Unit</span></th>
                    <th class="text-left font-semibold px-3 py-2 text-[11px] uppercase min-w-[90px]">उत्पादन परिमाण<br><span class="font-normal normal-case opacity-70">Prod. Qty</span></th>
                    <th class="text-left font-semibold px-3 py-2 text-[11px] uppercase min-w-[90px]">बिक्री परिमाण<br><span class="font-normal normal-case opacity-70">Sale Qty</span></th>
                    <th class="text-left font-semibold px-3 py-2 text-[11px] uppercase min-w-[100px]">प्रति इकाई मुल्य<br><span class="font-normal normal-case opacity-70">Per Unit Price</span></th>
                    <th class="w-8 px-3 py-2"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-amber-50">
                  <tr v-for="(row, idx) in form.livestockDetail.product_rows" :key="idx"
                    :class="idx % 2 === 0 ? 'bg-white' : 'bg-amber-50/30'">
                    <td class="px-3 py-1.5">
                      <select v-model="row.product"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                        <option value="">— छान्नुहोस् —</option>
                        <option v-for="opt in (surveyOptions.livestock_product ?? [])" :key="opt.value" :value="opt.value">
                          {{ opt.label_ne ?? opt.label }}
                        </option>
                      </select>
                    </td>
                    <td class="px-3 py-1.5">
                      <select v-model="row.unit"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                        <option value="">— एकाइ —</option>
                        <option v-for="opt in (surveyOptions.livestock_product_unit ?? [])" :key="opt.value" :value="opt.value">
                          {{ opt.label_ne ?? opt.label }}
                        </option>
                      </select>
                    </td>
                    <td class="px-3 py-1.5">
                      <input v-model="row.qty" type="number" min="0" step="0.01" placeholder="0"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
                    </td>
                    <td class="px-3 py-1.5">
                      <input v-model="row.sale_qty" type="number" min="0" step="0.01" placeholder="0"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
                    </td>
                    <td class="px-3 py-1.5">
                      <input v-model="row.price_per_unit" type="number" min="0" step="0.01" placeholder="0"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
                    </td>
                    <td class="px-3 py-1.5 text-center">
                      <button type="button" @click="removeProductRow(idx)" class="text-red-400 hover:text-red-600 transition-colors">
                        <i class="fa-solid fa-times text-xs"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Section C: कृत्रिम गर्भाधान -->
        <div class="bg-orange-50/40">
          <div class="flex items-center gap-2 px-5 py-2.5 border-b border-orange-100 bg-orange-100/60">
            <i class="fa-solid fa-syringe text-orange-600 text-xs"></i>
            <h4 class="text-[12px] font-bold text-orange-800 uppercase tracking-wider">कृत्रिम गर्भाधान <span class="font-normal normal-case text-orange-600">— Artificial Insemination</span></h4>
          </div>
          <div class="p-4">
            <div class="flex items-center justify-between px-3 py-2.5 bg-white rounded border border-[#e6e7e9]">
              <div>
                <p class="text-[13px] font-medium text-gray-700">कृत्रिम गर्भाधान गर्नुहुन्छ?</p>
                <p class="text-[11px] text-gray-400">Do you use artificial insemination?</p>
              </div>
              <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
                <button type="button" @click="form.livestockDetail.Ai_in_livestock = 'yes'"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.livestockDetail.Ai_in_livestock === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
                <button type="button" @click="form.livestockDetail.Ai_in_livestock = 'no'"
                  :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.livestockDetail.Ai_in_livestock === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
              </div>
            </div>
          </div>
        </div>

      </template>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 11: मौरी/माछा/रेशम
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 11" class="rounded-md border border-[#e6e7e9] overflow-hidden">
      <div class="bg-sky-600 px-5 py-3 flex items-center gap-2">
        <i class="fa-solid fa-fish text-white/80 text-sm"></i>
        <h3 class="text-[14px] font-semibold text-white tracking-wide">मौरी / माछा / रेशम</h3>
      </div>

      <!-- Main toggle -->
      <div class="bg-sky-50/40 border-b border-sky-100 p-4">
        <div class="flex items-center justify-between px-3 py-2.5 bg-white rounded border border-[#e6e7e9]">
          <div>
            <p class="text-[13px] font-medium text-gray-700">यस परिवारले माछा / मौरी / रेशम मध्ये कुनै पालन गर्नु भएको छ ?</p>
            <p class="text-[11px] text-gray-400">Does this family have fishery, beekeeping or silk farming?</p>
          </div>
          <div class="flex rounded overflow-hidden border border-[#e6e7e9] shrink-0 ml-4">
            <button type="button" @click="form.beeFishSilk.has_bee_fish_silk = 'yes'"
              :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.beeFishSilk.has_bee_fish_silk === 'yes' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
            <button type="button" @click="form.beeFishSilk.has_bee_fish_silk = 'no'; form.beeFishSilk.bee_fish_silk_select = []"
              :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-[#e6e7e9]', form.beeFishSilk.has_bee_fish_silk === 'no' ? 'bg-[#206bc4] text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
          </div>
        </div>
      </div>

      <template v-if="form.beeFishSilk.has_bee_fish_silk === 'yes'">

      <!-- Selection -->
      <div class="bg-sky-50/50 border-b border-sky-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-sky-100 bg-sky-100/60">
          <i class="fa-solid fa-hand-pointer text-sky-600 text-xs"></i>
          <h4 class="text-[12px] font-bold text-sky-800 uppercase tracking-wider">के पाल्नुहुन्छ? <span class="font-normal normal-case text-sky-600">(बहु छनोट)</span></h4>
        </div>
        <div class="p-4 flex gap-3">
          <label v-for="item in [{value:'bee',label:'🐝 मौरी'},{value:'fish',label:'🐟 माछा'},{value:'silk',label:'🪱 रेशम'}]" :key="item.value"
            class="flex items-center gap-2 px-5 py-2.5 rounded border cursor-pointer transition-colors text-[13px] font-medium"
            :class="form.beeFishSilk.bee_fish_silk_select.includes(item.value)
              ? 'border-sky-500 bg-sky-100 text-sky-700'
              : 'border-[#e6e7e9] bg-white text-gray-700 hover:border-sky-300'">
            <input type="checkbox" :value="item.value" v-model="form.beeFishSilk.bee_fish_silk_select" class="rounded text-sky-600" />
            {{ item.label }}
          </label>
        </div>
      </div>

      <!-- Bee section -->
      <div v-if="form.beeFishSilk.bee_fish_silk_select.includes('bee')" class="bg-amber-50/50 border-b border-amber-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-amber-100 bg-amber-100/60">
          <span class="text-sm">🐝</span>
          <h4 class="text-[12px] font-bold text-amber-800 uppercase tracking-wider">मौरीपालन विवरण</h4>
        </div>
        <div class="p-4 grid grid-cols-2 gap-x-5 gap-y-3">
          <div class="flex flex-col gap-1"><label class="text-[12px] font-semibold text-[#626976]">मुढे घार संख्या</label><input v-model="form.beeFishSilk.Mudhe_Hive" type="number" min="0" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" /></div>
          <div class="flex flex-col gap-1"><label class="text-[12px] font-semibold text-[#626976]">आधुनिक घार संख्या</label><input v-model="form.beeFishSilk.Modern_Hive" type="number" min="0" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" /></div>
          <div class="flex flex-col gap-1"><label class="text-[12px] font-semibold text-[#626976]">मुढे घारबाट मह उत्पादन (के.जी.)</label><input v-model="form.beeFishSilk.Honey_Production_Mudhe_Hive" type="number" min="0" step="0.1" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" /></div>
          <div class="flex flex-col gap-1"><label class="text-[12px] font-semibold text-[#626976]">आधुनिक घारबाट मह उत्पादन (के.जी.)</label><input v-model="form.beeFishSilk.Honey_Production_Modern_Hive" type="number" min="0" step="0.1" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" /></div>
          <div class="flex flex-col gap-1 col-span-2"><label class="text-[12px] font-semibold text-[#626976]">मह बिक्री (के.जी.)</label><input v-model="form.beeFishSilk.honey_sales" type="number" min="0" step="0.1" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" /></div>
        </div>
      </div>

      <!-- Fish section -->
      <div v-if="form.beeFishSilk.bee_fish_silk_select.includes('fish')" class="bg-blue-50/50 border-b border-blue-100">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-blue-100 bg-blue-100/60">
          <span class="text-sm">🐟</span>
          <h4 class="text-[12px] font-bold text-blue-800 uppercase tracking-wider">माछापालन विवरण</h4>
        </div>
        <div class="p-4 grid grid-cols-3 gap-x-5 gap-y-3">
          <div class="flex flex-col gap-1"><label class="text-[12px] font-semibold text-[#626976]">पोखरी क्षेत्रफल (वर्गमिटर)</label><input v-model="form.beeFishSilk.Pond_area" type="number" min="0" step="0.01" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" /></div>
          <div class="flex flex-col gap-1"><label class="text-[12px] font-semibold text-[#626976]">माछा उत्पादन (के.जी.)</label><input v-model="form.beeFishSilk.fish_prod" type="number" min="0" step="0.1" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" /></div>
          <div class="flex flex-col gap-1"><label class="text-[12px] font-semibold text-[#626976]">माछा बिक्री (के.जी.)</label><input v-model="form.beeFishSilk.fish_sales" type="number" min="0" step="0.1" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" /></div>
        </div>
      </div>

      <!-- Silk section -->
      <div v-if="form.beeFishSilk.bee_fish_silk_select.includes('silk')" class="bg-purple-50/50">
        <div class="flex items-center gap-2 px-5 py-2.5 border-b border-purple-100 bg-purple-100/60">
          <span class="text-sm">🪱</span>
          <h4 class="text-[12px] font-bold text-purple-800 uppercase tracking-wider">रेशम विवरण</h4>
        </div>
        <div class="p-4 grid grid-cols-2 gap-x-5 gap-y-3">
          <div class="flex flex-col gap-1"><label class="text-[12px] font-semibold text-[#626976]">रेशम उत्पादन (के.जी.)</label><input v-model="form.beeFishSilk.silk_prod" type="number" min="0" step="0.1" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" /></div>
          <div class="flex flex-col gap-1"><label class="text-[12px] font-semibold text-[#626976]">रेशम बिक्री (के.जी.)</label><input v-model="form.beeFishSilk.silk_sales" type="number" min="0" step="0.1" placeholder="0" class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" /></div>
        </div>
      </div>

      </template>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 12: आम्दानी र खर्च
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 12" class="rounded-md border border-[#e6e7e9] overflow-hidden">
      <div class="bg-green-700 px-5 py-3 flex items-center gap-2">
        <i class="fa-solid fa-money-bill-wave text-white/80 text-sm"></i>
        <h3 class="text-[14px] font-semibold text-white tracking-wide">आम्दानी र खर्च</h3>
      </div>

      <!-- Section A: आय विवरण -->
      <div class="bg-green-50/60 border-b border-green-100">
        <div class="flex items-center justify-between px-5 py-2.5 border-b border-green-100 bg-green-100/60">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-coins text-green-600 text-xs"></i>
            <h4 class="text-[12px] font-bold text-green-800 uppercase tracking-wider">आय विवरण <span class="font-normal normal-case text-green-600">— Income Details</span></h4>
          </div>
          <button type="button" @click="addIncomeRow"
            class="flex items-center gap-1.5 px-3 py-1 rounded border border-dashed border-green-400 text-green-700 text-[11px] font-semibold hover:bg-green-50 transition-colors">
            <i class="fa-solid fa-plus text-xs"></i> आय थप्नुहोस्
          </button>
        </div>
        <div class="p-3">
          <div v-if="!form.economic.income_rows.length"
            class="text-center text-[12px] text-gray-400 py-4 border border-dashed border-gray-200 rounded">
            आय स्रोत थप्न माथिको बटन थिच्नुहोस्
          </div>
          <div v-else class="overflow-x-auto rounded border border-green-200">
            <table class="w-full text-[12px]">
              <thead>
                <tr class="bg-green-100/80 text-green-800">
                  <th class="text-left font-semibold px-3 py-2 text-[11px] uppercase">आम्दानीको स्रोत<br><span class="font-normal normal-case opacity-70">Income Source</span></th>
                  <th class="text-left font-semibold px-3 py-2 text-[11px] uppercase min-w-[140px]">बार्षिक आय (रु)<br><span class="font-normal normal-case opacity-70">Annual Income</span></th>
                  <th class="w-8 px-3 py-2"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-green-50">
                <tr v-for="(row, idx) in form.economic.income_rows" :key="idx"
                  :class="idx % 2 === 0 ? 'bg-white' : 'bg-green-50/30'">
                  <td class="px-3 py-1.5">
                    <select v-model="row.source"
                      class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                      <option value="">— छान्नुहोस् —</option>
                      <option v-for="opt in (surveyOptions.income_source ?? [])" :key="opt.value" :value="opt.value">
                        {{ opt.label_ne ?? opt.label }}
                      </option>
                    </select>
                  </td>
                  <td class="px-3 py-1.5">
                    <input v-model="row.annual_amount" type="number" min="0" placeholder="0"
                      class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
                  </td>
                  <td class="px-3 py-1.5 text-center">
                    <button type="button" @click="removeIncomeRow(idx)" class="text-red-400 hover:text-red-600 transition-colors">
                      <i class="fa-solid fa-times text-xs"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Section B: खर्च विवरण -->
      <div class="bg-rose-50/50">
        <div class="flex items-center justify-between px-5 py-2.5 border-b border-rose-100 bg-rose-100/60">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-receipt text-rose-600 text-xs"></i>
            <h4 class="text-[12px] font-bold text-rose-800 uppercase tracking-wider">खर्च विवरण <span class="font-normal normal-case text-rose-600">— Expense Details</span></h4>
          </div>
          <button type="button" @click="addExpenseRow"
            class="flex items-center gap-1.5 px-3 py-1 rounded border border-dashed border-rose-400 text-rose-700 text-[11px] font-semibold hover:bg-rose-50 transition-colors">
            <i class="fa-solid fa-plus text-xs"></i> खर्च थप्नुहोस्
          </button>
        </div>
        <div class="p-3">
          <div v-if="!form.economic.expense_rows.length"
            class="text-center text-[12px] text-gray-400 py-4 border border-dashed border-gray-200 rounded">
            खर्च शिर्षक थप्न माथिको बटन थिच्नुहोस्
          </div>
          <div v-else class="overflow-x-auto rounded border border-rose-200">
            <table class="w-full text-[12px]">
              <thead>
                <tr class="bg-rose-100/80 text-rose-800">
                  <th class="text-left font-semibold px-3 py-2 text-[11px] uppercase">खर्च शिर्षक<br><span class="font-normal normal-case opacity-70">Expense Category</span></th>
                  <th class="text-left font-semibold px-3 py-2 text-[11px] uppercase min-w-[140px]">बार्षिक खर्च (रु)<br><span class="font-normal normal-case opacity-70">Annual Expense</span></th>
                  <th class="w-8 px-3 py-2"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-rose-50">
                <tr v-for="(row, idx) in form.economic.expense_rows" :key="idx"
                  :class="idx % 2 === 0 ? 'bg-white' : 'bg-rose-50/30'">
                  <td class="px-3 py-1.5">
                    <select v-model="row.category"
                      class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]">
                      <option value="">— छान्नुहोस् —</option>
                      <option v-for="opt in (surveyOptions.expense_category ?? [])" :key="opt.value" :value="opt.value">
                        {{ opt.label_ne ?? opt.label }}
                      </option>
                    </select>
                  </td>
                  <td class="px-3 py-1.5">
                    <input v-model="row.annual_amount" type="number" min="0" placeholder="0"
                      class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-[#206bc4]/20 focus:border-[#206bc4]" />
                  </td>
                  <td class="px-3 py-1.5 text-center">
                    <button type="button" @click="removeExpenseRow(idx)" class="text-red-400 hover:text-red-600 transition-colors">
                      <i class="fa-solid fa-times text-xs"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 13: प्राकृतिक प्रकोप
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 13" class="space-y-3">

      <!-- ── Section A: मृत्यु विवरण ── -->
      <div class="rounded-md border border-[#e6e7e9] overflow-hidden">
        <div class="bg-slate-700 px-5 py-3 flex items-center gap-2">
          <i class="fa-solid fa-cross text-white/70 text-sm"></i>
          <div>
            <h3 class="text-[14px] font-semibold text-white">मृत्यु विवरण</h3>
            <p class="text-[11px] text-white/50">Death Record</p>
          </div>
        </div>
        <div class="p-4 space-y-3">
          <!-- Toggle -->
          <div class="flex items-center justify-between px-4 py-3 bg-slate-50 rounded border border-slate-200">
            <div>
              <p class="text-[13px] font-semibold text-slate-800">यस १ बर्ष भित्रमा यस परिवारमा कुनै सदस्यको मृत्यु भयो ?</p>
              <p class="text-[11px] text-slate-500 mt-0.5">Is there any death of family members during this year?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-slate-300 shrink-0 ml-4">
              <button type="button" @click="form.disaster.has_death_this_year = 'yes'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.disaster.has_death_this_year === 'yes' ? 'bg-slate-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
              <button type="button" @click="form.disaster.has_death_this_year = 'no'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-slate-300', form.disaster.has_death_this_year === 'no' ? 'bg-slate-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
            </div>
          </div>

          <!-- Death rows table -->
          <template v-if="form.disaster.has_death_this_year === 'yes'">
            <div class="overflow-x-auto rounded border border-slate-200">
              <table class="w-full text-[12px]">
                <thead>
                  <tr class="bg-slate-100 border-b border-slate-200">
                    <th class="px-3 py-2 text-left font-semibold text-slate-700 w-8">#</th>
                    <th class="px-3 py-2 text-left font-semibold text-slate-700">नाम (Name)</th>
                    <th class="px-3 py-2 text-left font-semibold text-slate-700 w-32">लिङ्ग (Gender)</th>
                    <th class="px-3 py-2 text-left font-semibold text-slate-700 w-24">उमेर (Age)</th>
                    <th class="px-3 py-2 text-left font-semibold text-slate-700 w-48">मृत्युको कारण (Cause)</th>
                    <th class="px-2 py-2 w-8"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in form.disaster.death_rows" :key="idx"
                    class="border-b border-slate-100 last:border-0 hover:bg-slate-50">
                    <td class="px-3 py-1.5 text-slate-400 font-medium">{{ idx + 1 }}</td>
                    <td class="px-2 py-1.5">
                      <input v-model="row.name" type="text" placeholder="नाम लेख्नुहोस्"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-slate-300 focus:border-slate-400" />
                    </td>
                    <td class="px-2 py-1.5">
                      <select v-model="row.gender"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-slate-300 focus:border-slate-400">
                        <option value="">छान्नुहोस्</option>
                        <option value="male">पुरुष</option>
                        <option value="female">महिला</option>
                        <option value="other">अन्य</option>
                      </select>
                    </td>
                    <td class="px-2 py-1.5">
                      <input v-model="row.age" type="number" min="0" placeholder="0"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-slate-300 focus:border-slate-400" />
                    </td>
                    <td class="px-2 py-1.5">
                      <select v-model="row.cause"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-slate-300 focus:border-slate-400">
                        <option value="">छान्नुहोस्</option>
                        <option v-for="o in deathCauseOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                      </select>
                    </td>
                    <td class="px-2 py-1.5 text-center">
                      <button type="button" @click="removeDeathRow(idx)"
                        class="w-6 h-6 rounded bg-red-100 text-red-500 hover:bg-red-200 transition-colors flex items-center justify-center mx-auto">
                        <i class="fa-solid fa-xmark text-[10px]"></i>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="form.disaster.death_rows.length === 0">
                    <td colspan="6" class="px-3 py-3 text-center text-[12px] text-slate-400 italic">कुनै रेकर्ड छैन</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button type="button" @click="addDeathRow"
              class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white text-[12px] font-medium rounded transition-colors">
              <i class="fa-solid fa-plus text-[10px]"></i> थप्नुहोस्
            </button>
          </template>
        </div>
      </div>

      <!-- ── Section B: जोखिम प्रकार ── -->
      <div class="rounded-md border border-red-200 overflow-hidden">
        <div class="bg-red-600 px-5 py-3 flex items-center gap-2">
          <i class="fa-solid fa-triangle-exclamation text-white/80 text-sm"></i>
          <div>
            <h3 class="text-[14px] font-semibold text-white">जोखिम प्रकार</h3>
            <p class="text-[11px] text-white/50">Risk Types</p>
          </div>
        </div>
        <div class="p-4 space-y-3">
          <!-- Risk types multi-select -->
          <div>
            <p class="text-[12px] font-semibold text-red-800 mb-2">तपाइँको घर-परिवार कस्तो प्रकारको जोखिममा छ? <span class="font-normal text-red-500">(बहु छनोट)</span></p>
            <p class="text-[11px] text-red-400 mb-2">What type of risk does this family have?</p>
            <div class="grid grid-cols-3 gap-2">
              <label v-for="risk in riskTypeOptions" :key="risk.value"
                class="flex items-center gap-2 px-3 py-2 rounded border cursor-pointer transition-colors text-[13px]"
                :class="form.disaster.risk_types.includes(risk.value)
                  ? 'border-red-400 bg-red-50 text-red-700 font-medium'
                  : 'border-[#e6e7e9] hover:border-red-300 text-gray-700 bg-white'">
                <input type="checkbox" :value="risk.value" v-model="form.disaster.risk_types" class="rounded text-red-600" />
                {{ risk.label }}
              </label>
            </div>
          </div>

          <!-- Risk reduction plan toggle -->
          <div class="flex items-center justify-between px-4 py-3 bg-red-50 rounded border border-red-200 mt-2">
            <div>
              <p class="text-[13px] font-semibold text-red-800">जोखिम न्युनिकरणका लागि पारिवारिक योजना बनेको छ?</p>
              <p class="text-[11px] text-red-400 mt-0.5">Is there a preparedness plan for risk reduction?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-red-300 shrink-0 ml-4">
              <button type="button" @click="form.disaster.risk_reduction_plan = 'yes'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.disaster.risk_reduction_plan === 'yes' ? 'bg-red-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
              <button type="button" @click="form.disaster.risk_reduction_plan = 'no'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-red-300', form.disaster.risk_reduction_plan === 'no' ? 'bg-red-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Section C: विगत ३ वर्षको प्रकोप क्षति ── -->
      <div class="rounded-md border border-orange-200 overflow-hidden">
        <div class="bg-orange-500 px-5 py-3 flex items-center gap-2">
          <i class="fa-solid fa-house-crack text-white/80 text-sm"></i>
          <div>
            <h3 class="text-[14px] font-semibold text-white">प्रकोप क्षति विवरण</h3>
            <p class="text-[11px] text-white/50">Disaster Loss Details</p>
          </div>
        </div>
        <div class="p-4 space-y-4">
          <!-- Toggle: affected last 3 years -->
          <div class="flex items-center justify-between px-4 py-3 bg-orange-50 rounded border border-orange-200">
            <div>
              <p class="text-[13px] font-semibold text-orange-800">गत ३ वर्षमा कुनै प्राकृतिक प्रकोपबाट पिडित हुनुहुन्छ ?</p>
              <p class="text-[11px] text-orange-500 mt-0.5">Has this family been affected by natural disasters in the last 3 years?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-orange-300 shrink-0 ml-4">
              <button type="button" @click="form.disaster.affected_last_3years = 'yes'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.disaster.affected_last_3years === 'yes' ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
              <button type="button" @click="form.disaster.affected_last_3years = 'no'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-orange-300', form.disaster.affected_last_3years === 'no' ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
            </div>
          </div>

          <template v-if="form.disaster.affected_last_3years === 'yes'">
            <!-- Human Loss table -->
            <div>
              <div class="flex items-center gap-2 mb-2">
                <span class="w-2 h-2 rounded-full bg-orange-500 shrink-0"></span>
                <p class="text-[12px] font-bold text-orange-800 uppercase tracking-wide">मानवीय क्षति (Humanitarian Loss)</p>
              </div>
              <div class="overflow-x-auto rounded border border-orange-200">
                <table class="w-full text-[12px]">
                  <thead>
                    <tr class="bg-orange-50 border-b border-orange-200">
                      <th class="px-3 py-2 text-left font-semibold text-orange-800 w-8">#</th>
                      <th class="px-3 py-2 text-left font-semibold text-orange-800">प्रकोपको किसिम (Disaster Type)</th>
                      <th class="px-3 py-2 text-left font-semibold text-orange-800 w-32">मृत्यु संख्या (Deaths)</th>
                      <th class="px-3 py-2 text-left font-semibold text-orange-800 w-32">घाइते संख्या (Injured)</th>
                      <th class="px-2 py-2 w-8"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, idx) in form.disaster.human_loss_rows" :key="idx"
                      class="border-b border-orange-100 last:border-0 hover:bg-orange-50/50">
                      <td class="px-3 py-1.5 text-orange-400 font-medium">{{ idx + 1 }}</td>
                      <td class="px-2 py-1.5">
                        <select v-model="row.disaster_type"
                          class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-orange-200 focus:border-orange-400">
                          <option value="">छान्नुहोस्</option>
                          <option v-for="o in riskTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                      </td>
                      <td class="px-2 py-1.5">
                        <input v-model="row.deaths" type="number" min="0" placeholder="0"
                          class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-orange-200 focus:border-orange-400" />
                      </td>
                      <td class="px-2 py-1.5">
                        <input v-model="row.injured" type="number" min="0" placeholder="0"
                          class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-orange-200 focus:border-orange-400" />
                      </td>
                      <td class="px-2 py-1.5 text-center">
                        <button type="button" @click="removeHumanLossRow(idx)"
                          class="w-6 h-6 rounded bg-red-100 text-red-500 hover:bg-red-200 transition-colors flex items-center justify-center mx-auto">
                          <i class="fa-solid fa-xmark text-[10px]"></i>
                        </button>
                      </td>
                    </tr>
                    <tr v-if="form.disaster.human_loss_rows.length === 0">
                      <td colspan="5" class="px-3 py-3 text-center text-[12px] text-orange-400 italic">कुनै रेकर्ड छैन</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <button type="button" @click="addHumanLossRow"
                class="mt-2 flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-[12px] font-medium rounded transition-colors">
                <i class="fa-solid fa-plus text-[10px]"></i> थप्नुहोस्
              </button>
            </div>

            <!-- Physical Loss table -->
            <div>
              <div class="flex items-center gap-2 mb-2">
                <span class="w-2 h-2 rounded-full bg-red-500 shrink-0"></span>
                <p class="text-[12px] font-bold text-red-800 uppercase tracking-wide">भौतिक क्षति (Physical Property Loss)</p>
              </div>
              <div class="overflow-x-auto rounded border border-red-200">
                <table class="w-full text-[12px]">
                  <thead>
                    <tr class="bg-red-50 border-b border-red-200">
                      <th class="px-3 py-2 text-left font-semibold text-red-800 w-8">#</th>
                      <th class="px-3 py-2 text-left font-semibold text-red-800">प्रकोपको किसिम (Disaster Type)</th>
                      <th class="px-3 py-2 text-left font-semibold text-red-800">भौतिक क्षतिको किसिम (Lost Property)</th>
                      <th class="px-3 py-2 text-left font-semibold text-red-800 w-40">क्षतिको अनुमानित रकम (Amount)</th>
                      <th class="px-2 py-2 w-8"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, idx) in form.disaster.physical_loss_rows" :key="idx"
                      class="border-b border-red-100 last:border-0 hover:bg-red-50/50">
                      <td class="px-3 py-1.5 text-red-400 font-medium">{{ idx + 1 }}</td>
                      <td class="px-2 py-1.5">
                        <select v-model="row.disaster_type"
                          class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-red-200 focus:border-red-400">
                          <option value="">छान्नुहोस्</option>
                          <option v-for="o in riskTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                      </td>
                      <td class="px-2 py-1.5">
                        <select v-model="row.property_type"
                          class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-red-200 focus:border-red-400">
                          <option value="">छान्नुहोस्</option>
                          <option v-for="o in disasterPropertyOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                      </td>
                      <td class="px-2 py-1.5">
                        <input v-model="row.amount" type="number" min="0" placeholder="रु. 0"
                          class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-red-200 focus:border-red-400" />
                      </td>
                      <td class="px-2 py-1.5 text-center">
                        <button type="button" @click="removePhysicalLossRow(idx)"
                          class="w-6 h-6 rounded bg-red-100 text-red-500 hover:bg-red-200 transition-colors flex items-center justify-center mx-auto">
                          <i class="fa-solid fa-xmark text-[10px]"></i>
                        </button>
                      </td>
                    </tr>
                    <tr v-if="form.disaster.physical_loss_rows.length === 0">
                      <td colspan="5" class="px-3 py-3 text-center text-[12px] text-red-400 italic">कुनै रेकर्ड छैन</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <button type="button" @click="addPhysicalLossRow"
                class="mt-2 flex items-center gap-1.5 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-[12px] font-medium rounded transition-colors">
                <i class="fa-solid fa-plus text-[10px]"></i> थप्नुहोस्
              </button>
            </div>
          </template>
        </div>
      </div>

      <!-- ── Section D: राहत सहयोग ── -->
      <div class="rounded-md border border-green-200 overflow-hidden">
        <div class="bg-green-600 px-5 py-3 flex items-center gap-2">
          <i class="fa-solid fa-hand-holding-heart text-white/80 text-sm"></i>
          <div>
            <h3 class="text-[14px] font-semibold text-white">राहत सहयोग</h3>
            <p class="text-[11px] text-white/50">Relief Support</p>
          </div>
        </div>
        <div class="p-4 space-y-3">
          <!-- Toggle: received relief -->
          <div class="flex items-center justify-between px-4 py-3 bg-green-50 rounded border border-green-200">
            <div>
              <p class="text-[13px] font-semibold text-green-800">प्रकोप प्राभावित भएर कुनै राहत पाउनु भएको छ ?</p>
              <p class="text-[11px] text-green-500 mt-0.5">Have you received any relief support after a disaster?</p>
            </div>
            <div class="flex rounded overflow-hidden border border-green-300 shrink-0 ml-4">
              <button type="button" @click="form.disaster.received_relief = 'yes'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors', form.disaster.received_relief === 'yes' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ</button>
              <button type="button" @click="form.disaster.received_relief = 'no'"
                :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-green-300', form.disaster.received_relief === 'no' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन</button>
            </div>
          </div>

          <!-- Relief rows table -->
          <template v-if="form.disaster.received_relief === 'yes'">
            <div class="overflow-x-auto rounded border border-green-200">
              <table class="w-full text-[12px]">
                <thead>
                  <tr class="bg-green-50 border-b border-green-200">
                    <th class="px-3 py-2 text-left font-semibold text-green-800 w-8">#</th>
                    <th class="px-3 py-2 text-left font-semibold text-green-800">राहतको किसिम (Relief Type)</th>
                    <th class="px-3 py-2 text-left font-semibold text-green-800 w-32">एकाइ (Unit)</th>
                    <th class="px-3 py-2 text-left font-semibold text-green-800 w-28">परिमाण (Qty)</th>
                    <th class="px-3 py-2 text-left font-semibold text-green-800">राहतको स्रोत (Source)</th>
                    <th class="px-2 py-2 w-8"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in form.disaster.relief_rows" :key="idx"
                    class="border-b border-green-100 last:border-0 hover:bg-green-50/50">
                    <td class="px-3 py-1.5 text-green-500 font-medium">{{ idx + 1 }}</td>
                    <td class="px-2 py-1.5">
                      <select v-model="row.relief_type"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-green-200 focus:border-green-400">
                        <option value="">छान्नुहोस्</option>
                        <option v-for="o in reliefTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                      </select>
                    </td>
                    <td class="px-2 py-1.5">
                      <select v-model="row.unit"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-green-200 focus:border-green-400">
                        <option value="">छान्नुहोस्</option>
                        <option v-for="o in reliefUnitOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                      </select>
                    </td>
                    <td class="px-2 py-1.5">
                      <input v-model="row.quantity" type="number" min="0" placeholder="0"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-green-200 focus:border-green-400" />
                    </td>
                    <td class="px-2 py-1.5">
                      <select v-model="row.source"
                        class="w-full rounded border border-[#e6e7e9] px-2 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-green-200 focus:border-green-400">
                        <option value="">छान्नुहोस्</option>
                        <option v-for="o in reliefSourceOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                      </select>
                    </td>
                    <td class="px-2 py-1.5 text-center">
                      <button type="button" @click="removeReliefRow(idx)"
                        class="w-6 h-6 rounded bg-red-100 text-red-500 hover:bg-red-200 transition-colors flex items-center justify-center mx-auto">
                        <i class="fa-solid fa-xmark text-[10px]"></i>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="form.disaster.relief_rows.length === 0">
                    <td colspan="6" class="px-3 py-3 text-center text-[12px] text-green-500 italic">कुनै रेकर्ड छैन</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button type="button" @click="addReliefRow"
              class="flex items-center gap-1.5 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-[12px] font-medium rounded transition-colors">
              <i class="fa-solid fa-plus text-[10px]"></i> थप्नुहोस्
            </button>
          </template>
        </div>
      </div>

    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 14: परिवार सदस्य
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 14" class="space-y-3">

      <!-- ── Header bar ── -->
      <div class="rounded-md border border-[#e6e7e9] overflow-hidden">
        <div class="bg-indigo-600 px-5 py-3 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-people-group text-white/80 text-sm"></i>
            <h3 class="text-[14px] font-semibold text-white tracking-wide">परिवार सदस्यहरू</h3>
          </div>
          <span class="bg-white/20 text-white text-xs font-bold px-2.5 py-1 rounded-full">{{ form.members.length }} / {{ totalMembersFromStep2 }} सदस्य</span>
        </div>

        <!-- ── Alert: member count from Step 2 ── -->
        <div v-if="totalMembersFromStep2 > 0"
          :class="form.members.length === totalMembersFromStep2
            ? 'bg-green-50 border-green-200'
            : form.members.length > totalMembersFromStep2
              ? 'bg-red-50 border-red-200'
              : 'bg-amber-50 border-amber-200'"
          class="px-5 py-3 border-b flex items-start gap-3">
          <i :class="form.members.length === totalMembersFromStep2
              ? 'fa-solid fa-circle-check text-green-500'
              : form.members.length > totalMembersFromStep2
                ? 'fa-solid fa-circle-xmark text-red-500'
                : 'fa-solid fa-circle-info text-amber-500'"
            class="text-sm mt-0.5 shrink-0"></i>
          <div>
            <p :class="form.members.length === totalMembersFromStep2
                ? 'text-green-800'
                : form.members.length > totalMembersFromStep2
                  ? 'text-red-800'
                  : 'text-amber-800'"
              class="text-[12px] font-semibold">
              यस परिवारमा <strong>{{ totalMembersFromStep2 }}</strong> सदस्यहरु छन् — यहाँ ती सदस्यहरुको विवरण भर्नु होस्।
            </p>
            <p :class="form.members.length === totalMembersFromStep2
                ? 'text-green-600'
                : form.members.length > totalMembersFromStep2
                  ? 'text-red-600'
                  : 'text-amber-600'"
              class="text-[11px] mt-0.5">
              <template v-if="form.members.length === totalMembersFromStep2">
                This family has {{ totalMembersFromStep2 }} member(s). All members have been filled in.
              </template>
              <template v-else-if="form.members.length > totalMembersFromStep2">
                Warning: {{ form.members.length }} members added but Step 2 shows {{ totalMembersFromStep2 }}. Please correct.
              </template>
              <template v-else>
                This family has {{ totalMembersFromStep2 }} member(s). Please fill in details for all members ({{ totalMembersFromStep2 - form.members.length }} remaining).
              </template>
            </p>
          </div>
        </div>
        <div v-else class="bg-blue-50 border-b border-blue-100 px-5 py-2.5">
          <p class="text-[12px] text-blue-700"><i class="fa-solid fa-circle-info mr-1"></i> कृपया पहिले चरण २ मा पुरुष/महिला/अन्यको सङ्ख्या भर्नुहोस्।</p>
        </div>
      </div>

      <!-- ── Accordion member list ── -->
      <div v-for="(member, idx) in form.members" :key="idx"
        class="rounded-md border overflow-hidden shadow-sm transition-all"
        :class="member.is_household_head ? 'border-indigo-300' : 'border-[#e6e7e9]'">

        <!-- ── Collapsed header row (always visible) ── -->
        <div class="flex items-center gap-3 px-4 py-3 bg-white cursor-pointer select-none"
          @click="toggleMemberExpand(idx)">
          <!-- S.N. circle -->
          <span class="w-7 h-7 shrink-0 rounded-full flex items-center justify-center text-[12px] font-bold text-white"
            :class="member.is_household_head ? 'bg-indigo-600' : 'bg-slate-400'">{{ idx + 1 }}</span>

          <!-- Name + head badge -->
          <div class="flex-1 min-w-0">
            <p class="text-[13px] font-semibold text-[#1d273b] truncate">
              {{ member.full_name || ('सदस्य ' + (idx + 1)) }}
              <span v-if="member.is_household_head"
                class="ml-1.5 text-[10px] bg-indigo-100 text-indigo-700 font-bold px-1.5 py-0.5 rounded">घरमुली</span>
            </p>
            <p class="text-[11px] text-gray-400 mt-0.5 truncate">
              <template v-if="member.age">{{ member.age }} वर्ष</template>
              <template v-if="member.gender"> • {{ member.gender === 'male' ? 'पुरुष' : member.gender === 'female' ? 'महिला' : 'अन्य' }}</template>
              <template v-if="member.relation_to_head"> • {{ soLabel('relation_to_head', member.relation_to_head) || member.relation_to_head }}</template>
              <template v-if="member.education_level"> • {{ soLabel('education_level', member.education_level) || member.education_level }}</template>
            </p>
          </div>

          <!-- Quick badges -->
          <div class="hidden sm:flex items-center gap-1.5 shrink-0">
            <span v-if="member.disability_status"
              class="text-[10px] bg-orange-100 text-orange-700 font-semibold px-1.5 py-0.5 rounded">
              <i class="fa-solid fa-wheelchair text-[9px]"></i> अपाङ्गता
            </span>
            <span v-if="member.blood_group"
              class="text-[10px] bg-rose-100 text-rose-700 font-semibold px-1.5 py-0.5 rounded">
              {{ member.blood_group }}
            </span>
            <span v-if="member.has_citizenship"
              class="text-[10px] bg-green-100 text-green-700 font-semibold px-1.5 py-0.5 rounded">नागरिकता</span>
          </div>

          <!-- Action buttons -->
          <div class="flex items-center gap-1 shrink-0" @click.stop>
            <button type="button" @click="openEditMember(idx)"
              class="flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded transition-colors">
              <i class="fa-solid fa-pen text-[9px]"></i> सम्पादन
            </button>
            <button type="button" @click="removeMember(idx)"
              class="flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-red-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors">
              <i class="fa-solid fa-trash text-[9px]"></i>
            </button>
          </div>

          <!-- Expand chevron -->
          <button type="button" @click="toggleMemberExpand(idx)"
            class="shrink-0 w-7 h-7 flex items-center justify-center rounded text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
            <i :class="isMemberExpanded(idx) ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'" class="text-[10px]"></i>
          </button>
        </div>

        <!-- ── Expanded detail panel (read-only summary) ── -->
        <div v-show="isMemberExpanded(idx)"
          class="border-t border-[#e6e7e9] bg-slate-50/40 px-4 py-3">
          <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-1.5 text-[12px]">
            <div v-if="member.father_name" class="flex gap-1.5">
              <span class="text-gray-500 shrink-0">बुवाको नाम:</span>
              <span class="font-semibold text-gray-700">{{ member.father_name }}</span>
            </div>
            <div class="flex gap-1.5">
              <span class="text-gray-500 shrink-0">वैवाहिक:</span>
              <span class="font-semibold text-gray-700">{{ soLabel('marital_status', member.marital_status) || '—' }}</span>
            </div>
            <div class="flex gap-1.5">
              <span class="text-gray-500 shrink-0">पेशा:</span>
              <span class="font-semibold text-gray-700">{{ soLabel('occupation_type', member.occupation_type) || '—' }}</span>
            </div>
            <div class="flex gap-1.5">
              <span class="text-gray-500 shrink-0">बसोबास:</span>
              <span class="font-semibold text-gray-700">{{ soLabel('residence_status', member.residence_status) || '—' }}</span>
            </div>
            <div v-if="member.education_level" class="flex gap-1.5">
              <span class="text-gray-500 shrink-0">शैक्षिक अवस्था:</span>
              <span class="font-semibold text-gray-700">
                {{ getEduStatusOpts(member.education_level).find(o => o.value === member.education_status)?.label?.split('(')[0]?.trim() || '—' }}
              </span>
            </div>
            <div v-if="member.school_name" class="flex gap-1.5">
              <span class="text-gray-500 shrink-0">संस्था:</span>
              <span class="font-semibold text-gray-700 truncate">{{ member.school_name }}</span>
            </div>
            <div v-if="member.disability_status" class="flex gap-1.5">
              <span class="text-gray-500 shrink-0">अपाङ्गता:</span>
              <span class="font-semibold text-orange-600">{{ soLabel('disability_type', member.disability_type) || 'छ' }}</span>
            </div>
            <div v-if="member.disability_card === 'yes'" class="flex gap-1.5">
              <span class="text-gray-500 shrink-0">परिचयपत्र रंग:</span>
              <span class="font-semibold text-gray-700">{{ soLabel('disability_card_color', member.disability_card_color) || '—' }}</span>
            </div>
            <!-- Documents row -->
            <div class="col-span-2 md:col-span-3 flex flex-wrap gap-2 mt-1 pt-1.5 border-t border-slate-200">
              <span class="text-gray-500 text-[11px]">कागजात:</span>
              <span v-if="member.has_citizenship"   class="text-[11px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-semibold">✓ नागरिकता</span>
              <span v-if="member.has_voters_card"   class="text-[11px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-semibold">✓ मतदाता</span>
              <span v-if="member.has_national_id_card" class="text-[11px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-semibold">✓ राष्ट्रिय परिचयपत्र</span>
              <span v-if="member.has_passport"      class="text-[11px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-semibold">✓ राहदानी</span>
              <span v-if="member.age && parseInt(member.age) <= 18 && member.has_birth_certificate" class="text-[11px] bg-teal-100 text-teal-700 px-1.5 py-0.5 rounded font-semibold">✓ जन्म दर्ता</span>
              <span v-if="!member.has_citizenship && !member.has_voters_card && !member.has_national_id_card && !member.has_passport" class="text-[11px] text-gray-400">कुनै कागजात दर्ता छैन</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Empty state ── -->
      <div v-if="form.members.length === 0"
        class="rounded-md border-2 border-dashed border-indigo-100 bg-indigo-50/30 p-8 text-center">
        <i class="fa-solid fa-users text-3xl text-indigo-200 mb-2 block"></i>
        <p class="text-[13px] font-semibold text-indigo-700">अहिलेसम्म कुनै सदस्य थपिएको छैन</p>
        <p class="text-[11px] text-indigo-400 mt-0.5">No members added yet. Click the button below to add the first member.</p>
      </div>

      <!-- ── Add member button ── -->
      <button type="button"
        @click="totalMembersFromStep2 > 0 && form.members.length >= totalMembersFromStep2 ? showCountPrompt = true : openAddMember()"
        class="w-full flex items-center justify-center gap-2 border-2 border-dashed rounded-md py-3 text-[13px] font-semibold transition-colors"
        :class="totalMembersFromStep2 > 0 && form.members.length >= totalMembersFromStep2
          ? 'border-amber-300 text-amber-700 hover:border-amber-500 hover:bg-amber-50 bg-amber-50/40'
          : 'border-indigo-300 text-indigo-600 hover:border-indigo-500 hover:bg-indigo-50'">
        <i class="fa-solid fa-user-plus text-xs"></i>
        <template v-if="totalMembersFromStep2 > 0 && form.members.length >= totalMembersFromStep2">
          <span>नयाँ सदस्य थप्नुहोस्</span>
          <span class="text-[11px] font-normal">(नयाँ जन्म / परिवार परिवर्तन)</span>
          <i class="fa-solid fa-circle-info text-xs ml-1"></i>
        </template>
        <template v-else>
          <span>सदस्य थप्नुहोस् <span class="text-[11px] font-normal">(Add Member)</span></span>
          <span v-if="totalMembersFromStep2 > 0" class="text-[11px] font-normal text-indigo-400">— {{ totalMembersFromStep2 - form.members.length }} बाँकी</span>
        </template>
      </button>

      <!-- ── Count prompt modal — shown when adding beyond Step 2 limit ── -->
      <Teleport to="body">
        <Transition name="modal-fade">
          <div v-if="showCountPrompt"
            class="fixed inset-0 z-[1100] flex items-center justify-center bg-black/50 backdrop-blur-sm px-4"
            @click.self="showCountPrompt = false">
            <div class="w-full max-w-md bg-white rounded-xl shadow-2xl overflow-hidden">
              <div class="bg-amber-500 px-5 py-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-amber-400 flex items-center justify-center flex-shrink-0">
                  <i class="fa-solid fa-users text-white text-sm"></i>
                </div>
                <div>
                  <p class="text-white font-semibold text-[14px]">सदस्य संख्या अद्यावधिक आवश्यक</p>
                  <p class="text-amber-100 text-[11px]">Member count update required</p>
                </div>
              </div>
              <div class="px-5 py-4 space-y-3 text-[13px] text-gray-700">
                <p>
                  चरण २ मा परिवारको सदस्य संख्या
                  <strong class="text-amber-700">{{ totalMembersFromStep2 }} (पु: {{ form.social.male_num || 0 }}, म: {{ form.social.female_num || 0 }}, अ: {{ form.social.other_num || 0 }})</strong>
                  भनी उल्लेख गरिएको छ, र यति नै सदस्यहरूको विवरण पहिले नै भरिसकिएको छ।
                </p>
                <p>
                  नयाँ सदस्य (नयाँ जन्म वा परिवारमा थपिएको सदस्य) थप्नका लागि पहिले
                  <strong>चरण २</strong> मा गई सम्बन्धित लिंगको संख्या बढाउनुहोस्, त्यसपछि यहाँ फर्केर नयाँ सदस्यको विवरण भर्नुहोस्।
                </p>
                <div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-[12px] text-amber-800 flex items-start gap-2">
                  <i class="fa-solid fa-lightbulb text-amber-500 mt-0.5 flex-shrink-0"></i>
                  <span>उदाहरण: नवजात शिशु छोरा भए <strong>पुरुष संख्या +१</strong> गर्नुहोस्। त्यसपछि यहाँ नयाँ सदस्यको रूपमा थप्न सकिन्छ।</span>
                </div>
              </div>
              <div class="px-5 pb-5 flex gap-3">
                <button type="button"
                  @click="goToStep2ForCount()"
                  class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-500 text-white text-[13px] font-semibold rounded-lg hover:bg-amber-600 transition">
                  <i class="fa-solid fa-arrow-left text-xs"></i>
                  चरण २ मा जानुहोस्
                  <span class="text-[11px] font-normal opacity-80">(Update counts)</span>
                </button>
                <button type="button"
                  @click="showCountPrompt = false"
                  class="px-4 py-2.5 border border-gray-300 text-gray-600 text-[13px] font-medium rounded-lg hover:bg-gray-50 transition">
                  रद्द गर्नुहोस्
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>

      <!-- ══════════════════════════════════════════════════════
           MEMBER MODAL — full-screen overlay
      ══════════════════════════════════════════════════════ -->
      <Teleport to="body">
        <Transition name="modal-fade">
          <div v-if="memberModalOpen"
            class="fixed inset-0 z-[999] flex items-start justify-center bg-black/50 backdrop-blur-sm overflow-y-auto py-4 px-4">
            <div class="w-full max-w-4xl bg-white rounded-lg shadow-2xl flex flex-col my-auto">

              <!-- Modal header -->
              <div class="bg-indigo-600 px-6 py-4 rounded-t-lg flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                  <span class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center text-[13px] font-bold text-white">
                    {{ memberEditIdx !== null ? memberEditIdx + 1 : form.members.length + 1 }}
                  </span>
                  <div>
                    <p class="text-[14px] font-bold text-white">
                      {{ memberEditIdx !== null ? 'सदस्य सम्पादन' : 'नयाँ सदस्य थप्नुहोस्' }}
                    </p>
                    <p class="text-[11px] text-indigo-200">
                      {{ memberEditIdx !== null ? 'Edit Member Details' : 'Add New Family Member' }}
                    </p>
                  </div>
                </div>
                <button type="button" @click="closeMemberModal"
                  class="w-8 h-8 flex items-center justify-center text-white/70 hover:text-white hover:bg-white/20 rounded-full transition-colors">
                  <i class="fa-solid fa-xmark"></i>
                </button>
              </div>

              <!-- Modal body (scrollable) -->
              <div class="p-6 space-y-5 overflow-y-auto max-h-[85vh]">

                <!-- ROW A: Name + Father + Head -->
                <div class="grid grid-cols-2 gap-4">
                  <div class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold text-[#9aa0ac]">सदस्यको नाम <span class="text-red-500">*</span> <span class="text-gray-400">(Members Name)</span></label>
                    <input v-model="memberDraft.full_name" type="text" placeholder="पूरा नाम — Full Name"
                      class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold text-[#9aa0ac]">
                      <span class="text-indigo-600">{{ memberDraft.full_name || 'सदस्य' }}</span> को बुवाको नाम
                      <span class="text-gray-400">(Father's Name)</span>
                    </label>
                    <input v-model="memberDraft.father_name" type="text" placeholder="बुवाको नाम"
                      class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400" />
                  </div>
                </div>

                <!-- घरमुली toggle -->
                <div class="flex items-center gap-4 px-4 py-2.5 bg-indigo-50 rounded border border-indigo-100">
                  <span class="text-[12px] font-semibold text-indigo-800 flex-1">घरमुली हो? <span class="font-normal text-indigo-500">(Is Household Head?)</span></span>
                  <div class="flex rounded border border-indigo-200 overflow-hidden">
                    <button type="button" @click="memberDraft.is_household_head = true"
                      :class="['px-4 py-1.5 text-xs font-semibold transition-colors', memberDraft.is_household_head ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-indigo-50']">हो (Yes)</button>
                    <button type="button" @click="memberDraft.is_household_head = false"
                      :class="['px-4 py-1.5 text-xs font-semibold transition-colors border-l border-indigo-200', !memberDraft.is_household_head ? 'bg-slate-500 text-white' : 'bg-white text-gray-600 hover:bg-slate-50']">होइन (No)</button>
                  </div>
                </div>

                <!-- ROW B: Age + Relation + Gender -->
                <div class="grid grid-cols-3 gap-4">
                  <div class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold text-[#9aa0ac]">
                      <span class="text-indigo-600">{{ memberDraft.full_name || 'सदस्य' }}</span> को उमेर <span class="text-gray-400">(Age)</span>
                    </label>
                    <input v-model="memberDraft.age" type="number" min="0" max="120" placeholder="0"
                      class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold text-[#9aa0ac]">
                      <span class="text-indigo-600">{{ memberDraft.full_name || 'सदस्य' }}</span> को घरमुलीको के पर्छ?
                    </label>
                    <select v-model="memberDraft.relation_to_head"
                      class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                      <option value="">-- छान्नुहोस् --</option>
                      <option v-for="opt in (surveyOptions.relation_to_head ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold text-[#9aa0ac]">
                      <span class="text-indigo-600">{{ memberDraft.full_name || 'सदस्य' }}</span> को लिङ्ग <span class="text-gray-400">(Gender)</span>
                    </label>
                    <select v-model="memberDraft.gender"
                      class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                      <option value="">-- छान्नुहोस् --</option>
                      <option value="male">पुरुष (Male)</option>
                      <option value="female">महिला (Female)</option>
                      <option value="other">अन्य (Other)</option>
                    </select>
                  </div>
                </div>

                <!-- ROW C: Blood Group + Birth Certificate -->
                <div class="grid grid-cols-3 gap-4">
                  <div class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold text-[#9aa0ac]">
                      <span class="text-indigo-600">{{ memberDraft.full_name || 'सदस्य' }}</span> ले रक्त समूह परिक्षण गरेको छ?
                    </label>
                    <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                      <button type="button" @click="memberDraft.blood_group_tested = 'yes'"
                        :class="['flex-1 px-3 py-2 text-xs font-semibold transition-colors', memberDraft.blood_group_tested === 'yes' ? 'bg-rose-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ (Yes)</button>
                      <button type="button" @click="memberDraft.blood_group_tested = 'no'; memberDraft.blood_group = ''"
                        :class="['flex-1 px-3 py-2 text-xs font-semibold transition-colors border-l', memberDraft.blood_group_tested === 'no' ? 'bg-slate-400 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन (No)</button>
                    </div>
                  </div>
                  <div v-if="memberDraft.blood_group_tested === 'yes'" class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold text-[#9aa0ac]">
                      <span class="text-indigo-600">{{ memberDraft.full_name || 'सदस्य' }}</span> को रक्त समूह <span class="text-gray-400">(Blood Group)</span>
                    </label>
                    <select v-model="memberDraft.blood_group"
                      class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-rose-200 focus:border-rose-400">
                      <option value="">-- छान्नुहोस् --</option>
                      <option v-for="opt in (surveyOptions.blood_group ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                  </div>
                  <div v-if="memberDraft.age && parseInt(memberDraft.age) <= 18" class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold text-[#9aa0ac]">
                      <span class="text-indigo-600">{{ memberDraft.full_name || 'सदस्य' }}</span> को जन्म दर्ता गरेको छ?
                    </label>
                    <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                      <button type="button" @click="memberDraft.has_birth_certificate = true"
                        :class="['flex-1 px-3 py-2 text-xs font-semibold transition-colors', memberDraft.has_birth_certificate ? 'bg-teal-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ (Yes)</button>
                      <button type="button" @click="memberDraft.has_birth_certificate = false"
                        :class="['flex-1 px-3 py-2 text-xs font-semibold transition-colors border-l', !memberDraft.has_birth_certificate ? 'bg-slate-400 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन (No)</button>
                    </div>
                  </div>
                </div>

                <!-- ══ AGE 0–3: शिशु स्वास्थ्य तथा विकास ══════════════════════════ -->
                <div v-if="memberDraft.age !== '' && parseInt(memberDraft.age) < 4"
                  class="rounded border border-sky-100 bg-sky-50/40 p-3 space-y-3">
                  <p class="text-[11px] font-bold text-sky-700 uppercase tracking-wide flex items-center gap-1">
                    <i class="fa-solid fa-baby text-xs"></i>
                    शिशु स्वास्थ्य (उमेर ०–३) — Infant Health (Age 0–3)
                  </p>
                  <div class="grid grid-cols-3 gap-3">
                    <!-- Place of birth -->
                    <div class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">जन्मिएको ठाउँ <span class="text-gray-400">(Birth Place)</span></label>
                      <select v-model="memberDraft.childbornplace"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-sky-200 focus:border-sky-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.childbornplace ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                    <!-- Birth weight -->
                    <div class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">जन्मको तौल (केजी) <span class="text-gray-400">(Birth Weight kg)</span></label>
                      <input v-model="memberDraft.child_weight_in_birth" type="number" step="0.01" min="0" max="10" placeholder="2.50"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-sky-200 focus:border-sky-400" />
                    </div>
                    <!-- Caregiver -->
                    <div class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">बच्चाको मुख्य हेरचाह गर्ने <span class="text-gray-400">(Primary Caregiver)</span></label>
                      <select v-model="memberDraft.child_caregiver"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-sky-200 focus:border-sky-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.caregiver ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                    <!-- Breastfeeding duration -->
                    <div class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">स्तनपान गराएको अवधि (महिना) <span class="text-gray-400">(Breastfeeding months)</span></label>
                      <input v-model="memberDraft.durationbrestfeed" type="number" min="0" max="36" placeholder="महिना"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-sky-200 focus:border-sky-400" />
                    </div>
                  </div>
                  <!-- Yes/No flags -->
                  <div class="grid grid-cols-2 gap-2">
                    <template v-for="flag in [
                      { key: 'colostrum',                 ne: 'ढाडको दूध खुवाएको छ?',        en: 'Colostrum fed?' },
                      { key: 'fullmilkcourse',            ne: 'पूर्ण स्तनपान गराइएको?',       en: 'Full breastfeeding course?' },
                      { key: 'vit_a_dewormin',            ne: 'भिटामिन A / डिवर्मिङ दिएको?', en: 'Vitamin A & Deworming?' },
                      { key: 'childhealthcheckup',        ne: 'स्वास्थ्य जाँच गरिएको?',      en: 'Health checkup done?' },
                      { key: 'childbmi',                  ne: 'पोषण/तौल अनुगमन गरिएको?',    en: 'BMI/Nutrition monitored?' },
                      { key: 'diarrhea_before_two_weeks', ne: 'पछिल्लो २ हप्तामा झाडापखाला?', en: 'Diarrhoea last 2 weeks?' },
                      { key: 'fever_resp',                ne: 'ज्वरो/श्वासप्रश्वास समस्या?',  en: 'Fever/respiratory issues?' },
                    ]" :key="flag.key">
                      <div class="flex items-center justify-between gap-2 px-3 py-2 bg-white rounded border border-[#e6e7e9]">
                        <span class="text-[11px] text-gray-600">{{ flag.ne }} <span class="text-gray-400 text-[10px]">({{ flag.en }})</span></span>
                        <div class="flex rounded border border-[#e6e7e9] overflow-hidden shrink-0">
                          <button type="button" @click="memberDraft[flag.key] = true"
                            :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft[flag.key] === true ? 'bg-sky-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">हो</button>
                          <button type="button" @click="memberDraft[flag.key] = false"
                            :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', memberDraft[flag.key] === false ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">होइन</button>
                        </div>
                      </div>
                    </template>
                  </div>
                  <!-- Vaccines multi-select -->
                  <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold text-[#9aa0ac]">लगाइएका खोपहरू <span class="text-gray-400">(Vaccines received — multi-select)</span></label>
                    <div class="grid grid-cols-4 gap-1.5">
                      <label v-for="opt in (surveyOptions.childvaccine ?? [])" :key="opt.value"
                        class="flex items-center gap-1.5 px-2.5 py-1.5 rounded border cursor-pointer transition-colors text-[11px]"
                        :class="(memberDraft.childvaccine ?? []).includes(opt.value) ? 'border-sky-400 bg-sky-50' : 'border-[#e6e7e9] bg-white hover:bg-gray-50'">
                        <input type="checkbox" :value="opt.value" v-model="memberDraft.childvaccine" class="accent-sky-500 w-3 h-3" />
                        {{ opt.label }}
                      </label>
                    </div>
                  </div>
                </div>

                <!-- ══ AGE 4–5: बाल विकास ══════════════════════════════════════ -->
                <div v-if="memberDraft.age !== '' && parseInt(memberDraft.age) >= 4 && parseInt(memberDraft.age) <= 5"
                  class="rounded border border-emerald-100 bg-emerald-50/40 p-3 space-y-3">
                  <p class="text-[11px] font-bold text-emerald-700 uppercase tracking-wide flex items-center gap-1">
                    <i class="fa-solid fa-child text-xs"></i>
                    बाल विकास (उमेर ४–५) — Child Development (Age 4–5)
                  </p>
                  <!-- Vitamin A toggle — full-width row so buttons never clip -->
                  <div class="flex items-center justify-between gap-3 px-3 py-2 bg-white rounded border border-[#e6e7e9]">
                    <span class="text-[12px] text-gray-600">भिटामिन A दिएको छ? <span class="text-gray-400 text-[10px]">(Vitamin A?)</span></span>
                    <div class="flex rounded border border-[#e6e7e9] overflow-hidden shrink-0">
                      <button type="button" @click="memberDraft.vitaminA = true"
                        :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.vitaminA === true ? 'bg-emerald-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">हो</button>
                      <button type="button" @click="memberDraft.vitaminA = false"
                        :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', memberDraft.vitaminA === false ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">होइन</button>
                    </div>
                  </div>
                  <!-- 3-column dropdowns -->
                  <div class="grid grid-cols-3 gap-3">
                    <!-- Physical development -->
                    <div class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">शारीरिक विकास <span class="text-gray-400">(Physical Dev.)</span></label>
                      <select v-model="memberDraft.childphydev"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.childphydev ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                    <!-- Language development -->
                    <div class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">भाषा विकास <span class="text-gray-400">(Language Dev.)</span></label>
                      <select v-model="memberDraft.childlgdev"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.childlgdev ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                    <!-- Socialization -->
                    <div class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">सामाजिकीकरण <span class="text-gray-400">(Socialization)</span></label>
                      <select v-model="memberDraft.childsocialization"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.childsocialization ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                  </div>
                </div>

                <!-- ══ AGE 6–15: बाल विकास तथा शैक्षिक अवस्था ══════════════ -->
                <div v-if="memberDraft.age !== '' && parseInt(memberDraft.age) >= 6 && parseInt(memberDraft.age) <= 15"
                  class="rounded border border-violet-100 bg-violet-50/30 p-3 space-y-3">
                  <p class="text-[11px] font-bold text-violet-700 uppercase tracking-wide flex items-center gap-1">
                    <i class="fa-solid fa-person-chalkboard text-xs"></i>
                    बाल विकास तथा शैक्षिक अवस्था (उमेर ६–१५) — Child Development (Age 6–15)
                  </p>
                  <!-- Row 1: boolean toggles — 2 per row so label+buttons always fit -->
                  <div class="grid grid-cols-2 gap-3">
                    <!-- Physical dev normal? -->
                    <div class="flex items-center justify-between gap-3 px-3 py-2 bg-white rounded border border-[#e6e7e9]">
                      <span class="text-[11px] text-gray-600">शारीरिक विकास सामान्य छ? <span class="text-gray-400 text-[10px]">(Phys. Dev. normal?)</span></span>
                      <div class="flex rounded border border-[#e6e7e9] overflow-hidden shrink-0">
                        <button type="button" @click="memberDraft.phy_dev_6_15 = true"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.phy_dev_6_15 === true ? 'bg-violet-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">हो</button>
                        <button type="button" @click="memberDraft.phy_dev_6_15 = false"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', memberDraft.phy_dev_6_15 === false ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">होइन</button>
                      </div>
                    </div>
                    <!-- Allergy/Infection -->
                    <div class="flex items-center justify-between gap-3 px-3 py-2 bg-white rounded border border-[#e6e7e9]">
                      <span class="text-[11px] text-gray-600">एलर्जी/संक्रमण छ? <span class="text-gray-400 text-[10px]">(Allergy/Infection?)</span></span>
                      <div class="flex rounded border border-[#e6e7e9] overflow-hidden shrink-0">
                        <button type="button" @click="memberDraft.allergy_infection_6_15 = true"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.allergy_infection_6_15 === true ? 'bg-violet-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">हो</button>
                        <button type="button" @click="memberDraft.allergy_infection_6_15 = false"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', memberDraft.allergy_infection_6_15 === false ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">होइन</button>
                      </div>
                    </div>
                    <!-- Screen/mobile toggle -->
                    <div class="flex items-center justify-between gap-3 px-3 py-2 bg-white rounded border border-[#e6e7e9]">
                      <span class="text-[11px] text-gray-600">मोबाइल/टिभि/ट्याब हेर्छन्? <span class="text-gray-400 text-[10px]">(Uses screen/mobile?)</span></span>
                      <div class="flex rounded border border-[#e6e7e9] overflow-hidden shrink-0">
                        <button type="button" @click="memberDraft.watch_mobile_tv_6_15 = true"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.watch_mobile_tv_6_15 === true ? 'bg-violet-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">हो</button>
                        <button type="button" @click="memberDraft.watch_mobile_tv_6_15 = false; memberDraft.digital_habit_6_15 = ''"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', memberDraft.watch_mobile_tv_6_15 === false ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">होइन</button>
                      </div>
                    </div>
                    <!-- Digital habit (when screen=yes) -->
                    <div v-if="memberDraft.watch_mobile_tv_6_15 === true" class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">दैनिक स्क्रिन समय <span class="text-gray-400">(Daily Screen Time)</span></label>
                      <select v-model="memberDraft.digital_habit_6_15"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-violet-200 focus:border-violet-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.digital_habit ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                  </div>
                  <!-- Row 2: dropdowns — 3 per row -->
                  <div class="grid grid-cols-3 gap-3">
                    <!-- Physical dev detail -->
                    <div class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">शारीरिक विकास स्तर</label>
                      <select v-model="memberDraft.childphydev_6_15"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-violet-200 focus:border-violet-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.childphydev ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                    <!-- Language dev detail -->
                    <div class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">भाषा विकास स्तर</label>
                      <select v-model="memberDraft.childlgdev_6_15"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-violet-200 focus:border-violet-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.childlgdev ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                    <!-- Other interest -->
                    <div class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">अन्य रुचि <span class="text-gray-400">(Other Interests)</span></label>
                      <select v-model="memberDraft.other_interest_6_15"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-violet-200 focus:border-violet-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.other_interest ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                    <!-- School interest (GoingSchool only) -->
                    <div v-if="memberDraft.education_status === 'GoingSchool'" class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">पढाइमा रुचि <span class="text-gray-400">(School Interest)</span></label>
                      <select v-model="memberDraft.school_interest_6_15"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-violet-200 focus:border-violet-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.schoolinterest ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                    <!-- Interesting subject (GoingSchool only) -->
                    <div v-if="memberDraft.education_status === 'GoingSchool'" class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">रुचाउने विषय <span class="text-gray-400">(Interesting Subject)</span></label>
                      <select v-model="memberDraft.interesting_subject_6_15"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-violet-200 focus:border-violet-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.interestingsubject ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                    <!-- HW support (GoingSchool only) -->
                    <div v-if="memberDraft.education_status === 'GoingSchool'" class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">गृहकार्य सहयोग <span class="text-gray-400">(Homework Support)</span></label>
                      <select v-model="memberDraft.hw_support_6_15"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-violet-200 focus:border-violet-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.hwsupport ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                  </div>
                  <!-- Child Labor (age ≤ 18 + Stopped education) -->
                  <div v-if="memberDraft.education_status === 'Stopped'" class="border-t border-violet-200 pt-2">
                    <div class="flex items-center justify-between gap-2 px-3 py-2 bg-white rounded border border-red-200">
                      <span class="text-[12px] font-semibold text-red-700">
                        <i class="fa-solid fa-triangle-exclamation mr-1 text-xs"></i>
                        बाल श्रम: काममा लगाइएको छ? <span class="text-gray-400 text-[10px]">(Child Labor?)</span>
                      </span>
                      <div class="flex rounded border border-red-200 overflow-hidden">
                        <button type="button" @click="memberDraft.child_labor = true"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.child_labor === true ? 'bg-red-500 text-white' : 'bg-white text-gray-500 hover:bg-red-50']">हो</button>
                        <button type="button" @click="memberDraft.child_labor = false; memberDraft.workplace_child_labor = ''"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', memberDraft.child_labor === false ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-red-50']">होइन</button>
                      </div>
                    </div>
                    <div v-if="memberDraft.child_labor === true" class="mt-2 flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-red-700">काम गर्ने ठाउँ <span class="text-gray-400">(Workplace)</span></label>
                      <input v-model="memberDraft.workplace_child_labor" type="text" placeholder="काम गर्ने ठाउँ उल्लेख गर्नुहोस्..."
                        class="w-full rounded border border-red-200 px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-red-200 focus:border-red-400" />
                    </div>
                  </div>
                </div>

                <!-- ROW D: Disability -->
                <div class="rounded border border-orange-100 bg-orange-50/30 p-3 space-y-3">
                  <p class="text-[11px] font-bold text-orange-700 uppercase tracking-wide flex items-center gap-1">
                    <i class="fa-solid fa-wheelchair text-xs"></i> अपाङ्गता — Disability
                  </p>
                  <div class="grid grid-cols-3 gap-4">
                    <div class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">
                        <span class="text-indigo-600">{{ memberDraft.full_name || 'सदस्य' }}</span> लाई कुनै अपाङ्गता छ?
                      </label>
                      <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                        <button type="button" @click="memberDraft.disability_status = true"
                          :class="['flex-1 px-3 py-2 text-xs font-semibold transition-colors', memberDraft.disability_status ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ (Yes)</button>
                        <button type="button" @click="memberDraft.disability_status = false; memberDraft.disability_type = ''; memberDraft.disability_card = ''; memberDraft.disability_card_color = ''"
                          :class="['flex-1 px-3 py-2 text-xs font-semibold transition-colors border-l', !memberDraft.disability_status ? 'bg-slate-400 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन (No)</button>
                      </div>
                    </div>
                    <template v-if="memberDraft.disability_status">
                      <div class="flex flex-col gap-1">
                        <label class="text-[11px] font-semibold text-[#9aa0ac]">अपाङ्गताको प्रकार <span class="text-gray-400">(Type)</span></label>
                        <select v-model="memberDraft.disability_type"
                          class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-orange-200 focus:border-orange-400">
                          <option value="">-- छान्नुहोस् --</option>
                          <option v-for="opt in (surveyOptions.disability_type ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                      </div>
                      <div class="flex flex-col gap-1">
                        <label class="text-[11px] font-semibold text-[#9aa0ac]">अपाङ्गता परिचयपत्र छ?</label>
                        <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                          <button type="button" @click="memberDraft.disability_card = 'yes'"
                            :class="['flex-1 px-3 py-2 text-xs font-semibold transition-colors', memberDraft.disability_card === 'yes' ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छ (Yes)</button>
                          <button type="button" @click="memberDraft.disability_card = 'no'; memberDraft.disability_card_color = ''"
                            :class="['flex-1 px-3 py-2 text-xs font-semibold transition-colors border-l', memberDraft.disability_card === 'no' ? 'bg-slate-400 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">छैन (No)</button>
                        </div>
                      </div>
                      <div v-if="memberDraft.disability_card === 'yes'" class="flex flex-col gap-1">
                        <label class="text-[11px] font-semibold text-[#9aa0ac]">परिचयपत्र रंग <span class="text-gray-400">(Card Color)</span></label>
                        <select v-model="memberDraft.disability_card_color"
                          class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-orange-200 focus:border-orange-400">
                          <option value="">-- छान्नुहोस् --</option>
                          <option v-for="opt in (surveyOptions.disability_card_color ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                      </div>
                    </template>
                  </div>
                </div>

                <!-- ROW E: Education section -->
                <div class="rounded border border-blue-100 bg-blue-50/30 p-3 space-y-3">
                  <p class="text-[11px] font-bold text-blue-700 uppercase tracking-wide flex items-center gap-1">
                    <i class="fa-solid fa-graduation-cap text-xs"></i> शिक्षा — Education
                  </p>
                  <div class="grid grid-cols-3 gap-4">
                    <!-- Education Level -->
                    <div class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">
                        <span class="text-indigo-600">{{ memberDraft.full_name || 'सदस्य' }}</span> को शैक्षिक योग्यता
                      </label>
                      <select v-model="memberDraft.education_level"
                        @change="memberDraft.education_status = ''; memberDraft.current_class = ''; memberDraft.higher_education_level = ''; memberDraft.cause_not_school = []"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-blue-200 focus:border-blue-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.education_level ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                    <!-- Education Status (filtered by level) -->
                    <div v-if="memberDraft.education_level" class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">वर्तमान शैक्षिक अवस्था</label>
                      <select v-model="memberDraft.education_status"
                        @change="memberDraft.current_class = ''; memberDraft.higher_education_level = ''; memberDraft.cause_not_school = []"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-blue-200 focus:border-blue-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in getEduStatusOpts(memberDraft.education_level)" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                    <!-- Current Class (GoingSchool) -->
                    <div v-if="memberDraft.education_status === 'GoingSchool'" class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">कुन कक्षामा पढ्छन्?</label>
                      <select v-model="memberDraft.current_class"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-blue-200 focus:border-blue-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.child_school_grade ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                    <!-- Higher Education Level (RegularHigherEducation) -->
                    <div v-if="memberDraft.education_status === 'RegularHigherEducation'" class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">उच्च शिक्षाको तह</label>
                      <select v-model="memberDraft.higher_education_level"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-blue-200 focus:border-blue-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option value="Bachelor">स्नातक (Bachelor)</option>
                        <option value="Master">स्नातकोत्तर (Master)</option>
                        <option value="MPhil">एमफिल (MPhil)</option>
                        <option value="PhD">पिएचडी (PhD)</option>
                      </select>
                    </div>
                    <!-- School Type (GoingSchool or RegularHigherEducation) -->
                    <div v-if="memberDraft.education_status === 'GoingSchool' || memberDraft.education_status === 'RegularHigherEducation'" class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">शैक्षिक संस्थाको प्रकार</label>
                      <select v-model="memberDraft.school_type"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-blue-200 focus:border-blue-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.school_type ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                    <!-- School Performance (GoingSchool only) -->
                    <div v-if="memberDraft.education_status === 'GoingSchool'" class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">बिधालयमा पढाइको अवस्था</label>
                      <select v-model="memberDraft.school_performance"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-blue-200 focus:border-blue-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.school_performance ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                    <!-- Institution Location (GoingSchool or RegularHigherEducation) -->
                    <div v-if="memberDraft.education_status === 'GoingSchool' || memberDraft.education_status === 'RegularHigherEducation'" class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">शैक्षिक संस्था कहाँ छ?</label>
                      <select v-model="memberDraft.school_location"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-blue-200 focus:border-blue-400">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="opt in (surveyOptions.institutionlocation ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </div>
                    <!-- Institution Name (GoingSchool or RegularHigherEducation) -->
                    <div v-if="memberDraft.education_status === 'GoingSchool' || memberDraft.education_status === 'RegularHigherEducation'" class="flex flex-col gap-1 col-span-2">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">शैक्षिक संस्थाको नाम <span class="text-gray-400">(Institution Name)</span></label>
                      <input v-model="memberDraft.school_name" type="text" placeholder="संस्थाको नाम"
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-blue-200 focus:border-blue-400" />
                    </div>
                    <!-- Cause Not in School (Stopped or NotAdmitted) — multi-select -->
                    <div v-if="memberDraft.education_status === 'Stopped' || memberDraft.education_status === 'NotAdmitted'" class="flex flex-col gap-1 col-span-3">
                      <label class="text-[11px] font-semibold text-amber-700">
                        <span class="text-indigo-600">{{ memberDraft.full_name || 'सदस्य' }}</span> को शैक्षिक संस्था नजानुको कारण
                        <span class="text-gray-400">(Cause Not in School)</span>
                      </label>
                      <div class="grid grid-cols-4 gap-1.5">
                        <label v-for="opt in (surveyOptions.cause_not_school ?? [])" :key="opt.value"
                          class="flex items-center gap-2 px-2.5 py-1.5 rounded border cursor-pointer transition-colors"
                          :class="(memberDraft.cause_not_school ?? []).includes(opt.value) ? 'border-amber-400 bg-amber-50' : 'border-[#e6e7e9] bg-white hover:bg-gray-50'">
                          <input type="checkbox" :value="opt.value" v-model="memberDraft.cause_not_school" class="accent-amber-500 w-3.5 h-3.5" />
                          <span class="text-[11px]">{{ opt.label }}</span>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- ROW F: Occupation (age > 10) + Marital + Residence -->
                <div class="grid grid-cols-3 gap-4">
                  <!-- Occupation — shown when age > 10 (or age unknown) -->
                  <div v-if="!memberDraft.age || parseInt(memberDraft.age) > 10" class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold text-[#9aa0ac]">पेशाको किसिम <span class="text-gray-400">(Occupation)</span></label>
                    <select v-model="memberDraft.occupation_type"
                      class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                      <option value="">-- छान्नुहोस् --</option>
                      <option v-for="opt in (surveyOptions.occupation_type ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                  </div>
                  <div v-if="!memberDraft.age || parseInt(memberDraft.age) >= 10" class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold text-[#9aa0ac]">वैवाहिक स्थिति <span class="text-gray-400">(Marital Status)</span></label>
                    <select v-model="memberDraft.marital_status"
                      @change="memberDraft.ageofmarriage = ''; memberDraft.no_birth_given = ''"
                      class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                      <option value="">-- छान्नुहोस् --</option>
                      <option v-for="opt in (surveyOptions.marital_status ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                  </div>
                  <!-- Age at marriage (when married — any status except Single) -->
                  <div v-if="(!memberDraft.age || parseInt(memberDraft.age) >= 10) && MARRIED_STATUSES.includes(memberDraft.marital_status)" class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold text-[#9aa0ac]">
                      <span class="text-indigo-600">{{ memberDraft.full_name || 'सदस्य' }}</span> को विवाह हुँदाको उमेर
                      <span class="text-gray-400">(Age at Marriage)</span>
                    </label>
                    <input v-model="memberDraft.ageofmarriage" type="number" min="1" max="99" placeholder="उमेर"
                      class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400" />
                  </div>
                  <!-- Births given (female + married + age ≥ 10) -->
                  <div v-if="(!memberDraft.age || parseInt(memberDraft.age) >= 10) && memberDraft.gender === 'Female' && MARRIED_STATUSES.includes(memberDraft.marital_status)" class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold text-[#9aa0ac]">
                      उहाँले जम्मा कति जना बच्चा जन्माउनु भयो?
                      <span class="text-gray-400">(Total Births Given)</span>
                    </label>
                    <input v-model="memberDraft.no_birth_given" type="number" min="0" max="30" placeholder="संख्या"
                      class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold text-[#9aa0ac]">बसोबासको स्थिति <span class="text-gray-400">(Residence)</span></label>
                    <select v-model="memberDraft.residence_status"
                      class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                      <option value="">-- छान्नुहोस् --</option>
                      <option v-for="opt in (surveyOptions.residence_status ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                  </div>
                </div>

                <!-- ROW G: Documents + Smoking/Drinking (age ≥ 16) -->
                <div v-if="!memberDraft.age || parseInt(memberDraft.age) >= 16"
                  class="rounded border border-slate-100 bg-slate-50/50 p-3 space-y-4">
                  <p class="text-[11px] font-bold text-slate-600 uppercase flex items-center gap-1">
                    <i class="fa-solid fa-id-card text-xs"></i> परिचयपत्र तथा कागजात — Documents
                  </p>

                  <!-- Citizenship -->
                  <div class="grid grid-cols-3 gap-3">
                    <div class="flex items-center justify-between gap-2 px-3 py-2 bg-white rounded border border-[#e6e7e9]">
                      <span class="text-[12px] text-gray-600">नागरिकता <span class="text-gray-400 text-[10px]">(Citizenship)</span></span>
                      <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                        <button type="button" @click="memberDraft.has_citizenship = true; memberDraft.no_citizenship_reason = ''"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.has_citizenship ? 'bg-green-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छ</button>
                        <button type="button" @click="memberDraft.has_citizenship = false"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', !memberDraft.has_citizenship ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छैन</button>
                      </div>
                    </div>
                    <!-- No citizenship reason -->
                    <div v-if="memberDraft.has_citizenship === false" class="flex flex-col gap-1">
                      <label class="text-[11px] font-semibold text-[#9aa0ac]">नागरिकता नभएको कारण</label>
                      <input v-model="memberDraft.no_citizenship_reason" type="text" placeholder="कारण लेख्नुहोस्..."
                        class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-blue-200 focus:border-blue-400" />
                    </div>

                    <!-- Voter's Card -->
                    <div class="flex items-center justify-between gap-2 px-3 py-2 bg-white rounded border border-[#e6e7e9]">
                      <span class="text-[12px] text-gray-600">मतदाता परिचयपत्र <span class="text-gray-400 text-[10px]">(Voter's Card)</span></span>
                      <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                        <button type="button" @click="memberDraft.has_voters_card = true"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.has_voters_card ? 'bg-green-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छ</button>
                        <button type="button" @click="memberDraft.has_voters_card = false"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', !memberDraft.has_voters_card ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छैन</button>
                      </div>
                    </div>
                    <!-- National ID -->
                    <div class="flex items-center justify-between gap-2 px-3 py-2 bg-white rounded border border-[#e6e7e9]">
                      <span class="text-[12px] text-gray-600">राष्ट्रिय परिचयपत्र <span class="text-gray-400 text-[10px]">(National ID)</span></span>
                      <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                        <button type="button" @click="memberDraft.has_national_id_card = true"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.has_national_id_card ? 'bg-green-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छ</button>
                        <button type="button" @click="memberDraft.has_national_id_card = false"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', !memberDraft.has_national_id_card ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छैन</button>
                      </div>
                    </div>
                  </div>

                  <!-- Passport section -->
                  <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2 px-3 py-2 bg-white rounded border border-[#e6e7e9]">
                      <span class="text-[12px] text-gray-600">राहदानी <span class="text-gray-400 text-[10px]">(Passport)</span></span>
                      <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                        <button type="button" @click="memberDraft.has_passport = true"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.has_passport ? 'bg-green-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छ</button>
                        <button type="button" @click="memberDraft.has_passport = false; memberDraft.passport_info = false; memberDraft.passport_number = ''; memberDraft.passport_issued_district = ''; memberDraft.passport_issued_date = ''; memberDraft.passport_expiry_date = ''"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', !memberDraft.has_passport ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छैन</button>
                      </div>
                    </div>
                    <!-- Passport details sub-section -->
                    <div v-if="memberDraft.has_passport" class="pl-4 border-l-2 border-green-200 space-y-2">
                      <div class="flex items-center gap-2">
                        <span class="text-[11px] text-gray-500">राहदानीको विवरण थाहा छ?</span>
                        <button type="button" @click="memberDraft.passport_info = true"
                          :class="['px-3 py-1 text-xs font-semibold rounded border transition-colors', memberDraft.passport_info ? 'bg-blue-500 text-white border-blue-500' : 'bg-white text-gray-600 border-[#e6e7e9] hover:bg-gray-50']">छ</button>
                        <button type="button" @click="memberDraft.passport_info = false"
                          :class="['px-3 py-1 text-xs font-semibold rounded border transition-colors', !memberDraft.passport_info ? 'bg-slate-400 text-white border-slate-400' : 'bg-white text-gray-600 border-[#e6e7e9] hover:bg-gray-50']">छैन</button>
                      </div>
                      <div v-if="memberDraft.passport_info" class="grid grid-cols-4 gap-2">
                        <div class="flex flex-col gap-1">
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">राहदानी नं. (Passport No.)</label>
                          <input v-model="memberDraft.passport_number" type="text" placeholder="PA1234567"
                            class="w-full rounded border border-[#e6e7e9] px-2.5 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-blue-200 focus:border-blue-400" />
                        </div>
                        <div class="flex flex-col gap-1">
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">जारी गरेको जिल्ला</label>
                          <input v-model="memberDraft.passport_issued_district" type="text" placeholder="जिल्ला"
                            class="w-full rounded border border-[#e6e7e9] px-2.5 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-blue-200 focus:border-blue-400" />
                        </div>
                        <div class="flex flex-col gap-1">
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">जारी मिति (Issued Date BS)</label>
                          <input v-model="memberDraft.passport_issued_date" type="text" placeholder="२०७५-०५-१५"
                            class="w-full rounded border border-[#e6e7e9] px-2.5 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-blue-200 focus:border-blue-400" />
                        </div>
                        <div class="flex flex-col gap-1">
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">म्याद सकिने मिति (Expiry BS)</label>
                          <input v-model="memberDraft.passport_expiry_date" type="text" placeholder="२०८५-०५-१४"
                            class="w-full rounded border border-[#e6e7e9] px-2.5 py-1.5 text-[12px] bg-white focus:ring-2 focus:ring-blue-200 focus:border-blue-400" />
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- PAN -->
                  <div class="flex items-center justify-between gap-2 px-3 py-2 bg-white rounded border border-[#e6e7e9]">
                    <span class="text-[12px] text-gray-600">स्थायी लेखा नं. (PAN)</span>
                    <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                      <button type="button" @click="memberDraft.has_pan = true"
                        :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.has_pan ? 'bg-green-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छ</button>
                      <button type="button" @click="memberDraft.has_pan = false"
                        :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', !memberDraft.has_pan ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छैन</button>
                    </div>
                  </div>

                  <!-- Drivers License -->
                  <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2 px-3 py-2 bg-white rounded border border-[#e6e7e9]">
                      <span class="text-[12px] text-gray-600">सवारी चालक अनुमतिपत्र <span class="text-gray-400 text-[10px]">(Driving License)</span></span>
                      <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                        <button type="button" @click="memberDraft.has_drivers_license = true"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.has_drivers_license ? 'bg-green-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छ</button>
                        <button type="button" @click="memberDraft.has_drivers_license = false; memberDraft.drivers_license_category = []"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', !memberDraft.has_drivers_license ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छैन</button>
                      </div>
                    </div>
                    <div v-if="memberDraft.has_drivers_license" class="pl-4 border-l-2 border-green-200">
                      <label class="text-[11px] font-semibold text-[#9aa0ac] mb-1.5 block">लाइसेन्सको वर्ग <span class="text-gray-400">(License Category — multi-select)</span></label>
                      <div class="grid grid-cols-4 gap-1.5">
                        <label v-for="opt in (surveyOptions.drivers_license_category ?? [])" :key="opt.value"
                          class="flex items-center gap-1.5 px-2 py-1.5 rounded border cursor-pointer transition-colors text-[11px]"
                          :class="(memberDraft.drivers_license_category ?? []).includes(opt.value) ? 'border-green-400 bg-green-50' : 'border-[#e6e7e9] bg-white hover:bg-gray-50'">
                          <input type="checkbox" :value="opt.value" v-model="memberDraft.drivers_license_category" class="accent-green-500 w-3 h-3" />
                          {{ opt.label }}
                        </label>
                      </div>
                    </div>
                  </div>

                  <!-- Smoking / Drinking section (age ≥ 16) -->
                  <div class="space-y-2 border-t border-slate-200 pt-3">
                    <p class="text-[11px] font-bold text-slate-600 uppercase flex items-center gap-1">
                      <i class="fa-solid fa-smoking text-xs"></i> धुम्रपान / मद्यपान — Smoking & Drinking
                    </p>
                    <div class="flex items-center justify-between gap-2 px-3 py-2 bg-white rounded border border-[#e6e7e9]">
                      <span class="text-[12px] text-gray-600">सिगरेट, तम्बाकु, जाड, रक्सी सेवन गर्नु हुन्छ?</span>
                      <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                        <button type="button" @click="memberDraft.smoking_drinking = true"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.smoking_drinking ? 'bg-orange-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">हो</button>
                        <button type="button" @click="memberDraft.smoking_drinking = false; memberDraft.smoking_drinking_items = []; memberDraft.frequency_smoking_drinking = ''"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', !memberDraft.smoking_drinking ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">होइन</button>
                      </div>
                    </div>
                    <template v-if="memberDraft.smoking_drinking">
                      <div class="pl-4 border-l-2 border-orange-200 space-y-2">
                        <div>
                          <label class="text-[11px] font-semibold text-[#9aa0ac] mb-1.5 block">के के सेवन गर्नु हुन्छ? <span class="text-gray-400">(Items)</span></label>
                          <div class="grid grid-cols-4 gap-1.5">
                            <label v-for="opt in (surveyOptions.smoking_drinking_items ?? [])" :key="opt.value"
                              class="flex items-center gap-1.5 px-2 py-1.5 rounded border cursor-pointer transition-colors text-[11px]"
                              :class="(memberDraft.smoking_drinking_items ?? []).includes(opt.value) ? 'border-orange-400 bg-orange-50' : 'border-[#e6e7e9] bg-white hover:bg-gray-50'">
                              <input type="checkbox" :value="opt.value" v-model="memberDraft.smoking_drinking_items" class="accent-orange-500 w-3 h-3" />
                              {{ opt.label }}
                            </label>
                          </div>
                        </div>
                        <div class="flex flex-col gap-1">
                          <label class="text-[11px] font-semibold text-[#9aa0ac]">कसरी / कति पटक सेवन गर्नु हुन्छ? <span class="text-gray-400">(Frequency)</span></label>
                          <select v-model="memberDraft.frequency_smoking_drinking"
                            class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-orange-200 focus:border-orange-400">
                            <option value="">-- छान्नुहोस् --</option>
                            <option v-for="opt in (surveyOptions.frequency_smoking_drinking ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                          </select>
                        </div>
                      </div>
                    </template>
                  </div>

                  <!-- Social Security Allowance (conditional: age ≥ 70, or female ≥ 60 + widow/divorced/single, or disability card Red/Blue) -->
                  <div v-if="
                    (memberDraft.age && parseInt(memberDraft.age) >= 70) ||
                    (memberDraft.gender === 'Female' && memberDraft.age && parseInt(memberDraft.age) >= 60 && ['Single','Divorced','Widow'].includes(memberDraft.marital_status)) ||
                    (memberDraft.disability_status && ['Red','Blue'].includes(memberDraft.disability_card_color))
                  " class="border-t border-slate-200 pt-3">
                    <div class="flex items-center justify-between gap-2 px-3 py-2 bg-blue-50 rounded border border-blue-200">
                      <span class="text-[12px] font-semibold text-blue-700">
                        <i class="fa-solid fa-hand-holding-heart mr-1"></i>
                        सामाजिक सुरक्षा भत्ता लिनु भएको छ? <span class="text-blue-400 text-[10px]">(Social Security Allowance)</span>
                      </span>
                      <div class="flex rounded border border-blue-200 overflow-hidden">
                        <button type="button" @click="memberDraft.ss_allowance = true"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.ss_allowance ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 hover:bg-blue-50']">छ</button>
                        <button type="button" @click="memberDraft.ss_allowance = false"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', !memberDraft.ss_allowance ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-blue-50']">छैन</button>
                      </div>
                    </div>
                  </div>

                </div>

                <!-- ── Phase 3: Contact Info (age > 15) ─────────────────── -->
                <div v-if="Number(memberDraft.age) > 15"
                  class="border border-teal-200 rounded-lg overflow-hidden">
                  <button type="button" @click="p3ContactOpen = !p3ContactOpen"
                    class="w-full flex items-center justify-between px-4 py-2.5 bg-teal-50 hover:bg-teal-100 transition-colors">
                    <span class="text-[12px] font-bold text-teal-800 uppercase tracking-wider">
                      <i class="fa-solid fa-phone text-xs mr-1.5"></i> सम्पर्क जानकारी — Contact Info
                    </span>
                    <i :class="['fa-solid text-teal-500 text-xs', p3ContactOpen ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
                  </button>
                  <div v-show="p3ContactOpen" class="px-4 py-3 space-y-3 bg-white">
                    <!-- Phone -->
                    <div class="grid grid-cols-1 gap-2">
                      <div class="flex items-center justify-between gap-2">
                        <span class="text-[12px] font-semibold text-gray-700">फोन छ? <span class="text-gray-400 text-[10px]">(Has Phone)</span></span>
                        <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                          <button type="button" @click="memberDraft.has_phone = true"
                            :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.has_phone === true ? 'bg-teal-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छ</button>
                          <button type="button" @click="memberDraft.has_phone = false; memberDraft.know_phone = null; memberDraft.member_phone = ''"
                            :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', memberDraft.has_phone === false ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छैन</button>
                        </div>
                      </div>
                      <template v-if="memberDraft.has_phone === false">
                        <div class="flex items-center justify-between gap-2 pl-4 border-l-2 border-teal-100">
                          <span class="text-[11px] text-gray-600">फोन नम्बर थाहा छ? <span class="text-gray-400 text-[10px]">(Know Phone)</span></span>
                          <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                            <button type="button" @click="memberDraft.know_phone = true"
                              :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.know_phone === true ? 'bg-teal-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">थाहा छ</button>
                            <button type="button" @click="memberDraft.know_phone = false; memberDraft.member_phone = ''"
                              :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', memberDraft.know_phone === false ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">थाहा छैन</button>
                          </div>
                        </div>
                      </template>
                      <template v-if="memberDraft.has_phone === true || memberDraft.know_phone === true">
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">फोन नम्बर (Phone Number)</label>
                          <input v-model="memberDraft.member_phone" type="text" placeholder="९८xxxxxxxx"
                            class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500" />
                        </div>
                      </template>
                    </div>
                    <!-- Email -->
                    <div class="grid grid-cols-1 gap-2 border-t border-teal-100 pt-2">
                      <div class="flex items-center justify-between gap-2">
                        <span class="text-[12px] font-semibold text-gray-700">इमेल छ? <span class="text-gray-400 text-[10px]">(Has Email)</span></span>
                        <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                          <button type="button" @click="memberDraft.has_email = true"
                            :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.has_email === true ? 'bg-teal-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छ</button>
                          <button type="button" @click="memberDraft.has_email = false; memberDraft.know_email = null; memberDraft.member_email = ''"
                            :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', memberDraft.has_email === false ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छैन</button>
                        </div>
                      </div>
                      <template v-if="memberDraft.has_email === false">
                        <div class="flex items-center justify-between gap-2 pl-4 border-l-2 border-teal-100">
                          <span class="text-[11px] text-gray-600">इमेल ठेगाना थाहा छ? <span class="text-gray-400 text-[10px]">(Know Email)</span></span>
                          <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                            <button type="button" @click="memberDraft.know_email = true"
                              :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.know_email === true ? 'bg-teal-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">थाहा छ</button>
                            <button type="button" @click="memberDraft.know_email = false; memberDraft.member_email = ''"
                              :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', memberDraft.know_email === false ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">थाहा छैन</button>
                          </div>
                        </div>
                      </template>
                      <template v-if="memberDraft.has_email === true || memberDraft.know_email === true">
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">इमेल ठेगाना (Email Address)</label>
                          <input v-model="memberDraft.member_email" type="email" placeholder="example@email.com"
                            class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500" />
                        </div>
                      </template>
                    </div>
                  </div>
                </div>

                <!-- ── Phase 3: Vocational Training (age > 15) ───────────── -->
                <div v-if="Number(memberDraft.age) > 15"
                  class="border border-amber-200 rounded-lg overflow-hidden">
                  <button type="button" @click="p3VocOpen = !p3VocOpen"
                    class="w-full flex items-center justify-between px-4 py-2.5 bg-amber-50 hover:bg-amber-100 transition-colors">
                    <span class="text-[12px] font-bold text-amber-800 uppercase tracking-wider">
                      <i class="fa-solid fa-hammer text-xs mr-1.5"></i> व्यावसायिक तालिम — Vocational Training
                    </span>
                    <i :class="['fa-solid text-amber-500 text-xs', p3VocOpen ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
                  </button>
                  <div v-show="p3VocOpen" class="px-4 py-3 space-y-3 bg-white">
                    <div class="flex items-center justify-between gap-2">
                      <span class="text-[12px] font-semibold text-gray-700">व्यावसायिक तालिम लिनुभएको छ? <span class="text-gray-400 text-[10px]">(Vocational Training)</span></span>
                      <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                        <button type="button" @click="memberDraft.vocational_training = true"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.vocational_training === true ? 'bg-amber-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छ</button>
                        <button type="button" @click="memberDraft.vocational_training = false; memberDraft.vt_subject = ''; memberDraft.vt_duration_min = ''; memberDraft.vt_duration_max = ''"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', memberDraft.vocational_training === false ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छैन</button>
                      </div>
                    </div>
                    <div v-if="memberDraft.vocational_training === true" class="pl-4 border-l-2 border-amber-200 space-y-2">
                      <div>
                        <label class="text-[10px] font-semibold text-[#9aa0ac]">तालिम विषय (Subject)</label>
                        <select v-model="memberDraft.vt_subject"
                          class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 bg-white">
                          <option value="">— छान्नुहोस् —</option>
                          <option v-for="opt in (surveyOptions.vocational_training_subject ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                      </div>
                      <div class="grid grid-cols-2 gap-2">
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">अवधि न्यूनतम — महिना (Min Months)</label>
                          <input v-model="memberDraft.vt_duration_min" type="number" min="0" placeholder="०"
                            class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500" />
                        </div>
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">अवधि अधिकतम — महिना (Max Months)</label>
                          <input v-model="memberDraft.vt_duration_max" type="number" min="0" placeholder="०"
                            class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500" />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- ── Phase 3: Technical Training (age > 15) ────────────── -->
                <div v-if="Number(memberDraft.age) > 15"
                  class="border border-purple-200 rounded-lg overflow-hidden">
                  <button type="button" @click="p3TechOpen = !p3TechOpen"
                    class="w-full flex items-center justify-between px-4 py-2.5 bg-purple-50 hover:bg-purple-100 transition-colors">
                    <span class="text-[12px] font-bold text-purple-800 uppercase tracking-wider">
                      <i class="fa-solid fa-wrench text-xs mr-1.5"></i> प्राविधिक तालिम — Technical Training
                    </span>
                    <i :class="['fa-solid text-purple-500 text-xs', p3TechOpen ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
                  </button>
                  <div v-show="p3TechOpen" class="px-4 py-3 space-y-3 bg-white">
                    <div class="flex items-center justify-between gap-2">
                      <span class="text-[12px] font-semibold text-gray-700">प्राविधिक तालिम लिनुभएको छ? <span class="text-gray-400 text-[10px]">(Technical Training)</span></span>
                      <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                        <button type="button" @click="memberDraft.techtraining = true"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.techtraining === true ? 'bg-purple-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छ</button>
                        <button type="button" @click="memberDraft.techtraining = false; memberDraft.techtraining_subject = ''; memberDraft.tech_duration_min = ''; memberDraft.tech_duration_max = ''"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', memberDraft.techtraining === false ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छैन</button>
                      </div>
                    </div>
                    <div v-if="memberDraft.techtraining === true" class="pl-4 border-l-2 border-purple-200 space-y-2">
                      <div>
                        <label class="text-[10px] font-semibold text-[#9aa0ac]">तालिम विषय (Subject)</label>
                        <select v-model="memberDraft.techtraining_subject"
                          class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 bg-white">
                          <option value="">— छान्नुहोस् —</option>
                          <option v-for="opt in (surveyOptions.technical_training_subject ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                      </div>
                      <div class="grid grid-cols-2 gap-2">
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">अवधि न्यूनतम — महिना (Min Months)</label>
                          <input v-model="memberDraft.tech_duration_min" type="number" min="0" placeholder="०"
                            class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500" />
                        </div>
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">अवधि अधिकतम — महिना (Max Months)</label>
                          <input v-model="memberDraft.tech_duration_max" type="number" min="0" placeholder="०"
                            class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500" />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- ── Phase 3: SA/Other Training (age > 15) ─────────────── -->
                <div v-if="Number(memberDraft.age) > 15"
                  class="border border-rose-200 rounded-lg overflow-hidden">
                  <button type="button" @click="p3SaOpen = !p3SaOpen"
                    class="w-full flex items-center justify-between px-4 py-2.5 bg-rose-50 hover:bg-rose-100 transition-colors">
                    <span class="text-[12px] font-bold text-rose-800 uppercase tracking-wider">
                      <i class="fa-solid fa-book-open text-xs mr-1.5"></i> साक्षरता / अन्य तालिम — SA / Other Training
                    </span>
                    <i :class="['fa-solid text-rose-500 text-xs', p3SaOpen ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
                  </button>
                  <div v-show="p3SaOpen" class="px-4 py-3 space-y-3 bg-white">
                    <div class="flex items-center justify-between gap-2">
                      <span class="text-[12px] font-semibold text-gray-700">साक्षरता/अन्य तालिम लिनुभएको छ? <span class="text-gray-400 text-[10px]">(SA Training)</span></span>
                      <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                        <button type="button" @click="memberDraft.sa_training = true"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.sa_training === true ? 'bg-rose-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छ</button>
                        <button type="button" @click="memberDraft.sa_training = false; memberDraft.sa_training_subject = ''; memberDraft.sa_duration_min = ''; memberDraft.sa_duration_max = ''"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', memberDraft.sa_training === false ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छैन</button>
                      </div>
                    </div>
                    <div v-if="memberDraft.sa_training === true" class="pl-4 border-l-2 border-rose-200 space-y-2">
                      <div>
                        <label class="text-[10px] font-semibold text-[#9aa0ac]">तालिम विषय (Subject)</label>
                        <select v-model="memberDraft.sa_training_subject"
                          class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 bg-white">
                          <option value="">— छान्नुहोस् —</option>
                          <option v-for="opt in (surveyOptions.sa_training_subject ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                      </div>
                      <div class="grid grid-cols-2 gap-2">
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">अवधि न्यूनतम — महिना (Min Months)</label>
                          <input v-model="memberDraft.sa_duration_min" type="number" min="0" placeholder="०"
                            class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500" />
                        </div>
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">अवधि अधिकतम — महिना (Max Months)</label>
                          <input v-model="memberDraft.sa_duration_max" type="number" min="0" placeholder="०"
                            class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500" />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- ── Phase 3: Address / Location (all ages) ────────────── -->
                <div class="border border-cyan-200 rounded-lg overflow-hidden">
                  <button type="button" @click="p3AddrOpen = !p3AddrOpen"
                    class="w-full flex items-center justify-between px-4 py-2.5 bg-cyan-50 hover:bg-cyan-100 transition-colors">
                    <span class="text-[12px] font-bold text-cyan-800 uppercase tracking-wider">
                      <i class="fa-solid fa-location-dot text-xs mr-1.5"></i> ठेगाना / बसोबास स्थान — Address / Location
                    </span>
                    <i :class="['fa-solid text-cyan-500 text-xs', p3AddrOpen ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
                  </button>
                  <div v-show="p3AddrOpen" class="px-4 py-3 space-y-3 bg-white">
                    <!-- addrslocation -->
                    <div>
                      <label class="text-[10px] font-semibold text-[#9aa0ac]">बसोबासको स्थान (Address Location)</label>
                      <div class="flex flex-wrap gap-2 mt-1">
                        <button v-for="opt in (surveyOptions.addrslocation ?? [])" :key="opt.value"
                          type="button" @click="memberDraft.addrslocation = opt.value; memberDraft.residing_district = ''; memberDraft.purpose_living_outside = ''; memberDraft.duration_of_stay = ''; memberDraft.residing_country = []; memberDraft.purpose_living_abroad = ''; memberDraft.typeofabroadjob = ''; memberDraft.abroadjobstatus = ''"
                          :class="['px-3 py-1.5 text-xs font-semibold rounded-full border transition-colors',
                            memberDraft.addrslocation === opt.value
                              ? 'bg-cyan-600 text-white border-cyan-600'
                              : 'bg-white text-gray-600 border-[#e6e7e9] hover:bg-cyan-50']">
                          {{ opt.label }}
                        </button>
                      </div>
                    </div>

                    <!-- outside Nepal (within Nepal, different municipality) -->
                    <template v-if="memberDraft.addrslocation === 'outside_withinNepal'">
                      <div class="pl-4 border-l-2 border-cyan-200 space-y-2">
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">बस्ने जिल्ला (Residing District)</label>
                          <input v-model="memberDraft.residing_district" type="text" placeholder="जिल्लाको नाम"
                            class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500" />
                        </div>
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">बाहिर बस्नुको उद्देश्य (Purpose)</label>
                          <select v-model="memberDraft.purpose_living_outside"
                            class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500 bg-white">
                            <option value="">— छान्नुहोस् —</option>
                            <option v-for="opt in (surveyOptions.purpose_living_outside ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                          </select>
                        </div>
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">बसाइ अवधि — वर्ष (Duration of Stay)</label>
                          <input v-model="memberDraft.duration_of_stay" type="number" min="0" placeholder="०"
                            class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500" />
                        </div>
                      </div>
                    </template>

                    <!-- Abroad -->
                    <template v-if="memberDraft.addrslocation === 'Abroad'">
                      <div class="pl-4 border-l-2 border-cyan-200 space-y-2">
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">बस्ने देश (Residing Country) — एकभन्दा बढी छान्न सकिन्छ</label>
                          <div class="mt-1 grid grid-cols-4 gap-1">
                            <label v-for="opt in (surveyOptions.residing_country ?? [])" :key="opt.value"
                              :class="['flex items-center gap-1.5 px-2 py-1 rounded border text-[11px] cursor-pointer transition-colors',
                                (memberDraft.residing_country ?? []).includes(opt.value) ? 'border-cyan-400 bg-cyan-50' : 'border-[#e6e7e9] bg-white hover:bg-gray-50']">
                              <input type="checkbox" :value="opt.value" v-model="memberDraft.residing_country" class="accent-cyan-600 w-3 h-3" />
                              {{ opt.label }}
                            </label>
                          </div>
                        </div>
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">विदेशमा बस्नुको उद्देश्य (Purpose Abroad)</label>
                          <select v-model="memberDraft.purpose_living_abroad"
                            class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500 bg-white">
                            <option value="">— छान्नुहोस् —</option>
                            <option v-for="opt in (surveyOptions.purpose_living_outside ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                          </select>
                        </div>
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">विदेशी रोजगारीको प्रकार (Type of Abroad Job)</label>
                          <select v-model="memberDraft.typeofabroadjob"
                            class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500 bg-white">
                            <option value="">— छान्नुहोस् —</option>
                            <option v-for="opt in (surveyOptions.typeofabroadjob ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                          </select>
                        </div>
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">विदेशी रोजगारीको अवस्था (Abroad Job Status)</label>
                          <select v-model="memberDraft.abroadjobstatus"
                            class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500 bg-white">
                            <option value="">— छान्नुहोस् —</option>
                            <option v-for="opt in (surveyOptions.abroadjobstatus ?? [])" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                          </select>
                        </div>
                        <div>
                          <label class="text-[10px] font-semibold text-[#9aa0ac]">बसाइ अवधि — वर्ष (Duration of Stay)</label>
                          <input v-model="memberDraft.duration_of_stay" type="number" min="0" placeholder="०"
                            class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500" />
                        </div>
                      </div>
                    </template>
                  </div>
                </div>

                <!-- ── Phase 3: Business (age > 15) ──────────────────────── -->
                <div v-if="Number(memberDraft.age) > 15"
                  class="border border-emerald-200 rounded-lg overflow-hidden">
                  <button type="button" @click="p3BizOpen = !p3BizOpen"
                    class="w-full flex items-center justify-between px-4 py-2.5 bg-emerald-50 hover:bg-emerald-100 transition-colors">
                    <span class="text-[12px] font-bold text-emerald-800 uppercase tracking-wider">
                      <i class="fa-solid fa-briefcase text-xs mr-1.5"></i> व्यवसाय — Business
                    </span>
                    <i :class="['fa-solid text-emerald-500 text-xs', p3BizOpen ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
                  </button>
                  <div v-show="p3BizOpen" class="px-4 py-3 space-y-3 bg-white">
                    <div class="flex items-center justify-between gap-2">
                      <span class="text-[12px] font-semibold text-gray-700">व्यवसायमा संलग्न हुनुहुन्छ? <span class="text-gray-400 text-[10px]">(Involved in Business)</span></span>
                      <div class="flex rounded border border-[#e6e7e9] overflow-hidden">
                        <button type="button" @click="memberDraft.involveinbusiness = true"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors', memberDraft.involveinbusiness === true ? 'bg-emerald-500 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छ</button>
                        <button type="button" @click="memberDraft.involveinbusiness = false; memberDraft.business_name = ''"
                          :class="['px-3 py-1 text-xs font-semibold transition-colors border-l', memberDraft.involveinbusiness === false ? 'bg-slate-400 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">छैन</button>
                      </div>
                    </div>
                    <div v-if="memberDraft.involveinbusiness === true" class="pl-4 border-l-2 border-emerald-200">
                      <label class="text-[10px] font-semibold text-[#9aa0ac]">व्यवसायको नाम (Business Name)</label>
                      <input v-model="memberDraft.business_name" type="text" placeholder="व्यवसायको नाम लेख्नुहोस्"
                        class="w-full mt-0.5 rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" />
                    </div>
                  </div>
                </div>

              </div><!-- end modal body -->

              <!-- Modal footer -->
              <div class="px-6 py-4 border-t border-[#e6e7e9] bg-gray-50 rounded-b-lg flex items-center justify-between gap-3 shrink-0">
                <p v-if="!memberDraft.full_name?.trim()" class="text-[11px] text-amber-600 flex items-center gap-1">
                  <i class="fa-solid fa-triangle-exclamation text-[10px]"></i> सदस्यको नाम आवश्यक छ
                </p>
                <div v-else></div>
                <div class="flex items-center gap-3">
                  <button type="button" @click="closeMemberModal"
                    class="px-4 py-2 text-[13px] font-semibold text-gray-600 bg-white border border-[#e6e7e9] rounded hover:bg-gray-50 transition-colors">
                    रद्द गर्नुहोस् (Cancel)
                  </button>
                  <button type="button" @click="saveMemberModal"
                    :disabled="!memberDraft.full_name?.trim()"
                    class="px-5 py-2 text-[13px] font-semibold text-white rounded transition-colors"
                    :class="memberDraft.full_name?.trim() ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-300 cursor-not-allowed'">
                    <i class="fa-solid fa-check text-xs mr-1"></i>
                    {{ memberEditIdx !== null ? 'अद्यावधिक गर्नुहोस् (Update)' : 'सुरक्षित गर्नुहोस् (Save)' }}
                  </button>
                </div>
              </div>

            </div><!-- end modal card -->
          </div><!-- end modal overlay -->
        </Transition>
      </Teleport>

    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 15: अन्तिम समीक्षा
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 15" class="space-y-4">

      <!-- ── Header ── -->
      <div class="bg-gradient-to-r from-[#182433] to-[#206bc4] rounded-md px-5 py-4 text-white flex items-center justify-between">
        <div class="flex items-center gap-3">
          <i class="fa-solid fa-clipboard-check text-2xl"></i>
          <div>
            <p class="font-bold text-base">सर्वेक्षण सारांश</p>
            <p class="text-blue-200 text-xs mt-0.5">पेश गर्नु अघि सबै विवरण जाँच गर्नुहोस्</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <!-- Print / PDF buttons -->
          <div class="flex items-center gap-2">
            <button type="button" @click="printReview(false)"
              class="flex items-center gap-1.5 px-3 py-2 bg-white/15 hover:bg-white/25 text-white text-xs font-medium rounded transition-colors border border-white/20">
              <i class="fa-solid fa-print text-[11px]"></i>
              प्रिन्ट
            </button>
            <button type="button" @click="printReview(true)"
              class="flex items-center gap-1.5 px-3 py-2 bg-white text-[#182433] text-xs font-semibold rounded hover:bg-blue-50 transition-colors">
              <i class="fa-solid fa-file-pdf text-red-500 text-[11px]"></i>
              PDF डाउनलोड
            </button>
          </div>
          <div class="text-right border-l border-white/20 pl-3">
            <p class="text-2xl font-bold text-white">{{ fillProgress }}%</p>
            <p class="text-[11px] text-blue-200">पूर्णता</p>
          </div>
        </div>
      </div>

      <!-- Validation summary -->
      <div v-if="hasValidationErrors" class="bg-red-50 border border-red-200 rounded-md px-4 py-3 space-y-2">
        <div class="flex items-center gap-2">
          <i class="fa-solid fa-circle-exclamation text-red-500 text-sm"></i>
          <p class="text-[13px] font-semibold text-red-700">
            {{ validationErrorCount }} वटा आवश्यक विवरण खाली छन् — मस्यौदा सुरक्षित गर्न मिल्छ, तर पेस गर्न अगाडि भर्नुहोस्।
          </p>
        </div>
        <div class="space-y-1 pl-5">
          <div v-for="(errs, stepNum) in stepValidationErrors" :key="stepNum"
            class="flex items-start gap-2 text-[12px] text-red-600 leading-relaxed">
            <button type="button" @click="goToStep(Number(stepNum))"
              class="shrink-0 font-bold underline hover:text-red-800 transition whitespace-nowrap">
              चरण {{ stepNum }}
            </button>
            <span>— {{ errs.join(' · ') }}</span>
          </div>
        </div>
      </div>
      <div v-else class="bg-green-50 border border-green-200 rounded-md px-4 py-3 flex items-center gap-2">
        <i class="fa-solid fa-circle-check text-green-500 text-sm"></i>
        <p class="text-[13px] font-semibold text-green-700">सबै आवश्यक विवरण भरिएको छ — पेस गर्न तयार छ।</p>
      </div>

      <!-- ╔══ PRINTABLE AREA ══════════════════════════════════════════ -->
      <div id="review-print-area">

      <!-- ── Row 1: Basic + Family ── -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <!-- Step 1 — Basic Info -->
        <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-2.5 flex items-center gap-2 border-b" style="background:#eff6ff;border-color:#bfdbfe">
            <span class="w-5 h-5 rounded bg-blue-600 text-white text-[9px] font-bold flex items-center justify-center shrink-0">1</span>
            <span class="text-xs font-bold text-blue-800 uppercase">घर तथा आधारभूत</span>
          </div>
          <div class="p-4 space-y-1.5 text-[13px]">
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">उत्तरदाता</span><span class="font-semibold text-right">{{ form.respondent_name || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">सम्पर्क</span><span class="font-semibold text-right">{{ form.respondent_contact || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">सर्वे मिति</span><span class="font-semibold text-right">{{ form.survey_date || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">पुरुष / महिला / अन्य</span>
              <span class="font-semibold text-right">
                {{ (form.social.male_num || 0) }} / {{ (form.social.female_num || 0) }} / {{ (form.social.other_num || 0) }}
              </span>
            </div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">वडा नं.</span><span class="font-semibold text-right">{{ form.ward_id || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">GPS</span>
              <span class="font-semibold text-right text-[12px]">
                {{ form.gps_lat && form.gps_lng ? form.gps_lat + ', ' + form.gps_lng : '—' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Step 2 — Family & Social -->
        <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-2.5 flex items-center gap-2 border-b" style="background:#fdf4ff;border-color:#e9d5ff">
            <span class="w-5 h-5 rounded bg-purple-600 text-white text-[9px] font-bold flex items-center justify-center shrink-0">2</span>
            <span class="text-xs font-bold text-purple-800 uppercase">परिवार तथा सामाजिक</span>
          </div>
          <div class="p-4 space-y-1.5 text-[13px]">
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">घरमुली</span><span class="font-semibold text-right">{{ form.social.family_head || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">जाति</span><span class="font-semibold text-right">{{ soLabel('ethnicity', form.social.ethnicity) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">धर्म</span><span class="font-semibold text-right">{{ soLabel('religion', form.social.religion) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">मातृभाषा</span><span class="font-semibold text-right">{{ soLabel('language', form.social.mother_tongue) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">परिवारको किसिम</span><span class="font-semibold text-right">{{ soLabel('family_type', form.social.family_type) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">मुख्य पेशा</span><span class="font-semibold text-right">{{ soLabel('major_occupation', form.social.major_family_occupation) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">बसोबासको प्रकार</span><span class="font-semibold text-right">{{ soLabel('settlement_type', form.social.settlement_type) || '—' }}</span></div>
          </div>
        </div>
      </div>

      <!-- ── Row 2: Housing + Utilities ── -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <!-- Housing Details -->
        <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-2.5 flex items-center gap-2 border-b" style="background:#fff7ed;border-color:#fed7aa">
            <span class="w-5 h-5 rounded bg-orange-500 text-white text-[9px] font-bold flex items-center justify-center shrink-0">1</span>
            <span class="text-xs font-bold text-orange-800 uppercase">घर संरचना</span>
          </div>
          <div class="p-4 space-y-1.5 text-[13px]">
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">जग्गाको किसिम</span><span class="font-semibold text-right">{{ soLabel('house_land_type', form.housing.house_land_type) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">घरको बनावट</span><span class="font-semibold text-right">{{ soLabel('house_type', form.housing.house_type) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">घरको उद्देश्य</span><span class="font-semibold text-right">{{ soLabel('house_purpose', form.housing.house_purpose) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">छानो</span><span class="font-semibold text-right">{{ soLabel('roof_type', form.housing.roof_type) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">भित्ता</span><span class="font-semibold text-right">{{ soLabel('wall_type', form.housing.wall_type) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">जग</span><span class="font-semibold text-right">{{ soLabel('foundation_type', form.housing.foundation_type) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">स्वामित्व</span><span class="font-semibold text-right">{{ soLabel('ownership_type', form.housing.ownership_type) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">घरको अवस्था</span><span class="font-semibold text-right">{{ soLabel('house_condition', form.housing.house_condition) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">कोठा / तला</span><span class="font-semibold text-right">{{ form.housing.total_rooms || '—' }} / {{ form.housing.total_floors || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">निर्माण वर्ष</span><span class="font-semibold text-right">{{ form.housing.construction_year || '—' }}</span></div>
          </div>
        </div>

        <!-- Utilities -->
        <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-2.5 flex items-center gap-2 border-b" style="background:#f0fdf4;border-color:#bbf7d0">
            <span class="w-5 h-5 rounded bg-green-600 text-white text-[9px] font-bold flex items-center justify-center shrink-0">3</span>
            <span class="text-xs font-bold text-green-800 uppercase">सरसफाई तथा सुविधा</span>
          </div>
          <div class="p-4 space-y-1.5 text-[13px]">
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">खानेपानी</span><span class="font-semibold text-right">{{ soLabel('water_source', form.utilities.water_source) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">पानी दूरी (मिनेट)</span><span class="font-semibold text-right">{{ form.utilities.water_distance_minutes || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">शौचालय</span><span class="font-semibold text-right">{{ soLabel('toilet_type', form.utilities.toilet_type) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">बिजुली</span><span class="font-semibold text-right">{{ soLabel('electricity_source', form.utilities.electricity_source) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">खाना पकाउने इन्धन</span>
              <span class="font-semibold text-right text-[12px]">
                {{ form.utilities.cooking_fuel_arr.length ? form.utilities.cooking_fuel_arr.map(v => soLabel('cooking_fuel', v) || v).join(', ') : '—' }}
              </span>
            </div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">चुलो</span><span class="font-semibold text-right">{{ soLabel('cooking_stove', form.utilities.cooking_stove) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">इन्टरनेट</span><span class="font-semibold text-right">{{ soLabel('internet_type', form.utilities.internet_type) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">फोहोर व्यवस्थापन</span><span class="font-semibold text-right">{{ soLabel('waste_management', form.utilities.solid_waste_management) || '—' }}</span></div>
          </div>
        </div>
      </div>

      <!-- ── Row 2b: Decisions + Wellbeing + Health ── -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        <!-- Step 4 — Decisions -->
        <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-2.5 flex items-center gap-2 border-b" style="background:#eff6ff;border-color:#bfdbfe">
            <span class="w-5 h-5 rounded bg-blue-500 text-white text-[9px] font-bold flex items-center justify-center shrink-0">4</span>
            <span class="text-xs font-bold text-blue-800 uppercase">घरपरिवारको निर्णय</span>
          </div>
          <div class="p-4 space-y-1.5 text-[13px]">
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">घरायसी काम</span><span class="font-semibold text-right">{{ soLabel('decision_owner', form.decisions.decisioninHHWork) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">सम्पत्तिको निर्णय</span><span class="font-semibold text-right">{{ soLabel('decision_owner', form.decisions.decisioninProperty) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">समिति सहभागिता</span><span class="font-semibold text-right">{{ soLabel('decision_owner', form.decisions.decisioninCCommittee) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">SMC सहभागिता</span><span class="font-semibold text-right">{{ soLabel('decision_owner', form.decisions.decisioninSMC) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">व्यापार निर्णय</span><span class="font-semibold text-right">{{ soLabel('decision_owner', form.decisions.decisioninBusiness) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">संस्थामा सहभागी</span><span class="font-semibold text-right">{{ form.decisions.org_participation || '—' }}</span></div>
          </div>
        </div>

        <!-- Step 5/6 — Wellbeing -->
        <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-2.5 flex items-center gap-2 border-b" style="background:#fefce8;border-color:#fde68a">
            <span class="w-5 h-5 rounded bg-yellow-500 text-white text-[9px] font-bold flex items-center justify-center shrink-0">6</span>
            <span class="text-xs font-bold text-yellow-800 uppercase">खुसी र मेलमिलाप</span>
          </div>
          <div class="p-4 space-y-1.5 text-[13px]">
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">बसोबास प्रति दृष्टिकोण</span><span class="font-semibold text-right">{{ soLabel('residence_perception', form.wellbeing.residence_perception) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">परिवार भेटघाट</span><span class="font-semibold text-right">{{ soLabel('family_gathering_freq', form.wellbeing.family_gathering_freq) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">छिमेकी सहकार्य</span><span class="font-semibold text-right">{{ soLabel('neighbor_cooperation', form.wellbeing.neighbor_cooperation) || '—' }}</span></div>
            <div class="flex justify-between gap-2">
              <span class="text-gray-500 shrink-0">खुसीका क्रियाकलाप</span>
              <span class="font-semibold text-right text-[12px]">
                {{ form.wellbeing.happiness_activities.length ? form.wellbeing.happiness_activities.map(v => soLabel('happiness_activities', v) || v).join(', ') : '—' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Health -->
        <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-2.5 flex items-center gap-2 border-b" style="background:#f0fdf4;border-color:#bbf7d0">
            <span class="w-5 h-5 rounded bg-green-500 text-white text-[9px] font-bold flex items-center justify-center shrink-0">3</span>
            <span class="text-xs font-bold text-green-800 uppercase">स्वास्थ्य</span>
          </div>
          <div class="p-4 space-y-1.5 text-[13px]">
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">उपचार स्थान</span><span class="font-semibold text-right">{{ soLabel('treatment_place', form.health.treatment_place) || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">स्वा. केन्द्र समय (मिनेट)</span><span class="font-semibold text-right">{{ form.health.time_to_health_center || '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">स्वास्थ्य बिमा</span><span class="font-semibold text-right">{{ form.health.has_health_insurance === 'yes' ? 'छ ✓' : form.health.has_health_insurance === 'no' ? 'छैन' : '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">गर्भवती महिला</span><span class="font-semibold text-right">{{ form.health.has_pregnant_woman === 'yes' ? 'छ ✓' : form.health.has_pregnant_woman === 'no' ? 'छैन' : '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">पोषण जागरुकता</span><span class="font-semibold text-right">{{ form.health.aware_nutrition === 'yes' ? 'छ ✓' : form.health.aware_nutrition === 'no' ? 'छैन' : '—' }}</span></div>
          </div>
        </div>
      </div>

      <!-- ── Row 3: Occupation + Economic ── -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <!-- Occupation -->
        <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-2.5 flex items-center gap-2 border-b" style="background:#fdf2f8;border-color:#fbcfe8">
            <span class="w-5 h-5 rounded bg-pink-600 text-white text-[9px] font-bold flex items-center justify-center shrink-0">7</span>
            <span class="text-xs font-bold text-pink-800 uppercase">व्यवसाय तथा रोजगारी</span>
          </div>
          <div class="p-4">
            <div class="space-y-1.5">
              <template v-for="(row, idx) in form.occupation.occupation_rows" :key="idx">
                <div v-if="row.sector && Number(row.count) > 0" class="flex justify-between gap-2 text-[13px]">
                  <span class="text-gray-500">{{ soLabel('occupation_sector', row.sector) || row.sector }}</span>
                  <span class="font-semibold">{{ row.count }} जना</span>
                </div>
              </template>
            </div>
            <p v-if="!form.occupation.occupation_rows.some(r => r.sector && Number(r.count) > 0)" class="text-[12px] text-gray-400 italic">उल्लेख नभएको</p>
          </div>
        </div>

        <!-- Economic summary -->
        <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-2.5 flex items-center gap-2 border-b" style="background:#f7fee7;border-color:#d9f99d">
            <span class="w-5 h-5 rounded bg-lime-600 text-white text-[9px] font-bold flex items-center justify-center shrink-0">12</span>
            <span class="text-xs font-bold text-lime-800 uppercase">आम्दानी र खर्च</span>
          </div>
          <div class="p-4 space-y-1.5 text-[13px]">
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">आय रेकर्ड</span><span class="font-semibold">{{ form.economic.income_rows.length }} वटा</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">खर्च रेकर्ड</span><span class="font-semibold">{{ form.economic.expense_rows.length }} वटा</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">बैंक खाता</span><span class="font-semibold">{{ form.economic.has_bank_account ? 'छ ✓' : 'छैन' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">बिमा</span><span class="font-semibold">{{ form.economic.has_insurance ? 'छ ✓' : 'छैन' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">गरिबी कार्ड</span><span class="font-semibold">{{ form.economic.poverty_card ? 'छ ✓' : 'छैन' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">सा.सु. भत्ता</span><span class="font-semibold">{{ form.economic.social_security_allowance ? 'छ ✓' : 'छैन' }}</span></div>
          </div>
        </div>
      </div>

      <!-- ── Row 4: Agriculture + Livestock ── -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <!-- Agriculture -->
        <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-2.5 flex items-center gap-2 border-b" style="background:#ecfdf5;border-color:#a7f3d0">
            <span class="w-5 h-5 rounded bg-emerald-600 text-white text-[9px] font-bold flex items-center justify-center shrink-0">8</span>
            <span class="text-xs font-bold text-emerald-800 uppercase">खेतीपाती</span>
          </div>
          <div class="p-4 space-y-1.5 text-[13px]">
            <div class="flex justify-between gap-2">
              <span class="text-gray-500 shrink-0">कृषि शिर्षक</span>
              <span class="font-semibold text-right">
                {{ form.agriculture.agri_categories.length
                    ? form.agriculture.agri_categories.map(k => soLabel('agri_category', k) || k).join(', ')
                    : '—' }}
              </span>
            </div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">रासायनिक मल</span><span class="font-semibold">{{ form.agriculture.use_of_chem_fertilizer === 'yes' ? 'प्रयोग गरिन्छ' : form.agriculture.use_of_chem_fertilizer === 'no' ? 'प्रयोग गरिँदैन' : '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">रासायनिक बिषादी</span><span class="font-semibold">{{ form.agriculture.use_of_chem_pesticide === 'yes' ? 'प्रयोग गरिन्छ' : form.agriculture.use_of_chem_pesticide === 'no' ? 'प्रयोग गरिँदैन' : '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">बाली विवरण (Step 9)</span><span class="font-semibold">{{ Object.values(form.cropProduction).flat().filter(r => r.crop_name || r.area).length }} वटा</span></div>
          </div>
        </div>

        <!-- Livestock & Bee/Fish/Silk -->
        <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-2.5 flex items-center gap-2 border-b" style="background:#fdf4ff;border-color:#f5d0fe">
            <span class="w-5 h-5 rounded bg-fuchsia-600 text-white text-[9px] font-bold flex items-center justify-center shrink-0">10</span>
            <span class="text-xs font-bold text-fuchsia-800 uppercase">पशुपालन / मौरी–माछा–रेशम</span>
          </div>
          <div class="p-4 space-y-1.5 text-[13px]">
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">पशुपालन गर्नुहुन्छ?</span><span class="font-semibold">{{ form.livestockDetail.has_livestock === 'yes' ? 'छ ✓' : form.livestockDetail.has_livestock === 'no' ? 'छैन' : '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">पशु रेकर्ड</span><span class="font-semibold">{{ form.livestockDetail.livestock_rows.length }} वटा</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">पशु उत्पादन</span><span class="font-semibold">{{ form.livestockDetail.product_rows.length }} वटा</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">AI सेवा</span><span class="font-semibold">{{ form.livestockDetail.Ai_in_livestock === 'yes' ? 'छ ✓' : form.livestockDetail.Ai_in_livestock === 'no' ? 'छैन' : '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">मौरी/माछा/रेशम</span><span class="font-semibold">{{ form.beeFishSilk.has_bee_fish_silk === 'yes' ? 'छ ✓' : form.beeFishSilk.has_bee_fish_silk === 'no' ? 'छैन' : '—' }}</span></div>
          </div>
        </div>
      </div>

      <!-- ── Row 5: Disaster ── -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <!-- Disaster -->
        <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-2.5 flex items-center gap-2 border-b" style="background:#fff1f2;border-color:#fecdd3">
            <span class="w-5 h-5 rounded bg-rose-600 text-white text-[9px] font-bold flex items-center justify-center shrink-0">13</span>
            <span class="text-xs font-bold text-rose-800 uppercase">प्राकृतिक प्रकोप</span>
          </div>
          <div class="p-4 space-y-1.5 text-[13px]">
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">मृत्यु भयो?</span><span class="font-semibold">{{ form.disaster.has_death_this_year === 'yes' ? 'भयो (' + form.disaster.death_rows.length + ' जना)' : form.disaster.has_death_this_year === 'no' ? 'भएन' : '—' }}</span></div>
            <div class="flex justify-between gap-2">
              <span class="text-gray-500 shrink-0">जोखिम</span>
              <span class="font-semibold text-right text-[12px]">
                {{ form.disaster.risk_types.length ? form.disaster.risk_types.map(v => soLabel('disaster_type', v) || v).join(', ') : '—' }}
              </span>
            </div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">जोखिम न्युनिकरण योजना</span><span class="font-semibold">{{ form.disaster.risk_reduction_plan === 'yes' ? 'छ ✓' : form.disaster.risk_reduction_plan === 'no' ? 'छैन' : '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">प्रकोप पिडित (३ वर्ष)</span><span class="font-semibold">{{ form.disaster.affected_last_3years === 'yes' ? 'छ ✓' : form.disaster.affected_last_3years === 'no' ? 'छैन' : '—' }}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">मानवीय क्षति रेकर्ड</span><span class="font-semibold">{{ form.disaster.human_loss_rows.length }} वटा</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">भौतिक क्षति रेकर्ड</span><span class="font-semibold">{{ form.disaster.physical_loss_rows.length }} वटा</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">राहत पाउनुभयो?</span><span class="font-semibold">{{ form.disaster.received_relief === 'yes' ? 'पाइयो (' + form.disaster.relief_rows.length + ' रेकर्ड)' : form.disaster.received_relief === 'no' ? 'पाइएन' : '—' }}</span></div>
          </div>
        </div>

        <!-- Members summary card -->
        <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-2.5 flex items-center gap-2 border-b" style="background:#f8fafc;border-color:#cbd5e1">
            <span class="w-5 h-5 rounded bg-slate-600 text-white text-[9px] font-bold flex items-center justify-center shrink-0">14</span>
            <span class="text-xs font-bold text-slate-800 uppercase">परिवार सदस्य सारांश</span>
          </div>
          <div class="p-4 space-y-1.5 text-[13px]">
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">कुल सदस्य</span><span class="font-semibold">{{ form.members.length }} जना</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">पुरुष</span><span class="font-semibold">{{ form.members.filter(m => m.gender === 'male').length }} जना</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">महिला</span><span class="font-semibold">{{ form.members.filter(m => m.gender === 'female').length }} जना</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">अपाङ्गता भएका</span><span class="font-semibold">{{ form.members.filter(m => m.disability_status).length }} जना</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 shrink-0">नागरिकता भएका</span><span class="font-semibold">{{ form.members.filter(m => m.has_citizenship).length }} जना</span></div>
          </div>
        </div>
      </div>

      <!-- ── Members Full Table ── -->
      <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
        <div class="bg-[#182433] border-b border-[#182433] px-4 py-2.5 flex items-center gap-2">
          <i class="fa-solid fa-id-card text-white/70 text-sm"></i>
          <span class="text-xs font-bold text-white uppercase">परिवार सदस्यहरूको विवरण — {{ form.members.length }} जना</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-[12px]">
            <thead class="bg-gray-50 border-b border-gray-200">
              <tr>
                <th class="px-3 py-2 text-left font-semibold text-gray-600 w-6">#</th>
                <th class="px-3 py-2 text-left font-semibold text-gray-600">नाम</th>
                <th class="px-3 py-2 text-center font-semibold text-gray-600">उमेर</th>
                <th class="px-3 py-2 text-center font-semibold text-gray-600">लिंग</th>
                <th class="px-3 py-2 text-left font-semibold text-gray-600">सम्बन्ध</th>
                <th class="px-3 py-2 text-left font-semibold text-gray-600">शिक्षा</th>
                <th class="px-3 py-2 text-left font-semibold text-gray-600">पेशा</th>
                <th class="px-3 py-2 text-center font-semibold text-gray-600">नागरिकता</th>
                <th class="px-3 py-2 text-center font-semibold text-gray-600">अपाङ्गता</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="(m, i) in form.members" :key="i" class="hover:bg-gray-50 transition-colors"
                :class="m.is_household_head ? 'bg-blue-50/40' : ''">
                <td class="px-3 py-2 text-gray-400 font-medium">{{ i + 1 }}</td>
                <td class="px-3 py-2 font-semibold text-[#1d273b]">
                  {{ m.full_name || '—' }}
                  <span v-if="m.is_household_head" class="ml-1 text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-medium">घरमुली</span>
                </td>
                <td class="px-3 py-2 text-center">{{ m.age || '—' }}</td>
                <td class="px-3 py-2 text-center">{{ genderMapReview[m.gender] ?? m.gender ?? '—' }}</td>
                <td class="px-3 py-2">{{ soLabel('relation_to_head', m.relation_to_head) || '—' }}</td>
                <td class="px-3 py-2">{{ soLabel('education_level', m.education_level) || '—' }}</td>
                <td class="px-3 py-2">{{ soLabel('occupation_type', m.occupation_type) || '—' }}</td>
                <td class="px-3 py-2 text-center">
                  <span :class="m.has_citizenship ? 'text-green-600 font-semibold' : 'text-gray-400'">{{ m.has_citizenship ? '✓' : '—' }}</span>
                </td>
                <td class="px-3 py-2 text-center">
                  <span :class="m.disability_status ? 'text-rose-600 font-semibold' : 'text-gray-400'">{{ m.disability_status ? '✓' : '—' }}</span>
                </td>
              </tr>
              <tr v-if="!form.members.length">
                <td colspan="9" class="px-3 py-4 text-center text-[12px] text-gray-400 italic">कोही सदस्य थपिएको छैन</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Income & Expense mini-tables ── -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4" v-if="form.economic.income_rows.length || form.economic.expense_rows.length">

        <!-- Income rows -->
        <div v-if="form.economic.income_rows.length" class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-2.5 flex items-center gap-2 border-b bg-lime-50 border-lime-100">
            <i class="fa-solid fa-arrow-trend-up text-lime-600 text-sm"></i>
            <span class="text-xs font-bold text-lime-800 uppercase">आय विवरण</span>
          </div>
          <table class="w-full text-[12px]">
            <thead class="bg-gray-50 border-b border-gray-100">
              <tr>
                <th class="px-3 py-2 text-left font-semibold text-gray-600">आय स्रोत</th>
                <th class="px-3 py-2 text-right font-semibold text-gray-600">वार्षिक रकम (रु.)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="(row, i) in form.economic.income_rows" :key="i" class="hover:bg-gray-50">
                <td class="px-3 py-1.5">{{ soLabel('income_source', row.source) || row.source || '—' }}</td>
                <td class="px-3 py-1.5 text-right font-semibold">{{ row.annual_amount ? Number(row.annual_amount).toLocaleString() : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Expense rows -->
        <div v-if="form.economic.expense_rows.length" class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
          <div class="px-4 py-2.5 flex items-center gap-2 border-b bg-rose-50 border-rose-100">
            <i class="fa-solid fa-arrow-trend-down text-rose-500 text-sm"></i>
            <span class="text-xs font-bold text-rose-800 uppercase">खर्च विवरण</span>
          </div>
          <table class="w-full text-[12px]">
            <thead class="bg-gray-50 border-b border-gray-100">
              <tr>
                <th class="px-3 py-2 text-left font-semibold text-gray-600">खर्च शिर्षक</th>
                <th class="px-3 py-2 text-right font-semibold text-gray-600">वार्षिक रकम (रु.)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="(row, i) in form.economic.expense_rows" :key="i" class="hover:bg-gray-50">
                <td class="px-3 py-1.5">{{ soLabel('expense_category', row.category) || row.category || '—' }}</td>
                <td class="px-3 py-1.5 text-right font-semibold">{{ row.annual_amount ? Number(row.annual_amount).toLocaleString() : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      </div><!-- ╚══ END PRINTABLE AREA ══════════════════════════════════════ -->

      <!-- ── Submit note ── -->
      <div class="bg-green-50 border border-green-200 rounded-md p-4 flex items-start gap-3">
        <i class="fa-solid fa-circle-check text-green-500 text-xl mt-0.5 shrink-0"></i>
        <div>
          <p class="text-sm font-semibold text-green-800">सबै विवरण सही छ भने पेश गर्नुहोस्</p>
          <p class="text-xs text-green-600 mt-0.5">पेश गरेपछि वडा समीक्षकले यो सर्वेक्षण हेर्नेछन्।</p>
        </div>
      </div>

    </div>

    <!-- ══════════════════════════════════════════════════════════════
         NAVIGATION
    ══════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm px-4 py-3 flex flex-wrap items-center justify-between gap-3 sticky bottom-4">

      <!-- Left: Back + draft load/clear -->
      <div class="flex items-center gap-2">
        <button
          type="button"
          v-if="canGoBack"
          @click="prevStep"
          class="flex items-center gap-2 border border-[#e6e7e9] text-gray-700 rounded-md px-4 py-2 text-sm font-medium hover:bg-gray-50 transition-colors"
        >
          <i class="fa-solid fa-arrow-left text-xs"></i>
          पछाडि
        </button>

        <!-- Load Draft -->
        <button
          v-if="draftExists"
          type="button"
          @click="loadDraft"
          class="flex items-center gap-1.5 border border-amber-400 text-amber-700 rounded-md px-3 py-2 text-xs font-medium hover:bg-amber-50 transition-colors"
          title="ड्राफ्ट लोड गर्नुहोस्"
        >
          <i class="fa-solid fa-rotate-right text-[10px]"></i>
          ड्राफ्ट लोड
        </button>

        <!-- Clear Draft -->
        <button
          v-if="draftExists"
          type="button"
          @click="clearDraft"
          class="flex items-center gap-1.5 border border-red-300 text-red-600 rounded-md px-3 py-2 text-xs font-medium hover:bg-red-50 transition-colors"
          title="ड्राफ्ट खाली गर्नुहोस्"
        >
          <i class="fa-solid fa-trash-can text-[10px]"></i>
          ड्राफ्ट खाली
        </button>
      </div>

      <!-- Right: auto-save indicator + save draft + next/submit -->
      <div class="flex items-center gap-3">
        <!-- Auto-save status indicator -->
        <span v-if="autoSaveStatus === 'saving'" class="text-xs text-gray-400 flex items-center gap-1">
          <i class="fa-solid fa-spinner fa-spin text-[10px]"></i> सुरक्षित गर्दै...
        </span>
        <span v-else-if="autoSaveStatus === 'saved'" class="text-xs text-green-500 flex items-center gap-1">
          <i class="fa-solid fa-circle-check text-[10px]"></i> स्वतः सुरक्षित
        </span>
        <span v-else class="text-xs text-gray-400">{{ step }} / {{ stepLabels.length }}</span>

        <!-- Save Draft (to server) -->
        <button
          type="button"
          @click="saveDraft"
          :disabled="form.processing"
          class="flex items-center gap-1.5 border border-[#206bc4] text-[#206bc4] rounded-md px-3 py-2 text-xs font-medium hover:bg-blue-50 transition-colors disabled:opacity-60"
        >
          <i :class="['fa-solid text-[10px]', form.processing && form.save_draft ? 'fa-spinner fa-spin' : 'fa-cloud-arrow-up']"></i>
          ड्राफ्ट सुरक्षित
        </button>

        <button
          v-if="canGoNext"
          type="button"
          @click="nextStep"
          class="flex items-center gap-2 bg-[#206bc4] text-white rounded-md px-5 py-2 text-sm font-medium hover:bg-indigo-700 transition-colors"
        >
          अर्को
          <i class="fa-solid fa-arrow-right text-xs"></i>
        </button>

        <button
          v-else
          type="button"
          @click="submitForm"
          :disabled="form.processing"
          class="relative flex items-center gap-2 bg-[#206bc4] text-white rounded-md px-6 py-2 text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-60"
        >
          <i :class="['fa-solid text-xs', form.processing && !form.save_draft ? 'fa-spinner fa-spin' : 'fa-floppy-disk']"></i>
          {{ isEdit ? 'अद्यावधिक गर्नुहोस्' : 'सुरक्षित गर्नुहोस्' }}
          <span v-if="hasValidationErrors"
            class="absolute -top-2 -right-2 min-w-[18px] h-[18px] px-1 bg-amber-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center leading-none">
            {{ Math.min(validationErrorCount, 99) }}
          </span>
        </button>
      </div>
    </div>

  </div>

  <!-- ── Unsaved-changes leave modal ────────────────────────────────── -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="showLeaveModal"
        class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/50 backdrop-blur-sm px-4"
        @click.self="cancelLeave">
        <div class="w-full max-w-md bg-white rounded-xl shadow-2xl overflow-hidden">
          <div class="bg-red-500 px-5 py-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-red-400 flex items-center justify-center flex-shrink-0">
              <i class="fa-solid fa-triangle-exclamation text-white text-sm"></i>
            </div>
            <div>
              <p class="text-white font-semibold text-[14px]">असुरक्षित डेटा छ</p>
              <p class="text-red-100 text-[11px]">You have unsaved data</p>
            </div>
          </div>
          <div class="px-5 py-4 text-[13px] text-gray-700 space-y-2">
            <p>तपाईंले यस घरधुरी फारममा परिवर्तन गर्नुभएको छ तर अझै सुरक्षित गर्नुभएको छैन।</p>
            <p class="text-gray-500 text-[12px]">You have unsaved changes in this household form. If you leave now, your progress may be lost.</p>
          </div>
          <div class="px-5 pb-5 flex gap-3">
            <button type="button" @click="cancelLeave"
              class="flex-1 px-4 py-2.5 bg-indigo-600 text-white text-[13px] font-semibold rounded-lg hover:bg-indigo-700 transition">
              <i class="fa-solid fa-arrow-left text-xs mr-1"></i> रहनुहोस् (Stay)
            </button>
            <button type="button" @click="confirmLeave"
              class="px-4 py-2.5 border border-red-300 text-red-600 text-[13px] font-medium rounded-lg hover:bg-red-50 transition">
              छोड्नुहोस् (Leave)
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { useNavigationGuard } from '@/composables/useNavigationGuard'
import NepaliDatePicker from '@/components/NepaliDatePicker.vue'
import nepalGeoData from '@/data/nepal-localgov.json'

// ── Props ──────────────────────────────────────────────────────────
const props = defineProps({
  household:     { type: Object, default: null },
  wards:         { type: Array,  default: () => [] },
  settlements:   { type: Array,  default: () => [] },
  initialWardId: { type: Number, default: null },
  surveyOptions: { type: Object, default: () => ({}) },
  mapConfig:     { type: Object, default: () => ({}) },
})

// ── Page meta ──────────────────────────────────────────────────────
const isEdit = computed(() => props.household !== null)
const pageTitle = computed(() => isEdit.value ? 'घरधुरी सम्पादन' : 'घरधुरी सिर्जना')

// ── Data fill progress ─────────────────────────────────────────────
const fillProgress = computed(() => {
  const chk = []
  const v = (val) => Array.isArray(val) ? val.length > 0 : (val !== '' && val !== null && val !== undefined && val !== false)

  // Step 1
  chk.push(v(form.ward_id), v(form.respondent_name), v(form.survey_date))
  chk.push(v(form.housing.house_type), v(form.housing.roof_type), v(form.housing.wall_type))
  chk.push(v(form.housing.foundation_type), v(form.housing.ownership_type), v(form.housing.house_condition))
  chk.push(v(form.housing.total_rooms))

  // Step 2
  chk.push(v(form.social.family_head), v(form.social.male_num), v(form.social.female_num))
  chk.push(v(form.social.ethnicity), v(form.social.religion), v(form.social.mother_tongue))
  chk.push(v(form.social.family_type), v(form.social.major_family_occupation))

  // Step 3
  chk.push(v(form.utilities.water_source), v(form.utilities.electricity_source))
  chk.push(v(form.utilities.cooking_stove), v(form.utilities.solid_waste_management))

  // Step 4
  chk.push(v(form.decisions.decisioninHHWork), v(form.decisions.decisioninProperty))

  // Step 7
  chk.push(form.occupation.occupation_rows.length > 0)

  // Step 12
  chk.push(v(form.economic.primary_income_source), v(form.economic.annual_income_range))

  // Step 14
  chk.push(form.members.length > 0)

  const filled = chk.filter(Boolean).length
  return Math.round((filled / chk.length) * 100)
})

const fillColor = computed(() => {
  if (fillProgress.value < 30) return 'from-red-400 to-red-500'
  if (fillProgress.value < 60) return 'from-amber-400 to-orange-400'
  if (fillProgress.value < 85) return 'from-blue-400 to-[#206bc4]'
  return 'from-[#206bc4] to-green-500'
})

// ── Step management ────────────────────────────────────────────────
const step = ref(1)
const stepLabels = [
  'घर विवरण',
  'परिवार तथा सामाजिक',
  'सरसफाई तथा ऊर्जा',
  'घरपरिवारको निर्णय',
  'घरेलु सुविधाहरू',
  'खुसी र मेलमिलाप',
  'परिवारको पेशा',
  'खेतीपाती',
  'कृषि उत्पादन',
  'पशुपालन',
  'मौरी/माछा/रेशम',
  'आम्दानी र खर्च',
  'प्राकृतिक प्रकोप',
  'परिवार सदस्य',
  'अन्तिम समीक्षा',
]
const canGoBack = computed(() => step.value > 1)
const canGoNext = computed(() => step.value < stepLabels.length)

function scrollTop() { window.scrollTo({ top: 0, behavior: 'smooth' }) }
function nextStep() { if (canGoNext.value) { step.value++; scrollTop() } }
function prevStep() { if (canGoBack.value) { step.value--; scrollTop() } }
function goToStep(n) {
  if (isEdit.value || n <= step.value) { step.value = n; scrollTop() }
}

// ── Survey option label lookup ─────────────────────────────────────
function soLabel(category, value) {
  if (!value) return null
  const found = (props.surveyOptions[category] ?? []).find(o => o.value === value)
  return found ? found.label : value
}
const genderMapReview = { male: 'पुरुष', female: 'महिला', other: 'अन्य' }

// ── Print / PDF ────────────────────────────────────────────────────
function printReview(autoPrint = false) {
  const el = document.getElementById('review-print-area')
  if (!el) return

  const hh = props.household
  const code = hh?.unique_code ?? ''
  const name = form.respondent_name ?? ''
  const date = form.survey_date ?? new Date().toISOString().slice(0, 10)

  const win = window.open('', '_blank', 'width=960,height=800,scrollbars=yes')
  if (!win) { alert('Popup blocked — please allow popups for this site.'); return }

  win.document.write(`<!DOCTYPE html>
<html lang="ne">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=1200">
  <title>घरधुरी सर्वेक्षण — ${code || name}</title>
  <script src="https://cdn.tailwindcss.com"><\/script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;600;700&display=swap');

    /* ── Base ── */
    * { box-sizing: border-box; }
    body {
      font-family: 'Noto Sans Devanagari', sans-serif;
      font-size: 11px;
      background: #f8fafc;
      margin: 0; padding: 0;
    }

    /* ── Force 2-column grid for ALL card rows (screen + print) ── */
    .card-grid {
      display: grid !important;
      grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
      gap: 8px !important;
      margin-bottom: 8px;
    }

    /* ── Compact card internals ── */
    .card-grid > div > div:first-child { padding: 6px 10px !important; }
    .card-grid > div > div:not(:first-child) { padding: 8px 10px !important; }
    .card-grid .space-y-1\\.5 > * + * { margin-top: 3px !important; }
    .card-grid .text-\\[13px\\] { font-size: 11px !important; }
    .card-grid .text-xs { font-size: 10px !important; }
    .card-grid .text-sm { font-size: 11px !important; }

    /* ── Full-width sections ── */
    .full-row { margin-bottom: 8px; }
    .full-row table { font-size: 11px; }
    .full-row th, .full-row td { padding: 4px 8px !important; }

    /* ── Page header (print only) ── */
    .print-header {
      display: none;
      padding: 8px 12px 6px;
      border-bottom: 2px solid #182433;
      margin-bottom: 8px;
    }

    /* ── Print settings ── */
    @media print {
      @page { size: A4 landscape; margin: 10mm; }
      body {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        background: white !important;
        font-size: 10px;
      }
      .no-print { display: none !important; }
      .print-header { display: flex !important; justify-content: space-between; align-items: center; }

      /* Keep grid 2-col in print */
      .card-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 6px !important;
        break-inside: avoid;
      }

      /* Avoid splitting a card across pages */
      .card-grid > div { break-inside: avoid; page-break-inside: avoid; }

      /* Members table: don't break rows */
      .full-row tr { break-inside: avoid; page-break-inside: avoid; }
      .full-row .overflow-x-auto { overflow: visible !important; }

      /* Tighter spacing for print */
      .card-grid > div > div:first-child { padding: 5px 8px !important; }
      .card-grid > div > div:not(:first-child) { padding: 6px 8px !important; }
      .full-row th, .full-row td { padding: 3px 6px !important; }
    }
  </style>
</head>
<body>

  <!-- Toolbar — screen only -->
  <div class="no-print" style="position:sticky;top:0;z-index:50;background:#182433;color:white;padding:8px 16px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 8px rgba(0,0,0,.3)">
    <div style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600">
      <i class="fa-solid fa-clipboard-check" style="color:#93c5fd"></i>
      घरधुरी सर्वेक्षण सारांश
      ${code ? `<span style="background:rgba(255,255,255,.15);padding:2px 8px;border-radius:4px;font-size:11px;font-family:monospace">${code}</span>` : ''}
      <span style="background:rgba(255,255,255,.1);padding:2px 8px;border-radius:4px;font-size:11px">A4 Landscape · 2 columns</span>
    </div>
    <div style="display:flex;gap:8px">
      <button onclick="window.print()" style="background:white;color:#182433;border:none;padding:6px 14px;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px">
        <i class="fa-solid fa-print" style="font-size:10px"></i> Print / Save PDF
      </button>
      <button onclick="window.close()" style="background:rgba(255,255,255,.15);color:white;border:none;padding:6px 12px;border-radius:5px;font-size:12px;cursor:pointer">
        <i class="fa-solid fa-xmark"></i> बन्द
      </button>
    </div>
  </div>

  <!-- Page header — print only -->
  <div class="print-header">
    <div>
      <div style="font-size:14px;font-weight:700;color:#182433">घरधुरी सर्वेक्षण — सारांश</div>
      <div style="font-size:10px;color:#64748b">Household Survey Summary</div>
    </div>
    <div style="text-align:right;font-size:10px;color:#64748b">
      ${code ? `<div style="font-family:monospace;font-weight:700;color:#1e293b">${code}</div>` : ''}
      <div>${date} &nbsp;|&nbsp; ${name}</div>
    </div>
  </div>

  <!-- Review content — grids replaced with .card-grid class -->
  <div style="padding:8px 10px" id="print-body">
    ${el.innerHTML
        .replace(/class="grid grid-cols-1 md:grid-cols-2 gap-4"/g, 'class="card-grid"')
        .replace(/class="grid grid-cols-1 md:grid-cols-3 gap-4"/g, 'class="card-grid"')
        .replace(/class="grid grid-cols-1 md:grid-cols-2 gap-4" v-if[^"]*"/g, 'class="card-grid"')
        .replace(/<div class="overflow-x-auto">/g, '<div class="overflow-x-auto full-row">')
        .replace(/<div class="bg-white rounded-md border border-\[#e6e7e9\] overflow-hidden">\s*<div class="bg-\[#182433\]/g, '<div class="full-row bg-white rounded-md border border-[#e6e7e9] overflow-hidden"><div class="bg-[#182433]')
    }
  </div>

</body>
</html>`)

  win.document.close()
  if (autoPrint) {
    win.onload = () => setTimeout(() => win.print(), 600)
  }
}

// ── Facility options (from surveyOptions) ──────────────────────────
const facilityOptions = computed(() => props.surveyOptions.facilities ?? [])

// ── Vehicle type options (from surveyOptions) ──────────────────────

// ── GPS ────────────────────────────────────────────────────────────
const gpsLoading       = ref(false)
const gpsError         = ref('')
const gpsBoundaryResult = ref(null)

import { useGpsBoundary } from '@/composables/useGpsBoundary.js'
const { checkBoundary } = useGpsBoundary()

watch([() => form.gps_lat, () => form.gps_lng, () => form.ward_id], async ([lat, lng, wardId]) => {
  gpsBoundaryResult.value = null
  if (!lat || !lng || !wardId || !props.mapConfig?.geojson_url) return
  const wardNumber = props.wards.find(w => String(w.id) === String(wardId))?.ward_number
  if (!wardNumber) return
  gpsBoundaryResult.value = await checkBoundary(
    parseFloat(lat), parseFloat(lng),
    wardNumber,
    props.mapConfig.geojson_url,
    props.mapConfig.ward_number_property ?? 'WARD_NO'
  )
})

function getGPS() {
  if (!navigator.geolocation) {
    gpsError.value = 'यो ब्राउजरले GPS समर्थन गर्दैन।'
    return
  }
  gpsLoading.value = true
  gpsError.value = ''
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      form.gps_lat      = pos.coords.latitude
      form.gps_lng      = pos.coords.longitude
      form.gps_accuracy = pos.coords.accuracy
      form.gps_altitude = pos.coords.altitude ?? ''
      gpsLoading.value  = false
    },
    (err) => {
      gpsError.value   = 'GPS प्राप्त गर्न सकिएन: ' + err.message
      gpsLoading.value = false
    },
    { enableHighAccuracy: true, timeout: 15000 }
  )
}

// ── Reference data ─────────────────────────────────────────────────

const decisionFields = [
  { key: 'decisioninHHWork',     label: 'घरायसी काममा निर्णय',       en: 'Decision in Household Work' },
  { key: 'decisioninProperty',   label: 'सम्पत्तिमा निर्णय',          en: 'Decision in Property Matters' },
  { key: 'decisioninCCommittee', label: 'सामुदायिक समितिमा निर्णय',   en: 'Decision in Community Committee' },
  { key: 'decisioninSMC',        label: 'SMC मा निर्णय',              en: 'Decision in SMC' },
  { key: 'decisioninBusiness',   label: 'व्यापारमा निर्णय',            en: 'Decision in Business' },
]

const farmChallengeOptions = computed(() => props.surveyOptions.farm_challenge ?? [])

// ── Agriculture category definitions (step 8 → step 9 mapping) ───────
const agriCategoryDefs = [
  {
    key: 'food_crops',  cropCategory: 'crop_food',
    label: 'अन्न बाली',       labelEn: 'Food Crops',
    icon: 'fa-solid fa-wheat-awn', iconColor: 'text-green-700',
    headerBg: 'bg-green-100/70', bodyBg: 'bg-green-50/30',
    titleColor: 'text-green-800', theadBg: 'bg-green-100/80 text-green-800',
    addBtnClass: 'border-green-400 text-green-700 hover:bg-green-50',
  },
  {
    key: 'pulses',      cropCategory: 'crop_pulse',
    label: 'दलहन बाली',       labelEn: 'Pulses',
    icon: 'fa-solid fa-seedling', iconColor: 'text-teal-700',
    headerBg: 'bg-teal-100/70', bodyBg: 'bg-teal-50/30',
    titleColor: 'text-teal-800', theadBg: 'bg-teal-100/80 text-teal-800',
    addBtnClass: 'border-teal-400 text-teal-700 hover:bg-teal-50',
  },
  {
    key: 'oil_seeds',   cropCategory: 'crop_oil',
    label: 'तेलहन बाली',      labelEn: 'Oil Seeds',
    icon: 'fa-solid fa-droplet', iconColor: 'text-yellow-700',
    headerBg: 'bg-yellow-100/70', bodyBg: 'bg-yellow-50/30',
    titleColor: 'text-yellow-800', theadBg: 'bg-yellow-100/80 text-yellow-800',
    addBtnClass: 'border-yellow-400 text-yellow-700 hover:bg-yellow-50',
  },
  {
    key: 'vegetables',  cropCategory: 'crop_vegetable',
    label: 'तरकारी खेती',     labelEn: 'Vegetables',
    icon: 'fa-solid fa-carrot', iconColor: 'text-orange-600',
    headerBg: 'bg-orange-100/70', bodyBg: 'bg-orange-50/30',
    titleColor: 'text-orange-800', theadBg: 'bg-orange-100/80 text-orange-800',
    addBtnClass: 'border-orange-400 text-orange-700 hover:bg-orange-50',
  },
  {
    key: 'cash_crops',  cropCategory: 'crop_cash',
    label: 'नगदेबाली',         labelEn: 'Cash Crops',
    icon: 'fa-solid fa-coins', iconColor: 'text-amber-700',
    headerBg: 'bg-amber-100/70', bodyBg: 'bg-amber-50/30',
    titleColor: 'text-amber-800', theadBg: 'bg-amber-100/80 text-amber-800',
    addBtnClass: 'border-amber-400 text-amber-700 hover:bg-amber-50',
  },
  {
    key: 'fruits',      cropCategory: 'crop_fruit',
    label: 'फलफुल खेति',      labelEn: 'Fruits',
    icon: 'fa-solid fa-apple-whole', iconColor: 'text-rose-600',
    headerBg: 'bg-rose-100/70', bodyBg: 'bg-rose-50/30',
    titleColor: 'text-rose-800', theadBg: 'bg-rose-100/80 text-rose-800',
    addBtnClass: 'border-rose-400 text-rose-700 hover:bg-rose-50',
  },
  {
    key: 'other_crops', cropCategory: 'crop_other',
    label: 'अन्य खेति',       labelEn: 'Other Crops',
    icon: 'fa-solid fa-leaf', iconColor: 'text-violet-600',
    headerBg: 'bg-violet-100/70', bodyBg: 'bg-violet-50/30',
    titleColor: 'text-violet-800', theadBg: 'bg-violet-100/80 text-violet-800',
    addBtnClass: 'border-violet-400 text-violet-700 hover:bg-violet-50',
  },
]


const riskTypeOptions          = computed(() => props.surveyOptions.disaster_type        ?? [])
const deathCauseOptions        = computed(() => props.surveyOptions.death_cause           ?? [])
const disasterPropertyOptions  = computed(() => props.surveyOptions.disaster_property_loss ?? [])
const reliefTypeOptions        = computed(() => props.surveyOptions.relief_type           ?? [])
const reliefUnitOptions        = computed(() => props.surveyOptions.relief_unit           ?? [])
const reliefSourceOptions      = computed(() => props.surveyOptions.relief_source         ?? [])

// ── Form (useForm) ─────────────────────────────────────────────────
const hh = props.household
const form = useForm({
  ward_id:            hh?.ward_id             ?? '',
  settlement_id:      hh?.settlement_id       ?? '',
  respondent_name:    hh?.respondent_name     ?? '',
  respondent_contact: hh?.respondent_contact  ?? '',
  survey_date:        hh?.survey_date         ?? '',
  remarks:            hh?.remarks             ?? '',
  gps_lat:            hh?.gps_lat             ?? '',
  gps_lng:            hh?.gps_lng             ?? '',
  gps_accuracy:       hh?.gps_accuracy        ?? '',
  gps_altitude:       hh?.gps_altitude        ?? '',
  photo_number:       hh?.photo_number        ?? '',
  save_draft:           false,
  duplicate_confirmed:  false,

  location: {
    province:           hh?.survey_data?.location?.province           ?? '',
    district:           hh?.survey_data?.location?.district           ?? '',
    local_gov_name:     hh?.survey_data?.location?.local_gov_name     ?? '',
  },

  housing: {
    house_land_type:      hh?.housing?.house_land_type      ?? '',
    land_measurement_unit:hh?.housing?.land_measurement_unit?? '',
    house_type:           hh?.housing?.house_type           ?? '',
    roof_type:            hh?.housing?.roof_type            ?? '',
    wall_type:            hh?.housing?.wall_type            ?? '',
    foundation_type:      hh?.housing?.foundation_type      ?? '',
    ownership_type:       hh?.housing?.ownership_type       ?? '',
    rent_amount:          hh?.housing?.rent_amount          ?? '',
    total_rooms:          hh?.housing?.total_rooms          ?? '',
    total_floors:         hh?.housing?.total_floors         ?? '',
    has_building_permit:  hh?.housing?.has_building_permit  ?? false,
    construction_year:    hh?.housing?.construction_year    ?? '',
    house_condition:      hh?.housing?.house_condition      ?? '',
    is_at_risk:           hh?.housing?.is_at_risk           ?? false,
    risk_types:           hh?.housing?.risk_types           ?? [],
    house_purpose:          hh?.housing?.house_purpose          ?? '',
    location_house_built:   hh?.housing?.location_house_built   ?? '',
    distance_to_mainroad:   hh?.housing?.distance_to_mainroad   ?? '',
    distance_to_school:     hh?.housing?.distance_to_school     ?? '',
  },

  social: {
    family_head:            hh?.survey_data?.social?.family_head            ?? '',
    contact_no_fam_head:    hh?.survey_data?.social?.contact_no_fam_head    ?? '',
    male_num:               hh?.survey_data?.social?.male_num               ?? '',
    female_num:             hh?.survey_data?.social?.female_num             ?? '',
    other_num:              hh?.survey_data?.social?.other_num              ?? '',
    family_type:            hh?.survey_data?.social?.family_type            ?? '',
    ethnicity:              hh?.survey_data?.social?.ethnicity              ?? '',
    religion:               hh?.survey_data?.social?.religion               ?? '',
    mother_tongue:          hh?.survey_data?.social?.mother_tongue          ?? '',
    com_language:           hh?.survey_data?.social?.com_language           ?? '',
    major_family_occupation:hh?.survey_data?.social?.major_family_occupation?? '',
    settlement_type:        hh?.survey_data?.social?.settlement_type        ?? '',
    migration_status:         hh?.survey_data?.social?.migration_status         ?? '',
    migration_last_5years:    hh?.survey_data?.social?.migration_last_5years    ?? '',
    migration_registered:     hh?.survey_data?.social?.migration_registered     ?? '',
    migration_purpose:        hh?.survey_data?.social?.migration_purpose        ?? '',
    migration_from_province:  hh?.survey_data?.social?.migration_from_province  ?? '',
    migration_from_district:  hh?.survey_data?.social?.migration_from_district  ?? '',
    migration_from_local_gov: hh?.survey_data?.social?.migration_from_local_gov ?? '',
    senior_in_old_age_home:   hh?.survey_data?.social?.senior_in_old_age_home   ?? '',
    seniors_list:             hh?.survey_data?.social?.seniors_list             ?? [],
    have_land:                hh?.survey_data?.social?.have_land                ?? '',
    land_unit:              hh?.survey_data?.social?.land_unit              ?? '',
    land_khet:              hh?.survey_data?.social?.land_khet              ?? '',
    land_bari:              hh?.survey_data?.social?.land_bari              ?? '',
    land_pakho:             hh?.survey_data?.social?.land_pakho             ?? '',
    consumption:            hh?.survey_data?.social?.consumption            ?? '',
    health_insurance:       hh?.survey_data?.social?.health_insurance       ?? '',
    bankloan:               hh?.survey_data?.social?.bankloan               ?? '',
    loansource:             hh?.survey_data?.social?.loansource             ?? [],
    loanpurpose:            hh?.survey_data?.social?.loanpurpose            ?? [],
    orgparticipation:       hh?.survey_data?.social?.orgparticipation       ?? [],
    death:                  hh?.survey_data?.social?.death                  ?? '',
    deaths:                 hh?.survey_data?.social?.deaths                 ?? [],
  },

  utilities: {
    water_source:            hh?.utilities?.water_source            ?? '',
    water_distance_minutes:  hh?.utilities?.water_distance_minutes  ?? '',
    has_toilet:              hh?.utilities?.has_toilet              ?? false,
    toilet_type:             hh?.utilities?.toilet_type             ?? '',
    sanitation_type:         hh?.utilities?.sanitation_type         ?? '',
    electricity_source:       hh?.utilities?.electricity_source       ?? '',
    monthly_electricity_units:hh?.utilities?.monthly_electricity_units ?? '',
    has_alt_electricity:      hh?.utilities?.has_alt_electricity      ?? '',
    alt_electricity_sources:  Array.isArray(hh?.utilities?.alt_electricity_sources) ? hh.utilities.alt_electricity_sources : (hh?.utilities?.alt_electricity_sources ? [hh.utilities.alt_electricity_sources] : []),
    cooking_fuel_arr:        Array.isArray(hh?.utilities?.cooking_fuel_arr) ? hh.utilities.cooking_fuel_arr : (hh?.utilities?.cooking_fuel_arr ? [hh.utilities.cooking_fuel_arr] : []),
    cooking_stove:           hh?.utilities?.cooking_stove           ?? '',
    uses_firewood:           hh?.utilities?.uses_firewood           ?? '',
    firewood_source:         Array.isArray(hh?.utilities?.firewood_source) ? hh.utilities.firewood_source : (hh?.utilities?.firewood_source ? [hh.utilities.firewood_source] : []),
    has_biogas:              hh?.utilities?.has_biogas              ?? false,
    solid_waste_management:  hh?.utilities?.solid_waste_management  ?? '',
    has_internet:            hh?.utilities?.has_internet            ?? false,
    internet_type:           hh?.utilities?.internet_type           ?? '',
    handwashing:             hh?.utilities?.handwashing             ?? '',
    access_to_facilities:    hh?.utilities?.access_to_facilities    ?? [],
    vehicle:                 hh?.utilities?.vehicle                 ?? '',
    vehicles:                hh?.utilities?.vehicles                ?? [],
  },

  health: {
    aware_handwashing:        hh?.survey_data?.health?.aware_handwashing        ?? '',
    illness_frequency:        hh?.survey_data?.health?.illness_frequency        ?? '',
    treatment_place:          hh?.survey_data?.health?.treatment_place          ?? '',
    time_to_health_center:    hh?.survey_data?.health?.time_to_health_center    ?? '',
    aware_nutrition:          hh?.survey_data?.health?.aware_nutrition          ?? '',
    aware_pregnant_nutrition: hh?.survey_data?.health?.aware_pregnant_nutrition ?? '',
    aware_iron_capsule:       hh?.survey_data?.health?.aware_iron_capsule       ?? '',
    has_pregnant_woman:       hh?.survey_data?.health?.has_pregnant_woman       ?? '',
    pregnancy_months:         hh?.survey_data?.health?.pregnancy_months         ?? '',
    pregnant_age:             hh?.survey_data?.health?.pregnant_age             ?? '',
    has_health_insurance:     hh?.survey_data?.health?.has_health_insurance     ?? '',
  },

  wellbeing: {
    happy_with_location:      hh?.survey_data?.wellbeing?.happy_with_location      ?? '',
    residence_perception:     hh?.survey_data?.wellbeing?.residence_perception     ?? '',
    family_gathering_freq:    hh?.survey_data?.wellbeing?.family_gathering_freq    ?? '',
    happiness_activities:     Array.isArray(hh?.survey_data?.wellbeing?.happiness_activities) ? hh.survey_data.wellbeing.happiness_activities : (hh?.survey_data?.wellbeing?.happiness_activities ? [hh.survey_data.wellbeing.happiness_activities] : []),
    neighbors_nice:           hh?.survey_data?.wellbeing?.neighbors_nice           ?? '',
    neighbor_cooperation:     hh?.survey_data?.wellbeing?.neighbor_cooperation     ?? '',
  },

  decisions: {
    decisioninHHWork:      hh?.survey_data?.decisions?.decisioninHHWork      ?? '',
    decisioninProperty:    hh?.survey_data?.decisions?.decisioninProperty    ?? '',
    decisioninCCommittee:  hh?.survey_data?.decisions?.decisioninCCommittee  ?? '',
    decisioninSMC:         hh?.survey_data?.decisions?.decisioninSMC         ?? '',
    decisioninBusiness:    hh?.survey_data?.decisions?.decisioninBusiness    ?? '',
    org_participation:       hh?.survey_data?.decisions?.org_participation       ?? '',
    org_participation_types: hh?.survey_data?.decisions?.org_participation_types ?? [],
    has_bank_account:        hh?.survey_data?.decisions?.has_bank_account        ?? '',
    bank_account_owner:    hh?.survey_data?.decisions?.bank_account_owner    ?? '',
  },

  occupation: {
    occupation_rows:  Array.isArray(hh?.survey_data?.occupation?.occupation_rows) ? hh.survey_data.occupation.occupation_rows : [],
    foreign_india:    hh?.survey_data?.occupation?.foreign_india    ?? '',
    foreign_gulf:     hh?.survey_data?.occupation?.foreign_gulf     ?? '',
    foreign_others:   hh?.survey_data?.occupation?.foreign_others   ?? '',
    skill_training:   hh?.survey_data?.occupation?.skill_training   ?? '',
    skill_rows:       Array.isArray(hh?.survey_data?.occupation?.skill_rows) ? hh.survey_data.occupation.skill_rows : [],
  },

  agriculture: {
    agriculture:            hh?.agriculture?.agriculture            ?? '',
    agri_categories:        Array.isArray(hh?.agriculture?.agri_categories) ? hh.agriculture.agri_categories : (hh?.agriculture?.agri_categories ? [hh.agriculture.agri_categories] : []),
    use_of_chem_fertilizer: hh?.agriculture?.use_of_chem_fertilizer ?? '',
    chem_fertilizer_types:  Array.isArray(hh?.agriculture?.chem_fertilizer_types) ? hh.agriculture.chem_fertilizer_types : [],
    chem_fertilizer_kg:     hh?.agriculture?.chem_fertilizer_kg     ?? '',
    fertilizer_sources:     Array.isArray(hh?.agriculture?.fertilizer_sources) ? hh.agriculture.fertilizer_sources : [],
    use_of_chem_pesticide:  hh?.agriculture?.use_of_chem_pesticide  ?? '',
    chem_pesticide_types:   Array.isArray(hh?.agriculture?.chem_pesticide_types) ? hh.agriculture.chem_pesticide_types : [],
    chem_pesticide_cost:    hh?.agriculture?.chem_pesticide_cost    ?? '',
    Farm_Challenges:        Array.isArray(hh?.agriculture?.Farm_Challenges) ? hh.agriculture.Farm_Challenges : [],
    owns_land:              hh?.agriculture?.owns_land              ?? false,
    land_area_ropani:       hh?.agriculture?.land_area_ropani       ?? '',
    irrigated_area_ropani:  hh?.agriculture?.irrigated_area_ropani  ?? '',
    annual_agri_income:     hh?.agriculture?.annual_agri_income     ?? '',
  },

  cropProduction: {
    food_crops:  Array.isArray(hh?.survey_data?.cropProduction?.food_crops)  ? hh.survey_data.cropProduction.food_crops  : [],
    pulses:      Array.isArray(hh?.survey_data?.cropProduction?.pulses)      ? hh.survey_data.cropProduction.pulses      : [],
    oil_seeds:   Array.isArray(hh?.survey_data?.cropProduction?.oil_seeds)   ? hh.survey_data.cropProduction.oil_seeds   : [],
    vegetables:  Array.isArray(hh?.survey_data?.cropProduction?.vegetables)  ? hh.survey_data.cropProduction.vegetables  : [],
    cash_crops:  Array.isArray(hh?.survey_data?.cropProduction?.cash_crops)  ? hh.survey_data.cropProduction.cash_crops  : [],
    fruits:      Array.isArray(hh?.survey_data?.cropProduction?.fruits)      ? hh.survey_data.cropProduction.fruits      : [],
    other_crops: Array.isArray(hh?.survey_data?.cropProduction?.other_crops) ? hh.survey_data.cropProduction.other_crops : [],
  },

  livestock: hh?.livestock ?? [],

  livestockDetail: {
    has_livestock:    hh?.survey_data?.livestockDetail?.has_livestock    ?? '',
    livestock_rows:   Array.isArray(hh?.survey_data?.livestockDetail?.livestock_rows)  ? hh.survey_data.livestockDetail.livestock_rows  : [],
    product_rows:     Array.isArray(hh?.survey_data?.livestockDetail?.product_rows)    ? hh.survey_data.livestockDetail.product_rows    : [],
    Ai_in_livestock:  hh?.survey_data?.livestockDetail?.Ai_in_livestock  ?? '',
  },

  beeFishSilk: {
    has_bee_fish_silk:            hh?.survey_data?.beeFishSilk?.has_bee_fish_silk            ?? '',
    bee_fish_silk_select:         hh?.survey_data?.beeFishSilk?.bee_fish_silk_select         ?? [],
    Mudhe_Hive:                   hh?.survey_data?.beeFishSilk?.Mudhe_Hive                   ?? '',
    Modern_Hive:                  hh?.survey_data?.beeFishSilk?.Modern_Hive                  ?? '',
    Honey_Production_Mudhe_Hive:  hh?.survey_data?.beeFishSilk?.Honey_Production_Mudhe_Hive  ?? '',
    Honey_Production_Modern_Hive: hh?.survey_data?.beeFishSilk?.Honey_Production_Modern_Hive ?? '',
    Bee_Training:                 hh?.survey_data?.beeFishSilk?.Bee_Training                 ?? '',
    Pond_area:                    hh?.survey_data?.beeFishSilk?.Pond_area                    ?? '',
    Sik:                          hh?.survey_data?.beeFishSilk?.Sik                          ?? '',
    milk_prod:                    hh?.survey_data?.beeFishSilk?.milk_prod                    ?? '',
    milk_sales:                   hh?.survey_data?.beeFishSilk?.milk_sales                   ?? '',
    fish_prod:                    hh?.survey_data?.beeFishSilk?.fish_prod                    ?? '',
    fish_sales:                   hh?.survey_data?.beeFishSilk?.fish_sales                   ?? '',
    silk_prod:                    hh?.survey_data?.beeFishSilk?.silk_prod                    ?? '',
    silk_sales:                   hh?.survey_data?.beeFishSilk?.silk_sales                   ?? '',
    honey_prod:                   hh?.survey_data?.beeFishSilk?.honey_prod                   ?? '',
    honey_sales:                  hh?.survey_data?.beeFishSilk?.honey_sales                  ?? '',
  },

  economic: {
    income_rows:                Array.isArray(hh?.economic?.income_rows)   ? hh.economic.income_rows   : [],
    expense_rows:               Array.isArray(hh?.economic?.expense_rows)  ? hh.economic.expense_rows  : [],
    primary_income_source:      hh?.economic?.primary_income_source      ?? '',
    annual_income_range:        hh?.economic?.annual_income_range        ?? '',
    annual_expenditure_range:   hh?.economic?.annual_expenditure_range   ?? '',
    remittance_amount:          hh?.economic?.remittance_amount          ?? '',
    loan_sources:               hh?.economic?.loan_sources               ?? [],
    total_loan_amount:          hh?.economic?.total_loan_amount          ?? '',
    has_bank_account:           hh?.economic?.has_bank_account           ?? false,
    has_insurance:              hh?.economic?.has_insurance              ?? false,
    poverty_card:               hh?.economic?.poverty_card               ?? false,
    social_security_allowance:  hh?.economic?.social_security_allowance  ?? false,
    income_source:              hh?.economic?.income_source              ?? [],
    agri_livestock_income:      hh?.economic?.agri_livestock_income      ?? '',
    business_industry_income:   hh?.economic?.business_industry_income   ?? '',
    employment_service_income:  hh?.economic?.employment_service_income  ?? '',
    abroad_job_income:          hh?.economic?.abroad_job_income          ?? '',
    food_expense:               hh?.economic?.food_expense               ?? '',
    clothes_expense:            hh?.economic?.clothes_expense            ?? '',
    education_expense:          hh?.economic?.education_expense          ?? '',
    treatment_expense:          hh?.economic?.treatment_expense          ?? '',
    other_expense:              hh?.economic?.other_expense              ?? '',
  },

  disaster: {
    has_death_this_year:  hh?.disaster?.has_death_this_year  ?? '',
    death_rows:           Array.isArray(hh?.disaster?.death_rows)         ? hh.disaster.death_rows         : [],
    risk_types:           hh?.disaster?.risk_types           ?? [],
    risk_reduction_plan:  hh?.disaster?.risk_reduction_plan  ?? '',
    affected_last_3years: hh?.disaster?.affected_last_3years ?? '',
    human_loss_rows:      Array.isArray(hh?.disaster?.human_loss_rows)    ? hh.disaster.human_loss_rows    : [],
    physical_loss_rows:   Array.isArray(hh?.disaster?.physical_loss_rows) ? hh.disaster.physical_loss_rows : [],
    received_relief:      hh?.disaster?.received_relief      ?? '',
    relief_rows:          Array.isArray(hh?.disaster?.relief_rows)        ? hh.disaster.relief_rows        : [],
  },

  members: (hh?.members ?? []).map(m => ({
    _id:                      m.id ?? null,
    is_household_head:        m.is_household_head        ?? false,
    full_name:                m.full_name                ?? '',
    father_name:              m.father_name              ?? '',
    relation_to_head:         m.relation_to_head         ?? '',
    age:                      m.age                      ?? '',
    gender:                   m.gender                   ?? '',
    // Marital
    marital_status:           m.marital_status           ?? '',
    ageofmarriage:            m.ageofmarriage            ?? '',
    // Blood group
    blood_group_tested:       m.blood_group_tested       ?? '',
    blood_group:              m.blood_group              ?? '',
    // Birth cert (age ≤ 18)
    has_birth_certificate:    m.has_birth_certificate    ?? false,
    // ── Age 0–3 ───────────────────────────────────
    childbornplace:           m.childbornplace           ?? '',
    child_weight_in_birth:    m.child_weight_in_birth    ?? '',
    colostrum:                m.colostrum                ?? null,
    childvaccine:             Array.isArray(m.childvaccine) ? m.childvaccine : [],
    vit_a_dewormin:           m.vit_a_dewormin           ?? null,
    fullmilkcourse:           m.fullmilkcourse           ?? null,
    durationbrestfeed:        m.durationbrestfeed        ?? '',
    childhealthcheckup:       m.childhealthcheckup       ?? null,
    childbmi:                 m.childbmi                 ?? null,
    child_caregiver:          m.child_caregiver          ?? '',
    diarrhea_before_two_weeks:m.diarrhea_before_two_weeks ?? null,
    fever_resp:               m.fever_resp               ?? null,
    // ── Age 4–5 ───────────────────────────────────
    vitaminA:                 m.vitaminA                 ?? null,
    childphydev:              m.childphydev              ?? '',
    childlgdev:               m.childlgdev               ?? '',
    childsocialization:       m.childsocialization       ?? '',
    // ── Age 6–15 ──────────────────────────────────
    phy_dev_6_15:             m.phy_dev_6_15             ?? null,
    childphydev_6_15:         m.childphydev_6_15         ?? '',
    childlgdev_6_15:          m.childlgdev_6_15          ?? '',
    school_interest_6_15:     m.school_interest_6_15     ?? '',
    interesting_subject_6_15: m.interesting_subject_6_15 ?? '',
    hw_support_6_15:          m.hw_support_6_15          ?? '',
    allergy_infection_6_15:   m.allergy_infection_6_15   ?? null,
    watch_mobile_tv_6_15:     m.watch_mobile_tv_6_15     ?? null,
    digital_habit_6_15:       m.digital_habit_6_15       ?? '',
    other_interest_6_15:      m.other_interest_6_15      ?? '',
    // Child labor
    child_labor:              m.child_labor              ?? null,
    workplace_child_labor:    m.workplace_child_labor    ?? '',
    // Disability
    disability_status:        m.disability_status        ?? false,
    disability_type:          m.disability_type          ?? '',
    disability_card:          m.disability_card          ?? '',
    disability_card_color:    m.disability_card_color    ?? '',
    // Chronic illness
    chronic_illness:          m.chronic_illness          ?? false,
    chronic_illness_types:    Array.isArray(m.chronic_illness_types) ? m.chronic_illness_types : [],
    // Education
    education_level:          m.education_level          ?? '',
    education_status:         m.education_status         ?? '',
    current_class:            m.current_class            ?? '',
    higher_education_level:   m.higher_education_level   ?? '',
    school_type:              m.school_type              ?? '',
    school_performance:       m.school_performance       ?? '',
    school_location:          m.school_location          ?? '',
    school_name:              m.school_name              ?? '',
    cause_not_school:         Array.isArray(m.cause_not_school) ? m.cause_not_school : [],
    // Occupation
    occupation_type:          m.occupation_type          ?? '',
    employment_status:        m.employment_status        ?? '',
    // Family
    no_birth_given:           m.no_birth_given           ?? '',
    // Lifestyle
    smoking_drinking:         m.smoking_drinking         ?? false,
    smoking_drinking_items:   Array.isArray(m.smoking_drinking_items) ? m.smoking_drinking_items : [],
    frequency_smoking_drinking: m.frequency_smoking_drinking ?? '',
    // Social security
    ss_allowance:             m.ss_allowance             ?? false,
    // Documents
    has_citizenship:          m.has_citizenship          ?? false,
    no_citizenship_reason:    m.no_citizenship_reason    ?? '',
    has_voters_card:          m.has_voters_card          ?? false,
    has_national_id_card:     m.has_national_id_card     ?? false,
    has_passport:             m.has_passport             ?? false,
    passport_info:            m.passport_info            ?? false,
    passport_number:          m.passport_number          ?? '',
    passport_issued_district: m.passport_issued_district ?? '',
    passport_issued_date:     m.passport_issued_date     ?? '',
    passport_expiry_date:     m.passport_expiry_date     ?? '',
    has_pan:                  m.has_pan                  ?? false,
    has_drivers_license:      m.has_drivers_license      ?? false,
    drivers_license_category: Array.isArray(m.drivers_license_category) ? m.drivers_license_category : [],
    // Contact info (age > 15)
    has_phone:                m.has_phone                ?? null,
    know_phone:               m.know_phone               ?? null,
    member_phone:             m.member_phone             ?? '',
    has_email:                m.has_email                ?? null,
    know_email:               m.know_email               ?? null,
    member_email:             m.member_email             ?? '',
    // Vocational training (age > 15)
    vocational_training:      m.vocational_training      ?? null,
    vt_subject:               m.vt_subject               ?? '',
    vt_duration_min:          m.vt_duration_min          ?? '',
    vt_duration_max:          m.vt_duration_max          ?? '',
    // Technical training (age > 15)
    techtraining:             m.techtraining             ?? null,
    techtraining_subject:     m.techtraining_subject     ?? '',
    tech_duration_min:        m.tech_duration_min        ?? '',
    tech_duration_max:        m.tech_duration_max        ?? '',
    // SA training (age > 15)
    sa_training:              m.sa_training              ?? null,
    sa_training_subject:      m.sa_training_subject      ?? '',
    sa_duration_min:          m.sa_duration_min          ?? '',
    sa_duration_max:          m.sa_duration_max          ?? '',
    // Address / location
    addrslocation:            m.addrslocation            ?? '',
    residing_district:        m.residing_district        ?? '',
    purpose_living_outside:   m.purpose_living_outside   ?? '',
    duration_of_stay:         m.duration_of_stay         ?? '',
    residing_country:         Array.isArray(m.residing_country) ? m.residing_country : [],
    purpose_living_abroad:    m.purpose_living_abroad    ?? '',
    typeofabroadjob:          m.typeofabroadjob          ?? '',
    abroadjobstatus:          m.abroadjobstatus          ?? '',
    // Business (age > 15)
    involveinbusiness:        m.involveinbusiness        ?? null,
    business_name:            m.business_name            ?? '',
    // Residence / remarks
    residence_status:         m.residence_status         ?? '',
    remarks:                  m.remarks                  ?? '',
  })),
})

// ── Computed ───────────────────────────────────────────────────────
const filteredSettlements = computed(() =>
  form.ward_id
    ? props.settlements.filter(s => String(s.ward_id) === String(form.ward_id))
    : props.settlements
)

// ── Nepal Geo: Province list (used by migration origin + future steps) ──
const provinceList = computed(() => nepalGeoData.provinces)

// ── Migration origin: cascading Province → District → Local Gov ───
const migFromDistrictList = computed(() =>
  form.social.migration_from_province
    ? (nepalGeoData.districts[form.social.migration_from_province] ?? [])
    : []
)

const migFromLocalGovList = computed(() =>
  form.social.migration_from_district
    ? (nepalGeoData.localGovs[form.social.migration_from_district] ?? [])
    : []
)

watch(() => form.social.migration_from_province, () => {
  form.social.migration_from_district  = ''
  form.social.migration_from_local_gov = ''
})

watch(() => form.social.migration_from_district, () => {
  form.social.migration_from_local_gov = ''
})

// ── Seniors helpers ────────────────────────────────────────────────
function addSenior() {
  form.social.seniors_list.push({ name: '', age: '', gender: '' })
}
function removeSenior(idx) {
  form.social.seniors_list.splice(idx, 1)
}

// ── Occupation row helpers ────────────────────────────────────────
function addOccupationRow() {
  form.occupation.occupation_rows.push({ sector: '', count: '' })
}
function removeOccupationRow(idx) {
  form.occupation.occupation_rows.splice(idx, 1)
}

// ── Skill row helpers ─────────────────────────────────────────────
function addSkillRow() {
  form.occupation.skill_rows.push({ skill: '', count: '' })
}
function removeSkillRow(idx) {
  form.occupation.skill_rows.splice(idx, 1)
}

// ── Economic row helpers ──────────────────────────────────────────
function addIncomeRow() {
  form.economic.income_rows.push({ source: '', annual_amount: '' })
}
function removeIncomeRow(idx) {
  form.economic.income_rows.splice(idx, 1)
}
function addExpenseRow() {
  form.economic.expense_rows.push({ category: '', annual_amount: '' })
}
function removeExpenseRow(idx) {
  form.economic.expense_rows.splice(idx, 1)
}

// ── Disaster row helpers ──────────────────────────────────────────
function addDeathRow() {
  form.disaster.death_rows.push({ name: '', gender: '', age: '', cause: '' })
}
function removeDeathRow(idx) { form.disaster.death_rows.splice(idx, 1) }

function addHumanLossRow() {
  form.disaster.human_loss_rows.push({ disaster_type: '', deaths: '', injured: '' })
}
function removeHumanLossRow(idx) { form.disaster.human_loss_rows.splice(idx, 1) }

function addPhysicalLossRow() {
  form.disaster.physical_loss_rows.push({ disaster_type: '', property_type: '', amount: '' })
}
function removePhysicalLossRow(idx) { form.disaster.physical_loss_rows.splice(idx, 1) }

function addReliefRow() {
  form.disaster.relief_rows.push({ relief_type: '', unit: '', quantity: '', source: '' })
}
function removeReliefRow(idx) { form.disaster.relief_rows.splice(idx, 1) }

// ── Livestock row helpers ─────────────────────────────────────────
function addLivestockRow() {
  form.livestockDetail.livestock_rows.push({ kind: '', qty: '', breed: '', rearing_method: '' })
}
function removeLivestockRow(idx) {
  form.livestockDetail.livestock_rows.splice(idx, 1)
}
function addProductRow() {
  form.livestockDetail.product_rows.push({ product: '', unit: '', qty: '', sale_qty: '', price_per_unit: '' })
}
function removeProductRow(idx) {
  form.livestockDetail.product_rows.splice(idx, 1)
}

// ── Vehicle helpers ───────────────────────────────────────────────
function addVehicle() {
  form.utilities.vehicles.push({ type: '', count: '' })
}
function removeVehicle(idx) {
  form.utilities.vehicles.splice(idx, 1)
}

// ── Members: total from Step 2 ─────────────────────────────────────
const totalMembersFromStep2 = computed(() =>
  (parseInt(form.social.male_num)   || 0) +
  (parseInt(form.social.female_num) || 0) +
  (parseInt(form.social.other_num)  || 0)
)

// ── Members: education status options (conditional by level) ───────
// ── Education status options filtered by education_level ──────────
// Logic mirrors XLS edu_groups column:
//   illiterate group (Illitrate, Literate)    → NotApplicable, NotAdmitted, Stopped
//   school group (BasicEd1-5..Intermediate)   → GoingSchool, Stopped
//   higher group (Bachelor..PhD)              → RegularHigherEducation, Stopped
//   other                                     → all five options
function getEduStatusOpts(level) {
  const illiterateGroup = ['Illitrate', 'Literate']
  const schoolGroup     = ['BasicEducation1_5', 'BasicEducation6_8', 'SecondarySchool', 'SLC', 'Intermediate']
  const higherGroup     = ['Bachelor', 'Master', 'MPhil', 'PhD']
  const opts = []
  if (!level) return opts
  if (illiterateGroup.includes(level)) {
    opts.push({ value: 'NotApplicable', label: 'निरक्षर भएको हुँदा लागु नहुने (Not Applicable)' })
    opts.push({ value: 'NotAdmitted',   label: 'कुनै पनि शैक्षिक केन्द्रमा भर्ना नभएको (Not Admitted)' })
    opts.push({ value: 'Stopped',       label: 'पुरा गरेको/छोडेको (Completed/Stopped)' })
  } else if (schoolGroup.includes(level)) {
    opts.push({ value: 'GoingSchool',   label: 'बिधालय गइरहेका छन् (Going to School)' })
    opts.push({ value: 'Stopped',       label: 'पुरा गरेको/छोडेको (Completed/Stopped)' })
  } else if (higherGroup.includes(level)) {
    opts.push({ value: 'RegularHigherEducation', label: 'कलेज तथा बिश्व बिद्यालयको नियमित अध्ययन (Higher Education)' })
    opts.push({ value: 'Stopped',                label: 'पुरा गरेको/छोडेको (Completed/Stopped)' })
  } else {
    // 'other' — show all options
    opts.push({ value: 'NotApplicable',          label: 'निरक्षर भएको हुँदा लागु नहुने (Not Applicable)' })
    opts.push({ value: 'NotAdmitted',            label: 'कुनै पनि शैक्षिक केन्द्रमा भर्ना नभएको (Not Admitted)' })
    opts.push({ value: 'GoingSchool',            label: 'बिधालय गइरहेका छन् (Going to School)' })
    opts.push({ value: 'RegularHigherEducation', label: 'कलेज तथा बिश्व बिद्यालयको नियमित अध्ययन (Higher Education)' })
    opts.push({ value: 'Stopped',                label: 'पुरा गरेको/छोडेको (Completed/Stopped)' })
  }
  return opts
}

// XLS-aligned married status values (for conditional logic)
const MARRIED_STATUSES = ['SingleMarried', 'MultiMarried', 'ReMarried', 'Widow', 'Divorced', 'Seperated']

// ── Navigation guard — warn before leaving with unsaved changes ────
const { showLeaveModal, confirmLeave, cancelLeave, bypassForSubmit } = useNavigationGuard(
  () => form.isDirty && !form.processing
)

// ── Member count prompt (shown when adding beyond Step 2 limit) ────
const showCountPrompt      = ref(false)
const highlightGenderCounts = ref(false)

function goToStep2ForCount() {
  showCountPrompt.value       = false
  highlightGenderCounts.value = true
  step.value = 2
  nextTick(() => {
    const el = document.getElementById('gender-count-anchor')
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    else scrollTop()
  })
  // Remove highlight after 10 s so it doesn't persist forever
  setTimeout(() => { highlightGenderCounts.value = false }, 10000)
}

// ── Member modal state ─────────────────────────────────────────────
const memberModalOpen  = ref(false)
const memberEditIdx    = ref(null)
const expandedMembers  = ref([])          // array of indices that are expanded
// Phase 3 accordion open states
const p3ContactOpen    = ref(false)
const p3VocOpen        = ref(false)
const p3TechOpen       = ref(false)
const p3SaOpen         = ref(false)
const p3AddrOpen       = ref(false)
const p3BizOpen        = ref(false)

function blankMemberDraft() {
  return {
    _id: null, is_household_head: false,
    full_name: '', father_name: '', relation_to_head: '',
    age: '', gender: '',
    // Marital
    marital_status: '', ageofmarriage: '',
    // Blood group
    blood_group_tested: '', blood_group: '',
    // Birth cert (age ≤ 18)
    has_birth_certificate: false,
    // ── Age 0–3 ──────────────────────────────────
    childbornplace: '', child_weight_in_birth: '',
    colostrum: null, childvaccine: [],
    vit_a_dewormin: null, fullmilkcourse: null,
    durationbrestfeed: '', childhealthcheckup: null,
    childbmi: null, child_caregiver: '',
    diarrhea_before_two_weeks: null, fever_resp: null,
    // ── Age 4–5 ──────────────────────────────────
    vitaminA: null, childphydev: '', childlgdev: '', childsocialization: '',
    // ── Age 6–15 ─────────────────────────────────
    phy_dev_6_15: null, childphydev_6_15: '', childlgdev_6_15: '',
    school_interest_6_15: '', interesting_subject_6_15: '',
    hw_support_6_15: '', allergy_infection_6_15: null,
    watch_mobile_tv_6_15: null, digital_habit_6_15: '', other_interest_6_15: '',
    // Child labor (age ≤ 18 + Stopped)
    child_labor: null, workplace_child_labor: '',
    // Disability
    disability_status: false, disability_type: '', disability_card: '', disability_card_color: '',
    // Chronic illness (age > 15)
    chronic_illness: false, chronic_illness_types: [],
    // Education (age > 5)
    education_level: '', education_status: '', current_class: '', higher_education_level: '',
    school_type: '', school_performance: '', school_location: '', school_name: '',
    cause_not_school: [],
    // Occupation (age > 10)
    occupation_type: '', employment_status: '',
    // Family
    no_birth_given: '',
    // Lifestyle (age ≥ 16)
    smoking_drinking: false, smoking_drinking_items: [], frequency_smoking_drinking: '',
    // Social security
    ss_allowance: false,
    // Documents (age ≥ 16)
    has_citizenship: false, no_citizenship_reason: '',
    has_voters_card: false,
    has_national_id_card: false,
    has_passport: false, passport_info: false,
    passport_number: '', passport_issued_district: '',
    passport_issued_date: '', passport_expiry_date: '',
    has_pan: false,
    has_drivers_license: false, drivers_license_category: [],
    // ── Contact info (age > 15) ──────────────────────────────
    has_phone: null, know_phone: null, member_phone: '',
    has_email: null, know_email: null, member_email: '',
    // ── Vocational training (age > 15) ──────────────────────
    vocational_training: null, vt_subject: '', vt_duration_min: '', vt_duration_max: '',
    // ── Technical training (age > 15) ───────────────────────
    techtraining: null, techtraining_subject: '', tech_duration_min: '', tech_duration_max: '',
    // ── SA/Other training (age > 15) ────────────────────────
    sa_training: null, sa_training_subject: '', sa_duration_min: '', sa_duration_max: '',
    // ── Address / location ───────────────────────────────────
    addrslocation: '',
    residing_district: '', purpose_living_outside: '', duration_of_stay: '',
    residing_country: [], purpose_living_abroad: '', typeofabroadjob: '', abroadjobstatus: '',
    // ── Business (age > 15) ──────────────────────────────────
    involveinbusiness: null, business_name: '',
    // Residence / remarks
    residence_status: '', remarks: '',
  }
}

const memberDraft = ref(blankMemberDraft())

function openAddMember() {
  memberEditIdx.value = null
  memberDraft.value   = blankMemberDraft()
  p3ContactOpen.value = false; p3VocOpen.value = false; p3TechOpen.value = false
  p3SaOpen.value = false; p3AddrOpen.value = false; p3BizOpen.value = false
  memberModalOpen.value = true
}

function openEditMember(idx) {
  memberEditIdx.value = idx
  const m = form.members[idx]
  memberDraft.value   = {
    ...m,
    chronic_illness_types:    [...(m.chronic_illness_types    ?? [])],
    cause_not_school:         [...(m.cause_not_school         ?? [])],
    drivers_license_category: [...(m.drivers_license_category ?? [])],
    smoking_drinking_items:   [...(m.smoking_drinking_items   ?? [])],
    childvaccine:             [...(m.childvaccine             ?? [])],
    residing_country:         [...(m.residing_country         ?? [])],
  }
  // Auto-open Phase 3 accordions if the member has existing data in that section
  p3ContactOpen.value = m.has_phone !== null || m.has_email !== null || !!m.member_phone || !!m.member_email
  p3VocOpen.value     = m.vocational_training !== null
  p3TechOpen.value    = m.techtraining !== null
  p3SaOpen.value      = m.sa_training !== null
  p3AddrOpen.value    = !!m.addrslocation
  p3BizOpen.value     = m.involveinbusiness !== null
  memberModalOpen.value = true
}

function saveMemberModal() {
  if (!memberDraft.value.full_name?.trim()) return
  const d = memberDraft.value
  const saved = {
    ...d,
    chronic_illness_types:    [...(d.chronic_illness_types    ?? [])],
    cause_not_school:         [...(d.cause_not_school         ?? [])],
    drivers_license_category: [...(d.drivers_license_category ?? [])],
    smoking_drinking_items:   [...(d.smoking_drinking_items   ?? [])],
    childvaccine:             [...(d.childvaccine             ?? [])],
    residing_country:         [...(d.residing_country         ?? [])],
  }
  if (memberEditIdx.value !== null) {
    form.members[memberEditIdx.value] = saved
  } else {
    form.members.push(saved)
    // auto-expand the newly added member
    const newIdx = form.members.length - 1
    if (!expandedMembers.value.includes(newIdx)) expandedMembers.value.push(newIdx)
  }
  memberModalOpen.value = false
}

function closeMemberModal() {
  memberModalOpen.value = false
}

function toggleMemberExpand(idx) {
  const pos = expandedMembers.value.indexOf(idx)
  if (pos > -1) expandedMembers.value.splice(pos, 1)
  else expandedMembers.value.push(idx)
}

function isMemberExpanded(idx) {
  return expandedMembers.value.includes(idx)
}

// ── Members helpers ────────────────────────────────────────────────
function addMember() {
  // legacy shim for onMounted auto-add — now a no-op (modal handles adding)
}

function removeMember(idx) {
  form.members.splice(idx, 1)
  // re-index expanded list
  expandedMembers.value = expandedMembers.value
    .filter(i => i !== idx)
    .map(i => (i > idx ? i - 1 : i))
}

// ── Crop production helpers ────────────────────────────────────────
function addCropRow(category) {
  form.cropProduction[category].push({ crop: '', land_unit: '', land_area: '', prod_unit: '', prod_qty: '', sale_qty: '', price_per_unit: '' })
}

function removeCropRow(category, idx) {
  form.cropProduction[category].splice(idx, 1)
}

// ── Local Draft (IndexedDB) ───────────────────────────────────────
import { saveDraft as dbSaveDraft, getDraft as dbGetDraft, deleteDraft as dbDeleteDraft, enqueue as dbEnqueue } from '@/utils/draftStore'
import { useSyncManager } from '@/utils/syncManager'

const { refreshCount: syncRefreshCount } = useSyncManager()


const DRAFT_KEYS = [
  'ward_id', 'settlement_id', 'respondent_name', 'respondent_contact',
  'survey_date', 'remarks', 'gps_lat', 'gps_lng', 'gps_accuracy', 'gps_altitude', 'photo_number',
  'location', 'housing', 'social', 'utilities', 'health', 'wellbeing', 'decisions', 'occupation',
  'agriculture', 'cropProduction', 'livestock', 'livestockDetail',
  'beeFishSilk', 'economic', 'disaster', 'members',
]

const draftKey        = computed(() => props.household ? `hh_draft_${props.household.id}` : 'hh_draft_new')
const showDraftBanner = ref(false)
const draftExists     = ref(false)
const draftSavedAt    = ref('')
const autoSaveStatus  = ref('')   // '' | 'saving' | 'saved'
let   _autoSaveTimer  = null
let   _statusTimer    = null

function formatDraftTime(iso) {
  try {
    return new Date(iso).toLocaleString('ne-NP', { dateStyle: 'short', timeStyle: 'short' })
  } catch {
    return iso
  }
}

async function saveDraftLocally() {
  const data = DRAFT_KEYS.reduce((acc, key) => {
    acc[key] = form[key]
    return acc
  }, {})
  data._savedAt = new Date().toISOString()
  await dbSaveDraft(draftKey.value, data)
  draftExists.value  = true
  draftSavedAt.value = formatDraftTime(data._savedAt)
}

async function loadDraft() {
  const data = await dbGetDraft(draftKey.value)
  if (!data) return
  try {
    DRAFT_KEYS.forEach(key => {
      if (data[key] === undefined) return
      if (Array.isArray(data[key])) {
        form[key] = [...data[key]]
      } else if (data[key] !== null && typeof data[key] === 'object') {
        Object.assign(form[key], data[key])
      } else {
        form[key] = data[key]
      }
    })
    if (data._savedAt) draftSavedAt.value = formatDraftTime(data._savedAt)
  } catch (e) {
    console.error('Failed to load draft:', e)
  }
  showDraftBanner.value = false
}

async function clearDraft() {
  await dbDeleteDraft(draftKey.value)
  draftExists.value     = false
  showDraftBanner.value = false
  draftSavedAt.value    = ''
  form.reset()
}

function dismissDraftBanner() {
  showDraftBanner.value = false
}

// Auto-save watcher (debounced 1.5 s)
const _draftSnapshot = computed(() =>
  DRAFT_KEYS.reduce((acc, key) => { acc[key] = form[key]; return acc }, {})
)
watch(_draftSnapshot, () => {
  if (form.processing) return
  autoSaveStatus.value = 'saving'
  clearTimeout(_autoSaveTimer)
  _autoSaveTimer = setTimeout(() => {
    saveDraftLocally()   // fire-and-forget async
    autoSaveStatus.value = 'saved'
    clearTimeout(_statusTimer)
    _statusTimer = setTimeout(() => { autoSaveStatus.value = '' }, 3000)
  }, 1500)
}, { deep: true })

onUnmounted(() => {
  clearTimeout(_autoSaveTimer)
  clearTimeout(_statusTimer)
})

// ── Offline toast ──────────────────────────────────────────────────
const offlineToast = ref('')
let   _toastTimer  = null
function showOfflineToast(msg) {
  offlineToast.value = msg
  clearTimeout(_toastTimer)
  _toastTimer = setTimeout(() => { offlineToast.value = '' }, 4000)
}

// ── Duplicate detection ────────────────────────────────────────────
const duplicateMatches = computed(() => {
  const raw = form.errors.duplicate_warning
  if (!raw) return null
  try { return JSON.parse(raw) } catch { return null }
})
function confirmDuplicate() {
  form.clearErrors('duplicate_warning')
  form.duplicate_confirmed = true
  submitForm()
}
function dismissDuplicate() {
  form.clearErrors('duplicate_warning')
  form.duplicate_confirmed = false
}

// ── Submission validation ───────────────────────────────────────────
const stepValidationErrors = computed(() => {
  const f    = form
  const errs = {}

  // Step 1 — घर विवरण
  const s1 = []
  if (!f.ward_id)                             s1.push('वडा नं. आवश्यक छ')
  if (!f.respondent_name?.trim())             s1.push('उत्तरदाताको नाम आवश्यक छ')
  if (!f.survey_date)                         s1.push('सर्वेक्षण मिति आवश्यक छ')
  if (!f.gps_lat || !f.gps_lng)              s1.push('GPS स्थान आवश्यक छ')
  if (!f.housing.house_type)                  s1.push('घरको प्रकार आवश्यक छ')
  if (!f.housing.roof_type)                   s1.push('छानाको प्रकार आवश्यक छ')
  if (!f.housing.wall_type)                   s1.push('पर्खालको प्रकार आवश्यक छ')
  if (!f.housing.foundation_type)             s1.push('जगको प्रकार आवश्यक छ')
  if (!f.housing.ownership_type)              s1.push('स्वामित्वको प्रकार आवश्यक छ')
  if (!f.housing.house_condition)             s1.push('घरको अवस्था आवश्यक छ')
  if (s1.length) errs[1] = s1

  // Step 2 — परिवार तथा सामाजिक
  const s2 = []
  if (!f.social.family_head?.trim())          s2.push('घरमूलीको नाम आवश्यक छ')
  if (!f.social.male_num && !f.social.female_num && !f.social.other_num)
                                              s2.push('परिवार सदस्य संख्या आवश्यक छ')
  if (!f.social.family_type)                  s2.push('परिवारको प्रकार आवश्यक छ')
  if (!f.social.ethnicity)                    s2.push('जाति/जनजाति आवश्यक छ')
  if (!f.social.religion)                     s2.push('धर्म आवश्यक छ')
  if (!f.social.mother_tongue)                s2.push('मातृभाषा आवश्यक छ')
  if (!f.social.major_family_occupation)      s2.push('मुख्य पेशा आवश्यक छ')
  if (f.social.migration_status === '')       s2.push('बसाइँसराइ अवस्था आवश्यक छ')
  if (f.social.have_land === '')              s2.push('जग्गा स्वामित्व आवश्यक छ')
  if (f.social.health_insurance === '')       s2.push('स्वास्थ्य बीमा आवश्यक छ')
  if (f.social.bankloan === '')               s2.push('ऋण विवरण आवश्यक छ')
  if (f.social.death === '')                  s2.push('मृत्यु विवरण आवश्यक छ')
  if (s2.length) errs[2] = s2

  // Step 3 — सरसफाई तथा ऊर्जा
  const s3 = []
  if (!f.utilities.water_source)              s3.push('पानीको स्रोत आवश्यक छ')
  if (!f.utilities.electricity_source)        s3.push('बिजुलीको स्रोत आवश्यक छ')
  if (!f.utilities.cooking_stove)             s3.push('खाना पकाउने चुलो आवश्यक छ')
  if (!f.utilities.solid_waste_management)    s3.push('फोहोर व्यवस्थापन आवश्यक छ')
  if (f.utilities.uses_firewood === '')       s3.push('दाउराको प्रयोग (छ/छैन) आवश्यक छ')
  if (f.utilities.has_alt_electricity === '') s3.push('बैकल्पिक बिजुली जानकारी आवश्यक छ')
  if (f.utilities.handwashing === '')         s3.push('हात धुने सुविधा आवश्यक छ')
  if (f.utilities.vehicle === '')             s3.push('सवारी साधन आवश्यक छ')
  if (s3.length) errs[3] = s3

  // Step 4 — घरपरिवारको निर्णय
  const s4 = []
  if (!f.decisions.decisioninHHWork)          s4.push('घरायसी काम निर्णय आवश्यक छ')
  if (!f.decisions.decisioninProperty)        s4.push('सम्पत्ति निर्णय आवश्यक छ')
  if (!f.decisions.decisioninBusiness)        s4.push('व्यवसाय निर्णय आवश्यक छ')
  if (f.decisions.has_bank_account === '')    s4.push('बैंक खाता जानकारी आवश्यक छ')
  if (s4.length) errs[4] = s4

  // Step 6 — खुसी र मेलमिलाप
  const s6 = []
  if (!f.wellbeing.happy_with_location)       s6.push('स्थानप्रति सन्तुष्टि आवश्यक छ')
  if (!f.wellbeing.residence_perception)      s6.push('बसाइको अनुभूति आवश्यक छ')
  if (!f.wellbeing.family_gathering_freq)     s6.push('परिवार भेटघाट आवश्यक छ')
  if (!f.wellbeing.neighbors_nice)            s6.push('छिमेकी सम्बन्ध आवश्यक छ')
  if (s6.length) errs[6] = s6

  // Step 7 — परिवारको पेशा
  const s7 = []
  if (!f.occupation.occupation_rows?.length)  s7.push('कम्तीमा एक पेशा विवरण आवश्यक छ')
  if (f.occupation.skill_training === '')     s7.push('सीप तालिम जानकारी आवश्यक छ')
  if (s7.length) errs[7] = s7

  // Step 8 — खेतीपाती
  const s8 = []
  if (f.agriculture.agriculture === '')       s8.push('कृषि गर्नु हुन्छ/हुन्न आवश्यक छ')
  if (s8.length) errs[8] = s8

  // Step 10 — पशुपालन
  const s10 = []
  if (f.livestockDetail.has_livestock === '') s10.push('पशुपालन जानकारी (छ/छैन) आवश्यक छ')
  if (s10.length) errs[10] = s10

  // Step 11 — मौरी/माछा/रेशम
  const s11 = []
  if (f.beeFishSilk.has_bee_fish_silk === '') s11.push('मौरी/माछा/रेशम जानकारी (छ/छैन) आवश्यक छ')
  if (s11.length) errs[11] = s11

  // Step 12 — आम्दानी र खर्च
  const s12 = []
  if (!f.economic.primary_income_source)      s12.push('मुख्य आम्दानीको स्रोत आवश्यक छ')
  if (!f.economic.annual_income_range)        s12.push('वार्षिक आम्दानी दायरा आवश्यक छ')
  if (!f.economic.annual_expenditure_range)   s12.push('वार्षिक खर्च दायरा आवश्यक छ')
  if (s12.length) errs[12] = s12

  // Step 13 — प्राकृतिक प्रकोप
  const s13 = []
  if (f.disaster.affected_last_3years === '') s13.push('प्राकृतिक प्रकोप प्रभाव आवश्यक छ')
  if (!f.disaster.risk_reduction_plan)        s13.push('जोखिम न्यूनीकरण योजना आवश्यक छ')
  if (s13.length) errs[13] = s13

  // Step 14 — परिवार सदस्य
  const s14 = []
  if (!f.members.length) {
    s14.push('कम्तीमा एक परिवार सदस्य आवश्यक छ')
  } else {
    f.members.forEach((m, i) => {
      if (!m.full_name?.trim()) s14.push(`सदस्य ${i + 1}: पूरा नाम आवश्यक छ`)
      if (!m.relation_to_head)  s14.push(`सदस्य ${i + 1}: नाता आवश्यक छ`)
      if (m.age === '' || m.age === null) s14.push(`सदस्य ${i + 1}: उमेर आवश्यक छ`)
      if (!m.gender)            s14.push(`सदस्य ${i + 1}: लिङ्ग आवश्यक छ`)
    })
  }
  if (s14.length) errs[14] = s14

  return errs
})

const stepsWithErrors      = computed(() => new Set(Object.keys(stepValidationErrors.value).map(Number)))
const hasValidationErrors  = computed(() => stepsWithErrors.value.size > 0)
const validationErrorCount = computed(() =>
  Object.values(stepValidationErrors.value).reduce((t, arr) => t + arr.length, 0)
)

// ── Submit ─────────────────────────────────────────────────────────
async function submitForm() {
  bypassForSubmit()
  form.save_draft = false

  if (!navigator.onLine) {
    await saveDraftLocally()
    const url    = isEdit.value ? `/households/${props.household.id}` : '/households'
    const method = isEdit.value ? 'PUT' : 'POST'
    await dbEnqueue({ type: 'household', method, url, data: { ...form.data(), save_draft: false } })
    await syncRefreshCount()
    showOfflineToast('अफलाइन — पठाउन कतारमा राखियो। जडान फर्किएपछि स्वतः पठाइनेछ।')
    return
  }

  if (isEdit.value) {
    form.put(`/households/${props.household.id}`)
  } else {
    form.post('/households')
  }
}

// ── Save Draft (server) ────────────────────────────────────────────
async function saveDraft() {
  await saveDraftLocally()
  form.save_draft = true

  if (!navigator.onLine) {
    const url    = isEdit.value ? `/households/${props.household.id}` : '/households'
    const method = isEdit.value ? 'PUT' : 'POST'
    await dbEnqueue({ type: 'household', method, url, data: { ...form.data(), save_draft: true } })
    await syncRefreshCount()
    showOfflineToast('मस्यौदा कतारमा राखियो। जडान फर्किएपछि स्वतः सुरक्षित हुनेछ।')
    return
  }

  if (isEdit.value) {
    form.put(`/households/${props.household.id}`)
  } else {
    form.post('/households')
  }
}

// ── Mount ──────────────────────────────────────────────────────────
onMounted(async () => {
  if (props.initialWardId) {
    form.ward_id = props.initialWardId
  }
  if (form.members.length === 0) {
    addMember()
  }

  // Check for existing local draft in IndexedDB
  try {
    const data = await dbGetDraft(draftKey.value)
    if (data) {
      draftExists.value     = true
      showDraftBanner.value = true
      draftSavedAt.value    = data._savedAt ? formatDraftTime(data._savedAt) : ''
    }
  } catch {
    // IndexedDB unavailable (private browsing on some browsers) — silently ignore
  }
})
</script>
