<template>
  <div class="space-y-4">
    <!-- Page header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <h1 class="text-[15px] font-semibold text-[#1d273b] flex items-center gap-2">
          <i class="fa-solid fa-house text-[#206bc4]"></i> घरधुरी सूची
        </h1>
        <p class="text-[11px] text-[#9aa0ac] mt-0.5">
          जम्मा
          <span class="font-semibold text-[#626976]">{{ households.meta?.total ?? 0 }}</span>
          घरधुरी
        </p>
      </div>
      <a
        href="/households/create"
        class="inline-flex items-center gap-2 h-9 px-4 bg-[#206bc4] hover:bg-[#1a5ba8] text-white rounded text-[13px] font-medium transition"
      >
        <i class="fa-solid fa-plus"></i> नयाँ घरधुरी
      </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-md border border-[#e6e7e9] p-4">
      <div class="flex flex-col sm:flex-row gap-3">
        <!-- Search -->
        <div class="relative flex-1">
          <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[#9aa0ac] text-sm pointer-events-none"></i>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="नाम, कोड वा सम्पर्क खोज्नुस्..."
            class="w-full h-9 rounded border border-[#e6e7e9] pl-9 pr-3 text-[13px] text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] outline-none transition"
          />
        </div>

        <!-- Ward filter -->
        <select
          v-model="wardFilter"
          @change="applyFilters"
          class="h-9 rounded border border-[#e6e7e9] px-3 text-[13px] text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] outline-none transition bg-white min-w-[140px]"
        >
          <option value="">सबै वडा</option>
          <option v-for="w in wards" :key="w.id" :value="w.id">वडा {{ w.ward_number }}</option>
        </select>

        <!-- Status filter -->
        <select
          v-model="statusFilter"
          @change="applyFilters"
          class="h-9 rounded border border-[#e6e7e9] px-3 text-[13px] text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] outline-none transition bg-white min-w-[140px]"
        >
          <option value="">सबै अवस्था</option>
          <option value="draft">मस्यौदा</option>
          <option value="submitted">पेस भएको</option>
          <option value="ward_approved">वडा अनुमोदित</option>
          <option value="published">प्रकाशित</option>
          <option value="rejected">अस्वीकृत</option>
        </select>

        <!-- Clear -->
        <button
          v-if="hasActiveFilters"
          @click="clearFilters"
          class="h-9 px-4 border border-[#e6e7e9] text-[#626976] rounded text-[13px] hover:bg-gray-50 transition flex items-center gap-1.5"
        >
          <i class="fa-solid fa-xmark"></i> खाली गर्नुस्
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="border-b border-[#e6e7e9]">
            <tr>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]">कोड</th>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]">उत्तरदाता</th>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]">वडा</th>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]">बस्ती</th>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]">मिति</th>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]">अवस्था</th>
              <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]">गणक</th>
              <th class="px-4 py-2.5 text-right text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.08em]">कार्यहरू</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#e6e7e9]">
            <tr
              v-for="hh in households.data"
              :key="hh.id"
              class="hover:bg-gray-50 transition-colors"
            >
              <td class="px-4 py-2.5">
                <span class="font-mono text-[11px] bg-[#206bc4]/8 text-[#206bc4] px-2 py-0.5 rounded">{{ hh.unique_code }}</span>
              </td>
              <td class="px-4 py-2.5">
                <div class="text-[13px] font-medium text-[#1d273b]">{{ hh.respondent_name || '—' }}</div>
                <div v-if="hh.respondent_contact" class="text-[11px] text-[#9aa0ac]">{{ hh.respondent_contact }}</div>
              </td>
              <td class="px-4 py-2.5">
                <span v-if="hh.ward" class="bg-gray-100 text-[#626976] text-[11px] px-2 py-0.5 rounded">
                  वडा {{ hh.ward.ward_number }}
                </span>
              </td>
              <td class="px-4 py-2.5 text-[13px] text-[#626976]">{{ hh.settlement?.name_np || '—' }}</td>
              <td class="px-4 py-2.5 text-[13px] text-[#9aa0ac] whitespace-nowrap">{{ formatDate(hh.survey_date) }}</td>
              <td class="px-4 py-2.5">
                <span :class="['text-[11px] px-2.5 py-1 rounded font-medium', statusBadge(hh.status)]">
                  {{ statusLabel(hh.status) }}
                </span>
              </td>
              <td class="px-4 py-2.5 text-[13px] text-[#9aa0ac]">{{ hh.enumerator?.name || '—' }}</td>
              <td class="px-4 py-2.5">
                <div class="flex items-center justify-end gap-2">
                  <!-- Quick View -->
                  <button
                    @click="openQuickView(hh)"
                    class="inline-flex items-center gap-1 text-[13px] text-[#206bc4] hover:text-[#1a5ba8] font-medium px-2 py-1 rounded hover:bg-indigo-50 transition-colors"
                    title="छिटो हेर्नुस्"
                  >
                    <i class="fa-solid fa-eye"></i>
                  </button>

                  <!-- Edit -->
                  <a
                    v-if="hh.can_edit"
                    :href="`/households/${hh.id}/edit`"
                    class="inline-flex items-center gap-1 text-[13px] text-[#626976] hover:text-[#1d273b] font-medium px-2 py-1 rounded hover:bg-gray-100 transition-colors"
                  >
                    <i class="fa-solid fa-pen"></i>
                  </a>

                  <!-- Submit -->
                  <button
                    v-if="hh.can_submit"
                    @click="submitHousehold(hh.id)"
                    class="inline-flex items-center gap-1 text-[13px] text-white bg-[#206bc4] hover:bg-[#1a5ba8] font-medium px-2.5 py-1 rounded transition-colors"
                    :disabled="submitting === hh.id"
                  >
                    <i class="fa-solid fa-paper-plane"></i>
                    <span v-if="submitting !== hh.id">पेस</span>
                    <span v-else><i class="fa-solid fa-spinner fa-spin"></i></span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Empty state -->
      <div v-if="!households.data?.length" class="py-16 text-center">
        <i class="fa-solid fa-house-circle-xmark text-5xl text-gray-200 mb-4 block"></i>
        <p class="text-[13px] text-[#626976] font-medium">कुनै घरधुरी फेला परेन</p>
        <p class="text-[11px] text-[#9aa0ac] mt-1">फिल्टर परिवर्तन गर्नुस् वा नयाँ घरधुरी थप्नुस्</p>
        <a
          href="/households/create"
          class="mt-4 inline-flex items-center gap-2 h-9 px-4 bg-[#206bc4] text-white rounded text-[13px] font-medium hover:bg-[#1a5ba8] transition"
        >
          <i class="fa-solid fa-plus"></i> नयाँ घरधुरी थप्नुस्
        </a>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="households.meta && households.meta.last_page > 1" class="flex items-center justify-between">
      <p class="text-[13px] text-[#626976]">
        देखाइएको
        <span class="font-semibold">{{ households.meta.from }}</span>–<span class="font-semibold">{{ households.meta.to }}</span>
        / <span class="font-semibold">{{ households.meta.total }}</span>
      </p>
      <div class="flex items-center gap-1">
        <!-- Prev -->
        <button
          :disabled="households.meta.current_page <= 1"
          @click="goToPage(households.meta.current_page - 1)"
          class="rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] text-[#626976] hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
        >
          <i class="fa-solid fa-chevron-left"></i>
        </button>

        <!-- Page numbers (up to 7 visible) -->
        <template v-for="page in visiblePages" :key="page">
          <span v-if="page === '...'" class="px-2 text-[#9aa0ac] text-[13px]">…</span>
          <button
            v-else
            @click="goToPage(page)"
            :class="[
              'rounded border px-3 py-1.5 text-[13px] transition',
              page === households.meta.current_page
                ? 'bg-[#206bc4] border-[#206bc4] text-white'
                : 'border-[#e6e7e9] text-[#626976] hover:bg-gray-50'
            ]"
          >{{ page }}</button>
        </template>

        <!-- Next -->
        <button
          :disabled="households.meta.current_page >= households.meta.last_page"
          @click="goToPage(households.meta.current_page + 1)"
          class="rounded border border-[#e6e7e9] px-3 py-1.5 text-[13px] text-[#626976] hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
        >
          <i class="fa-solid fa-chevron-right"></i>
        </button>
      </div>
    </div>

  </div>

  <!-- ── Quick-View Slide-Over ──────────────────────────────────────────────── -->
  <Teleport to="body">
    <!-- Backdrop -->
    <Transition name="qv-backdrop">
      <div
        v-if="quickViewOpen"
        class="fixed inset-0 bg-black/30 z-40"
        @click="closeQuickView"
      ></div>
    </Transition>

    <!-- Panel -->
    <Transition name="qv-panel">
      <div
        v-if="quickViewOpen"
        class="fixed top-0 right-0 h-full w-full max-w-[680px] bg-gray-50 z-50 shadow-xl flex flex-col overflow-hidden"
      >
        <!-- Header -->
        <div class="bg-white border-b border-[#e6e7e9] px-5 py-3 flex items-center gap-3 shrink-0">
          <div class="flex items-center gap-2 flex-wrap min-w-0 flex-1">
            <i class="fa-solid fa-house text-[#206bc4]"></i>
            <span class="text-[14px] font-semibold text-[#1d273b]">घरधुरी विवरण</span>
            <span v-if="quickViewHh" class="font-mono text-[11px] bg-[#206bc4]/8 text-[#206bc4] px-2 py-0.5 rounded">
              {{ quickViewHh.unique_code }}
            </span>
            <span v-if="quickViewHh" :class="['text-[11px] px-2 py-0.5 rounded font-medium', statusBadge(quickViewHh.status)]">
              {{ statusLabel(quickViewHh.status) }}
            </span>
          </div>
          <button
            @click="closeQuickView"
            class="w-8 h-8 rounded border border-[#e6e7e9] flex items-center justify-center text-[#626976] hover:bg-gray-50 transition-colors shrink-0"
          >
            <i class="fa-solid fa-xmark text-[13px]"></i>
          </button>
        </div>

        <!-- Loading -->
        <div v-if="quickViewLoading" class="flex-1 flex items-center justify-center">
          <div class="text-center text-[#9aa0ac]">
            <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 block text-[#206bc4]"></i>
            <p class="text-[13px]">लोड हुँदैछ...</p>
          </div>
        </div>

        <!-- Content -->
        <div v-else-if="quickViewData" class="flex-1 overflow-y-auto p-4 space-y-4">

          <!-- Action buttons -->
          <div class="bg-white rounded-md border border-[#e6e7e9] p-3 flex flex-wrap gap-2">
            <a
              :href="`/households/${quickViewData.household.id}`"
              class="inline-flex items-center gap-1.5 h-8 px-3 bg-[#206bc4] hover:bg-[#1a5ba8] text-white rounded text-[12px] font-medium transition"
            >
              <i class="fa-solid fa-up-right-from-square"></i> पूरा विवरण
            </a>
            <a
              v-if="quickViewData.canEdit"
              :href="`/households/${quickViewData.household.id}/edit`"
              class="inline-flex items-center gap-1.5 h-8 px-3 border border-[#e6e7e9] text-[#626976] hover:bg-gray-50 rounded text-[12px] font-medium transition"
            >
              <i class="fa-solid fa-pen"></i> सम्पादन
            </a>
            <button
              v-if="quickViewData.canSubmit"
              @click="submitFromDrawer(quickViewData.household.id)"
              :disabled="submitting === quickViewData.household.id"
              class="inline-flex items-center gap-1.5 h-8 px-3 bg-amber-500 hover:bg-amber-600 text-white rounded text-[12px] font-medium transition disabled:opacity-60"
            >
              <i :class="submitting === quickViewData.household.id ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-paper-plane'"></i>
              पेस गर्नुस्
            </button>
          </div>

          <!-- 1. मूल विवरण -->
          <section class="bg-white rounded-md border border-[#e6e7e9] p-4">
            <QvSectionHead icon="fa-circle-info" color="indigo" title="मूल विवरण" />
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <QvRow label="उत्तरदाताको नाम" :value="quickViewData.household.respondent_name" />
              <QvRow label="सम्पर्क" :value="quickViewData.household.respondent_contact" />
              <QvRow label="वडा नं." :value="quickViewData.household.ward ? `वडा ${quickViewData.household.ward.ward_number}` : null" />
              <QvRow label="बस्ती" :value="quickViewData.household.settlement?.name_np" />
              <QvRow label="गणक" :value="quickViewData.household.enumerator?.name" />
              <QvRow label="सर्वेक्षण मिति" :value="formatDate(quickViewData.household.survey_date)" />
              <QvRow v-if="quickViewData.household.gps_lat" label="GPS (Lat)" :value="String(quickViewData.household.gps_lat)" />
              <QvRow v-if="quickViewData.household.gps_lng" label="GPS (Lng)" :value="String(quickViewData.household.gps_lng)" />
            </div>
            <div v-if="quickViewData.household.remarks" class="mt-3 p-2.5 bg-yellow-50 rounded border border-yellow-100">
              <p class="text-[10px] text-yellow-600 font-medium mb-1"><i class="fa-solid fa-note-sticky mr-1"></i>कैफियत</p>
              <p class="text-[12px] text-gray-700">{{ quickViewData.household.remarks }}</p>
            </div>
          </section>

          <!-- 2. आवास विवरण -->
          <section v-if="quickViewData.household.housing" class="bg-white rounded-md border border-[#e6e7e9] p-4">
            <QvSectionHead icon="fa-house" color="orange" title="आवास विवरण" />
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <QvRow label="घरको प्रकार" :value="qvLabel('house_type', quickViewData.household.housing.house_type)" />
              <QvRow label="छानाको प्रकार" :value="qvLabel('roof_type', quickViewData.household.housing.roof_type)" />
              <QvRow label="भित्ताको प्रकार" :value="qvLabel('wall_type', quickViewData.household.housing.wall_type)" />
              <QvRow label="जग प्रकार" :value="qvLabel('foundation_type', quickViewData.household.housing.foundation_type)" />
              <QvRow label="स्वामित्व" :value="qvLabel('ownership_type', quickViewData.household.housing.ownership_type)" />
              <QvRow label="घरको अवस्था" :value="qvLabel('house_condition', quickViewData.household.housing.house_condition)" />
              <QvRow label="कोठा संख्या" :value="qvStr(quickViewData.household.housing.total_rooms)" />
              <QvRow label="तल्ला संख्या" :value="qvStr(quickViewData.household.housing.total_floors)" />
              <QvRow label="जग्गाको प्रकार" :value="qvLabel('house_land_type', quickViewData.household.housing.house_land_type)" />
              <QvRow label="घरको उद्देश्य" :value="qvLabel('house_purpose', quickViewData.household.housing.house_purpose)" />
              <QvRow label="निर्माण वर्ष" :value="qvStr(quickViewData.household.housing.construction_year)" />
              <QvRow label="जोखिम क्षेत्र" :value="qvYesNo(quickViewData.household.housing.is_at_risk)" />
              <QvRow v-if="quickViewData.household.housing.risk_types?.length" label="जोखिमका किसिम" :value="qvLabelArr('risk_types', quickViewData.household.housing.risk_types)" class="col-span-2" />
            </div>
          </section>

          <!-- 3. परिवार तथा सामाजिक -->
          <section v-if="quickViewData.household.survey_data?.social" class="bg-white rounded-md border border-[#e6e7e9] p-4">
            <QvSectionHead icon="fa-people-roof" color="violet" title="परिवार तथा सामाजिक" />
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <QvRow label="घरमुलीको नाम" :value="quickViewData.household.survey_data.social.family_head" />
              <QvRow label="घरमुलीको सम्पर्क" :value="quickViewData.household.survey_data.social.contact_no_fam_head" />
              <QvRow label="पुरुष संख्या" :value="qvStr(quickViewData.household.survey_data.social.male_num)" />
              <QvRow label="महिला संख्या" :value="qvStr(quickViewData.household.survey_data.social.female_num)" />
              <QvRow label="अन्य संख्या" :value="qvStr(quickViewData.household.survey_data.social.other_num)" />
              <QvRow label="परिवार प्रकार" :value="qvLabel('family_type', quickViewData.household.survey_data.social.family_type)" />
              <QvRow label="जातजाति" :value="qvLabel('ethnicity', quickViewData.household.survey_data.social.ethnicity)" />
              <QvRow label="धर्म" :value="qvLabel('religion', quickViewData.household.survey_data.social.religion)" />
              <QvRow label="मातृभाषा" :value="qvLabel('mother_tongue', quickViewData.household.survey_data.social.mother_tongue)" />
              <QvRow label="सञ्चार भाषा" :value="qvLabel('mother_tongue', quickViewData.household.survey_data.social.com_language)" />
              <QvRow label="मुख्य पेशा" :value="qvLabel('occupation_type', quickViewData.household.survey_data.social.major_family_occupation)" />
              <QvRow label="बसोबास प्रकार" :value="qvLabel('settlement_type', quickViewData.household.survey_data.social.settlement_type)" />
              <QvRow label="जमिन छ?" :value="qvYesNo(quickViewData.household.survey_data.social.have_land)" />
              <template v-if="quickViewData.household.survey_data.social.have_land === 'yes'">
                <QvRow label="जग्गाको एकाइ" :value="qvLabel('land_unit', quickViewData.household.survey_data.social.land_unit)" />
                <QvRow label="खेत" :value="qvStr(quickViewData.household.survey_data.social.land_khet)" />
                <QvRow label="बारी" :value="qvStr(quickViewData.household.survey_data.social.land_bari)" />
                <QvRow label="पाखो" :value="qvStr(quickViewData.household.survey_data.social.land_pakho)" />
              </template>
              <QvRow label="ऋण लिएको?" :value="qvYesNo(quickViewData.household.survey_data.social.bankloan)" />
              <QvRow label="स्वास्थ्य बिमा" :value="qvYesNo(quickViewData.household.survey_data.social.health_insurance)" />
              <QvRow label="आप्रवासन" :value="qvYesNo(quickViewData.household.survey_data.social.migration_last_5years)" />
              <QvRow label="मृत्यु (यो वर्ष)" :value="qvYesNo(quickViewData.household.survey_data.social.death)" />
            </div>
          </section>

          <!-- 4. सरसफाई तथा सुविधा -->
          <section v-if="quickViewData.household.utilities" class="bg-white rounded-md border border-[#e6e7e9] p-4">
            <QvSectionHead icon="fa-faucet" color="blue" title="सरसफाई तथा सुविधा" />
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <QvRow label="पिउने पानीको स्रोत" :value="qvLabel('water_source', quickViewData.household.utilities.water_source)" />
              <QvRow label="पानी दूरी (मि.)" :value="qvStr(quickViewData.household.utilities.water_distance_minutes)" />
              <QvRow label="शौचालय छ?" :value="qvBool(quickViewData.household.utilities.has_toilet)" />
              <QvRow label="शौचालयको प्रकार" :value="qvLabel('toilet_type', quickViewData.household.utilities.toilet_type)" />
              <QvRow label="सरसफाई प्रकार" :value="qvLabel('sanitation_type', quickViewData.household.utilities.sanitation_type)" />
              <QvRow label="बिजुलीको स्रोत" :value="qvLabel('electricity_source', quickViewData.household.utilities.electricity_source)" />
              <QvRow label="मासिक एकाइ (kWh)" :value="qvStr(quickViewData.household.utilities.monthly_electricity_units)" />
              <QvRow label="वैकल्पिक ऊर्जा" :value="qvYesNo(quickViewData.household.utilities.has_alt_electricity)" />
              <QvRow label="खाना पकाउने इन्धन" :value="qvLabelArr('cooking_fuel', quickViewData.household.utilities.cooking_fuel_arr)" />
              <QvRow label="चुलोको प्रकार" :value="qvLabel('cooking_stove', quickViewData.household.utilities.cooking_stove)" />
              <QvRow label="दाउरा प्रयोग?" :value="qvYesNo(quickViewData.household.utilities.uses_firewood)" />
              <QvRow label="बायोग्यास छ?" :value="qvBool(quickViewData.household.utilities.has_biogas)" />
              <QvRow label="फोहोर व्यवस्थापन" :value="qvLabel('solid_waste_management', quickViewData.household.utilities.solid_waste_management)" />
              <QvRow label="इन्टरनेट छ?" :value="qvBool(quickViewData.household.utilities.has_internet)" />
              <QvRow label="इन्टरनेट प्रकार" :value="qvLabel('internet_type', quickViewData.household.utilities.internet_type)" />
              <QvRow label="हात धुने सुविधा" :value="qvLabel('handwashing', quickViewData.household.utilities.handwashing)" />
            </div>
          </section>

          <!-- 5. स्वास्थ्य तथा सुखाकार -->
          <section v-if="quickViewData.household.survey_data?.health" class="bg-white rounded-md border border-[#e6e7e9] p-4">
            <QvSectionHead icon="fa-heart-pulse" color="rose" title="स्वास्थ्य तथा सुखाकार" />
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <QvRow label="स्वास्थ्य बिमा" :value="qvYesNo(quickViewData.household.survey_data.health.has_health_insurance)" />
              <QvRow label="बिरामी आवृत्ति" :value="qvLabel('illness_frequency', quickViewData.household.survey_data.health.illness_frequency)" />
              <QvRow label="उपचार स्थान" :value="qvLabel('treatment_place', quickViewData.household.survey_data.health.treatment_place)" />
              <QvRow label="स्वास्थ्य केन्द्र दूरी" :value="qvLabel('time_to_health_center', quickViewData.household.survey_data.health.time_to_health_center)" />
              <QvRow label="गर्भवती महिला?" :value="qvYesNo(quickViewData.household.survey_data.health.has_pregnant_woman)" />
              <QvRow label="हात धुने जानकारी" :value="qvYesNo(quickViewData.household.survey_data.health.aware_handwashing)" />
              <QvRow label="पोषण जानकारी" :value="qvYesNo(quickViewData.household.survey_data.health.aware_nutrition)" />
            </div>
            <template v-if="quickViewData.household.survey_data.wellbeing">
              <div class="border-t border-gray-100 mt-3 pt-3">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-2">सुखाकार</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                  <QvRow label="बसोबासमा खुशी?" :value="qvYesNo(quickViewData.household.survey_data.wellbeing.happy_with_location)" />
                  <QvRow label="छिमेकी मिलन" :value="qvLabel('neighbor_cooperation', quickViewData.household.survey_data.wellbeing.neighbor_cooperation)" />
                  <QvRow label="पारिवारिक भेलाघर" :value="qvLabel('family_gathering_freq', quickViewData.household.survey_data.wellbeing.family_gathering_freq)" />
                </div>
              </div>
            </template>
          </section>

          <!-- 6. यातायात तथा संस्था -->
          <section v-if="quickViewData.household.utilities || quickViewData.household.survey_data?.decisions" class="bg-white rounded-md border border-[#e6e7e9] p-4">
            <QvSectionHead icon="fa-car" color="cyan" title="यातायात तथा संस्था" />
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <template v-if="quickViewData.household.utilities">
                <QvRow label="सवारी साधन" :value="qvLabel('vehicle', quickViewData.household.utilities.vehicle)" />
                <QvRow v-if="quickViewData.household.utilities.vehicles?.length" label="सवारी विवरण" :value="quickViewData.household.utilities.vehicles.length + ' प्रकार'" />
              </template>
              <template v-if="quickViewData.household.survey_data?.decisions">
                <QvRow label="घरायसी काम निर्णय" :value="qvLabel('decision_owner', quickViewData.household.survey_data.decisions.decisioninHHWork)" />
                <QvRow label="सम्पत्ति निर्णय" :value="qvLabel('decision_owner', quickViewData.household.survey_data.decisions.decisioninProperty)" />
                <QvRow label="समिति सहभागिता" :value="qvLabel('decision_owner', quickViewData.household.survey_data.decisions.decisioninCCommittee)" />
                <QvRow label="SMC सहभागिता" :value="qvLabel('decision_owner', quickViewData.household.survey_data.decisions.decisioninSMC)" />
                <QvRow label="व्यापार निर्णय" :value="qvLabel('decision_owner', quickViewData.household.survey_data.decisions.decisioninBusiness)" />
                <QvRow label="संस्थामा सहभागी?" :value="qvYesNo(quickViewData.household.survey_data.decisions.org_participation)" />
                <QvRow label="बैंक खाता छ?" :value="qvYesNo(quickViewData.household.survey_data.decisions.has_bank_account)" />
                <QvRow label="खाता धनी" :value="qvLabel('bank_account_owner', quickViewData.household.survey_data.decisions.bank_account_owner)" />
              </template>
            </div>
          </section>

          <!-- 7. व्यवसाय तथा रोजगार -->
          <section v-if="quickViewData.household.survey_data?.occupation" class="bg-white rounded-md border border-[#e6e7e9] p-4">
            <QvSectionHead icon="fa-briefcase" color="amber" title="व्यवसाय तथा रोजगार" />
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <QvRow label="भारत विदेश" :value="qvStr(quickViewData.household.survey_data.occupation.foreign_india)" />
              <QvRow label="खाडी विदेश" :value="qvStr(quickViewData.household.survey_data.occupation.foreign_gulf)" />
              <QvRow label="अन्य विदेश" :value="qvStr(quickViewData.household.survey_data.occupation.foreign_others)" />
              <QvRow label="सीप तालिम" :value="qvYesNo(quickViewData.household.survey_data.occupation.skill_training)" />
              <QvRow v-if="quickViewData.household.survey_data.occupation.occupation_rows?.length" label="व्यवसाय विवरण" :value="quickViewData.household.survey_data.occupation.occupation_rows.length + ' पङ्क्ति'" />
            </div>
          </section>

          <!-- 8. खेतीपाती -->
          <section v-if="quickViewData.household.agriculture" class="bg-white rounded-md border border-[#e6e7e9] p-4">
            <QvSectionHead icon="fa-seedling" color="green" title="खेतीपाती" />
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <QvRow label="खेती गर्नुहुन्छ?" :value="qvYesNo(quickViewData.household.agriculture.agriculture)" />
              <QvRow v-if="quickViewData.household.agriculture.agri_categories?.length" label="खेती प्रकार" :value="qvLabelArr('agri_categories', quickViewData.household.agriculture.agri_categories)" />
              <QvRow label="जमिन छ?" :value="qvBool(quickViewData.household.agriculture.owns_land)" />
              <QvRow label="जमिन क्षेत्र (रोपनी)" :value="qvStr(quickViewData.household.agriculture.land_area_ropani)" />
              <QvRow label="सिञ्चित क्षेत्र" :value="qvStr(quickViewData.household.agriculture.irrigated_area_ropani)" />
              <QvRow label="वार्षिक कृषि आम्दानी" :value="qvStr(quickViewData.household.agriculture.annual_agri_income)" />
              <QvRow label="रासायनिक मल?" :value="qvYesNo(quickViewData.household.agriculture.use_of_chem_fertilizer)" />
              <template v-if="quickViewData.household.agriculture.use_of_chem_fertilizer === 'yes'">
                <QvRow label="मल परिमाण (के.जी.)" :value="qvStr(quickViewData.household.agriculture.chem_fertilizer_kg)" />
                <QvRow v-if="quickViewData.household.agriculture.chem_fertilizer_types?.length" label="मलको प्रकार" :value="qvLabelArr('chem_fertilizer_types', quickViewData.household.agriculture.chem_fertilizer_types)" />
                <QvRow v-if="quickViewData.household.agriculture.fertilizer_sources?.length" label="मलको स्रोत" :value="qvLabelArr('fertilizer_sources', quickViewData.household.agriculture.fertilizer_sources)" />
              </template>
              <QvRow label="रासायनिक कीटनाशक?" :value="qvYesNo(quickViewData.household.agriculture.use_of_chem_pesticide)" />
              <QvRow v-if="quickViewData.household.agriculture.Farm_Challenges?.length" label="चुनौतीहरू" :value="qvLabelArr('Farm_Challenges', quickViewData.household.agriculture.Farm_Challenges)" />
            </div>
          </section>

          <!-- 9. कृषि उत्पादन -->
          <section v-if="qvHasCropData()" class="bg-white rounded-md border border-[#e6e7e9] p-4">
            <QvSectionHead icon="fa-wheat-awn" color="yellow" title="कृषि उत्पादन" />
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <QvRow label="खाद्यान्न बाली" :value="qvCropCount('food_crops')" />
              <QvRow label="दलहन बाली" :value="qvCropCount('pulses')" />
              <QvRow label="तेलहन बाली" :value="qvCropCount('oil_seeds')" />
              <QvRow label="तरकारी" :value="qvCropCount('vegetables')" />
              <QvRow label="नगदे बाली" :value="qvCropCount('cash_crops')" />
              <QvRow label="फलफूल" :value="qvCropCount('fruits')" />
              <QvRow label="अन्य बाली" :value="qvCropCount('other_crops')" />
            </div>
          </section>

          <!-- 10. पशुपालन -->
          <section v-if="quickViewData.household.livestock?.length || quickViewData.household.survey_data?.livestockDetail" class="bg-white rounded-md border border-[#e6e7e9] p-4">
            <QvSectionHead icon="fa-cow" color="teal" title="पशुपालन" />
            <template v-if="quickViewData.household.survey_data?.livestockDetail">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-3">
                <QvRow label="पशुपालन गर्नुहुन्छ?" :value="qvYesNo(quickViewData.household.survey_data.livestockDetail.has_livestock)" />
                <QvRow label="AI सेवा" :value="qvYesNo(quickViewData.household.survey_data.livestockDetail.Ai_in_livestock)" />
              </div>
            </template>
            <div v-if="quickViewData.household.livestock?.length" class="overflow-x-auto">
              <table class="w-full text-[11px]">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="text-left px-2 py-1.5 text-gray-500 font-semibold">पशु/पक्षी</th>
                    <th class="text-right px-2 py-1.5 text-gray-500 font-semibold">जम्मा</th>
                    <th class="text-right px-2 py-1.5 text-gray-500 font-semibold">नर</th>
                    <th class="text-right px-2 py-1.5 text-gray-500 font-semibold">मादा</th>
                    <th class="text-left px-2 py-1.5 text-gray-500 font-semibold">उत्पादन</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#e6e7e9]">
                  <tr v-for="(lv, i) in quickViewData.household.livestock" :key="i" class="hover:bg-gray-50">
                    <td class="px-2 py-1.5 font-medium text-gray-800">{{ qvLabel('livestock_type', lv.livestock_type) || lv.livestock_type }}</td>
                    <td class="px-2 py-1.5 text-right text-gray-600">{{ lv.total_count ?? '—' }}</td>
                    <td class="px-2 py-1.5 text-right text-gray-600">{{ lv.male_count ?? '—' }}</td>
                    <td class="px-2 py-1.5 text-right text-gray-600">{{ lv.female_count ?? '—' }}</td>
                    <td class="px-2 py-1.5 text-gray-600">{{ lv.production_amount ? lv.production_amount + (lv.production_unit ? ' ' + lv.production_unit : '') : '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <!-- 11. मौरी/माछा/रेशम -->
          <section v-if="quickViewData.household.survey_data?.beeFishSilk?.has_bee_fish_silk === 'yes'" class="bg-white rounded-md border border-[#e6e7e9] p-4">
            <QvSectionHead icon="fa-egg" color="amber" title="मौरी / माछा / रेशम" />
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <QvRow v-if="quickViewData.household.survey_data.beeFishSilk.bee_fish_silk_select?.length" label="प्रकार" :value="quickViewData.household.survey_data.beeFishSilk.bee_fish_silk_select.join(', ')" />
              <QvRow label="मुढे घार" :value="qvStr(quickViewData.household.survey_data.beeFishSilk.Mudhe_Hive)" />
              <QvRow label="आधुनिक घार" :value="qvStr(quickViewData.household.survey_data.beeFishSilk.Modern_Hive)" />
              <QvRow label="मह उत्पादन (मुढे)" :value="qvStr(quickViewData.household.survey_data.beeFishSilk.Honey_Production_Mudhe_Hive)" />
              <QvRow label="मह उत्पादन (आधुनिक)" :value="qvStr(quickViewData.household.survey_data.beeFishSilk.Honey_Production_Modern_Hive)" />
              <QvRow label="मौरी तालिम" :value="qvYesNo(quickViewData.household.survey_data.beeFishSilk.Bee_Training)" />
              <QvRow label="पोखरी क्षेत्र" :value="qvStr(quickViewData.household.survey_data.beeFishSilk.Pond_area)" />
              <QvRow label="माछा उत्पादन" :value="qvStr(quickViewData.household.survey_data.beeFishSilk.fish_prod)" />
              <QvRow label="माछा बिक्री" :value="qvStr(quickViewData.household.survey_data.beeFishSilk.fish_sales)" />
              <QvRow label="रेशम (Kg)" :value="qvStr(quickViewData.household.survey_data.beeFishSilk.Sik)" />
              <QvRow label="रेशम उत्पादन" :value="qvStr(quickViewData.household.survey_data.beeFishSilk.silk_prod)" />
              <QvRow label="रेशम बिक्री" :value="qvStr(quickViewData.household.survey_data.beeFishSilk.silk_sales)" />
            </div>
          </section>

          <!-- 12. आम्दानी तथा खर्च -->
          <section v-if="quickViewData.household.economic" class="bg-white rounded-md border border-[#e6e7e9] p-4">
            <QvSectionHead icon="fa-coins" color="green" title="आम्दानी तथा खर्च" />
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <QvRow label="मुख्य आयस्रोत" :value="qvLabel('income_source', quickViewData.household.economic.primary_income_source)" />
              <QvRow label="वार्षिक आम्दानी" :value="quickViewData.household.economic.annual_income_range" />
              <QvRow label="वार्षिक खर्च" :value="quickViewData.household.economic.annual_expenditure_range" />
              <QvRow label="रेमिट्यान्स" :value="qvStr(quickViewData.household.economic.remittance_amount)" />
              <QvRow label="ऋण रकम" :value="qvStr(quickViewData.household.economic.total_loan_amount)" />
              <QvRow label="बैंक खाता" :value="qvBool(quickViewData.household.economic.has_bank_account)" />
              <QvRow label="बिमा" :value="qvBool(quickViewData.household.economic.has_insurance)" />
              <QvRow label="गरिबी कार्ड" :value="qvBool(quickViewData.household.economic.poverty_card)" />
              <QvRow label="सामाजिक सुरक्षा" :value="qvBool(quickViewData.household.economic.social_security_allowance)" />
              <QvRow label="कृषि/पशु आम्दानी" :value="qvStr(quickViewData.household.economic.agri_livestock_income)" />
              <QvRow label="व्यापार आम्दानी" :value="qvStr(quickViewData.household.economic.business_industry_income)" />
              <QvRow label="रोजगार आम्दानी" :value="qvStr(quickViewData.household.economic.employment_service_income)" />
              <QvRow label="विदेश आम्दानी" :value="qvStr(quickViewData.household.economic.abroad_job_income)" />
              <QvRow label="खाना खर्च" :value="qvStr(quickViewData.household.economic.food_expense)" />
              <QvRow label="शिक्षा खर्च" :value="qvStr(quickViewData.household.economic.education_expense)" />
              <QvRow label="उपचार खर्च" :value="qvStr(quickViewData.household.economic.treatment_expense)" />
            </div>
          </section>

          <!-- 13. प्राकृतिक प्रकोप -->
          <section v-if="quickViewData.household.disaster" class="bg-white rounded-md border border-[#e6e7e9] p-4">
            <QvSectionHead icon="fa-triangle-exclamation" color="red" title="प्राकृतिक प्रकोप" />
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <QvRow v-if="quickViewData.household.disaster.risk_types?.length" label="जोखिमका किसिम" :value="qvLabelArr('disaster_risk_type', quickViewData.household.disaster.risk_types)" />
              <QvRow label="जोखिम न्यूनीकरण योजना" :value="qvLabel('risk_reduction_plan', quickViewData.household.disaster.risk_reduction_plan)" />
              <QvRow label="पछिल्लो ३ वर्षमा प्रभावित?" :value="qvYesNo(quickViewData.household.disaster.affected_last_3years)" />
              <QvRow label="यो वर्ष मृत्यु?" :value="qvYesNo(quickViewData.household.disaster.has_death_this_year)" />
              <QvRow v-if="quickViewData.household.disaster.death_rows?.length" label="मृत्यु संख्या" :value="String(quickViewData.household.disaster.death_rows.length)" />
              <QvRow v-if="quickViewData.household.disaster.human_loss_rows?.length" label="मानवीय क्षति" :value="String(quickViewData.household.disaster.human_loss_rows.length) + ' रेकर्ड'" />
              <QvRow v-if="quickViewData.household.disaster.physical_loss_rows?.length" label="भौतिक क्षति" :value="String(quickViewData.household.disaster.physical_loss_rows.length) + ' रेकर्ड'" />
              <QvRow label="राहत प्राप्त?" :value="qvYesNo(quickViewData.household.disaster.received_relief)" />
              <QvRow v-if="quickViewData.household.disaster.relief_rows?.length" label="राहत विवरण" :value="String(quickViewData.household.disaster.relief_rows.length) + ' रेकर्ड'" />
            </div>
          </section>

          <!-- 14. परिवार सदस्यहरू — accordion card layout -->
          <section v-if="quickViewData.household.members?.length" class="bg-white rounded-md border border-[#e6e7e9] p-4">
            <div class="flex items-center mb-3">
              <QvSectionHead icon="fa-users" color="purple" title="परिवार सदस्यहरू" />
              <span class="ml-auto bg-purple-100 text-purple-700 text-[10px] px-2 py-0.5 rounded-full font-semibold">
                {{ quickViewData.household.members.length }} जना
              </span>
            </div>

            <div class="space-y-2">
              <div
                v-for="(m, idx) in quickViewData.household.members"
                :key="m.id ?? idx"
                class="rounded-md border overflow-hidden shadow-sm transition-all"
                :class="m.is_household_head ? 'border-indigo-300' : 'border-[#e6e7e9]'"
              >
                <!-- Collapsed header row -->
                <div
                  class="flex items-center gap-2.5 px-3 py-2.5 bg-white cursor-pointer select-none"
                  @click="toggleQvMember(idx)"
                >
                  <!-- S.N. circle -->
                  <span
                    class="w-6 h-6 shrink-0 rounded-full flex items-center justify-center text-[11px] font-bold text-white"
                    :class="m.is_household_head ? 'bg-indigo-600' : 'bg-slate-400'"
                  >{{ idx + 1 }}</span>

                  <!-- Name + sub-line -->
                  <div class="flex-1 min-w-0">
                    <p class="text-[12px] font-semibold text-[#1d273b] truncate">
                      {{ m.full_name || ('सदस्य ' + (idx + 1)) }}
                      <span v-if="m.is_household_head"
                        class="ml-1.5 text-[9px] bg-indigo-100 text-indigo-700 font-bold px-1.5 py-0.5 rounded">घरमुली</span>
                    </p>
                    <p class="text-[10px] text-gray-400 mt-0.5 truncate">
                      <template v-if="m.age">{{ m.age }} वर्ष</template>
                      <template v-if="m.gender"> • {{ genderMap[m.gender] ?? m.gender }}</template>
                      <template v-if="m.relation_to_head"> • {{ qvLabel('relation_to_head', m.relation_to_head) || m.relation_to_head }}</template>
                      <template v-if="m.education_level"> • {{ qvLabel('education_level', m.education_level) || m.education_level }}</template>
                    </p>
                  </div>

                  <!-- Quick badges -->
                  <div class="hidden sm:flex items-center gap-1 shrink-0">
                    <span v-if="m.disability_status"
                      class="text-[9px] bg-orange-100 text-orange-700 font-semibold px-1.5 py-0.5 rounded">
                      <i class="fa-solid fa-wheelchair text-[8px]"></i> अपाङ्गता
                    </span>
                    <span v-if="m.blood_group"
                      class="text-[9px] bg-rose-100 text-rose-700 font-semibold px-1.5 py-0.5 rounded">
                      {{ m.blood_group }}
                    </span>
                    <span v-if="m.has_citizenship"
                      class="text-[9px] bg-green-100 text-green-700 font-semibold px-1.5 py-0.5 rounded">नागरिकता</span>
                  </div>

                  <!-- Expand chevron -->
                  <span class="shrink-0 w-6 h-6 flex items-center justify-center rounded text-gray-400">
                    <i :class="expandedQvMembers.has(idx) ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'" class="text-[9px]"></i>
                  </span>
                </div>

                <!-- Expanded detail panel -->
                <div v-show="expandedQvMembers.has(idx)"
                  class="border-t border-[#e6e7e9] bg-slate-50/40 px-3 py-3">
                  <div class="grid grid-cols-2 md:grid-cols-3 gap-x-5 gap-y-1.5 text-[11px]">

                    <!-- Identity -->
                    <div v-if="m.father_name" class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">बुवाको नाम:</span>
                      <span class="font-semibold text-gray-700">{{ m.father_name }}</span>
                    </div>
                    <div class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">जन्म मिति:</span>
                      <span class="font-semibold text-gray-700">{{ m.date_of_birth ? formatDate(m.date_of_birth) : '—' }}</span>
                    </div>
                    <div class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">जातजाति:</span>
                      <span class="font-semibold text-gray-700">{{ qvLabel('ethnicity', m.ethnicity) || m.ethnicity || '—' }}</span>
                    </div>
                    <div class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">धर्म:</span>
                      <span class="font-semibold text-gray-700">{{ qvLabel('religion', m.religion) || m.religion || '—' }}</span>
                    </div>
                    <div class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">मातृभाषा:</span>
                      <span class="font-semibold text-gray-700">{{ qvLabel('mother_tongue', m.mother_tongue) || m.mother_tongue || '—' }}</span>
                    </div>
                    <div class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">रक्त समूह:</span>
                      <span class="font-semibold text-gray-700">{{ m.blood_group || '—' }}</span>
                    </div>

                    <!-- Marital -->
                    <div class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">वैवाहिक:</span>
                      <span class="font-semibold text-gray-700">{{ qvLabel('marital_status', m.marital_status) || '—' }}</span>
                    </div>

                    <!-- Education -->
                    <div class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">शिक्षा:</span>
                      <span class="font-semibold text-gray-700">{{ qvLabel('education_level', m.education_level) || '—' }}</span>
                    </div>
                    <div v-if="m.school_name" class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">संस्था:</span>
                      <span class="font-semibold text-gray-700 truncate">{{ m.school_name }}</span>
                    </div>

                    <!-- Occupation -->
                    <div class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">पेशा:</span>
                      <span class="font-semibold text-gray-700">{{ qvLabel('occupation_type', m.occupation_type) || '—' }}</span>
                    </div>
                    <div class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">रोजगारी:</span>
                      <span class="font-semibold text-gray-700">{{ qvLabel('employment_status', m.employment_status) || '—' }}</span>
                    </div>

                    <!-- Residence -->
                    <div class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">बसोबास:</span>
                      <span class="font-semibold text-gray-700">{{ qvLabel('residence_status', m.residence_status) || '—' }}</span>
                    </div>
                    <div v-if="m.addrslocation" class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">ठेगाना:</span>
                      <span class="font-semibold text-gray-700">{{ qvLabel('addrslocation', m.addrslocation) || m.addrslocation }}</span>
                    </div>

                    <!-- Contact -->
                    <div v-if="m.member_phone" class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">फोन:</span>
                      <span class="font-semibold text-gray-700">{{ m.member_phone }}</span>
                    </div>
                    <div v-if="m.member_email" class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">इमेल:</span>
                      <span class="font-semibold text-gray-700">{{ m.member_email }}</span>
                    </div>

                    <!-- Disability -->
                    <div v-if="m.disability_status" class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">अपाङ्गता:</span>
                      <span class="font-semibold text-orange-600">{{ qvLabel('disability_type', m.disability_type) || 'छ' }}</span>
                    </div>
                    <div v-if="m.disability_card === 'yes'" class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">परिचयपत्र रंग:</span>
                      <span class="font-semibold text-gray-700">{{ qvLabel('disability_card_color', m.disability_card_color) || '—' }}</span>
                    </div>

                    <!-- Chronic illness -->
                    <div v-if="m.chronic_illness" class="flex gap-1.5">
                      <span class="text-gray-500 shrink-0">दीर्घरोग:</span>
                      <span class="font-semibold text-red-600">
                        {{ (m.chronic_illness_types?.length ? m.chronic_illness_types.map(v => qvLabel('chronic_illness_type', v) || v).join(', ') : 'छ') }}
                      </span>
                    </div>

                    <!-- Documents row — full width -->
                    <div class="col-span-2 md:col-span-3 flex flex-wrap gap-1.5 mt-1 pt-1.5 border-t border-slate-200">
                      <span class="text-gray-500 text-[10px]">कागजात:</span>
                      <span v-if="m.has_citizenship"      class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-semibold">✓ नागरिकता</span>
                      <span v-if="m.has_voters_card"      class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-semibold">✓ मतदाता</span>
                      <span v-if="m.has_national_id_card" class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-semibold">✓ राष्ट्रिय परिचयपत्र</span>
                      <span v-if="m.has_passport"         class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-semibold">✓ राहदानी</span>
                      <span v-if="m.age && parseInt(m.age) <= 18 && m.has_birth_certificate"
                        class="text-[10px] bg-teal-100 text-teal-700 px-1.5 py-0.5 rounded font-semibold">✓ जन्म दर्ता</span>
                      <span v-if="!m.has_citizenship && !m.has_voters_card && !m.has_national_id_card && !m.has_passport"
                        class="text-[10px] text-gray-400">कुनै कागजात दर्ता छैन</span>
                    </div>

                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Submission Log -->
          <section v-if="quickViewData.household.submission_logs?.length" class="bg-white rounded-md border border-[#e6e7e9] p-4">
            <h3 class="text-[12px] font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
              <i class="fa-solid fa-timeline text-indigo-400"></i> कार्यक्रम लग
            </h3>
            <div class="relative">
              <div class="absolute left-3 top-0 bottom-0 w-px bg-gray-100"></div>
              <div class="space-y-3">
                <div
                  v-for="log in quickViewData.household.submission_logs"
                  :key="log.id"
                  class="relative pl-8"
                >
                  <div class="absolute left-0 top-1 w-6 h-6 rounded-full bg-white border-2 border-indigo-200 flex items-center justify-center">
                    <div class="w-2 h-2 rounded-full bg-indigo-400"></div>
                  </div>
                  <div>
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span class="text-[11px] font-semibold text-gray-700">{{ log.actor?.name || '—' }}</span>
                      <span class="text-[11px] text-gray-400">—</span>
                      <span :class="['text-[11px] px-1.5 py-0.5 rounded font-medium', actionBadge(log.action)]">{{ log.action }}</span>
                    </div>
                    <p v-if="log.notes" class="text-[11px] text-gray-500 mt-0.5">{{ log.notes }}</p>
                    <p class="text-[10px] text-gray-400 mt-0.5">{{ formatDate(log.created_at) }}</p>
                  </div>
                </div>
              </div>
            </div>
          </section>

        </div>
      </div>
    </Transition>
  </Teleport>

</template>

<script setup>
import { ref, computed, watch, h } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  households:    { type: Object, default: () => ({ data: [], meta: {}, links: [] }) },
  wards:         { type: Array,  default: () => [] },
  filters:       { type: Object, default: () => ({ search: '', ward_id: '', status: '' }) },
  surveyOptions: { type: Object, default: () => ({}) },
})

const searchQuery = ref(props.filters.search || '')
const wardFilter = ref(props.filters.ward_id || '')
const statusFilter = ref(props.filters.status || '')
const submitting = ref(null)

let searchTimeout = null

const hasActiveFilters = computed(() => searchQuery.value || wardFilter.value || statusFilter.value)

// ── Quick-View ────────────────────────────────────────────────────────────────
const quickViewOpen       = ref(false)
const quickViewHh         = ref(null)   // lightweight row from list (for header while loading)
const quickViewLoading    = ref(false)
const quickViewData       = ref(null)   // full data from /preview
const expandedQvMembers   = ref(new Set())

async function openQuickView(hh) {
  quickViewHh.value         = hh
  quickViewData.value       = null
  quickViewOpen.value       = true
  quickViewLoading.value    = true
  expandedQvMembers.value   = new Set()

  try {
    const res = await fetch(`/households/${hh.id}/preview`, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    if (!res.ok) throw new Error('Failed')
    quickViewData.value = await res.json()
    // Auto-expand the household head member
    quickViewData.value.household.members?.forEach((m, i) => {
      if (m.is_household_head) expandedQvMembers.value = new Set([...expandedQvMembers.value, i])
    })
  } catch {
    quickViewData.value = null
  } finally {
    quickViewLoading.value = false
  }
}

function closeQuickView() {
  quickViewOpen.value     = false
  quickViewHh.value       = null
  quickViewData.value     = null
  expandedQvMembers.value = new Set()
}

function toggleQvMember(idx) {
  const next = new Set(expandedQvMembers.value)
  if (next.has(idx)) next.delete(idx)
  else next.add(idx)
  expandedQvMembers.value = next
}

function qvLabel(category, value) {
  if (!value) return null
  const opts = props.surveyOptions[category] ?? []
  const found = opts.find(o => o.value === value)
  return found ? found.label : value
}

const genderMap = { male: 'पुरुष', female: 'महिला', other: 'अन्य' }

function actionBadge(a) {
  const map = {
    submitted:    'bg-yellow-100 text-yellow-700',
    approved:     'bg-blue-100 text-blue-700',
    rejected:     'bg-red-100 text-red-600',
    published:    'bg-green-100 text-green-700',
  }
  return map[a] ?? 'bg-gray-100 text-gray-600'
}

function submitFromDrawer(id) {
  if (!confirm('के तपाईं यो घरधुरी पेस गर्न निश्चित हुनुहुन्छ?')) return
  submitting.value = id
  router.post(`/households/${id}/submit`, {}, {
    onFinish: () => {
      submitting.value = null
      closeQuickView()
    },
  })
}

// ── Inline sub-components (render fn — no runtime compiler needed) ────────────
const QvRow = {
  props: ['label', 'value'],
  render() {
    return h('div', [
      h('p', { class: 'text-[10px] text-gray-400 mb-0.5' }, this.label),
      h('p', { class: 'text-[12px] font-medium text-gray-800' }, this.value ?? '—'),
    ])
  },
}

const colorMap = {
  orange: ['bg-orange-100', 'text-orange-600'],
  blue:   ['bg-blue-100',   'text-blue-600'],
  green:  ['bg-green-100',  'text-green-600'],
  violet: ['bg-violet-100', 'text-violet-600'],
  rose:   ['bg-rose-100',   'text-rose-600'],
  cyan:   ['bg-cyan-100',   'text-cyan-600'],
  amber:  ['bg-amber-100',  'text-amber-600'],
  teal:   ['bg-teal-100',   'text-teal-600'],
  yellow: ['bg-yellow-100', 'text-yellow-600'],
  red:    ['bg-red-100',    'text-red-600'],
  purple: ['bg-purple-100', 'text-purple-600'],
  indigo: ['bg-indigo-100', 'text-indigo-600'],
}

const QvSectionHead = {
  props: ['icon', 'color', 'title'],
  render() {
    const [bg, text] = colorMap[this.color] ?? ['bg-gray-100', 'text-gray-600']
    return h('h2', { class: 'text-[12px] font-semibold text-gray-700 mb-3 flex items-center gap-2' }, [
      h('span', { class: `w-5 h-5 rounded ${bg} flex items-center justify-center` }, [
        h('i', { class: `fa-solid ${this.icon} ${text} text-[10px]` }),
      ]),
      this.title,
    ])
  },
}

// ── Quick-view data helpers ────────────────────────────────────────────────────
function qvStr(v)  { return v !== null && v !== undefined && v !== '' ? String(v) : null }
function qvBool(v) { return v === true ? 'छ' : v === false ? 'छैन' : null }
function qvYesNo(v){ return v === 'yes' || v === true ? 'छ' : v === 'no' || v === false ? 'छैन' : null }
function qvLabelArr(category, arr) {
  if (!arr?.length) return null
  return arr.map(v => qvLabel(category, v)).join(', ')
}
function qvHasCropData() {
  const cp = quickViewData.value?.household?.survey_data?.cropProduction
  if (!cp) return false
  return Object.values(cp).some(arr => Array.isArray(arr) && arr.length)
}
function qvCropCount(key) {
  const arr = quickViewData.value?.household?.survey_data?.cropProduction?.[key]
  return arr?.length ? arr.length + ' किसिम' : null
}

// ── helpers ───────────────────────────────────────────────────────────────────

function statusBadge(s) {
  const map = {
    draft: 'bg-gray-100 text-gray-600',
    submitted: 'bg-yellow-100 text-yellow-700',
    ward_approved: 'bg-blue-100 text-blue-700',
    rejected: 'bg-red-100 text-red-700',
    published: 'bg-green-100 text-green-700',
  }
  return map[s] ?? 'bg-gray-100 text-gray-600'
}

function statusLabel(s) {
  const map = {
    draft: 'मस्यौदा',
    submitted: 'पेस भएको',
    ward_approved: 'वडा अनुमोदित',
    rejected: 'अस्वीकृत',
    published: 'प्रकाशित',
  }
  return map[s] ?? s
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ne-NP')
}

// ── filters ───────────────────────────────────────────────────────────────────

function applyFilters() {
  router.get(
    '/households',
    {
      search: searchQuery.value,
      ward_id: wardFilter.value,
      status: statusFilter.value,
    },
    { preserveState: true, replace: true },
  )
}

// Debounced search
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    applyFilters()
  }, 400)
})

