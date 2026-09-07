<template>
  <div>

    <!-- ── Page body ── -->
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 py-4">

      <!-- ══ WELCOME BANNER ══ -->
      <Transition name="banner-slide">
        <div v-if="showBanner" class="mb-4">
          <!-- Close button row -->
          <div class="flex justify-end mb-1.5">
            <button @click="dismissBanner"
              class="flex items-center gap-1.5 text-xs text-gray-400 hover:text-gray-600 transition-colors px-2 py-1 rounded hover:bg-gray-100">
              <i class="fa-solid fa-xmark"></i>
              बन्द गर्नुहोस्
            </button>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

            <!-- Welcome message card -->
            <div class="lg:col-span-5 bg-white border border-[#e6e7e9] rounded-md p-6 flex flex-col">
              <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-2">स्वागत सन्देश</p>
              <h2 class="text-2xl font-bold text-gray-900 leading-tight mb-3">
                {{ props.welcomeConfig.message || 'हार्दिक स्वागत छ' }}
              </h2>
              <p class="text-sm text-gray-600 leading-relaxed flex-1">
                {{ props.welcomeConfig.message_body || '' }}
              </p>
              <a v-if="props.lg" href="/public/profile"
                class="inline-flex items-center gap-1.5 mt-4 self-start px-4 py-2 bg-[#206bc4] hover:bg-[#1a5ba8] text-white rounded text-sm font-medium transition-colors">
                थप जानकारी →
              </a>
            </div>

            <!-- Map image -->
            <div class="lg:col-span-4 bg-white border border-[#e6e7e9] rounded-md p-4 flex flex-col">
              <p class="text-xs font-semibold text-gray-500 flex items-center gap-1.5 mb-3">
                <i class="fa-solid fa-location-dot text-[#7b1f1f]"></i> स्थान नक्सा
              </p>
              <div class="flex-1 flex items-center justify-center bg-[#fdf5f5] rounded border border-dashed border-[#e6c5c5] min-h-[140px]">
                <img v-if="props.welcomeConfig?.map_image"
                  :src="`/storage/${props.welcomeConfig.map_image}`"
                  class="max-h-48 max-w-full object-contain p-2" />
                <div v-else class="text-center py-4">
                  <i class="fa-solid fa-map text-[#c0a0a0] text-3xl mb-2 block"></i>
                  <p class="text-xs text-gray-400">नक्सा चित्र उपलब्ध छैन</p>
                </div>
              </div>
            </div>

            <!-- Leadership cards -->
            <div class="lg:col-span-3 flex flex-col gap-3">
              <template v-for="(leader, role) in {
                chairperson:      { label: 'अध्यक्ष',                    defaultEmail: 'chair@mun.gov.np' },
                vice_chairperson: { label: 'उपाध्यक्ष',                   defaultEmail: 'vice@mun.gov.np' },
                cao:              { label: 'प्रमुख प्रशासकीय अधिकृत',    defaultEmail: 'cao@mun.gov.np' },
              }" :key="role">
                <div v-if="props.welcomeConfig[role]?.name || props.welcomeConfig[role]?.designation"
                  class="bg-white border border-[#e6e7e9] rounded-md px-4 py-3 flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-gray-100 overflow-hidden shrink-0 flex items-center justify-center">
                    <img v-if="props.welcomeConfig[role]?.photo"
                      :src="`/storage/${props.welcomeConfig[role].photo}`"
                      class="w-full h-full object-cover" />
                    <i v-else class="fa-solid fa-user text-gray-300 text-lg"></i>
                  </div>
                  <div class="min-w-0">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide truncate">
                      {{ props.welcomeConfig[role]?.designation || leader.label }}
                    </p>
                    <p class="text-sm font-semibold text-gray-800 truncate">
                      {{ props.welcomeConfig[role]?.name || 'श्री/श्रीमती ———' }}
                    </p>
                    <div class="flex items-center gap-2 mt-0.5">
                      <span v-if="props.welcomeConfig[role]?.contact"
                        class="text-[11px] text-gray-500 flex items-center gap-1">
                        <i class="fa-solid fa-phone text-[9px] text-[#7b1f1f]"></i>
                        {{ props.welcomeConfig[role].contact }}
                      </span>
                      <span v-if="props.welcomeConfig[role]?.email"
                        class="text-[11px] text-gray-400 flex items-center gap-1 truncate">
                        <i class="fa-solid fa-envelope text-[9px]"></i>
                        {{ props.welcomeConfig[role].email }}
                      </span>
                    </div>
                  </div>
                </div>
              </template>
            </div>

          </div>
        </div>
      </Transition>

      <!-- ══ FILTER BAR ══ -->
      <div class="bg-white border border-[#e6e7e9] rounded-md p-4 mb-4">
        <div class="flex flex-wrap items-end gap-3">
          <!-- Ward -->
          <div class="flex-1 min-w-[160px]">
            <label class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] mb-1">
              {{ t('filter.ward') }}
            </label>
            <select v-model="selWard" @change="onWardChange"
              class="w-full h-9 rounded border border-[#e6e7e9] bg-white px-3 text-[13px] text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] transition outline-none">
              <option value="">{{ t('filter.allWards') }}</option>
              <option v-for="w in props.wards" :key="w.id" :value="w.id">{{ t('common.ward') }} {{ w.ward_number }}</option>
            </select>
          </div>
          <!-- Settlement -->
          <div class="flex-1 min-w-[160px]">
            <label class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] mb-1">
              {{ t('filter.tole') }}
            </label>
            <select v-model="selSettlement" @change="applyFilter"
              :disabled="!selWard || !props.settlements.length"
              class="w-full h-9 rounded border border-[#e6e7e9] bg-white px-3 text-[13px] text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] transition outline-none"
              :class="(!selWard || !props.settlements.length) ? 'opacity-40 cursor-not-allowed' : ''">
              <option value="">{{ t('filter.allToles') }}</option>
              <option v-for="s in props.settlements" :key="s.id" :value="s.id">{{ locale === 'ne' ? (s.name_np || s.name_en) : (s.name_en || s.name_np) }}</option>
            </select>
          </div>
          <!-- Buttons -->
          <div class="flex items-center gap-2">
            <button @click="applyFilter"
              class="h-9 flex items-center gap-1.5 px-4 bg-[#206bc4] hover:bg-[#1a5ba8] text-white text-[13px] font-medium rounded transition">
              <i class="fa-solid fa-filter text-[11px]"></i>{{ t('filter.filter') }}
            </button>
            <button v-if="isFiltered" @click="clearFilter"
              class="h-9 flex items-center gap-1.5 px-3 border border-[#e6e7e9] text-[#626976] text-[13px] rounded hover:bg-gray-50 transition">
              <i class="fa-solid fa-xmark text-[11px]"></i>{{ t('filter.clear') }}
            </button>
          </div>
        </div>
        <!-- Active filter badge -->
        <div v-if="isFiltered" class="mt-3 flex flex-wrap items-center gap-2">
          <span class="inline-flex items-center gap-1.5 text-[12px] text-[#206bc4] bg-[#206bc4]/8 border border-[#206bc4]/20 px-2.5 py-1 rounded">
            <i class="fa-solid fa-circle-info text-[10px]"></i>
            {{ t('filter.showing') }}
            <strong v-if="selWard">{{ t('common.ward') }} {{ props.wards.find(w => w.id == selWard)?.ward_number }}</strong>
            <template v-if="selSettlement && props.settlements.length">
              <span class="opacity-50">›</span>
              <strong>{{ locale === 'ne' ? (props.settlements.find(s => s.id == selSettlement)?.name_np || props.settlements.find(s => s.id == selSettlement)?.name_en) : (props.settlements.find(s => s.id == selSettlement)?.name_en || props.settlements.find(s => s.id == selSettlement)?.name_np) }}</strong>
            </template>
          </span>
        </div>
      </div>

      <!-- ══ STATS CARDS ══ -->
      <section id="analytics" class="scroll-mt-[100px] mb-4">
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 mb-4">
          <div v-for="card in statCards" :key="card.key"
            class="bg-white border border-[#e6e7e9] rounded-md p-4 hover:border-[#206bc4]/30 transition-colors">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac] mb-1.5">{{ card.label }}</p>
                <p class="text-2xl font-semibold leading-none" :class="card.valClass || 'text-[#1d273b]'">
                  {{ fmt(card.value) }}
                </p>
              </div>
              <div class="flex w-9 h-9 items-center justify-center rounded shrink-0 mt-0.5" :class="card.iconClass">
                <i :class="[card.icon, 'text-base']"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- ── MAP ── -->
        <div v-if="props.lg?.map_config !== undefined" class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden mb-4">
          <div class="flex items-center justify-between px-4 py-3 border-b border-[#e6e7e9]">
            <div>
              <p class="text-[13px] font-semibold text-[#1d273b]">{{ t('dashboard.locationMap') }}</p>
              <p class="text-[12px] text-[#9aa0ac] mt-0.5">{{ props.lg?.name_en }}</p>
            </div>
            <span class="text-[11px] text-[#9aa0ac] bg-gray-100 px-2 py-1 rounded">OpenStreetMap</span>
          </div>
          <div ref="mapEl" style="height: 400px; width: 100%;"></div>
        </div>

        <!-- ── CHARTS ROW ── -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 mb-4">
          <!-- Gender -->
          <div class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e6e7e9]">
              <p class="text-[13px] font-semibold text-[#1d273b]">{{ t('dashboard.genderChart') }}</p>
              <p class="text-[11px] text-[#9aa0ac] mt-0.5">{{ t('stats.male') }} · {{ t('stats.female') }}</p>
            </div>
            <div class="p-4 h-44"><canvas ref="genderChartEl"></canvas></div>
          </div>
          <!-- Age -->
          <div class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e6e7e9]">
              <p class="text-[13px] font-semibold text-[#1d273b]">{{ t('dashboard.ageChart') }}</p>
              <p class="text-[11px] text-[#9aa0ac] mt-0.5">{{ t('stats.population') }}</p>
            </div>
            <div class="p-4 h-44"><canvas ref="ageChartEl"></canvas></div>
          </div>
          <!-- Institutions -->
          <div v-if="hasInstitutions" class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e6e7e9]">
              <p class="text-[13px] font-semibold text-[#1d273b]">{{ t('dashboard.institutionChart') }}</p>
              <p class="text-[11px] text-[#9aa0ac] mt-0.5">{{ t('stats.institutions') }}</p>
            </div>
            <div class="p-4 h-44"><canvas ref="institutionChartEl"></canvas></div>
          </div>
        </div>
      </section>

      <!-- ══ KEY METRICS ══ -->
      <section class="mb-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-white border border-[#e6e7e9] rounded-md p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded bg-emerald-100 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-book-open text-emerald-600 text-sm"></i>
            </div>
            <div>
              <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">साक्षरता दर</p>
              <p class="text-xl font-bold text-emerald-600">{{ props.keyMetrics?.literacy_rate ?? 0 }}%</p>
            </div>
          </div>
          <div class="bg-white border border-[#e6e7e9] rounded-md p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded bg-amber-100 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-house-user text-amber-600 text-sm"></i>
            </div>
            <div>
              <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">औसत परिवार</p>
              <p class="text-xl font-bold text-amber-600">{{ props.keyMetrics?.avg_hh_size ?? 0 }}</p>
            </div>
          </div>
          <div class="bg-white border border-[#e6e7e9] rounded-md p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded bg-indigo-100 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-venus-mars text-indigo-600 text-sm"></i>
            </div>
            <div>
              <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">लिंग अनुपात</p>
              <p class="text-xl font-bold text-indigo-600">{{ props.keyMetrics?.sex_ratio ?? 0 }}</p>
              <p class="text-[10px] text-[#9aa0ac]">प्रति १०० महिला</p>
            </div>
          </div>
          <div class="bg-white border border-[#e6e7e9] rounded-md p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded bg-teal-100 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-piggy-bank text-teal-600 text-sm"></i>
            </div>
            <div>
              <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">बैंक खाता</p>
              <p class="text-xl font-bold text-teal-600">{{ props.keyMetrics?.bank_account_pct ?? 0 }}%</p>
            </div>
          </div>
        </div>
      </section>

      <!-- ══ BASIC SERVICES ══ -->
      <section v-if="hasServices" class="mb-4">
        <div class="flex items-center justify-between mb-2">
          <p class="text-[12px] font-semibold text-[#626976] uppercase tracking-wide flex items-center gap-1.5">
            <i class="fa-solid fa-faucet text-[#9aa0ac] text-[11px]"></i> आधारभूत सेवाहरू
          </p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
          <MiniBarChart v-if="props.waterData?.length"
            title="पानीको स्रोत" subtitle="Water Source"
            icon="fa-solid fa-droplet" icon-color="text-blue-600" icon-bg="bg-blue-50"
            :data="props.waterData" color="#3B82F6" />
          <MiniBarChart v-if="props.toiletData?.length"
            title="शौचालयको प्रकार" subtitle="Toilet Type"
            icon="fa-solid fa-toilet" icon-color="text-green-600" icon-bg="bg-green-50"
            :data="props.toiletData" color="#10B981" />
          <MiniBarChart v-if="props.electricityData?.length"
            title="विद्युत स्रोत" subtitle="Electricity Source"
            icon="fa-solid fa-bolt" icon-color="text-yellow-600" icon-bg="bg-yellow-50"
            :data="props.electricityData" color="#F59E0B" />
          <MiniBarChart v-if="props.cookingData?.length"
            title="खाना पकाउने इन्धन" subtitle="Cooking Fuel"
            icon="fa-solid fa-fire-burner" icon-color="text-orange-600" icon-bg="bg-orange-50"
            :data="props.cookingData" color="#F97316" />
        </div>
      </section>

      <!-- ══ ECONOMY & EDUCATION ══ -->
      <section class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <!-- Income source -->
          <div v-if="props.incomeData?.length" class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e6e7e9] flex items-center gap-2">
              <div class="w-7 h-7 rounded bg-violet-100 flex items-center justify-center">
                <i class="fa-solid fa-sack-dollar text-violet-600 text-xs"></i>
              </div>
              <div>
                <p class="text-[13px] font-semibold text-[#1d273b]">आयको मुख्य स्रोत</p>
                <p class="text-[11px] text-[#9aa0ac]">Primary Income Source</p>
              </div>
            </div>
            <div class="p-3" :style="`height:${Math.min(60 + props.incomeData.length * 28, 280)}px`">
              <canvas ref="incomeChartEl"></canvas>
            </div>
          </div>
          <!-- Education level -->
          <div v-if="props.educationData?.length" class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e6e7e9] flex items-center gap-2">
              <div class="w-7 h-7 rounded bg-sky-100 flex items-center justify-center">
                <i class="fa-solid fa-graduation-cap text-sky-600 text-xs"></i>
              </div>
              <div>
                <p class="text-[13px] font-semibold text-[#1d273b]">शैक्षिक स्तर</p>
                <p class="text-[11px] text-[#9aa0ac]">Education Level</p>
              </div>
            </div>
            <div class="p-3" :style="`height:${Math.min(60 + props.educationData.length * 28, 280)}px`">
              <canvas ref="educationChartEl"></canvas>
            </div>
          </div>
        </div>
      </section>

      <!-- ══ OCCUPATION & LITERACY ══ -->
      <section class="mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <!-- Occupation -->
          <div v-if="props.occupationData?.length" class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e6e7e9] flex items-center gap-2">
              <div class="w-7 h-7 rounded bg-rose-100 flex items-center justify-center">
                <i class="fa-solid fa-briefcase text-rose-600 text-xs"></i>
              </div>
              <div>
                <p class="text-[13px] font-semibold text-[#1d273b]">पेशा / व्यवसाय</p>
                <p class="text-[11px] text-[#9aa0ac]">Occupation Type</p>
              </div>
            </div>
            <div class="p-3" :style="`height:${Math.min(60 + props.occupationData.length * 28, 280)}px`">
              <canvas ref="occupationChartEl"></canvas>
            </div>
          </div>
          <!-- Literacy by gender + business -->
          <div class="flex flex-col gap-3">
            <!-- Literacy by gender -->
            <div v-if="props.litByGender?.length" class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
              <div class="px-4 py-3 border-b border-[#e6e7e9] flex items-center gap-2">
                <div class="w-7 h-7 rounded bg-emerald-100 flex items-center justify-center">
                  <i class="fa-solid fa-spell-check text-emerald-600 text-xs"></i>
                </div>
                <div>
                  <p class="text-[13px] font-semibold text-[#1d273b]">लिंग अनुसार साक्षरता</p>
                  <p class="text-[11px] text-[#9aa0ac]">Literacy by Gender</p>
                </div>
              </div>
              <div class="p-3 h-36"><canvas ref="litGenderChartEl"></canvas></div>
            </div>
            <!-- Businesses -->
            <div v-if="props.businessData?.length" class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
              <div class="px-4 py-3 border-b border-[#e6e7e9] flex items-center gap-2">
                <div class="w-7 h-7 rounded bg-orange-100 flex items-center justify-center">
                  <i class="fa-solid fa-store text-orange-600 text-xs"></i>
                </div>
                <div>
                  <p class="text-[13px] font-semibold text-[#1d273b]">व्यवसायको प्रकार</p>
                  <p class="text-[11px] text-[#9aa0ac]">Business Type</p>
                </div>
              </div>
              <div class="p-3" :style="`height:${Math.min(60 + props.businessData.length * 26, 220)}px`">
                <canvas ref="businessChartEl"></canvas>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ══ SOCIAL INDICATORS ══ -->
      <section v-if="hasSocialData" class="mb-4">
        <div class="flex items-center gap-2 mb-3">
          <h2 class="text-[13px] font-bold uppercase tracking-widest text-[#9aa0ac]">सामाजिक सूचकहरू</h2>
          <div class="flex-1 border-t border-[#e6e7e9]"></div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-white border border-[#e6e7e9] rounded-md p-4 flex flex-col gap-1">
            <div class="w-8 h-8 rounded bg-rose-100 flex items-center justify-center mb-1">
              <i class="fa-solid fa-plane-departure text-rose-600 text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-[#1d273b]">{{ fmt(props.migrationData?.abroad ?? 0) }}</p>
            <p class="text-[12px] font-semibold text-[#1d273b]">विदेश गएका</p>
            <p class="text-[11px] text-[#9aa0ac]">Abroad</p>
          </div>
          <div class="bg-white border border-[#e6e7e9] rounded-md p-4 flex flex-col gap-1">
            <div class="w-8 h-8 rounded bg-amber-100 flex items-center justify-center mb-1">
              <i class="fa-solid fa-road text-amber-600 text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-[#1d273b]">{{ fmt(props.migrationData?.internal ?? 0) }}</p>
            <p class="text-[12px] font-semibold text-[#1d273b]">आन्तरिक बसाइसराइ</p>
            <p class="text-[11px] text-[#9aa0ac]">Internal Migration</p>
          </div>
          <div class="bg-white border border-[#e6e7e9] rounded-md p-4 flex flex-col gap-1">
            <div class="w-8 h-8 rounded bg-indigo-100 flex items-center justify-center mb-1">
              <i class="fa-solid fa-hammer text-indigo-600 text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-[#1d273b]">{{ props.trainingData?.vocational_pct ?? 0 }}%</p>
            <p class="text-[12px] font-semibold text-[#1d273b]">व्यावसायिक तालिम</p>
            <p class="text-[11px] text-[#9aa0ac]">Vocational Training</p>
          </div>
          <div class="bg-white border border-[#e6e7e9] rounded-md p-4 flex flex-col gap-1">
            <div class="w-8 h-8 rounded bg-teal-100 flex items-center justify-center mb-1">
              <i class="fa-solid fa-microchip text-teal-600 text-sm"></i>
            </div>
            <p class="text-2xl font-bold text-[#1d273b]">{{ props.trainingData?.technical_pct ?? 0 }}%</p>
            <p class="text-[12px] font-semibold text-[#1d273b]">प्राविधिक तालिम</p>
            <p class="text-[11px] text-[#9aa0ac]">Technical Training</p>
          </div>
        </div>
      </section>

      <!-- ══ DECISION MAKING ══ -->
      <section v-if="props.decisionData?.length" class="mb-4">
        <div class="flex items-center gap-2 mb-3">
          <h2 class="text-[13px] font-bold uppercase tracking-widest text-[#9aa0ac]">निर्णय लिने अधिकार</h2>
          <div class="flex-1 border-t border-[#e6e7e9]"></div>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-[12px]">
              <thead class="bg-[#f8f9fa] border-b border-[#e6e7e9]">
                <tr>
                  <th class="text-left px-4 py-2.5 font-semibold text-[#6e7985] w-40">निर्णय क्षेत्र</th>
                  <th class="text-right px-3 py-2.5 font-semibold text-blue-700">पुरुष</th>
                  <th class="text-right px-3 py-2.5 font-semibold text-pink-600">महिला</th>
                  <th class="text-right px-3 py-2.5 font-semibold text-emerald-600">दुवै</th>
                  <th class="text-left px-3 py-2.5 font-semibold text-[#6e7985] w-36">वितरण</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in props.decisionData" :key="row.key"
                  class="border-b border-[#f3f4f6] hover:bg-[#f8f9fa] transition-colors">
                  <td class="px-4 py-2.5 font-medium text-[#1d273b]">{{ row.label }}</td>
                  <td class="px-3 py-2.5 text-right text-blue-700 font-semibold tabular-nums">
                    {{ fmt(row.male) }}
                    <span class="text-[10px] text-[#9aa0ac] font-normal ml-0.5">({{ decisionPct(row.male, row) }}%)</span>
                  </td>
                  <td class="px-3 py-2.5 text-right text-pink-600 font-semibold tabular-nums">
                    {{ fmt(row.female) }}
                    <span class="text-[10px] text-[#9aa0ac] font-normal ml-0.5">({{ decisionPct(row.female, row) }}%)</span>
                  </td>
                  <td class="px-3 py-2.5 text-right text-emerald-600 font-semibold tabular-nums">
                    {{ fmt(row.both) }}
                    <span class="text-[10px] text-[#9aa0ac] font-normal ml-0.5">({{ decisionPct(row.both, row) }}%)</span>
                  </td>
                  <td class="px-3 py-2.5">
                    <div class="flex h-3 rounded overflow-hidden w-full bg-gray-100 min-w-[80px]">
                      <div v-if="row.male"   :style="`width:${decisionPct(row.male,row)}%`"   class="bg-blue-500"></div>
                      <div v-if="row.female" :style="`width:${decisionPct(row.female,row)}%`" class="bg-pink-400"></div>
                      <div v-if="row.both"   :style="`width:${decisionPct(row.both,row)}%`"   class="bg-emerald-400"></div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="px-4 py-2 border-t border-[#f3f4f6] flex gap-4 text-[10px] text-[#9aa0ac]">
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-blue-500 inline-block"></span> पुरुष Male</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-pink-400 inline-block"></span> महिला Female</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-400 inline-block"></span> दुवै Both</span>
          </div>
        </div>
      </section>

      <!-- ══ QUICK LINKS TO DETAIL REPORTS ══ -->
      <section class="mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <a href="/public/reports/population-pyramid"
            class="bg-white border border-[#e6e7e9] hover:border-[#206bc4]/40 rounded-md p-4 flex items-center gap-3 group transition-colors">
            <div class="w-10 h-10 rounded bg-indigo-100 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-chart-bar text-indigo-600"></i>
            </div>
            <div class="min-w-0">
              <p class="text-[13px] font-semibold text-[#1d273b] group-hover:text-[#206bc4] transition">जनसंख्या पिरामिड</p>
              <p class="text-[11px] text-[#9aa0ac]">उमेर समूह × लिंग — विस्तृत तालिका</p>
            </div>
            <i class="fa-solid fa-arrow-right text-[#9aa0ac] group-hover:text-[#206bc4] ml-auto shrink-0 transition"></i>
          </a>
          <a href="/public/reports/ward-summary"
            class="bg-white border border-[#e6e7e9] hover:border-[#206bc4]/40 rounded-md p-4 flex items-center gap-3 group transition-colors">
            <div class="w-10 h-10 rounded bg-emerald-100 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-table text-emerald-600"></i>
            </div>
            <div class="min-w-0">
              <p class="text-[13px] font-semibold text-[#1d273b] group-hover:text-[#206bc4] transition">वडागत सारांश</p>
              <p class="text-[11px] text-[#9aa0ac]">वडा अनुसार घरधुरी, जनसंख्या र संस्था</p>
            </div>
            <i class="fa-solid fa-arrow-right text-[#9aa0ac] group-hover:text-[#206bc4] ml-auto shrink-0 transition"></i>
          </a>
        </div>
      </section>

      <!-- ══ PROFILE CTA ══ -->
      <div v-if="props.profileFilledCount > 0"
        class="relative overflow-hidden bg-[#206bc4] rounded-md p-4 flex items-center justify-between gap-4 mb-4">
        <div class="text-white min-w-0">
          <p class="text-[14px] font-semibold leading-tight">{{ lgTypeLabel }}</p>
          <p class="text-blue-200 text-[12px] mt-0.5">
            {{ t('dashboard.profileCTADesc') }} —
            <span class="font-semibold text-white">{{ props.profileFilledCount }} {{ t('dashboard.sectionsAvailable') }}</span>
          </p>
        </div>
        <a href="/public/profile"
          class="shrink-0 flex items-center gap-1.5 h-8 px-4 bg-white text-[#206bc4] rounded text-[13px] font-semibold hover:bg-blue-50 transition whitespace-nowrap">
          {{ t('dashboard.viewProfile') }}
          <i class="fa-solid fa-arrow-right text-[11px]"></i>
        </a>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import Chart from 'chart.js/auto'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import PublicLayout from '@/layouts/PublicLayout.vue'
import { useLocale } from '@/i18n/index.js'
import { useSidebar } from '@/composables/useSidebar.js'

import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon   from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({ iconRetinaUrl: markerIcon2x, iconUrl: markerIcon, shadowUrl: markerShadow })

function toSafeGeoJsonUrl(url) {
  if (!url) return url
  // Convert GitHub blob → raw content URL
  url = url.replace(
    /^https?:\/\/github\.com\/([^/]+)\/([^/]+)\/blob\/(.+)$/,
    'https://raw.githubusercontent.com/$1/$2/$3'
  )
  // Force HTTPS — prevents mixed-content block on HTTPS sites
  url = url.replace(/^http:\/\//, 'https://')
  return url
}

defineOptions({ layout: PublicLayout })

const { locale, t } = useLocale()
const { clearSections } = useSidebar()

const props = defineProps({
  lg:                   { type: Object,  default: null },
  wards:                { type: Array,   default: () => [] },
  settlements:          { type: Array,   default: () => [] },
  stats:                { type: Object,  default: () => ({}) },
  genderData:           { type: Object,  default: () => ({}) },
  ageData:              { type: Object,  default: () => ({}) },
  institutionData:      { type: Array,   default: () => [] },
  wardSummary:          { type: Array,   default: () => [] },
  keyMetrics:           { type: Object,  default: () => ({}) },
  waterData:            { type: Array,   default: () => [] },
  toiletData:           { type: Array,   default: () => [] },
  electricityData:      { type: Array,   default: () => [] },
  cookingData:          { type: Array,   default: () => [] },
  incomeData:           { type: Array,   default: () => [] },
  occupationData:       { type: Array,   default: () => [] },
  educationData:        { type: Array,   default: () => [] },
  litByGender:          { type: Array,   default: () => [] },
  businessData:         { type: Array,   default: () => [] },
  migrationData:        { type: Object,  default: () => ({}) },
  trainingData:         { type: Object,  default: () => ({}) },
  decisionData:         { type: Array,   default: () => [] },
  selectedWardId:       { type: [Number, null], default: null },
  selectedSettlementId: { type: [Number, null], default: null },
  profileFilledCount:   { type: Number,  default: 0 },
  welcomeConfig:        { type: Object,  default: () => ({}) },
})

// ── Welcome banner dismiss (session-only, resets on every page load) ─
const bannerDismissed = ref(false)
function dismissBanner() {
  bannerDismissed.value = true
}
const showBanner = computed(() =>
  !bannerDismissed.value &&
  props.welcomeConfig?.show_banner !== false &&
  (props.welcomeConfig?.message || props.welcomeConfig?.chairperson?.name)
)

// ── Helpers ──────────────────────────────────────────────────────────
const fmt = n => Number(n ?? 0).toLocaleString()
const ucLabel = v => (v ?? '').split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')

const hasInstitutions = computed(() => props.institutionData.length > 0)
const hasServices     = computed(() => props.waterData?.length || props.toiletData?.length || props.electricityData?.length || props.cookingData?.length)
const hasSocialData   = computed(() => (props.migrationData?.abroad ?? 0) + (props.migrationData?.internal ?? 0) + (props.trainingData?.total ?? 0) > 0)

const LG_TYPE_LABELS = {
  municipality:      'नगरपालिका परिचय',
  rural_municipality:'गाउँपालिका परिचय',
  sub_metropolitan:  'उपमहानगरपालिका परिचय',
  metropolitan:      'महानगरपालिका परिचय',
}
const lgTypeLabel = computed(() =>
  LG_TYPE_LABELS[props.lg?.type] ?? t('dashboard.profileCTA')
)

const decisionPct = (n, row) => {
  const tot = (row.male ?? 0) + (row.female ?? 0) + (row.both ?? 0) + (row.other ?? 0)
  return tot > 0 ? Math.round(n / tot * 100) : 0
}

onMounted(() => {
  initAllCharts()
})

onUnmounted(() => {
  clearSections()
  genderChart?.destroy(); ageChart?.destroy(); institutionChart?.destroy()
  incomeChart?.destroy(); educationChart?.destroy(); occupationChart?.destroy()
  litGenderChart?.destroy(); businessChart?.destroy()
  leafletMap?.remove()
})

// ── Filter state ─────────────────────────────────────────────────────
const selWard       = ref(props.selectedWardId ?? '')
const selSettlement = ref(props.selectedSettlementId ?? '')
const isFiltered    = computed(() => !!selWard.value || !!selSettlement.value)

watch(() => props.selectedWardId,       v => { selWard.value       = v ?? '' })
watch(() => props.selectedSettlementId, v => { selSettlement.value = v ?? '' })

function onWardChange() {
  selSettlement.value = ''
  applyFilter()
}
function applyFilter() {
  const p = {}
  if (selWard.value)       p.ward_id       = selWard.value
  if (selSettlement.value) p.settlement_id = selSettlement.value
  router.get('/public', p, { preserveState: true, replace: true })
}
function clearFilter() {
  selWard.value = ''; selSettlement.value = ''
  router.get('/public', {}, { preserveState: true, replace: true })
}

// ── Stat cards ───────────────────────────────────────────────────────
const statCards = computed(() => [
  { key: 'households',   value: props.stats.total_households ?? 0,   label: t('stats.households'),   icon: 'fa-solid fa-house',    iconClass: 'bg-amber-100 text-amber-600',    valClass: '' },
  { key: 'members',      value: props.stats.total_members ?? 0,      label: t('stats.population'),   icon: 'fa-solid fa-users',    iconClass: 'bg-indigo-100 text-indigo-600',  valClass: '' },
  { key: 'male',         value: props.genderData?.male ?? 0,         label: t('stats.male'),         icon: 'fa-solid fa-mars',     iconClass: 'bg-blue-100 text-blue-700',      valClass: 'text-blue-700' },
  { key: 'female',       value: props.genderData?.female ?? 0,       label: t('stats.female'),       icon: 'fa-solid fa-venus',    iconClass: 'bg-pink-100 text-pink-600',      valClass: 'text-pink-600' },
  { key: 'institutions', value: props.stats.total_institutions ?? 0, label: t('stats.institutions'), icon: 'fa-solid fa-building', iconClass: 'bg-violet-100 text-violet-600',  valClass: '' },
  { key: 'wards',        value: props.stats.total_wards ?? 0,        label: t('stats.wards'),        icon: 'fa-solid fa-map',      iconClass: 'bg-emerald-100 text-emerald-600', valClass: '' },
])

// ── Map ──────────────────────────────────────────────────────────────
const mapEl = ref(null)
let leafletMap = null

async function initMap() {
  if (!mapEl.value || !props.lg) return
  leafletMap?.remove()
  const cfg  = props.lg.map_config ?? {}
  const lat  = cfg.center_lat ?? 27.7
  const lng  = cfg.center_lng ?? 85.3
  leafletMap = L.map(mapEl.value, { scrollWheelZoom: false }).setView([lat, lng], cfg.zoom ?? 13)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(leafletMap)

  // ── Choropleth layer ─────────────────────────────────────────────────
  if (cfg.geojson_url) {
    const wardProp = cfg.ward_number_property ?? 'WARD_NO'
    const wardData = Object.fromEntries((props.wardSummary ?? []).map(w => [String(w.ward_number), w]))
    const maxPop   = Math.max(...(props.wardSummary ?? []).map(w => w.total), 1)

    const choroplethColor = pop => {
      const r = pop / maxPop
      if (r > 0.8) return '#1a4e8a'
      if (r > 0.6) return '#2169b8'
      if (r > 0.4) return '#4299e1'
      if (r > 0.2) return '#90cdf4'
      if (pop > 0) return '#bee3f8'
      return '#e8f0fb'
    }

    try {
      const res = await fetch(toSafeGeoJsonUrl(cfg.geojson_url))
      if (res.ok) {
        const geojson = await res.json()
        L.geoJSON(geojson, {
          style: feature => {
            const wNum = String(feature.properties?.[wardProp] ?? '')
            const pop  = wardData[wNum]?.total ?? 0
            return { fillColor: choroplethColor(pop), weight: 1.5, opacity: 1, color: '#ffffff', fillOpacity: 0.72 }
          },
          onEachFeature: (feature, layer) => {
            const wNum = String(feature.properties?.[wardProp] ?? '')
            const w    = wardData[wNum]
            layer.bindPopup(
              `<b>वडा नं. ${wNum}</b><br>` +
              `जनसंख्या: ${(w?.total ?? 0).toLocaleString()}<br>` +
              `घरधुरी: ${(w?.households ?? 0).toLocaleString()}`
            )
            layer.on({
              mouseover: e => { e.target.setStyle({ weight: 2.5, fillOpacity: 0.92 }); e.target.bringToFront() },
              mouseout:  e => { e.target.setStyle({ weight: 1.5, fillOpacity: 0.72 }) },
            })
          },
        }).addTo(leafletMap)

        // Choropleth legend
        const legend = L.control({ position: 'bottomright' })
        legend.onAdd = () => {
          const div = L.DomUtil.create('div')
          div.style.cssText = 'background:rgba(255,255,255,0.92);padding:8px 10px;border-radius:6px;font-size:11px;line-height:1.8;box-shadow:0 1px 5px rgba(0,0,0,0.18)'
          div.innerHTML =
            '<b style="display:block;margin-bottom:2px">जनसंख्या</b>' +
            [['#1a4e8a','80–100%'],['#2169b8','60–80%'],['#4299e1','40–60%'],['#90cdf4','20–40%'],['#bee3f8','0–20%']]
              .map(([c,l]) => `<span style="display:inline-block;width:11px;height:11px;background:${c};margin-right:5px;border-radius:2px;vertical-align:middle"></span>${l}`)
              .join('<br>')
          return div
        }
        legend.addTo(leafletMap)
      }
    } catch {
      // GeoJSON unavailable — map still works without choropleth
    }
  }

  // ── Point markers ────────────────────────────────────────────────────
  const colors = { office: '#1D4ED8', school: '#7C3AED', health: '#059669', ward: '#D97706', tourism: '#DB2777', other: '#6B7280' }
  const makeIcon = type => {
    const c = colors[type] ?? '#6B7280'
    return L.divIcon({
      html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 36" width="24" height="36"><path fill="${c}" stroke="#fff" stroke-width="1.5" d="M12 0C5.4 0 0 5.4 0 12c0 9 12 24 12 24S24 21 24 12C24 5.4 18.6 0 12 0z"/><circle fill="#fff" cx="12" cy="12" r="5"/></svg>`,
      className: '', iconSize: [24, 36], iconAnchor: [12, 36], popupAnchor: [0, -36],
    })
  }

  const markers = cfg.markers ?? []
  if (!markers.length) {
    L.marker([lat, lng], { icon: makeIcon('office') }).addTo(leafletMap)
      .bindPopup(`<b>${props.lg.name_en}</b>`)
  } else {
    markers.forEach(m => L.marker([m.lat, m.lng], { icon: makeIcon(m.type ?? 'other') })
      .addTo(leafletMap).bindPopup(`<b>${m.name}</b>${m.popup ? `<br><small>${m.popup}</small>` : ''}`))
  }
  leafletMap.on('click', () => leafletMap.scrollWheelZoom.enable())
  leafletMap.on('mouseout', () => leafletMap.scrollWheelZoom.disable())
}

// ── Chart refs ───────────────────────────────────────────────────────
const genderChartEl      = ref(null)
const ageChartEl         = ref(null)
const institutionChartEl = ref(null)
const incomeChartEl      = ref(null)
const educationChartEl   = ref(null)
const occupationChartEl  = ref(null)
const litGenderChartEl   = ref(null)
const businessChartEl    = ref(null)

let genderChart = null, ageChart = null, institutionChart = null
let incomeChart = null, educationChart = null, occupationChart = null
let litGenderChart = null, businessChart = null

const chartOpts = (extra = {}) => ({
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  ...extra,
})

const hBarOpts = () => chartOpts({
  indexAxis: 'y',
  scales: {
    x: { beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { font: { size: 11 } } },
    y: { grid: { display: false }, ticks: { font: { size: 12 } } },
  },
  plugins: { legend: { display: false } },
})

function initGenderChart() {
  if (!genderChartEl.value || !Object.keys(props.genderData).length) return
  genderChart?.destroy()
  genderChart = new Chart(genderChartEl.value, {
    type: 'doughnut',
    data: {
      labels: Object.keys(props.genderData).map(k => ({ male: 'पुरुष', female: 'महिला', other: 'अन्य' }[k] ?? k)),
      datasets: [{ data: Object.values(props.genderData), backgroundColor: ['#3B82F6','#EC4899','#6B7280'], hoverOffset: 4 }],
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } },
  })
}

function initAgeChart() {
  if (!ageChartEl.value || !Object.keys(props.ageData).length) return
  ageChart?.destroy()
  ageChart = new Chart(ageChartEl.value, {
    type: 'bar',
    data: {
      labels: Object.keys(props.ageData),
      datasets: [{ data: Object.values(props.ageData), backgroundColor: '#6366F1', borderRadius: 4, borderSkipped: false }],
    },
    options: chartOpts({ scales: { y: { beginAtZero: true, grid: { color: '#f3f4f6' } }, x: { grid: { display: false } } } }),
  })
}

function initInstitutionChart() {
  if (!institutionChartEl.value || !props.institutionData.length) return
  institutionChart?.destroy()
  institutionChart = new Chart(institutionChartEl.value, {
    type: 'bar',
    data: {
      labels: props.institutionData.map(r => (locale.value === 'ne' && r.label_ne) ? r.label_ne : (r.label_en || '')),
      datasets: [{ data: props.institutionData.map(r => r.count), backgroundColor: '#10B981', borderRadius: 4, borderSkipped: false }],
    },
    options: hBarOpts(),
  })
}

function initHBar(elRef, data, color) {
  if (!elRef.value || !data?.length) return null
  return new Chart(elRef.value, {
    type: 'bar',
    data: {
      labels: data.map(r => (locale.value === 'ne' && r.label_ne) ? r.label_ne : (r.label_en || ucLabel(r.value))),
      datasets: [{ data: data.map(r => r.count), backgroundColor: color, borderRadius: 3, borderSkipped: false }],
    },
    options: hBarOpts(),
  })
}

function initLitGenderChart() {
  if (!litGenderChartEl.value || !props.litByGender?.length) return
  litGenderChart?.destroy()
  const labels = props.litByGender.map(r => ({ male: 'पुरुष', female: 'महिला', other: 'अन्य' }[r.gender] ?? r.gender))
  litGenderChart = new Chart(litGenderChartEl.value, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { label: 'साक्षर',   data: props.litByGender.map(r => r.lit),         backgroundColor: '#10B981', borderRadius: 3 },
        { label: 'निरक्षर', data: props.litByGender.map(r => r.tot - r.lit),  backgroundColor: '#F87171', borderRadius: 3 },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
      scales: { y: { beginAtZero: true, stacked: false, grid: { color: '#f3f4f6' } }, x: { grid: { display: false } } },
    },
  })
}

