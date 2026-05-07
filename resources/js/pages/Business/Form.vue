<template>
  <div class="max-w-3xl mx-auto space-y-5">

    <!-- Header -->
    <div class="flex items-center gap-3">
      <Link href="/businesses"
        class="w-8 h-8 rounded bg-white border border-[#e6e7e9] flex items-center justify-center text-gray-500 hover:bg-gray-50 transition-colors shrink-0">
        <i class="fa-solid fa-arrow-left text-xs"></i>
      </Link>
      <div class="w-9 h-9 rounded-md bg-amber-600 flex items-center justify-center text-white shrink-0">
        <i class="fa-solid fa-briefcase text-sm"></i>
      </div>
      <div>
        <h2 class="text-base font-bold text-gray-800">
          {{ mode === 'edit' ? 'व्यवसाय सम्पादन' : 'नयाँ व्यवसाय दर्ता' }}
        </h2>
        <p class="text-xs text-gray-500">व्यापार व्यवसाय विवरण भर्नुहोस्</p>
      </div>
    </div>

    <!-- Step indicator -->
    <div class="flex items-center gap-2 bg-white border border-[#e6e7e9] rounded-md px-4 py-3 shadow-sm">
      <template v-for="(s, i) in steps" :key="i">
        <div class="flex items-center gap-1.5">
          <div class="relative shrink-0">
            <div :class="[
              'w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold transition-colors',
              currentStep > i
                ? (stepsWithErrors.has(i + 1) ? 'bg-red-500 text-white' : 'bg-amber-600 text-white')
                : currentStep === i ? 'bg-amber-100 text-amber-700 ring-2 ring-amber-400'
                : 'bg-gray-100 text-gray-400'
            ]">
              <i v-if="currentStep > i && stepsWithErrors.has(i + 1)" class="fa-solid fa-exclamation text-[9px]"></i>
              <span v-else>{{ i + 1 }}</span>
            </div>
            <span v-if="stepsWithErrors.has(i + 1) && currentStep > i"
              class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-red-500 border border-white"></span>
          </div>
          <span v-if="!collapsed_steps" :class="[
            'text-xs font-medium hidden sm:block',
            currentStep === i ? 'text-amber-700' : currentStep > i ? 'text-amber-600' : 'text-gray-400'
          ]">{{ s }}</span>
        </div>
        <div v-if="i < steps.length - 1" class="flex-1 h-px bg-gray-200 min-w-[8px]"></div>
      </template>
    </div>

    <!-- Member banner (visible on steps 1–3 once a member is known) -->
    <div v-if="currentStep >= 1 && resolvedMember"
      class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-md px-4 py-2.5 shadow-sm">
      <div class="w-8 h-8 rounded-full bg-amber-600 flex items-center justify-center text-white text-xs font-bold shrink-0">
        {{ resolvedMember.full_name?.split(' ').filter(Boolean).slice(0,2).map(w => w[0]).join('').toUpperCase() || '?' }}
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-amber-800 leading-tight truncate">{{ resolvedMember.full_name }}</p>
        <p class="text-xs text-amber-600 leading-tight">
          <span v-if="resolvedMember.age">{{ resolvedMember.age }} वर्ष</span>
          <span v-if="resolvedMember.age && resolvedMember.gender"> · </span>
          <span v-if="resolvedMember.gender">{{ resolvedMember.gender === 'male' ? 'पुरुष' : resolvedMember.gender === 'female' ? 'महिला' : 'अन्य' }}</span>
        </p>
      </div>
      <div class="text-xs text-amber-500 font-medium shrink-0">
        <i class="fa-solid fa-user-tie mr-1"></i>व्यवसाय स्वामी
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         STEP 0 — सदस्य चयन (create only)
    ═══════════════════════════════════════════════════════ -->
    <div v-if="currentStep === 0" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-6 space-y-5">
      <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
        <i class="fa-solid fa-user-tie text-amber-500"></i>
        सदस्य छान्नुहोस्
      </h3>

      <!-- If pre-selected from member page -->
      <template v-if="selectedMember && selectedHousehold">
        <div class="bg-amber-50 border border-amber-200 rounded-md p-4 space-y-2">
          <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide">चयन गरिएको सदस्य</p>
          <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
            <div><span class="text-gray-500 text-xs">सदस्यको नाम:</span> <span class="font-semibold">{{ selectedMember.full_name }}</span></div>
            <div><span class="text-gray-500 text-xs">उमेर:</span> <span class="font-medium">{{ selectedMember.age ?? '—' }}</span></div>
            <div><span class="text-gray-500 text-xs">घरधुरी कोड:</span> <span class="font-medium font-mono">{{ selectedHousehold.unique_code }}</span></div>
            <div v-if="selectedHousehold.settlement"><span class="text-gray-500 text-xs">बस्ती:</span> <span class="font-medium">{{ selectedHousehold.settlement.name_np }}</span></div>
          </div>
          <input type="hidden" v-model="form.household_member_id" />
        </div>
      </template>

      <!-- Manual selection -->
      <template v-else>

        <!-- ── Row 1: Ward + Tole ────────────────────────────────────────── -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <!-- Ward -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">वडा नं <span class="text-red-500">*</span></label>
            <select v-model="selectedWardId" @change="onWardChange"
              class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
              <option value="">— वडा छान्नुहोस् —</option>
              <option v-for="w in wards" :key="w.id" :value="w.id">वडा {{ w.ward_number }}</option>
            </select>
          </div>

          <!-- Tole / Settlement (dynamic after ward) -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">
              टोल/बस्ती
              <span class="font-normal text-gray-400">(वैकल्पिक)</span>
            </label>
            <select v-model="selectedSettlementId" @change="onSettlementChange"
              :disabled="!selectedWardId || loadingSettlements"
              class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 disabled:bg-gray-50 disabled:text-gray-400">
              <option value="">— सबै टोल —</option>
              <option v-for="s in settlementList" :key="s.id" :value="s.id">{{ s.name_np }}</option>
            </select>
          </div>
        </div>

        <!-- ── Search box (shown after ward chosen) ──────────────────────── -->
        <div v-if="selectedWardId">
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">
            <i class="fa-solid fa-magnifying-glass mr-1 text-amber-500"></i>
            घरधुरी खोज्नुहोस्
          </label>
          <div class="relative">
            <input
              v-model="hhSearch"
              @input="onHhSearchInput"
              type="text"
              placeholder="घरधुरी कोड / घरमूलीको नाम / सम्पर्क नं..."
              class="w-full border border-gray-200 rounded-md pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400" />
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
            <div v-if="loadingHouseholds"
              class="absolute right-3 top-1/2 -translate-y-1/2">
              <i class="fa-solid fa-circle-notch fa-spin text-amber-400 text-xs"></i>
            </div>
          </div>
          <p class="text-[11px] text-gray-400 mt-1">
            <i class="fa-solid fa-circle-info mr-1"></i>
            कोड (HH-XXXX), घरमूलीको नाम वा फोन नम्बरले खोज्न सकिन्छ
          </p>
        </div>

        <!-- ── Search results / household list ──────────────────────────── -->
        <div v-if="selectedWardId && householdList.length > 0 && !selectedHouseholdId">
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">
            घरधुरी छान्नुहोस्
            <span class="font-normal text-gray-400">({{ householdList.length }} भेटिए)</span>
          </label>
          <div class="border border-gray-200 rounded-md overflow-hidden max-h-56 overflow-y-auto divide-y divide-gray-100">
            <button
              v-for="hh in householdList" :key="hh.id"
              type="button"
              @click="selectHousehold(hh)"
              class="w-full flex items-center gap-3 px-3 py-2.5 text-left hover:bg-amber-50 transition-colors group">
              <div class="w-8 h-8 rounded-md bg-amber-100 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-house text-amber-600 text-xs"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <span class="font-mono text-xs font-bold text-amber-700">{{ hh.unique_code }}</span>
                  <span v-if="hh.settlement" class="text-[10px] bg-gray-100 text-gray-500 rounded px-1.5 py-0.5">
                    {{ hh.settlement.name_np }}
                  </span>
                </div>
                <div class="text-xs text-gray-600 truncate mt-0.5">
                  <span v-if="hh.respondent_name">
                    <i class="fa-solid fa-user text-gray-400 mr-1 text-[10px]"></i>{{ hh.respondent_name }}
                  </span>
                  <span v-if="hh.respondent_contact" class="ml-2 text-gray-400">
                    <i class="fa-solid fa-phone text-[10px] mr-0.5"></i>{{ hh.respondent_contact }}
                  </span>
                </div>
              </div>
              <i class="fa-solid fa-chevron-right text-gray-300 text-xs group-hover:text-amber-400 transition-colors shrink-0"></i>
            </button>
          </div>
        </div>

        <!-- ── Selected household confirmation card ──────────────────────── -->
        <div v-if="selectedHouseholdId">
          <div class="bg-amber-50 border border-amber-300 rounded-md px-4 py-3 flex items-start gap-3">
            <div class="w-9 h-9 rounded-md bg-amber-500 flex items-center justify-center shrink-0 mt-0.5">
              <i class="fa-solid fa-house text-white text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1">चयन गरिएको घरधुरी</p>
              <div class="grid grid-cols-2 gap-x-4 gap-y-0.5 text-sm">
                <div>
                  <span class="text-gray-500 text-xs">कोड:</span>
                  <span class="font-mono font-bold text-amber-700 ml-1">{{ selectedHouseholdData?.unique_code }}</span>
                </div>
                <div v-if="selectedHouseholdData?.settlement">
                  <span class="text-gray-500 text-xs">टोल:</span>
                  <span class="font-medium ml-1">{{ selectedHouseholdData.settlement.name_np }}</span>
                </div>
                <div v-if="selectedHouseholdData?.respondent_name">
                  <span class="text-gray-500 text-xs">घरमूली:</span>
                  <span class="font-medium ml-1">{{ selectedHouseholdData.respondent_name }}</span>
                </div>
                <div v-if="selectedHouseholdData?.respondent_contact">
                  <span class="text-gray-500 text-xs">सम्पर्क:</span>
                  <span class="font-medium ml-1">{{ selectedHouseholdData.respondent_contact }}</span>
                </div>
              </div>
            </div>
            <button type="button" @click="clearHousehold"
              class="text-amber-400 hover:text-amber-600 transition-colors shrink-0 mt-0.5" title="फेर्नुहोस्">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>
        </div>

        <!-- ── No results hint ────────────────────────────────────────────── -->
        <div v-if="selectedWardId && hhSearch && householdList.length === 0 && !loadingHouseholds && !selectedHouseholdId"
          class="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded px-3 py-2">
          <i class="fa-solid fa-circle-info mr-1"></i>
          "{{ hhSearch }}" से मेल खाने घरधुरी भेटिएन। कोड, नाम वा फोन नं जाँच गर्नुहोस्।
        </div>

        <!-- ── Member dropdown (after household confirmed) ───────────────── -->
        <div v-if="selectedHouseholdId">
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">
            सदस्य (व्यवसायमा संलग्न) <span class="text-red-500">*</span>
          </label>
          <div v-if="loadingMembers" class="text-xs text-gray-400 py-2">
            <i class="fa-solid fa-circle-notch fa-spin mr-1"></i>सदस्य लोड हुँदैछ...
          </div>
          <div v-else-if="memberList.length === 0"
            class="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded px-3 py-2">
            <i class="fa-solid fa-circle-info mr-1"></i>
            यस घरधुरीमा "व्यवसायमा संलग्न = छ" भनी चिनिएका र व्यवसाय दर्ता नभएका सदस्य भेटिएनन्।
            परिवार सदस्य (Step 14) मा <strong>व्यवसायमा संलग्न हुनुहुन्छ?</strong> = <strong>छ</strong> सेट गर्नुहोस्।
          </div>
          <select v-else v-model="form.household_member_id"
            class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
            <option value="">— सदस्य छान्नुहोस् —</option>
            <option v-for="m in memberList" :key="m.id" :value="m.id">
              {{ m.full_name }} ({{ m.age ?? '?' }} वर्ष)
            </option>
          </select>
        </div>

      </template>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         STEP 1 — व्यवसाय पहिचान
    ═══════════════════════════════════════════════════════ -->
    <div v-if="currentStep === 1" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-6 space-y-5">
      <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
        <i class="fa-solid fa-id-card text-amber-500"></i>
        व्यवसाय पहिचान
      </h3>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Business Name -->
        <div class="sm:col-span-2">
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">व्यवसायको नाम <span class="text-red-500">*</span></label>
          <input v-model="form.business_name" type="text" placeholder="व्यवसायको नाम लेख्नुहोस्"
            class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400" />
          <p v-if="errors.business_name" class="text-xs text-red-500 mt-1">{{ errors.business_name }}</p>
        </div>

        <!-- Father Name -->
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">बाबुको नाम</label>
          <input v-model="form.owner_father_name" type="text" placeholder="व्यवसाय धनीको बाबुको नाम"
            class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400" />
        </div>

        <!-- Grandfather Name -->
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">बाजेको नाम</label>
          <input v-model="form.owner_grand_father_name" type="text" placeholder="व्यवसाय धनीको बाजेको नाम"
            class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400" />
        </div>

        <!-- Business Location -->
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">व्यवसाय गरेको स्थान</label>
          <select v-model="form.business_location"
            class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400">
            <option value="">— छान्नुहोस् —</option>
            <option v-for="o in surveyOptions.business_location" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>

        <!-- Business Type -->
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">व्यवसायको किसिम</label>
          <select v-model="form.business_type"
            class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400">
            <option value="">— छान्नुहोस् —</option>
            <option v-for="o in surveyOptions.business_type" :key="o.value" :value="o.value">{{ o.label }}</option>
            <option value="other">अन्य</option>
          </select>
        </div>

        <!-- Type Other -->
        <div v-if="form.business_type === 'other'" class="sm:col-span-2">
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">व्यवसायको किसिम (अन्य)</label>
          <input v-model="form.business_type_other" type="text" placeholder="व्यवसायको किसिम लेख्नुहोस्"
            class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400" />
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         STEP 2 — व्यवसायको क्षेत्र
    ═══════════════════════════════════════════════════════ -->
    <div v-if="currentStep === 2" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-6 space-y-5">
      <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
        <i class="fa-solid fa-sitemap text-amber-500"></i>
        व्यवसायको क्षेत्र
      </h3>

      <!-- Business Field -->
      <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1.5">व्यवसाय क्षेत्र</label>
        <select v-model="form.business_field" @change="form.business_sub_category = ''"
          class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400">
          <option value="">— छान्नुहोस् —</option>
          <option v-for="o in surveyOptions.business_field" :key="o.value" :value="o.value">{{ o.label }}</option>
          <option value="other">अन्य</option>
        </select>
      </div>

      <!-- Field Other -->
      <div v-if="form.business_field === 'other'">
        <label class="block text-xs font-semibold text-gray-600 mb-1.5">व्यवसाय क्षेत्र (अन्य)</label>
        <input v-model="form.business_field_other" type="text" placeholder="व्यवसाय क्षेत्र लेख्नुहोस्"
          class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400" />
      </div>

      <!-- Sub-category (conditional) -->
      <div v-if="subCategoryOptions.length > 0">
        <label class="block text-xs font-semibold text-gray-600 mb-1.5">
          व्यवसायको विस्तृत किसिम
          <span class="text-gray-400 font-normal ml-1">({{ fieldLabel }})</span>
        </label>
        <select v-model="form.business_sub_category"
          class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400">
          <option value="">— छान्नुहोस् —</option>
          <option v-for="o in subCategoryOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
      </div>

      <!-- Info when field has no sub-list -->
      <div v-else-if="form.business_field && form.business_field !== 'other'" class="text-xs text-gray-400 bg-gray-50 rounded px-3 py-2">
        <i class="fa-solid fa-circle-info mr-1"></i>
        यस क्षेत्रको लागि थप उप-वर्गीकरण छैन।
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         STEP 3 — दर्ता तथा कर
    ═══════════════════════════════════════════════════════ -->
    <div v-if="currentStep === 3" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-6 space-y-5">
      <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
        <i class="fa-solid fa-file-contract text-amber-500"></i>
        दर्ता तथा कर
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
            <button type="button" @click="currentStep = Number(stepNum)"
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

      <!-- Is Registered -->
      <div>
        <label class="block text-xs font-semibold text-gray-600 mb-2">व्यवसाय दर्ता गरेको छ?</label>
        <div class="flex gap-4">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" v-model="form.is_registered" :value="true" class="accent-amber-600" /> <span class="text-sm">छ</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" v-model="form.is_registered" :value="false" class="accent-amber-600" /> <span class="text-sm">छैन</span>
          </label>
        </div>
      </div>

      <!-- Registration details (if registered) -->
      <template v-if="form.is_registered">
        <!-- Place of registration (multi-select) -->
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-2">दर्ता गरेको स्थान (एक वा बढी)</label>
          <div class="space-y-2">
            <label v-for="o in surveyOptions.business_registration_place" :key="o.value"
              class="flex items-center gap-2.5 cursor-pointer p-2 rounded hover:bg-amber-50 transition-colors">
              <input type="checkbox" :value="o.value" v-model="form.place_of_registration"
                class="w-4 h-4 accent-amber-600 rounded" />
              <span class="text-sm text-gray-700">{{ o.label }}</span>
            </label>
            <label class="flex items-center gap-2.5 cursor-pointer p-2 rounded hover:bg-amber-50">
              <input type="checkbox" value="other" v-model="form.place_of_registration" class="w-4 h-4 accent-amber-600" />
              <span class="text-sm text-gray-700">अन्य</span>
            </label>
          </div>
        </div>

        <!-- Registration numbers (conditional per place) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" v-if="form.place_of_registration?.length">
          <div v-if="form.place_of_registration.includes('Municipality')">
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">पालिकामा दर्ता नं.</label>
            <input v-model="form.reg_no_municipality" type="text" placeholder="दर्ता नं."
              class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400" />
          </div>
          <div v-if="form.place_of_registration.includes('HomeandCottageInd')">
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">घरेलु उद्योगमा दर्ता नं.</label>
            <input v-model="form.reg_no_home_cottage" type="text" placeholder="दर्ता नं."
              class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400" />
          </div>
          <div v-if="form.place_of_registration.includes('CompanyRegistrar')">
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">कम्पनी रजिस्ट्रारमा दर्ता नं.</label>
            <input v-model="form.reg_no_company_registrar" type="text" placeholder="दर्ता नं."
              class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400" />
          </div>
          <div v-if="form.place_of_registration.includes('FCCI')">
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">उद्योग बाणिज्य संघमा दर्ता नं.</label>
            <input v-model="form.reg_no_fcci" type="text" placeholder="दर्ता नं."
              class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400" />
          </div>
          <div v-if="form.place_of_registration.includes('GovernmentOffice')">
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">सरकारी कार्यालयमा दर्ता नं.</label>
            <input v-model="form.reg_no_govt_office" type="text" placeholder="दर्ता नं."
              class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400" />
          </div>
          <div v-if="form.place_of_registration.includes('other')" class="sm:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">अन्य दर्ता स्थान</label>
            <input v-model="form.place_of_registration_other" type="text" placeholder="दर्ता स्थान लेख्नुहोस्"
              class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400" />
          </div>
        </div>

        <!-- PAN/VAT -->
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-2">पान वा भ्याट दर्ता गरेको छ?</label>
          <div class="flex gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="form.has_pan_vat" :value="true" class="accent-amber-600" /> <span class="text-sm">छ</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="form.has_pan_vat" :value="false" class="accent-amber-600" /> <span class="text-sm">छैन</span>
            </label>
          </div>
        </div>

        <!-- PAN/VAT Number (conditional) -->
        <div v-if="form.has_pan_vat === true">
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">
            PAN / VAT नं. <span class="text-gray-400 font-normal">(ऐच्छिक)</span>
          </label>
          <input
            v-model="form.pan_vat_number"
            type="text"
            placeholder="PAN वा VAT नम्बर लेख्नुस्"
            class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400"
          />
        </div>

        <!-- Tax -->
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-2">व्यवसाय कर तिर्नु भएको छ?</label>
          <div class="flex gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="form.paid_business_tax" :value="true" class="accent-amber-600" /> <span class="text-sm">छ</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="form.paid_business_tax" :value="false" class="accent-amber-600" /> <span class="text-sm">छैन</span>
            </label>
          </div>
        </div>

        <!-- Tax Amount -->
        <div v-if="form.paid_business_tax">
          <label class="block text-xs font-semibold text-gray-600 mb-1.5">यस वर्ष कति कर तिर्नु भयो? (रुपैयाँ)</label>
          <input v-model="form.tax_amount" type="number" min="0" placeholder="कर रकम"
            class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400" />
        </div>
      </template>

      <!-- Contact -->
      <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1.5">व्यवसायको सम्पर्क नं.</label>
        <input v-model="form.contact_number" type="text" placeholder="सम्पर्क नम्बर"
          class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400" />
      </div>
    </div>

    <!-- Navigation buttons -->
    <div class="flex items-center justify-between">
      <button v-if="currentStep > 0" @click="prevStep"
        class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-md text-sm text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left text-xs"></i> पछाडि
      </button>
      <div v-else></div>

      <div class="flex items-center gap-3">
        <!-- Save draft -->
        <button v-if="currentStep > 0" @click="saveDraft" :disabled="saving"
          class="flex items-center gap-2 px-4 py-2 border border-amber-200 text-amber-700 rounded-md text-sm hover:bg-amber-50 transition-colors disabled:opacity-50">
          <i class="fa-solid fa-floppy-disk text-xs"></i>
          {{ saving ? 'सुरक्षित...' : 'मस्यौदा सुरक्षित' }}
        </button>

        <!-- Next or Submit -->
        <button v-if="currentStep < 3" @click="nextStep"
          :disabled="!canProceed"
          class="flex items-center gap-2 px-5 py-2 bg-amber-600 text-white rounded-md text-sm font-semibold hover:bg-amber-700 disabled:opacity-50 transition-colors">
          अगाडि <i class="fa-solid fa-arrow-right text-xs"></i>
        </button>
        <button v-else @click="submitForm" :disabled="saving"
          class="flex items-center gap-2 px-5 py-2 bg-amber-600 text-white rounded-md text-sm font-semibold hover:bg-amber-700 disabled:opacity-50 transition-colors">
          <i class="fa-solid fa-check text-xs"></i>
          {{ saving ? 'सुरक्षित...' : (mode === 'edit' ? 'अपडेट गर्नुहोस्' : 'सुरक्षित गर्नुहोस्') }}
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
            <p>तपाईंले यस व्यवसाय फारममा परिवर्तन गर्नुभएको छ तर अझै सुरक्षित गर्नुभएको छैन।</p>
            <p class="text-gray-500 text-[12px]">You have unsaved changes in this business form. If you leave now, your progress may be lost.</p>
          </div>
          <div class="px-5 pb-5 flex gap-3">
            <button type="button" @click="cancelLeave"
              class="flex-1 px-4 py-2.5 bg-amber-600 text-white text-[13px] font-semibold rounded-lg hover:bg-amber-700 transition">
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
import { ref, computed, watch, onMounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useNavigationGuard } from '@/composables/useNavigationGuard'

