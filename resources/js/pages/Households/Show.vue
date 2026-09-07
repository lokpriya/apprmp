<template>
  <div>
    <!-- Print-only header (hidden on screen) -->
    <div class="print-only hidden mb-6 pb-4 border-b-2 border-gray-800">
      <p class="text-xs text-gray-500 mb-1">{{ page.props.auth?.lg?.name_np ?? 'स्थानीय तह' }}</p>
      <h1 class="text-xl font-bold text-gray-900">घरधुरी विवरण</h1>
      <div class="flex items-center gap-4 mt-2 text-sm text-gray-600">
        <span>कोड: <strong class="font-mono">{{ household.unique_code }}</strong></span>
        <span>वडा: <strong>{{ household.ward?.ward_number ?? '—' }}</strong></span>
        <span>अवस्था: <strong>{{ statusLabel(household.status) }}</strong></span>
        <span class="ml-auto text-xs text-gray-400">मुद्रण मिति: {{ new Date().toLocaleDateString('ne-NP') }}</span>
      </div>
    </div>

    <!-- Page header (screen only) -->
    <div class="no-print flex items-center gap-3 mb-4">
      <a href="/households"
        class="w-8 h-8 rounded border border-[#e6e7e9] flex items-center justify-center text-[#626976] hover:bg-gray-50 transition-colors shrink-0">
        <i class="fa-solid fa-arrow-left text-[13px]"></i>
      </a>
      <div class="flex items-center gap-2 flex-wrap min-w-0">
        <h1 class="text-[15px] font-semibold text-[#1d273b]">घरधुरी विवरण</h1>
        <span class="font-mono text-[11px] bg-[#206bc4]/8 text-[#206bc4] px-2 py-0.5 rounded">{{ household.unique_code }}</span>
        <span :class="['text-[11px] px-2 py-0.5 rounded font-medium', statusBadge(household.status)]">
          {{ statusLabel(household.status) }}
        </span>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

      <!-- ── Main content (2/3) ─────────────────────────────────────── -->
      <div class="lg:col-span-2 space-y-4">

        <!-- 1. मूल विवरण -->
        <section class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <SectionHead icon="fa-circle-info" color="indigo" title="मूल विवरण" />
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-5">
            <DetailRow label="उत्तरदाताको नाम"  :value="household.respondent_name" />
            <DetailRow label="सम्पर्क"           :value="household.respondent_contact" />
            <DetailRow label="वडा नं."            :value="household.ward ? `वडा ${household.ward.ward_number}` : null" />
            <DetailRow label="बस्ती"              :value="household.settlement?.name_np" />
            <DetailRow label="गणक"               :value="household.enumerator?.name" />
            <DetailRow label="सर्वेक्षण मिति"     :value="formatDate(household.survey_date)" />
            <DetailRow v-if="household.gps_lat"  label="GPS (Lat)"  :value="String(household.gps_lat)" />
            <DetailRow v-if="household.gps_lng"  label="GPS (Lng)"  :value="String(household.gps_lng)" />
            <DetailRow v-if="household.gps_accuracy" label="GPS सटीकता" :value="String(household.gps_accuracy)" />
          </div>
          <div v-if="household.remarks" class="mt-4 p-3 bg-yellow-50 rounded-md border border-yellow-100">
            <p class="text-xs text-yellow-600 font-medium mb-1"><i class="fa-solid fa-note-sticky mr-1"></i>कैफियत</p>
            <p class="text-sm text-gray-700">{{ household.remarks }}</p>
          </div>
        </section>

        <!-- 2. आवास विवरण -->
        <section v-if="household.housing" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <SectionHead icon="fa-house" color="orange" title="आवास विवरण" />
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-5">
            <DetailRow label="घरको प्रकार"       :value="label('house_type', household.housing.house_type)" />
            <DetailRow label="छानाको प्रकार"     :value="label('roof_type', household.housing.roof_type)" />
            <DetailRow label="भित्ताको प्रकार"   :value="label('wall_type', household.housing.wall_type)" />
            <DetailRow label="जग प्रकार"          :value="label('foundation_type', household.housing.foundation_type)" />
            <DetailRow label="स्वामित्व"           :value="label('ownership_type', household.housing.ownership_type)" />
            <DetailRow label="घरको अवस्था"       :value="label('house_condition', household.housing.house_condition)" />
            <DetailRow label="कोठा संख्या"        :value="str(household.housing.total_rooms)" />
            <DetailRow label="तल्ला संख्या"       :value="str(household.housing.total_floors)" />
            <DetailRow label="जग्गाको प्रकार"    :value="label('house_land_type', household.housing.house_land_type)" />
            <DetailRow label="घरको उद्देश्य"     :value="label('house_purpose', household.housing.house_purpose)" />
            <DetailRow label="निर्माण वर्ष"      :value="str(household.housing.construction_year)" />
            <DetailRow label="जोखिम क्षेत्र"     :value="yesNo(household.housing.is_at_risk)" />
            <DetailRow v-if="household.housing.risk_types?.length"
              label="जोखिमका किसिम"
              :value="labels('disaster_type', household.housing.risk_types)"
              class="col-span-2" />
          </div>
        </section>

        <!-- 2b. जग्गा विवरण (पालिकामा) -->
        <section v-if="household.survey_data?.hasLandInMunicipality" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <SectionHead icon="fa-map" color="amber" title="जग्गा विवरण (पालिकामा)" />
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-5 mb-4">
            <DetailRow label="पालिकामा जग्गा छ?"
              :value="household.survey_data.hasLandInMunicipality === 'yes' ? 'छ' : 'छैन'" />
          </div>
          <template v-if="household.survey_data.hasLandInMunicipality === 'yes' && household.survey_data.landParcels?.length">
            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-2">जग्गा पार्सेल विवरण</p>
            <div class="overflow-x-auto">
              <table class="w-full text-xs">
                <thead>
                  <tr class="bg-gray-50 text-gray-500 uppercase text-[10px] tracking-wide">
                    <th class="px-3 py-2 text-left font-semibold">#</th>
                    <th class="px-3 py-2 text-left font-semibold">जमिनको किसिम</th>
                    <th class="px-3 py-2 text-left font-semibold">एकाइ</th>
                    <th class="px-3 py-2 text-right font-semibold">परिमाण</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#e6e7e9]">
                  <tr v-for="(parcel, i) in household.survey_data.landParcels" :key="i" class="hover:bg-gray-50">
                    <td class="px-3 py-2 text-gray-400">{{ i + 1 }}</td>
                    <td class="px-3 py-2 font-medium text-gray-800">{{ label('land_use_type', parcel.land_use_type) || parcel.land_use_type || '—' }}</td>
                    <td class="px-3 py-2 text-gray-600">{{ label('land_measurement_unit', parcel.land_unit) || parcel.land_unit || '—' }}</td>
                    <td class="px-3 py-2 text-right text-gray-800 font-medium">{{ parcel.quantity || '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </section>

        <!-- 3. परिवार तथा सामाजिक -->
        <section v-if="household.survey_data?.social" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <SectionHead icon="fa-people-roof" color="violet" title="परिवार तथा सामाजिक" />
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-5">
            <DetailRow label="घरमुलीको नाम"       :value="household.survey_data.social.family_head" />
            <DetailRow label="घरमुलीको सम्पर्क"  :value="household.survey_data.social.contact_no_fam_head" />
            <DetailRow label="पुरुष संख्या"        :value="str(household.survey_data.social.male_num)" />
            <DetailRow label="महिला संख्या"        :value="str(household.survey_data.social.female_num)" />
            <DetailRow label="अन्य संख्या"         :value="str(household.survey_data.social.other_num)" />
            <DetailRow label="परिवार प्रकार"       :value="label('family_type', household.survey_data.social.family_type)" />
            <DetailRow label="जातजाति"             :value="label('ethnicity', household.survey_data.social.ethnicity)" />
            <DetailRow label="धर्म"                :value="label('religion', household.survey_data.social.religion)" />
            <DetailRow label="मातृभाषा"            :value="label('mother_tongue', household.survey_data.social.mother_tongue)" />
            <DetailRow label="सञ्चार भाषा"         :value="label('mother_tongue', household.survey_data.social.com_language)" />
            <DetailRow label="मुख्य पेशा"          :value="label('occupation_type', household.survey_data.social.major_family_occupation)" />
            <DetailRow label="बसोबास प्रकार"       :value="label('settlement_type', household.survey_data.social.settlement_type)" />
            <DetailRow label="जमिन छ?"             :value="yesNo(household.survey_data.social.have_land)" />
            <template v-if="household.survey_data.social.have_land === 'yes'">
              <DetailRow label="जग्गाको एकाइ"    :value="label('land_unit', household.survey_data.social.land_unit)" />
              <DetailRow label="खेत"               :value="str(household.survey_data.social.land_khet)" />
              <DetailRow label="बारी"              :value="str(household.survey_data.social.land_bari)" />
              <DetailRow label="पाखो"              :value="str(household.survey_data.social.land_pakho)" />
            </template>
            <DetailRow label="ऋण लिएको?"          :value="yesNo(household.survey_data.social.bankloan)" />
            <DetailRow label="स्वास्थ्य बिमा"      :value="yesNo(household.survey_data.social.health_insurance)" />
            <DetailRow label="आप्रवासन"            :value="yesNo(household.survey_data.social.migration_last_5years)" />
            <DetailRow label="मृत्यु (यो वर्ष)"   :value="yesNo(household.survey_data.social.death)" />
          </div>
        </section>

        <!-- 4. सरसफाई तथा सुविधा -->
        <section v-if="household.utilities" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <SectionHead icon="fa-faucet" color="blue" title="सरसफाई तथा सुविधा" />
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-5">
            <DetailRow label="पिउने पानीको स्रोत"    :value="label('water_source', household.utilities.water_source)" />
            <DetailRow label="पानी दूरी (मिनेट)"     :value="str(household.utilities.water_distance_minutes)" />
            <DetailRow label="शौचालय छ?"              :value="bool(household.utilities.has_toilet)" />
            <DetailRow label="शौचालयको प्रकार"        :value="label('toilet_type', household.utilities.toilet_type)" />
            <DetailRow label="सरसफाई प्रकार"          :value="label('sanitation_type', household.utilities.sanitation_type)" />
            <DetailRow label="बिजुलीको स्रोत"         :value="label('electricity_source', household.utilities.electricity_source)" />
            <DetailRow label="मासिक एकाइ (kWh)"      :value="str(household.utilities.monthly_electricity_units)" />
            <DetailRow label="वैकल्पिक ऊर्जा"         :value="yesNo(household.utilities.has_alt_electricity)" />
            <DetailRow v-if="household.utilities.alt_electricity_sources?.length"
              label="वैकल्पिक ऊर्जाको स्रोत"
              :value="labels('alt_electricity_source', household.utilities.alt_electricity_sources)" />
            <DetailRow label="खाना पकाउने इन्धन"     :value="labels('cooking_fuel', household.utilities.cooking_fuel_arr)" />
            <DetailRow label="चुलोको प्रकार"          :value="label('cooking_stove', household.utilities.cooking_stove)" />
            <DetailRow label="दाउरा प्रयोग?"          :value="yesNo(household.utilities.uses_firewood)" />
            <DetailRow v-if="household.utilities.firewood_source?.length"
              label="दाउराको स्रोत"
              :value="labels('firewood_source', household.utilities.firewood_source)" />
            <DetailRow label="बायोग्यास छ?"           :value="bool(household.utilities.has_biogas)" />
            <DetailRow label="फोहोर व्यवस्थापन"      :value="label('solid_waste_management', household.utilities.solid_waste_management)" />
            <DetailRow label="इन्टरनेट छ?"            :value="bool(household.utilities.has_internet)" />
            <DetailRow label="इन्टरनेट प्रकार"        :value="label('internet_type', household.utilities.internet_type)" />
            <DetailRow label="हात धुने सुविधा"        :value="label('handwashing', household.utilities.handwashing)" />
            <DetailRow v-if="household.utilities.access_to_facilities?.length"
              label="उपलब्ध सुविधाहरू"
              :value="labels('facilities', household.utilities.access_to_facilities)"
              class="col-span-full" />
          </div>
        </section>

        <!-- 5. स्वास्थ्य तथा खुसी-सुखी जीवन -->
        <section v-if="household.survey_data?.health" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <SectionHead icon="fa-heart-pulse" color="rose" title="स्वास्थ्य तथा खुसी-सुखी जीवन" />
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-5">
            <DetailRow label="स्वास्थ्य बिमा"           :value="yesNo(household.survey_data.health.has_health_insurance)" />
            <DetailRow label="बिरामी आवृत्ति"           :value="label('illness_frequency', household.survey_data.health.illness_frequency)" />
            <DetailRow label="उपचार स्थान"              :value="label('treatment_place', household.survey_data.health.treatment_place)" />
            <DetailRow label="स्वास्थ्य केन्द्र दूरी"   :value="label('time_to_health_center', household.survey_data.health.time_to_health_center)" />
            <DetailRow label="गर्भवती महिला?"           :value="yesNo(household.survey_data.health.has_pregnant_woman)" />
            <DetailRow v-if="household.survey_data.health.has_pregnant_woman === 'yes'"
              label="गर्भ महिना"
              :value="str(household.survey_data.health.pregnancy_months)" />
            <DetailRow v-if="household.survey_data.health.has_pregnant_woman === 'yes'"
              label="गर्भवतीको उमेर"
              :value="str(household.survey_data.health.pregnant_age)" />
            <DetailRow label="हात धुने जानकारी"         :value="yesNo(household.survey_data.health.aware_handwashing)" />
            <DetailRow label="पोषण जानकारी"             :value="yesNo(household.survey_data.health.aware_nutrition)" />
            <DetailRow label="गर्भवती पोषण जानकारी"    :value="yesNo(household.survey_data.health.aware_pregnant_nutrition)" />
            <DetailRow label="आइरन चक्की जानकारी"  :value="yesNo(household.survey_data.health.aware_iron_capsule)" />
          </div>
          <template v-if="household.survey_data?.wellbeing">
            <div class="border-t border-gray-100 mt-4 pt-4">
              <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">खुसी-सुखी जीवन</p>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-5">
                <DetailRow label="बसोबासमा खुशी?"       :value="yesNo(household.survey_data.wellbeing.happy_with_location)" />
                <DetailRow label="बसोबास धारणा"          :value="label('residence_perception', household.survey_data.wellbeing.residence_perception)" />
                <DetailRow label="छिमेकी राम्रा?"        :value="household.survey_data.wellbeing.neighbors_nice === 'yes' ? 'हुन्' : household.survey_data.wellbeing.neighbors_nice === 'no' ? 'होइनन्' : household.survey_data.wellbeing.neighbors_nice === 'neutral' ? 'त्यस्तो लाग्दैन' : null" />
                <DetailRow label="छिमेकी मिलन"           :value="label('neighbor_cooperation', household.survey_data.wellbeing.neighbor_cooperation)" />
                <DetailRow label="पारिवारिक भेलाघर"      :value="label('family_gathering_freq', household.survey_data.wellbeing.family_gathering_freq)" />
                <DetailRow v-if="household.survey_data.wellbeing.happiness_activities?.length"
                  label="खुशीका गतिविधि"
                  :value="labels('happiness_activities', household.survey_data.wellbeing.happiness_activities)"
                  class="col-span-full" />
              </div>
            </div>
          </template>
        </section>

        <!-- 6. यातायात तथा संस्था -->
        <section v-if="household.utilities || household.survey_data?.decisions" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <SectionHead icon="fa-car" color="cyan" title="यातायात तथा संस्था" />
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-5">
            <template v-if="household.utilities">
              <DetailRow label="सवारी साधन"            :value="label('vehicle_type', household.utilities.vehicle)" />
              <DetailRow v-if="household.utilities.vehicles?.length"
                label="सवारी विवरण" :value="household.utilities.vehicles.length + ' प्रकार'" />
            </template>
            <template v-if="household.survey_data?.decisions">
              <DetailRow label="घरायसी काम निर्णय"   :value="label('decision_owner', household.survey_data.decisions.decisioninHHWork)" />
              <DetailRow label="सम्पत्ति निर्णय"      :value="label('decision_owner', household.survey_data.decisions.decisioninProperty)" />
              <DetailRow label="समिति सहभागिता"       :value="label('decision_owner', household.survey_data.decisions.decisioninCCommittee)" />
              <DetailRow label="SMC सहभागिता"         :value="label('decision_owner', household.survey_data.decisions.decisioninSMC)" />
              <DetailRow label="व्यापार निर्णय"       :value="label('decision_owner', household.survey_data.decisions.decisioninBusiness)" />
              <DetailRow label="संस्थामा सहभागी?"    :value="yesNo(household.survey_data.decisions.org_participation)" />
              <DetailRow label="बैंक खाता छ?"         :value="yesNo(household.survey_data.decisions.has_bank_account)" />
              <DetailRow label="खाता धनी"             :value="label('bank_account_owner', household.survey_data.decisions.bank_account_owner)" />
            </template>
          </div>
        </section>

        <!-- 7. व्यवसाय तथा रोजगार -->
        <section v-if="household.survey_data?.occupation" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <SectionHead icon="fa-briefcase" color="amber" title="व्यवसाय तथा रोजगार" />
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-5">
            <DetailRow label="भारत विदेश"  :value="str(household.survey_data.occupation.foreign_india)" />
            <DetailRow label="खाडी विदेश"  :value="str(household.survey_data.occupation.foreign_gulf)" />
            <DetailRow label="अन्य विदेश"  :value="str(household.survey_data.occupation.foreign_others)" />
            <DetailRow label="सीप तालिम"   :value="yesNo(household.survey_data.occupation.skill_training)" />
            <DetailRow v-if="household.survey_data.occupation.skill_rows?.length"
              label="सीप विवरण"
              :value="household.survey_data.occupation.skill_rows.length + ' प्रकार'" />
            <DetailRow v-if="household.survey_data.occupation.occupation_rows?.length"
              label="व्यवसाय विवरण"
              :value="household.survey_data.occupation.occupation_rows.length + ' पङ्क्ति'" />
          </div>
        </section>

        <!-- 8. खेतीपाती -->
        <section v-if="household.agriculture" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <SectionHead icon="fa-seedling" color="green" title="खेतीपाती" />
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-5">
            <DetailRow label="खेती गर्नुहुन्छ?"     :value="yesNo(household.agriculture.agriculture)" />
            <DetailRow v-if="household.agriculture.agri_categories?.length"
              label="खेती प्रकार"
              :value="labels('agri_category', household.agriculture.agri_categories)"
              class="col-span-2" />
            <DetailRow label="जमिन छ?"              :value="bool(household.agriculture.owns_land)" />
            <DetailRow label="जमिन (रोपनी)"         :value="str(household.agriculture.land_area_ropani)" />
            <DetailRow label="सिञ्चित क्षेत्र"      :value="str(household.agriculture.irrigated_area_ropani)" />
            <DetailRow label="वार्षिक कृषि आम्दानी" :value="str(household.agriculture.annual_agri_income)" />
            <DetailRow label="रासायनिक मल?"          :value="yesNo(household.agriculture.use_of_chem_fertilizer)" />
            <template v-if="household.agriculture.use_of_chem_fertilizer === 'yes'">
              <DetailRow label="मल परिमाण (के.जी.)" :value="str(household.agriculture.chem_fertilizer_kg)" />
              <DetailRow v-if="household.agriculture.chem_fertilizer_types?.length"
                label="मलको प्रकार"
                :value="labels('chem_fertilizer_types', household.agriculture.chem_fertilizer_types)" />
              <DetailRow v-if="household.agriculture.fertilizer_sources?.length"
                label="मलको स्रोत"
                :value="labels('fertilizer_sources', household.agriculture.fertilizer_sources)" />
            </template>
            <DetailRow label="रासायनिक कीटनाशक?"    :value="yesNo(household.agriculture.use_of_chem_pesticide)" />
            <DetailRow label="कीटनाशक लागत"         :value="str(household.agriculture.chem_pesticide_cost)" />
            <DetailRow v-if="household.agriculture.Farm_Challenges?.length"
              label="चुनौतीहरू"
              :value="labels('farm_challenge', household.agriculture.Farm_Challenges)"
              class="col-span-2" />
          </div>
        </section>

        <!-- 9. कृषि उत्पादन -->
        <section v-if="hasCropData" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <SectionHead icon="fa-wheat-awn" color="yellow" title="कृषि उत्पादन" />
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-5">
            <DetailRow label="खाद्यान्न बाली" :value="cropCount('food_crops')" />
            <DetailRow label="दलहन बाली"       :value="cropCount('pulses')" />
            <DetailRow label="तेलहन बाली"      :value="cropCount('oil_seeds')" />
            <DetailRow label="तरकारी"           :value="cropCount('vegetables')" />
            <DetailRow label="नगदे बाली"        :value="cropCount('cash_crops')" />
            <DetailRow label="फलफूल"            :value="cropCount('fruits')" />
            <DetailRow label="अन्य बाली"        :value="cropCount('other_crops')" />
          </div>
        </section>

        <!-- 10. पशुपालन -->
        <section v-if="household.livestock?.length || household.survey_data?.livestockDetail" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <SectionHead icon="fa-cow" color="teal" title="पशुपालन" />
          <template v-if="household.survey_data?.livestockDetail">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
              <DetailRow label="पशुपालन गर्नुहुन्छ?" :value="yesNo(household.survey_data.livestockDetail.has_livestock)" />
              <DetailRow label="AI सेवा"               :value="yesNo(household.survey_data.livestockDetail.Ai_in_livestock)" />
            </div>
          </template>
          <!-- Livestock rows table -->
          <div v-if="household.survey_data?.livestockDetail?.livestock_rows?.length" class="overflow-x-auto mb-3">
            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1">पशु/पक्षी विवरण</p>
            <table class="w-full text-xs">
              <thead class="bg-gray-50">
                <tr>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold">पशु/पक्षी</th>
                  <th class="text-right px-3 py-2 text-gray-500 font-semibold">परिमाण</th>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold">जात</th>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold">पालनको तरिका</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#e6e7e9]">
                <tr v-for="(row, i) in household.survey_data.livestockDetail.livestock_rows" :key="i" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-800">{{ label('livestock_kind', row.kind) || row.kind || '—' }}</td>
                  <td class="px-3 py-2 text-right text-gray-600">{{ row.qty || '—' }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ label('livestock_breed', row.breed) || row.breed || '—' }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ label('livestock_rearing_method', row.rearing_method) || row.rearing_method || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Production rows table -->
          <div v-if="household.survey_data?.livestockDetail?.product_rows?.length" class="overflow-x-auto">
            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1">पशु उत्पादन</p>
            <table class="w-full text-xs">
              <thead class="bg-gray-50">
                <tr>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold">उत्पादनको किसिम</th>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold">एकाइ</th>
                  <th class="text-right px-3 py-2 text-gray-500 font-semibold">उत्पादन</th>
                  <th class="text-right px-3 py-2 text-gray-500 font-semibold">बिक्री</th>
                  <th class="text-right px-3 py-2 text-gray-500 font-semibold">प्रति इकाई मूल्य</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#e6e7e9]">
                <tr v-for="(row, i) in household.survey_data.livestockDetail.product_rows" :key="i" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-medium text-gray-800">{{ label('livestock_product', row.product) || row.product || '—' }}</td>
                  <td class="px-3 py-2 text-gray-600">{{ label('livestock_product_unit', row.unit) || row.unit || '—' }}</td>
                  <td class="px-3 py-2 text-right text-gray-600">{{ row.qty || '—' }}</td>
                  <td class="px-3 py-2 text-right text-gray-600">{{ row.sale_qty || '—' }}</td>
                  <td class="px-3 py-2 text-right text-gray-600">{{ row.price_per_unit || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- 11. मौरी/माछा/रेशम -->
        <section v-if="household.survey_data?.beeFishSilk?.has_bee_fish_silk === 'yes'" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <SectionHead icon="fa-egg" color="amber" title="मौरी / माछा / रेशम" />
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-5">
            <DetailRow v-if="household.survey_data.beeFishSilk.bee_fish_silk_select?.length"
              label="प्रकार" :value="labels('bee_fish_silk', household.survey_data.beeFishSilk.bee_fish_silk_select)" />
            <DetailRow label="मुढे घार"                :value="str(household.survey_data.beeFishSilk.Mudhe_Hive)" />
            <DetailRow label="आधुनिक घार"              :value="str(household.survey_data.beeFishSilk.Modern_Hive)" />
            <DetailRow label="मह उत्पादन (मुढे)"       :value="str(household.survey_data.beeFishSilk.Honey_Production_Mudhe_Hive)" />
            <DetailRow label="मह उत्पादन (आधुनिक)"    :value="str(household.survey_data.beeFishSilk.Honey_Production_Modern_Hive)" />
            <DetailRow label="मौरी तालिम"              :value="yesNo(household.survey_data.beeFishSilk.Bee_Training)" />
            <DetailRow label="पोखरी क्षेत्र"           :value="str(household.survey_data.beeFishSilk.Pond_area)" />
            <DetailRow label="माछा उत्पादन"            :value="str(household.survey_data.beeFishSilk.fish_prod)" />
            <DetailRow label="माछा बिक्री"             :value="str(household.survey_data.beeFishSilk.fish_sales)" />
            <DetailRow label="रेशम (Kg)"               :value="str(household.survey_data.beeFishSilk.Sik)" />
            <DetailRow label="रेशम उत्पादन"            :value="str(household.survey_data.beeFishSilk.silk_prod)" />
            <DetailRow label="रेशम बिक्री"             :value="str(household.survey_data.beeFishSilk.silk_sales)" />
          </div>
        </section>

        <!-- 12. आम्दानी तथा खर्च -->
        <section v-if="household.economic" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <SectionHead icon="fa-coins" color="green" title="आम्दानी तथा खर्च" />
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-5">
            <DetailRow label="मुख्य आयस्रोत"       :value="label('income_source', household.economic.primary_income_source)" />
            <DetailRow label="वार्षिक आम्दानी"      :value="household.economic.annual_income_range" />
            <DetailRow label="वार्षिक खर्च"         :value="household.economic.annual_expenditure_range" />
            <DetailRow label="रेमिट्यान्स"          :value="str(household.economic.remittance_amount)" />
            <DetailRow label="ऋणको स्रोत"           :value="labels('loan_source', household.economic.loan_sources)" />
            <DetailRow label="जम्मा ऋण"             :value="str(household.economic.total_loan_amount)" />
            <DetailRow label="बैंक खाता"            :value="bool(household.economic.has_bank_account)" />
            <DetailRow label="बिमा"                  :value="bool(household.economic.has_insurance)" />
            <DetailRow label="गरिबी कार्ड"          :value="bool(household.economic.poverty_card)" />
            <DetailRow label="सामाजिक सुरक्षा"      :value="bool(household.economic.social_security_allowance)" />
            <DetailRow label="कृषि/पशु आम्दानी"    :value="str(household.economic.agri_livestock_income)" />
            <DetailRow label="व्यापार आम्दानी"      :value="str(household.economic.business_industry_income)" />
            <DetailRow label="रोजगार आम्दानी"       :value="str(household.economic.employment_service_income)" />
            <DetailRow label="विदेश आम्दानी"        :value="str(household.economic.abroad_job_income)" />
            <DetailRow label="खाना खर्च"            :value="str(household.economic.food_expense)" />
            <DetailRow label="शिक्षा खर्च"          :value="str(household.economic.education_expense)" />
            <DetailRow label="उपचार खर्च"           :value="str(household.economic.treatment_expense)" />
          </div>
        </section>

        <!-- 13. प्राकृतिक प्रकोप -->
        <section v-if="household.disaster" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <SectionHead icon="fa-triangle-exclamation" color="red" title="प्राकृतिक प्रकोप" />
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-5">
            <DetailRow v-if="household.disaster.risk_types?.length"
              label="जोखिमका किसिम"
              :value="labels('disaster_type', household.disaster.risk_types)"
              class="col-span-2" />
            <DetailRow label="जोखिम न्यूनीकरण योजना"    :value="label('risk_reduction_plan', household.disaster.risk_reduction_plan)" />
            <DetailRow label="पछिल्लो ३ वर्षमा प्रभावित?" :value="yesNo(household.disaster.affected_last_3years)" />
            <DetailRow label="यो वर्ष मृत्यु?"            :value="yesNo(household.disaster.has_death_this_year)" />
            <DetailRow v-if="household.disaster.death_rows?.length"
              label="मृत्यु संख्या" :value="String(household.disaster.death_rows.length)" />
            <DetailRow v-if="household.disaster.human_loss_rows?.length"
              label="मानवीय क्षति" :value="String(household.disaster.human_loss_rows.length) + ' रेकर्ड'" />
            <DetailRow v-if="household.disaster.physical_loss_rows?.length"
              label="भौतिक क्षति" :value="String(household.disaster.physical_loss_rows.length) + ' रेकर्ड'" />
            <DetailRow label="राहत प्राप्त?"              :value="yesNo(household.disaster.received_relief)" />
            <DetailRow v-if="household.disaster.relief_rows?.length"
              label="राहत विवरण" :value="String(household.disaster.relief_rows.length) + ' रेकर्ड'" />
          </div>
        </section>

        <!-- 14. परिवार सदस्यहरू — accordion cards -->
        <section v-if="household.members?.length" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <!-- Members section head with count badge inline -->
          <div class="section-head-bar -mx-5 -mt-5 px-5 py-2.5 mb-4 rounded-t-md bg-purple-600 flex items-center gap-2.5">
            <span class="w-6 h-6 rounded bg-white/20 flex items-center justify-center flex-shrink-0">
              <i class="fa-solid fa-users text-white text-[11px]"></i>
            </span>
            <span class="text-[13px] font-semibold text-white tracking-wide flex-1">परिवार सदस्यहरू</span>
            <span class="bg-white/25 text-white text-[11px] font-bold px-2 py-0.5 rounded-full">
              {{ household.members.length }} जना
            </span>
          </div>
          <div class="space-y-2">
            <div
              v-for="(m, idx) in household.members"
              :key="m.id ?? idx"
              class="rounded-md border overflow-hidden shadow-sm"
              :class="m.is_deceased ? 'border-gray-300 opacity-75' : m.is_household_head ? 'border-indigo-300' : 'border-[#e6e7e9]'"
            >
              <!-- Collapsed header -->
              <div class="flex items-center gap-3 px-4 py-3 bg-white cursor-pointer select-none" @click="toggleMember(idx)">
                <span class="w-7 h-7 shrink-0 rounded-full flex items-center justify-center text-[12px] font-bold text-white"
                  :class="m.is_household_head ? 'bg-indigo-600' : 'bg-slate-400'">{{ idx + 1 }}</span>
                <div class="flex-1 min-w-0">
                  <p class="text-[13px] font-semibold text-[#1d273b] truncate">
                    <a :href="`/households/${household.id}/members/${m.id}`"
                       @click.stop
                       class="hover:text-indigo-600 hover:underline transition-colors">
                      {{ m.full_name || ('सदस्य ' + (idx + 1)) }}
                    </a>
                    <span v-if="m.is_household_head" class="ml-1.5 text-[10px] bg-indigo-100 text-indigo-700 font-bold px-1.5 py-0.5 rounded">घरमुली</span>
                    <span v-if="m.is_deceased" class="ml-1.5 text-[10px] bg-gray-200 text-gray-600 font-bold px-1.5 py-0.5 rounded line-through">मृत्यु भयो</span>
                  </p>
                  <p class="text-[11px] text-gray-400 mt-0.5 truncate">
                    <template v-if="m.age">{{ m.age }} वर्ष</template>
                    <template v-if="m.gender"> • {{ genderMap[m.gender] ?? m.gender }}</template>
                    <template v-if="m.relation_to_head"> • {{ label('relation_to_head', m.relation_to_head) || m.relation_to_head }}</template>
                    <template v-if="m.education_level"> • {{ label('education_level', m.education_level) || m.education_level }}</template>
                  </p>
                </div>
                <div class="hidden sm:flex items-center gap-1.5 shrink-0">
                  <span v-if="m.disability_status" class="text-[10px] bg-orange-100 text-orange-700 font-semibold px-1.5 py-0.5 rounded">
                    <i class="fa-solid fa-wheelchair text-[9px]"></i> अपाङ्गता
                  </span>
                  <span v-if="m.blood_group" class="text-[10px] bg-rose-100 text-rose-700 font-semibold px-1.5 py-0.5 rounded">{{ m.blood_group }}</span>
                  <span v-if="m.has_citizenship" class="text-[10px] bg-green-100 text-green-700 font-semibold px-1.5 py-0.5 rounded">नागरिकता</span>
                  <template v-if="canMarkDeceased">
                    <button v-if="!m.is_deceased" type="button"
                      @click.stop="openDeceasedModal(m)"
                      class="text-[10px] bg-gray-100 hover:bg-red-50 text-gray-500 hover:text-red-600 border border-gray-200 hover:border-red-200 font-medium px-1.5 py-0.5 rounded transition">
                      <i class="fa-solid fa-skull text-[9px]"></i> मृत्यु भए दर्शाउनुहोस्
                    </button>
                    <button v-else type="button"
                      @click.stop="openDeceasedModal(m)"
                      class="text-[10px] bg-gray-200 hover:bg-gray-300 text-gray-600 font-medium px-1.5 py-0.5 rounded transition">
                      <i class="fa-solid fa-pencil text-[9px]"></i> मृत्यु सम्पादन
                    </button>
                  </template>
                </div>
                <span class="shrink-0 w-7 h-7 flex items-center justify-center rounded text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                  <i :class="expandedMembers.has(idx) ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'" class="text-[10px]"></i>
                </span>
              </div>

              <!-- Expanded detail -->
              <div v-show="expandedMembers.has(idx)" class="member-body border-t border-[#e6e7e9] bg-slate-50/40 px-4 py-3">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3 text-[13px]">
                  <div v-if="m.father_name" class="flex gap-1.5"><span class="text-gray-500 shrink-0">बुवाको नाम:</span><span class="font-semibold text-gray-700">{{ m.father_name }}</span></div>
                  <div class="flex gap-1.5"><span class="text-gray-500 shrink-0">जन्म मिति:</span><span class="font-semibold text-gray-700">{{ m.date_of_birth ? formatDate(m.date_of_birth) : '—' }}</span></div>
                  <div class="flex gap-1.5"><span class="text-gray-500 shrink-0">जातजाति:</span><span class="font-semibold text-gray-700">{{ label('ethnicity', m.ethnicity) || m.ethnicity || '—' }}</span></div>
                  <div class="flex gap-1.5"><span class="text-gray-500 shrink-0">धर्म:</span><span class="font-semibold text-gray-700">{{ label('religion', m.religion) || m.religion || '—' }}</span></div>
                  <div class="flex gap-1.5"><span class="text-gray-500 shrink-0">मातृभाषा:</span><span class="font-semibold text-gray-700">{{ label('mother_tongue', m.mother_tongue) || m.mother_tongue || '—' }}</span></div>
                  <div class="flex gap-1.5"><span class="text-gray-500 shrink-0">रक्त समूह:</span><span class="font-semibold text-gray-700">{{ m.blood_group || '—' }}</span></div>
                  <div class="flex gap-1.5"><span class="text-gray-500 shrink-0">वैवाहिक:</span><span class="font-semibold text-gray-700">{{ label('marital_status', m.marital_status) || '—' }}</span></div>
                  <div class="flex gap-1.5"><span class="text-gray-500 shrink-0">शिक्षा:</span><span class="font-semibold text-gray-700">{{ label('education_level', m.education_level) || '—' }}</span></div>
                  <div v-if="m.school_name" class="flex gap-1.5"><span class="text-gray-500 shrink-0">संस्था:</span><span class="font-semibold text-gray-700 truncate">{{ m.school_name }}</span></div>
                  <div class="flex gap-1.5"><span class="text-gray-500 shrink-0">पेशा:</span><span class="font-semibold text-gray-700">{{ label('occupation_type', m.occupation_type) || '—' }}</span></div>
                  <div class="flex gap-1.5"><span class="text-gray-500 shrink-0">रोजगारी:</span><span class="font-semibold text-gray-700">{{ label('employment_status', m.employment_status) || '—' }}</span></div>
                  <div class="flex gap-1.5"><span class="text-gray-500 shrink-0">बसोबास:</span><span class="font-semibold text-gray-700">{{ label('residence_status', m.residence_status) || '—' }}</span></div>
                  <div v-if="m.member_phone" class="flex gap-1.5"><span class="text-gray-500 shrink-0">फोन:</span><span class="font-semibold text-gray-700">{{ m.member_phone }}</span></div>
                  <div v-if="m.member_email" class="flex gap-1.5"><span class="text-gray-500 shrink-0">इमेल:</span><span class="font-semibold text-gray-700">{{ m.member_email }}</span></div>
                  <div v-if="m.disability_status" class="flex gap-1.5"><span class="text-gray-500 shrink-0">अपाङ्गता:</span><span class="font-semibold text-orange-600">{{ label('disability_type', m.disability_type) || 'छ' }}</span></div>
                  <div v-if="m.disability_card === 'yes'" class="flex gap-1.5"><span class="text-gray-500 shrink-0">परिचयपत्र रंग:</span><span class="font-semibold text-gray-700">{{ label('disability_card_color', m.disability_card_color) || '—' }}</span></div>
                  <div v-if="m.chronic_illness" class="flex gap-1.5"><span class="text-gray-500 shrink-0">दीर्घरोग:</span><span class="font-semibold text-red-600">{{ m.chronic_illness_types?.length ? labels('chronic_illness_type', m.chronic_illness_types) : 'छ' }}</span></div>
                  <!-- Deceased info row -->
                  <div v-if="m.is_deceased" class="col-span-2 md:col-span-3 flex flex-wrap items-center gap-3 mt-1 pt-1.5 border-t border-red-200 bg-red-50 -mx-4 px-4 py-2 rounded-b">
                    <i class="fa-solid fa-skull text-red-400 text-[11px]"></i>
                    <span class="text-[11px] font-semibold text-red-700">मृत्यु भयो</span>
                    <span v-if="m.death_date" class="text-[11px] text-red-600">मिति: {{ m.death_date }}</span>
                    <span v-if="m.death_cause" class="text-[11px] text-red-600">कारण: {{ m.death_cause }}</span>
                    <span v-if="m.death_remarks" class="text-[11px] text-gray-500 italic">{{ m.death_remarks }}</span>
                    <button v-if="canMarkDeceased" type="button" @click.stop="unmarkDeceased(m)"
                      class="ml-auto text-[10px] text-gray-400 hover:text-red-500 underline">हटाउनुहोस्</button>
                  </div>

                  <!-- Documents row -->
                  <div class="col-span-2 md:col-span-3 flex flex-wrap gap-2 mt-1 pt-1.5 border-t border-slate-200">
                    <span class="text-gray-500 text-[11px]">कागजात:</span>
                    <span v-if="m.has_citizenship"      class="text-[11px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-semibold">✓ नागरिकता</span>
                    <span v-if="m.has_voters_card"      class="text-[11px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-semibold">✓ मतदाता</span>
                    <span v-if="m.has_national_id_card" class="text-[11px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-semibold">✓ राष्ट्रिय परिचयपत्र</span>
                    <span v-if="m.has_passport"         class="text-[11px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-semibold">✓ राहदानी</span>
                    <span v-if="m.age && parseInt(m.age) <= 18 && m.has_birth_certificate"
                      class="text-[11px] bg-teal-100 text-teal-700 px-1.5 py-0.5 rounded font-semibold">✓ जन्म दर्ता</span>
                    <span v-if="!m.has_citizenship && !m.has_voters_card && !m.has_national_id_card && !m.has_passport"
                      class="text-[11px] text-gray-400">कुनै कागजात दर्ता छैन</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

      </div>

      <!-- ── Sidebar (1/3) ──────────────────────────────────────────── -->
      <div class="no-print space-y-4">

        <!-- Status + Actions -->
        <div class="no-print bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">अवस्था</h3>
          <div class="flex items-center gap-2 mb-4">
            <span :class="['text-sm px-3 py-1.5 rounded-full font-semibold', statusBadge(household.status)]">
              {{ statusLabel(household.status) }}
            </span>
          </div>
          <div class="space-y-2">
            <a v-if="canEdit" :href="`/households/${household.id}/edit`"
              class="flex items-center justify-center gap-2 w-full rounded-md border border-[#e6e7e9] px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
              <i class="fa-solid fa-pen text-gray-400"></i>
              सम्पादन गर्नुस्
              <span v-if="household.status === 'published'" class="ml-auto text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-semibold">प्रकाशित</span>
            </a>
            <button v-if="canSubmit" @click="submitHousehold" :disabled="actionLoading"
              class="flex items-center justify-center gap-2 w-full rounded-md bg-[#206bc4] hover:bg-[#1a5ba8] px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60">
              <i :class="actionLoading ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-paper-plane'"></i>
              पेस गर्नुस्
            </button>
            <button v-if="canApprove" @click="approveHousehold" :disabled="actionLoading"
              class="flex items-center justify-center gap-2 w-full rounded-md bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60">
              <i :class="actionLoading ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-circle-check'"></i>
              स्वीकृत गर्नुस्
            </button>
            <button v-if="canReject" @click="showRejectModal = true" :disabled="actionLoading"
              class="flex items-center justify-center gap-2 w-full rounded-md bg-red-600 hover:bg-red-700 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60">
              <i class="fa-solid fa-circle-xmark"></i>
              अस्वीकार गर्नुस्
            </button>
            <button v-if="canPublish" @click="publishHousehold" :disabled="actionLoading"
              class="flex items-center justify-center gap-2 w-full rounded-md bg-green-600 hover:bg-green-700 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60">
              <i :class="actionLoading ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-globe'"></i>
              प्रकाशित गर्नुस्
            </button>
            <!-- Ward reviewer: request edit on published records -->
            <button v-if="canRequestEdit" @click="showRequestModal = true"
              class="flex items-center justify-center gap-2 w-full rounded-md border border-orange-300 bg-orange-50 hover:bg-orange-100 px-4 py-2 text-sm font-medium text-orange-700 transition-colors">
              <i class="fa-solid fa-pen-to-square text-orange-400"></i> सम्पादन अनुरोध
            </button>
            <div v-if="hasPendingEditRequest"
              class="flex items-center gap-2 w-full rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-700">
              <i class="fa-solid fa-clock text-amber-400"></i> अनुरोध पेस गरिएको — समीक्षा बाँकी
            </div>
            <!-- Print button -->
            <button @click="printPage"
              class="flex items-center justify-center gap-2 w-full rounded-md border border-violet-200 bg-violet-50 hover:bg-violet-100 px-4 py-2 text-sm font-medium text-violet-700 transition-colors">
              <i class="fa-solid fa-print text-violet-400"></i> प्रिन्ट
            </button>
            <!-- Download PDF button -->
            <a :href="`/households/${household.id}/pdf`" target="_blank"
              class="flex items-center justify-center gap-2 w-full rounded-md border border-rose-200 bg-rose-50 hover:bg-rose-100 px-4 py-2 text-sm font-medium text-rose-700 transition-colors">
              <i class="fa-solid fa-file-pdf text-rose-400"></i> PDF डाउनलोड
            </a>
          </div>
          <div class="mt-4 pt-4 border-t border-gray-50">
            <a href="/households" class="flex items-center gap-2 text-xs text-gray-400 hover:text-gray-600 transition-colors">
              <i class="fa-solid fa-arrow-left"></i> सूचीमा फर्कनुस्
            </a>
          </div>
        </div>

        <!-- Photo Card -->
        <div class="no-print bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="flex items-center gap-2 px-4 py-2.5 border-b border-[#e6e7e9] bg-gray-50">
            <i class="fa-solid fa-camera text-violet-500 text-xs"></i>
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">फोटो</h3>
          </div>
          <div class="p-3 space-y-2">
            <!-- Has photo URL → show image -->
            <div v-if="photoUrl" class="rounded-md overflow-hidden border border-[#e6e7e9] bg-gray-100">
              <img :src="photoUrl" :alt="`Photo ${household.photo_number}`"
                class="w-full object-cover"
                style="max-height:320px;"
                @error="e => { const t = e.target; if (photoUrlFallback && t.src !== photoUrlFallback && t.src !== photoUrlFallback2) { t.src = photoUrlFallback } else if (photoUrlFallback2 && t.src !== photoUrlFallback2) { t.src = photoUrlFallback2 } else { t.closest('div').style.display='none' } }" />
            </div>
            <!-- Has number but no base URL -->
            <div v-else-if="household.photo_number" class="rounded-md border border-dashed border-amber-200 bg-amber-50 flex flex-col items-center justify-center py-5 gap-1.5">
              <i class="fa-solid fa-link-slash text-amber-300 text-xl"></i>
              <p class="text-xs text-amber-600 text-center leading-snug">फोटो Base URL<br>सेटिङ गरिएको छैन</p>
            </div>
            <!-- No photo number at all -->
            <div v-else class="rounded-md border border-dashed border-gray-200 bg-gray-50 flex flex-col items-center justify-center py-6 gap-1.5">
              <i class="fa-solid fa-camera text-gray-300 text-2xl"></i>
              <p class="text-xs text-gray-400">फोटो नम्बर राखिएको छैन</p>
            </div>
            <!-- Footer row -->
            <div class="flex items-center justify-between pt-0.5">
              <div>
                <p class="text-[10px] text-gray-400">फोटो नम्बर</p>
                <p class="text-xs font-mono font-semibold text-gray-700">{{ household.photo_number || '—' }}</p>
              </div>
              <a v-if="photoUrl" :href="photoUrl" target="_blank"
                class="flex items-center gap-1 text-xs text-[#206bc4] hover:underline">
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i> ठूलो
              </a>
            </div>
          </div>
        </div>

        <!-- Quick Info -->
        <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">छोटो जानकारी</h3>
          <div class="space-y-3">
            <div><p class="text-xs text-gray-400">अद्वितीय कोड</p><p class="text-sm font-mono font-semibold text-indigo-700">{{ household.unique_code }}</p></div>
            <div><p class="text-xs text-gray-400">गणक</p><p class="text-sm font-medium text-gray-700">{{ household.enumerator?.name || '—' }}</p></div>
            <div><p class="text-xs text-gray-400">सर्वेक्षण मिति</p><p class="text-sm font-medium text-gray-700">{{ formatDate(household.survey_date) }}</p></div>
            <div v-if="household.gps_lat && household.gps_lng">
              <p class="text-xs text-gray-400">GPS</p>
              <p class="text-xs font-mono text-gray-600">{{ household.gps_lat }}, {{ household.gps_lng }}</p>
            </div>
            <div v-if="household.survey_data?.social">
              <p class="text-xs text-gray-400">परिवार सदस्य</p>
              <p class="text-sm font-medium text-gray-700">
                पु {{ household.survey_data.social.male_num || 0 }} /
                म {{ household.survey_data.social.female_num || 0 }} /
                अ {{ household.survey_data.social.other_num || 0 }}
              </p>
            </div>
          </div>
        </div>

        <!-- Data Quality Flags -->
        <div v-if="activeFlagBadges.length" class="no-print bg-amber-50 border border-amber-200 rounded-md p-4">
          <h3 class="text-[12px] font-semibold text-amber-800 flex items-center gap-2 mb-3">
            <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
            डेटा गुणस्तर चेतावनी
            <span class="text-[10px] font-normal text-amber-600 ml-1">Data Quality Flags</span>
          </h3>
          <div class="flex flex-wrap gap-2">
            <span v-for="b in activeFlagBadges" :key="b.key"
              :class="['inline-flex items-center gap-1.5 text-[12px] font-medium px-3 py-1.5 rounded-full border', b.cls]">
              <i :class="[b.icon, 'text-[11px]']"></i>
              {{ b.label }}
            </span>
          </div>
          <p class="text-[11px] text-amber-600 mt-2.5">
            <i class="fa-solid fa-circle-info mr-1"></i>
            यी समस्याहरू डेटा प्रविष्टिमा त्रुटि संकेत गर्न सक्छन् — समीक्षा गर्नुअघि जाँच गर्नुहोस्।
          </p>
        </div>

        <!-- Field Verification -->
        <FieldVerificationCard
          :verification="fieldVerification"
          :can-verify="canVerify"
          :save-url="`/households/${household.id}/field-verify`"
        />

        <!-- Submission Log -->
        <div v-if="household.submissionLogs?.length" class="no-print bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
            <i class="fa-solid fa-timeline text-indigo-400"></i> कार्यक्रम लग
          </h3>
          <div class="relative">
            <div class="absolute left-3 top-0 bottom-0 w-px bg-gray-100"></div>
            <div class="space-y-4">
              <div v-for="log in household.submissionLogs" :key="log.id" class="relative pl-8">
                <div class="absolute left-0 top-1 w-6 h-6 rounded-full bg-white border-2 border-indigo-200 flex items-center justify-center">
                  <div class="w-2 h-2 rounded-full bg-indigo-400"></div>
                </div>
                <div>
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-xs font-semibold text-gray-700">{{ log.actor?.name || '—' }}</span>
                    <span class="text-xs text-gray-400">—</span>
                    <span :class="['text-xs px-1.5 py-0.5 rounded font-medium', actionBadge(log.action)]">{{ log.action }}</span>
                  </div>
                  <p v-if="log.notes" class="text-xs text-gray-500 mt-0.5">{{ log.notes }}</p>
                  <p class="text-xs text-gray-400 mt-0.5">{{ formatDateTime(log.created_at) }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- ── Edit Request Modal ──────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="showRequestModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
      @click.self="showRequestModal = false">
      <div class="bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-md mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-pen-to-square text-orange-500"></i>
          </div>
          <div>
            <h3 class="text-[15px] font-semibold text-gray-900">सम्पादन अनुरोध</h3>
            <p class="text-[12px] text-gray-400">घरधुरी: <span class="font-mono">{{ household.unique_code }}</span></p>
          </div>
        </div>
        <p class="text-sm text-gray-600 mb-3">यो प्रकाशित रेकर्डमा सम्पादन आवश्यक पर्नुको कारण उल्लेख गर्नुहोस्:</p>
        <textarea
          v-model="editRequestReason"
          rows="4"
          placeholder="उदाहरण: नयाँ सदस्य जन्मेको छ / मृत्यु भएको छ / ठेगाना परिवर्तन भएको छ..."
          class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-300 resize-none"
        ></textarea>
        <div class="flex gap-3 mt-4">
          <button @click="showRequestModal = false"
            class="flex-1 rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
            रद्द गर्नुस्
          </button>
          <button @click="submitEditRequest" :disabled="requestLoading || !editRequestReason.trim()"
            class="flex-1 rounded-md bg-orange-500 hover:bg-orange-600 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60">
            <i :class="requestLoading ? 'fa-solid fa-spinner fa-spin mr-1' : 'fa-solid fa-paper-plane mr-1'"></i>
            अनुरोध पठाउनुस्
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- ── Deceased Modal ─────────────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="deceasedModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="flex items-center gap-3 px-5 py-4 border-b border-[#e6e7e9]">
          <i class="fa-solid fa-skull text-red-500"></i>
          <h3 class="text-[15px] font-semibold text-[#1d273b]">मृत्यु विवरण</h3>
          <span class="text-[13px] text-gray-400 ml-1">— {{ deceasedMember?.full_name }}</span>
          <button type="button" @click="deceasedModal = false" class="ml-auto text-gray-400 hover:text-gray-600">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        <div class="px-5 py-4 space-y-4">
          <div>
            <label class="block text-[12px] font-medium text-gray-600 mb-1">मृत्यु मिति</label>
            <NepaliDatePicker v-model="deceasedForm.death_date" placeholder="मृत्यु मिति (बि.सं.)" />
          </div>
          <div>
            <label class="block text-[12px] font-medium text-gray-600 mb-1">मृत्युको कारण</label>
            <select v-model="deceasedForm.death_cause"
              class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] focus:ring-2 focus:ring-red-500/20 focus:border-red-400 outline-none bg-white">
              <option value="">— छान्नुहोस् —</option>
              <option v-for="c in (props.surveyOptions?.death_cause ?? [])" :key="c.value" :value="c.value">{{ c.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-[12px] font-medium text-gray-600 mb-1">थप टिप्पणी</label>
            <textarea v-model="deceasedForm.death_remarks" rows="2"
              class="w-full rounded border border-[#e6e7e9] px-3 py-2 text-[13px] focus:ring-2 focus:ring-red-500/20 focus:border-red-400 outline-none resize-none"
              placeholder="वैकल्पिक टिप्पणी…"></textarea>
          </div>
        </div>
        <div class="flex items-center justify-between px-5 py-4 border-t border-[#e6e7e9] gap-3">
          <button v-if="deceasedMember?.is_deceased" type="button" @click="unmarkDeceased(deceasedMember); deceasedModal = false"
            class="text-[12px] text-gray-400 hover:text-red-500 underline">
            मृत्यु स्थिति हटाउनुहोस्
          </button>
          <div class="flex gap-2 ml-auto">
            <button type="button" @click="deceasedModal = false"
              class="px-4 py-2 text-[13px] border border-[#e6e7e9] rounded-md text-gray-600 hover:bg-gray-50 transition">
              रद्द गर्नुहोस्
            </button>
            <button type="button" @click="saveDeceased" :disabled="deceasedSaving"
              class="px-4 py-2 text-[13px] bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white font-semibold rounded-md transition flex items-center gap-2">
              <i v-if="deceasedSaving" class="fa-solid fa-spinner fa-spin text-xs"></i>
              सुरक्षित गर्नुहोस्
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Reject modal -->
  <Teleport to="body">
    <div v-if="showRejectModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
      @click.self="showRejectModal = false">
      <div class="bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-md mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-circle-xmark text-red-500"></i>
          </div>
          <div>
            <h3 class="text-[15px] font-semibold text-gray-900">अस्वीकार गर्नुस्</h3>
            <p class="text-[12px] text-gray-400">घरधुरी: <span class="font-mono">{{ household.unique_code }}</span></p>
          </div>
        </div>
        <p class="text-sm text-gray-600 mb-3">अस्वीकार गर्नुको कारण उल्लेख गर्नुहोस् (अनिवार्य):</p>
        <textarea
          v-model="rejectComment"
          rows="3"
          placeholder="उदाहरण: विवरण अपूर्ण छ / GPS गलत छ / सदस्य संख्या मेल खाँदैन..."
          class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-300 resize-none"
        ></textarea>
        <div class="flex gap-3 mt-4">
          <button @click="showRejectModal = false"
            class="flex-1 rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
            रद्द गर्नुस्
          </button>
          <button @click="submitReject" :disabled="rejectLoading || !rejectComment.trim()"
            class="flex-1 rounded-md bg-red-600 hover:bg-red-700 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60">
            <i :class="rejectLoading ? 'fa-solid fa-spinner fa-spin mr-1' : 'fa-solid fa-circle-xmark mr-1'"></i>
            अस्वीकार गर्नुस्
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, h } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import FieldVerificationCard from '@/components/FieldVerificationCard.vue'
import NepaliDatePicker      from '@/components/NepaliDatePicker.vue'

const page = usePage()
function printPage() { window.print() }

const props = defineProps({
  household:              { type: Object,  required: true },
  canEdit:                { type: Boolean, default: false },
  canSubmit:              { type: Boolean, default: false },
  canApprove:             { type: Boolean, default: false },
  canReject:              { type: Boolean, default: false },
  canPublish:             { type: Boolean, default: false },
  canRequestEdit:         { type: Boolean, default: false },
  hasPendingEditRequest:  { type: Boolean, default: false },
  canVerify:              { type: Boolean, default: false },
  canMarkDeceased:        { type: Boolean, default: false },
  fieldVerification:      { type: Object,  default: null },
  surveyOptions:          { type: Object,  default: () => ({}) },
  dataFlags:              { type: Object,  default: () => ({}) },
  flagMeta:               { type: Object,  default: () => ({}) },
})

const activeFlagBadges = computed(() => {
  const FLAG_COLORS = { warning: 'bg-amber-50 border-amber-200 text-amber-800', danger: 'bg-red-50 border-red-200 text-red-700' }
  return Object.entries(props.flagMeta ?? {})
    .filter(([key]) => props.dataFlags?.[key])
    .map(([key, [label, icon, severity]]) => ({ key, label, icon, cls: FLAG_COLORS[severity] ?? FLAG_COLORS.warning }))
})

const photoUrl = computed(() => {
  const base = usePage().props.auth?.lg?.photo_dir?.household_base_url
  const num  = props.household.photo_number
  if (!base || !num) return null
  const hasExt = /\.[a-zA-Z]{3,4}$/.test(num)
  return `${base}/${hasExt ? num : num + '.jpg'}?v=${encodeURIComponent(num)}`
})
const photoUrlFallback = computed(() => {
  const base = usePage().props.auth?.lg?.photo_dir?.household_base_url
  const num  = props.household.photo_number
  if (!base || !num) return null
  const hasExt = /\.[a-zA-Z]{3,4}$/.test(num)
  return hasExt ? null : `${base}/${num}.jpeg?v=${encodeURIComponent(num)}`
})
const photoUrlFallback2 = computed(() => {
  const base = usePage().props.auth?.lg?.photo_dir?.household_base_url
  const num  = props.household.photo_number
  if (!base || !num) return null
  const hasExt = /\.[a-zA-Z]{3,4}$/.test(num)
  return hasExt ? null : `${base}/${num}.png?v=${encodeURIComponent(num)}`
})

// ── Survey option helpers ─────────────────────────────────────────────────────
const koboFallback = {
  occupation_type: {
    agricultureandanimalhusbandry: 'कृषि तथा पशुपालन', business: 'व्यापार/व्यवसाय',
    governmentemployee: 'सरकारी जागिर', employmentinprivatesector: 'निजी क्षेत्र जागिर',
    foreignemployment: 'वैदेशिक रोजगार', dailywage: 'दैनिक मजदुरी',
    selfemployment: 'स्वरोजगार', supportfamilyjobs: 'घरेलु काम',
    student: 'विद्यार्थी', unemployed: 'बेरोजगार', other: 'अन्य',
  },
  relation_to_head: {
    headofhouse_self: 'घरमुली आफै', husband: 'श्रीमान', wife: 'श्रीमती',
    daughterinlaw: 'बुहारी', soninlaw: 'ज्वाइँ', grandson: 'नाति', granddaughter: 'नातिनी',
    greatgrandson: 'पनाती', greatgranddaughter: 'पनातिनी',
  },
  marital_status: {
    singlemarried: 'विवाहित', multimarried: 'बहु विवाह',
    remarried: 'पुनः विवाह', widow: 'विधुर/विधवा', seperated: 'अलग',
  },
  education_level: {
    illitrate: 'निरक्षर (पढ्न लेख्न नसक्ने)', literate: 'साक्षर (पढ्न लेख्न सक्ने)',
    basiceducation1_5: 'आधारभूत (कक्षा १-५)', basiceducation6_8: 'आधारभूत (कक्षा ६-८)',
    secondaryschool: 'माध्यमिक (कक्षा ९-१०)', slc: 'SLC/SEE',
    intermediate: '+२/प्रवीणता', mphil: 'एमफिल', phd: 'पिएचडी', other: 'अन्य',
  },
  mother_tongue: {
    bantawa: 'बान्तवा (राई)', nepali: 'नेपाली', limbu: 'लिम्बु', maithili: 'मैथली',
    bhojpuri: 'भोजपुरी', hindi: 'हिन्दी', tharu: 'थारु', tamang: 'तामांग',
    nepalbhasa: 'नेपाल भाषा', newar: 'नेपाल भाषा (नेवारी)', magar: 'मगर',
    gurung: 'गुरुङ', rai: 'राई', sherpa: 'शेर्पा', other: 'अन्य',
  },
  ethnicity: {
    brahmin: 'ब्राह्मण', chettri: 'क्षेत्री', rai: 'राई', limbu: 'लिम्बु',
    magar: 'मगर', tamang: 'तामांग', gurung: 'गुरुङ', newar: 'नेवार',
    thakuri: 'ठकुरी', tharu: 'थारु', kaami: 'कामी/बिश्वकर्मा', damai: 'दमाई',
    sarki: 'सार्की', yadav: 'यादव', musalmaan: 'मुसलमान', sherpa: 'शेर्पा', other: 'अन्य',
  },
  religion: {
    hindu: 'हिन्दू', buddhist: 'बौद्ध', kirant: 'किराँत',
    christian: 'क्रिस्चियन', muslim: 'मुस्लिम', prakriti: 'प्रकृति',
    athiest: 'नास्तिक', bone: 'बोन', other: 'अन्य',
  },
  water_source: {
    tap_house: 'धाराको पानी (घरभित्र)', tap_public: 'धाराको पानी (बाहिर)',
    tubewell: 'ट्युबवेल/हाते धारा', covered_well: 'ढाकिएको कुवा',
    open_well: 'खुला कुवा', jaruwa: 'मूलको पानी/जरुवा',
    bottledwater: 'जार/बोतलको पानी', river: 'नदी/खोला',
  },
  toilet_type: {
    standard_septictank: 'फ्लस (सेप्टिक ट्याङ्क)', standard_publicdrainage: 'फ्लस (सार्वजनिक ढल)',
    normal: 'साधारण शौचालय', no_toilet: 'शौचालय छैन',
  },
  electricity_source: {
    natonalelectricgrid: 'राष्ट्रिय ग्रिड', microhydro: 'लघु जलविद्युत',
    kerosene: 'मट्टितेल', solarenergy: 'सोलार',
  },
  cooking_fuel: { lp_gas: 'ग्याँस (LPG)', guitha: 'गुइँठा' },
  roof_type: {
    straw: 'फुस/खर', cgi_sheet: 'जस्ता', tiles_stone: 'टायल/ढुंगा',
    wood: 'काठ', mud: 'माटो',
  },
  wall_type: {
    mud_and_stone: 'माटो र ढुंगा/इट्टा', cement_and_stone: 'सिमेन्ट र ढुंगा/इट्टा',
    center_bera: 'सेन्टर बेरा',
  },
  primary_income_source: { selfemployment: 'स्वरोजगार', other_income: 'अन्य' },
  income_source:         { selfemployment: 'स्वरोजगार', other_income: 'अन्य' },
  annual_income_range: {
    below_50000: 'रु ५०,००० भन्दा कम', '50000_100000': 'रु ५०,०००–१,००,०००',
    '100000_200000': 'रु १,००,०००–२,००,०००', '200000_500000': 'रु २,००,०००–५,००,०००',
    above_500000: 'रु ५,००,००० माथि',
  },
  annual_expenditure_range: {
    below_50000: 'रु ५०,००० भन्दा कम', '50000_100000': 'रु ५०,०००–१,००,०००',
    '100000_200000': 'रु १,००,०००–२,००,०००', '200000_500000': 'रु २,००,०००–५,००,०००',
    above_500000: 'रु ५,००,००० माथि',
  },
  disability_type: {
    blind: 'दृष्टिसम्बन्धी', deaf: 'श्रवणसम्बन्धी',
    speech: 'स्वर बोलाई', multiple_disability: 'बहुअपाङ्गता',
  },
}

function label(category, value) {
  if (!value) return null
  const opts = props.surveyOptions[category] ?? []
  const lower = String(value).toLowerCase()
  const found = opts.find(o => o.value === value || String(o.value).toLowerCase() === lower)
  if (found) return found.label
  return koboFallback[category]?.[lower] ?? value
}

function labels(category, values) {
  if (!values?.length) return null
  return values.map(v => label(category, v)).join(', ')
}

// ── Display helpers ───────────────────────────────────────────────────────────
function str(v)    { return v !== null && v !== undefined && v !== '' ? String(v) : null }
function bool(v)   { return v === true ? 'छ' : v === false ? 'छैन' : null }
function yesNo(v)  { return v === 'yes' || v === true ? 'छ' : v === 'no' || v === false ? 'छैन' : null }

const genderMap = { male: 'पुरुष', female: 'महिला', other: 'अन्य' }

// ── Crop production helpers ───────────────────────────────────────────────────
const hasCropData = computed(() => {
  const cp = props.household?.survey_data?.cropProduction
  if (!cp) return false
  return Object.values(cp).some(arr => Array.isArray(arr) && arr.length)
})

function cropCount(key) {
  const arr = props.household?.survey_data?.cropProduction?.[key]
  return arr?.length ? arr.length + ' किसिम' : null
}

// ── Deceased modal ────────────────────────────────────────────────────────────
const deceasedModal   = ref(false)
const deceasedMember  = ref(null)
const deceasedForm    = ref({ death_date: '', death_cause: '', death_remarks: '' })
const deceasedSaving  = ref(false)

function openDeceasedModal(member) {
  deceasedMember.value = member
  deceasedForm.value   = {
    death_date:    member.death_date ?? '',
    death_cause:   member.death_cause ?? '',
    death_remarks: member.death_remarks ?? '',
  }
  deceasedModal.value = true
}

function saveDeceased() {
  if (deceasedSaving.value) return
  deceasedSaving.value = true
  router.patch(
    `/households/${props.household.id}/members/${deceasedMember.value.id}/deceased`,
    deceasedForm.value,
    {
      preserveScroll: true,
      onFinish: () => { deceasedSaving.value = false; deceasedModal.value = false },
    }
  )
}

function unmarkDeceased(member) {
  if (!confirm(`${member.full_name} को मृत्यु स्थिति हटाउने?`)) return
  router.delete(
    `/households/${props.household.id}/members/${member.id}/deceased`,
    { preserveScroll: true }
  )
}

// ── Member accordion ──────────────────────────────────────────────────────────
const expandedMembers = ref(new Set(
  (props.household.members ?? []).map((m, i) => m.is_household_head ? i : null).filter(i => i !== null)
))

function toggleMember(idx) {
  const next = new Set(expandedMembers.value)
  if (next.has(idx)) next.delete(idx)
  else next.add(idx)
  expandedMembers.value = next
}

// ── Inline components (render fn) ────────────────────────────────────────────
const DetailRow = {
  props: ['label', 'value'],
  render() {
    return h('div', [
      h('p', { class: 'text-[12px] text-gray-500 mb-1 leading-snug' }, this.label),
      h('p', { class: 'text-[15px] font-medium text-gray-800 leading-snug' }, this.value ?? '—'),
    ])
  },
}

const colorMap = {
  orange: 'bg-orange-500',  blue:   'bg-blue-600',
  green:  'bg-green-600',   violet: 'bg-violet-600',
  rose:   'bg-rose-500',    cyan:   'bg-cyan-600',
  amber:  'bg-amber-500',   teal:   'bg-teal-600',
  yellow: 'bg-yellow-500',  red:    'bg-red-500',
  purple: 'bg-purple-600',  indigo: 'bg-indigo-600',
}

const SectionHead = {
  props: ['icon', 'color', 'title'],
  render() {
    const bg = colorMap[this.color] ?? 'bg-gray-500'
    return h('div', {
      class: `section-head-bar -mx-5 -mt-5 px-5 py-3 mb-5 rounded-t-md ${bg} flex items-center gap-3`,
    }, [
      h('span', { class: 'w-7 h-7 rounded bg-white/20 flex items-center justify-center flex-shrink-0' }, [
        h('i', { class: `fa-solid ${this.icon} text-white text-xs` }),
      ]),
      h('span', { class: 'text-[15px] font-semibold text-white tracking-wide' }, this.title),
    ])
  },
}

// ── Status helpers ────────────────────────────────────────────────────────────
function statusBadge(s) {
  const map = { draft: 'bg-gray-100 text-gray-600', submitted: 'bg-yellow-100 text-yellow-700',
    ward_approved: 'bg-blue-100 text-blue-700', rejected: 'bg-red-100 text-red-700',
    published: 'bg-green-100 text-green-700' }
  return map[s] ?? 'bg-gray-100 text-gray-600'
}

function statusLabel(s) {
  const map = { draft: 'मस्यौदा', submitted: 'पेस भएको', ward_approved: 'वडा अनुमोदित',
    rejected: 'अस्वीकृत', published: 'प्रकाशित' }
  return map[s] ?? s
}

function actionBadge(a) {
  const map = { submitted: 'bg-yellow-100 text-yellow-700', approved: 'bg-blue-100 text-blue-700',
    rejected: 'bg-red-100 text-red-600', published: 'bg-green-100 text-green-700' }
  return map[a] ?? 'bg-gray-100 text-gray-600'
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ne-NP')
}

function formatDateTime(d) {
  if (!d) return ''
  return new Date(d).toLocaleString('ne-NP')
}

// ── Actions ───────────────────────────────────────────────────────────────────
const actionLoading = ref(false)

function submitHousehold() {
  if (!confirm('के तपाईं यो घरधुरी पेस गर्न निश्चित हुनुहुन्छ?')) return
  actionLoading.value = true
  router.post(`/households/${props.household.id}/submit`, {}, {
    onFinish: () => { actionLoading.value = false },
  })
}

function publishHousehold() {
  if (!confirm('के तपाईं यो घरधुरी प्रकाशित गर्न निश्चित हुनुहुन्छ?')) return
  actionLoading.value = true
  router.patch(`/admin/households/${props.household.id}/publish`, {}, {
    onFinish: () => { actionLoading.value = false },
  })
}

const showRejectModal = ref(false)
const rejectComment   = ref('')
const rejectLoading   = ref(false)

function approveHousehold() {
  if (!confirm('के तपाईं यो घरधुरी स्वीकृत गर्न निश्चित हुनुहुन्छ?')) return
  actionLoading.value = true
  router.patch(`/review/households/${props.household.id}/approve`, {}, {
    onFinish: () => { actionLoading.value = false },
  })
}

function submitReject() {
  if (!rejectComment.value.trim()) return
  rejectLoading.value = true
  router.patch(`/review/households/${props.household.id}/reject`, { comment: rejectComment.value }, {
    onSuccess: () => { showRejectModal.value = false; rejectComment.value = '' },
    onFinish:  () => { rejectLoading.value = false },
  })
}

// ── Edit Request ──────────────────────────────────────────────────────────────
const showRequestModal  = ref(false)
const editRequestReason = ref('')
const requestLoading    = ref(false)

function submitEditRequest() {
  if (!editRequestReason.value.trim()) return
  requestLoading.value = true
  router.post('/edit-requests', {
    requestable_type: 'household',
    requestable_id:   props.household.id,
    reason:           editRequestReason.value,
  }, {
    onSuccess: () => { showRequestModal.value = false; editRequestReason.value = '' },
    onFinish:  () => { requestLoading.value = false },
  })
}
</script>

<style>
/* ── Screen: section head bar always shows color ── */
.section-head-bar {
  print-color-adjust: exact;
  -webkit-print-color-adjust: exact;
}

@media print {
  @page { size: A4; margin: 10mm 12mm; }

  /* ── CRITICAL: un-clip the layout so all pages print ── */
  html, body,
  #app,
  [data-page],
  div.flex.h-screen,
  div.flex.flex-col.flex-1,
  main {
    overflow: visible !important;
    height: auto !important;
    max-height: none !important;
  }

  /* Force color printing for colored headers */
  * { print-color-adjust: exact !important; -webkit-print-color-adjust: exact !important; }

  /* ── Hide UI chrome ── */
  .no-print { display: none !important; }

  /* ── Page structure: collapse to single column ── */
  .grid.grid-cols-1.lg\:grid-cols-3 {
    display: block !important;
  }
  .lg\:col-span-2 {
    width: 100% !important;
    max-width: 100% !important;
  }

  /* ── Sections: compact cards ── */
  section {
    break-inside: avoid;
    page-break-inside: avoid;
    margin-bottom: 6pt !important;
    padding: 14pt !important;
    box-shadow: none !important;
    border: 0.5pt solid #d1d5db !important;
    border-radius: 4pt !important;
  }

  /* ── Section head bar: shorter, print colors ── */
  .section-head-bar {
    margin: -14pt -14pt 8pt -14pt !important;
    padding: 5pt 14pt !important;
    border-radius: 4pt 4pt 0 0 !important;
  }
  .section-head-bar span:first-child { width: 18pt !important; height: 18pt !important; }
  .section-head-bar span:last-child  { font-size: 9pt !important; }

  /* ── Detail grids: 4 columns for compactness ── */
  .grid.grid-cols-2,
  .grid.sm\:grid-cols-3,
  .grid.md\:grid-cols-3 {
    grid-template-columns: repeat(4, 1fr) !important;
    gap: 4pt 8pt !important;
  }

  /* ── Text: tighten sizes ── */
  p, span, td, th, div { font-size: 8pt !important; line-height: 1.35 !important; }
  .text-xs  { font-size: 7pt  !important; }
  .text-sm  { font-size: 8pt  !important; }
  .text-\[11px\], .text-\[12px\] { font-size: 7pt  !important; }
  .text-\[13px\] { font-size: 8pt !important; }

  /* ── Table: compact ── */
  table { font-size: 7pt !important; width: 100% !important; }
  td, th { padding: 2pt 4pt !important; }

  /* ── Member accordion: force all bodies visible ── */
  .member-body { display: block !important; }

  /* ── Reduce spacing helpers ── */
  .space-y-2 > * + *, .space-y-3 > * + *, .space-y-4 > * + * { margin-top: 4pt !important; }
  .mb-4 { margin-bottom: 4pt !important; }
  .mt-4 { margin-top: 4pt !important; }
  .py-3, .py-2 { padding-top: 3pt !important; padding-bottom: 3pt !important; }
  .px-4, .px-3 { padding-left: 6pt !important; padding-right: 6pt !important; }

  /* ── Print-only header: show ── */
  .print-only { display: block !important; }

  /* ── Borders: subtle ── */
  .border, .border-t, .border-b { border-color: #e5e7eb !important; }

  /* ── Shadows: remove ── */
  .shadow-sm, .shadow { box-shadow: none !important; }
}
</style>
