<template>
  <div class="space-y-5">

    <!-- Page header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-lg font-bold text-gray-900">सेटिङहरू</h1>
        <p class="text-xs text-gray-500 mt-0.5">Municipal Settings</p>
      </div>
      <div class="flex items-center gap-2">
        <!-- Locked state -->
        <template v-if="isLocked">
          <div class="flex items-center gap-2 px-3 py-1.5 bg-red-50 border border-red-200 rounded-md">
            <i class="fa-solid fa-lock text-red-500 text-xs"></i>
            <div class="leading-none">
              <p class="text-[12px] font-semibold text-red-700">लक गरिएको छ</p>
              <p v-if="lockedByName" class="text-[10px] text-red-400 mt-0.5">{{ lockedByName }} द्वारा</p>
            </div>
          </div>
          <button v-if="isSuperAdmin" @click="unlockSettings"
            class="inline-flex items-center gap-1.5 h-8 px-3 bg-amber-500 hover:bg-amber-600 text-white text-[12px] font-medium rounded-md transition-colors">
            <i class="fa-solid fa-lock-open text-[10px]"></i>
            अनलक गर्नुहोस्
          </button>
        </template>
        <!-- Unlocked state -->
        <template v-else>
          <div class="flex items-center gap-2 px-3 py-1.5 bg-green-50 border border-green-200 rounded-md">
            <i class="fa-solid fa-lock-open text-green-500 text-xs"></i>
            <p class="text-[12px] font-semibold text-green-700">सम्पादन योग्य</p>
          </div>
          <button @click="lockSettings"
            class="inline-flex items-center gap-1.5 h-8 px-3 bg-[#1d273b] hover:bg-[#2d3a4f] text-white text-[12px] font-medium rounded-md transition-colors">
            <i class="fa-solid fa-lock text-[10px]"></i>
            लक गर्नुहोस्
          </button>
        </template>
      </div>
    </div>

    <!-- Locked warning banner (non-super-admin) -->
    <div v-if="isLocked && !isSuperAdmin"
      class="flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-md">
      <i class="fa-solid fa-lock text-red-500 shrink-0"></i>
      <div>
        <p class="text-[13px] font-semibold text-red-700">सेटिङ लक गरिएको छ</p>
        <p class="text-[11px] text-red-500 mt-0.5">
          सम्पादन गर्न Super Admin सम्पर्क गर्नुहोस्।
          <span v-if="lockedAt"> — {{ lockedAt }}</span>
        </p>
      </div>
    </div>

    <!-- Tab bar -->
    <div class="flex items-center gap-1 bg-white rounded-md border border-[#e6e7e9] shadow-sm p-1 overflow-x-auto">
      <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
        class="flex items-center gap-1.5 px-4 py-2 rounded text-sm font-medium whitespace-nowrap transition-colors"
        :class="activeTab === t.key
          ? 'bg-[#206bc4] text-white shadow-sm'
          : 'text-gray-500 hover:bg-gray-100'">
        <i :class="[t.icon, 'text-xs']"></i>
        {{ t.label }}
      </button>
    </div>

    <!-- Lock overlay for non-super-admin -->
    <div v-if="locked" class="flex items-center gap-3 px-4 py-3 bg-amber-50 border border-amber-200 rounded-md">
      <i class="fa-solid fa-lock text-amber-500 shrink-0 text-sm"></i>
      <p class="text-[13px] text-amber-800 font-medium">
        सेटिङ लक गरिएको छ — हेर्न मात्र सकिन्छ, सम्पादन गर्न Super Admin ले अनलक गर्नुपर्छ।
      </p>
    </div>

    <!-- ── General Tab ──────────────────────────────────────────────── -->
    <div v-if="activeTab === 'general'" class="space-y-5" :class="locked ? 'pointer-events-none opacity-60' : ''">

      <!-- Logo upload -->
      <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-image text-[#206bc4] text-xs"></i>
          लोगो
        </h2>
        <div class="flex items-center gap-5">
          <div class="w-20 h-20 rounded-md border-2 border-dashed border-[#e6e7e9] flex items-center justify-center bg-gray-50 overflow-hidden shrink-0">
            <img v-if="props.lg.logo" :src="`/storage/${props.lg.logo}`" class="w-full h-full object-contain" />
            <i v-else class="fa-solid fa-image text-gray-300 text-2xl"></i>
          </div>
          <div>
            <form @submit.prevent="uploadLogo">
              <input ref="logoInputRef" type="file" accept="image/*" class="hidden" @change="onLogoSelect" />
              <button type="button" @click="logoInputRef.click()"
                class="px-4 py-2 bg-[#206bc4]/8 hover:bg-[#206bc4]/15 text-[#206bc4] text-sm font-medium rounded-md transition-colors">
                <i class="fa-solid fa-upload mr-1.5 text-xs"></i>
                लोगो अपलोड गर्नुहोस्
              </button>
              <p class="text-xs text-gray-400 mt-1.5">PNG, JPG, SVG — अधिकतम 2MB</p>
              <div v-if="logoFile" class="mt-2 flex items-center gap-2">
                <span class="text-xs text-gray-600">{{ logoFile.name }}</span>
                <button type="submit" :disabled="logoForm.processing || locked"
                  class="px-3 py-1 bg-[#206bc4] text-white text-xs rounded">
                  <i v-if="logoForm.processing" class="fa-solid fa-spinner fa-spin mr-1"></i>
                  अपलोड
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- General info form -->
      <form @submit.prevent="saveGeneral">
        <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5 space-y-4">
          <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
            <i class="fa-solid fa-building-columns text-[#206bc4] text-xs"></i>
            आधारभूत विवरण
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="field-label">
                नाम (नेपाली) <span class="text-red-400">*</span>
                <span v-if="selectedLgId" class="ml-1 text-[10px] text-[#206bc4] font-normal">
                  <i class="fa-solid fa-lock text-[9px]"></i> स्वतः भरिएको
                </span>
              </label>
              <input v-model="genForm.name_np" required type="text"
                :readonly="!!selectedLgId"
                :class="['field-input', selectedLgId ? 'bg-[#206bc4]/5 text-[#626976] cursor-default select-none' : '']" />
              <p v-if="genForm.errors.name_np" class="field-error">{{ genForm.errors.name_np }}</p>
            </div>
            <div>
              <label class="field-label">
                नाम (अंग्रेजी) <span class="text-red-400">*</span>
                <span v-if="selectedLgId" class="ml-1 text-[10px] text-[#206bc4] font-normal">
                  <i class="fa-solid fa-lock text-[9px]"></i> स्वतः भरिएको
                </span>
              </label>
              <input v-model="genForm.name_en" required type="text"
                :readonly="!!selectedLgId"
                :class="['field-input', selectedLgId ? 'bg-[#206bc4]/5 text-[#626976] cursor-default select-none' : '']" />
              <p v-if="genForm.errors.name_en" class="field-error">{{ genForm.errors.name_en }}</p>
            </div>
            <div>
              <label class="field-label">प्रकार <span class="text-red-400">*</span></label>
              <select v-model="genForm.type" required class="field-input">
                <option value="municipality">Municipality (नगरपालिका)</option>
                <option value="rural_municipality">Rural Municipality (गाउँपालिका)</option>
                <option value="sub_metropolitan">Sub-Metropolitan (उपमहानगरपालिका)</option>
                <option value="metropolitan">Metropolitan (महानगरपालिका)</option>
              </select>
            </div>
            <!-- ── Province → District → Local Level cascade ── -->
            <div>
              <label class="field-label">प्रदेश <span class="text-red-400">*</span></label>
              <select v-model="selectedProvince" @change="onProvinceChange" class="field-input">
                <option value="">-- प्रदेश छान्नुहोस् --</option>
                <option v-for="p in props.provinces" :key="p.id" :value="p.id">
                  {{ p.name_np }} ({{ p.name_en }})
                </option>
              </select>
            </div>

            <div>
              <label class="field-label">जिल्ला <span class="text-red-400">*</span></label>
              <select v-model="genForm.district_id" @change="onDistrictChange" required class="field-input"
                :disabled="!selectedProvince">
                <option value="">-- जिल्ला छान्नुहोस् --</option>
                <option v-for="d in filteredDistricts" :key="d.id" :value="d.id">
                  {{ d.name_np }} ({{ d.name_en }})
                </option>
              </select>
            </div>

            <div>
              <label class="field-label">स्थानीय तह <span class="text-[#9aa0ac] font-normal text-[11px]">(ऐच्छिक)</span></label>
              <div class="relative">
                <select v-model="selectedLgId" @change="onLgSelect" class="field-input pr-10"
                  :disabled="!genForm.district_id || lgLoading">
                  <option value="">-- स्थानीय तह छान्नुहोस् --</option>
                  <option v-for="lg in localGovs" :key="lg.id" :value="lg.id">
                    {{ lg.name_np }} — {{ lg.name_en }}
                  </option>
                </select>
                <div v-if="lgLoading" class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                  <i class="fa-solid fa-spinner fa-spin text-[#9aa0ac] text-sm"></i>
                </div>
              </div>
              <p v-if="!localGovs.length && genForm.district_id && !lgLoading"
                class="text-[11px] text-[#9aa0ac] mt-1">
                यस जिल्लामा कुनै स्थानीय तह फेला परेन
              </p>
            </div>
            <div>
              <label class="field-label">वडा संख्या <span class="text-red-400">*</span></label>
              <input v-model="genForm.ward_count" type="number" min="1" max="35" required class="field-input" />
            </div>
            <div>
              <label class="field-label">स्थापना मिति</label>
              <NepaliDatePicker v-model="genForm.established_date" placeholder="स्थापना मिति (बि.सं.)" />
            </div>
            <div>
              <label class="field-label">सम्पर्क इमेल</label>
              <input v-model="genForm.contact_email" type="email" class="field-input" />
            </div>
            <div>
              <label class="field-label">सम्पर्क फोन</label>
              <input v-model="genForm.contact_phone" type="text" class="field-input" />
            </div>
            <div class="sm:col-span-2">
              <label class="field-label">वेबसाइट</label>
              <input v-model="genForm.website" type="url" placeholder="https://" class="field-input" />
            </div>
          </div>

          <div class="flex justify-end">
            <button type="submit" :disabled="genForm.processing || locked"
              class="px-6 py-2.5 bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-60 text-white text-sm font-semibold rounded-md transition-colors flex items-center gap-2">
              <i v-if="genForm.processing" class="fa-solid fa-spinner fa-spin text-xs"></i>
              <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
              सुरक्षित गर्नुहोस्
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- ── Wards Tab ────────────────────────────────────────────────── -->
    <div v-if="activeTab === 'wards'" class="space-y-4" :class="locked ? 'pointer-events-none opacity-60' : ''">

      <!-- Add ward form -->
      <div class="bg-white rounded-md border shadow-sm p-5"
        :class="wardLimitReached ? 'border-red-200' : 'border-[#e6e7e9]'">

        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
            <i class="fa-solid fa-plus text-[#206bc4] text-xs"></i>
            नयाँ वडा थप्नुहोस्
          </h2>
          <div class="flex items-center gap-2">
            <!-- Ward count badge -->
            <span class="text-[12px] px-2.5 py-1 rounded-full font-semibold"
              :class="wardLimitReached
                ? 'bg-red-100 text-red-700 border border-red-200'
                : 'bg-[#206bc4]/8 text-[#206bc4] border border-[#206bc4]/20'">
              {{ props.wards.length }} / {{ props.lg.ward_count }} वडा
            </span>
            <a href="/admin/settings/wards-settlements/template"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 border border-[#e6e7e9] text-[#626976] text-[12px] font-medium rounded hover:bg-gray-100 transition-colors">
              <i class="fa-solid fa-file-arrow-down text-[#206bc4] text-[11px]"></i>
              Template
            </a>
            <button @click="openCombinedImport"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#206bc4] hover:bg-[#1a5ba8] text-white text-[12px] font-medium rounded transition-colors">
              <i class="fa-solid fa-file-import text-[11px]"></i>
              Excel आयात
            </button>
          </div>
        </div>

        <!-- Combined Ward+Settlement import result banner -->
        <div v-if="combinedImportResult" class="mb-4 rounded-md border px-4 py-3 space-y-3 bg-white border-[#e6e7e9]">
          <p class="text-[13px] font-semibold text-[#1d273b] flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-green-500"></i> आयात सम्पन्न भयो
          </p>
          <div class="space-y-2">
            <p class="text-[11px] font-semibold text-[#626976] uppercase tracking-wide">वडा</p>
            <div class="flex flex-wrap gap-2 text-[12px]">
              <span class="flex items-center gap-1.5 text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded">
                <i class="fa-solid fa-plus text-[10px]"></i> {{ combinedImportResult.wards_inserted }} नयाँ
              </span>
              <span class="flex items-center gap-1.5 text-[#206bc4] bg-[#206bc4]/8 border border-[#206bc4]/20 px-2.5 py-1 rounded">
                <i class="fa-solid fa-pen text-[10px]"></i> {{ combinedImportResult.wards_updated }} अपडेट
              </span>
              <span v-if="combinedImportResult.wards_skipped" class="flex items-center gap-1.5 text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded">
                <i class="fa-solid fa-triangle-exclamation text-[10px]"></i> {{ combinedImportResult.wards_skipped }} छोडियो
              </span>
            </div>
            <p class="text-[11px] font-semibold text-[#626976] uppercase tracking-wide">टोल/बस्ती</p>
            <div class="flex flex-wrap gap-2 text-[12px]">
              <span class="flex items-center gap-1.5 text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded">
                <i class="fa-solid fa-plus text-[10px]"></i> {{ combinedImportResult.settl_inserted }} नयाँ
              </span>
              <span class="flex items-center gap-1.5 text-[#206bc4] bg-[#206bc4]/8 border border-[#206bc4]/20 px-2.5 py-1 rounded">
                <i class="fa-solid fa-pen text-[10px]"></i> {{ combinedImportResult.settl_updated }} अपडेट
              </span>
              <span v-if="combinedImportResult.settl_skipped" class="flex items-center gap-1.5 text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded">
                <i class="fa-solid fa-triangle-exclamation text-[10px]"></i> {{ combinedImportResult.settl_skipped }} छोडियो
              </span>
            </div>
          </div>
          <ul v-if="combinedImportResult.errors?.length" class="mt-1 space-y-0.5 max-h-28 overflow-y-auto">
            <li v-for="(e,i) in combinedImportResult.errors" :key="i"
              class="text-[11px] text-red-600 font-mono bg-red-50 px-2 py-0.5 rounded">{{ e }}</li>
          </ul>
        </div>

        <!-- Limit reached notice -->
        <div v-if="wardLimitReached"
          class="flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-md">
          <i class="fa-solid fa-circle-xmark text-red-500 shrink-0 mt-0.5"></i>
          <div>
            <p class="text-[13px] font-semibold text-red-700">थप वडा थप्न मिल्दैन</p>
            <p class="text-[12px] text-red-500 mt-0.5">
              अनुमत वडा संख्या ({{ props.lg.ward_count }}) पूरा भइसकेको छ।
              थप वडाको लागि Super Admin लाई सम्पर्क गर्नुहोस् र वडा संख्या बढाउन अनुरोध गर्नुहोस्।
            </p>
          </div>
        </div>

        <!-- Add form (hidden when limit reached) -->
        <form v-else @submit.prevent="addWard">
          <div class="flex flex-wrap items-end gap-3">
            <div>
              <label class="field-label">वडा नम्बर <span class="text-red-400">*</span></label>
              <input v-model="wardForm.ward_number" type="number" min="1" max="35" required
                class="field-input w-28" />
            </div>
            <div class="flex-1 min-w-[180px]">
              <label class="field-label">वडाध्यक्ष</label>
              <input v-model="wardForm.ward_chairperson" type="text" placeholder="वडाध्यक्षको नाम"
                class="field-input" />
            </div>
            <div class="flex-1 min-w-[140px]">
              <label class="field-label">सम्पर्क फोन</label>
              <input v-model="wardForm.contact_phone" type="text" placeholder="98XXXXXXXX"
                class="field-input" />
            </div>
            <button type="submit" :disabled="wardForm.processing || locked"
              class="px-5 py-2.5 bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-60 text-white text-sm font-semibold rounded-md transition-colors shrink-0">
              <i v-if="wardForm.processing" class="fa-solid fa-spinner fa-spin mr-1 text-xs"></i>
              थप्नुहोस्
            </button>
          </div>
          <p v-if="wardForm.errors.ward_number" class="field-error mt-2">{{ wardForm.errors.ward_number }}</p>
        </form>
      </div>

      <!-- Wards list -->
      <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">

        <!-- Bulk action bar -->
        <div v-if="selectedWards.length" class="flex items-center justify-between px-4 py-2.5 bg-red-50 border-b border-red-200">
          <span class="text-[13px] font-medium text-red-700">
            <i class="fa-solid fa-square-check mr-1.5"></i>
            {{ selectedWards.length }} वडा चयन भयो
          </span>
          <div class="flex items-center gap-2">
            <button @click="selectedWards = []"
              class="text-[12px] text-gray-500 hover:text-gray-700 px-2 py-1 rounded hover:bg-white transition-colors">
              रद्द गर्नुहोस्
            </button>
            <button @click="confirmBulkDeleteWards"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white text-[12px] font-medium rounded transition-colors">
              <i class="fa-solid fa-trash-can text-[10px]"></i>
              चयन मेटाउनुहोस्
            </button>
          </div>
        </div>

        <table class="min-w-full divide-y divide-[#e6e7e9]">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 w-10">
                <input type="checkbox" :checked="wardAllSelected" :indeterminate="wardSomeSelected"
                  @change="toggleAllWards"
                  class="w-4 h-4 rounded border-gray-300 text-[#206bc4] cursor-pointer" />
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">वडा</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">वडाध्यक्ष</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">टोलहरू</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">कार्य</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#e6e7e9]">
            <tr v-if="props.wards.length === 0">
              <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">कुनै वडा छैन</td>
            </tr>
            <template v-for="ward in props.wards" :key="ward.id">
              <tr class="hover:bg-gray-50/50 transition-colors"
                :class="selectedWards.includes(ward.id) ? 'bg-red-50/40' : ''">
                <td class="px-4 py-3">
                  <input type="checkbox" :value="ward.id" v-model="selectedWards"
                    class="w-4 h-4 rounded border-gray-300 text-[#206bc4] cursor-pointer" />
                </td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center justify-center w-8 h-8 rounded bg-[#206bc4]/8 text-[#206bc4] text-sm font-bold">
                    {{ ward.ward_number }}
                  </span>
                </td>
                <td class="px-4 py-3 hidden sm:table-cell text-sm text-gray-700">
                  {{ ward.ward_chairperson ?? '—' }}
                </td>
                <td class="px-4 py-3 hidden md:table-cell text-sm text-gray-500">
                  {{ ward.settlements_count }} टोल
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button @click="startEditWard(ward)"
                      class="p-1.5 text-gray-400 hover:text-[#206bc4] hover:bg-[#206bc4]/8 rounded transition-colors">
                      <i class="fa-solid fa-pen text-xs"></i>
                    </button>
                    <button @click="confirmDeleteWard(ward)"
                      class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition-colors">
                      <i class="fa-solid fa-trash-can text-xs"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <!-- Inline edit row -->
              <tr v-if="editingWardId === ward.id" class="bg-[#206bc4]/5">
                <td colspan="5" class="px-4 py-3">
                  <form @submit.prevent="saveWard(ward)" class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[180px]">
                      <label class="field-label">वडाध्यक्ष</label>
                      <input v-model="editWardForm.ward_chairperson" type="text" class="field-input" />
                    </div>
                    <div class="flex-1 min-w-[140px]">
                      <label class="field-label">फोन</label>
                      <input v-model="editWardForm.contact_phone" type="text" class="field-input" />
                    </div>
                    <div class="flex gap-2">
                      <button type="submit" :disabled="editWardForm.processing || locked"
                        class="px-4 py-2 bg-[#206bc4] text-white text-sm font-medium rounded-md">
                        सुरक्षित
                      </button>
                      <button type="button" @click="editingWardId = null"
                        class="px-4 py-2 border border-[#e6e7e9] text-gray-600 text-sm rounded-md">
                        रद्द
                      </button>
                    </div>
                  </form>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Toles / Settlements Tab ──────────────────────────────────── -->
    <div v-if="activeTab === 'toles'" class="space-y-4" :class="locked ? 'pointer-events-none opacity-60' : ''">

      <!-- Toolbar: ward selector + import buttons -->
      <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[200px]">
          <label class="field-label">वडा छान्नुहोस्</label>
          <select v-model="selectedWardId" class="field-input max-w-xs">
            <option value="">— वडा छान्नुहोस् —</option>
            <option v-for="w in props.wards" :key="w.id" :value="w.id">वडा {{ w.ward_number }}</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <a href="/admin/settings/wards-settlements/template"
            class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-50 border border-[#e6e7e9] text-[#626976] text-[12px] font-medium rounded-md hover:bg-gray-100 transition-colors">
            <i class="fa-solid fa-file-arrow-down text-[#206bc4] text-[11px]"></i>
            Template
          </a>
          <button @click="openCombinedImport"
            class="inline-flex items-center gap-1.5 px-3 py-2 bg-[#206bc4] hover:bg-[#1a5ba8] text-white text-[12px] font-medium rounded-md transition-colors">
            <i class="fa-solid fa-file-import text-[11px]"></i>
            Excel आयात
          </button>
        </div>
      </div>

      <!-- Combined import result (shown in Wards tab; Toles tab redirects there) -->

      <template v-if="selectedWard">
        <!-- Add settlement form -->
        <form @submit.prevent="addSettlement">
          <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
              <i class="fa-solid fa-plus text-[#206bc4] text-xs"></i>
              नयाँ टोल/बस्ती थप्नुहोस् — वडा {{ selectedWard.ward_number }}
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
              <div>
                <label class="field-label">नाम (नेपाली) <span class="text-red-400">*</span></label>
                <input v-model="settlForm.name_np" required type="text" class="field-input" />
              </div>
              <div>
                <label class="field-label">नाम (अंग्रेजी) <span class="text-red-400">*</span></label>
                <input v-model="settlForm.name_en" required type="text" class="field-input" />
              </div>
              <div>
                <label class="field-label">प्रकार <span class="text-red-400">*</span></label>
                <select v-model="settlForm.settlement_type" required class="field-input">
                  <option value="tole">टोल (Tole)</option>
                  <option value="gau">गाउँ (Gau)</option>
                  <option value="chowk">चोक (Chowk)</option>
                  <option value="basti">बस्ती (Basti)</option>
                </select>
              </div>
              <button type="submit" :disabled="settlForm.processing || locked"
                class="px-5 py-2.5 bg-[#206bc4] hover:bg-[#1a5ba8] text-white text-sm font-semibold rounded-md transition-colors self-end">
                <i v-if="settlForm.processing" class="fa-solid fa-spinner fa-spin mr-1 text-xs"></i>
                थप्नुहोस्
              </button>
            </div>
          </div>
        </form>

        <!-- Settlements list -->
        <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">

          <!-- Bulk action bar -->
          <div v-if="selectedSettlements.length" class="flex items-center justify-between px-4 py-2.5 bg-red-50 border-b border-red-200">
            <span class="text-[13px] font-medium text-red-700">
              <i class="fa-solid fa-square-check mr-1.5"></i>
              {{ selectedSettlements.length }} टोल/बस्ती चयन भयो
            </span>
            <div class="flex items-center gap-2">
              <button @click="selectedSettlements = []"
                class="text-[12px] text-gray-500 hover:text-gray-700 px-2 py-1 rounded hover:bg-white transition-colors">
                रद्द गर्नुहोस्
              </button>
              <button @click="confirmBulkDeleteSettlements"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white text-[12px] font-medium rounded transition-colors">
                <i class="fa-solid fa-trash-can text-[10px]"></i>
                चयन मेटाउनुहोस्
              </button>
            </div>
          </div>

          <table class="min-w-full divide-y divide-[#e6e7e9]">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 w-10">
                  <input type="checkbox" :checked="settlAllSelected" :indeterminate="settlSomeSelected"
                    @change="toggleAllSettlements"
                    class="w-4 h-4 rounded border-gray-300 text-[#206bc4] cursor-pointer" />
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">नाम</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">अंग्रेजी</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">प्रकार</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">कार्य</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#e6e7e9]">
              <tr v-if="selectedWard.settlements?.length === 0">
                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">कुनै टोल/बस्ती छैन</td>
              </tr>
              <template v-for="s in selectedWard.settlements" :key="s.id">
                <tr class="hover:bg-gray-50/50 transition-colors"
                  :class="selectedSettlements.includes(s.id) ? 'bg-red-50/40' : ''">
                  <td class="px-4 py-3">
                    <input type="checkbox" :value="s.id" v-model="selectedSettlements"
                      class="w-4 h-4 rounded border-gray-300 text-[#206bc4] cursor-pointer" />
                  </td>
                  <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ s.name_np }}</td>
                  <td class="px-4 py-3 text-sm text-gray-500 hidden sm:table-cell">{{ s.name_en }}</td>
                  <td class="px-4 py-3 hidden md:table-cell">
                    <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs">{{ s.settlement_type }}</span>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <div class="flex items-center justify-end gap-1">
                      <button @click="startEditSettlement(s)"
                        class="p-1.5 text-gray-400 hover:text-[#206bc4] hover:bg-[#206bc4]/8 rounded transition-colors">
                        <i class="fa-solid fa-pen text-xs"></i>
                      </button>
                      <button @click="deleteSettlement(s)"
                        class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition-colors">
                        <i class="fa-solid fa-trash-can text-xs"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <!-- Inline edit row -->
                <tr v-if="editingSettlId === s.id" class="bg-[#206bc4]/5">
                  <td colspan="4" class="px-4 py-3">
                    <form @submit.prevent="saveSettlement(s)" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                      <div>
                        <label class="field-label">नेपाली नाम</label>
                        <input v-model="editSettlForm.name_np" required type="text" class="field-input" />
                      </div>
                      <div>
                        <label class="field-label">अंग्रेजी नाम</label>
                        <input v-model="editSettlForm.name_en" required type="text" class="field-input" />
                      </div>
                      <div>
                        <label class="field-label">प्रकार</label>
                        <select v-model="editSettlForm.settlement_type" class="field-input">
                          <option value="tole">टोल</option>
                          <option value="gau">गाउँ</option>
                          <option value="chowk">चोक</option>
                          <option value="basti">बस्ती</option>
                        </select>
                      </div>
                      <div class="sm:col-span-3 flex gap-2 justify-end">
                        <button type="submit" :disabled="editSettlForm.processing || locked"
                          class="px-4 py-2 bg-[#206bc4] text-white text-sm font-medium rounded-md">सुरक्षित</button>
                        <button type="button" @click="editingSettlId = null"
                          class="px-4 py-2 border border-[#e6e7e9] text-gray-600 text-sm rounded-md">रद्द</button>
                      </div>
                    </form>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </template>

      <p v-else class="text-sm text-gray-400 text-center py-8">
        टोलहरू हेर्न वडा छान्नुहोस्
      </p>
    </div>

    <!-- ── Profile Tab ─────────────────────────────────────────────── -->
    <div v-if="activeTab === 'profile'" :class="locked ? 'pointer-events-none opacity-60' : ''">
      <form @submit.prevent="saveProfile">
        <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5 space-y-4">
          <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2 mb-2">
            <i class="fa-solid fa-file-lines text-[#206bc4] text-xs"></i>
            प्रोफाइल सामग्री — Profile Narrative Content
          </h2>
          <p class="text-xs text-gray-400 -mt-2">यी सामग्री सार्वजनिक प्रोफाइल पेजमा देखाइन्छन्।</p>

          <div v-for="(label, key) in props.profileSections" :key="key">
            <label class="field-label">{{ label }}</label>
            <textarea v-model="profileForm.profile[key]" rows="4"
              class="w-full px-3 py-2.5 rounded-md border border-[#e6e7e9] text-sm focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] resize-none"
              :placeholder="`${label} बारे जानकारी लेख्नुहोस्...`"></textarea>
          </div>

          <div class="flex justify-end">
            <button type="submit" :disabled="profileForm.processing || locked"
              class="px-6 py-2.5 bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-60 text-white text-sm font-semibold rounded-md transition-colors flex items-center gap-2">
              <i v-if="profileForm.processing" class="fa-solid fa-spinner fa-spin text-xs"></i>
              <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
              सुरक्षित गर्नुहोस्
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- ── Map Tab ─────────────────────────────────────────────────── -->
    <div v-if="activeTab === 'map'" :class="locked ? 'pointer-events-none opacity-60' : ''">
      <form @submit.prevent="saveMap">
        <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5 space-y-4">
          <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2 mb-2">
            <i class="fa-solid fa-map text-[#206bc4] text-xs"></i>
            नक्सा कन्फिगरेसन
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="field-label">Center Latitude <span class="text-red-400">*</span></label>
              <input v-model="mapForm.center_lat" type="number" step="any" required class="field-input" placeholder="27.7172" />
            </div>
            <div>
              <label class="field-label">Center Longitude <span class="text-red-400">*</span></label>
              <input v-model="mapForm.center_lng" type="number" step="any" required class="field-input" placeholder="85.3240" />
            </div>
            <div>
              <label class="field-label">Zoom Level <span class="text-red-400">*</span></label>
              <input v-model="mapForm.zoom" type="number" min="5" max="18" required class="field-input" />
            </div>
          </div>

          <!-- Markers -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="field-label mb-0">Markers</label>
              <button type="button" @click="addMarker"
                class="text-xs text-[#206bc4] hover:underline flex items-center gap-1">
                <i class="fa-solid fa-plus text-[10px]"></i> थप्नुहोस्
              </button>
            </div>
            <div v-if="mapForm.markers.length === 0" class="text-xs text-gray-400 py-2">
              कुनै marker छैन।
            </div>
            <div v-for="(m, i) in mapForm.markers" :key="i"
              class="grid grid-cols-2 sm:grid-cols-5 gap-2 items-end mb-2 p-3 bg-gray-50 rounded-md">
              <div class="sm:col-span-2">
                <label class="field-label">नाम</label>
                <input v-model="m.name" required type="text" class="field-input" />
              </div>
              <div>
                <label class="field-label">Lat</label>
                <input v-model="m.lat" required type="number" step="any" class="field-input" />
              </div>
              <div>
                <label class="field-label">Lng</label>
                <input v-model="m.lng" required type="number" step="any" class="field-input" />
              </div>
              <div>
                <label class="field-label">प्रकार</label>
                <select v-model="m.type" class="field-input">
                  <option value="office">Office</option>
                  <option value="school">School</option>
                  <option value="health">Health</option>
                  <option value="ward">Ward</option>
                  <option value="tourism">Tourism</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <button type="button" @click="mapForm.markers.splice(i, 1)"
                class="text-red-400 hover:text-red-600 p-1.5 self-end">
                <i class="fa-solid fa-trash-can text-xs"></i>
              </button>
            </div>
          </div>

          <div class="flex justify-end">
            <button type="submit" :disabled="mapForm.processing || locked"
              class="px-6 py-2.5 bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-60 text-white text-sm font-semibold rounded-md transition-colors flex items-center gap-2">
              <i v-if="mapForm.processing" class="fa-solid fa-spinner fa-spin text-xs"></i>
              <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
              सुरक्षित गर्नुहोस्
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- ── Photo Directory tab ──────────────────────────────────────────── -->
    <div v-if="activeTab === 'photo'" :class="locked ? 'pointer-events-none opacity-60' : ''">
      <form @submit.prevent="savePhotoDir">
        <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-6 space-y-5">
          <div>
            <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2 mb-1">
              <i class="fa-solid fa-camera text-[#206bc4] text-xs"></i>
              फोटो डाइरेक्टरी सेटिङ
            </h3>
            <p class="text-xs text-gray-400 leading-relaxed">
              घरधुरी र संस्थाका फोटोहरू बाह्य भण्डारणमा राखिएको हुन्छ।
              यहाँ आधार URL राख्नुहोस् — फोटो नम्बर जोडेर लिंक बन्छ।<br>
              उदाहरण: <code class="bg-gray-100 px-1 rounded">https://photos.example.com/households</code> → <code class="bg-gray-100 px-1 rounded">.../households/00247.png</code>
            </p>
          </div>

          <!-- Household base URL -->
          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700">
              <i class="fa-solid fa-house text-gray-400 mr-1 text-xs"></i>
              घरधुरी फोटो आधार URL
            </label>
            <input v-model="photoForm.household_base_url" type="url"
              placeholder="https://photos.example.com/households"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4] font-mono" />
            <p v-if="photoForm.errors.household_base_url" class="text-xs text-red-500">{{ photoForm.errors.household_base_url }}</p>
            <p v-if="photoForm.household_base_url" class="text-xs text-gray-400 font-mono">
              → {{ photoForm.household_base_url }}/00247.png
            </p>
          </div>

          <!-- Institution base URL -->
          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-gray-700">
              <i class="fa-solid fa-building text-gray-400 mr-1 text-xs"></i>
              संस्था फोटो आधार URL
            </label>
            <input v-model="photoForm.institution_base_url" type="url"
              placeholder="https://photos.example.com/institutions"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4] font-mono" />
            <p v-if="photoForm.errors.institution_base_url" class="text-xs text-red-500">{{ photoForm.errors.institution_base_url }}</p>
            <p v-if="photoForm.institution_base_url" class="text-xs text-gray-400 font-mono">
              → {{ photoForm.institution_base_url }}/00131.png
            </p>
          </div>

          <!-- Gallery URL -->
          <div>
            <label class="block text-sm font-semibold text-[#1d273b] mb-1.5">
              <i class="fa-solid fa-images text-purple-500 mr-1.5"></i>
              फोटो ग्यालरी आधार URL
            </label>
            <input v-model="photoForm.gallery_url" type="url"
              placeholder="https://photos.example.com/gallery"
              class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4] font-mono" />
            <p v-if="photoForm.errors.gallery_url" class="text-xs text-red-500">{{ photoForm.errors.gallery_url }}</p>
            <p v-if="photoForm.gallery_url" class="text-xs text-gray-400 font-mono mt-1">
              → {{ photoForm.gallery_url }}/001.jpg
            </p>
            <p class="text-xs text-gray-400 mt-1">फोटो ग्यालरी ट्याबमा Photo No सँग जोडिन्छ</p>
          </div>

          <div class="pt-2">
            <button type="submit" :disabled="photoForm.processing || locked"
              class="px-6 py-2.5 bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-60 text-white text-sm font-semibold rounded-md transition-colors flex items-center gap-2">
              <i v-if="photoForm.processing" class="fa-solid fa-spinner fa-spin text-xs"></i>
              <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
              सुरक्षित गर्नुहोस्
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- ── Welcome Message Tab ──────────────────────────────────────── -->
    <div v-if="activeTab === 'welcome'" :class="locked ? 'pointer-events-none opacity-60' : ''">
      <form @submit.prevent="saveWelcome">
        <div class="space-y-5">

          <!-- Banner toggle + headline -->
          <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-6 space-y-5">
            <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
              <i class="fa-solid fa-bullhorn text-[#206bc4] text-xs"></i>
              स्वागत ब्यानर
            </h3>

            <!-- Show/hide toggle -->
            <label class="flex items-center gap-3 cursor-pointer">
              <div class="relative inline-flex items-center">
                <input type="checkbox" v-model="welcomeForm.show_banner" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-[#206bc4]/30 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#206bc4]"></div>
              </div>
              <span class="text-sm font-medium text-gray-700">सार्वजनिक पृष्ठमा स्वागत ब्यानर देखाउने</span>
            </label>

            <!-- Headline -->
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-gray-700">स्वागत शीर्षक <span class="text-gray-400 font-normal">(heading)</span></label>
              <input v-model="welcomeForm.message" type="text" maxlength="200"
                placeholder="हार्दिक स्वागत छ"
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
            </div>

            <!-- Body text -->
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-gray-700">स्वागत सन्देश <span class="text-gray-400 font-normal">(body text)</span></label>
              <textarea v-model="welcomeForm.message_body" rows="4" maxlength="2000"
                placeholder="नगरपालिकाको परिचय, लक्ष्य, उद्देश्य..."
                class="w-full rounded-md border border-[#e6e7e9] px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4] resize-none"></textarea>
              <p class="text-xs text-gray-400 text-right">{{ (welcomeForm.message_body || '').length }} / 2000</p>
            </div>
          </div>

          <!-- Map image upload -->
          <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-6">
            <h3 class="text-sm font-semibold text-gray-800 flex items-center gap-2 mb-4">
              <i class="fa-solid fa-map text-[#206bc4] text-xs"></i>
              स्थान नक्सा चित्र
              <span class="text-xs font-normal text-gray-400">(SVG वा PNG)</span>
            </h3>
            <div class="flex items-start gap-6">
              <!-- Preview -->
              <div class="w-40 h-32 rounded-md border-2 border-dashed border-[#e6e7e9] bg-gray-50 overflow-hidden shrink-0 flex items-center justify-center">
                <img v-if="props.welcomeConfig?.map_image"
                  :src="`/storage/${props.welcomeConfig.map_image}`"
                  class="w-full h-full object-contain p-1" />
                <i v-else class="fa-solid fa-map text-gray-300 text-3xl"></i>
              </div>
              <!-- Upload -->
              <div>
                <p class="text-xs text-gray-500 mb-3 leading-relaxed">
                  नगरपालिकाको वडा नक्शा SVG वा PNG ढाँचामा अपलोड गर्नुहोस्।<br>
                  अधिकतम: 4MB
                </p>
                <input ref="mapImageInputRef" type="file" accept="image/svg+xml,image/png,image/jpeg,image/jpg"
                  class="hidden" @change="onMapImageChange" />
                <div class="flex items-center gap-2">
                  <button type="button" @click="mapImageInputRef?.click()" :disabled="locked"
                    class="flex items-center gap-2 px-4 py-2 bg-[#206bc4]/8 hover:bg-[#206bc4]/15 text-[#206bc4] text-sm font-medium rounded-md transition-colors disabled:opacity-50">
                    <i class="fa-solid fa-upload text-xs"></i>
                    {{ props.welcomeConfig?.map_image ? 'बदल्नुहोस्' : 'अपलोड गर्नुहोस्' }}
                  </button>
                  <button v-if="props.welcomeConfig?.map_image" type="button"
                    @click="deleteMapImage" :disabled="locked"
                    class="flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 text-sm font-medium rounded-md transition-colors disabled:opacity-50">
                    <i class="fa-solid fa-trash text-xs"></i>
                    हटाउनुहोस्
                  </button>
                </div>
                <p v-if="props.welcomeConfig?.map_image" class="text-xs text-gray-400 mt-2">
                  <i class="fa-solid fa-check text-green-500 mr-1"></i>
                  अपलोड भइसकेको छ
                </p>
              </div>
            </div>
          </div>

          <!-- Leadership cards -->
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <template v-for="(role, roleKey) in {
              chairperson:      { label: 'अध्यक्ष', icon: 'fa-solid fa-user-tie', color: 'blue' },
              vice_chairperson: { label: 'उपाध्यक्ष', icon: 'fa-solid fa-user-tie', color: 'indigo' },
              cao:              { label: 'प्रमुख प्रशासकीय अधिकृत', icon: 'fa-solid fa-briefcase', color: 'green' },
            }" :key="roleKey">
              <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5 space-y-4">
                <!-- Photo + title -->
                <div class="flex items-center gap-3">
                  <div class="w-14 h-14 rounded-full border-2 border-dashed border-[#e6e7e9] bg-gray-50 overflow-hidden shrink-0 flex items-center justify-center">
                    <img v-if="leaderPhotoUrl(roleKey)" :src="leaderPhotoUrl(roleKey)"
                      class="w-full h-full object-cover" />
                    <i v-else class="fa-solid fa-user text-gray-300 text-xl"></i>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ role.label }}</p>
                    <button type="button" @click="triggerLeaderPhotoUpload(roleKey)"
                      class="mt-1 text-xs text-[#206bc4] hover:underline">
                      <i class="fa-solid fa-camera mr-1"></i>फोटो बदल्नुहोस्
                    </button>
                    <input :ref="el => leaderPhotoInputs[roleKey] = el" type="file" accept="image/jpeg,image/png,image/jpg"
                      class="hidden" @change="onLeaderPhotoChange(roleKey, $event)" />
                  </div>
                </div>

                <!-- Name -->
                <div class="space-y-1">
                  <label class="block text-xs font-medium text-gray-600">पूरा नाम</label>
                  <input v-model="welcomeForm[roleKey].name" type="text" maxlength="100"
                    placeholder="श्री/श्रीमती..."
                    class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                </div>

                <!-- Designation -->
                <div class="space-y-1">
                  <label class="block text-xs font-medium text-gray-600">पद / ओहदा</label>
                  <input v-model="welcomeForm[roleKey].designation" type="text" maxlength="100"
                    class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                </div>

                <!-- Contact -->
                <div class="space-y-1">
                  <label class="block text-xs font-medium text-gray-600">सम्पर्क नम्बर</label>
                  <input v-model="welcomeForm[roleKey].contact" type="text" maxlength="30"
                    placeholder="०१-XXXXXXX"
                    class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                </div>

                <!-- Email -->
                <div class="space-y-1">
                  <label class="block text-xs font-medium text-gray-600">इमेल</label>
                  <input v-model="welcomeForm[roleKey].email" type="email" maxlength="100"
                    placeholder="name@mun.gov.np"
                    class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]" />
                </div>
              </div>
            </template>
          </div>

          <!-- Save button -->
          <div class="flex justify-end">
            <button type="submit" :disabled="welcomeForm.processing || locked"
              class="px-6 py-2.5 bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-60 text-white text-sm font-semibold rounded-md transition-colors flex items-center gap-2">
              <i v-if="welcomeForm.processing" class="fa-solid fa-spinner fa-spin text-xs"></i>
              <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
              सुरक्षित गर्नुहोस्
            </button>
          </div>

        </div>
      </form>
    </div>

    <!-- ── Representatives Tab ──────────────────────────────────────── -->
    <div v-if="activeTab === 'representatives'" class="space-y-4" :class="locked ? 'pointer-events-none opacity-60' : ''">

      <!-- Header row -->
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-[15px] font-semibold text-[#1d273b]">जनप्रतिनिधि प्रोफायल</h2>
          <p class="text-xs text-gray-400 mt-0.5">स्थानीय सरकारका निर्वाचित जनप्रतिनिधिहरूको विवरण</p>
        </div>
        <button @click="openRepForm(null)"
          class="flex items-center gap-2 px-4 py-2 bg-[#206bc4] hover:bg-[#1a5ba8] text-white text-sm font-semibold rounded-md transition-colors">
          <i class="fa-solid fa-plus text-xs"></i> नयाँ थप्नुहोस्
        </button>
      </div>

      <!-- Empty state -->
      <div v-if="!props.representatives.length"
        class="bg-white border border-dashed border-[#e6e7e9] rounded-md py-12 flex flex-col items-center gap-3 text-gray-400">
        <i class="fa-solid fa-people-group text-3xl"></i>
        <p class="text-sm">कुनै जनप्रतिनिधि विवरण छैन</p>
        <button @click="openRepForm(null)"
          class="text-xs text-[#206bc4] hover:underline">पहिलो जनप्रतिनिधि थप्नुहोस्</button>
      </div>

      <!-- Accordion list -->
      <div v-else class="space-y-2">
        <div v-for="rep in props.representatives" :key="rep.id"
          class="bg-white border border-[#e6e7e9] rounded-md shadow-sm overflow-hidden">

          <!-- Accordion header -->
          <button type="button" @click="toggleRep(rep.id)"
            class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors">
            <!-- Photo thumb -->
            <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center overflow-hidden shrink-0 border border-indigo-200">
              <img v-if="rep.photo" :src="`/storage/${rep.photo}`" class="w-full h-full object-cover" />
              <i v-else class="fa-solid fa-user text-indigo-400 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-[#1d273b] truncate">{{ rep.name }}</p>
              <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5">
                <span class="text-xs text-indigo-600 font-medium">{{ positionLabel(rep.position) }}</span>
                <span v-if="rep.ward" class="text-xs text-gray-400">
                  <i class="fa-solid fa-map-pin text-[9px] mr-0.5"></i>वडा {{ rep.ward.ward_number }}
                </span>
                <span v-if="rep.address_tole" class="text-xs text-gray-400 hidden sm:inline">
                  {{ rep.address_tole.name_np }}
                </span>
                <span v-if="rep.contact" class="text-xs text-gray-500">
                  <i class="fa-solid fa-phone text-[9px] mr-0.5"></i>{{ rep.contact }}
                </span>
                <span v-if="rep.elected_party" class="text-xs text-gray-400 hidden md:inline">
                  <i class="fa-solid fa-flag text-[9px] mr-0.5"></i>{{ rep.elected_party }}
                </span>
              </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <button type="button" @click.stop="openRepForm(rep)"
                class="text-xs text-[#206bc4] hover:underline px-2 py-1 rounded hover:bg-blue-50">
                <i class="fa-solid fa-pen text-[10px] mr-1"></i>सम्पादन
              </button>
              <button type="button" @click.stop="confirmDeleteRep(rep)"
                class="text-xs text-red-500 hover:underline px-2 py-1 rounded hover:bg-red-50">
                <i class="fa-solid fa-trash text-[10px] mr-1"></i>हटाउनुहोस्
              </button>
              <i :class="['fa-solid text-gray-400 text-xs transition-transform', openReps[rep.id] ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
            </div>
          </button>

          <!-- Accordion body — view mode -->
          <div v-if="openReps[rep.id]" class="border-t border-[#e6e7e9] px-5 py-4">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 text-xs">
              <div v-if="rep.contact"><p class="text-gray-400 mb-0.5">सम्पर्क</p><p class="font-medium text-gray-700">{{ rep.contact }}</p></div>
              <div v-if="rep.email"><p class="text-gray-400 mb-0.5">इमेल</p><p class="font-medium text-gray-700">{{ rep.email }}</p></div>
              <div v-if="rep.gender"><p class="text-gray-400 mb-0.5">लिङ्ग</p><p class="font-medium text-gray-700">{{ genderLabel(rep.gender) }}</p></div>
              <div v-if="rep.ethnicity"><p class="text-gray-400 mb-0.5">जातजाति</p><p class="font-medium text-gray-700">{{ optLabel('ethnicity', rep.ethnicity) }}</p></div>
              <div v-if="rep.education"><p class="text-gray-400 mb-0.5">शिक्षा</p><p class="font-medium text-gray-700">{{ optLabel('education_level', rep.education) }}</p></div>
              <div v-if="rep.date_of_birth"><p class="text-gray-400 mb-0.5">जन्म मिति</p><p class="font-medium text-gray-700">{{ String(rep.date_of_birth).slice(0, 10) }}</p></div>
              <div v-if="rep.birth_place"><p class="text-gray-400 mb-0.5">जन्म स्थान</p><p class="font-medium text-gray-700">{{ rep.birth_place }}</p></div>
              <div v-if="rep.elected_party"><p class="text-gray-400 mb-0.5">निर्वाचित दल</p><p class="font-medium text-gray-700">{{ rep.elected_party }}</p></div>
              <div v-if="rep.father_name"><p class="text-gray-400 mb-0.5">बाबुको नाम</p><p class="font-medium text-gray-700">{{ rep.father_name }}</p></div>
              <div v-if="rep.mother_name"><p class="text-gray-400 mb-0.5">आमाको नाम</p><p class="font-medium text-gray-700">{{ rep.mother_name }}</p></div>
              <div v-if="rep.spouse_name"><p class="text-gray-400 mb-0.5">श्रीमान/श्रीमती</p><p class="font-medium text-gray-700">{{ rep.spouse_name }}</p></div>
              <div v-if="rep.address_ward"><p class="text-gray-400 mb-0.5">ठेगाना (वडा)</p><p class="font-medium text-gray-700">वडा {{ rep.address_ward.ward_number }}</p></div>
              <div v-if="rep.address_tole"><p class="text-gray-400 mb-0.5">टोल/बस्ती</p><p class="font-medium text-gray-700">{{ rep.address_tole.name_np }}</p></div>
            </div>
            <div v-if="rep.work_experience" class="mt-3 pt-3 border-t border-[#e6e7e9]">
              <p class="text-xs text-gray-400 mb-1">कार्य अनुभव</p>
              <p class="text-xs text-gray-700 whitespace-pre-wrap">{{ rep.work_experience }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Employees Tab ────────────────────────────────────────────── -->
    <div v-if="activeTab === 'employees'" class="space-y-4" :class="locked ? 'pointer-events-none opacity-60' : ''">

      <!-- Header row -->
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-[15px] font-semibold text-[#1d273b]">कर्मचारी विवरण</h2>
          <p class="text-xs text-[#9aa0ac] mt-0.5">पालिकाका कर्मचारीहरूको विवरण</p>
        </div>
        <button @click="openEmpForm(null)"
          class="inline-flex items-center gap-2 px-3 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-md text-[13px] font-medium transition-colors shadow-sm">
          <i class="fa-solid fa-plus text-[11px]"></i> नयाँ कर्मचारी
        </button>
      </div>

      <!-- Empty state -->
      <div v-if="!props.employees.length"
        class="bg-white border border-dashed border-[#e6e7e9] rounded-md py-12 flex flex-col items-center gap-3 text-gray-400">
        <i class="fa-solid fa-id-badge text-3xl"></i>
        <p class="text-sm">अहिलेसम्म कुनै कर्मचारी थपिएको छैन</p>
        <button @click="openEmpForm(null)"
          class="text-xs text-cyan-600 hover:underline font-medium">+ नयाँ कर्मचारी थप्नुहोस्</button>
      </div>

      <!-- Accordion list -->
      <div v-else class="space-y-2">
        <div v-for="emp in props.employees" :key="emp.id"
          class="bg-white border border-[#e6e7e9] rounded-md shadow-sm overflow-hidden">

          <!-- Accordion header -->
          <button type="button" @click="toggleEmp(emp.id)"
            class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors">
            <div class="w-9 h-9 rounded-full bg-cyan-100 flex items-center justify-center overflow-hidden shrink-0 border border-cyan-200">
              <img v-if="emp.photo" :src="`/storage/${emp.photo}`" class="w-full h-full object-cover" />
              <i v-else class="fa-solid fa-user text-cyan-400 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-[#1d273b] truncate">{{ emp.name }}</p>
              <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5">
                <span v-if="emp.position"   class="text-xs text-cyan-600 font-medium">{{ emp.position }}</span>
                <span v-if="emp.department" class="text-xs text-gray-400"><i class="fa-solid fa-building text-[9px] mr-0.5"></i>{{ emp.department }}</span>
                <span v-if="emp.contact"    class="text-xs text-gray-500"><i class="fa-solid fa-phone text-[9px] mr-0.5"></i>{{ emp.contact }}</span>
              </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <button @click.stop="openEmpForm(emp)"
                class="text-xs text-cyan-600 hover:text-cyan-800 border border-cyan-200 hover:border-cyan-400 rounded px-2 py-1 transition-colors">
                <i class="fa-solid fa-pen text-[10px] mr-1"></i>सम्पादन
              </button>
              <button @click.stop="confirmDeleteEmp(emp)"
                class="text-xs text-red-500 hover:text-red-700 border border-red-200 hover:border-red-400 rounded px-2 py-1 transition-colors">
                <i class="fa-solid fa-trash text-[10px] mr-1"></i>हटाउनुहोस्
              </button>
              <i :class="['fa-solid text-gray-400 text-xs transition-transform', openEmps[emp.id] ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
            </div>
          </button>

          <!-- Accordion body -->
          <div v-if="openEmps[emp.id]" class="border-t border-[#e6e7e9] px-5 py-4">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 text-xs">
              <div v-if="emp.position">       <p class="text-gray-400 mb-0.5">पद</p>                   <p class="font-medium text-gray-700">{{ emp.position }}</p></div>
              <div v-if="emp.department">     <p class="text-gray-400 mb-0.5">शाखा</p>                 <p class="font-medium text-gray-700">{{ emp.department }}</p></div>
              <div v-if="emp.entry_date">     <p class="text-gray-400 mb-0.5">पदबहाल मिति</p>         <p class="font-medium text-gray-700">{{ String(emp.entry_date).slice(0,10) }}</p></div>
              <div v-if="emp.contact">        <p class="text-gray-400 mb-0.5">सम्पर्क</p>             <p class="font-medium text-gray-700">{{ emp.contact }}</p></div>
              <div v-if="emp.email">          <p class="text-gray-400 mb-0.5">इमेल</p>                <p class="font-medium text-gray-700">{{ emp.email }}</p></div>
              <div v-if="emp.gender">         <p class="text-gray-400 mb-0.5">लिङ्ग</p>               <p class="font-medium text-gray-700">{{ genderLabel(emp.gender) }}</p></div>
              <div v-if="emp.ethnicity">      <p class="text-gray-400 mb-0.5">जातजाति</p>             <p class="font-medium text-gray-700">{{ optLabel('ethnicity', emp.ethnicity) }}</p></div>
              <div v-if="emp.education">      <p class="text-gray-400 mb-0.5">शिक्षा</p>               <p class="font-medium text-gray-700">{{ optLabel('education_level', emp.education) }}</p></div>
              <div v-if="emp.date_of_birth">  <p class="text-gray-400 mb-0.5">जन्म मिति</p>           <p class="font-medium text-gray-700">{{ String(emp.date_of_birth).slice(0,10) }}</p></div>
              <div v-if="emp.birth_place">    <p class="text-gray-400 mb-0.5">जन्म स्थान</p>          <p class="font-medium text-gray-700">{{ emp.birth_place }}</p></div>
              <div v-if="emp.permanent_address" class="sm:col-span-2">
                                              <p class="text-gray-400 mb-0.5">स्थायी ठेगाना</p>       <p class="font-medium text-gray-700">{{ emp.permanent_address }}</p></div>
              <div v-if="emp.father_name">    <p class="text-gray-400 mb-0.5">बाबुको नाम</p>          <p class="font-medium text-gray-700">{{ emp.father_name }}</p></div>
              <div v-if="emp.mother_name">    <p class="text-gray-400 mb-0.5">आमाको नाम</p>           <p class="font-medium text-gray-700">{{ emp.mother_name }}</p></div>
              <div v-if="emp.spouse_name">    <p class="text-gray-400 mb-0.5">श्रीमान/श्रीमती</p>    <p class="font-medium text-gray-700">{{ emp.spouse_name }}</p></div>
            </div>
            <!-- Social IDs -->
            <div v-if="emp.facebook || emp.instagram || emp.tiktok || emp.twitter_x"
              class="mt-3 pt-3 border-t border-[#e6e7e9] flex flex-wrap gap-x-5 gap-y-1 text-xs">
              <span v-if="emp.facebook">  <i class="fa-brands fa-facebook text-blue-500 mr-1"></i>{{ emp.facebook }}</span>
              <span v-if="emp.instagram"> <i class="fa-brands fa-instagram text-pink-500 mr-1"></i>{{ emp.instagram }}</span>
              <span v-if="emp.tiktok">    <i class="fa-brands fa-tiktok text-gray-700 mr-1"></i>{{ emp.tiktok }}</span>
              <span v-if="emp.twitter_x"> <i class="fa-brands fa-x-twitter text-gray-700 mr-1"></i>{{ emp.twitter_x }}</span>
            </div>
            <div v-if="emp.work_experience" class="mt-3 pt-3 border-t border-[#e6e7e9]">
              <p class="text-xs text-gray-400 mb-1">कार्य अनुभव</p>
              <p class="text-xs text-gray-700 whitespace-pre-wrap">{{ emp.work_experience }}</p>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ── Photo Gallery Tab ────────────────────────────────────────── -->
    <div v-if="activeTab === 'gallery'" class="space-y-4" :class="locked ? 'pointer-events-none opacity-60' : ''">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-[15px] font-semibold text-[#1d273b]">फोटो ग्यालरी</h2>
          <p class="text-xs text-[#9aa0ac] mt-0.5">पालिकाको फोटो संग्रह</p>
        </div>
        <button @click="openGalleryForm(null)"
          class="inline-flex items-center gap-2 px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-md text-[13px] font-medium transition-colors shadow-sm">
          <i class="fa-solid fa-plus text-[11px]"></i> नयाँ फोटो
        </button>
      </div>

      <!-- Empty state -->
      <div v-if="!props.photos.length"
        class="bg-white border border-dashed border-[#e6e7e9] rounded-md py-12 flex flex-col items-center gap-3 text-gray-400">
        <i class="fa-solid fa-images text-3xl"></i>
        <p class="text-sm">अहिलेसम्म कुनै फोटो थपिएको छैन</p>
        <button @click="openGalleryForm(null)" class="text-xs text-purple-600 hover:underline font-medium">+ फोटो थप्नुहोस्</button>
      </div>

      <!-- Accordion list -->
      <div v-else class="space-y-2">
        <div v-for="ph in props.photos" :key="ph.id"
          class="bg-white border border-[#e6e7e9] rounded-md shadow-sm overflow-hidden">

          <!-- Accordion header -->
          <button type="button" @click="togglePhoto(ph.id)"
            class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors">

            <!-- Thumbnail -->
            <div class="w-14 h-10 rounded overflow-hidden bg-gray-100 border border-gray-200 shrink-0 relative">
              <img v-if="ph.photo_no && props.lg.photo_dir?.gallery_url"
                :src="`${props.lg.photo_dir.gallery_url}/${ph.photo_no}`"
                class="w-full h-full object-cover"
                @error="e => e.target.style.display='none'" />
              <div class="absolute inset-0 flex items-center justify-center text-gray-300">
                <i class="fa-solid fa-image text-sm"></i>
              </div>
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <p class="text-[13.5px] font-semibold text-[#1d273b] truncate">{{ ph.title || '(शीर्षक छैन)' }}</p>
                <span v-if="ph.category"
                  class="text-[10px] bg-purple-100 text-purple-700 font-semibold px-2 py-0.5 rounded-full">
                  {{ ph.category }}
                </span>
              </div>
              <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5">
                <span v-if="ph.photo_no" class="text-xs text-gray-400 font-mono">
                  <i class="fa-solid fa-hashtag text-[9px] mr-0.5"></i>{{ ph.photo_no }}
                </span>
                <span v-if="ph.photo_date" class="text-xs text-gray-400">
                  <i class="fa-solid fa-calendar text-[9px] mr-0.5"></i>{{ String(ph.photo_date).slice(0,10) }}
                </span>
                <span v-if="ph.photo_credit" class="text-xs text-gray-400 hidden sm:inline">
                  <i class="fa-solid fa-camera text-[9px] mr-0.5"></i>{{ ph.photo_credit }}
                </span>
              </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <button @click.stop="openGalleryForm(ph)"
                class="text-xs text-purple-600 hover:text-purple-800 border border-purple-200 hover:border-purple-400 rounded px-2 py-1 transition-colors">
                <i class="fa-solid fa-pen text-[10px] mr-1"></i>सम्पादन
              </button>
              <button @click.stop="confirmDeletePhoto(ph)"
                class="text-xs text-red-500 hover:text-red-700 border border-red-200 hover:border-red-400 rounded px-2 py-1 transition-colors">
                <i class="fa-solid fa-trash text-[10px] mr-1"></i>हटाउनुहोस्
              </button>
              <i :class="['fa-solid text-gray-400 text-xs transition-transform',
                          openPhotos[ph.id] ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
            </div>
          </button>

          <!-- Accordion body -->
          <div v-if="openPhotos[ph.id]" class="border-t border-[#e6e7e9] px-5 py-4">
            <div class="flex gap-5">
              <!-- Larger preview -->
              <div v-if="ph.photo_no && props.lg.photo_dir?.gallery_url"
                class="w-32 h-24 rounded-lg overflow-hidden bg-gray-100 border border-gray-200 shrink-0 relative">
                <img :src="`${props.lg.photo_dir.gallery_url}/${ph.photo_no}`"
                  class="w-full h-full object-cover"
                  @error="e => e.target.style.display='none'" />
                <div class="absolute inset-0 flex items-center justify-center text-gray-300">
                  <i class="fa-solid fa-image text-2xl"></i>
                </div>
              </div>
              <!-- Details grid -->
              <div class="flex-1 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                <div v-if="ph.subtitle">       <p class="text-gray-400 mb-0.5">Caption</p>          <p class="font-medium text-gray-700">{{ ph.subtitle }}</p></div>
                <div v-if="ph.gps_location">   <p class="text-gray-400 mb-0.5">GPS</p>              <p class="font-medium text-gray-700 font-mono">{{ ph.gps_location }}</p></div>
                <div v-if="ph.photo_date">     <p class="text-gray-400 mb-0.5">फोटो मिति</p>        <p class="font-medium text-gray-700">{{ String(ph.photo_date).slice(0,10) }}</p></div>
                <div v-if="ph.photo_credit">   <p class="text-gray-400 mb-0.5">Photo Credit</p>     <p class="font-medium text-gray-700">{{ ph.photo_credit }}</p></div>
                <div v-if="ph.category">       <p class="text-gray-400 mb-0.5">विधा</p>             <p class="font-medium text-gray-700">{{ ph.category }}</p></div>
                <div v-if="ph.photo_no">       <p class="text-gray-400 mb-0.5">फोटो न.</p>          <p class="font-medium text-gray-700 font-mono">{{ ph.photo_no }}</p></div>
                <div v-if="ph.description" class="col-span-2 sm:col-span-3">
                                               <p class="text-gray-400 mb-0.5">विवरण</p>            <p class="font-medium text-gray-700">{{ ph.description }}</p></div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ── Maintenance Tab ───────────────────────────────────────────── -->
    <div v-if="activeTab === 'maintenance'" class="space-y-5">

      <!-- Active warning banner -->
      <div v-if="maintForm.is_active"
        class="flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg">
        <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center shrink-0">
          <i class="fa-solid fa-triangle-exclamation text-red-500 text-sm"></i>
        </div>
        <div>
          <p class="text-[13px] font-bold text-red-700">मर्मत अवस्था सक्रिय छ!</p>
          <p class="text-[11px] text-red-500 mt-0.5">लगइन नगरेका सबै आगन्तुकले मर्मत पृष्ठ देख्छन्। लगइन भएका प्रयोगकर्ताले सामान्य रूपमा हेर्न सक्छन्।</p>
        </div>
      </div>

      <!-- Toggle card -->
      <div class="bg-white rounded-xl border border-[#e6e7e9] shadow-sm p-5">
        <div class="flex items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
              :class="maintForm.is_active ? 'bg-red-100' : 'bg-gray-100'">
              <i class="fa-solid fa-power-off text-base"
                :class="maintForm.is_active ? 'text-red-500' : 'text-gray-400'"></i>
            </div>
            <div>
              <p class="text-sm font-bold text-gray-800">मर्मत अवस्था</p>
              <p class="text-xs text-gray-500 mt-0.5">
                {{ maintForm.is_active ? 'सक्रिय — सार्वजनिक पृष्ठहरू बन्द छन्' : 'निष्क्रिय — सार्वजनिक पृष्ठहरू खुला छन्' }}
              </p>
            </div>
          </div>
          <!-- Toggle switch -->
          <button type="button" @click="maintForm.is_active = !maintForm.is_active"
            class="relative inline-flex h-7 w-14 items-center rounded-full transition-colors duration-300 focus:outline-none shrink-0"
            :class="maintForm.is_active ? 'bg-red-500' : 'bg-gray-300'">
            <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow-md transition-transform duration-300"
              :class="maintForm.is_active ? 'translate-x-8' : 'translate-x-1'"></span>
          </button>
        </div>
      </div>

      <!-- Message + Countdown -->
      <div class="bg-white rounded-xl border border-[#e6e7e9] shadow-sm p-5 space-y-4">
        <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
          <i class="fa-solid fa-message text-orange-400 text-xs"></i>
          सूचना सन्देश
        </h3>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">मुख्य सन्देश</label>
          <textarea v-model="maintForm.message" rows="3" maxlength="500"
            placeholder="जस्तै: हामी हाम्रो वेबसाइटमा सुधार गरिरहेका छौं। छिट्टै फर्किनेछौं।"
            class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-orange-300 focus:border-orange-400 outline-none resize-none"></textarea>
          <p class="text-[11px] text-gray-400 mt-1 text-right">{{ (maintForm.message||'').length }}/500</p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">उप-सन्देश <span class="font-normal text-gray-400">(वैकल्पिक)</span></label>
          <input v-model="maintForm.message_sub" type="text" maxlength="300"
            placeholder="जस्तै: हामी ०७ मई, २०२६ सम्म तयार हुनेछौं।"
            class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-orange-300 focus:border-orange-400 outline-none" />
        </div>

        <!-- Countdown date + time -->
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">
            <i class="fa-solid fa-clock text-orange-400 mr-1 text-[10px]"></i>
            काउन्टडाउन मिति र समय
          </label>
          <div class="grid grid-cols-2 gap-3">
            <input v-model="maintCountdownDate" type="date"
              class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-orange-300 focus:border-orange-400 outline-none" />
            <input v-model="maintCountdownTime" type="time"
              class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-orange-300 focus:border-orange-400 outline-none" />
          </div>
          <p v-if="maintCountdownDate" class="text-[11px] text-orange-600 mt-1">
            <i class="fa-solid fa-circle-info mr-1"></i>
            काउन्टडाउन: {{ maintCountdownDate }} {{ maintCountdownTime || '00:00' }}
          </p>
        </div>
      </div>

      <!-- Background -->
      <div class="bg-white rounded-xl border border-[#e6e7e9] shadow-sm p-5 space-y-4">
        <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
          <i class="fa-solid fa-image text-purple-400 text-xs"></i>
          पृष्ठभूमि
        </h3>

        <!-- Type toggle -->
        <div class="flex gap-2">
          <button v-for="opt in bgTypeOptions" :key="opt.value" type="button"
            @click="maintForm.bg_type = opt.value"
            class="flex-1 py-2 rounded-md text-xs font-semibold border transition-colors"
            :class="maintForm.bg_type === opt.value
              ? 'bg-purple-600 border-purple-600 text-white'
              : 'bg-white border-[#e6e7e9] text-gray-500 hover:bg-gray-50'">
            <i :class="[opt.icon, 'mr-1 text-[10px]']"></i>{{ opt.label }}
          </button>
        </div>

        <!-- Upload -->
        <div v-if="maintForm.bg_type === 'upload'">
          <div class="flex items-start gap-3">
            <div class="w-24 h-16 rounded-md overflow-hidden border border-purple-100 bg-purple-50 flex items-center justify-center shrink-0 relative">
              <img v-if="maintBgPreview || props.maintenanceConfig?.bg_image"
                :src="maintBgPreview || `/storage/${props.maintenanceConfig.bg_image}`"
                class="w-full h-full object-cover" />
              <i v-else class="fa-solid fa-image text-purple-200 text-2xl"></i>
              <button v-if="props.maintenanceConfig?.bg_image && !maintBgPreview" type="button"
                @click="deleteMaintBg"
                class="absolute top-0.5 right-0.5 w-5 h-5 bg-red-500 hover:bg-red-600 text-white rounded-full text-[9px] flex items-center justify-center">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </div>
            <div class="flex-1">
              <input ref="maintBgInput" type="file" accept="image/jpeg,image/png,image/jpg,image/webp"
                class="text-xs text-gray-600 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 w-full"
                @change="onMaintBgChange" />
              <p class="text-[11px] text-gray-400 mt-1">JPG, PNG, WebP — अधिकतम 4MB</p>
              <button v-if="maintBgFile" type="button" @click="uploadMaintBg"
                :disabled="maintBgUploading"
                class="mt-2 px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-md transition-colors disabled:opacity-60">
                <i v-if="maintBgUploading" class="fa-solid fa-spinner fa-spin mr-1 text-[10px]"></i>
                <i v-else class="fa-solid fa-upload mr-1 text-[10px]"></i>
                अपलोड गर्नुहोस्
              </button>
            </div>
          </div>
        </div>

        <!-- URL -->
        <div v-if="maintForm.bg_type === 'url'">
          <label class="block text-xs font-semibold text-gray-600 mb-1">पृष्ठभूमि इमेज URL</label>
          <input v-model="maintForm.bg_url" type="text" maxlength="1000"
            placeholder="https://images.unsplash.com/photo-..."
            class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-purple-300 focus:border-purple-400 outline-none" />
          <div v-if="maintForm.bg_url" class="mt-2 h-24 rounded-md overflow-hidden border border-[#e6e7e9]">
            <img :src="maintForm.bg_url" class="w-full h-full object-cover"
              @error="e => e.target.style.display='none'" />
          </div>
        </div>

        <!-- Gradient (default) -->
        <div v-if="maintForm.bg_type === 'gradient'"
          class="h-16 rounded-md overflow-hidden flex items-center justify-center text-white text-xs font-semibold"
          style="background: linear-gradient(135deg, #0f1923 0%, #1a3a4a 40%, #0d3d3a 100%)">
          <i class="fa-solid fa-palette mr-2"></i>पूर्वनिर्धारित ग्राडियन्ट
        </div>
      </div>

      <!-- Preview + Save -->
      <div class="flex items-center gap-3">
        <a href="/public" target="_blank"
          class="flex items-center gap-2 px-4 py-2.5 rounded-lg border border-[#e6e7e9] text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
          <i class="fa-solid fa-eye text-xs"></i>
          सार्वजनिक दृश्य
        </a>
        <button type="button" @click="saveMaintenance" :disabled="maintSaving"
          class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-lg
                 text-sm font-bold text-white transition-colors disabled:opacity-60"
          :class="maintForm.is_active ? 'bg-red-500 hover:bg-red-600' : 'bg-[#206bc4] hover:bg-[#1a5ba8]'">
          <i v-if="maintSaving" class="fa-solid fa-spinner fa-spin text-xs"></i>
          <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
          {{ maintForm.is_active ? 'मर्मत अवस्था सक्रिय गरेर सुरक्षित गर्नुहोस्' : 'सुरक्षित गर्नुहोस्' }}
        </button>
      </div>

    </div>

    <!-- ── Downloads Tab ────────────────────────────────────────────── -->
    <div v-if="activeTab === 'downloads'" class="space-y-4" :class="locked ? 'pointer-events-none opacity-60' : ''">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-[15px] font-semibold text-[#1d273b]">डाउनलोड लिंकहरू</h2>
          <p class="text-xs text-[#9aa0ac] mt-0.5">सार्वजनिक डाउनलोडका लागि URL लिंकहरू</p>
        </div>
        <button @click="openDownloadForm(null)"
          class="inline-flex items-center gap-2 px-3 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-md text-[13px] font-medium transition-colors shadow-sm">
          <i class="fa-solid fa-plus text-[11px]"></i> नयाँ लिंक
        </button>
      </div>

      <!-- Empty state -->
      <div v-if="!props.downloadLinks.length"
        class="bg-white border border-dashed border-[#e6e7e9] rounded-md py-12 flex flex-col items-center gap-3 text-gray-400">
        <i class="fa-solid fa-link text-3xl"></i>
        <p class="text-sm">अहिलेसम्म कुनै डाउनलोड लिंक थपिएको छैन</p>
        <button @click="openDownloadForm(null)" class="text-xs text-teal-600 hover:underline font-medium">+ लिंक थप्नुहोस्</button>
      </div>

      <!-- List -->
      <div v-else class="space-y-2">
        <div v-for="dl in props.downloadLinks" :key="dl.id"
          class="bg-white border border-[#e6e7e9] rounded-md shadow-sm overflow-hidden">

          <!-- Row -->
          <div class="flex items-start gap-4 px-5 py-4">
            <!-- Thumbnail or icon -->
            <div class="w-16 h-12 rounded-md overflow-hidden bg-teal-50 border border-teal-100 flex items-center justify-center shrink-0 mt-0.5">
              <img v-if="dl.thumbnail_url" :src="dl.thumbnail_url"
                class="w-full h-full object-cover"
                @error="e => e.target.style.display='none'" />
              <i v-else class="fa-solid fa-file-arrow-down text-teal-400 text-lg"></i>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-[13.5px] text-[#1d273b] leading-tight truncate">{{ dl.title }}</p>
              <p v-if="dl.description" class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ dl.description }}</p>
              <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5 mt-1.5">
                <a :href="dl.url" target="_blank" rel="noopener"
                  class="text-xs text-teal-700 hover:underline font-mono truncate max-w-[300px]">
                  <i class="fa-solid fa-arrow-up-right-from-square mr-0.5 text-[9px]"></i>{{ dl.url }}
                </a>
                <span v-if="dl.created_date" class="text-xs text-gray-400">
                  <i class="fa-solid fa-calendar text-[9px] mr-0.5"></i>{{ String(dl.created_date).slice(0,10) }}
                </span>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 shrink-0 mt-0.5">
              <button @click="openDownloadForm(dl)"
                class="text-xs text-teal-600 hover:text-teal-800 border border-teal-200 hover:border-teal-400 rounded px-2 py-1 transition-colors">
                <i class="fa-solid fa-pen text-[10px] mr-1"></i>सम्पादन
              </button>
              <button @click="confirmDeleteDownload(dl)"
                class="text-xs text-red-500 hover:text-red-700 border border-red-200 hover:border-red-400 rounded px-2 py-1 transition-colors">
                <i class="fa-solid fa-trash text-[10px] mr-1"></i>हटाउनुहोस्
              </button>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Ward delete confirm -->
    <Teleport to="body">
      <div v-if="deleteWardTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-md shadow-2xl max-w-sm w-full p-6">
          <div class="w-12 h-12 rounded-md bg-red-100 flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-triangle-exclamation text-red-500 text-lg"></i>
          </div>
          <h3 class="text-base font-bold text-gray-900 text-center mb-1">वडा हटाउने?</h3>
          <p class="text-sm text-gray-500 text-center mb-5">
            वडा <strong>{{ deleteWardTarget.ward_number }}</strong> र यसका सबै टोलहरू हटाइनेछन्।
          </p>
          <div class="flex gap-3">
            <button @click="deleteWardTarget = null"
              class="flex-1 py-2 rounded-md border border-[#e6e7e9] text-sm font-medium text-gray-700 hover:bg-gray-50">
              रद्द गर्नुहोस्
            </button>
            <button @click="doDeleteWard"
              class="flex-1 py-2 rounded-md bg-red-500 hover:bg-red-600 text-white text-sm font-medium">
              हटाउनुहोस्
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Combined Ward + Settlement Import Modal ───────────────────── -->
    <Teleport to="body">
      <div v-if="showCombinedImport" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-md shadow-2xl w-full max-w-md">
          <div class="bg-[#182433] px-5 py-4 rounded-t-md flex items-center justify-between">
            <div>
              <h3 class="text-[14px] font-semibold text-white">वडा र टोल/बस्ती Excel आयात</h3>
              <p class="text-[11px] text-white/50 mt-0.5">Wards &amp; Settlements Bulk Import</p>
            </div>
            <button @click="closeCombinedImport" class="text-white/50 hover:text-white transition-colors">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>
          <div class="px-5 py-4 space-y-4">
            <div class="bg-[#206bc4]/8 border border-[#206bc4]/20 rounded-md px-4 py-3 text-[12px] text-[#206bc4] space-y-1.5">
              <p class="font-semibold flex items-center gap-1.5"><i class="fa-solid fa-circle-info text-[11px]"></i> Excel ढाँचा (२ Sheet)</p>
              <ul class="list-disc pl-4 space-y-0.5">
                <li><strong>Wards</strong> sheet: <code class="bg-[#206bc4]/15 px-1 rounded">ward_number, ward_chairperson, contact_phone</code></li>
                <li><strong>Settlements</strong> sheet: <code class="bg-[#206bc4]/15 px-1 rounded">ward_number, name_np, name_en, settlement_type</code></li>
                <li><code class="bg-[#206bc4]/15 px-1 rounded">settlement_type</code>: tole | gau | chowk | basti</li>
                <li>अवस्थित रेकर्ड भएमा अपडेट, नयाँ भएमा सिर्जना हुन्छ</li>
              </ul>
            </div>
            <a href="/admin/settings/wards-settlements/template"
              class="flex items-center gap-2 w-full px-4 py-2.5 border border-[#e6e7e9] rounded-md text-[13px] text-[#626976] hover:bg-gray-50 transition-colors">
              <i class="fa-solid fa-file-arrow-down text-[#206bc4] text-[13px]"></i>
              <span>Template डाउनलोड <span class="text-[11px] text-[#9aa0ac]">(Reference sheet सहित)</span></span>
            </a>
            <div>
              <label class="block text-[12px] font-semibold text-[#626976] uppercase tracking-wide mb-1.5">
                Excel फाइल <span class="text-red-400">*</span>
              </label>
              <input ref="combinedFileInput" type="file" accept=".xlsx,.xls"
                @change="onCombinedFileChange"
                class="w-full text-[13px] text-[#626976] file:mr-3 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-[#206bc4] file:text-white file:text-[12px] file:font-medium file:cursor-pointer border border-[#e6e7e9] rounded-md px-2 py-1.5"
                :class="combinedImportForm.errors.file ? 'border-red-400' : ''" />
              <p v-if="combinedImportForm.errors.file" class="text-[11px] text-red-500 mt-1">{{ combinedImportForm.errors.file }}</p>
            </div>
          </div>
          <div class="px-5 py-3 bg-gray-50 border-t border-[#e6e7e9] rounded-b-md flex items-center justify-end gap-2">
            <button @click="closeCombinedImport"
              class="px-4 py-2 text-[13px] font-medium text-[#626976] bg-white border border-[#e6e7e9] rounded hover:bg-gray-50 transition-colors">
              रद्द गर्नुहोस्
            </button>
            <button @click="submitCombinedImport" :disabled="!combinedImportForm.file || combinedImportForm.processing"
              class="inline-flex items-center gap-2 px-4 py-2 bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-50 disabled:cursor-not-allowed text-white text-[13px] font-medium rounded transition-colors">
              <i :class="combinedImportForm.processing ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-file-import'" class="text-[11px]"></i>
              {{ combinedImportForm.processing ? 'आयात हुँदैछ...' : 'आयात गर्नुहोस्' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Bulk Delete Wards Confirm Modal ──────────────────────────── -->
    <Teleport to="body">
      <div v-if="showBulkDeleteWardsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-md shadow-2xl max-w-sm w-full p-6">
          <div class="w-12 h-12 rounded-md bg-red-100 flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-triangle-exclamation text-red-500 text-lg"></i>
          </div>
          <h3 class="text-base font-bold text-gray-900 text-center mb-1">{{ selectedWards.length }} वडा मेटाउने?</h3>
          <p class="text-sm text-gray-500 text-center mb-2">
            चयन गरिएका वडाहरू स्थायी रूपमा मेटाइनेछन्।
          </p>
          <p class="text-xs text-amber-600 text-center bg-amber-50 rounded px-3 py-2 mb-5">
            ⚠ घरधुरी वा संस्था भएका वडाहरू मेटाइने छैनन्।
          </p>
          <div class="flex gap-3">
            <button @click="showBulkDeleteWardsModal = false"
              class="flex-1 py-2 rounded-md border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50">
              रद्द गर्नुहोस्
            </button>
            <button @click="doBulkDeleteWards"
              class="flex-1 py-2 rounded-md bg-red-500 hover:bg-red-600 text-white text-sm font-medium">
              मेटाउनुहोस्
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Bulk Delete Settlements Confirm Modal ─────────────────────── -->
    <Teleport to="body">
      <div v-if="showBulkDeleteSettlModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-md shadow-2xl max-w-sm w-full p-6">
          <div class="w-12 h-12 rounded-md bg-red-100 flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-triangle-exclamation text-red-500 text-lg"></i>
          </div>
          <h3 class="text-base font-bold text-gray-900 text-center mb-1">{{ selectedSettlements.length }} टोल/बस्ती मेटाउने?</h3>
          <p class="text-sm text-gray-500 text-center mb-5">
            चयन गरिएका टोल/बस्तीहरू स्थायी रूपमा मेटाइनेछन्।
          </p>
          <div class="flex gap-3">
            <button @click="showBulkDeleteSettlModal = false"
              class="flex-1 py-2 rounded-md border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50">
              रद्द गर्नुहोस्
            </button>
            <button @click="doBulkDeleteSettlements"
              class="flex-1 py-2 rounded-md bg-red-500 hover:bg-red-600 text-white text-sm font-medium">
              मेटाउनुहोस्
            </button>
          </div>
        </div>
      </div>
    </Teleport>


  </div>

  <!-- ── Employee Create/Edit Modal ──────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="empModal.open"
      class="fixed inset-0 z-50 flex items-start justify-center p-4 bg-black/50 overflow-y-auto"
      @click.self="empModal.open = false">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl my-6">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#e6e7e9] bg-cyan-50 rounded-t-xl">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-cyan-100 flex items-center justify-center">
              <i class="fa-solid fa-id-badge text-cyan-600 text-sm"></i>
            </div>
            <h3 class="text-[15px] font-semibold text-cyan-900">
              {{ empModal.isEdit ? 'कर्मचारी विवरण सम्पादन' : 'नयाँ कर्मचारी थप्नुहोस्' }}
            </h3>
          </div>
          <button @click="empModal.open = false" class="text-gray-400 hover:text-gray-600 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
          </button>
        </div>

        <form @submit.prevent="submitEmpForm" class="px-6 py-5 space-y-5">

          <!-- ── Primary fields ── -->
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-cyan-600 mb-3">
              <i class="fa-solid fa-circle-user mr-1"></i> प्राथमिक विवरण
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

              <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">नाम <span class="text-red-500">*</span></label>
                <input v-model="empForm.name" type="text" maxlength="150" required placeholder="पूरा नाम"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 focus:border-cyan-400 outline-none" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">पद</label>
                <input v-model="empForm.position" type="text" maxlength="150" placeholder="पद/जागिर"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 focus:border-cyan-400 outline-none" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">शाखा</label>
                <input v-model="empForm.department" type="text" maxlength="150" placeholder="शाखा/विभाग"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 focus:border-cyan-400 outline-none" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">पालिकामा पदबहाल मिति</label>
                <NepaliDatePicker v-model="empForm.entry_date" placeholder="YYYY-MM-DD"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 focus:border-cyan-400 outline-none" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">जन्म मिति</label>
                <NepaliDatePicker v-model="empForm.date_of_birth" placeholder="YYYY-MM-DD"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 focus:border-cyan-400 outline-none" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">जन्म स्थान</label>
                <input v-model="empForm.birth_place" type="text" maxlength="150" placeholder="जन्म स्थान"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 focus:border-cyan-400 outline-none" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">लिङ्ग</label>
                <select v-model="empForm.gender"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 focus:border-cyan-400 outline-none">
                  <option value="">— छान्नुहोस् —</option>
                  <option value="male">पुरुष</option>
                  <option value="female">महिला</option>
                  <option value="other">अन्य</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">जातजाति</label>
                <select v-model="empForm.ethnicity"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 focus:border-cyan-400 outline-none">
                  <option value="">— छान्नुहोस् —</option>
                  <option v-for="o in (props.surveyOptsForRep?.ethnicity ?? [])" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">शैक्षिक योग्यता</label>
                <select v-model="empForm.education"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 focus:border-cyan-400 outline-none">
                  <option value="">— छान्नुहोस् —</option>
                  <option v-for="o in (props.surveyOptsForRep?.education_level ?? [])" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
                </select>
              </div>

              <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">स्थायी ठेगाना</label>
                <input v-model="empForm.permanent_address" type="text" maxlength="255" placeholder="स्थायी ठेगाना"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 focus:border-cyan-400 outline-none" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">सम्पर्क नम्बर</label>
                <input v-model="empForm.contact" type="text" maxlength="30" placeholder="फोन नम्बर"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 focus:border-cyan-400 outline-none" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">इमेल</label>
                <input v-model="empForm.email" type="email" maxlength="100" placeholder="email@example.com"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 focus:border-cyan-400 outline-none" />
              </div>

              <!-- Photo -->
              <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">फोटो</label>
                <div class="flex items-center gap-4">
                  <div class="w-14 h-14 rounded-full overflow-hidden bg-cyan-50 border border-cyan-200 flex items-center justify-center shrink-0">
                    <img v-if="empPhotoPreview" :src="empPhotoPreview" class="w-full h-full object-cover" />
                    <img v-else-if="empModal.existingPhoto" :src="`/storage/${empModal.existingPhoto}`" class="w-full h-full object-cover" />
                    <i v-else class="fa-solid fa-user text-cyan-300 text-xl"></i>
                  </div>
                  <input ref="empPhotoInput" type="file" accept="image/*" @change="onEmpPhotoChange"
                    class="text-xs text-gray-500 file:mr-2 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-cyan-600 file:text-white file:text-xs file:font-medium file:cursor-pointer" />
                </div>
              </div>
            </div>
          </div>

          <!-- ── Social IDs ── -->
          <div class="border-t border-[#e6e7e9] pt-4">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">
              <i class="fa-solid fa-share-nodes mr-1"></i> सामाजिक सञ्जाल
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1"><i class="fa-brands fa-facebook text-blue-500 mr-1"></i>Facebook</label>
                <input v-model="empForm.facebook" type="text" maxlength="255" placeholder="Username वा URL"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1"><i class="fa-brands fa-instagram text-pink-500 mr-1"></i>Instagram</label>
                <input v-model="empForm.instagram" type="text" maxlength="255" placeholder="Username"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1"><i class="fa-brands fa-tiktok text-gray-700 mr-1"></i>TikTok</label>
                <input v-model="empForm.tiktok" type="text" maxlength="255" placeholder="Username"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1"><i class="fa-brands fa-x-twitter text-gray-700 mr-1"></i>X (Twitter)</label>
                <input v-model="empForm.twitter_x" type="text" maxlength="255" placeholder="Username"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 outline-none" />
              </div>
            </div>
          </div>

          <!-- ── Secondary fields ── -->
          <div class="border-t border-[#e6e7e9] pt-4">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">
              <i class="fa-solid fa-circle-info mr-1"></i> थप विवरण
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">बाबुको नाम</label>
                <input v-model="empForm.father_name" type="text" maxlength="150"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">आमाको नाम</label>
                <input v-model="empForm.mother_name" type="text" maxlength="150"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">श्रीमान/श्रीमती</label>
                <input v-model="empForm.spouse_name" type="text" maxlength="150"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 outline-none" />
              </div>
              <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">कार्य अनुभव</label>
                <textarea v-model="empForm.work_experience" rows="3" maxlength="2000"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-300 outline-none resize-none"></textarea>
              </div>
            </div>
          </div>

          <!-- Footer buttons -->
          <div class="flex justify-end gap-3 border-t border-[#e6e7e9] pt-4">
            <button type="button" @click="empModal.open = false"
              class="px-4 py-2 rounded-md border border-[#e6e7e9] text-sm font-medium text-gray-700 hover:bg-gray-50">
              रद्द गर्नुहोस्
            </button>
            <button type="submit" :disabled="empSaving"
              class="px-5 py-2 rounded-md bg-cyan-600 hover:bg-cyan-700 disabled:opacity-60 text-white text-sm font-medium transition-colors">
              <i v-if="empSaving" class="fa-solid fa-spinner fa-spin mr-1.5 text-xs"></i>
              {{ empModal.isEdit ? 'अद्यावधिक गर्नुहोस्' : 'सुरक्षित गर्नुहोस्' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>

  <!-- ── Employee delete confirm ────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="deleteEmpTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div class="bg-white rounded-xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-triangle-exclamation text-red-500 text-lg"></i>
        </div>
        <h3 class="text-base font-bold text-gray-900 text-center mb-1">कर्मचारी हटाउने?</h3>
        <p class="text-sm text-gray-500 text-center mb-5">
          <strong>{{ deleteEmpTarget.name }}</strong> को विवरण स्थायी रूपमा मेटाइनेछ।
        </p>
        <div class="flex gap-3">
          <button @click="deleteEmpTarget = null"
            class="flex-1 py-2 rounded-md border border-[#e6e7e9] text-sm font-medium text-gray-700 hover:bg-gray-50">
            रद्द गर्नुहोस्
          </button>
          <button @click="doDeleteEmp"
            class="flex-1 py-2 rounded-md bg-red-500 hover:bg-red-600 text-white text-sm font-medium">
            मेटाउनुहोस्
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- ── Photo Gallery Create/Edit Modal ─────────────────────────────── -->
  <Teleport to="body">
    <div v-if="galleryModal.open"
      class="fixed inset-0 z-50 flex items-start justify-center p-4 bg-black/50 overflow-y-auto"
      @click.self="galleryModal.open = false">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl my-6">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#e6e7e9] bg-purple-50 rounded-t-xl">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
              <i class="fa-solid fa-images text-purple-600 text-sm"></i>
            </div>
            <h3 class="text-[15px] font-semibold text-purple-900">
              {{ galleryModal.isEdit ? 'फोटो विवरण सम्पादन' : 'नयाँ फोटो थप्नुहोस्' }}
            </h3>
          </div>
          <button @click="galleryModal.open = false" class="text-gray-400 hover:text-gray-600">
            <i class="fa-solid fa-xmark text-lg"></i>
          </button>
        </div>

        <form @submit.prevent="submitGalleryForm" class="px-6 py-5 space-y-4">

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <!-- फोटो न. — mandatory -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-gray-600 mb-1">
                फोटो न. <span class="text-red-500">*</span>
                <span class="font-normal text-gray-400 ml-1">(संख्या मात्र, Extension छान्नुहोस्)</span>
              </label>
              <div class="flex gap-2">
                <input v-model="galleryForm.photo_num" type="text" required
                  inputmode="numeric" maxlength="50"
                  placeholder="जस्तै: 001, 042, 1234"
                  class="flex-1 rounded-md border border-[#e6e7e9] px-3 py-2 text-sm font-mono
                         focus:ring-2 focus:ring-purple-300 focus:border-purple-400 outline-none"
                  :class="galleryForm.photo_num && !/^\d+$/.test(galleryForm.photo_num) ? 'border-red-400' : ''" />
                <select v-model="galleryForm.photo_ext"
                  class="w-24 rounded-md border border-[#e6e7e9] px-2 py-2 text-sm font-mono
                         focus:ring-2 focus:ring-purple-300 focus:border-purple-400 outline-none bg-white">
                  <option value="jpg">.jpg</option>
                  <option value="jpeg">.jpeg</option>
                  <option value="png">.png</option>
                </select>
              </div>
              <p v-if="galleryForm.photo_num && !/^\d+$/.test(galleryForm.photo_num)"
                class="text-xs text-red-500 mt-1">केवल संख्या मात्र प्रविष्ट गर्नुहोस्</p>
              <p v-if="galleryForm.photo_num && /^\d+$/.test(galleryForm.photo_num) && props.lg.photo_dir?.gallery_url"
                class="text-xs text-purple-600 font-mono mt-1">
                <i class="fa-solid fa-link mr-1"></i>{{ props.lg.photo_dir.gallery_url }}/{{ galleryForm.photo_num }}.{{ galleryForm.photo_ext }}
              </p>
            </div>

            <!-- Category -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-gray-600 mb-1">फोटोको विधा (Category)</label>
              <select v-model="galleryForm.category"
                class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 focus:border-purple-400 outline-none">
                <option value="">— छान्नुहोस् —</option>
                <option v-for="cat in PHOTO_CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
              </select>
            </div>

            <!-- Title -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-gray-600 mb-1">फोटो शीर्षक (Title)</label>
              <input v-model="galleryForm.title" type="text" maxlength="255" placeholder="फोटोको शीर्षक"
                class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 focus:border-purple-400 outline-none" />
            </div>

            <!-- Subtitle/Caption -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-gray-600 mb-1">Caption / Sub Title</label>
              <input v-model="galleryForm.subtitle" type="text" maxlength="255" placeholder="Caption"
                class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 focus:border-purple-400 outline-none" />
            </div>

            <!-- GPS -->
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">GPS Location</label>
              <input v-model="galleryForm.gps_location" type="text" maxlength="150" placeholder="27.7172° N, 85.3240° E"
                class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none font-mono" />
            </div>

            <!-- Date -->
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">फोटो मिति</label>
              <NepaliDatePicker v-model="galleryForm.photo_date" placeholder="YYYY-MM-DD"
                class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none" />
            </div>

            <!-- Photo Credit -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-gray-600 mb-1">फोटो क्रेडिट (Photo Credit)</label>
              <input v-model="galleryForm.photo_credit" type="text" maxlength="150" placeholder="फोटोग्राफरको नाम"
                class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none" />
            </div>

            <!-- Description -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-gray-600 mb-1">छोटो विवरण</label>
              <textarea v-model="galleryForm.description" rows="3" maxlength="2000"
                class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none resize-none"></textarea>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex justify-end gap-3 border-t border-[#e6e7e9] pt-4">
            <button type="button" @click="galleryModal.open = false"
              class="px-4 py-2 rounded-md border border-[#e6e7e9] text-sm font-medium text-gray-700 hover:bg-gray-50">
              रद्द गर्नुहोस्
            </button>
            <button type="submit" :disabled="gallerySaving"
              class="px-5 py-2 rounded-md bg-purple-600 hover:bg-purple-700 disabled:opacity-60 text-white text-sm font-medium">
              <i v-if="gallerySaving" class="fa-solid fa-spinner fa-spin mr-1.5 text-xs"></i>
              {{ galleryModal.isEdit ? 'अद्यावधिक गर्नुहोस्' : 'सुरक्षित गर्नुहोस्' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>

  <!-- ── Photo Gallery delete confirm ──────────────────────────────── -->
  <Teleport to="body">
    <div v-if="deletePhotoTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div class="bg-white rounded-xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-triangle-exclamation text-red-500 text-lg"></i>
        </div>
        <h3 class="text-base font-bold text-gray-900 text-center mb-1">फोटो हटाउने?</h3>
        <p class="text-sm text-gray-500 text-center mb-5">
          <strong>{{ deletePhotoTarget.title || '#' + deletePhotoTarget.photo_no }}</strong> स्थायी रूपमा मेटाइनेछ।
        </p>
        <div class="flex gap-3">
          <button @click="deletePhotoTarget = null"
            class="flex-1 py-2 rounded-md border border-[#e6e7e9] text-sm font-medium text-gray-700 hover:bg-gray-50">
            रद्द गर्नुहोस्
          </button>
          <button @click="doDeletePhoto"
            class="flex-1 py-2 rounded-md bg-red-500 hover:bg-red-600 text-white text-sm font-medium">
            मेटाउनुहोस्
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- ── Representative Create/Edit Modal ─────────────────────────────── -->
  <Teleport to="body">
    <div v-if="repModal.open"
      class="fixed inset-0 z-50 flex items-start justify-center p-4 bg-black/50 overflow-y-auto"
      @click.self="repModal.open = false">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl my-6">
        <!-- Modal header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#e6e7e9] bg-indigo-50 rounded-t-xl">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
              <i class="fa-solid fa-person-chalkboard text-indigo-600 text-sm"></i>
            </div>
            <h3 class="text-[15px] font-semibold text-indigo-900">
              {{ repModal.isEdit ? 'जनप्रतिनिधि सम्पादन' : 'नयाँ जनप्रतिनिधि थप्नुहोस्' }}
            </h3>
          </div>
          <button @click="repModal.open = false" class="text-gray-400 hover:text-gray-600 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
          </button>
        </div>

        <form @submit.prevent="submitRepForm" class="px-6 py-5 space-y-5">

          <!-- ── Primary fields ── -->
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-indigo-500 mb-3">
              <i class="fa-solid fa-circle-user mr-1"></i> प्राथमिक विवरण
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

              <!-- Name -->
              <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">नाम <span class="text-red-500">*</span></label>
                <input v-model="repForm.name" type="text" maxlength="150" required placeholder="पूरा नाम"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none" />
              </div>

              <!-- Position -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">पद <span class="text-red-500">*</span></label>
                <select v-model="repForm.position" required
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                  <option value="">— छान्नुहोस् —</option>
                  <option v-for="p in POSITIONS" :key="p.value" :value="p.value">{{ p.label }}</option>
                </select>
              </div>

              <!-- Represented Ward -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">प्रतिनिधि वार्ड</label>
                <select v-model="repForm.ward_id"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                  <option value="">— छान्नुहोस् —</option>
                  <option v-for="w in wardList" :key="w.id" :value="w.id">वडा {{ w.ward_number }}</option>
                </select>
              </div>

              <!-- Gender -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">लिङ्ग</label>
                <select v-model="repForm.gender"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                  <option value="">— छान्नुहोस् —</option>
                  <option value="male">पुरुष</option>
                  <option value="female">महिला</option>
                  <option value="other">अन्य</option>
                </select>
              </div>

              <!-- Ethnicity -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">जातजाति</label>
                <select v-model="repForm.ethnicity"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                  <option value="">— छान्नुहोस् —</option>
                  <option v-for="o in (props.surveyOptsForRep?.ethnicity ?? [])" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
                </select>
              </div>

              <!-- Education -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">शैक्षिक योग्यता</label>
                <select v-model="repForm.education"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                  <option value="">— छान्नुहोस् —</option>
                  <option v-for="o in (props.surveyOptsForRep?.education_level ?? [])" :key="o.value" :value="o.value">{{ o.label_ne }}</option>
                </select>
              </div>

              <!-- DOB (Bikram Sambat) -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">जन्म मिति (बि.सं.)</label>
                <NepaliDatePicker v-model="repForm.date_of_birth" placeholder="जन्म मिति छान्नुहोस्" />
              </div>

              <!-- Birth Place -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">जन्म स्थान</label>
                <input v-model="repForm.birth_place" type="text" maxlength="150" placeholder="जन्म स्थान"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none" />
              </div>

              <!-- Contact -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">सम्पर्क नं.</label>
                <input v-model="repForm.contact" type="text" maxlength="30" placeholder="९८xxxxxxxx"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none" />
              </div>

              <!-- Email -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">इमेल</label>
                <input v-model="repForm.email" type="email" maxlength="100" placeholder="example@email.com"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none" />
              </div>

              <!-- Address Ward -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">ठेगाना — वडा</label>
                <select v-model="repForm.address_ward_id" @change="repForm.address_tole_id = ''"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                  <option value="">— वडा छान्नुहोस् —</option>
                  <option v-for="w in wardList" :key="w.id" :value="w.id">वडा {{ w.ward_number }}</option>
                </select>
              </div>

              <!-- Address Tole (filtered by selected ward) -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">ठेगाना — टोल/बस्ती</label>
                <select v-model="repForm.address_tole_id"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                  <option value="">— टोल छान्नुहोस् —</option>
                  <option v-for="s in filteredRepToles" :key="s.id" :value="s.id">{{ s.name_np }}</option>
                </select>
              </div>

              <!-- Photo -->
              <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">फोटो</label>
                <div class="flex items-center gap-3">
                  <div v-if="repModal.existingPhoto || repPhotoPreview"
                    class="w-14 h-14 rounded-full border border-indigo-200 overflow-hidden bg-indigo-50 shrink-0">
                    <img :src="repPhotoPreview || `/storage/${repModal.existingPhoto}`" class="w-full h-full object-cover" />
                  </div>
                  <input ref="repPhotoInput" type="file" accept="image/jpeg,image/png,image/jpg"
                    class="text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    @change="onRepPhotoChange" />
                </div>
              </div>

            </div>
          </div>

          <!-- ── Secondary fields ── -->
          <div class="border-t border-[#e6e7e9] pt-4">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">
              <i class="fa-solid fa-circle-info mr-1"></i> थप विवरण
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">बाबुको नाम</label>
                <input v-model="repForm.father_name" type="text" maxlength="150" placeholder="बाबुको नाम"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">आमाको नाम</label>
                <input v-model="repForm.mother_name" type="text" maxlength="150" placeholder="आमाको नाम"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">श्रीमान/श्रीमती</label>
                <input v-model="repForm.spouse_name" type="text" maxlength="150" placeholder="श्रीमान/श्रीमतीको नाम"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">निर्वाचित दल</label>
                <input v-model="repForm.elected_party" type="text" maxlength="150" placeholder="राजनैतिक दल"
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none" />
              </div>

              <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">कार्य अनुभव</label>
                <textarea v-model="repForm.work_experience" rows="3" maxlength="2000"
                  placeholder="अनुभव र योगदानको संक्षिप्त विवरण..."
                  class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none resize-none"></textarea>
              </div>

            </div>
          </div>

          <!-- Form actions -->
          <div class="flex gap-3 pt-2 border-t border-[#e6e7e9]">
            <button type="button" @click="repModal.open = false"
              class="flex-1 py-2.5 rounded-md border border-[#e6e7e9] text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
              रद्द गर्नुहोस्
            </button>
            <button type="submit" :disabled="repSaving"
              class="flex-1 py-2.5 rounded-md bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-semibold transition-colors flex items-center justify-center gap-2">
              <i v-if="repSaving" class="fa-solid fa-spinner fa-spin text-xs"></i>
              <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
              सुरक्षित गर्नुहोस्
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>

  <!-- ── Download Link Modal ──────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="downloadModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 overflow-y-auto"
      @click.self="downloadModal.open = false">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg my-4">
        <!-- Header -->
        <div class="bg-teal-700 px-6 py-4 rounded-t-xl flex items-center justify-between">
          <div>
            <h3 class="text-[14px] font-semibold text-white">
              {{ downloadModal.isEdit ? 'डाउनलोड लिंक सम्पादन' : 'नयाँ डाउनलोड लिंक' }}
            </h3>
            <p class="text-[11px] text-white/50 mt-0.5">Download Link</p>
          </div>
          <button @click="downloadModal.open = false" class="text-white/60 hover:text-white transition-colors">
            <i class="fa-solid fa-xmark text-base"></i>
          </button>
        </div>

        <!-- Body -->
        <form @submit.prevent="submitDownloadForm" class="px-6 py-5 space-y-4">

          <!-- Title -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">
              शीर्षक <span class="text-red-500">*</span>
            </label>
            <input v-model="downloadForm.title" type="text" required maxlength="255"
              placeholder="जस्तै: वार्षिक बजेट २०८१/८२"
              class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-teal-300 focus:border-teal-400 outline-none" />
          </div>

          <!-- URL -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">
              URL / लिंक <span class="text-red-500">*</span>
            </label>
            <input v-model="downloadForm.url" type="text" required maxlength="1000"
              placeholder="https://example.com/document.pdf"
              class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-teal-300 focus:border-teal-400 outline-none" />
          </div>

          <!-- Description -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">विवरण</label>
            <textarea v-model="downloadForm.description" rows="3" maxlength="2000"
              placeholder="फाइलको संक्षिप्त विवरण..."
              class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-teal-300 focus:border-teal-400 outline-none resize-none"></textarea>
          </div>

          <!-- Thumbnail -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">थम्बनेल / कभर फोटो</label>
            <div class="flex items-start gap-3">
              <div v-if="downloadThumbPreview || downloadModal.existingThumb"
                class="w-20 h-14 rounded-md border border-teal-200 overflow-hidden bg-teal-50 shrink-0">
                <img :src="downloadThumbPreview || downloadModal.existingThumb"
                  class="w-full h-full object-cover" />
              </div>
              <div v-else
                class="w-20 h-14 rounded-md border border-dashed border-[#e6e7e9] bg-gray-50 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-image text-gray-300 text-xl"></i>
              </div>
              <div class="flex-1">
                <input ref="downloadThumbInput" type="file" accept="image/jpeg,image/png,image/jpg,image/webp"
                  class="text-xs text-gray-600 file:mr-2 file:py-1 file:px-2.5 file:rounded file:border-0 file:text-xs file:font-medium file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 w-full"
                  @change="onDownloadThumbChange" />
                <p class="text-[11px] text-gray-400 mt-1">JPG, PNG, WebP — अधिकतम 2MB</p>
              </div>
            </div>
          </div>

          <!-- Date + Sort order -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">सिर्जना मिति</label>
              <NepaliDatePicker v-model="downloadForm.created_date" placeholder="YYYY-MM-DD"
                class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-teal-300 focus:border-teal-400 outline-none" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">क्रम</label>
              <input v-model.number="downloadForm.sort_order" type="number" min="0"
                class="w-full rounded-md border border-[#e6e7e9] px-3 py-2 text-sm focus:ring-2 focus:ring-teal-300 focus:border-teal-400 outline-none" />
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 pt-1 border-t border-[#e6e7e9]">
            <button type="button" @click="downloadModal.open = false"
              class="flex-1 py-2.5 rounded-md border border-[#e6e7e9] text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
              रद्द गर्नुहोस्
            </button>
            <button type="submit" :disabled="downloadSaving"
              class="flex-1 py-2.5 rounded-md bg-teal-600 hover:bg-teal-700 disabled:opacity-60 text-white text-sm font-semibold transition-colors flex items-center justify-center gap-2">
              <i v-if="downloadSaving" class="fa-solid fa-spinner fa-spin text-xs"></i>
              <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
              {{ downloadModal.isEdit ? 'अद्यावधिक गर्नुहोस्' : 'सुरक्षित गर्नुहोस्' }}
            </button>
          </div>

        </form>
      </div>
    </div>
  </Teleport>

  <!-- ── Download Link delete confirm ─────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="deleteDownloadTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div class="bg-white rounded-xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-triangle-exclamation text-red-500 text-lg"></i>
        </div>
        <h3 class="text-base font-bold text-gray-900 text-center mb-1">लिंक हटाउने?</h3>
        <p class="text-sm text-gray-500 text-center mb-5">
          <strong>{{ deleteDownloadTarget?.title }}</strong> स्थायी रूपमा मेटाइनेछ।
        </p>
        <div class="flex gap-3">
          <button @click="deleteDownloadTarget = null"
            class="flex-1 py-2 rounded-md border border-[#e6e7e9] text-sm font-medium text-gray-700 hover:bg-gray-50">
            रद्द गर्नुहोस्
          </button>
          <button @click="doDeleteDownload"
            class="flex-1 py-2 rounded-md bg-red-500 hover:bg-red-600 text-white text-sm font-medium">
            मेटाउनुहोस्
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- ── Representative delete confirm ────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="deleteRepTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div class="bg-white rounded-xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-triangle-exclamation text-red-500 text-lg"></i>
        </div>
        <h3 class="text-base font-bold text-gray-900 text-center mb-1">जनप्रतिनिधि हटाउने?</h3>
        <p class="text-sm text-gray-500 text-center mb-5">
          <strong>{{ deleteRepTarget.name }}</strong> को विवरण स्थायी रूपमा मेटाइनेछ।
        </p>
        <div class="flex gap-3">
          <button @click="deleteRepTarget = null"
            class="flex-1 py-2 rounded-md border border-[#e6e7e9] text-sm font-medium text-gray-700 hover:bg-gray-50">
            रद्द गर्नुहोस्
          </button>
          <button @click="doDeleteRep"
            class="flex-1 py-2 rounded-md bg-red-500 hover:bg-red-600 text-white text-sm font-medium">
            मेटाउनुहोस्
          </button>
        </div>
      </div>
    </div>
  </Teleport>

</template>

<script setup>
import { ref, computed, watch, reactive, onMounted } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import NepaliDatePicker from '@/components/NepaliDatePicker.vue'

const props = defineProps({
  lg:                { type: Object,  required: true },
  provinces:         { type: Array,   default: () => [] },
  districts:         { type: Array,   default: () => [] },
  wards:             { type: Array,   default: () => [] },
  profileSections:   { type: Object,  default: () => ({}) },
  tab:               { type: String,  default: 'general' },
  isLocked:          { type: Boolean, default: false },
  lockedAt:          { type: String,  default: null },
  lockedByName:      { type: String,  default: null },
  isSuperAdmin:      { type: Boolean, default: false },
  welcomeConfig:     { type: Object,  default: () => ({}) },
  representatives:   { type: Array,   default: () => [] },
  surveyOptsForRep:  { type: Object,  default: () => ({}) },
  employees:         { type: Array,   default: () => [] },
  photos:            { type: Array,   default: () => [] },
  downloadLinks:      { type: Array,   default: () => [] },
  maintenanceConfig:  { type: Object,  default: () => ({}) },
  settlements:        { type: Array,   default: () => [] },
})

// ── Lock / Unlock ─────────────────────────────────────────────────────────────
// true whenever settings are locked — applies to ALL roles including super_admin
const locked = computed(() => props.isLocked)

function lockSettings()   { router.post('/admin/settings/lock') }
function unlockSettings() { router.post('/admin/settings/unlock') }

const tabs = [
  { key: 'general',         label: 'सामान्य',           icon: 'fa-solid fa-gear' },
  { key: 'wards',           label: 'वडाहरू',            icon: 'fa-solid fa-map-pin' },
  { key: 'toles',           label: 'टोल/बस्ती',        icon: 'fa-solid fa-location-dot' },
  { key: 'profile',         label: 'प्रोफाइल',         icon: 'fa-solid fa-file-lines' },
  { key: 'map',             label: 'नक्सा',            icon: 'fa-solid fa-map' },
  { key: 'photo',           label: 'फोटो डाइरेक्टरी', icon: 'fa-solid fa-camera' },
  { key: 'welcome',         label: 'स्वागत सन्देश',    icon: 'fa-solid fa-bullhorn' },
  { key: 'representatives', label: 'जनप्रतिनिधि',      icon: 'fa-solid fa-people-group' },
  { key: 'employees',       label: 'कर्मचारी',          icon: 'fa-solid fa-id-badge' },
  { key: 'gallery',         label: 'फोटो ग्यालरी',     icon: 'fa-solid fa-images' },
  { key: 'downloads',       label: 'डाउनलोड लिंक',     icon: 'fa-solid fa-link' },
  { key: 'maintenance',     label: 'मर्मत अवस्था',      icon: 'fa-solid fa-triangle-exclamation' },
]

const activeTab = ref(props.tab)

// ── Province → District → Local Level cascade ─────────────────────────────
const selectedProvince = ref(
  props.lg.district?.province_id
    ?? props.districts.find(d => d.id === props.lg.district_id)?.province_id
    ?? ''
)
const localGovs    = ref([])
const lgLoading    = ref(false)
const selectedLgId = ref('')

const filteredDistricts = computed(() =>
  selectedProvince.value
    ? props.districts.filter(d => d.province_id === selectedProvince.value)
    : []
)

function onProvinceChange() {
  genForm.district_id = ''
  localGovs.value = []
  selectedLgId.value = ''
}

async function onDistrictChange() {
  localGovs.value = []
  selectedLgId.value = ''
  if (!genForm.district_id) return
  lgLoading.value = true
  try {
    const res = await fetch(`/admin/settings/api/local-governments?district_id=${genForm.district_id}`)
    localGovs.value = await res.json()
  } finally {
    lgLoading.value = false
  }
}

function onLgSelect() {
  const lg = localGovs.value.find(l => l.id === selectedLgId.value)
  if (!lg) return
  genForm.name_np = lg.name_np
  genForm.name_en = lg.name_en
  if (lg.type) genForm.type = lg.type
}

// Pre-load LGs for the currently saved district on page open
onMounted(async () => {
  if (genForm.district_id) {
    lgLoading.value = true
    try {
      const res = await fetch(`/admin/settings/api/local-governments?district_id=${genForm.district_id}`)
      localGovs.value = await res.json()
    } finally {
      lgLoading.value = false
    }
  }
})

// ── General form ───────────────────────────────────────────────────────────
const genForm = useForm({
  name_np:          props.lg.name_np          ?? '',
  name_en:          props.lg.name_en          ?? '',
  type:             props.lg.type             ?? 'municipality',
  district_id:      props.lg.district_id      ?? '',
  ward_count:       props.lg.ward_count       ?? 1,
  contact_email:    props.lg.contact_email    ?? '',
  contact_phone:    props.lg.contact_phone    ?? '',
  website:          props.lg.website          ?? '',
  established_date: props.lg.established_date ?? '',
})
function saveGeneral() {
  if (locked.value) return
  genForm.patch('/admin/settings/general')
}

// ── Logo upload ────────────────────────────────────────────────────────────
const logoInputRef = ref(null)
const logoFile     = ref(null)
const logoForm     = useForm({ logo: null })
function onLogoSelect(e) {
  logoFile.value     = e.target.files[0] ?? null
  logoForm.logo      = logoFile.value
}
function uploadLogo() {
  if (locked.value) return
  logoForm.post('/admin/settings/logo', { forceFormData: true })
}

// ── Profile form ───────────────────────────────────────────────────────────
const profileInitial = {}
for (const key of Object.keys(props.profileSections)) {
  profileInitial[key] = props.lg.profile_content?.[key] ?? ''
}
const profileForm = useForm({ profile: profileInitial })
function saveProfile() {
  if (locked.value) return
  profileForm.patch('/admin/settings/profile')
}

// ── Map form ───────────────────────────────────────────────────────────────
const mapForm = useForm({
  center_lat: props.lg.map_config?.center_lat ?? 27.7172,
  center_lng: props.lg.map_config?.center_lng ?? 85.3240,
  zoom:       props.lg.map_config?.zoom       ?? 10,
  markers:    [...(props.lg.map_config?.markers ?? [])],
})
function addMarker() {
  mapForm.markers.push({ name: '', lat: '', lng: '', type: 'other', popup: '' })
}
function saveMap() {
  if (locked.value) return
  mapForm.patch('/admin/settings/map')
}

// ── Photo directory form ───────────────────────────────────────────────────
const photoForm = useForm({
  household_base_url:   props.lg.photo_dir?.household_base_url   ?? '',
  institution_base_url: props.lg.photo_dir?.institution_base_url ?? '',
  gallery_url:          props.lg.photo_dir?.gallery_url          ?? '',
})
function savePhotoDir() {
  if (locked.value) return
  photoForm.patch('/admin/settings/photo-dir')
}

// ── Welcome config form ────────────────────────────────────────────────────
const wc = props.welcomeConfig
const welcomeForm = useForm({
  show_banner:       wc.show_banner   ?? true,
  message:           wc.message       ?? '',
  message_body:      wc.message_body  ?? '',
  chairperson: {
    name:        wc.chairperson?.name        ?? '',
    designation: wc.chairperson?.designation ?? 'अध्यक्ष',
    contact:     wc.chairperson?.contact     ?? '',
    email:       wc.chairperson?.email       ?? '',
  },
  vice_chairperson: {
    name:        wc.vice_chairperson?.name        ?? '',
    designation: wc.vice_chairperson?.designation ?? 'उपाध्यक्ष',
    contact:     wc.vice_chairperson?.contact     ?? '',
    email:       wc.vice_chairperson?.email       ?? '',
  },
  cao: {
    name:        wc.cao?.name        ?? '',
    designation: wc.cao?.designation ?? 'प्रमुख प्रशासकीय अधिकृत',
    contact:     wc.cao?.contact     ?? '',
    email:       wc.cao?.email       ?? '',
  },
})
function saveWelcome() {
  if (locked.value) return
  welcomeForm.patch('/admin/settings/welcome')
}

// Leader photo upload
const leaderPhotoInputs = ref({ chairperson: null, vice_chairperson: null, cao: null })
function triggerLeaderPhotoUpload(role) {
  leaderPhotoInputs.value[role]?.click()
}
function onLeaderPhotoChange(role, e) {
  const file = e.target.files[0]
  if (!file) return
  const fd = new FormData()
  fd.append('role', role)
  fd.append('photo', file)
  router.post('/admin/settings/leader-photo', fd, { forceFormData: true })
}
function leaderPhotoUrl(role) {
  const path = props.welcomeConfig?.[role]?.photo
  return path ? `/storage/${path}` : null
}

// Map image upload / delete
const mapImageInputRef = ref(null)
function onMapImageChange(e) {
  const file = e.target.files[0]
  if (!file) return
  const fd = new FormData()
  fd.append('map_image', file)
  router.post('/admin/settings/map-image', fd, { forceFormData: true })
}
function deleteMapImage() {
  if (locked.value) return
  router.delete('/admin/settings/map-image')
}

// ── Wards ──────────────────────────────────────────────────────────────────
const wardForm = useForm({ ward_number: '', ward_chairperson: '', contact_phone: '' })
const wardLimitReached = computed(() => props.wards.length >= props.lg.ward_count)

function addWard() {
  if (locked.value) return
  if (wardLimitReached.value) return
  wardForm.post('/admin/settings/wards', {
    onSuccess: () => wardForm.reset(),
  })
}

const editingWardId = ref(null)
const editWardForm  = useForm({ ward_chairperson: '', contact_phone: '' })
function startEditWard(ward) {
  editingWardId.value = ward.id
  editWardForm.ward_chairperson = ward.ward_chairperson ?? ''
  editWardForm.contact_phone    = ward.contact_phone    ?? ''
}
function saveWard(ward) {
  if (locked.value) return
  editWardForm.patch(`/admin/settings/wards/${ward.id}`, {
    onSuccess: () => { editingWardId.value = null },
  })
}

const deleteWardTarget = ref(null)
function confirmDeleteWard(ward) { deleteWardTarget.value = ward }
function doDeleteWard() {
  if (locked.value) return
  router.delete(`/admin/settings/wards/${deleteWardTarget.value.id}`, {
    onSuccess: () => { deleteWardTarget.value = null },
  })
}

// ── Ward bulk selection ────────────────────────────────────────────────────
const selectedWards    = ref([])
const wardAllSelected  = computed(() => props.wards.length > 0 && selectedWards.value.length === props.wards.length)
const wardSomeSelected = computed(() => selectedWards.value.length > 0 && selectedWards.value.length < props.wards.length)

function toggleAllWards(e) {
  selectedWards.value = e.target.checked ? props.wards.map(w => w.id) : []
}

const showBulkDeleteWardsModal = ref(false)
function confirmBulkDeleteWards() { showBulkDeleteWardsModal.value = true }
function doBulkDeleteWards() {
  if (locked.value) return
  router.delete('/admin/settings/wards/bulk', {
    data: { ids: selectedWards.value },
    onSuccess: () => { selectedWards.value = []; showBulkDeleteWardsModal.value = false },
  })
}

// ── Settlements ────────────────────────────────────────────────────────────
const selectedWardId = ref('')
const selectedWard   = computed(() => props.wards.find(w => w.id === selectedWardId.value) ?? null)

const settlForm = useForm({ name_np: '', name_en: '', settlement_type: 'tole' })
function addSettlement() {
  if (locked.value) return
  settlForm.post(`/admin/settings/wards/${selectedWardId.value}/settlements`, {
    onSuccess: () => settlForm.reset(),
  })
}

const editingSettlId = ref(null)
const editSettlForm  = useForm({ name_np: '', name_en: '', settlement_type: 'tole' })
function startEditSettlement(s) {
  editingSettlId.value        = s.id
  editSettlForm.name_np        = s.name_np
  editSettlForm.name_en        = s.name_en
  editSettlForm.settlement_type = s.settlement_type
}
function saveSettlement(s) {
  if (locked.value) return
  editSettlForm.patch(`/admin/settings/settlements/${s.id}`, {
    onSuccess: () => { editingSettlId.value = null },
  })
}
function deleteSettlement(s) {
  if (locked.value) return
  if (confirm(`"${s.name_np}" हटाउने?`)) {
    router.delete(`/admin/settings/settlements/${s.id}`)
  }
}

// ── Settlement bulk selection ──────────────────────────────────────────────
const selectedSettlements    = ref([])
const currentSettlements     = computed(() => selectedWard.value?.settlements ?? [])
const settlAllSelected       = computed(() => currentSettlements.value.length > 0 && selectedSettlements.value.length === currentSettlements.value.length)
const settlSomeSelected      = computed(() => selectedSettlements.value.length > 0 && selectedSettlements.value.length < currentSettlements.value.length)

function toggleAllSettlements(e) {
  selectedSettlements.value = e.target.checked ? currentSettlements.value.map(s => s.id) : []
}

// Clear settlement selection when ward changes
watch(selectedWardId, () => { selectedSettlements.value = [] })

const showBulkDeleteSettlModal = ref(false)
function confirmBulkDeleteSettlements() { showBulkDeleteSettlModal.value = true }
function doBulkDeleteSettlements() {
  if (locked.value) return
  router.delete('/admin/settings/settlements/bulk', {
    data: { ids: selectedSettlements.value },
    onSuccess: () => { selectedSettlements.value = []; showBulkDeleteSettlModal.value = false },
  })
}

// ── Representatives ────────────────────────────────────────────────────────

const POSITIONS = [
  { value: 'pramukh',          label: 'प्रमुख' },
  { value: 'upa_pramukh',      label: 'उप-प्रमुख' },
  { value: 'adhyaksha',        label: 'अध्यक्ष' },
  { value: 'upa_adhyaksha',    label: 'उपाध्यक्ष' },
  { value: 'ward_adhyaksha',   label: 'वार्ड अध्यक्ष' },
  { value: 'mahila_sadashya',  label: 'महिला सदश्य' },
  { value: 'dalit_mahila',     label: 'दलित महिला सदश्य' },
  { value: 'sadashya',         label: 'सदश्य' },
]

function positionLabel(v) {
  return POSITIONS.find(p => p.value === v)?.label ?? v
}
function genderLabel(v) {
  return { male: 'पुरुष', female: 'महिला', other: 'अन्य' }[v] ?? v
}
function optLabel(category, value) {
  if (!value) return '—'
  const found = (props.surveyOptsForRep?.[category] ?? []).find(o => o.value === value)
  return found?.label_ne ?? value
}

const wardList = computed(() => props.wards.map(w => ({ id: w.id, ward_number: w.ward_number })))

// Accordion open state
const openReps = reactive({})
function toggleRep(id) { openReps[id] = !openReps[id] }

// Modal state
const repModal = reactive({ open: false, isEdit: false, id: null, existingPhoto: null })
const repPhotoInput = ref(null)
const repPhotoPreview = ref(null)
const repSaving = ref(false)

const repFormDefaults = () => ({
  name: '', position: '', ward_id: '', birth_place: '', date_of_birth: '',
  ethnicity: '', gender: '', education: '', address_ward_id: '', address_tole_id: '',
  contact: '', email: '', father_name: '', mother_name: '', spouse_name: '',
  elected_party: '', work_experience: '',
})
const repForm = reactive(repFormDefaults())

const filteredRepToles = computed(() => {
  if (!repForm.address_ward_id) return []
  return (props.settlements ?? []).filter(s => s.ward_id === repForm.address_ward_id)
})

function openRepForm(rep) {
  Object.assign(repForm, repFormDefaults())
  repPhotoPreview.value = null
  if (rep) {
    Object.assign(repForm, {
      name: rep.name ?? '', position: rep.position ?? '',
      ward_id: rep.ward_id ?? '', birth_place: rep.birth_place ?? '',
      date_of_birth: rep.date_of_birth ? String(rep.date_of_birth).slice(0, 10) : '', ethnicity: rep.ethnicity ?? '',
      gender: rep.gender ?? '', education: rep.education ?? '',
      address_ward_id: rep.address_ward_id ?? '', address_tole_id: rep.address_tole_id ?? '',
      contact: rep.contact ?? '', email: rep.email ?? '',
      father_name: rep.father_name ?? '', mother_name: rep.mother_name ?? '',
      spouse_name: rep.spouse_name ?? '', elected_party: rep.elected_party ?? '',
      work_experience: rep.work_experience ?? '',
    })
    repModal.isEdit        = true
    repModal.id            = rep.id
    repModal.existingPhoto = rep.photo ?? null
  } else {
    repModal.isEdit        = false
    repModal.id            = null
    repModal.existingPhoto = null
  }
  repModal.open = true
}

function onRepPhotoChange(e) {
  const file = e.target.files[0]
  if (!file) { repPhotoPreview.value = null; return }
  repPhotoPreview.value = URL.createObjectURL(file)
}

function submitRepForm() {
  if (locked.value) return
  repSaving.value = true

  const fd = new FormData()
  Object.entries(repForm).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined) fd.append(k, v) })
  if (repPhotoInput.value?.files?.[0]) fd.append('photo', repPhotoInput.value.files[0])

  const url = repModal.isEdit
    ? `/admin/settings/representatives/${repModal.id}`
    : '/admin/settings/representatives'

  router.post(url, fd, {
    forceFormData: true,
    onSuccess: () => { repModal.open = false; repPhotoPreview.value = null },
    onFinish:  () => { repSaving.value = false },
  })
}

const deleteRepTarget = ref(null)
function confirmDeleteRep(rep) { deleteRepTarget.value = rep }
function doDeleteRep() {
  if (!deleteRepTarget.value) return
  router.delete(`/admin/settings/representatives/${deleteRepTarget.value.id}`, {
    onSuccess: () => { deleteRepTarget.value = null },
  })
}

// ── Employees ─────────────────────────────────────────────────────────────
const openEmps = reactive({})
function toggleEmp(id) { openEmps[id] = !openEmps[id] }

const empModal = reactive({ open: false, isEdit: false, id: null, existingPhoto: null })
const empPhotoInput = ref(null)
const empPhotoPreview = ref(null)
const empSaving = ref(false)

const empFormDefaults = () => ({
  name: '', position: '', department: '', entry_date: '', date_of_birth: '',
  birth_place: '', ethnicity: '', gender: '', education: '',
  permanent_address: '', contact: '', email: '',
  facebook: '', instagram: '', tiktok: '', twitter_x: '',
  father_name: '', mother_name: '', spouse_name: '', work_experience: '',
})
const empForm = reactive(empFormDefaults())

function openEmpForm(emp) {
  Object.assign(empForm, empFormDefaults())
  empPhotoPreview.value = null
  if (emp) {
    Object.assign(empForm, {
      name:               emp.name               ?? '',
      position:           emp.position           ?? '',
      department:         emp.department         ?? '',
      entry_date:         emp.entry_date         ? String(emp.entry_date).slice(0, 10) : '',
      date_of_birth:      emp.date_of_birth      ? String(emp.date_of_birth).slice(0, 10) : '',
      birth_place:        emp.birth_place        ?? '',
      ethnicity:          emp.ethnicity          ?? '',
      gender:             emp.gender             ?? '',
      education:          emp.education          ?? '',
      permanent_address:  emp.permanent_address  ?? '',
      contact:            emp.contact            ?? '',
      email:              emp.email              ?? '',
      facebook:           emp.facebook           ?? '',
      instagram:          emp.instagram          ?? '',
      tiktok:             emp.tiktok             ?? '',
      twitter_x:          emp.twitter_x          ?? '',
      father_name:        emp.father_name        ?? '',
      mother_name:        emp.mother_name        ?? '',
      spouse_name:        emp.spouse_name        ?? '',
      work_experience:    emp.work_experience    ?? '',
    })
    empModal.isEdit        = true
    empModal.id            = emp.id
    empModal.existingPhoto = emp.photo ?? null
  } else {
    empModal.isEdit        = false
    empModal.id            = null
    empModal.existingPhoto = null
  }
  empModal.open = true
}

function onEmpPhotoChange(e) {
  const file = e.target.files[0]
  if (!file) { empPhotoPreview.value = null; return }
  empPhotoPreview.value = URL.createObjectURL(file)
}

function submitEmpForm() {
  if (locked.value) return
  empSaving.value = true

  const fd = new FormData()
  Object.entries(empForm).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined) fd.append(k, v) })
  if (empPhotoInput.value?.files?.[0]) fd.append('photo', empPhotoInput.value.files[0])

  const url = empModal.isEdit
    ? `/admin/settings/employees/${empModal.id}`
    : '/admin/settings/employees'

  router.post(url, fd, {
    forceFormData: true,
    onSuccess: () => { empModal.open = false; empPhotoPreview.value = null },
    onFinish:  () => { empSaving.value = false },
  })
}

const deleteEmpTarget = ref(null)
function confirmDeleteEmp(emp) { deleteEmpTarget.value = emp }
function doDeleteEmp() {
  if (!deleteEmpTarget.value) return
  router.delete(`/admin/settings/employees/${deleteEmpTarget.value.id}`, {
    onSuccess: () => { deleteEmpTarget.value = null },
  })
}

// ── Photo Gallery ──────────────────────────────────────────────────────────
const PHOTO_CATEGORIES = [
  'स्वास्थ्य','शिक्षा','कृषि','पर्यटन','धार्मिक स्थल','पुल-पुलेसा',
  'सरकारी भवन','सामुदायिक भवन','वनजंगल','जडिबुटी','जनवार','उद्योग',
  'सडक','चाडपर्व','संस्कृति','व्यवसाय','खानेपानी','खनिज',
  'सवदाह/ चियान क्षेत्र','अन्य',
]

const openPhotos = reactive({})
function togglePhoto(id) { openPhotos[id] = !openPhotos[id] }

const galleryModal = reactive({ open: false, isEdit: false, id: null })
const gallerySaving = ref(false)

const galleryFormDefaults = () => ({
  photo_num: '', photo_ext: 'jpg', category: '', title: '', subtitle: '',
  gps_location: '', description: '', photo_date: '', photo_credit: '',
})
const galleryForm = reactive(galleryFormDefaults())

function openGalleryForm(ph) {
  Object.assign(galleryForm, galleryFormDefaults())
  if (ph) {
    // Split stored photo_no (e.g. "042.png") back into num + ext
    const stored = ph.photo_no ?? ''
    const dotIdx = stored.lastIndexOf('.')
    const num = dotIdx >= 0 ? stored.slice(0, dotIdx)  : stored
    const ext = dotIdx >= 0 ? stored.slice(dotIdx + 1) : 'jpg'
    Object.assign(galleryForm, {
      photo_num:    num,
      photo_ext:    ['jpg','jpeg','png'].includes(ext) ? ext : 'jpg',
      category:     ph.category     ?? '',
      title:        ph.title        ?? '',
      subtitle:     ph.subtitle     ?? '',
      gps_location: ph.gps_location ?? '',
      description:  ph.description  ?? '',
      photo_date:   ph.photo_date   ? String(ph.photo_date).slice(0, 10) : '',
      photo_credit: ph.photo_credit ?? '',
    })
    galleryModal.isEdit = true
    galleryModal.id     = ph.id
  } else {
    galleryModal.isEdit = false
    galleryModal.id     = null
  }
  galleryModal.open = true
}

function submitGalleryForm() {
  if (locked.value) return
  if (!/^\d+$/.test(galleryForm.photo_num)) return
  gallerySaving.value = true
  const { photo_num, photo_ext, ...rest } = galleryForm
  const url = galleryModal.isEdit
    ? `/admin/settings/gallery/${galleryModal.id}`
    : '/admin/settings/gallery'
  router.post(url, { ...rest, photo_no: `${photo_num}.${photo_ext}` }, {
    onSuccess: () => { galleryModal.open = false },
    onFinish:  () => { gallerySaving.value = false },
  })
}

const deletePhotoTarget = ref(null)
function confirmDeletePhoto(ph) { deletePhotoTarget.value = ph }
function doDeletePhoto() {
  if (!deletePhotoTarget.value) return
  router.delete(`/admin/settings/gallery/${deletePhotoTarget.value.id}`, {
    onSuccess: () => { deletePhotoTarget.value = null },
  })
}

// ── Maintenance Mode ───────────────────────────────────────────────────────

const bgTypeOptions = [
  { value: 'upload',   label: 'फोटो अपलोड',  icon: 'fa-solid fa-upload' },
  { value: 'url',      label: 'URL लिंक',     icon: 'fa-solid fa-link' },
  { value: 'gradient', label: 'ग्राडियन्ट',   icon: 'fa-solid fa-palette' },
]

const mc = props.maintenanceConfig ?? {}
const maintForm = reactive({
  is_active:    mc.is_active    ?? false,
  message:      mc.message      ?? '',
  message_sub:  mc.message_sub  ?? '',
  bg_type:      mc.bg_type      ?? 'gradient',
  bg_url:       mc.bg_url       ?? '',
})

// Split stored countdown_at into date + time parts
const maintCountdownDate = ref(mc.countdown_at ? String(mc.countdown_at).slice(0, 10) : '')
const maintCountdownTime = ref(mc.countdown_at ? String(mc.countdown_at).slice(11, 16) : '')

const maintSaving      = ref(false)
const maintBgFile      = ref(null)
const maintBgPreview   = ref(null)
const maintBgInput     = ref(null)
const maintBgUploading = ref(false)

function onMaintBgChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  maintBgFile.value    = file
  maintBgPreview.value = URL.createObjectURL(file)
}

function saveMaintenance() {
  maintSaving.value = true
  const countdown_at = maintCountdownDate.value
    ? `${maintCountdownDate.value} ${maintCountdownTime.value || '00:00'}:00`
    : null
  router.patch('/admin/settings/maintenance', {
    ...maintForm,
    countdown_at,
  }, {
    onFinish: () => { maintSaving.value = false },
  })
}

function uploadMaintBg() {
  if (!maintBgFile.value) return
  maintBgUploading.value = true
  router.post('/admin/settings/maintenance/bg', { bg_image: maintBgFile.value }, {
    forceFormData: true,
    onSuccess: () => { maintBgFile.value = null; maintBgPreview.value = null },
    onFinish:  () => { maintBgUploading.value = false },
  })
}

function deleteMaintBg() {
  router.delete('/admin/settings/maintenance/bg')
}

// ── Download Links ─────────────────────────────────────────────────────────

const downloadModal      = reactive({ open: false, isEdit: false, id: null, existingThumb: null })
const downloadSaving     = ref(false)
const downloadThumbInput = ref(null)
const downloadThumbFile  = ref(null)
const downloadThumbPreview = ref(null)

const downloadFormDefaults = () => ({
  title: '', description: '', url: '', created_date: '', sort_order: 0,
})
const downloadForm = reactive(downloadFormDefaults())

function onDownloadThumbChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  downloadThumbFile.value = file
  downloadThumbPreview.value = URL.createObjectURL(file)
}

function openDownloadForm(dl) {
  Object.assign(downloadForm, downloadFormDefaults())
  downloadThumbFile.value    = null
  downloadThumbPreview.value = null
  if (downloadThumbInput.value) downloadThumbInput.value.value = ''
  if (dl) {
    Object.assign(downloadForm, {
      title:        dl.title        ?? '',
      description:  dl.description  ?? '',
      url:          dl.url          ?? '',
      created_date: dl.created_date ? String(dl.created_date).slice(0, 10) : '',
      sort_order:   dl.sort_order   ?? 0,
    })
    downloadModal.existingThumb = dl.thumbnail_url ?? null
    downloadModal.isEdit = true
    downloadModal.id     = dl.id
  } else {
    downloadModal.existingThumb = null
    downloadModal.isEdit = false
    downloadModal.id     = null
  }
  downloadModal.open = true
}

function submitDownloadForm() {
  if (locked.value) return
  downloadSaving.value = true
  const url = downloadModal.isEdit
    ? `/admin/settings/download-links/${downloadModal.id}`
    : '/admin/settings/download-links'
  const payload = { ...downloadForm }
  if (downloadThumbFile.value) payload.thumbnail = downloadThumbFile.value
  router.post(url, payload, {
    forceFormData: true,
    onSuccess: () => { downloadModal.open = false },
    onFinish:  () => { downloadSaving.value = false },
  })
}

const deleteDownloadTarget = ref(null)
function confirmDeleteDownload(dl) { deleteDownloadTarget.value = dl }
function doDeleteDownload() {
  if (!deleteDownloadTarget.value) return
  router.delete(`/admin/settings/download-links/${deleteDownloadTarget.value.id}`, {
    onSuccess: () => { deleteDownloadTarget.value = null },
  })
}

// ── Combined Ward + Settlement bulk import ─────────────────────────────────
const page                 = usePage()
const combinedImportResult = computed(() => page.props.flash?.ward_settlements_import_result ?? null)
const showCombinedImport   = ref(false)
const combinedFileInput    = ref(null)
const combinedImportForm   = useForm({ file: null })

function openCombinedImport() {
  combinedImportForm.reset()
  combinedImportForm.clearErrors()
  if (combinedFileInput.value) combinedFileInput.value.value = ''
  showCombinedImport.value = true
}
function closeCombinedImport() { showCombinedImport.value = false }
function onCombinedFileChange(e) { combinedImportForm.file = e.target.files[0] ?? null }
function submitCombinedImport() {
  if (locked.value) return
  combinedImportForm.post('/admin/settings/wards-settlements/import', {
    forceFormData: true,
    onSuccess: () => { showCombinedImport.value = false },
  })
}
</script>

<style scoped>
.field-label { @apply block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5; }
.field-input { @apply w-full px-3 py-2.5 rounded-md border border-[#e6e7e9] text-sm focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] transition-colors; }
.field-error { @apply text-xs text-red-500 mt-1; }
</style>