function clearFilters() {
  searchQuery.value = ''
  wardFilter.value = ''
  statusFilter.value = ''
  applyFilters()
}

// ── pagination ────────────────────────────────────────────────────────────────

function goToPage(page) {
  router.get(
    '/households',
    {
      search: searchQuery.value,
      ward_id: wardFilter.value,
      status: statusFilter.value,
      page,
    },
    { preserveState: true, replace: true },
  )
}

const visiblePages = computed(() => {
  const meta = props.households.meta
  if (!meta) return []
  const current = meta.current_page
  const last = meta.last_page
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)

  const pages = []
  if (current <= 4) {
    for (let i = 1; i <= 5; i++) pages.push(i)
    pages.push('...')
    pages.push(last)
  } else if (current >= last - 3) {
    pages.push(1)
    pages.push('...')
    for (let i = last - 4; i <= last; i++) pages.push(i)
  } else {
    pages.push(1)
    pages.push('...')
    for (let i = current - 1; i <= current + 1; i++) pages.push(i)
    pages.push('...')
    pages.push(last)
  }
  return pages
})

// ── actions ───────────────────────────────────────────────────────────────────

function submitHousehold(id) {
  if (!confirm('के तपाईं यो घरधुरी पेस गर्न निश्चित हुनुहुन्छ?')) return
  submitting.value = id
  router.post(`/households/${id}/submit`, {}, {
    onFinish: () => { submitting.value = null },
  })
}
</script>

<style scoped>
/* Backdrop fade */
.qv-backdrop-enter-active,
.qv-backdrop-leave-active { transition: opacity 0.2s ease; }
.qv-backdrop-enter-from,
.qv-backdrop-leave-to    { opacity: 0; }

/* Panel slide-in from right */
.qv-panel-enter-active,
.qv-panel-leave-active { transition: transform 0.25s ease; }
.qv-panel-enter-from,
.qv-panel-leave-to     { transform: translateX(100%); }
</style>