const props = defineProps({
  mode:              { type: String,  default: 'create' },
  business:          { type: Object,  default: null },
  member:            { type: Object,  default: null },
  household:         { type: Object,  default: null },
  wards:             { type: Array,   default: () => [] },
  selectedHousehold: { type: Object,  default: null },
  selectedMember:    { type: Object,  default: null },
  surveyOptions:     { type: Object,  default: () => ({}) },
})

const page   = usePage()
const errors = computed(() => page.props.errors ?? {})
const saving = ref(false)

// ── Steps ──────────────────────────────────────────────────────────────────
const steps = props.mode === 'create'
  ? ['सदस्य चयन', 'पहिचान', 'क्षेत्र', 'दर्ता/कर']
  : ['पहिचान', 'क्षेत्र', 'दर्ता/कर']

const currentStep = ref(props.mode === 'create' ? 0 : 1)

// ── Form data ──────────────────────────────────────────────────────────────
const form = ref({
  household_member_id:        props.selectedMember?.id ?? props.business?.household_member_id ?? '',
  // Step 1
  business_name:              props.business?.business_name ?? '',
  owner_father_name:          props.business?.owner_father_name ?? '',
  owner_grand_father_name:    props.business?.owner_grand_father_name ?? '',
  business_location:          props.business?.business_location ?? '',
  business_type:              props.business?.business_type ?? '',
  business_type_other:        props.business?.business_type_other ?? '',
  // Step 2
  business_field:             props.business?.business_field ?? '',
  business_field_other:       props.business?.business_field_other ?? '',
  business_sub_category:      props.business?.business_sub_category ?? '',
  // Step 3
  is_registered:              props.business?.is_registered ?? null,
  place_of_registration:      props.business?.place_of_registration ?? [],
  place_of_registration_other:props.business?.place_of_registration_other ?? '',
  reg_no_municipality:        props.business?.reg_no_municipality ?? '',
  reg_no_home_cottage:        props.business?.reg_no_home_cottage ?? '',
  reg_no_company_registrar:   props.business?.reg_no_company_registrar ?? '',
  reg_no_fcci:                props.business?.reg_no_fcci ?? '',
  reg_no_govt_office:         props.business?.reg_no_govt_office ?? '',
  has_pan_vat:                props.business?.has_pan_vat ?? null,
  pan_vat_number:             props.business?.pan_vat_number ?? '',
  paid_business_tax:          props.business?.paid_business_tax ?? null,
  tax_amount:                 props.business?.tax_amount ?? '',
  contact_number:             props.business?.contact_number ?? '',
})

