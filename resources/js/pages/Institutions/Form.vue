<template>
  <div class="space-y-5">

    <!-- ── Duplicate Warning Modal ── -->
    <Teleport to="body">
      <div v-if="duplicateMatches" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden">
          <div :class="duplicateHardBlock ? 'bg-red-600' : 'bg-amber-500'"
            class="px-5 py-4 flex items-center gap-3">
            <i :class="duplicateHardBlock ? 'fa-solid fa-circle-xmark' : 'fa-solid fa-triangle-exclamation'"
              class="text-white text-lg"></i>
            <h2 class="text-[16px] font-bold text-white">
              {{ duplicateHardBlock ? 'डुप्लिकेट दर्ता नम्बर — ब्लक गरियो!' : 'सम्भावित डुप्लिकेट पाइयो!' }}
            </h2>
          </div>
          <div class="px-5 py-4 space-y-3 max-h-72 overflow-y-auto">
            <p class="text-[13px] text-[#626976]">
              {{ duplicateHardBlock
                  ? 'यो दर्ता नम्बर भएको संस्था पहिलेनै अभिलेखमा छ। नयाँ प्रविष्टि गर्न मिल्दैन।'
                  : 'तलका अभिलेखहरूसँग मिल्दो संस्था पहिलेनै दर्ता भएको देखिन्छ। जाँच गर्नुहोस्:' }}
            </p>
            <div v-for="m in duplicateMatches" :key="m.id"
              class="border rounded-lg px-4 py-3 space-y-1"
              :class="m.hard ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50'">
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
                {{ m.name || '—' }}
                <span v-if="m.ward_number"> · वडा {{ m.ward_number }}</span>
              </div>
              <div class="flex flex-wrap gap-1 mt-1">
                <span v-for="r in m.reasons" :key="r"
                  class="text-[11px] rounded px-1.5 py-0.5"
                  :class="m.hard ? 'bg-red-100 text-red-800 border border-red-200' : 'bg-amber-100 text-amber-800 border border-amber-200'">
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
            <button v-if="!duplicateHardBlock" @click="confirmDuplicate"
              class="px-4 py-2 rounded bg-amber-500 hover:bg-amber-600 text-white text-[13px] font-semibold transition">
              <i class="fa-solid fa-circle-check mr-1"></i>
              डुप्लिकेट होइन — सुरक्षित गर्नुहोस्
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Page heading -->
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-md bg-[#206bc4] flex items-center justify-center text-white shrink-0 shadow-md shadow-[#206bc4]/25">
        <i class="fa-solid fa-building text-sm"></i>
      </div>
      <div>
        <h2 class="text-[15px] font-semibold text-[#1d273b]">{{ pageTitle }}</h2>
        <p class="text-[11px] text-[#9aa0ac]">
          {{ isEdit ? 'विद्यमान संस्था अद्यावधिक गर्नुहोस्' : 'नयाँ संस्थाको विवरण भर्नुहोस्' }}
        </p>
      </div>
    </div>

    <!-- Draft restore banner -->
    <div
      v-if="showDraftBanner"
      class="bg-amber-50 border border-amber-200 rounded-md px-4 py-3 flex items-start gap-3"
    >
      <i class="fa-solid fa-floppy-disk text-amber-500 mt-0.5 shrink-0"></i>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-amber-800">सुरक्षित मस्यौदा फेला पर्यो</p>
        <p v-if="draftSavedAt" class="text-xs text-amber-600 mt-0.5">अन्तिम स्वतः सुरक्षित: {{ draftSavedAt }}</p>
      </div>
      <button type="button" @click="loadDraftData"
        class="text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 rounded-md transition-colors shrink-0">
        पुनःलोड गर्नुहोस्
      </button>
      <button type="button" @click="dismissDraftBanner"
        class="text-amber-400 hover:text-amber-600 transition-colors shrink-0 ml-1">
        <i class="fa-solid fa-xmark text-sm"></i>
      </button>
    </div>

    <!-- Offline queue toast -->
    <div
      v-if="offlineToast"
      class="flex items-center gap-2.5 bg-blue-600 text-white text-sm font-medium px-4 py-3 rounded-md shadow-md"
    >
      <i class="fa-solid fa-cloud-arrow-up text-white/80"></i>
      <span>{{ offlineToast }}</span>
    </div>

    <!-- Step indicator -->
    <div class="bg-white rounded-md border border-[#e6e7e9] p-4">
      <div class="flex items-center gap-1 overflow-x-auto">
        <template v-for="(label, idx) in stepLabels" :key="idx">
          <div
            @click="goToStep(idx + 1)"
            class="flex items-center gap-1.5 shrink-0 cursor-pointer"
          >
            <div class="relative">
              <div :class="[
                'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all',
                idx + 1 === step
                  ? 'bg-[#206bc4] text-white shadow-md shadow-[#206bc4]/20'
                  : idx + 1 < step
                    ? (stepsWithErrors.has(idx + 1) ? 'bg-red-100 text-red-600' : 'bg-[#206bc4]/10 text-[#206bc4]')
                    : 'bg-gray-100 text-gray-400',
              ]">
                <i v-if="idx + 1 < step && !stepsWithErrors.has(idx + 1)" class="fa-solid fa-check text-[10px]"></i>
                <i v-else-if="idx + 1 < step && stepsWithErrors.has(idx + 1)" class="fa-solid fa-exclamation text-[10px]"></i>
                <span v-else>{{ idx + 1 }}</span>
              </div>
              <span v-if="stepsWithErrors.has(idx + 1)"
                class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full bg-red-500 border border-white"></span>
            </div>
            <span :class="[
              'text-xs font-medium hidden sm:block',
              idx + 1 === step ? 'text-[#206bc4]' : idx + 1 < step ? 'text-[#206bc4]' : 'text-gray-400',
            ]">{{ label }}</span>
          </div>
          <div v-if="idx < stepLabels.length - 1" class="flex-1 min-w-[8px] h-px bg-gray-200 shrink-0"></div>
        </template>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 1: संस्था विवरण
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 1" class="bg-white rounded-md border border-[#e6e7e9] p-5 space-y-5">
      <h3 class="text-[15px] font-semibold text-[#1d273b] flex items-center gap-2">
        <i class="fa-solid fa-building text-[#206bc4]"></i> संस्था विवरण
      </h3>

      <!-- Institution type -->
      <div class="flex flex-col gap-1">
        <label class="text-[13px] font-semibold text-[#626976] mb-1.5">संस्थाको प्रकार <span class="text-red-500">*</span></label>
        <select v-model="form.institution_type_id" class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
          <option value="">-- प्रकार छान्नुहोस् --</option>
          <option v-for="t in props.institutionTypes" :key="t.id" :value="t.id">
            {{ t.name_np }} <template v-if="t.name_en">({{ t.name_en }})</template>
          </option>
        </select>
        <p v-if="form.errors.institution_type_id" class="text-[11px] text-red-500 mt-1">{{ form.errors.institution_type_id }}</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
        <!-- Name Nepali -->
        <div class="flex flex-col gap-1">
          <label class="text-[13px] font-semibold text-[#626976] mb-1.5">संस्थाको नाम (नेपाली) <span class="text-red-500">*</span></label>
          <input v-model="form.name_np" type="text" placeholder="संस्थाको नाम नेपालीमा" class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          <p v-if="form.errors.name_np" class="text-[11px] text-red-500 mt-1">{{ form.errors.name_np }}</p>
        </div>
        <!-- Name English -->
        <div class="flex flex-col gap-1">
          <label class="text-[13px] font-semibold text-[#626976] mb-1.5">संस्थाको नाम (अंग्रेजी)</label>
          <input v-model="form.name_en" type="text" placeholder="Institution name in English" class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
        </div>
        <!-- Ward -->
        <div class="flex flex-col gap-1">
          <label class="text-[13px] font-semibold text-[#626976] mb-1.5">वडा नं. <span class="text-red-500">*</span></label>
          <select v-model="form.ward_id" class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
            <option value="">-- वडा छान्नुहोस् --</option>
            <option v-for="w in props.wards" :key="w.id" :value="w.id">वडा {{ w.ward_number }}</option>
          </select>
          <p v-if="form.errors.ward_id" class="text-[11px] text-red-500 mt-1">{{ form.errors.ward_id }}</p>
        </div>
        <!-- Settlement -->
        <div class="flex flex-col gap-1">
          <label class="text-[13px] font-semibold text-[#626976] mb-1.5">बस्ती / टोल</label>
          <select v-model="form.settlement_id" class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
            <option value="">-- बस्ती छान्नुहोस् --</option>
            <option v-for="s in filteredSettlements" :key="s.id" :value="s.id">{{ s.name_np }}</option>
          </select>
        </div>
        <!-- Ownership type -->
        <div class="flex flex-col gap-1">
          <label class="text-[13px] font-semibold text-[#626976] mb-1.5">स्वामित्व प्रकार</label>
          <select v-model="form.ownership_type" class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
            <option value="">-- छान्नुहोस् --</option>
            <option value="govt">सरकारी</option>
            <option value="private">निजी</option>
            <option value="community">सामुदायिक</option>
            <option value="ngo">गैसस / NGO</option>
            <option value="cooperative">सहकारी</option>
            <option value="other">अन्य</option>
          </select>
        </div>
        <!-- Established date -->
        <div class="flex flex-col gap-1">
          <label class="text-[13px] font-semibold text-[#626976] mb-1.5">स्थापना मिति</label>
          <NepaliDatePicker v-model="form.established_date" placeholder="स्थापना मिति (बि.सं.)" />
        </div>
        <!-- Registration number -->
        <div class="flex flex-col gap-1">
          <label class="text-[13px] font-semibold text-[#626976] mb-1.5">दर्ता नं.</label>
          <input v-model="form.registration_number" type="text" placeholder="दर्ता नम्बर" class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 2: सम्पर्क विवरण
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 2" class="bg-white rounded-md border border-[#e6e7e9] p-5 space-y-5">
      <h3 class="text-[15px] font-semibold text-[#1d273b] flex items-center gap-2">
        <i class="fa-solid fa-address-card text-[#206bc4]"></i> सम्पर्क विवरण
      </h3>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
        <div class="flex flex-col gap-1">
          <label class="text-[13px] font-semibold text-[#626976] mb-1.5">उत्तरदाताको नाम <span class="text-red-500">*</span></label>
          <input v-model="form.respondent_name" type="text" placeholder="पूरा नाम" class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          <p v-if="form.errors.respondent_name" class="text-[11px] text-red-500 mt-1">{{ form.errors.respondent_name }}</p>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-[13px] font-semibold text-[#626976] mb-1.5">सम्पर्क नं.</label>
          <input v-model="form.respondent_contact" type="tel" placeholder="९८xxxxxxxx" class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-[13px] font-semibold text-[#626976] mb-1.5">सर्वेक्षण मिति <span class="text-red-500">*</span></label>
          <NepaliDatePicker v-model="form.survey_date" placeholder="सर्वेक्षण मिति (बि.सं.)" />
          <p v-if="form.errors.survey_date" class="text-[11px] text-red-500 mt-1">{{ form.errors.survey_date }}</p>
        </div>
      </div>

      <!-- GPS -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">GPS स्थान</label>
        <button type="button" @click="getGPS"
          class="flex items-center gap-2 px-4 py-2 bg-[#206bc4]/8 border border-teal-200 text-[#206bc4] rounded-md text-sm hover:bg-[#206bc4]/10 transition-colors mb-3">
          <i class="fa-solid fa-location-crosshairs"></i>
          GPS प्राप्त गर्नुहोस्
        </button>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-x-4 gap-y-4">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Latitude</label>
            <input v-model="form.gps_lat" type="text" placeholder="27.XXXXXXX" class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4] outline-none transition" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Longitude</label>
            <input v-model="form.gps_lng" type="text" placeholder="85.XXXXXXX" class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4] outline-none transition" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Accuracy (m)</label>
            <input v-model="form.gps_accuracy" type="text" placeholder="—" class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4] outline-none transition" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Altitude</label>
            <input v-model="form.gps_altitude" type="text" placeholder="—" class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4] outline-none transition" />
          </div>
        </div>

        <!-- GPS boundary alert -->
        <div v-if="gpsBoundaryResult && gpsBoundaryResult.status !== 'inside'"
          class="mt-3 flex items-start gap-2 rounded px-3 py-2 text-[12px]"
          :class="{
            'bg-amber-50 border border-amber-200 text-amber-800':    gpsBoundaryResult.status === 'near',
            'bg-orange-50 border border-orange-200 text-orange-800': gpsBoundaryResult.status === 'warn',
            'bg-red-50 border border-red-200 text-red-800':          gpsBoundaryResult.status === 'far',
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
      </div>

      <!-- Photo Number -->
      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-medium text-gray-700">
          <i class="fa-solid fa-camera text-gray-400 mr-1 text-xs"></i>
          फोटो नम्बर <span class="text-gray-400 font-normal">(Photo Number)</span>
        </label>
        <input v-model="form.photo_number" type="text" placeholder="जस्तै: 00131"
          class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-sm font-mono focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
        <p class="text-xs text-gray-400">फोटो फाइलको नाम (बिना .png)</p>
      </div>

      <!-- Remarks -->
      <div class="flex flex-col gap-1">
        <label class="text-[13px] font-semibold text-[#626976] mb-1.5">कैफियत</label>
        <textarea v-model="form.remarks" rows="3" placeholder="थप जानकारी..." class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4] resize-none"></textarea>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 3: भौतिक पूर्वाधार
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 3" class="bg-white rounded-md border border-[#e6e7e9] p-5 space-y-5">
      <h3 class="text-[15px] font-semibold text-[#1d273b] flex items-center gap-2">
        <i class="fa-solid fa-hard-hat text-[#206bc4]"></i> भौतिक पूर्वाधार
      </h3>

      <!-- Buildings (repeatable by type) -->
      <div>
        <div class="flex items-center justify-between mb-4">
          <h4 class="text-[14px] font-semibold text-[#1d273b] flex items-center gap-2">
            <i class="fa-solid fa-building text-[#206bc4]"></i> भवन विवरण
          </h4>
          <button type="button" @click="addBuilding"
            class="flex items-center gap-2 px-4 py-2 bg-[#206bc4] text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors">
            <i class="fa-solid fa-plus text-xs"></i> भवन थप्नुहोस्
          </button>
        </div>

        <div v-if="form.buildings.length > 0" class="space-y-4">
          <div v-for="(b, idx) in form.buildings" :key="idx"
            class="bg-gray-50 border border-[#e6e7e9] rounded-md p-5">
            <div class="flex items-center justify-between mb-4">
              <span class="text-[14px] font-semibold text-[#206bc4] flex items-center gap-2">
                <span class="w-7 h-7 rounded-full bg-[#206bc4] text-white flex items-center justify-center text-xs font-bold">{{ idx + 1 }}</span>
                भवन {{ idx + 1 }}
              </span>
              <button type="button" @click="removeBuilding(idx)"
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-red-500 border border-red-200 rounded-md hover:bg-red-50 transition-colors">
                <i class="fa-solid fa-trash text-xs"></i> हटाउनुहोस्
              </button>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-x-6 gap-y-4">
              <div class="flex flex-col gap-1">
                <label class="text-[13px] font-semibold text-[#626976] mb-1.5">भवनको प्रकार <span class="text-red-500">*</span></label>
                <select v-model="b.building_type"
                  class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                  <option value="">-- छान्नुहोस् --</option>
                  <option v-for="t in props.buildingTypes" :key="t.value" :value="t.value">
                    {{ t.label_ne }}
                  </option>
                </select>
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[13px] font-semibold text-[#626976] mb-1.5">भवन संख्या</label>
                <input v-model="b.building_count" type="number" min="0" placeholder="0"
                  class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[13px] font-semibold text-[#626976] mb-1.5">कोठा संख्या</label>
                <input v-model="b.room_count" type="number" min="0" placeholder="0"
                  class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
              </div>
            </div>
          </div>
        </div>

        <p v-else class="text-[14px] text-gray-400 text-center py-8 border-2 border-dashed border-[#e6e7e9] rounded-md">
          <i class="fa-solid fa-building text-gray-300 text-2xl block mb-2"></i>
          भवन थप्न माथिको बटन थिच्नुहोस्
        </p>
      </div>

      <!-- General infra fields -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4 border-t border-[#e6e7e9] pt-5">
        <div class="flex flex-col gap-1">
          <label class="text-[13px] font-semibold text-[#626976] mb-1.5">कुल क्षेत्रफल (वर्गमिटर)</label>
          <input v-model="form.infrastructure.total_area" type="number" min="0" placeholder="0" class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-[13px] font-semibold text-[#626976] mb-1.5">भवनको अवस्था</label>
          <select v-model="form.infrastructure.condition" class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
            <option value="">-- छान्नुहोस् --</option>
            <option value="good">राम्रो</option>
            <option value="average">मध्यम</option>
            <option value="poor">जीर्ण</option>
            <option value="damaged">क्षतिग्रस्त</option>
            <option value="under_const">निर्माणाधीन</option>
          </select>
        </div>
      </div>

      <!-- Facilities toggles -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-3">उपलब्ध सुविधाहरू</label>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <label v-for="f in facilityOptions" :key="f.key"
            class="flex items-center gap-2.5 p-3 rounded-md border border-[#e6e7e9] cursor-pointer hover:border-teal-300 hover:bg-[#206bc4]/8 transition-colors"
            :class="{ 'border-teal-400 bg-[#206bc4]/8': form.infrastructure[f.key] }">
            <input type="checkbox" v-model="form.infrastructure[f.key]" class="rounded text-[#206bc4] focus:ring-teal-500" />
            <span class="text-sm text-gray-700">{{ f.label }}</span>
          </label>
        </div>
      </div>

      <!-- Toilet details (conditional) -->
      <div v-if="form.infrastructure.has_toilet" class="grid grid-cols-2 gap-4 border-t border-[#e6e7e9] pt-4">
        <div class="flex flex-col gap-1">
          <label class="text-[13px] font-semibold text-[#626976] mb-1.5">पुरुष शौचालय संख्या</label>
          <input v-model="form.infrastructure.male_toilet" type="number" min="0" placeholder="0" class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-teal-500" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-[13px] font-semibold text-[#626976] mb-1.5">महिला शौचालय संख्या</label>
          <input v-model="form.infrastructure.female_toilet" type="number" min="0" placeholder="0" class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-teal-500" />
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 4: कर्मचारी
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 4" class="bg-white rounded-md border border-[#e6e7e9] p-5 space-y-5">
      <h3 class="text-[15px] font-semibold text-[#1d273b] flex items-center gap-2">
        <i class="fa-solid fa-users text-[#206bc4]"></i> कार्यसमिति तथा कर्मचारी
      </h3>

      <!-- Executive Committee -->
      <div class="border-t border-[#e6e7e9] pt-5">
        <div class="flex items-center gap-4 mb-5 p-4 bg-gray-50 rounded-md border border-[#e6e7e9]">
          <label class="relative inline-flex items-center cursor-pointer shrink-0">
            <input type="checkbox" v-model="form.executive_committee.has_committee" class="sr-only peer" />
            <div class="w-12 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#206bc4]/30 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#206bc4]"></div>
          </label>
          <div>
            <p class="text-[14px] font-semibold text-[#1d273b]">कार्यकारी समिति छ?</p>
            <p class="text-[13px] text-gray-500 mt-0.5">यस संस्थामा कार्यकारी समिति भए टगल अन गर्नुहोस्</p>
          </div>
        </div>

        <template v-if="form.executive_committee.has_committee">
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <h4 class="text-[14px] font-semibold text-[#1d273b] flex items-center gap-2">
                <i class="fa-solid fa-users text-[#206bc4]"></i>
                कार्यकारी समिति सदस्यहरु
              </h4>
              <button type="button" @click="addCommitteeMember"
                class="flex items-center gap-2 px-4 py-2 bg-[#206bc4] text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors">
                <i class="fa-solid fa-plus text-xs"></i>
                सदस्य थप्नुहोस्
              </button>
            </div>

            <div v-if="form.executive_committee.members.length > 0" class="space-y-2">
              <div v-for="(m, idx) in form.executive_committee.members" :key="idx"
                class="border border-blue-200 rounded-md overflow-hidden">
                <!-- Accordion header -->
                <div class="flex items-center justify-between px-4 py-3 bg-blue-50 cursor-pointer select-none"
                  @click="toggleOpen(openCommittee, idx)">
                  <span class="text-[14px] font-semibold text-[#206bc4] flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-[#206bc4] text-white flex items-center justify-center text-xs font-bold">{{ idx + 1 }}</span>
                    सदस्य: {{ m.full_name || 'नयाँ सदस्य' }} ({{ idx + 1 }})
                  </span>
                  <div class="flex items-center gap-2">
                    <button type="button" @click.stop="removeCommitteeMember(idx)"
                      class="flex items-center gap-1 px-2.5 py-1 text-xs text-red-500 border border-red-200 rounded hover:bg-red-50 transition-colors">
                      <i class="fa-solid fa-trash text-xs"></i> हटाउनुहोस्
                    </button>
                    <i class="fa-solid text-[#206bc4] text-sm transition-transform duration-200"
                      :class="openCommittee[idx] ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                  </div>
                </div>
                <!-- Accordion body -->
                <div v-show="openCommittee[idx]" class="p-5 bg-white border-t border-blue-100">
                  <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
                    <div class="flex flex-col gap-1">
                      <label class="text-[13px] font-semibold text-[#626976] mb-1.5">पूरा नाम <span class="text-red-500">*</span></label>
                      <input v-model="m.full_name" type="text" placeholder="सदस्यको पूरा नाम"
                        class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                    </div>
                    <div class="flex flex-col gap-1">
                      <label class="text-[13px] font-semibold text-[#626976] mb-1.5">पद / ओहदा</label>
                      <select v-if="(props.surveyOpts?.inst_committee_position ?? []).length"
                        v-model="m.position"
                        class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="o in props.surveyOpts.inst_committee_position" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
                      </select>
                      <input v-else v-model="m.position" type="text" placeholder="अध्यक्ष / उपाध्यक्ष / सचिव..."
                        class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                    </div>
                    <div class="flex flex-col gap-1">
                      <label class="text-[13px] font-semibold text-[#626976] mb-1.5">लिङ्ग</label>
                      <select v-model="m.gender"
                        class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                        <option value="">-- छान्नुहोस् --</option>
                        <option value="male">पुरुष</option>
                        <option value="female">महिला</option>
                        <option value="other">अन्य</option>
                      </select>
                    </div>
                    <div class="flex flex-col gap-1">
                      <label class="text-[13px] font-semibold text-[#626976] mb-1.5">जातजाति / समूह</label>
                      <select v-if="(props.surveyOpts?.ethnicity ?? []).length"
                        v-model="m.ethnicity"
                        class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="o in props.surveyOpts.ethnicity" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
                      </select>
                      <input v-else v-model="m.ethnicity" type="text" placeholder="जातजाति"
                        class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                    </div>
                    <div class="flex flex-col gap-1">
                      <label class="text-[13px] font-semibold text-[#626976] mb-1.5">उमेर</label>
                      <input v-model="m.age" type="number" min="18" max="120" placeholder="उमेर (वर्षमा)"
                        class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                    </div>
                    <div class="flex flex-col gap-1">
                      <label class="text-[13px] font-semibold text-[#626976] mb-1.5">पेशा / व्यवसाय</label>
                      <select v-if="(props.surveyOpts?.major_occupation ?? []).length"
                        v-model="m.occupation"
                        class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                        <option value="">-- छान्नुहोस् --</option>
                        <option v-for="o in props.surveyOpts.major_occupation" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
                      </select>
                      <input v-else v-model="m.occupation" type="text" placeholder="पेशा वा व्यवसाय"
                        class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                    </div>
                    <div class="flex flex-col gap-1">
                      <label class="text-[13px] font-semibold text-[#626976] mb-1.5">सम्पर्क नम्बर</label>
                      <input v-model="m.contact" type="tel" placeholder="९८xxxxxxxx"
                        class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                    </div>
                    <div class="flex flex-col gap-1">
                      <label class="text-[13px] font-semibold text-[#626976] mb-1.5">अपाङ्गता</label>
                      <label class="flex items-center gap-3 p-3 rounded-md border border-[#e6e7e9] cursor-pointer hover:border-blue-300 hover:bg-blue-50 transition-colors"
                        :class="{ 'border-blue-400 bg-blue-50': m.disability }">
                        <input type="checkbox" v-model="m.disability"
                          class="w-4 h-4 rounded border-gray-300 text-[#206bc4] focus:ring-[#206bc4]" />
                        <span class="text-[14px] text-gray-700">अपाङ्गता भएको व्यक्ति</span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <p v-else class="text-center text-[14px] text-gray-400 py-8 border-2 border-dashed border-[#e6e7e9] rounded-md">
              <i class="fa-solid fa-users text-gray-300 text-2xl block mb-2"></i>
              सदस्य थप्न माथिको बटन थिच्नुहोस्।
            </p>
          </div>
        </template>

        <div v-else class="text-[14px] text-gray-400 text-center py-6 border-2 border-dashed border-[#e6e7e9] rounded-md">
          <i class="fa-solid fa-toggle-off text-gray-300 text-2xl block mb-2"></i>
          कार्यकारी समिति नभएको संस्था।
        </div>
      </div>

      <!-- Staff -->
      <div class="border-t border-[#e6e7e9] pt-5">
        <div class="flex items-center gap-4 mb-5 p-4 bg-gray-50 rounded-md border border-[#e6e7e9]">
          <label class="relative inline-flex items-center cursor-pointer shrink-0">
            <input type="checkbox" v-model="form.has_staff" class="sr-only peer" />
            <div class="w-12 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#206bc4]/30 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#206bc4]"></div>
          </label>
          <div>
            <p class="text-[14px] font-semibold text-[#1d273b]">कर्मचारी छन्?</p>
            <p class="text-[13px] text-gray-500 mt-0.5">यस संस्थामा कर्मचारी भए टगल अन गर्नुहोस्</p>
          </div>
        </div>

        <template v-if="form.has_staff">
        <div class="flex items-center justify-between mb-4">
          <h4 class="text-[14px] font-semibold text-[#1d273b] flex items-center gap-2">
            <i class="fa-solid fa-user-tie text-[#206bc4]"></i> कर्मचारी विवरण
          </h4>
          <button type="button" @click="addStaff"
            class="flex items-center gap-2 px-4 py-2 bg-[#206bc4] text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors">
            <i class="fa-solid fa-plus text-xs"></i>
            कर्मचारी थप्नुहोस्
          </button>
        </div>

        <div v-if="form.staff.length > 0" class="space-y-2">
          <div v-for="(s, idx) in form.staff" :key="idx"
            class="border border-[#e6e7e9] rounded-md overflow-hidden">
            <!-- Accordion header -->
            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 cursor-pointer select-none"
              @click="toggleOpen(openStaff, idx)">
              <span class="text-[14px] font-semibold text-[#206bc4] flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-[#206bc4] text-white flex items-center justify-center text-xs font-bold">{{ idx + 1 }}</span>
                कर्मचारी: {{ s.name || 'नयाँ कर्मचारी' }} ({{ idx + 1 }})
              </span>
              <div class="flex items-center gap-2">
                <button type="button" @click.stop="removeStaff(idx)"
                  class="flex items-center gap-1 px-2.5 py-1 text-xs text-red-500 border border-red-200 rounded hover:bg-red-50 transition-colors">
                  <i class="fa-solid fa-trash text-xs"></i> हटाउनुहोस्
                </button>
                <i class="fa-solid text-[#206bc4] text-sm transition-transform duration-200"
                  :class="openStaff[idx] ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
              </div>
            </div>
            <!-- Accordion body -->
            <div v-show="openStaff[idx]" class="p-5 bg-white border-t border-[#e6e7e9]">
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
                <div class="flex flex-col gap-1">
                  <label class="text-[13px] font-semibold text-[#626976] mb-1.5">पूरा नाम <span class="text-red-500">*</span></label>
                  <input v-model="s.name" type="text" placeholder="कर्मचारीको पूरा नाम"
                    class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-[13px] font-semibold text-[#626976] mb-1.5">पद / ओहदा</label>
                  <select v-if="(props.surveyOpts?.inst_staff_category ?? []).length"
                    v-model="s.position"
                    class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                    <option value="">-- छान्नुहोस् --</option>
                    <option v-for="o in props.surveyOpts.inst_staff_category" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
                  </select>
                  <input v-else v-model="s.position" type="text" placeholder="प्रधानाध्यापक / लेखापाल..."
                    class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-[13px] font-semibold text-[#626976] mb-1.5">लिङ्ग</label>
                  <select v-model="s.gender"
                    class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                    <option value="">-- छान्नुहोस् --</option>
                    <option value="पुरुष">पुरुष</option>
                    <option value="महिला">महिला</option>
                    <option value="अन्य">अन्य</option>
                  </select>
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-[13px] font-semibold text-[#626976] mb-1.5">शैक्षिक योग्यता</label>
                  <select v-if="(props.surveyOpts?.education_level ?? []).length"
                    v-model="s.qualification"
                    class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                    <option value="">-- छान्नुहोस् --</option>
                    <option v-for="o in props.surveyOpts.education_level" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
                  </select>
                  <input v-else v-model="s.qualification" type="text" placeholder="SLC, +2, स्नातक..."
                    class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-[13px] font-semibold text-[#626976] mb-1.5">सेवाको प्रकार</label>
                  <select v-model="s.type"
                    class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                    <option value="">-- छान्नुहोस् --</option>
                    <option value="स्थायी">स्थायी</option>
                    <option value="अस्थायी">अस्थायी</option>
                    <option value="करार">करार</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        <p v-else class="text-[14px] text-gray-400 text-center py-8 border-2 border-dashed border-[#e6e7e9] rounded-md">
          <i class="fa-solid fa-user-tie text-gray-300 text-2xl block mb-2"></i>
          कर्मचारी थप्न माथिको बटन थिच्नुहोस्
        </p>
        </template>

        <div v-else class="text-[14px] text-gray-400 text-center py-6 border-2 border-dashed border-[#e6e7e9] rounded-md">
          <i class="fa-solid fa-toggle-off text-gray-300 text-2xl block mb-2"></i>
          कर्मचारी नभएको संस्था।
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 5: सेवाहरू
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 5" class="bg-white rounded-md border border-[#e6e7e9] p-5 space-y-5">
      <h3 class="text-[15px] font-semibold text-[#1d273b] flex items-center gap-2">
        <i class="fa-solid fa-list-check text-[#206bc4]"></i> प्रदान गरिने सेवाहरू
      </h3>

      <div v-if="(props.surveyOpts?.inst_service_type ?? []).length > 0" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <label v-for="svc in props.surveyOpts.inst_service_type" :key="svc.value"
          class="flex items-center gap-2.5 p-3 rounded-md border border-[#e6e7e9] cursor-pointer hover:border-teal-300 hover:bg-[#206bc4]/8 transition-colors"
          :class="{ 'border-teal-400 bg-[#206bc4]/8': form.services.includes(svc.value) }">
          <input type="checkbox" :value="svc.value" v-model="form.services" class="rounded text-[#206bc4] focus:ring-teal-500" />
          <span class="text-sm text-gray-700">{{ svc.label_ne }}</span>
        </label>
      </div>
      <p v-else class="text-sm text-gray-400 text-center py-8 border-2 border-dashed border-[#e6e7e9] rounded-md">
        <i class="fa-solid fa-gear text-gray-300 text-2xl block mb-2"></i>
        सेटिङमा सेवाको प्रकारहरू थप्नुहोस् (<span class="font-mono text-xs">inst_service_type</span>)
      </p>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 6: विशेष विवरण (dynamic by institution type)
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 6" class="bg-white rounded-md border border-[#e6e7e9] p-5 space-y-5">
      <h3 class="text-[15px] font-semibold text-[#1d273b] flex items-center gap-2">
        <i class="fa-solid fa-layer-group text-[#206bc4]"></i>
        {{ typespecificLabel }}
      </h3>

      <!-- No type selected -->
      <div v-if="!institutionSlug" class="text-sm text-gray-500 text-center py-10 border-2 border-dashed border-[#e6e7e9] rounded-md">
        <i class="fa-solid fa-building text-gray-300 text-3xl block mb-3"></i>
        कृपया चरण १ मा संस्थाको प्रकार छान्नुहोस्।
      </div>

      <!-- ── SCHOOL ─────────────────────────────────────────────── -->
      <template v-else-if="institutionSlug === 'school'">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-x-6 gap-y-4">
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">विद्यालयको तह</label>
            <select v-model="form.typespecific.school_level"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="o in (props.surveyOpts?.inst_school_level ?? [])" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">व्यवस्थापन प्रकार</label>
            <select v-model="form.typespecific.management_type"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="o in (props.surveyOpts?.inst_management_type ?? [])" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">शिक्षण माध्यम</label>
            <select v-model="form.typespecific.medium"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="o in (props.surveyOpts?.inst_medium ?? [])" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">कक्षाकोठा संख्या</label>
            <input v-model="form.infrastructure.total_classrooms" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">पुस्तकालय</label>
            <select v-model="form.infrastructure.has_library"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
              <option :value="false">छैन</option>
              <option :value="true">छ</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">प्रयोगशाला</label>
            <select v-model="form.infrastructure.has_lab"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
              <option :value="false">छैन</option>
              <option :value="true">छ</option>
            </select>
          </div>
        </div>

        <!-- Grade-wise enrollment table -->
        <div class="border-t border-[#e6e7e9] pt-5">
          <div class="flex items-center justify-between mb-4">
            <h4 class="text-[14px] font-semibold text-[#1d273b]">कक्षागत विद्यार्थी विवरण</h4>
            <button type="button" @click="addEducationRow"
              class="flex items-center gap-2 px-4 py-2 bg-[#206bc4] text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors">
              <i class="fa-solid fa-plus text-xs"></i> कक्षा थप्नुहोस्
            </button>
          </div>
          <div v-if="form.education.length > 0" class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-gray-50">
                  <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 rounded-l-md">कक्षा / तह</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">छात्र संख्या</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">छात्रा संख्या</th>
                  <th class="px-3 py-2 rounded-r-md"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#e6e7e9]">
                <tr v-for="(row, idx) in form.education" :key="idx">
                  <td class="px-3 py-2">
                    <select v-model="row.grade"
                      class="w-full rounded-md border border-[#e6e7e9] px-2 py-1.5 text-sm focus:ring-2 focus:ring-[#206bc4]/25">
                      <option value="">-- कक्षा --</option>
                      <option v-for="g in (props.surveyOpts?.inst_grade ?? [])" :key="g.value" :value="g.value">{{ g.label_ne }}</option>
                    </select>
                  </td>
                  <td class="px-3 py-2">
                    <input v-model="row.male_students" type="number" min="0" placeholder="0" class="w-28 rounded-md border border-[#e6e7e9] px-2 py-1.5 text-sm focus:ring-2 focus:ring-[#206bc4]/25" />
                  </td>
                  <td class="px-3 py-2">
                    <input v-model="row.female_students" type="number" min="0" placeholder="0" class="w-28 rounded-md border border-[#e6e7e9] px-2 py-1.5 text-sm focus:ring-2 focus:ring-[#206bc4]/25" />
                  </td>
                  <td class="px-3 py-2">
                    <button type="button" @click="removeEducationRow(idx)" class="text-red-400 hover:text-red-600 transition-colors">
                      <i class="fa-solid fa-trash text-xs"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="text-sm text-gray-400 text-center py-6 border-2 border-dashed border-[#e6e7e9] rounded-md">
            कक्षा थप्न माथिको बटन थिच्नुहोस्
          </p>
        </div>

        <!-- Teacher records -->
        <div class="border-t border-[#e6e7e9] pt-5">
          <div class="flex items-center justify-between mb-5">
            <h4 class="text-[14px] font-semibold text-[#1d273b] flex items-center gap-2">
              <i class="fa-solid fa-chalkboard-user text-[#206bc4]"></i> शिक्षक विवरण
            </h4>
            <button type="button" @click="addTeacher"
              class="flex items-center gap-2 px-4 py-2 bg-[#206bc4] text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors">
              <i class="fa-solid fa-plus text-xs"></i> शिक्षक थप्नुहोस्
            </button>
          </div>

          <div v-if="form.teachers.length > 0" class="space-y-2">
            <div v-for="(t, idx) in form.teachers" :key="idx"
              class="border border-indigo-200 rounded-md overflow-hidden">
              <!-- Accordion header -->
              <div class="flex items-center justify-between px-4 py-3 bg-indigo-50 cursor-pointer select-none"
                @click="toggleOpen(openTeachers, idx)">
                <span class="text-[14px] font-semibold text-indigo-700 flex items-center gap-2">
                  <span class="w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs font-bold">{{ idx + 1 }}</span>
                  शिक्षक: {{ t.full_name || 'नयाँ शिक्षक' }} ({{ idx + 1 }})
                </span>
                <div class="flex items-center gap-2">
                  <button type="button" @click.stop="removeTeacher(idx)"
                    class="flex items-center gap-1 px-2.5 py-1 text-xs text-red-500 border border-red-200 rounded hover:bg-red-50 transition-colors">
                    <i class="fa-solid fa-trash text-xs"></i> हटाउनुहोस्
                  </button>
                  <i class="fa-solid text-indigo-600 text-sm transition-transform duration-200"
                    :class="openTeachers[idx] ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </div>
              </div>
              <!-- Accordion body -->
              <div v-show="openTeachers[idx]" class="p-5 bg-white border-t border-indigo-100">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
                  <div class="flex flex-col gap-1">
                    <label class="text-[13px] font-semibold text-[#626976] mb-1.5">पूरा नाम <span class="text-red-500">*</span></label>
                    <input v-model="t.full_name" type="text" placeholder="शिक्षकको पूरा नाम"
                      class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="text-[13px] font-semibold text-[#626976] mb-1.5">शिक्षक श्रेणी / दर्जा</label>
                    <select v-model="t.category"
                      class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                      <option value="">-- श्रेणी छान्नुहोस् --</option>
                      <option v-for="cat in (props.surveyOpts?.inst_teacher_category ?? [])" :key="cat.value" :value="cat.value">
                        {{ cat.label_ne }}
                      </option>
                    </select>
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="text-[13px] font-semibold text-[#626976] mb-1.5">लिङ्ग</label>
                    <select v-model="t.gender"
                      class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                      <option value="">-- छान्नुहोस् --</option>
                      <option value="male">पुरुष</option>
                      <option value="female">महिला</option>
                      <option value="other">अन्य</option>
                    </select>
                  </div>
                  <!-- शैक्षिक योग्यता -->
                  <div class="flex flex-col gap-1">
                    <label class="text-[13px] font-semibold text-[#626976] mb-1.5">शैक्षिक योग्यता</label>
                    <select v-if="(props.surveyOpts?.inst_teacher_qualification ?? []).length"
                      v-model="t.qualification"
                      class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                      <option value="">-- छान्नुहोस् --</option>
                      <option v-for="o in props.surveyOpts.inst_teacher_qualification" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
                    </select>
                    <input v-else v-model="t.qualification" type="text" placeholder="SLC, +२, स्नातक..."
                      class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                  </div>

                  <!-- विषय विशिष्टीकरण (single) -->
                  <div class="flex flex-col gap-1">
                    <label class="text-[13px] font-semibold text-[#626976] mb-1.5">विषय विशिष्टीकरण</label>
                    <select v-if="(props.surveyOpts?.inst_teacher_subject ?? []).length"
                      v-model="t.specialization"
                      class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                      <option value="">-- विशेषज्ञता छान्नुहोस् --</option>
                      <option v-for="o in props.surveyOpts.inst_teacher_subject" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
                    </select>
                    <input v-else v-model="t.specialization" type="text" placeholder="मुख्य विशेषज्ञता..."
                      class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                  </div>

                  <!-- शिक्षक नियुक्ति तह -->
                  <div class="flex flex-col gap-1">
                    <label class="text-[13px] font-semibold text-[#626976] mb-1.5">शिक्षक नियुक्ति तह</label>
                    <select v-if="(props.surveyOpts?.inst_teacher_level ?? []).length"
                      v-model="t.appointment_level"
                      class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                      <option value="">-- तह छान्नुहोस् --</option>
                      <option v-for="o in props.surveyOpts.inst_teacher_level" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
                    </select>
                    <input v-else v-model="t.appointment_level" type="text" placeholder="नियुक्ति तह..."
                      class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                  </div>

                  <!-- बिद्यालयमा प्रवेश मिति -->
                  <div class="flex flex-col gap-1">
                    <label class="text-[13px] font-semibold text-[#626976] mb-1.5">बिद्यालयमा प्रवेश मिति</label>
                    <NepaliDatePicker v-model="t.enrollment_date" placeholder="प्रवेश मिति (बि.सं.)" />
                  </div>

                  <!-- पढाउने विषयहरू (multi-select) -->
                  <div class="flex flex-col gap-1 lg:col-span-2">
                    <label class="text-[13px] font-semibold text-[#626976] mb-1.5">
                      पढाउने विषयहरू
                      <span class="text-[11px] font-normal text-gray-400 ml-1">(एकभन्दा बढी छान्न सकिन्छ)</span>
                    </label>
                    <div v-if="(props.surveyOpts?.inst_teacher_subject ?? []).length"
                      class="grid grid-cols-2 sm:grid-cols-3 gap-2 p-3 rounded-md border border-[#e6e7e9] bg-gray-50">
                      <label v-for="o in props.surveyOpts.inst_teacher_subject" :key="o.value"
                        class="flex items-center gap-2 p-2 rounded cursor-pointer hover:bg-indigo-50 transition-colors"
                        :class="{ 'bg-indigo-50': (t.subjects ?? []).includes(o.value) }">
                        <input type="checkbox" :value="o.value" v-model="t.subjects"
                          class="w-3.5 h-3.5 rounded text-[#206bc4] border-gray-300 focus:ring-[#206bc4]" />
                        <span class="text-[13px] text-gray-700">{{ o.label_ne }}</span>
                      </label>
                    </div>
                    <input v-else v-model="t.subject" type="text" placeholder="गणित, विज्ञान, नेपाली..."
                      class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <p v-else class="text-[14px] text-gray-400 text-center py-8 border-2 border-dashed border-[#e6e7e9] rounded-md">
            <i class="fa-solid fa-chalkboard-user text-gray-300 text-2xl block mb-2"></i>
            शिक्षक थप्न माथिको बटन थिच्नुहोस्
          </p>
        </div>
      </template>

      <!-- ── HEALTH POST ─────────────────────────────────────────── -->
      <template v-else-if="institutionSlug === 'health_post'">

        <!-- §A: Basic info -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">स्वास्थ्य संस्थाको प्रकार <span class="text-red-500">*</span></label>
            <select v-model="form.health.health_type"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="t in (props.surveyOpts?.inst_health_type ?? [])" :key="t.value" :value="t.value">{{ t.label_ne }}</option>
            </select>
          </div>
        </div>

        <!-- §B: Bed capacity -->
        <div class="border-t border-[#e6e7e9] pt-5">
          <h4 class="text-[14px] font-semibold text-[#1d273b] mb-3 flex items-center gap-2">
            <i class="fa-solid fa-bed text-[#206bc4]"></i> शय्या विवरण
          </h4>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-4">
            <div class="flex flex-col gap-1">
              <label class="text-[13px] font-semibold text-[#626976] mb-1.5">कुल शय्या</label>
              <input v-model="form.health.bed_count" type="number" min="0" placeholder="0"
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[13px] font-semibold text-[#626976] mb-1.5">पुरुष वार्ड</label>
              <input v-model="form.health.male_ward_beds" type="number" min="0" placeholder="0"
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[13px] font-semibold text-[#626976] mb-1.5">महिला वार्ड</label>
              <input v-model="form.health.female_ward_beds" type="number" min="0" placeholder="0"
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[13px] font-semibold text-[#626976] mb-1.5">बाल वार्ड</label>
              <input v-model="form.health.children_ward_beds" type="number" min="0" placeholder="0"
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[13px] font-semibold text-[#626976] mb-1.5">प्रसूति वार्ड</label>
              <input v-model="form.health.maternity_beds" type="number" min="0" placeholder="0"
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[13px] font-semibold text-[#626976] mb-1.5">ICU शय्या</label>
              <input v-model="form.health.icu_beds" type="number" min="0" placeholder="0"
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[13px] font-semibold text-[#626976] mb-1.5">आपतकालीन शय्या</label>
              <input v-model="form.health.emergency_beds" type="number" min="0" placeholder="0"
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
            </div>
          </div>
        </div>

        <!-- §C: Patient statistics -->
        <div class="border-t border-[#e6e7e9] pt-5">
          <h4 class="text-[14px] font-semibold text-[#1d273b] mb-3 flex items-center gap-2">
            <i class="fa-solid fa-chart-line text-[#206bc4]"></i> बिरामी तथ्याङ्क
          </h4>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-4">
            <div class="flex flex-col gap-1">
              <label class="text-[13px] font-semibold text-[#626976] mb-1.5">दैनिक OPD (औसत)</label>
              <input v-model="form.health.opd_daily" type="number" min="0" placeholder="0"
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[13px] font-semibold text-[#626976] mb-1.5">मासिक OPD (औसत)</label>
              <input v-model="form.health.opd_monthly" type="number" min="0" placeholder="0"
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[13px] font-semibold text-[#626976] mb-1.5">मासिक IPD (औसत)</label>
              <input v-model="form.health.ipd_monthly" type="number" min="0" placeholder="0"
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[13px] font-semibold text-[#626976] mb-1.5">वार्षिक प्रसव संख्या</label>
              <input v-model="form.health.annual_delivery_count" type="number" min="0" placeholder="0"
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
            </div>
          </div>
        </div>

        <!-- §D: Health services (multi-select from survey options) -->
        <div class="border-t border-[#e6e7e9] pt-5">
          <h4 class="text-[14px] font-semibold text-[#1d273b] mb-3 flex items-center gap-2">
            <i class="fa-solid fa-stethoscope text-[#206bc4]"></i> प्रदान गरिने स्वास्थ्य सेवाहरू
          </h4>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <label v-for="svc in (props.surveyOpts?.inst_health_service ?? [])" :key="svc.value"
              class="flex items-center gap-2.5 p-3 rounded-md border border-[#e6e7e9] cursor-pointer hover:border-blue-300 hover:bg-blue-50 transition-colors"
              :class="{ 'border-blue-400 bg-blue-50': form.health.health_services.includes(svc.value) }">
              <input type="checkbox" :value="svc.value" v-model="form.health.health_services" class="rounded text-[#206bc4]" />
              <span class="text-[13px] text-gray-700">{{ svc.label_ne }}</span>
            </label>
          </div>
        </div>

        <!-- §E: Equipment & Facilities (multi-select) -->
        <div class="border-t border-[#e6e7e9] pt-5">
          <h4 class="text-[14px] font-semibold text-[#1d273b] mb-3 flex items-center gap-2">
            <i class="fa-solid fa-microscope text-[#206bc4]"></i> उपकरण तथा सुविधाहरू
          </h4>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <label v-for="eq in (props.surveyOpts?.inst_health_equipment ?? [])" :key="eq.value"
              class="flex items-center gap-2.5 p-3 rounded-md border border-[#e6e7e9] cursor-pointer hover:border-blue-300 hover:bg-blue-50 transition-colors"
              :class="{ 'border-blue-400 bg-blue-50': form.health.equipment.includes(eq.value) }">
              <input type="checkbox" :value="eq.value" v-model="form.health.equipment" class="rounded text-[#206bc4]" />
              <span class="text-[13px] text-gray-700">{{ eq.label_ne }}</span>
            </label>
          </div>
        </div>

        <!-- §F: Lab services (visible only if laboratory equipment selected) -->
        <div v-if="form.health.equipment.includes('laboratory')" class="border-t border-[#e6e7e9] pt-5">
          <h4 class="text-[14px] font-semibold text-[#1d273b] mb-3 flex items-center gap-2">
            <i class="fa-solid fa-flask text-[#206bc4]"></i> प्रयोगशाला सेवाहरू
          </h4>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <label v-for="lab in (props.surveyOpts?.inst_lab_service ?? [])" :key="lab.value"
              class="flex items-center gap-2.5 p-2.5 rounded-md border border-[#e6e7e9] cursor-pointer hover:border-blue-300 hover:bg-blue-50 transition-colors"
              :class="{ 'border-blue-400 bg-blue-50': form.health.lab_services.includes(lab.value) }">
              <input type="checkbox" :value="lab.value" v-model="form.health.lab_services" class="rounded text-[#206bc4]" />
              <span class="text-[13px] text-gray-700">{{ lab.label_ne }}</span>
            </label>
          </div>
        </div>

        <!-- §G: Health staff (repeatable cards) -->
        <div class="border-t border-[#e6e7e9] pt-5">
          <div class="flex items-center justify-between mb-5">
            <h4 class="text-[14px] font-semibold text-[#1d273b] flex items-center gap-2">
              <i class="fa-solid fa-user-doctor text-[#206bc4]"></i> स्वास्थ्यकर्मी विवरण
            </h4>
            <button type="button" @click="addHealthStaff"
              class="flex items-center gap-2 px-4 py-2 bg-[#206bc4] text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors">
              <i class="fa-solid fa-plus text-xs"></i> स्वास्थ्यकर्मी थप्नुहोस्
            </button>
          </div>

          <div v-if="form.health_staff.length > 0" class="space-y-4">
            <div v-for="(hs, idx) in form.health_staff" :key="idx"
              class="bg-red-50 border border-red-200 rounded-md p-5">
              <div class="flex items-center justify-between mb-4">
                <span class="text-[14px] font-semibold text-red-700 flex items-center gap-2">
                  <span class="w-7 h-7 rounded-full bg-red-600 text-white flex items-center justify-center text-xs font-bold">{{ idx + 1 }}</span>
                  स्वास्थ्यकर्मी {{ idx + 1 }}
                </span>
                <button type="button" @click="removeHealthStaff(idx)"
                  class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-red-500 border border-red-200 rounded-md hover:bg-red-100 transition-colors">
                  <i class="fa-solid fa-trash text-xs"></i> हटाउनुहोस्
                </button>
              </div>
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
                <div class="flex flex-col gap-1">
                  <label class="text-[13px] font-semibold text-[#626976] mb-1.5">पूरा नाम <span class="text-red-500">*</span></label>
                  <input v-model="hs.full_name" type="text" placeholder="स्वास्थ्यकर्मीको पूरा नाम"
                    class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-[13px] font-semibold text-[#626976] mb-1.5">पद / श्रेणी</label>
                  <select v-model="hs.staff_type"
                    class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                    <option value="">-- पद छान्नुहोस् --</option>
                    <option v-for="st in (props.surveyOpts?.inst_health_staff_type ?? [])" :key="st.value" :value="st.value">
                      {{ st.label_ne }}
                    </option>
                  </select>
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-[13px] font-semibold text-[#626976] mb-1.5">लिङ्ग</label>
                  <select v-model="hs.gender"
                    class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                    <option value="">-- छान्नुहोस् --</option>
                    <option value="male">पुरुष</option>
                    <option value="female">महिला</option>
                    <option value="other">अन्य</option>
                  </select>
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-[13px] font-semibold text-[#626976] mb-1.5">शैक्षिक योग्यता</label>
                  <input v-model="hs.qualification" type="text" placeholder="MBBS, BN, ANM, HA..."
                    class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                </div>
              </div>
            </div>
          </div>

          <p v-else class="text-[14px] text-gray-400 text-center py-8 border-2 border-dashed border-[#e6e7e9] rounded-md">
            <i class="fa-solid fa-user-doctor text-gray-300 text-2xl block mb-2"></i>
            स्वास्थ्यकर्मी थप्न माथिको बटन थिच्नुहोस्
          </p>
        </div>

      </template>

      <!-- ── COOPERATIVE ─────────────────────────────────────────── -->
      <template v-else-if="institutionSlug === 'cooperative'">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">सहकारीको प्रकार</label>
            <select v-model="form.typespecific.coop_type"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="o in (props.surveyOpts?.inst_coop_type ?? [])" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">शेयर पुँजी (रु.)</label>
            <input v-model="form.typespecific.share_capital" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">कुल ऋण लगानी (रु.)</label>
            <input v-model="form.typespecific.loan_outstanding" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
        </div>
        <div class="border-t border-[#e6e7e9] pt-4">
          <h4 class="text-[14px] font-semibold text-[#1d273b] mb-3">सदस्य संख्या</h4>
          <div class="grid grid-cols-3 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-[13px] font-semibold text-[#626976] mb-1.5">पुरुष</label>
              <input v-model="form.typespecific.male_members" type="number" min="0" placeholder="0"
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[13px] font-semibold text-[#626976] mb-1.5">महिला</label>
              <input v-model="form.typespecific.female_members" type="number" min="0" placeholder="0"
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[13px] font-semibold text-[#626976] mb-1.5">अन्य</label>
              <input v-model="form.typespecific.other_members" type="number" min="0" placeholder="0"
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
            </div>
          </div>
        </div>
      </template>

      <!-- ── BANK / FINANCIAL ───────────────────────────────────── -->
      <template v-else-if="institutionSlug === 'bank'">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">वित्तीय संस्थाको प्रकार</label>
            <select v-model="form.typespecific.bank_type"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="o in (props.surveyOpts?.inst_bank_type ?? [])" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">शाखा संख्या</label>
            <input v-model="form.typespecific.branch_count" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">कुल निक्षेप (रु. लाखमा)</label>
            <input v-model="form.typespecific.total_deposits" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">कुल ऋण लगानी (रु. लाखमा)</label>
            <input v-model="form.typespecific.total_loans" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
        </div>
      </template>

      <!-- ── GOVT OFFICE ────────────────────────────────────────── -->
      <template v-else-if="institutionSlug === 'govt_office'">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">कार्यालयको तह</label>
            <select v-model="form.typespecific.office_level"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="o in (props.surveyOpts?.inst_office_level ?? [])" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">विभाग / मन्त्रालय</label>
            <input v-model="form.typespecific.department" type="text" placeholder="विभाग वा मन्त्रालयको नाम"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
        </div>
      </template>

      <!-- ── NGO / CBO ───────────────────────────────────────────── -->
      <template v-else-if="institutionSlug === 'ngo_cbo'">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">दर्ताको प्रकार</label>
            <select v-model="form.typespecific.registration_type"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="o in (props.surveyOpts?.inst_registration_type ?? [])" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">प्रमुख दाता / साझेदार</label>
            <input v-model="form.typespecific.donor" type="text" placeholder="दातृ संस्था वा साझेदार"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">लाभग्राही (पुरुष)</label>
            <input v-model="form.typespecific.beneficiaries_m" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">लाभग्राही (महिला)</label>
            <input v-model="form.typespecific.beneficiaries_f" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
        </div>
        <div class="border-t border-[#e6e7e9] pt-4">
          <label class="block text-[13px] font-semibold text-[#626976] mb-3">कार्य क्षेत्रहरू</label>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <label v-for="area in (props.surveyOpts?.inst_working_area ?? [])" :key="area.value"
              class="flex items-center gap-2.5 p-3 rounded-md border border-[#e6e7e9] cursor-pointer hover:border-blue-300 hover:bg-blue-50 transition-colors"
              :class="{ 'border-blue-400 bg-blue-50': form.typespecific.working_areas.includes(area.value) }">
              <input type="checkbox" :value="area.value" v-model="form.typespecific.working_areas" class="rounded text-[#206bc4]" />
              <span class="text-sm text-gray-700">{{ area.label_ne }}</span>
            </label>
          </div>
        </div>
      </template>

      <!-- ── RELIGIOUS ───────────────────────────────────────────── -->
      <template v-else-if="institutionSlug === 'religious'">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">धर्म / संस्थाको प्रकार</label>
            <select v-model="form.typespecific.religion_type"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="r in (props.surveyOpts?.inst_religion_type ?? [])" :key="r.value" :value="r.value">{{ r.label_ne }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">दैनिक भक्तजन/दर्शनार्थी संख्या</label>
            <input v-model="form.typespecific.daily_visitors" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="flex items-center gap-3 p-3 rounded-md border border-[#e6e7e9] cursor-pointer hover:border-blue-300 hover:bg-blue-50 mt-5 transition-colors"
              :class="{ 'border-blue-400 bg-blue-50': form.typespecific.has_trust }">
              <input type="checkbox" v-model="form.typespecific.has_trust" class="w-4 h-4 rounded text-[#206bc4]" />
              <span class="text-[14px] text-gray-700">गुठी / ट्रस्ट दर्ता छ</span>
            </label>
          </div>
        </div>
      </template>

      <!-- ── MARKET ──────────────────────────────────────────────── -->
      <template v-else-if="institutionSlug === 'market'">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">पसल/व्यापार प्रतिष्ठान संख्या</label>
            <input v-model="form.typespecific.shop_count" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">प्रमुख वस्तु / व्यापार</label>
            <input v-model="form.typespecific.goods_types" type="text" placeholder="खाद्यान्न, कपडा, इलेक्ट्रोनिक्स..."
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
        </div>
        <div class="border-t border-[#e6e7e9] pt-4">
          <label class="block text-[13px] font-semibold text-[#626976] mb-3">बजार बस्ने दिन</label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <label v-for="d in (props.surveyOpts?.inst_market_day ?? [])" :key="d.value"
              class="flex items-center gap-2.5 p-3 rounded-md border border-[#e6e7e9] cursor-pointer hover:border-blue-300 hover:bg-blue-50 transition-colors"
              :class="{ 'border-blue-400 bg-blue-50': form.typespecific.market_days.includes(d.value) }">
              <input type="checkbox" :value="d.value" v-model="form.typespecific.market_days" class="rounded text-[#206bc4]" />
              <span class="text-sm text-gray-700">{{ d.label_ne }}</span>
            </label>
          </div>
        </div>
      </template>

      <!-- ── INDUSTRY ────────────────────────────────────────────── -->
      <template v-else-if="institutionSlug === 'industry'">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">उद्योगको प्रकार</label>
            <select v-model="form.typespecific.industry_type"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="i in (props.surveyOpts?.inst_industry_type ?? [])" :key="i.value" :value="i.value">{{ i.label_ne }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">वार्षिक उत्पादन (रु. लाखमा)</label>
            <input v-model="form.typespecific.annual_production" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">कामदार (पुरुष)</label>
            <input v-model="form.typespecific.male_workers" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">कामदार (महिला)</label>
            <input v-model="form.typespecific.female_workers" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
        </div>
      </template>

      <!-- ── MEDIA ───────────────────────────────────────────────── -->
      <template v-else-if="institutionSlug === 'media'">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">मिडियाको प्रकार</label>
            <select v-model="form.typespecific.media_type"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option v-for="m in (props.surveyOpts?.inst_media_type ?? [])" :key="m.value" :value="m.value">{{ m.label_ne }}</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">प्रसार क्षेत्र</label>
            <input v-model="form.typespecific.coverage_area" type="text" placeholder="जिल्ला, प्रदेश, राष्ट्रिय..."
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">प्रकाशन / प्रसारण आवृत्ति</label>
            <input v-model="form.typespecific.frequency" type="text" placeholder="दैनिक, साप्ताहिक, मासिक..."
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
        </div>
      </template>

      <!-- ── FOREST GROUP ────────────────────────────────────────── -->
      <template v-else-if="institutionSlug === 'forest_group'">
        <div class="space-y-6">

          <!-- §A: Basic Info -->
          <div>
            <h4 class="text-[13px] font-bold text-[#206bc4] uppercase tracking-wider mb-3 flex items-center gap-2">
              <i class="fa-solid fa-tree text-xs"></i> §A — वन विवरण
            </h4>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
              <div class="flex flex-col gap-1">
                <label class="text-[13px] font-semibold text-[#626976] mb-1.5">वनको किसिम <span class="text-red-500">*</span></label>
                <select v-model="form.typespecific.forest_type"
                  class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
                  <option value="">-- छान्नुहोस् --</option>
                  <option v-for="o in (props.surveyOpts?.inst_forest_type ?? [])" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
                </select>
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[13px] font-semibold text-[#626976] mb-1.5">वनको क्षेत्रफल (हेक्टेयर)</label>
                <input v-model="form.typespecific.forest_area_hectare" type="number" min="0" step="0.01" placeholder="0.00"
                  class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[13px] font-semibold text-[#626976] mb-1.5">समेटेका वडाहरु</label>
                <input v-model="form.typespecific.forest_covered_wards" type="text" placeholder="उदा: १, ३, ५"
                  class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[13px] font-semibold text-[#626976] mb-1.5">उपभोक्ता घरधुरी संख्या</label>
                <input v-model="form.typespecific.consumer_households" type="number" min="0" placeholder="0"
                  class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[13px] font-semibold text-[#626976] mb-1.5">जम्मा रुखको संख्या</label>
                <input v-model="form.typespecific.total_trees" type="number" min="0" placeholder="0"
                  class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[13px] font-semibold text-[#626976] mb-1.5">वार्षिक बजेट (रु.)</label>
                <input v-model="form.typespecific.annual_budget" type="number" min="0" placeholder="0"
                  class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[13px] font-semibold text-[#626976] mb-1.5">वार्षिक आम्दानी (रु.)</label>
                <input v-model="form.typespecific.annual_income" type="number" min="0" placeholder="0"
                  class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
              </div>
            </div>
          </div>

          <!-- §B: Members & Committee -->
          <div class="border-t border-[#e6e7e9] pt-5">
            <h4 class="text-[13px] font-bold text-[#206bc4] uppercase tracking-wider mb-3 flex items-center gap-2">
              <i class="fa-solid fa-users text-xs"></i> §B — सदस्यता तथा समिति
            </h4>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-x-6 gap-y-4">
              <div class="flex flex-col gap-1">
                <label class="text-[13px] font-semibold text-[#626976] mb-1.5">पुरुष सदस्य (घरमुली)</label>
                <input v-model="form.typespecific.member_count_m" type="number" min="0" placeholder="0"
                  class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[13px] font-semibold text-[#626976] mb-1.5">महिला सदस्य (घरमुली)</label>
                <input v-model="form.typespecific.member_count_f" type="number" min="0" placeholder="0"
                  class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[13px] font-semibold text-[#626976] mb-1.5">समिति अध्यक्षको नाम</label>
                <input v-model="form.typespecific.committee_chairperson" type="text" placeholder="पूरा नाम"
                  class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
              </div>
            </div>
          </div>

          <!-- §C: Tree Types -->
          <div class="border-t border-[#e6e7e9] pt-5">
            <h4 class="text-[13px] font-bold text-[#206bc4] uppercase tracking-wider mb-3 flex items-center gap-2">
              <i class="fa-solid fa-seedling text-xs"></i> §C — रुख तथा वनस्पतिका प्रजाति
            </h4>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
              <label v-for="t in (props.surveyOpts?.inst_tree_type ?? [])" :key="t.value"
                class="flex items-center gap-2.5 p-3 rounded-md border border-[#e6e7e9] cursor-pointer hover:border-green-300 hover:bg-green-50 transition-colors"
                :class="{ 'border-green-400 bg-green-50': form.typespecific.tree_types.includes(t.value) }">
                <input type="checkbox" :value="t.value" v-model="form.typespecific.tree_types" class="rounded text-green-600" />
                <span class="text-sm text-gray-700">{{ t.label_ne }}</span>
              </label>
            </div>
          </div>

          <!-- §D: Medicinal Herbs -->
          <div class="border-t border-[#e6e7e9] pt-5">
            <h4 class="text-[13px] font-bold text-[#206bc4] uppercase tracking-wider mb-3 flex items-center gap-2">
              <i class="fa-solid fa-leaf text-xs"></i> §D — जडिबुटी तथा औषधीय वनस्पति
            </h4>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
              <label v-for="h in (props.surveyOpts?.inst_herb_type ?? [])" :key="h.value"
                class="flex items-center gap-2.5 p-3 rounded-md border border-[#e6e7e9] cursor-pointer hover:border-emerald-300 hover:bg-emerald-50 transition-colors"
                :class="{ 'border-emerald-400 bg-emerald-50': form.typespecific.herb_types.includes(h.value) }">
                <input type="checkbox" :value="h.value" v-model="form.typespecific.herb_types" class="rounded text-emerald-600" />
                <span class="text-sm text-gray-700">{{ h.label_ne }}</span>
              </label>
            </div>
          </div>

          <!-- §E: Wild Animals -->
          <div class="border-t border-[#e6e7e9] pt-5">
            <h4 class="text-[13px] font-bold text-[#206bc4] uppercase tracking-wider mb-3 flex items-center gap-2">
              <i class="fa-solid fa-paw text-xs"></i> §E — वन्यजन्तु
            </h4>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
              <label v-for="w in (props.surveyOpts?.inst_wildlife ?? [])" :key="w.value"
                class="flex items-center gap-2.5 p-3 rounded-md border border-[#e6e7e9] cursor-pointer hover:border-amber-300 hover:bg-amber-50 transition-colors"
                :class="{ 'border-amber-400 bg-amber-50': form.typespecific.wildlife.includes(w.value) }">
                <input type="checkbox" :value="w.value" v-model="form.typespecific.wildlife" class="rounded text-amber-600" />
                <span class="text-sm text-gray-700">{{ w.label_ne }}</span>
              </label>
            </div>
          </div>

          <!-- §F: Forest Products -->
          <div class="border-t border-[#e6e7e9] pt-5">
            <h4 class="text-[13px] font-bold text-[#206bc4] uppercase tracking-wider mb-3 flex items-center gap-2">
              <i class="fa-solid fa-box text-xs"></i> §F — वन उत्पादन
            </h4>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
              <label v-for="p in (props.surveyOpts?.inst_forest_product ?? [])" :key="p.value"
                class="flex items-center gap-2.5 p-3 rounded-md border border-[#e6e7e9] cursor-pointer hover:border-orange-300 hover:bg-orange-50 transition-colors"
                :class="{ 'border-orange-400 bg-orange-50': form.typespecific.forest_products.includes(p.value) }">
                <input type="checkbox" :value="p.value" v-model="form.typespecific.forest_products" class="rounded text-orange-600" />
                <span class="text-sm text-gray-700">{{ p.label_ne }}</span>
              </label>
            </div>
          </div>

          <!-- §G: Conservation -->
          <div class="border-t border-[#e6e7e9] pt-5">
            <h4 class="text-[13px] font-bold text-[#206bc4] uppercase tracking-wider mb-3 flex items-center gap-2">
              <i class="fa-solid fa-shield-halved text-xs"></i> §G — संरक्षण तथा समस्या
            </h4>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-x-6 gap-y-4">
              <div class="flex flex-col gap-1">
                <label class="text-[13px] font-semibold text-[#626976] mb-1.5">वार्षिक डढेलोको घटना (संख्या)</label>
                <input v-model="form.typespecific.fire_incidents_yearly" type="number" min="0" placeholder="0"
                  class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
              </div>
              <div class="flex items-center gap-3 pt-6">
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.typespecific.has_conservation_plan" class="sr-only peer" />
                  <div class="w-10 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-[#206bc4]/25 rounded-full peer peer-checked:bg-[#206bc4] transition-colors"></div>
                  <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
                </label>
                <span class="text-[14px] text-[#626976] font-medium">संरक्षण कार्ययोजना छ</span>
              </div>
              <div class="flex items-center gap-3 pt-6">
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.typespecific.encroachment_issue" class="sr-only peer" />
                  <div class="w-10 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-[#206bc4]/25 rounded-full peer peer-checked:bg-[#206bc4] transition-colors"></div>
                  <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
                </label>
                <span class="text-[14px] text-[#626976] font-medium">अतिक्रमण समस्या छ</span>
              </div>
            </div>
          </div>

        </div>
      </template>

      <!-- ── SPORTS CLUB ─────────────────────────────────────────── -->
      <template v-else-if="institutionSlug === 'sports_club'">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">खेलाडी सदस्य (पुरुष)</label>
            <input v-model="form.typespecific.sports_member_m" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">खेलाडी सदस्य (महिला)</label>
            <input v-model="form.typespecific.sports_member_f" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
        </div>
        <div class="border-t border-[#e6e7e9] pt-4">
          <label class="block text-[13px] font-semibold text-[#626976] mb-3">खेलकुदको प्रकार</label>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <label v-for="g in (props.surveyOpts?.inst_game_type ?? [])" :key="g.value"
              class="flex items-center gap-2.5 p-3 rounded-md border border-[#e6e7e9] cursor-pointer hover:border-blue-300 hover:bg-blue-50 transition-colors"
              :class="{ 'border-blue-400 bg-blue-50': form.typespecific.game_types.includes(g.value) }">
              <input type="checkbox" :value="g.value" v-model="form.typespecific.game_types" class="rounded text-[#206bc4]" />
              <span class="text-sm text-gray-700">{{ g.label_ne }}</span>
            </label>
          </div>
        </div>
      </template>

      <!-- ── COMMUNITY INFRA ─────────────────────────────────────── -->
      <template v-else-if="institutionSlug === 'community_infra'">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">क्षमता (व्यक्ति संख्या)</label>
            <input v-model="form.typespecific.capacity" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">प्रयोगको आवृत्ति</label>
            <select v-model="form.typespecific.frequency"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option value="daily">दैनिक</option>
              <option value="weekly">साप्ताहिक</option>
              <option value="monthly">मासिक</option>
              <option value="occasional">अवसरमा मात्र</option>
            </select>
          </div>
        </div>
      </template>

      <!-- ── PARK / TOURISM ──────────────────────────────────────── -->
      <template v-else-if="institutionSlug === 'park_tourism'">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-6 gap-y-4">
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">पर्यटन स्थलको प्रकार</label>
            <select v-model="form.typespecific.capacity"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]">
              <option value="">-- छान्नुहोस् --</option>
              <option value="natural_park">प्राकृतिक उद्यान</option>
              <option value="heritage_site">सांस्कृतिक सम्पदा</option>
              <option value="eco_tourism">ईको–पर्यटन</option>
              <option value="adventure">साहसिक पर्यटन</option>
              <option value="picnic_spot">पिकनिक स्थल</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">वार्षिक पर्यटक संख्या</label>
            <input v-model="form.typespecific.annual_visitors" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-[13px] font-semibold text-[#626976] mb-1.5">प्रवेश शुल्क (रु.)</label>
            <input v-model="form.typespecific.entry_fee" type="number" min="0" placeholder="0"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
          </div>
        </div>
      </template>

      <!-- ── OTHER / FALLBACK ────────────────────────────────────── -->
      <template v-else>
        <div class="flex flex-col gap-1">
          <label class="text-[13px] font-semibold text-[#626976] mb-1.5">थप जानकारी</label>
          <textarea v-model="form.typespecific.additional_info" rows="5"
            placeholder="संस्थाको विशेष विवरण यहाँ लेख्नुहोस्..."
            class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-[14px] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4] resize-none"></textarea>
        </div>
      </template>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         STEP 7: समीक्षा (Review + Submit)
    ══════════════════════════════════════════════════════════════ -->
    <div v-if="step === 7" class="bg-white rounded-md border border-[#e6e7e9] p-5 space-y-5">
      <h3 class="text-[15px] font-semibold text-[#1d273b] flex items-center gap-2">
        <i class="fa-solid fa-circle-check text-[#206bc4]"></i> समीक्षा गर्नुहोस्
      </h3>

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

      <!-- ── Review sections ──────────────────────────────────────── -->
      <div class="space-y-4">

        <!-- §1 संस्था विवरण -->
        <div class="border border-[#e6e7e9] rounded-md overflow-hidden">
          <div class="bg-blue-50 border-b border-[#e6e7e9] px-4 py-2.5 flex items-center justify-between">
            <span class="text-[13px] font-semibold text-[#1d273b] flex items-center gap-2">
              <i class="fa-solid fa-building text-[#206bc4]"></i> चरण १ — संस्था विवरण
            </span>
            <button type="button" @click="goToStep(1)" class="text-xs text-[#206bc4] hover:underline">सम्पादन</button>
          </div>
          <div class="p-4 grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
            <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">संस्था प्रकार</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ selectedTypeName || '—' }}</p></div>
            <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">नाम (नेपाली)</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.name_np || '—' }}</p></div>
            <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">नाम (अंग्रेजी)</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.name_en || '—' }}</p></div>
            <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">वडा</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ selectedWardNumber ? `वडा ${selectedWardNumber}` : '—' }}</p></div>
            <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">स्वामित्व</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ lbl('ownership_type', form.ownership_type) }}</p></div>
            <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">दर्ता नं.</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.registration_number || '—' }}</p></div>
            <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">स्थापना मिति</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.established_date || '—' }}</p></div>
          </div>
        </div>

        <!-- §2 सम्पर्क विवरण -->
        <div class="border border-[#e6e7e9] rounded-md overflow-hidden">
          <div class="bg-blue-50 border-b border-[#e6e7e9] px-4 py-2.5 flex items-center justify-between">
            <span class="text-[13px] font-semibold text-[#1d273b] flex items-center gap-2">
              <i class="fa-solid fa-address-card text-[#206bc4]"></i> चरण २ — सम्पर्क विवरण
            </span>
            <button type="button" @click="goToStep(2)" class="text-xs text-[#206bc4] hover:underline">सम्पादन</button>
          </div>
          <div class="p-4 grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
            <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">उत्तरदाता</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.respondent_name || '—' }}</p></div>
            <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">सम्पर्क नं.</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.respondent_contact || '—' }}</p></div>
            <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">सर्वेक्षण मिति</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.survey_date || '—' }}</p></div>
            <div v-if="form.gps_lat"><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">GPS</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.gps_lat }}, {{ form.gps_lng }}</p></div>
            <div v-if="form.remarks" class="col-span-2"><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">कैफियत</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.remarks }}</p></div>
          </div>
        </div>

        <!-- §3 भौतिक पूर्वाधार -->
        <div class="border border-[#e6e7e9] rounded-md overflow-hidden">
          <div class="bg-blue-50 border-b border-[#e6e7e9] px-4 py-2.5 flex items-center justify-between">
            <span class="text-[13px] font-semibold text-[#1d273b] flex items-center gap-2">
              <i class="fa-solid fa-hard-hat text-[#206bc4]"></i> चरण ३ — भौतिक पूर्वाधार
            </span>
            <button type="button" @click="goToStep(3)" class="text-xs text-[#206bc4] hover:underline">सम्पादन</button>
          </div>
          <div class="p-4 space-y-3">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
              <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">कुल क्षेत्रफल</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.infrastructure.total_area ? `${form.infrastructure.total_area} वर्गमिटर` : '—' }}</p></div>
              <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">भवनको अवस्था</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ lbl('condition', form.infrastructure.condition) }}</p></div>
              <div>
                <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-1">सुविधाहरू</p>
                <div class="flex flex-wrap gap-1">
                  <span v-if="form.infrastructure.has_electricity" class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">विद्युत</span>
                  <span v-if="form.infrastructure.has_water"       class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full">खानेपानी</span>
                  <span v-if="form.infrastructure.has_internet"    class="px-2 py-0.5 bg-purple-100 text-purple-700 text-xs rounded-full">इन्टरनेट</span>
                  <span v-if="form.infrastructure.has_toilet"      class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded-full">शौचालय</span>
                  <span v-if="form.infrastructure.has_ramp"        class="px-2 py-0.5 bg-orange-100 text-orange-700 text-xs rounded-full">ढलान/Ramp</span>
                  <span v-if="!form.infrastructure.has_electricity && !form.infrastructure.has_water && !form.infrastructure.has_internet && !form.infrastructure.has_toilet && !form.infrastructure.has_ramp" class="text-[13px] text-gray-400">—</span>
                </div>
              </div>
            </div>
            <!-- Buildings list -->
            <div v-if="form.buildings.length > 0">
              <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-2">भवन विवरण</p>
              <div class="overflow-x-auto">
                <table class="w-full text-sm border border-[#e6e7e9] rounded-md">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">भवनको प्रकार</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">भवन संख्या</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">कोठा संख्या</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-[#e6e7e9]">
                    <tr v-for="(b, i) in form.buildings" :key="i">
                      <td class="px-3 py-2 text-[13px] text-[#1d273b]">{{ optLabel('inst_building_type', b.building_type) || b.building_type || '—' }}</td>
                      <td class="px-3 py-2 text-[13px] text-[#1d273b]">{{ b.building_count || '—' }}</td>
                      <td class="px-3 py-2 text-[13px] text-[#1d273b]">{{ b.room_count || '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- §4 कार्यसमिति तथा कर्मचारी -->
        <div class="border border-[#e6e7e9] rounded-md overflow-hidden">
          <div class="bg-blue-50 border-b border-[#e6e7e9] px-4 py-2.5 flex items-center justify-between">
            <span class="text-[13px] font-semibold text-[#1d273b] flex items-center gap-2">
              <i class="fa-solid fa-users text-[#206bc4]"></i> चरण ४ — कार्यसमिति तथा कर्मचारी
            </span>
            <button type="button" @click="goToStep(4)" class="text-xs text-[#206bc4] hover:underline">सम्पादन</button>
          </div>
          <div class="p-4 space-y-3">
            <!-- Committee -->
            <div>
              <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-2">कार्यकारी समिति</p>
              <div v-if="form.executive_committee.has_committee && form.executive_committee.members.length > 0" class="overflow-x-auto">
                <table class="w-full text-sm border border-[#e6e7e9] rounded-md">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">नाम</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">पद</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">लिङ्ग</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">सम्पर्क</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-[#e6e7e9]">
                    <tr v-for="(m, i) in form.executive_committee.members" :key="i">
                      <td class="px-3 py-2 text-[13px] font-medium text-[#1d273b]">{{ m.full_name || '—' }}</td>
                      <td class="px-3 py-2 text-[13px] text-gray-600">{{ m.position || '—' }}</td>
                      <td class="px-3 py-2 text-[13px] text-gray-600">{{ lbl('gender', m.gender) }}</td>
                      <td class="px-3 py-2 text-[13px] text-gray-600">{{ m.contact || '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-else class="text-[13px] text-gray-400">{{ form.executive_committee.has_committee ? 'सदस्य थपिएको छैन' : 'कार्यकारी समिति छैन' }}</p>
            </div>
            <!-- Staff -->
            <div class="border-t border-[#e6e7e9] pt-3">
              <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-2">कर्मचारी</p>
              <div v-if="form.has_staff && form.staff.length > 0" class="overflow-x-auto">
                <table class="w-full text-sm border border-[#e6e7e9] rounded-md">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">नाम</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">पद</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">लिङ्ग</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">सेवा प्रकार</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-[#e6e7e9]">
                    <tr v-for="(s, i) in form.staff" :key="i">
                      <td class="px-3 py-2 text-[13px] font-medium text-[#1d273b]">{{ s.name || '—' }}</td>
                      <td class="px-3 py-2 text-[13px] text-gray-600">{{ s.position || '—' }}</td>
                      <td class="px-3 py-2 text-[13px] text-gray-600">{{ lbl('gender', s.gender) }}</td>
                      <td class="px-3 py-2 text-[13px] text-gray-600">{{ s.type || '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-else class="text-[13px] text-gray-400">{{ form.has_staff ? 'कर्मचारी थपिएको छैन' : 'कर्मचारी छैनन्' }}</p>
            </div>
          </div>
        </div>

        <!-- §5 सेवाहरू -->
        <div class="border border-[#e6e7e9] rounded-md overflow-hidden">
          <div class="bg-blue-50 border-b border-[#e6e7e9] px-4 py-2.5 flex items-center justify-between">
            <span class="text-[13px] font-semibold text-[#1d273b] flex items-center gap-2">
              <i class="fa-solid fa-list-check text-[#206bc4]"></i> चरण ५ — सेवाहरू
            </span>
            <button type="button" @click="goToStep(5)" class="text-xs text-[#206bc4] hover:underline">सम्पादन</button>
          </div>
          <div class="p-4">
            <div v-if="form.services.length > 0" class="flex flex-wrap gap-2">
              <span v-for="svc in form.services" :key="svc"
                class="px-3 py-1 bg-[#206bc4]/10 text-[#206bc4] text-[13px] font-medium rounded-full">
                {{ optLabel('inst_service_type', svc) }}
              </span>
            </div>
            <p v-else class="text-[13px] text-gray-400">कुनै सेवा छानिएको छैन</p>
          </div>
        </div>

        <!-- §6 विशेष विवरण (dynamic) -->
        <div class="border border-[#e6e7e9] rounded-md overflow-hidden">
          <div class="bg-indigo-50 border-b border-[#e6e7e9] px-4 py-2.5 flex items-center justify-between">
            <span class="text-[13px] font-semibold text-[#1d273b] flex items-center gap-2">
              <i class="fa-solid fa-layer-group text-indigo-600"></i> चरण ६ — {{ typespecificLabel }}
            </span>
            <button type="button" @click="goToStep(6)" class="text-xs text-indigo-600 hover:underline">सम्पादन</button>
          </div>
          <div class="p-4">

            <!-- SCHOOL -->
            <template v-if="institutionSlug === 'school'">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3 mb-4">
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">विद्यालयको तह</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ optLabel('inst_school_level', form.typespecific.school_level) }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">व्यवस्थापन</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ optLabel('inst_management_type', form.typespecific.management_type) }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">शिक्षण माध्यम</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ optLabel('inst_medium', form.typespecific.medium) }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">कक्षाकोठा</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.infrastructure.total_classrooms || '—' }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">पुस्तकालय</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.infrastructure.has_library ? 'छ' : 'छैन' }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">प्रयोगशाला</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.infrastructure.has_lab ? 'छ' : 'छैन' }}</p></div>
              </div>
              <!-- Enrollment table -->
              <div v-if="form.education.length > 0" class="mb-4">
                <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-2">कक्षागत विद्यार्थी विवरण</p>
                <table class="w-full text-sm border border-[#e6e7e9] rounded-md">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">कक्षा</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">छात्र</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">छात्रा</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">जम्मा</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-[#e6e7e9]">
                    <tr v-for="(row, i) in form.education" :key="i">
                      <td class="px-3 py-2 text-[13px] font-medium text-[#1d273b]">{{ optLabel('inst_grade', row.grade) }}</td>
                      <td class="px-3 py-2 text-[13px] text-gray-700">{{ row.male_students || 0 }}</td>
                      <td class="px-3 py-2 text-[13px] text-gray-700">{{ row.female_students || 0 }}</td>
                      <td class="px-3 py-2 text-[13px] font-semibold text-[#206bc4]">{{ (Number(row.male_students) || 0) + (Number(row.female_students) || 0) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- Teachers table -->
              <div v-if="form.teachers.length > 0">
                <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-2">शिक्षक विवरण</p>
                <table class="w-full text-sm border border-[#e6e7e9] rounded-md">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">नाम</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">श्रेणी</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">लिङ्ग</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">योग्यता</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">नियुक्ति तह</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">विशिष्टीकरण</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">पढाउने विषय</th>
                      <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">प्रवेश मिति</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-[#e6e7e9]">
                    <tr v-for="(t, i) in form.teachers" :key="i">
                      <td class="px-3 py-2 text-[13px] font-medium text-[#1d273b]">{{ t.full_name || '—' }}</td>
                      <td class="px-3 py-2 text-[13px] text-gray-600">{{ optLabel('inst_teacher_category', t.category) }}</td>
                      <td class="px-3 py-2 text-[13px] text-gray-600">{{ lbl('gender', t.gender) }}</td>
                      <td class="px-3 py-2 text-[13px] text-gray-600">{{ optLabel('inst_teacher_qualification', t.qualification) }}</td>
                      <td class="px-3 py-2 text-[13px] text-gray-600">{{ optLabel('inst_teacher_level', t.appointment_level) }}</td>
                      <td class="px-3 py-2 text-[13px] text-gray-600">{{ optLabel('inst_teacher_subject', t.specialization) }}</td>
                      <td class="px-3 py-2 text-[13px] text-gray-600">
                        <span v-if="t.subjects?.length" class="flex flex-wrap gap-1">
                          <span v-for="s in t.subjects" :key="s" class="bg-indigo-50 text-indigo-700 text-[11px] px-1.5 py-0.5 rounded">{{ optLabel('inst_teacher_subject', s) }}</span>
                        </span>
                        <span v-else>—</span>
                      </td>
                      <td class="px-3 py-2 text-[13px] text-gray-600">{{ t.enrollment_date || '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-if="!form.education.length && !form.teachers.length" class="text-[13px] text-gray-400">विवरण भरिएको छैन</p>
            </template>

            <!-- HEALTH POST -->
            <template v-else-if="institutionSlug === 'health_post'">
              <div class="space-y-4">

                <!-- Basic + bed counts -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-3">
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">स्वास्थ्य संस्थाको प्रकार</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ optLabel('inst_health_type', form.health.health_type) || '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">जम्मा शय्या</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.health.bed_count || '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">पुरुष वार्ड</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.health.male_ward_beds || '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">महिला वार्ड</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.health.female_ward_beds || '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">बाल वार्ड</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.health.children_ward_beds || '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">प्रसूति</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.health.maternity_beds || '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">ICU</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.health.icu_beds || '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">आकस्मिक</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.health.emergency_beds || '—' }}</p></div>
                </div>

                <!-- Patient stats -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-3 border-t border-gray-100 pt-3">
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">दैनिक OPD</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.health.opd_daily || '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">मासिक OPD</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.health.opd_monthly || '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">मासिक IPD</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.health.ipd_monthly || '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">वार्षिक सुत्केरी</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.health.annual_delivery_count || '—' }}</p></div>
                </div>

                <!-- Health services pills -->
                <div class="border-t border-gray-100 pt-3">
                  <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-1.5">स्वास्थ्य सेवाहरू</p>
                  <div class="flex flex-wrap gap-1.5">
                    <span v-for="s in form.health.health_services" :key="s"
                      class="px-2.5 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full font-medium">
                      {{ optLabel('inst_health_service', s) }}
                    </span>
                    <span v-if="!form.health.health_services?.length" class="text-[13px] text-gray-400">—</span>
                  </div>
                </div>

                <!-- Equipment pills -->
                <div class="border-t border-gray-100 pt-3">
                  <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-1.5">उपकरण तथा सुविधा</p>
                  <div class="flex flex-wrap gap-1.5">
                    <span v-for="e in form.health.equipment" :key="e"
                      class="px-2.5 py-0.5 bg-emerald-100 text-emerald-700 text-xs rounded-full font-medium">
                      {{ optLabel('inst_health_equipment', e) }}
                    </span>
                    <span v-if="!form.health.equipment?.length" class="text-[13px] text-gray-400">—</span>
                  </div>
                </div>

                <!-- Lab services pills (only if lab is selected) -->
                <div v-if="form.health.equipment?.includes('laboratory')" class="border-t border-gray-100 pt-3">
                  <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-1.5">प्रयोगशाला सेवाहरू</p>
                  <div class="flex flex-wrap gap-1.5">
                    <span v-for="l in form.health.lab_services" :key="l"
                      class="px-2.5 py-0.5 bg-purple-100 text-purple-700 text-xs rounded-full font-medium">
                      {{ optLabel('inst_lab_service', l) }}
                    </span>
                    <span v-if="!form.health.lab_services?.length" class="text-[13px] text-gray-400">—</span>
                  </div>
                </div>

                <!-- Health staff table -->
                <div v-if="form.health_staff.length > 0" class="border-t border-gray-100 pt-3">
                  <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-2">स्वास्थ्यकर्मी विवरण</p>
                  <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                      <thead>
                        <tr class="bg-gray-50 text-[11px] text-gray-500 uppercase tracking-wide">
                          <th class="text-left px-3 py-2">पूरा नाम</th>
                          <th class="text-left px-3 py-2">पद</th>
                          <th class="text-left px-3 py-2">लिङ्ग</th>
                          <th class="text-left px-3 py-2">योग्यता</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(hs, i) in form.health_staff" :key="i" class="border-t border-gray-100">
                          <td class="px-3 py-2 font-medium text-[#1d273b]">{{ hs.full_name || '—' }}</td>
                          <td class="px-3 py-2">{{ optLabel('inst_health_staff_type', hs.staff_type) || '—' }}</td>
                          <td class="px-3 py-2">{{ hs.gender === 'male' ? 'पुरुष' : hs.gender === 'female' ? 'महिला' : hs.gender === 'other' ? 'अन्य' : '—' }}</td>
                          <td class="px-3 py-2">{{ hs.qualification || '—' }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div v-else class="border-t border-gray-100 pt-3">
                  <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-1">स्वास्थ्यकर्मी</p>
                  <p class="text-[13px] text-gray-400">कोही थपिएको छैन।</p>
                </div>

              </div>
            </template>

            <!-- COOPERATIVE -->
            <template v-else-if="institutionSlug === 'cooperative'">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">सहकारीको प्रकार</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ optLabel('inst_coop_type', form.typespecific.coop_type) }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">शेयर पुँजी</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.share_capital ? `रु. ${form.typespecific.share_capital}` : '—' }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">ऋण लगानी</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.loan_outstanding ? `रु. ${form.typespecific.loan_outstanding}` : '—' }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">सदस्य (पु/म/अ)</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.male_members || 0 }} / {{ form.typespecific.female_members || 0 }} / {{ form.typespecific.other_members || 0 }}</p></div>
              </div>
            </template>

            <!-- BANK -->
            <template v-else-if="institutionSlug === 'bank'">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">वित्तीय संस्था</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ optLabel('inst_bank_type', form.typespecific.bank_type) }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">शाखा संख्या</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.branch_count || '—' }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">कुल निक्षेप</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.total_deposits ? `रु. ${form.typespecific.total_deposits} लाख` : '—' }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">कुल ऋण</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.total_loans ? `रु. ${form.typespecific.total_loans} लाख` : '—' }}</p></div>
              </div>
            </template>

            <!-- GOVT OFFICE -->
            <template v-else-if="institutionSlug === 'govt_office'">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">कार्यालयको तह</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ optLabel('inst_office_level', form.typespecific.office_level) }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">विभाग</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.department || '—' }}</p></div>
              </div>
            </template>

            <!-- NGO/CBO -->
            <template v-else-if="institutionSlug === 'ngo_cbo'">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">दर्ता प्रकार</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ optLabel('inst_registration_type', form.typespecific.registration_type) }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">दाता/साझेदार</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.donor || '—' }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">लाभग्राही (पु/म)</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.beneficiaries_m || 0 }} / {{ form.typespecific.beneficiaries_f || 0 }}</p></div>
                <div class="col-span-2 sm:col-span-3">
                  <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-1">कार्य क्षेत्र</p>
                  <div class="flex flex-wrap gap-1">
                    <span v-for="a in form.typespecific.working_areas" :key="a" class="px-2 py-0.5 bg-teal-100 text-teal-700 text-xs rounded-full">{{ optLabel('inst_working_area', a) }}</span>
                    <span v-if="!form.typespecific.working_areas.length" class="text-[13px] text-gray-400">—</span>
                  </div>
                </div>
              </div>
            </template>

            <!-- RELIGIOUS -->
            <template v-else-if="institutionSlug === 'religious'">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">धर्म / प्रकार</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ optLabel('inst_religion_type', form.typespecific.religion_type) }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">दैनिक दर्शनार्थी</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.daily_visitors || '—' }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">गुठी/ट्रस्ट</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.has_trust ? 'दर्ता छ' : 'छैन' }}</p></div>
              </div>
            </template>

            <!-- MARKET -->
            <template v-else-if="institutionSlug === 'market'">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">पसल संख्या</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.shop_count || '—' }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">प्रमुख वस्तु</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.goods_types || '—' }}</p></div>
                <div class="col-span-2 sm:col-span-1">
                  <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-1">बजार बस्ने दिन</p>
                  <div class="flex flex-wrap gap-1">
                    <span v-for="d in form.typespecific.market_days" :key="d" class="px-2 py-0.5 bg-orange-100 text-orange-700 text-xs rounded-full">{{ optLabel('inst_market_day', d) }}</span>
                    <span v-if="!form.typespecific.market_days.length" class="text-[13px] text-gray-400">—</span>
                  </div>
                </div>
              </div>
            </template>

            <!-- INDUSTRY -->
            <template v-else-if="institutionSlug === 'industry'">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">उद्योगको प्रकार</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ optLabel('inst_industry_type', form.typespecific.industry_type) }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">कामदार (पु/म)</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.male_workers || 0 }} / {{ form.typespecific.female_workers || 0 }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">वार्षिक उत्पादन</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.annual_production ? `रु. ${form.typespecific.annual_production} लाख` : '—' }}</p></div>
              </div>
            </template>

            <!-- MEDIA -->
            <template v-else-if="institutionSlug === 'media'">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">मिडियाको प्रकार</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ optLabel('inst_media_type', form.typespecific.media_type) }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">प्रसार क्षेत्र</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.coverage_area || '—' }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">आवृत्ति</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.frequency || '—' }}</p></div>
              </div>
            </template>

            <!-- FOREST GROUP -->
            <template v-else-if="institutionSlug === 'forest_group'">
              <div class="space-y-4">

                <!-- Basic stats grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-3">
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">वनको किसिम</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ optLabel('inst_forest_type', form.typespecific.forest_type) || '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">क्षेत्रफल (हे.)</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.forest_area_hectare ? `${form.typespecific.forest_area_hectare} हे.` : '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">समेटेका वडाहरु</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.forest_covered_wards || '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">उपभोक्ता घरधुरी</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.consumer_households || '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">जम्मा रुख</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.total_trees || '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">वार्षिक बजेट</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.annual_budget ? `रु. ${form.typespecific.annual_budget}` : '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">वार्षिक आम्दानी</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.annual_income ? `रु. ${form.typespecific.annual_income}` : '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">सदस्य (पु/म)</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.member_count_m || 0 }} / {{ form.typespecific.member_count_f || 0 }}</p></div>
                  <div class="col-span-2"><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">समिति अध्यक्ष</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.committee_chairperson || '—' }}</p></div>
                  <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">डढेलो (वार्षिक)</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.fire_incidents_yearly ?? '—' }}</p></div>
                  <div>
                    <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-1">संरक्षण योजना</p>
                    <span :class="form.typespecific.has_conservation_plan ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="px-2.5 py-0.5 text-xs rounded-full font-medium">{{ form.typespecific.has_conservation_plan ? 'छ' : 'छैन' }}</span>
                  </div>
                  <div>
                    <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-1">अतिक्रमण समस्या</p>
                    <span :class="form.typespecific.encroachment_issue ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500'" class="px-2.5 py-0.5 text-xs rounded-full font-medium">{{ form.typespecific.encroachment_issue ? 'छ' : 'छैन' }}</span>
                  </div>
                </div>

                <!-- Tree types pills -->
                <div class="border-t border-gray-100 pt-3">
                  <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-1.5">रुखका प्रजाति</p>
                  <div class="flex flex-wrap gap-1.5">
                    <span v-for="t in form.typespecific.tree_types" :key="t" class="px-2.5 py-0.5 bg-green-100 text-green-700 text-xs rounded-full font-medium">{{ optLabel('inst_tree_type', t) }}</span>
                    <span v-if="!form.typespecific.tree_types?.length" class="text-[13px] text-gray-400">—</span>
                  </div>
                </div>

                <!-- Herb types pills -->
                <div class="border-t border-gray-100 pt-3">
                  <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-1.5">जडिबुटी तथा औषधीय वनस्पति</p>
                  <div class="flex flex-wrap gap-1.5">
                    <span v-for="h in form.typespecific.herb_types" :key="h" class="px-2.5 py-0.5 bg-emerald-100 text-emerald-700 text-xs rounded-full font-medium">{{ optLabel('inst_herb_type', h) }}</span>
                    <span v-if="!form.typespecific.herb_types?.length" class="text-[13px] text-gray-400">—</span>
                  </div>
                </div>

                <!-- Wildlife pills -->
                <div class="border-t border-gray-100 pt-3">
                  <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-1.5">वन्यजन्तु</p>
                  <div class="flex flex-wrap gap-1.5">
                    <span v-for="w in form.typespecific.wildlife" :key="w" class="px-2.5 py-0.5 bg-amber-100 text-amber-700 text-xs rounded-full font-medium">{{ optLabel('inst_wildlife', w) }}</span>
                    <span v-if="!form.typespecific.wildlife?.length" class="text-[13px] text-gray-400">—</span>
                  </div>
                </div>

                <!-- Forest products pills -->
                <div class="border-t border-gray-100 pt-3">
                  <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-1.5">वन उत्पादन</p>
                  <div class="flex flex-wrap gap-1.5">
                    <span v-for="p in form.typespecific.forest_products" :key="p" class="px-2.5 py-0.5 bg-orange-100 text-orange-700 text-xs rounded-full font-medium">{{ optLabel('inst_forest_product', p) }}</span>
                    <span v-if="!form.typespecific.forest_products?.length" class="text-[13px] text-gray-400">—</span>
                  </div>
                </div>

              </div>
            </template>

            <!-- SPORTS CLUB -->
            <template v-else-if="institutionSlug === 'sports_club'">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">सदस्य (पु/म)</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.sports_member_m || 0 }} / {{ form.typespecific.sports_member_f || 0 }}</p></div>
                <div class="col-span-2">
                  <p class="text-[11px] text-gray-400 uppercase tracking-wide mb-1">खेलकुदको प्रकार</p>
                  <div class="flex flex-wrap gap-1">
                    <span v-for="g in form.typespecific.game_types" :key="g" class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">{{ optLabel('inst_game_type', g) }}</span>
                    <span v-if="!form.typespecific.game_types.length" class="text-[13px] text-gray-400">—</span>
                  </div>
                </div>
              </div>
            </template>

            <!-- COMMUNITY INFRA -->
            <template v-else-if="institutionSlug === 'community_infra'">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">क्षमता</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.capacity ? `${form.typespecific.capacity} जना` : '—' }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">प्रयोग आवृत्ति</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ lbl('frequency', form.typespecific.frequency) }}</p></div>
              </div>
            </template>

            <!-- PARK/TOURISM -->
            <template v-else-if="institutionSlug === 'park_tourism'">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">प्रकार</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ lbl('park_type', form.typespecific.capacity) }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">वार्षिक पर्यटक</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.annual_visitors || '—' }}</p></div>
                <div><p class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">प्रवेश शुल्क</p><p class="text-[14px] font-semibold text-[#1d273b]">{{ form.typespecific.entry_fee ? `रु. ${form.typespecific.entry_fee}` : '—' }}</p></div>
              </div>
            </template>

            <!-- FALLBACK -->
            <template v-else>
              <p class="text-[13px] text-gray-600 whitespace-pre-wrap">{{ form.typespecific.additional_info || '—' }}</p>
            </template>

          </div>
        </div>

      </div><!-- end review sections -->

      <!-- Errors summary -->
      <div v-if="Object.keys(form.errors).length > 0" class="bg-red-50 border border-red-200 rounded-md p-4">
        <p class="text-sm font-semibold text-red-700 mb-2">
          <i class="fa-solid fa-circle-exclamation mr-1"></i> कृपया निम्न त्रुटिहरू सुधार गर्नुहोस्:
        </p>
        <ul class="list-disc list-inside space-y-1">
          <li v-for="(err, field) in form.errors" :key="field" class="text-sm text-red-600">{{ err }}</li>
        </ul>
      </div>
    </div>

    <!-- ── Navigation Buttons ─────────────────────────────────────── -->
    <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm px-6 py-4 flex items-center justify-between gap-3">
      <!-- Left: back + draft controls -->
      <div class="flex items-center gap-2">
        <button
          type="button"
          @click="prevStep"
          :disabled="step === 1"
          class="flex items-center gap-2 px-5 py-2.5 rounded-md border border-[#e6e7e9] text-gray-700 text-sm font-medium hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          <i class="fa-solid fa-arrow-left text-xs"></i>
          पछाडि
        </button>

        <!-- Clear draft -->
        <button
          v-if="draftExists"
          type="button"
          @click="clearDraftData"
          class="flex items-center gap-1.5 px-3 py-2.5 rounded-md border border-red-200 text-red-500 text-xs font-medium hover:bg-red-50 transition-colors"
          title="स्थानीय मस्यौदा मेटाउनुहोस्"
        >
          <i class="fa-solid fa-trash-can text-[10px]"></i> मेटाउनुहोस्
        </button>

        <!-- Auto-save indicator -->
        <span v-if="autoSaveStatus === 'saving'" class="text-[11px] text-gray-400 flex items-center gap-1">
          <i class="fa-solid fa-spinner fa-spin text-[10px]"></i> सुरक्षित हुँदैछ…
        </span>
        <span v-else-if="autoSaveStatus === 'saved'" class="text-[11px] text-green-500 flex items-center gap-1">
          <i class="fa-solid fa-check text-[10px]"></i> सुरक्षित भयो
        </span>
      </div>

      <span class="text-xs text-gray-400 font-medium">{{ step }} / {{ totalSteps }}</span>

      <button
        v-if="step < totalSteps"
        type="button"
        @click="nextStep"
        class="flex items-center gap-2 px-5 py-2.5 rounded-md bg-[#206bc4] text-white text-sm font-medium hover:bg-teal-700 transition-colors"
      >
        अगाडि
        <i class="fa-solid fa-arrow-right text-xs"></i>
      </button>

      <button
        v-else
        type="button"
        @click="submitForm"
        :disabled="form.processing"
        class="flex items-center gap-2 px-6 py-2.5 rounded-md bg-[#206bc4] text-white text-sm font-semibold hover:bg-teal-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
      >
        <i v-if="form.processing" class="fa-solid fa-spinner fa-spin text-xs"></i>
        <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
        {{ isEdit ? 'अद्यावधिक गर्नुहोस्' : 'सुरक्षित गर्नुहोस्' }}
      </button>
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
            <p>तपाईंले यस संस्था फारममा परिवर्तन गर्नुभएको छ तर अझै सुरक्षित गर्नुभएको छैन।</p>
            <p class="text-gray-500 text-[12px]">You have unsaved changes in this institution form. If you leave now, your progress may be lost.</p>
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
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import NepaliDatePicker from '@/components/NepaliDatePicker.vue'
import { saveDraft, getDraft, deleteDraft, enqueue as dbEnqueue } from '@/utils/draftStore'
import { useSyncManager } from '@/utils/syncManager'
import { useNavigationGuard } from '@/composables/useNavigationGuard'

const { refreshCount: syncRefreshCount } = useSyncManager()

// ── Props ──────────────────────────────────────────────────────────
const props = defineProps({
  institution:      { type: Object, default: null },
  wards:            { type: Array,  default: () => [] },
  settlements:      { type: Array,  default: () => [] },
  institutionTypes: { type: Array,  default: () => [] },
  buildingTypes:    { type: Array,  default: () => [] },
  surveyOpts:       { type: Object, default: () => ({}) },
  initialWardId:    { type: Number, default: null },
  mapConfig:        { type: Object, default: () => ({}) },
})

// ── Steps ──────────────────────────────────────────────────────────
const totalSteps = 7
const step = ref(1)

// ── Validation ─────────────────────────────────────────────────────
const stepValidationErrors = computed(() => {
  const f    = form
  const errs = {}

  // Step 1 — संस्था विवरण
  const s1 = []
  if (!f.institution_type_id)                      s1.push('संस्थाको प्रकार आवश्यक छ')
  if (!f.name_np?.trim() && !f.name_en?.trim())    s1.push('संस्थाको नाम आवश्यक छ')
  if (!f.ward_id)                                   s1.push('वडा नं. आवश्यक छ')
  if (s1.length) errs[1] = s1

  // Step 2 — सम्पर्क
  const s2 = []
  if (!f.respondent_name?.trim())  s2.push('उत्तरदाताको नाम आवश्यक छ')
  if (!f.survey_date)              s2.push('सर्वेक्षण मिति आवश्यक छ')
  if (!f.gps_lat || !f.gps_lng)   s2.push('GPS स्थान आवश्यक छ')
  if (s2.length) errs[2] = s2

  return errs
})

const stepsWithErrors      = computed(() => new Set(Object.keys(stepValidationErrors.value).map(Number)))
const hasValidationErrors  = computed(() => stepsWithErrors.value.size > 0)
const validationErrorCount = computed(() =>
  Object.values(stepValidationErrors.value).reduce((t, arr) => t + arr.length, 0)
)

const typespecificLabel = computed(() => {
  const labels = {
    school:          'शिक्षा विवरण',
    health_post:     'स्वास्थ्य विवरण',
    bank:            'वित्तीय विवरण',
    cooperative:     'सहकारी विवरण',
    govt_office:     'कार्यालय विवरण',
    ngo_cbo:         'संस्था विवरण',
    religious:       'धार्मिक विवरण',
    market:          'बजार विवरण',
    industry:        'उद्योग विवरण',
    community_infra: 'सामुदायिक विवरण',
    park_tourism:    'पर्यटन विवरण',
    media:           'मिडिया विवरण',
    forest_group:    'वन विवरण',
    sports_club:     'खेल विवरण',
  }
  return labels[institutionSlug.value] ?? 'विशेष विवरण'
})

const stepLabels = computed(() => [
  'संस्था विवरण', 'सम्पर्क', 'पूर्वाधार',
  'कर्मचारी', 'सेवाहरू',
  typespecificLabel.value, 'समीक्षा',
])

function goToStep(n) {
  if (n >= 1 && n <= totalSteps && n <= step.value) step.value = n
}
function nextStep() { if (step.value < totalSteps) step.value++ }
function prevStep() { if (step.value > 1) step.value-- }

// ── Form state ─────────────────────────────────────────────────────
const existing = props.institution

const form = useForm({
  ward_id:             existing?.ward_id            ?? '',
  settlement_id:       existing?.settlement_id      ?? '',
  institution_type_id: existing?.institution_type_id ?? '',
  name_np:             existing?.name_np            ?? '',
  name_en:             existing?.name_en            ?? '',
  established_date:    existing?.established_date ? existing.established_date.split('T')[0] : '',
  ownership_type:      existing?.ownership_type     ?? '',
  registration_number: existing?.registration_number ?? '',
  respondent_name:     existing?.respondent_name    ?? '',
  respondent_contact:  existing?.respondent_contact ?? '',
  survey_date:         existing?.survey_date ? existing.survey_date.split('T')[0] : new Date().toISOString().split('T')[0],
  remarks:             existing?.remarks            ?? '',
  gps_lat:             existing?.gps_lat            ?? '',
  gps_lng:             existing?.gps_lng            ?? '',
  gps_accuracy:        existing?.gps_accuracy       ?? '',
  gps_altitude:        existing?.gps_altitude       ?? '',
  photo_number:        existing?.photo_number       ?? '',
  buildings: existing?.infrastructure?.buildings ?? [],
  infrastructure: {
    total_area:        existing?.infrastructure?.total_area        ?? '',
    has_electricity:   existing?.infrastructure?.has_electricity   ?? false,
    has_water:         existing?.infrastructure?.has_water         ?? false,
    has_internet:      existing?.infrastructure?.has_internet      ?? false,
    has_toilet:        existing?.infrastructure?.has_toilet        ?? false,
    male_toilet:       existing?.infrastructure?.male_toilet       ?? '',
    female_toilet:     existing?.infrastructure?.female_toilet     ?? '',
    has_ramp:          existing?.infrastructure?.has_ramp          ?? false,
    has_library:       existing?.infrastructure?.has_library       ?? false,
    has_lab:           existing?.infrastructure?.has_lab           ?? false,
    total_classrooms:  existing?.infrastructure?.total_classrooms  ?? '',
    condition:         existing?.infrastructure?.condition         ?? '',
  },
  has_staff: existing?.staff?.length > 0 ? true : false,
  staff:     existing?.staff     ?? [],
  executive_committee: {
    has_committee: existing?.survey_data?.executive_committee?.has_committee ?? false,
    members:       existing?.survey_data?.executive_committee?.members       ?? [],
  },
  services:  existing?.services?.map(s => s.service_name ?? s) ?? [],
  education: existing?.education ?? [],
  teachers: (existing?.survey_data?.teachers ?? []).map(t => ({
    full_name:         t.full_name         ?? '',
    category:          t.category          ?? '',
    gender:            t.gender            ?? '',
    qualification:     t.qualification     ?? '',
    specialization:    t.specialization    ?? '',
    subjects:          Array.isArray(t.subjects) ? t.subjects : [],
    enrollment_date:   t.enrollment_date   ?? '',
    appointment_level: t.appointment_level ?? '',
  })),
  health: {
    health_type:          existing?.healthDetail?.health_type          ?? '',
    bed_count:            existing?.healthDetail?.bed_count            ?? '',
    male_ward_beds:       existing?.healthDetail?.male_ward_beds       ?? '',
    female_ward_beds:     existing?.healthDetail?.female_ward_beds     ?? '',
    children_ward_beds:   existing?.healthDetail?.children_ward_beds   ?? '',
    maternity_beds:       existing?.healthDetail?.maternity_beds       ?? '',
    icu_beds:             existing?.healthDetail?.icu_beds             ?? '',
    emergency_beds:       existing?.healthDetail?.emergency_beds       ?? '',
    opd_daily:            existing?.healthDetail?.opd_daily            ?? '',
    opd_monthly:          existing?.healthDetail?.opd_monthly          ?? '',
    ipd_monthly:          existing?.healthDetail?.ipd_monthly          ?? '',
    annual_delivery_count:existing?.healthDetail?.annual_delivery_count ?? '',
    health_services:      existing?.healthDetail?.health_services      ?? [],
    equipment:            existing?.healthDetail?.equipment            ?? [],
    lab_services:         existing?.healthDetail?.lab_services         ?? [],
    has_ambulance:        existing?.healthDetail?.has_ambulance        ?? false,
    has_xray:             existing?.healthDetail?.has_xray             ?? false,
    has_lab:              existing?.healthDetail?.has_lab              ?? false,
    has_ultrasound:       existing?.healthDetail?.has_ultrasound       ?? false,
    has_ecg:              existing?.healthDetail?.has_ecg              ?? false,
    has_icu:              existing?.healthDetail?.has_icu              ?? false,
    has_operation_theatre:existing?.healthDetail?.has_operation_theatre ?? false,
    has_blood_bank:       existing?.healthDetail?.has_blood_bank       ?? false,
    has_dialysis:         existing?.healthDetail?.has_dialysis         ?? false,
    has_dental:           existing?.healthDetail?.has_dental           ?? false,
    has_physiotherapy:    existing?.healthDetail?.has_physiotherapy    ?? false,
    has_oxygen_plant:     existing?.healthDetail?.has_oxygen_plant     ?? false,
  },
  health_staff: existing?.survey_data?.health_staff ?? [],
  typespecific: {
    // school
    school_level:        existing?.survey_data?.typespecific?.school_level        ?? '',
    management_type:     existing?.survey_data?.typespecific?.management_type     ?? '',
    medium:              existing?.survey_data?.typespecific?.medium              ?? '',
    // health_post
    health_type:         existing?.survey_data?.typespecific?.health_type         ?? '',
    // cooperative
    coop_type:           existing?.survey_data?.typespecific?.coop_type           ?? '',
    male_members:        existing?.survey_data?.typespecific?.male_members        ?? '',
    female_members:      existing?.survey_data?.typespecific?.female_members      ?? '',
    other_members:       existing?.survey_data?.typespecific?.other_members       ?? '',
    share_capital:       existing?.survey_data?.typespecific?.share_capital       ?? '',
    loan_outstanding:    existing?.survey_data?.typespecific?.loan_outstanding    ?? '',
    // bank
    bank_type:           existing?.survey_data?.typespecific?.bank_type           ?? '',
    branch_count:        existing?.survey_data?.typespecific?.branch_count        ?? '',
    total_deposits:      existing?.survey_data?.typespecific?.total_deposits      ?? '',
    total_loans:         existing?.survey_data?.typespecific?.total_loans         ?? '',
    // govt_office
    office_level:        existing?.survey_data?.typespecific?.office_level        ?? '',
    department:          existing?.survey_data?.typespecific?.department          ?? '',
    // ngo_cbo
    registration_type:   existing?.survey_data?.typespecific?.registration_type   ?? '',
    working_areas:       existing?.survey_data?.typespecific?.working_areas       ?? [],
    donor:               existing?.survey_data?.typespecific?.donor               ?? '',
    beneficiaries_m:     existing?.survey_data?.typespecific?.beneficiaries_m     ?? '',
    beneficiaries_f:     existing?.survey_data?.typespecific?.beneficiaries_f     ?? '',
    // religious
    religion_type:       existing?.survey_data?.typespecific?.religion_type       ?? '',
    daily_visitors:      existing?.survey_data?.typespecific?.daily_visitors      ?? '',
    has_trust:           existing?.survey_data?.typespecific?.has_trust           ?? false,
    // market
    market_days:         existing?.survey_data?.typespecific?.market_days         ?? [],
    shop_count:          existing?.survey_data?.typespecific?.shop_count          ?? '',
    goods_types:         existing?.survey_data?.typespecific?.goods_types         ?? '',
    // industry
    industry_type:       existing?.survey_data?.typespecific?.industry_type       ?? '',
    male_workers:        existing?.survey_data?.typespecific?.male_workers        ?? '',
    female_workers:      existing?.survey_data?.typespecific?.female_workers      ?? '',
    annual_production:   existing?.survey_data?.typespecific?.annual_production   ?? '',
    // media
    media_type:          existing?.survey_data?.typespecific?.media_type          ?? '',
    coverage_area:       existing?.survey_data?.typespecific?.coverage_area       ?? '',
    frequency:           existing?.survey_data?.typespecific?.frequency           ?? '',
    // forest_group
    forest_type:          existing?.survey_data?.typespecific?.forest_type          ?? '',
    forest_area_hectare:  existing?.survey_data?.typespecific?.forest_area_hectare  ?? '',
    forest_covered_wards: existing?.survey_data?.typespecific?.forest_covered_wards ?? '',
    consumer_households:  existing?.survey_data?.typespecific?.consumer_households  ?? '',
    total_trees:          existing?.survey_data?.typespecific?.total_trees          ?? '',
    annual_budget:        existing?.survey_data?.typespecific?.annual_budget        ?? '',
    annual_income:        existing?.survey_data?.typespecific?.annual_income        ?? '',
    member_count_m:       existing?.survey_data?.typespecific?.member_count_m       ?? '',
    member_count_f:       existing?.survey_data?.typespecific?.member_count_f       ?? '',
    committee_chairperson:existing?.survey_data?.typespecific?.committee_chairperson?? '',
    tree_types:           existing?.survey_data?.typespecific?.tree_types           ?? [],
    herb_types:           existing?.survey_data?.typespecific?.herb_types           ?? [],
    wildlife:             existing?.survey_data?.typespecific?.wildlife             ?? [],
    forest_products:      existing?.survey_data?.typespecific?.forest_products      ?? [],
    has_conservation_plan:existing?.survey_data?.typespecific?.has_conservation_plan?? false,
    fire_incidents_yearly:existing?.survey_data?.typespecific?.fire_incidents_yearly?? '',
    encroachment_issue:   existing?.survey_data?.typespecific?.encroachment_issue   ?? false,
    // sports_club
    game_types:          existing?.survey_data?.typespecific?.game_types          ?? [],
    sports_member_m:     existing?.survey_data?.typespecific?.sports_member_m     ?? '',
    sports_member_f:     existing?.survey_data?.typespecific?.sports_member_f     ?? '',
    // community_infra / park_tourism
    capacity:            existing?.survey_data?.typespecific?.capacity            ?? '',
    annual_visitors:     existing?.survey_data?.typespecific?.annual_visitors     ?? '',
    entry_fee:           existing?.survey_data?.typespecific?.entry_fee           ?? '',
    // generic
    additional_info:     existing?.survey_data?.typespecific?.additional_info     ?? '',
  },
  duplicate_confirmed: false,
})

// ── Navigation guard ──────────────────────────────────────────────
const { showLeaveModal, confirmLeave, cancelLeave, bypassForSubmit } = useNavigationGuard(
  () => form.isDirty && !form.processing
)

// ── Computed ───────────────────────────────────────────────────────
const isEdit = computed(() => !!props.institution)
const pageTitle = computed(() => isEdit.value ? 'संस्था सम्पादन' : 'नयाँ संस्था')

const filteredSettlements = computed(() =>
  form.ward_id
    ? props.settlements.filter(s => String(s.ward_id) === String(form.ward_id))
    : props.settlements
)

const selectedType = computed(() =>
  props.institutionTypes.find(t => String(t.id) === String(form.institution_type_id))
)
const selectedTypeName = computed(() => selectedType.value?.name_np ?? '')
const institutionSlug  = computed(() => selectedType.value?.slug   ?? '')

const selectedWardNumber = computed(() =>
  props.wards.find(w => String(w.id) === String(form.ward_id))?.ward_number ?? null
)

// ── Static options ─────────────────────────────────────────────────
const facilityOptions = [
  { key: 'has_electricity', label: 'विद्युत' },
  { key: 'has_water',       label: 'खानेपानी' },
  { key: 'has_internet',    label: 'इन्टरनेट' },
  { key: 'has_toilet',      label: 'शौचालय' },
  { key: 'has_ramp',        label: 'ढलान / Ramp' },
]


// ── Label helpers (for review step) ───────────────────────────────
// staticLabels: only for values NOT covered by surveyOpts (pure UI/local enums)
const staticLabels = {
  ownership_type:    { govt:'सरकारी', private:'निजी', community:'सामुदायिक', ngo:'गैसस/NGO', cooperative:'सहकारी', other:'अन्य' },
  gender:            { male:'पुरुष', female:'महिला', other:'अन्य' },
  condition:         { good:'राम्रो', average:'मध्यम', poor:'जीर्ण', damaged:'क्षतिग्रस्त', under_const:'निर्माणाधीन' },
  frequency:         { daily:'दैनिक', weekly:'साप्ताहिक', monthly:'मासिक', occasional:'अवसरमा मात्र' },
  park_type:         { natural_park:'प्राकृतिक उद्यान', heritage_site:'सांस्कृतिक सम्पदा', eco_tourism:'ईको–पर्यटन', adventure:'साहसिक पर्यटन', picnic_spot:'पिकनिक स्थल' },
  staff_type:        { 'स्थायी':'स्थायी', 'अस्थायी':'अस्थायी', 'करार':'करार' },
}
function lbl(map, value) {
  if (!value && value !== false) return '—'
  return staticLabels[map]?.[value] ?? value
}
function optLabel(category, value) {
  if (!value) return '—'
  return (props.surveyOpts?.[category] ?? []).find(o => o.value === value)?.label_ne ?? value
}
// ── Accordion open-state (reactive plain objects: idx → bool) ─────
const openStaff     = reactive({})
const openCommittee = reactive({})
const openTeachers  = reactive({})
function toggleOpen(map, idx) { map[idx] = !map[idx] }
function openOnly(map, idx)   { Object.keys(map).forEach(k => { map[k] = false }); map[idx] = true }
function closeAll(map)        { Object.keys(map).forEach(k => { map[k] = false }) }

// ── Staff helpers ──────────────────────────────────────────────────
function addStaff() {
  const idx = form.staff.length
  form.staff.push({ name: '', position: '', gender: '', qualification: '', type: '' })
  openOnly(openStaff, idx)
}
function removeStaff(idx) {
  form.staff.splice(idx, 1)
  closeAll(openStaff)
}

// ── Building helpers ───────────────────────────────────────────────
function addBuilding() {
  form.buildings.push({ building_type: '', building_count: '', room_count: '' })
}
function removeBuilding(idx) { form.buildings.splice(idx, 1) }

// ── Committee helpers ──────────────────────────────────────────────
function addCommitteeMember() {
  const idx = form.executive_committee.members.length
  form.executive_committee.members.push({
    full_name: '', position: '', gender: '', ethnicity: '',
    age: '', occupation: '', contact: '', disability: false,
  })
  openOnly(openCommittee, idx)
}
function removeCommitteeMember(idx) {
  form.executive_committee.members.splice(idx, 1)
  closeAll(openCommittee)
}

// ── Education helpers ──────────────────────────────────────────────
function addEducationRow() {
  form.education.push({ grade: '', male_students: '', female_students: '' })
}
function removeEducationRow(idx) { form.education.splice(idx, 1) }

// ── Teacher helpers ────────────────────────────────────────────────
function addTeacher() {
  const idx = form.teachers.length
  form.teachers.push({ full_name: '', category: '', gender: '', qualification: '', specialization: '', subjects: [], enrollment_date: '', appointment_level: '' })
  openOnly(openTeachers, idx)
}
function removeTeacher(idx) {
  form.teachers.splice(idx, 1)
  closeAll(openTeachers)
}

// ── Health staff helpers ───────────────────────────────────────────
function addHealthStaff() {
  form.health_staff.push({ full_name: '', staff_type: '', gender: '', qualification: '' })
}
function removeHealthStaff(idx) { form.health_staff.splice(idx, 1) }

// ── GPS ───────────────────────────────────────────────────────────
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
  if (!navigator.geolocation) return
  navigator.geolocation.getCurrentPosition((pos) => {
    form.gps_lat      = pos.coords.latitude.toFixed(7)
    form.gps_lng      = pos.coords.longitude.toFixed(7)
    form.gps_accuracy = pos.coords.accuracy.toFixed(1)
    form.gps_altitude = pos.coords.altitude ? pos.coords.altitude.toFixed(1) : ''
  })
}

// ── Local Draft (IndexedDB) ───────────────────────────────────────
const INST_DRAFT_KEYS = [
  'ward_id', 'settlement_id', 'institution_type_id', 'name_np', 'name_en',
  'established_date', 'ownership_type', 'registration_number',
  'respondent_name', 'respondent_contact', 'survey_date', 'remarks',
  'gps_lat', 'gps_lng', 'gps_accuracy', 'gps_altitude', 'photo_number',
  'buildings', 'infrastructure', 'has_staff', 'staff',
  'executive_committee', 'services', 'education', 'teachers',
  'health', 'health_staff', 'typespecific',
]

const instDraftKey     = computed(() => props.institution ? `inst_draft_${props.institution.id}` : 'inst_draft_new')
const showDraftBanner  = ref(false)
const draftExists      = ref(false)
const draftSavedAt     = ref('')
const autoSaveStatus   = ref('')
let   _autoSaveTimer   = null
let   _statusTimer     = null

function formatDraftTime(iso) {
  try {
    return new Date(iso).toLocaleString('ne-NP', { dateStyle: 'short', timeStyle: 'short' })
  } catch { return iso }
}

async function saveDraftLocally() {
  const data = INST_DRAFT_KEYS.reduce((acc, key) => {
    acc[key] = form[key]
    return acc
  }, {})
  data._savedAt = new Date().toISOString()
  await saveDraft(instDraftKey.value, data)
  draftExists.value  = true
  draftSavedAt.value = formatDraftTime(data._savedAt)
}

async function loadDraftData() {
  const data = await getDraft(instDraftKey.value)
  if (!data) return
  try {
    INST_DRAFT_KEYS.forEach(key => {
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
    console.error('Failed to load institution draft:', e)
  }
  showDraftBanner.value = false
}

async function clearDraftData() {
  await deleteDraft(instDraftKey.value)
  draftExists.value     = false
  showDraftBanner.value = false
  draftSavedAt.value    = ''
  form.reset()
}

function dismissDraftBanner() { showDraftBanner.value = false }

const _instDraftSnapshot = computed(() =>
  INST_DRAFT_KEYS.reduce((acc, key) => { acc[key] = form[key]; return acc }, {})
)
watch(_instDraftSnapshot, () => {
  if (form.processing) return
  autoSaveStatus.value = 'saving'
  clearTimeout(_autoSaveTimer)
  _autoSaveTimer = setTimeout(() => {
    saveDraftLocally()
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
const duplicateData = computed(() => {
  const raw = form.errors.duplicate_warning
  if (!raw) return null
  try { return JSON.parse(raw) } catch { return null }
})
const duplicateMatches = computed(() => duplicateData.value?.matches ?? null)
const duplicateHardBlock = computed(() => duplicateData.value?.hard_block ?? false)
function confirmDuplicate() {
  form.clearErrors('duplicate_warning')
  form.duplicate_confirmed = true
  submitForm()
}
function dismissDuplicate() {
  form.clearErrors('duplicate_warning')
  form.duplicate_confirmed = false
}

// ── Submit ─────────────────────────────────────────────────────────
async function submitForm() {
  bypassForSubmit()
  // Build the transformed payload (same as before)
  const payload = {
    ...form.data(),
    survey_data: {
      executive_committee: form.executive_committee,
      typespecific:        form.typespecific,
      teachers:            form.teachers,
      health_staff:        form.health_staff,
    },
    infrastructure: { ...form.infrastructure, buildings: form.buildings },
    services: form.services.map(v => ({ service_name: v })),
  }

  if (!navigator.onLine) {
    await saveDraftLocally()
    const url    = isEdit.value ? `/institutions/${props.institution.id}` : '/institutions'
    const method = isEdit.value ? 'PUT' : 'POST'
    await dbEnqueue({ type: 'institution', method, url, data: payload })
    await syncRefreshCount()
    showOfflineToast('अफलाइन — पठाउन कतारमा राखियो। जडान फर्किएपछि स्वतः पठाइनेछ।')
    return
  }

  if (isEdit.value) {
    form.transform(() => payload).put(`/institutions/${props.institution.id}`)
  } else {
    form.transform(() => payload).post('/institutions')
  }
}

// ── Mount ──────────────────────────────────────────────────────────
onMounted(async () => {
  if (props.initialWardId) form.ward_id = props.initialWardId

  try {
    const data = await getDraft(instDraftKey.value)
    if (data) {
      draftExists.value     = true
      showDraftBanner.value = true
      draftSavedAt.value    = data._savedAt ? formatDraftTime(data._savedAt) : ''
    }
  } catch {
    // IndexedDB unavailable — silently ignore
  }
})
</script>
