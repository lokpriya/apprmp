<template>
  <div class="flex gap-5 items-start">

    <!-- ── Left panel: category list ── -->
    <div class="w-80 shrink-0 sticky top-4 self-start">
      <div class="bg-white rounded-md border border-gray-100 shadow-sm overflow-hidden flex flex-col" style="max-height: calc(100vh - 5rem)">

        <!-- Header -->
        <div class="bg-[#182433] px-4 py-3">
          <p class="text-xs font-semibold text-white/60 uppercase tracking-wider">विकल्प श्रेणीहरू</p>
          <p class="text-[10px] text-white/30 mt-0.5">Option Categories</p>
        </div>

        <!-- Household / Institution / Business tabs -->
        <div class="flex border-b border-gray-100">
          <button @click="activeTab = 'household'"
            class="flex-1 py-2 text-[11px] font-semibold transition-colors"
            :class="activeTab === 'household' ? 'bg-[#206bc4] text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'">
            🏠 घरधुरी
          </button>
          <button @click="activeTab = 'institution'"
            class="flex-1 py-2 text-[11px] font-semibold transition-colors"
            :class="activeTab === 'institution' ? 'bg-[#206bc4] text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'">
            🏢 संस्था
          </button>
          <button @click="activeTab = 'business'"
            class="flex-1 py-2 text-[11px] font-semibold transition-colors"
            :class="activeTab === 'business' ? 'bg-orange-600 text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'">
            🏪 व्यवसाय
          </button>
        </div>

        <!-- Category list -->
        <div class="flex-1 overflow-y-auto">

          <!-- Household: grouped by step sections -->
          <template v-if="activeTab === 'household'">
            <template v-for="section in props.householdSections" :key="section.step">
              <!-- Section header -->
              <div class="flex items-center gap-2 px-3 pt-2.5 pb-2 border-b sticky top-0 z-10"
                :style="{ backgroundColor: stepColor(section.step).bg, borderColor: stepColor(section.step).border }">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded text-white text-[9px] font-bold shrink-0"
                  :style="{ backgroundColor: stepColor(section.step).badge }">
                  {{ section.step }}
                </span>
                <div class="min-w-0">
                  <p class="text-[10px] font-bold leading-tight truncate" :style="{ color: stepColor(section.step).text }">{{ section.title_ne }}</p>
                  <p class="text-[9px] leading-tight truncate" :style="{ color: stepColor(section.step).sub }">{{ section.title_en }}</p>
                </div>
              </div>
              <!-- Category buttons for this section -->
              <button
                v-for="key in section.keys.filter(k => props.householdCategories[k])"
                :key="key"
                @click="selectCategory(key)"
                class="w-full text-left px-3 py-2 border-b border-gray-50 transition-colors border-l-4"
                :class="activeCategory === key
                  ? 'bg-[#206bc4]/8 border-l-[#206bc4]'
                  : 'border-l-transparent hover:bg-gray-50'">
                <p class="text-xs font-semibold leading-tight"
                  :class="activeCategory === key ? 'text-[#206bc4]' : 'text-[#1d273b]'">
                  {{ categoryNe(props.householdCategories[key]) }}
                </p>
                <p v-if="categoryEn(props.householdCategories[key])" class="text-[10px] leading-tight mt-0.5"
                  :class="activeCategory === key ? 'text-[#206bc4]/70' : 'text-[#9aa0ac]'">
                  {{ categoryEn(props.householdCategories[key]) }}
                </p>
              </button>
            </template>
          </template>

          <!-- Institution: grouped by section -->
          <template v-else-if="activeTab === 'institution'">
            <template v-for="section in props.institutionSections" :key="section.step + section.title_en">
              <div class="flex items-center gap-2 px-3 pt-2.5 pb-2 border-b sticky top-0 z-10"
                :style="{ backgroundColor: stepColor(section.step).bg, borderColor: stepColor(section.step).border }">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded text-white text-[9px] font-bold shrink-0"
                  :style="{ backgroundColor: stepColor(section.step).badge }">
                  {{ section.step }}
                </span>
                <div class="min-w-0">
                  <p class="text-[10px] font-bold leading-tight truncate" :style="{ color: stepColor(section.step).text }">{{ section.title_ne }}</p>
                  <p class="text-[9px] leading-tight truncate" :style="{ color: stepColor(section.step).sub }">{{ section.title_en }}</p>
                </div>
              </div>
              <button
                v-for="key in section.keys.filter(k => props.institutionCategories[k])"
                :key="key"
                @click="selectCategory(key)"
                class="w-full text-left px-3 py-2 border-b border-gray-50 transition-colors border-l-4"
                :class="activeCategory === key
                  ? 'bg-[#206bc4]/8 border-l-[#206bc4]'
                  : 'border-l-transparent hover:bg-gray-50'">
                <p class="text-xs font-semibold leading-tight"
                  :class="activeCategory === key ? 'text-[#206bc4]' : 'text-[#1d273b]'">
                  {{ categoryNe(props.institutionCategories[key]) }}
                </p>
                <p v-if="categoryEn(props.institutionCategories[key])" class="text-[10px] leading-tight mt-0.5"
                  :class="activeCategory === key ? 'text-[#206bc4]/70' : 'text-[#9aa0ac]'">
                  {{ categoryEn(props.institutionCategories[key]) }}
                </p>
              </button>
            </template>
          </template>

          <!-- Business: grouped by form step sections -->
          <template v-else-if="activeTab === 'business'">
            <template v-for="section in props.businessSections" :key="section.step">
              <div class="flex items-center gap-2 px-3 pt-2.5 pb-2 border-b sticky top-0 z-10 bg-orange-50 border-orange-100">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded text-white text-[9px] font-bold shrink-0 bg-orange-500">
                  {{ section.step }}
                </span>
                <div class="min-w-0">
                  <p class="text-[10px] font-bold leading-tight truncate text-orange-700">{{ section.title_ne }}</p>
                  <p class="text-[9px] leading-tight truncate text-orange-400">{{ section.title_en }}</p>
                </div>
              </div>
              <button
                v-for="key in section.keys.filter(k => props.businessCategories[k])"
                :key="key"
                @click="selectCategory(key)"
                class="w-full text-left px-3 py-2 border-b border-gray-50 transition-colors border-l-4"
                :class="activeCategory === key
                  ? 'bg-orange-50 border-l-orange-500'
                  : 'border-l-transparent hover:bg-gray-50'">
                <p class="text-xs font-semibold leading-tight"
                  :class="activeCategory === key ? 'text-orange-600' : 'text-[#1d273b]'">
                  {{ categoryNe(props.businessCategories[key]) }}
                </p>
                <p v-if="categoryEn(props.businessCategories[key])" class="text-[10px] leading-tight mt-0.5"
                  :class="activeCategory === key ? 'text-orange-400' : 'text-[#9aa0ac]'">
                  {{ categoryEn(props.businessCategories[key]) }}
                </p>
              </button>
            </template>
          </template>

        </div>
      </div>
    </div>

    <!-- ── Right panel: options table + form ── -->
    <div class="flex-1 space-y-4 min-w-0">

      <!-- Panel header -->
      <div class="bg-white rounded-md border border-gray-100 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 bg-[#182433]">
          <div>
            <h2 class="text-sm font-semibold text-white">{{ categoryNe(activeCategoryLabel) }}</h2>
            <p v-if="categoryEn(activeCategoryLabel)" class="text-[10px] text-white/70 mt-0.5">
              {{ categoryEn(activeCategoryLabel) }}
            </p>
          </div>
          <div class="flex items-center gap-2">
            <a href="/admin/survey-options/template"
              class="flex items-center gap-1.5 px-3 py-1.5 bg-white/15 text-white text-xs font-medium rounded hover:bg-white/25 transition-colors">
              <i class="fa-solid fa-file-arrow-down text-[10px]"></i>
              Template
            </a>
            <button @click="openImport"
              class="flex items-center gap-1.5 px-3 py-1.5 bg-white/15 text-white text-xs font-medium rounded hover:bg-white/25 transition-colors">
              <i class="fa-solid fa-file-import text-[10px]"></i>
              Excel आयात
            </button>
            <button v-if="!showForm" @click="openAdd"
              class="flex items-center gap-1.5 px-3 py-1.5 bg-white text-[#206bc4] text-xs font-semibold rounded hover:bg-[#206bc4]/8 transition-colors">
              <i class="fa-solid fa-plus text-[10px]"></i>
              नयाँ विकल्प थप्नुहोस्
            </button>
            <button v-else @click="cancelForm"
              class="flex items-center gap-1.5 px-3 py-1.5 bg-white/20 text-white text-xs font-medium rounded hover:bg-white/30 transition-colors">
              <i class="fa-solid fa-xmark text-[10px]"></i>
              फाराम बन्द गर्नुहोस्
            </button>
          </div>
        </div>

        <!-- Add / Edit form -->
        <div v-if="showForm" class="border-b border-gray-100 bg-[#206bc4]/5 px-5 py-4">
          <h3 class="text-sm font-semibold text-gray-800 mb-3">
            {{ form.id ? 'विकल्प सम्पादन' : 'नयाँ विकल्प थप्नुहोस्' }}
            <span v-if="form.id" class="ml-2 text-xs font-normal text-gray-400">(ID: {{ form.id }})</span>
          </h3>
          <form @submit.prevent="submitForm">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <!-- Value -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                  Value (Code) <span class="text-red-400">*</span>
                  <span class="font-normal text-gray-400 normal-case ml-1">— a-z, 0-9, _ or -</span>
                </label>
                <input v-model="form.value" type="text" required pattern="[a-zA-Z0-9_\-]+"
                  placeholder="e.g. brahmin, piped_water"
                  class="w-full px-3 py-2 rounded-md border text-sm focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4]"
                  :class="form.errors.value ? 'border-red-400' : 'border-gray-200'" />
                <p v-if="form.id" class="text-[10px] text-amber-600 mt-1">⚠ Value बदल्दा पुरानो सर्वेक्षण डेटामा असर पर्न सक्छ।</p>
                <p v-if="form.errors.value" class="text-xs text-red-500 mt-1">{{ form.errors.value }}</p>
              </div>
              <!-- Sort order -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">क्रम (Sort Order)</label>
                <input v-model.number="form.sort_order" type="number" min="0"
                  class="w-full px-3 py-2 rounded-md border border-gray-200 text-sm focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4]" />
              </div>
              <!-- Nepali label -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                  नेपाली लेबल <span class="text-red-400">*</span>
                </label>
                <input v-model="form.label_ne" type="text" required
                  placeholder="उदाहरण: ब्राह्मण"
                  class="w-full px-3 py-2 rounded-md border text-sm focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4]"
                  :class="form.errors.label_ne ? 'border-red-400' : 'border-gray-200'" />
                <p v-if="form.errors.label_ne" class="text-xs text-red-500 mt-1">{{ form.errors.label_ne }}</p>
              </div>
              <!-- English label -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">
                  English Label <span class="text-gray-400 font-normal normal-case">(optional)</span>
                </label>
                <input v-model="form.label_en" type="text"
                  placeholder="e.g. Brahmin"
                  class="w-full px-3 py-2 rounded-md border border-gray-200 text-sm focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4]" />
              </div>
            </div>

            <!-- Active toggle -->
            <div class="flex items-center gap-3 mt-3">
              <button type="button" @click="form.is_active = !form.is_active"
                class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
                :class="form.is_active ? 'bg-[#206bc4]' : 'bg-gray-200'">
                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
                  :class="form.is_active ? 'translate-x-[18px]' : 'translate-x-0.5'"></span>
              </button>
              <span class="text-sm text-gray-700">सक्रिय (Active)</span>
            </div>

            <!-- Buttons -->
            <div class="flex items-center gap-2 mt-4">
              <button type="submit" :disabled="form.processing"
                class="px-5 py-2 bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-60 text-white text-sm font-medium rounded-md transition-colors flex items-center gap-2">
                <i v-if="form.processing" class="fa-solid fa-spinner fa-spin text-xs"></i>
                {{ form.id ? 'अपडेट गर्नुहोस्' : 'सुरक्षित गर्नुहोस्' }}
              </button>
              <button type="button" @click="cancelForm"
                class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-md transition-colors">
                रद्द गर्नुहोस्
              </button>
            </div>
          </form>
        </div>

        <!-- Options table -->
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-[#e6e7e9]">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-10">#</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Value</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">नेपाली लेबल</th>
                <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">English Label</th>
                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase w-24">स्थिति</th>
                <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase w-36">कार्य</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#e6e7e9]">
              <tr v-if="props.options.length === 0">
                <td colspan="6" class="px-4 py-12 text-center">
                  <i class="fa-solid fa-inbox text-3xl text-gray-200 block mb-2"></i>
                  <p class="text-sm text-gray-400">यस श्रेणीमा कुनै विकल्प छैन</p>
                  <p class="text-xs text-gray-300 mt-1">माथि "+ नयाँ विकल्प थप्नुहोस्" क्लिक गर्नुहोस्</p>
                </td>
              </tr>
              <tr v-for="opt in props.options" :key="opt.id"
                class="hover:bg-gray-50/50 transition-colors"
                :class="!opt.is_active ? 'opacity-50' : ''">
                <td class="px-3 py-2.5 text-xs text-gray-400">{{ opt.sort_order }}</td>
                <td class="px-3 py-2.5">
                  <code class="text-xs bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded font-mono">{{ opt.value }}</code>
                  <span v-if="opt.local_government_id === null"
                    class="ml-1 inline-flex items-center text-[10px] text-[#206bc4] bg-[#206bc4]/8 border border-[#206bc4]/30 px-1.5 py-0.5 rounded">
                    Global
                  </span>
                  <span v-else
                    class="ml-1 inline-flex items-center text-[10px] text-purple-600 bg-purple-50 border border-purple-200 px-1.5 py-0.5 rounded">
                    Local
                  </span>
                </td>
                <td class="px-3 py-2.5 text-sm text-gray-800">{{ opt.label_ne }}</td>
                <td class="px-3 py-2.5 text-sm text-gray-500 hidden md:table-cell">{{ opt.label_en ?? '—' }}</td>
                <td class="px-3 py-2.5 text-center">
                  <button @click="toggleActive(opt)"
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium transition-colors"
                    :class="opt.is_active
                      ? 'bg-green-100 text-green-700 hover:bg-green-200'
                      : 'bg-gray-100 text-gray-500 hover:bg-gray-200'">
                    {{ opt.is_active ? 'सक्रिय' : 'निष्क्रिय' }}
                  </button>
                </td>
                <td class="px-3 py-2.5 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <button @click="openEdit(opt)"
                      class="text-xs px-2 py-1 rounded bg-[#206bc4]/8 text-[#206bc4] hover:bg-[#206bc4]/15 font-medium transition-colors">
                      सम्पादन
                    </button>
                    <button @click="confirmDelete(opt)"
                      class="text-xs px-2 py-1 rounded bg-red-50 text-red-600 hover:bg-red-100 font-medium transition-colors">
                      मेटाउनुहोस्
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Footer count -->
        <div v-if="props.options.length > 0" class="px-5 py-2 bg-gray-50 border-t border-gray-100">
          <p class="text-xs text-gray-500">
            जम्मा {{ props.options.length }} विकल्प —
            <span class="text-[#206bc4]">{{ props.options.filter(o => o.local_government_id === null).length }} Global</span>,
            <span class="text-purple-600">{{ props.options.filter(o => o.local_government_id !== null).length }} Local</span>
          </p>
        </div>
      </div>

      <!-- Help note -->
      <div class="bg-[#206bc4]/8 border border-[#206bc4]/20 rounded-md px-4 py-3 text-xs text-[#206bc4] space-y-1">
        <p class="font-semibold">ℹ️ निर्देशन (Super Admin):</p>
        <ul class="list-disc pl-4 space-y-0.5 text-[#206bc4]">
          <li><strong>Global</strong> — सबै स्थानीय तहमा देखिने प्रणाली विकल्प।</li>
          <li><strong>Local</strong> — कुनै खास स्थानीय तहले थपेका विकल्प।</li>
          <li>नयाँ थप्दा <code class="bg-[#206bc4]/15 px-1 rounded">value</code> मा अंग्रेजी अक्षर, अंक, _ वा - मात्र।</li>
          <li>⚠ Value बदल्दा पुरानो सर्वेक्षण डेटा प्रभावित हुन सक्छ।</li>
        </ul>
      </div>
    </div>

    <!-- Import result banner -->
    <div v-if="importResult" class="bg-white rounded-md border border-[#e6e7e9] px-5 py-4 space-y-2">
      <p class="text-[13px] font-semibold text-[#1d273b] flex items-center gap-2">
        <i class="fa-solid fa-circle-check text-green-500"></i>
        आयात सम्पन्न भयो
      </p>
      <div class="flex flex-wrap gap-4 text-[12px]">
        <span class="flex items-center gap-1.5 text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded">
          <i class="fa-solid fa-plus text-[10px]"></i>
          {{ importResult.inserted }} नयाँ थपियो
        </span>
        <span class="flex items-center gap-1.5 text-[#206bc4] bg-[#206bc4]/8 border border-[#206bc4]/20 px-2.5 py-1 rounded">
          <i class="fa-solid fa-pen text-[10px]"></i>
          {{ importResult.updated }} अपडेट भयो
        </span>
        <span v-if="importResult.skipped" class="flex items-center gap-1.5 text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded">
          <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
          {{ importResult.skipped }} छोडियो
        </span>
      </div>
      <ul v-if="importResult.errors?.length" class="mt-2 space-y-0.5 max-h-32 overflow-y-auto">
        <li v-for="(err, i) in importResult.errors" :key="i"
          class="text-[11px] text-red-600 font-mono bg-red-50 px-2 py-0.5 rounded">{{ err }}</li>
      </ul>
    </div>

    <!-- Import modal -->
    <Teleport to="body">
      <div v-if="showImport" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-md shadow-2xl w-full max-w-md">

          <!-- Header -->
          <div class="bg-[#182433] px-5 py-4 rounded-t-md flex items-center justify-between">
            <div>
              <h3 class="text-[14px] font-semibold text-white">Excel बाट आयात</h3>
              <p class="text-[11px] text-white/50 mt-0.5">Import Survey Options</p>
            </div>
            <button @click="closeImport" class="text-white/50 hover:text-white transition-colors">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>

          <!-- Body -->
          <div class="px-5 py-4 space-y-4">

            <!-- Instructions -->
            <div class="bg-[#206bc4]/8 border border-[#206bc4]/20 rounded-md px-4 py-3 text-[12px] text-[#206bc4] space-y-1">
              <p class="font-semibold flex items-center gap-1.5"><i class="fa-solid fa-circle-info text-[11px]"></i> Excel ढाँचा</p>
              <ul class="list-disc pl-4 space-y-0.5">
                <li>पहिलो पङ्क्ति: <code class="bg-[#206bc4]/15 px-1 rounded">category, value, label_ne, label_en, sort_order, is_active</code></li>
                <li><code class="bg-[#206bc4]/15 px-1 rounded">category</code> — Categories sheet बाट key लिनुहोस्</li>
                <li>एउटै category+value भएमा रेकर्ड अपडेट हुनेछ (upsert)</li>
                <li>xlsx, xls वा csv फाइल मात्र मान्य</li>
              </ul>
            </div>

            <!-- Template download -->
            <a href="/admin/survey-options/template"
              class="flex items-center gap-2 w-full px-4 py-2.5 border border-[#e6e7e9] rounded-md text-[13px] text-[#626976] hover:bg-gray-50 transition-colors">
              <i class="fa-solid fa-file-arrow-down text-[#206bc4] text-[13px]"></i>
              <span>Template डाउनलोड गर्नुहोस् <span class="text-[11px] text-[#9aa0ac]">(sample data सहित)</span></span>
            </a>

            <!-- File input -->
            <div>
              <label class="block text-[12px] font-semibold text-[#626976] uppercase tracking-wide mb-1.5">
                Excel फाइल छान्नुहोस् <span class="text-red-400">*</span>
              </label>
              <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv"
                @change="onFileChange"
                class="w-full text-[13px] text-[#626976] file:mr-3 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-[#206bc4] file:text-white file:text-[12px] file:font-medium file:cursor-pointer cursor-pointer border border-[#e6e7e9] rounded-md px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-[#206bc4]/30"
                :class="importForm.errors.file ? 'border-red-400' : ''" />
              <p v-if="importForm.errors.file" class="text-[11px] text-red-500 mt-1">{{ importForm.errors.file }}</p>
            </div>

          </div>

          <!-- Footer -->
          <div class="px-5 py-3 bg-gray-50 border-t border-[#e6e7e9] rounded-b-md flex items-center justify-end gap-2">
            <button @click="closeImport"
              class="px-4 py-2 text-[13px] font-medium text-[#626976] bg-white border border-[#e6e7e9] rounded hover:bg-gray-50 transition-colors">
              रद्द गर्नुहोस्
            </button>
            <button @click="submitImport" :disabled="!importForm.file || importForm.processing"
              class="inline-flex items-center gap-2 px-4 py-2 bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-50 disabled:cursor-not-allowed text-white text-[13px] font-medium rounded transition-colors">
              <i :class="importForm.processing ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-file-import'" class="text-[11px]"></i>
              {{ importForm.processing ? 'आयात हुँदैछ...' : 'आयात गर्नुहोस्' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete confirm modal -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-md shadow-2xl max-w-sm w-full p-6">
          <div class="w-12 h-12 rounded-md bg-red-100 flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-triangle-exclamation text-red-500 text-lg"></i>
          </div>
          <h3 class="text-base font-bold text-gray-900 text-center mb-1">विकल्प मेटाउने?</h3>
          <p class="text-sm text-gray-500 text-center mb-5">
            <strong>{{ deleteTarget.label_ne }}</strong> स्थायी रूपमा मेटाइनेछ।
          </p>
          <div class="flex gap-3">
            <button @click="deleteTarget = null"
              class="flex-1 py-2 rounded-md border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50">
              रद्द गर्नुहोस्
            </button>
            <button @click="doDelete"
              class="flex-1 py-2 rounded-md bg-red-500 hover:bg-red-600 text-white text-sm font-medium">
              मेटाउनुहोस्
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  householdCategories:   { type: Object, default: () => ({}) },
  householdSections:     { type: Array,  default: () => [] },
  institutionCategories: { type: Object, default: () => ({}) },
  institutionSections:   { type: Array,  default: () => [] },
  businessCategories:    { type: Object, default: () => ({}) },
  businessSections:      { type: Array,  default: () => [] },
  activeCategory:        { type: String, default: '' },
  activeCategoryLabel:   { type: String, default: '' },
  options:               { type: Array,  default: () => [] },
})

const page = usePage()

// ── Tab & category ─────────────────────────────────────────────────
const activeTab = ref(
  Object.keys(props.institutionCategories).includes(props.activeCategory)
    ? 'institution'
    : Object.keys(props.businessCategories).includes(props.activeCategory)
      ? 'business'
      : 'household'
)

const activeCategory = ref(props.activeCategory)

// When tab changes, switch to first category of that tab
watch(activeTab, (tab) => {
  const cats = tab === 'institution'
    ? props.institutionCategories
    : tab === 'business'
      ? props.businessCategories
      : props.householdCategories
  selectCategory(Object.keys(cats)[0])
})

function selectCategory(key) {
  activeCategory.value = key
  cancelForm()
  router.get('/admin/survey-options', { category: key }, { preserveState: true, replace: true })
}

// ── Step colours ──────────────────────────────────────────────────
const STEP_COLORS = {
   1: { bg: '#fff7ed', border: '#fed7aa', badge: '#ea580c', text: '#9a3412', sub: '#c2410c' }, // orange
   2: { bg: '#fdf4ff', border: '#e9d5ff', badge: '#9333ea', text: '#6b21a8', sub: '#7e22ce' }, // purple
   3: { bg: '#f0fdf4', border: '#bbf7d0', badge: '#16a34a', text: '#14532d', sub: '#15803d' }, // green
   4: { bg: '#eff6ff', border: '#bfdbfe', badge: '#2563eb', text: '#1e3a8a', sub: '#1d4ed8' }, // blue
   5: { bg: '#fdf2f8', border: '#fbcfe8', badge: '#db2777', text: '#831843', sub: '#be185d' }, // pink
   6: { bg: '#f0fdfa', border: '#99f6e4', badge: '#0d9488', text: '#134e4a', sub: '#0f766e' }, // teal
   7: { bg: '#fefce8', border: '#fde68a', badge: '#ca8a04', text: '#713f12', sub: '#a16207' }, // yellow
   8: { bg: '#ecfdf5', border: '#a7f3d0', badge: '#059669', text: '#064e3b', sub: '#047857' }, // emerald
   9: { bg: '#fff7ed', border: '#fed7aa', badge: '#c2410c', text: '#7c2d12', sub: '#9a3412' }, // deep-orange
  10: { bg: '#fdf4ff', border: '#f5d0fe', badge: '#a21caf', text: '#701a75', sub: '#86198f' }, // fuchsia
  11: { bg: '#f0f9ff', border: '#bae6fd', badge: '#0284c7', text: '#0c4a6e', sub: '#0369a1' }, // sky
  12: { bg: '#f7fee7', border: '#d9f99d', badge: '#65a30d', text: '#3f6212', sub: '#4d7c0f' }, // lime
  13: { bg: '#fff1f2', border: '#fecdd3', badge: '#e11d48', text: '#881337', sub: '#be123c' }, // rose
  14: { bg: '#f8fafc', border: '#cbd5e1', badge: '#475569', text: '#1e293b', sub: '#64748b' }, // slate
}
function stepColor(step) {
  return STEP_COLORS[step] ?? { bg: '#f9fafb', border: '#e5e7eb', badge: '#374151', text: '#111827', sub: '#6b7280' }
}

// ── Helpers ────────────────────────────────────────────────────────
function categoryNe(label) {
  return label.includes('(') ? label.slice(0, label.indexOf('(')).trim() : label
}
function categoryEn(label) {
  if (!label.includes('(')) return ''
  return label.slice(label.indexOf('(') + 1, label.lastIndexOf(')')).trim()
}

// ── Form ───────────────────────────────────────────────────────────
const showForm = ref(false)
const form = useForm({
  id:         null,
  category:   props.activeCategory,
  value:      '',
  label_ne:   '',
  label_en:   '',
  sort_order: 0,
  is_active:  true,
})

// Keep form.category in sync when category changes
watch(() => props.activeCategory, (cat) => { form.category = cat })

function openAdd() {
  form.reset()
  form.id        = null
  form.category  = props.activeCategory
  form.sort_order = props.options.length + 1
  form.is_active = true
  showForm.value = true
}

function openEdit(opt) {
  form.id         = opt.id
  form.category   = props.activeCategory
  form.value      = opt.value
  form.label_ne   = opt.label_ne
  form.label_en   = opt.label_en ?? ''
  form.sort_order = opt.sort_order
  form.is_active  = opt.is_active
  showForm.value  = true
}

function cancelForm() {
  showForm.value = false
  form.reset()
  form.clearErrors()
}

function submitForm() {
  if (form.id) {
    form.put(`/admin/survey-options/${form.id}`, { onSuccess: cancelForm })
  } else {
    form.post('/admin/survey-options', { onSuccess: cancelForm })
  }
}

// ── Toggle active ──────────────────────────────────────────────────
function toggleActive(opt) {
  router.patch(`/admin/survey-options/${opt.id}/toggle`)
}

// ── Delete ─────────────────────────────────────────────────────────
const deleteTarget = ref(null)
function confirmDelete(opt) { deleteTarget.value = opt }
function doDelete() {
  router.delete(`/admin/survey-options/${deleteTarget.value.id}`, {
    onSuccess: () => { deleteTarget.value = null },
  })
}

// ── Import ──────────────────────────────────────────────────────────
const showImport   = ref(false)
const importResult = computed(() => page.props.flash?.import_result ?? null)

const importForm = useForm({ file: null, category: props.activeCategory })
watch(() => props.activeCategory, (cat) => { importForm.category = cat })

const fileInput = ref(null)

function openImport() {
  importForm.reset()
  importForm.clearErrors()
  if (fileInput.value) fileInput.value.value = ''
  showImport.value = true
}
function closeImport() { showImport.value = false }

function onFileChange(e) {
  importForm.file = e.target.files[0] ?? null
}

function submitImport() {
  importForm.post('/admin/survey-options/import', {
    forceFormData: true,
    onSuccess: () => { showImport.value = false },
  })
}
</script>