// ── Navigation guard ──────────────────────────────────────────────────────
const formTouched = ref(false)
watch(form, () => { formTouched.value = true }, { deep: true })

const { showLeaveModal, confirmLeave, cancelLeave, bypassForSubmit } = useNavigationGuard(
  () => formTouched.value && !saving.value
)

// ── Ward / Settlement / Household / Member cascades ──────────────────────
const selectedWardId       = ref('')
const selectedSettlementId = ref('')
const selectedHouseholdId  = ref('')
const settlementList       = ref([])
const householdList        = ref([])
const memberList           = ref([])
const loadingSettlements   = ref(false)
const loadingHouseholds    = ref(false)
const loadingMembers       = ref(false)
const hhSearch             = ref('')
const selectedHouseholdData = ref(null)   // full object of chosen household

let hhSearchTimer = null

async function onWardChange() {
  selectedSettlementId.value      = ''
  selectedHouseholdId.value       = ''
  selectedHouseholdData.value     = null
  form.value.household_member_id  = ''
  settlementList.value            = []
  householdList.value             = []
  memberList.value                = []
  hhSearch.value                  = ''
  if (!selectedWardId.value) return

  // Load settlements for tole filter
  loadingSettlements.value = true
  try {
    const res = await fetch(`/api/businesses/ward-settlements?ward_id=${selectedWardId.value}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    settlementList.value = await res.json()
  } finally {
    loadingSettlements.value = false
  }
}

async function onSettlementChange() {
  selectedHouseholdId.value      = ''
  selectedHouseholdData.value    = null
  form.value.household_member_id = ''
  memberList.value               = []
  householdList.value            = []
  hhSearch.value                 = ''
  // Re-run search if there was a query
  if (hhSearch.value) fetchHouseholds()
}

function onHhSearchInput() {
  clearTimeout(hhSearchTimer)
  selectedHouseholdId.value      = ''
  selectedHouseholdData.value    = null
  form.value.household_member_id = ''
  memberList.value               = []
  hhSearchTimer = setTimeout(fetchHouseholds, 350)
}

async function fetchHouseholds() {
  if (!selectedWardId.value) return
  loadingHouseholds.value = true
  try {
    const params = new URLSearchParams({ ward_id: selectedWardId.value })
    if (selectedSettlementId.value) params.set('settlement_id', selectedSettlementId.value)
    if (hhSearch.value.trim())      params.set('q', hhSearch.value.trim())
    const res = await fetch(`/api/businesses/households?${params}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    householdList.value = await res.json()
  } finally {
    loadingHouseholds.value = false
  }
}

async function selectHousehold(hh) {
  selectedHouseholdId.value   = hh.id
  selectedHouseholdData.value = hh
  householdList.value         = []   // collapse results list
  form.value.household_member_id = ''
  memberList.value               = []

  loadingMembers.value = true
  try {
    const res = await fetch(`/api/businesses/members?household_id=${hh.id}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    memberList.value = await res.json()
  } finally {
    loadingMembers.value = false
  }
}

function clearHousehold() {
  selectedHouseholdId.value      = ''
  selectedHouseholdData.value    = null
  form.value.household_member_id = ''
  memberList.value               = []
  householdList.value            = []
  hhSearch.value                 = ''
}

// ── Resolved member for banner ────────────────────────────────────────────
// edit mode: props.member  |  create pre-selected: props.selectedMember  |  create manual: pick from memberList
const resolvedMember = computed(() => {
  if (props.member) return props.member
  if (props.selectedMember) return props.selectedMember
  if (form.value.household_member_id && memberList.value.length) {
    return memberList.value.find(m => m.id == form.value.household_member_id) ?? null
  }
  return null
})

// ── Sub-category logic ────────────────────────────────────────────────────
const subKeyMap = {
  ProductionIndustry:          'sub_ProductionIndustry',
  AgricultureAnimalHusbandary: 'sub_AgricultureAnimalHusbandary',
  Suppliers:                   'sub_Suppliers',
  InformationAndCommunication: 'sub_InformationAndCommunication',
  HotelAndRestaurant:          'sub_HotelAndRestaurant',
  VehicleAndTrasnportation:    'sub_VehicleAndTransportation',
  EducationalInstutions:       'sub_EducationalInstutions',
  ConsultancyBusiness:         'sub_ConsultancyBusiness',
  MechinaryMaintenance:        'sub_MechinaryMaintenance',
  HospitalandClinc:            'sub_HospitalandClinc',
  FinancialInstutions:         'sub_FinancialInstutions',
  AbroadjobsSecurity:          'sub_AbroadjobsSecurity',
  FancyBusiness:               'sub_FancyBusiness',
  OtherShopBusiness:           'sub_OtherShopBusiness',
}

const subCategoryOptions = computed(() => {
  const key = subKeyMap[form.value.business_field]
  return key ? (props.surveyOptions[key] ?? []) : []
})

const fieldLabel = computed(() => {
  const f = props.surveyOptions.business_field?.find(o => o.value === form.value.business_field)
  return f?.label ?? ''
})

// ── Validation ─────────────────────────────────────────────────────────────
const stepValidationErrors = computed(() => {
  const f    = form.value
  const errs = {}

  // Step 1 — पहिचान
  const s1 = []
  if (!f.business_name?.trim()) s1.push('व्यवसायको नाम आवश्यक छ')
  if (!f.business_type)         s1.push('व्यवसायको प्रकार आवश्यक छ')
  if (s1.length) errs[1] = s1

  // Step 2 — क्षेत्र
  const s2 = []
  if (!f.business_field) s2.push('व्यवसायको क्षेत्र आवश्यक छ')
  if (s2.length) errs[2] = s2

  // Step 3 — दर्ता/कर
  const s3 = []
  if (f.is_registered === null || f.is_registered === '') s3.push('दर्ता अवस्था (छ/छैन) आवश्यक छ')
  if (s3.length) errs[3] = s3

  return errs
})

const stepsWithErrors      = computed(() => new Set(Object.keys(stepValidationErrors.value).map(Number)))
const hasValidationErrors  = computed(() => stepsWithErrors.value.size > 0)
const validationErrorCount = computed(() =>
  Object.values(stepValidationErrors.value).reduce((t, arr) => t + arr.length, 0)
)

// ── Step navigation ────────────────────────────────────────────────────────
const canProceed = computed(() => {
  if (currentStep.value === 0) {
    return !!form.value.household_member_id
  }
  if (currentStep.value === 1) {
    return !!form.value.business_name.trim()
  }
  return true
})

function nextStep() {
  if (!canProceed.value) return
  // In edit mode steps are 1,2,3 but array is 0-indexed at step 1
  const maxStep = props.mode === 'create' ? 3 : 3
  if (currentStep.value < maxStep) currentStep.value++
}

function prevStep() {
  if (currentStep.value > (props.mode === 'create' ? 0 : 1)) {
    currentStep.value--
  }
}

// ── Save / Submit ──────────────────────────────────────────────────────────
function saveDraft() {
  bypassForSubmit()
  saving.value = true
  const url    = props.mode === 'edit' ? `/businesses/${props.business.id}` : '/businesses'
  const method = props.mode === 'edit' ? 'put' : 'post'

  router[method](url, form.value, {
    onSuccess: () => { formTouched.value = false },
    onFinish:  () => { saving.value = false },
  })
}

function submitForm() {
  saveDraft()
}

// Pre-fill if coming from member page
onMounted(() => {
  if (props.selectedMember) {
    form.value.household_member_id = props.selectedMember.id
  }
})
</script>