function initAllCharts() {
  initGenderChart(); initAgeChart(); initInstitutionChart()
  incomeChart?.destroy();    incomeChart    = initHBar(incomeChartEl,    props.incomeData,    '#7C3AED')
  educationChart?.destroy(); educationChart = initHBar(educationChartEl, props.educationData, '#0EA5E9')
  occupationChart?.destroy(); occupationChart = initHBar(occupationChartEl, props.occupationData, '#F43F5E')
  businessChart?.destroy();  businessChart  = initHBar(businessChartEl,  props.businessData,  '#F97316')
  initLitGenderChart()
  initMap()
}

watch(
  () => [props.genderData, props.ageData, props.institutionData, props.incomeData, props.educationData, props.occupationData, props.litByGender, props.businessData, locale.value],
  initAllCharts, { deep: true }
)
</script>

<!-- ─────────────────────────────────────────────────────────────────
     MiniBarChart — compact horizontal-bar card sub-component
───────────────────────────────────────────────────────────────────── -->
<script>
import { ref, onMounted, onUnmounted, watch, h } from 'vue'
import Chart from 'chart.js/auto'
import { useLocale } from '@/i18n/index.js'

export const MiniBarChart = {
  props: {
    title:      String,
    subtitle:   String,
    icon:       String,
    iconColor:  String,
    iconBg:     String,
    data:       { type: Array, default: () => [] },
    color:      { type: String, default: '#3B82F6' },
  },
  setup(props) {
    const canvasEl = ref(null)
    const { locale } = useLocale()
    let chart = null

    const resolveLabel = r => (locale.value === 'ne' && r.label_ne)
      ? r.label_ne
      : (r.label_en || (r.value ?? '').split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' '))

    function init() {
      if (!canvasEl.value || !props.data?.length) return
      chart?.destroy()
      const total = props.data.reduce((s, r) => s + r.count, 0)
      chart = new Chart(canvasEl.value, {
        type: 'bar',
        data: {
          labels: props.data.map(resolveLabel),
          datasets: [{ data: props.data.map(r => r.count), backgroundColor: props.color, borderRadius: 3, borderSkipped: false }],
        },
        options: {
          indexAxis: 'y', responsive: true, maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => {
              const pct = total > 0 ? (ctx.parsed.x / total * 100).toFixed(1) : 0
              return ` ${ctx.parsed.x.toLocaleString()} (${pct}%)`
            }}},
          },
          scales: {
            x: { beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { font: { size: 11 } } },
            y: { grid: { display: false }, ticks: { font: { size: 12 } } },
          },
        },
      })
    }

    onMounted(init)
    onUnmounted(() => chart?.destroy())
    watch(() => props.data, init, { deep: true })
    watch(() => locale.value, init)

    return () => {
      const total = (props.data ?? []).reduce((s, r) => s + r.count, 0)
      const chartH = Math.min(44 + (props.data?.length ?? 0) * 26, 200)

      return h('div', { class: 'bg-white border border-[#e6e7e9] rounded-md overflow-hidden' }, [
        h('div', { class: 'px-3 py-2.5 border-b border-[#e6e7e9] flex items-center gap-2' }, [
          h('div', { class: `w-6 h-6 rounded flex items-center justify-center shrink-0 ${props.iconBg}` }, [
            h('i', { class: `${props.icon} ${props.iconColor} text-[10px]` }),
          ]),
          h('div', {}, [
            h('p', { class: 'text-[12px] font-semibold text-[#1d273b] leading-tight' }, props.title),
            h('p', { class: 'text-[10px] text-[#9aa0ac]' }, props.subtitle),
          ]),
        ]),
        h('div', { class: 'px-3 pb-2' }, [
          h('div', { style: `height:${chartH}px; padding-top:6px` }, [
            h('canvas', { ref: canvasEl }),
          ]),
          // Percentage list
          h('div', { class: 'mt-1 space-y-0.5' },
            (props.data ?? []).slice(0, 4).map(r => {
              const pct = total > 0 ? (r.count / total * 100).toFixed(1) : '0.0'
              return h('div', { key: r.value, class: 'flex items-center gap-1.5 text-[10px]' }, [
                h('div', { class: 'h-1 rounded-full flex-1 bg-gray-100 overflow-hidden' }, [
                  h('div', { style: `width:${pct}%; background:${props.color}`, class: 'h-full rounded-full' }),
                ]),
                h('span', { class: 'text-[#9aa0ac] shrink-0 w-8 text-right tabular-nums' }, `${pct}%`),
              ])
            })
          ),
        ]),
      ])
    }
  },
}
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

.banner-slide-enter-active,
.banner-slide-leave-active { transition: all 0.35s ease; overflow: hidden; }
.banner-slide-enter-from,
.banner-slide-leave-to    { opacity: 0; max-height: 0; margin-bottom: 0; }
.banner-slide-enter-to,
.banner-slide-leave-from  { opacity: 1; max-height: 800px; }
</style>
