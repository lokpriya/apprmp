<template>
  <div id="member-print-area" class="max-w-5xl mx-auto">

    <!-- Breadcrumb -->
    <div class="no-print flex items-center gap-2 text-[12px] text-gray-400 mb-4">
      <a href="/households" class="hover:text-indigo-600 transition-colors">घरधुरी सूची</a>
      <i class="fa-solid fa-chevron-right text-[9px]"></i>
      <a :href="`/households/${household.id}`" class="hover:text-indigo-600 transition-colors">{{ household.unique_code }}</a>
      <i class="fa-solid fa-chevron-right text-[9px]"></i>
      <span class="text-gray-600 font-medium">{{ member.full_name || 'सदस्य विवरण' }}</span>
    </div>

    <!-- ── Hero Header ───────────────────────────────────────────────────── -->
    <div class="relative rounded-xl overflow-hidden mb-6 shadow-md"
         :class="member.is_household_head ? 'bg-gradient-to-br from-indigo-700 via-indigo-600 to-blue-600'
                                          : 'bg-gradient-to-br from-slate-700 via-slate-600 to-gray-600'">
      <!-- subtle pattern -->
      <div class="absolute inset-0 opacity-10"
           style="background-image:radial-gradient(circle at 20% 50%,white 1px,transparent 1px),radial-gradient(circle at 80% 20%,white 1px,transparent 1px);background-size:30px 30px"></div>

      <div class="relative flex flex-col sm:flex-row items-start sm:items-center gap-5 px-6 py-7">

        <!-- Avatar -->
        <div class="w-20 h-20 rounded-2xl flex items-center justify-center text-3xl font-black text-white shrink-0 shadow-lg"
             :class="member.is_household_head ? 'bg-white/20 ring-2 ring-white/40' : 'bg-white/15 ring-2 ring-white/30'">
          {{ initials }}
        </div>

        <!-- Name + meta -->
        <div class="flex-1 min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <h1 class="text-xl font-bold text-white">{{ member.full_name || 'अज्ञात' }}</h1>
            <span v-if="member.is_household_head"
                  class="text-[11px] bg-yellow-400/20 text-yellow-200 border border-yellow-400/30 font-bold px-2 py-0.5 rounded-full">
              <i class="fa-solid fa-crown text-[9px] mr-0.5"></i> घरमुली
            </span>
          </div>
          <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-white/80 text-[13px]">
            <span v-if="member.age"><i class="fa-regular fa-calendar text-white/50 text-[11px] mr-1"></i>{{ member.age }} वर्ष</span>
            <span v-if="member.gender">• {{ genderMap[member.gender] ?? member.gender }}</span>
            <span v-if="member.relation_to_head">• {{ label('relation_to_head', member.relation_to_head) || member.relation_to_head }}</span>
            <span v-if="member.ethnicity">• {{ label('ethnicity', member.ethnicity) || member.ethnicity }}</span>
          </div>
          <div class="mt-2 flex flex-wrap gap-2">
            <span v-if="member.blood_group"
                  class="inline-flex items-center gap-1 text-[11px] bg-rose-500/30 text-rose-200 border border-rose-400/30 px-2.5 py-0.5 rounded-full font-semibold">
              <i class="fa-solid fa-droplet text-[9px]"></i> {{ member.blood_group }}
            </span>
            <span v-if="member.disability_status"
                  class="inline-flex items-center gap-1 text-[11px] bg-orange-400/20 text-orange-200 border border-orange-400/30 px-2.5 py-0.5 rounded-full font-semibold">
              <i class="fa-solid fa-wheelchair text-[9px]"></i> अपाङ्गता
            </span>
            <span v-if="member.chronic_illness"
                  class="inline-flex items-center gap-1 text-[11px] bg-red-400/20 text-red-200 border border-red-400/30 px-2.5 py-0.5 rounded-full font-semibold">
              <i class="fa-solid fa-heart-pulse text-[9px]"></i> दीर्घरोग
            </span>
            <span v-if="member.ss_allowance"
                  class="inline-flex items-center gap-1 text-[11px] bg-green-400/20 text-green-200 border border-green-400/30 px-2.5 py-0.5 rounded-full font-semibold">
              <i class="fa-solid fa-hand-holding-dollar text-[9px]"></i> सामाजिक सुरक्षा
            </span>
          </div>
        </div>

        <!-- Top-right: action buttons + household chip -->
        <div class="shrink-0 flex flex-col sm:flex-row items-end sm:items-center gap-2">
          <!-- Print / PDF buttons -->
          <div class="no-print flex items-center gap-2">
            <button @click="printPage"
                    class="flex items-center gap-1.5 bg-white/15 hover:bg-white/25 transition-colors rounded-lg px-3 py-2 text-white text-[12px] font-medium border border-white/20">
              <i class="fa-solid fa-print text-[11px]"></i>
              <span class="hidden sm:inline">प्रिन्ट</span>
            </button>
            <button @click="exportPDF"
                    class="flex items-center gap-1.5 bg-rose-500/30 hover:bg-rose-500/50 transition-colors rounded-lg px-3 py-2 text-white text-[12px] font-medium border border-rose-400/30">
              <i class="fa-solid fa-file-pdf text-[11px]"></i>
              <span class="hidden sm:inline">PDF</span>
            </button>
          </div>
          <!-- Household link chip -->
          <a :href="`/households/${household.id}`"
             class="no-print flex items-center gap-2 bg-white/15 hover:bg-white/25 transition-colors rounded-lg px-3 py-2 text-white text-[12px] font-medium border border-white/20">
            <i class="fa-solid fa-house text-[11px] text-white/70"></i>
            <div>
              <div class="font-mono text-[11px] text-white/70">{{ household.unique_code }}</div>
              <div class="text-[11px]">वडा {{ household.ward?.ward_number }}</div>
            </div>
          </a>
          <!-- Print-only household info (replaces chip) -->
          <div class="print-only hidden text-right">
            <div class="font-mono text-[11px] text-white/80">{{ household.unique_code }}</div>
            <div class="text-[11px] text-white/70">वडा {{ household.ward?.ward_number }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Two-column layout ─────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      <!-- Main content (2/3) -->
      <div class="lg:col-span-2 space-y-4 print-main">

        <!-- 1. व्यक्तिगत विवरण -->
        <section class="bg-white rounded-xl border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="bg-indigo-50 border-b border-indigo-100 px-5 py-3 [&_h2]:mb-0">
            <SectionHead icon="fa-id-card" color="indigo" title="व्यक्तिगत विवरण" />
          </div>
          <div class="p-5">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
              <DetailRow label="पूरा नाम"          :value="member.full_name" />
              <DetailRow label="बुवाको नाम"         :value="member.father_name" />
              <DetailRow label="घरसँगको सम्बन्ध"   :value="label('relation_to_head', member.relation_to_head) || member.relation_to_head" />
              <DetailRow label="जन्म मिति"          :value="formatDate(member.date_of_birth)" />
              <DetailRow label="उमेर"               :value="member.age ? member.age + ' वर्ष' : null" />
              <DetailRow label="लिङ्ग"              :value="genderMap[member.gender] ?? member.gender" />
              <FallbackRow label="जातजाति"  :entry="inherit('ethnicity',  member.ethnicity,  household.social?.ethnicity)"  />
              <FallbackRow label="धर्म"      :entry="inherit('religion',   member.religion,   household.social?.religion)"   />
              <FallbackRow label="मातृभाषा" :entry="inherit('mother_tongue', member.mother_tongue, household.social?.mother_tongue)" />
              <DetailRow label="वैवाहिक अवस्था"     :value="label('marital_status', member.marital_status)" />
              <DetailRow v-if="member.ageofmarriage" label="विवाह उमेर"  :value="member.ageofmarriage + ' वर्ष'" />
              <DetailRow label="रक्त समूह"          :value="member.blood_group" />
              <DetailRow label="बसोबास अवस्था"      :value="label('residence_status', member.residence_status)" />
              <DetailRow v-if="member.remarks" label="कैफियत" :value="member.remarks" class="col-span-2 sm:col-span-3" />
            </div>
          </div>
        </section>

        <!-- 2. शिक्षा तथा रोजगार -->
        <section class="bg-white rounded-xl border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="bg-blue-50 border-b border-blue-100 px-5 py-3 [&_h2]:mb-0">
            <SectionHead icon="fa-graduation-cap" color="blue" title="शिक्षा तथा रोजगार" />
          </div>
          <div class="p-5">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
              <DetailRow label="शिक्षा स्तर"        :value="label('education_level', member.education_level)" />
              <DetailRow label="शिक्षाको अवस्था"    :value="label('education_status', member.education_status)" />
              <DetailRow v-if="member.current_class"        label="कक्षा"          :value="str(member.current_class)" />
              <DetailRow v-if="member.higher_education_level" label="उच्च शिक्षा"  :value="label('higher_education_level', member.higher_education_level)" />
              <DetailRow v-if="member.school_name"           label="विद्यालय / संस्था" :value="member.school_name" />
              <DetailRow v-if="member.school_type"           label="विद्यालय प्रकार"   :value="label('school_type', member.school_type)" />
              <DetailRow v-if="member.school_performance"    label="शैक्षिक प्रदर्शन"  :value="label('school_performance', member.school_performance)" />
              <DetailRow v-if="member.school_location"       label="विद्यालय स्थान"    :value="label('school_location', member.school_location)" />
              <DetailRow label="साक्षरता"            :value="member.is_literate === true ? 'साक्षर' : member.is_literate === false ? 'निरक्षर' : null" />
              <DetailRow label="मुख्य पेशा"          :value="label('occupation_type', member.occupation_type)" />
              <DetailRow label="रोजगारी अवस्था"      :value="label('employment_status', member.employment_status)" />
              <DetailRow v-if="member.involveinbusiness" label="व्यवसाय छ?"   :value="'छ'" />
              <DetailRow v-if="member.business_name"     label="व्यवसायको नाम" :value="member.business_name" />
            </div>
            <!-- Cause not in school -->
            <div v-if="member.cause_not_school?.length" class="mt-4 p-3 bg-amber-50 rounded-lg border border-amber-100">
              <p class="text-xs text-amber-600 font-semibold mb-1.5"><i class="fa-solid fa-circle-exclamation mr-1"></i>विद्यालय नजाने कारण</p>
              <div class="flex flex-wrap gap-2">
                <span v-for="c in member.cause_not_school" :key="c"
                      class="text-xs bg-amber-100 text-amber-700 px-2.5 py-0.5 rounded-full font-medium">
                  {{ label('cause_not_school', c) || c }}
                </span>
              </div>
            </div>
          </div>
        </section>

        <!-- 3. तालिम तथा सीप -->
        <section v-if="hasTraining" class="bg-white rounded-xl border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="bg-teal-50 border-b border-teal-100 px-5 py-3 [&_h2]:mb-0">
            <SectionHead icon="fa-chalkboard-user" color="teal" title="तालिम तथा सीप" />
          </div>
          <div class="p-5">
          <div class="space-y-4">
            <!-- Vocational -->
            <div v-if="member.vocational_training" class="p-3 bg-teal-50 rounded-lg border border-teal-100">
              <p class="text-xs font-semibold text-teal-700 mb-2"><i class="fa-solid fa-tools mr-1"></i>व्यावसायिक तालिम</p>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <DetailRow label="विषय"     :value="member.vt_subject" />
                <DetailRow v-if="member.vt_duration_min || member.vt_duration_max"
                           label="अवधि"
                           :value="`${member.vt_duration_min ?? ''}–${member.vt_duration_max ?? ''} महिना`" />
              </div>
            </div>
            <!-- Technical -->
            <div v-if="member.techtraining" class="p-3 bg-blue-50 rounded-lg border border-blue-100">
              <p class="text-xs font-semibold text-blue-700 mb-2"><i class="fa-solid fa-microchip mr-1"></i>प्राविधिक तालिम</p>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <DetailRow label="विषय"     :value="member.techtraining_subject" />
                <DetailRow v-if="member.tech_duration_min || member.tech_duration_max"
                           label="अवधि"
                           :value="`${member.tech_duration_min ?? ''}–${member.tech_duration_max ?? ''} महिना`" />
              </div>
            </div>
            <!-- SA -->
            <div v-if="member.sa_training" class="p-3 bg-violet-50 rounded-lg border border-violet-100">
              <p class="text-xs font-semibold text-violet-700 mb-2"><i class="fa-solid fa-seedling mr-1"></i>SA तालिम</p>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <DetailRow label="विषय"     :value="member.sa_training_subject" />
                <DetailRow v-if="member.sa_duration_min || member.sa_duration_max"
                           label="अवधि"
                           :value="`${member.sa_duration_min ?? ''}–${member.sa_duration_max ?? ''} महिना`" />
              </div>
            </div>
          </div>
          </div>
        </section>

        <!-- 4. स्वास्थ्य विवरण -->
        <section class="bg-white rounded-xl border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="bg-rose-50 border-b border-rose-100 px-5 py-3 [&_h2]:mb-0">
            <SectionHead icon="fa-heart-pulse" color="rose" title="स्वास्थ्य विवरण" />
          </div>
          <div class="p-5">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
              <DetailRow label="रक्त समूह"        :value="member.blood_group" />
              <DetailRow label="रक्त परीक्षण"     :value="member.blood_group_tested ? 'भएको' : null" />
              <DetailRow label="अपाङ्गता"         :value="member.disability_status ? (label('disability_type', member.disability_type) || 'छ') : 'छैन'" />
              <DetailRow v-if="member.disability_status && member.disability_card === 'yes'"
                         label="परिचयपत्र रंग"
                         :value="label('disability_card_color', member.disability_card_color)" />
              <DetailRow label="दीर्घ रोग"        :value="member.chronic_illness ? 'छ' : 'छैन'" />
              <DetailRow label="सामाजिक सुरक्षा"  :value="member.ss_allowance ? 'पाउँछ' : null" />
              <DetailRow v-if="member.smoking_drinking" label="धुम्रपान/मद्यपान" :value="'छ'" />
            </div>
            <div v-if="member.chronic_illness && member.chronic_illness_types?.length" class="mt-4 p-3 bg-red-50 rounded-lg border border-red-100">
              <p class="text-xs text-red-600 font-semibold mb-2"><i class="fa-solid fa-virus mr-1"></i>दीर्घरोगका किसिम</p>
              <div class="flex flex-wrap gap-2">
                <span v-for="t in member.chronic_illness_types" :key="t"
                      class="text-xs bg-red-100 text-red-700 px-2.5 py-0.5 rounded-full font-medium">
                  {{ label('chronic_illness_type', t) || t }}
                </span>
              </div>
            </div>
            <div v-if="member.smoking_drinking && member.smoking_drinking_items?.length" class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
              <p class="text-xs text-gray-600 font-semibold mb-2">धुम्रपान/मद्यपान किसिम</p>
              <div class="flex flex-wrap gap-2">
                <span v-for="item in member.smoking_drinking_items" :key="item"
                      class="text-xs bg-gray-200 text-gray-700 px-2.5 py-0.5 rounded-full font-medium">
                  {{ label('smoking_drinking_item', item) || item }}
                </span>
              </div>
            </div>
          </div>
        </section>

        <!-- 5. बसोबास तथा विदेश -->
        <section v-if="hasResidenceInfo" class="bg-white rounded-xl border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="bg-cyan-50 border-b border-cyan-100 px-5 py-3 [&_h2]:mb-0">
            <SectionHead icon="fa-plane" color="cyan" title="बसोबास तथा विदेश" />
          </div>
          <div class="p-5">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
              <DetailRow label="बसोबास अवस्था"    :value="label('residence_status', member.residence_status)" />
              <DetailRow v-if="member.addrslocation"      label="ठेगाना"        :value="member.addrslocation" />
              <DetailRow v-if="member.residing_district"  label="जिल्ला"        :value="member.residing_district" />
              <DetailRow v-if="member.purpose_living_outside" label="बाहिर बस्ने कारण" :value="label('purpose_living_outside', member.purpose_living_outside)" />
              <DetailRow v-if="member.duration_of_stay"   label="बसेको अवधि"   :value="member.duration_of_stay" />
              <DetailRow v-if="member.residing_country?.length" label="विदेश मुलुक"   :value="member.residing_country.join(', ')" />
              <DetailRow v-if="member.purpose_living_abroad" label="विदेश जाने कारण" :value="label('purpose_living_abroad', member.purpose_living_abroad)" />
              <DetailRow v-if="member.typeofabroadjob"    label="विदेशी काम"   :value="label('typeofabroadjob', member.typeofabroadjob)" />
              <DetailRow v-if="member.abroadjobstatus"    label="काम अवस्था"   :value="label('abroadjobstatus', member.abroadjobstatus)" />
            </div>
          </div>
        </section>

        <!-- 6. सम्पर्क विवरण -->
        <section v-if="member.member_phone || member.member_email || member.has_phone || member.has_email"
                 class="bg-white rounded-xl border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="bg-green-50 border-b border-green-100 px-5 py-3 [&_h2]:mb-0">
            <SectionHead icon="fa-phone" color="green" title="सम्पर्क विवरण" />
          </div>
          <div class="p-5">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
              <DetailRow v-if="member.member_phone" label="फोन नम्बर"  :value="member.member_phone" />
              <DetailRow v-if="member.member_email" label="इमेल ठेगाना" :value="member.member_email" />
              <DetailRow label="फोन छ?"  :value="member.has_phone ? 'छ' : member.has_phone === false ? 'छैन' : null" />
              <DetailRow label="इमेल छ?" :value="member.has_email ? 'छ' : member.has_email === false ? 'छैन' : null" />
            </div>
          </div>
        </section>

        <!-- 7. राहदानी विवरण -->
        <section v-if="member.has_passport" class="bg-white rounded-xl border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="bg-amber-50 border-b border-amber-100 px-5 py-3 [&_h2]:mb-0">
            <SectionHead icon="fa-passport" color="amber" title="राहदानी विवरण" />
          </div>
          <div class="p-5">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
              <DetailRow label="राहदानी नम्बर"    :value="member.passport_number" />
              <DetailRow label="जारी जिल्ला"      :value="member.passport_issued_district" />
              <DetailRow label="जारी मिति"        :value="formatDate(member.passport_issued_date)" />
              <DetailRow label="म्याद सकिने मिति" :value="formatDate(member.passport_expiry_date)" />
            </div>
          </div>
        </section>

        <!-- 8. Driving license -->
        <section v-if="member.has_drivers_license" class="bg-white rounded-xl border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="bg-violet-50 border-b border-violet-100 px-5 py-3 [&_h2]:mb-0">
            <SectionHead icon="fa-car" color="violet" title="सवारी चालक अनुमति" />
          </div>
          <div class="p-5">
            <div v-if="member.drivers_license_category?.length">
              <p class="text-xs text-gray-400 mb-1.5">श्रेणी</p>
              <div class="flex flex-wrap gap-2">
                <span v-for="cat in member.drivers_license_category" :key="cat"
                      class="text-xs bg-violet-100 text-violet-700 px-2.5 py-1 rounded-full font-semibold">{{ cat }}</span>
              </div>
            </div>
          </div>
        </section>

      </div>

      <!-- ── Sidebar (1/3) ─────────────────────────────────────────────── -->
      <div class="space-y-4 no-print">

        <!-- Quick stats -->
        <div class="bg-white rounded-xl border border-[#e6e7e9] shadow-sm p-5">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">त्वरित जानकारी</h3>
          <div class="space-y-2.5">
            <div class="flex items-center justify-between text-[13px]">
              <span class="text-gray-500 flex items-center gap-1.5"><i class="fa-solid fa-droplet text-rose-400 text-[11px]"></i> रक्त समूह</span>
              <span class="font-semibold text-gray-800">{{ member.blood_group || '—' }}</span>
            </div>
            <div class="flex items-center justify-between text-[13px]">
              <span class="text-gray-500 flex items-center gap-1.5"><i class="fa-solid fa-venus-mars text-blue-400 text-[11px]"></i> लिङ्ग</span>
              <span class="font-semibold text-gray-800">{{ genderMap[member.gender] ?? '—' }}</span>
            </div>
            <div class="flex items-center justify-between text-[13px]">
              <span class="text-gray-500 flex items-center gap-1.5"><i class="fa-regular fa-calendar text-indigo-400 text-[11px]"></i> उमेर</span>
              <span class="font-semibold text-gray-800">{{ member.age ? member.age + ' वर्ष' : '—' }}</span>
            </div>
            <div class="flex items-center justify-between text-[13px]">
              <span class="text-gray-500 flex items-center gap-1.5"><i class="fa-solid fa-heart-pulse text-red-400 text-[11px]"></i> दीर्घरोग</span>
              <span :class="member.chronic_illness ? 'text-red-600 font-semibold' : 'text-green-600 font-semibold'">
                {{ member.chronic_illness ? 'छ' : 'छैन' }}
              </span>
            </div>
            <div class="flex items-center justify-between text-[13px]">
              <span class="text-gray-500 flex items-center gap-1.5"><i class="fa-solid fa-wheelchair text-orange-400 text-[11px]"></i> अपाङ्गता</span>
              <span :class="member.disability_status ? 'text-orange-600 font-semibold' : 'text-green-600 font-semibold'">
                {{ member.disability_status ? (label('disability_type', member.disability_type) || 'छ') : 'छैन' }}
              </span>
            </div>
            <div class="flex items-center justify-between text-[13px]">
              <span class="text-gray-500 flex items-center gap-1.5"><i class="fa-solid fa-graduation-cap text-blue-400 text-[11px]"></i> शिक्षा</span>
              <span class="font-semibold text-gray-800 text-right max-w-[120px] truncate">
                {{ label('education_level', member.education_level) || '—' }}
              </span>
            </div>
            <div class="flex items-center justify-between text-[13px]">
              <span class="text-gray-500 flex items-center gap-1.5"><i class="fa-solid fa-briefcase text-teal-400 text-[11px]"></i> पेशा</span>
              <span class="font-semibold text-gray-800 text-right max-w-[120px] truncate">
                {{ label('occupation_type', member.occupation_type) || '—' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Documents checklist -->
        <div class="bg-white rounded-xl border border-[#e6e7e9] shadow-sm p-5">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
            <i class="fa-solid fa-folder-open text-gray-400 mr-1"></i> कागजात
          </h3>
          <div class="space-y-2">
            <DocChip label="नागरिकता"            :has="member.has_citizenship" />
            <DocChip v-if="member.no_citizenship_reason && !member.has_citizenship"
                     label="नागरिकता नभएको कारण" :note="label('no_citizenship_reason', member.no_citizenship_reason) || member.no_citizenship_reason" />
            <DocChip label="मतदाता परिचयपत्र"    :has="member.has_voters_card" />
            <DocChip label="राष्ट्रिय परिचयपत्र"  :has="member.has_national_id_card" />
            <DocChip label="राहदानी"              :has="member.has_passport" />
            <DocChip label="स्थायी लेखा (PAN)"   :has="member.has_pan" />
            <DocChip label="सवारी अनुमतिपत्र"    :has="member.has_drivers_license" />
            <DocChip v-if="member.age && parseInt(member.age) <= 18"
                     label="जन्म दर्ता"           :has="member.has_birth_certificate" />
          </div>
        </div>

        <!-- Business card -->
        <div class="bg-white rounded-xl border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="flex items-center gap-2 px-4 py-2.5 border-b border-[#e6e7e9] bg-orange-50">
            <i class="fa-solid fa-store text-orange-500 text-xs"></i>
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">व्यवसाय विवरण</h3>
          </div>
          <!-- Has business record -->
          <div v-if="memberBusiness" class="p-4">
            <div class="flex items-start justify-between gap-2 mb-3">
              <div>
                <span class="font-mono text-xs bg-orange-50 text-orange-700 px-2 py-0.5 rounded">
                  {{ memberBusiness.unique_code }}
                </span>
                <p class="text-sm font-semibold text-gray-800 mt-1.5">{{ memberBusiness.business_name }}</p>
                <p v-if="memberBusiness.business_field" class="text-xs text-gray-500 mt-0.5">{{ memberBusiness.business_field }}</p>
              </div>
              <span :class="['text-[11px] px-2 py-0.5 rounded-full font-semibold shrink-0', bizStatusBadge(memberBusiness.status)]">
                {{ bizStatusLabel(memberBusiness.status) }}
              </span>
            </div>
            <a
              :href="`/businesses/${memberBusiness.id}`"
              class="flex items-center justify-center gap-1.5 w-full rounded-lg bg-orange-50 hover:bg-orange-100 border border-orange-200 px-3 py-2 text-xs font-medium text-orange-700 transition-colors"
            >
              <i class="fa-solid fa-eye text-orange-400"></i> विवरण हेर्नुस्
            </a>
          </div>
          <!-- No business record but involved_in_business -->
          <div v-else-if="member.involveinbusiness" class="p-4 text-center">
            <p class="text-xs text-gray-500 mb-3">व्यवसाय विवरण भर्न बाँकी छ</p>
            <a
              :href="`/businesses/create?household_id=${household.id}&member_id=${member.id}`"
              class="inline-flex items-center gap-1.5 rounded-lg bg-orange-600 hover:bg-orange-700 text-white px-3 py-2 text-xs font-medium transition-colors"
            >
              <i class="fa-solid fa-plus"></i> व्यवसाय थप्नुस्
            </a>
          </div>
          <!-- Not involved in business -->
          <div v-else class="p-4 text-center">
            <p class="text-xs text-gray-400">यो सदस्य व्यवसायमा संलग्न छैन</p>
          </div>
        </div>

        <!-- Back button -->
        <a :href="`/households/${household.id}`"
           class="flex items-center justify-center gap-2 w-full rounded-xl border border-[#e6e7e9] px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-indigo-600 transition-colors">
          <i class="fa-solid fa-arrow-left text-gray-400"></i> घरधुरीमा फर्कनुस्
        </a>

      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, h } from 'vue'

const props = defineProps({
  household:      { type: Object, required: true },
  member:         { type: Object, required: true },
  memberBusiness: { type: Object, default: null },
  surveyOptions:  { type: Object, default: () => ({}) },
})

// ── Initials ───────────────────────────────────────────────────────────────
const initials = computed(() => {
  const name = props.member.full_name ?? ''
  return name.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase() || '?'
})

// ── Training presence ──────────────────────────────────────────────────────
const hasTraining = computed(() =>
  props.member.vocational_training || props.member.techtraining || props.member.sa_training
)

// ── Residence / abroad info ────────────────────────────────────────────────
const hasResidenceInfo = computed(() =>
  props.member.residence_status || props.member.addrslocation ||
  props.member.residing_district || props.member.residing_country?.length ||
  props.member.purpose_living_abroad
)

// ── Helpers ────────────────────────────────────────────────────────────────
const genderMap = { male: 'पुरुष', female: 'महिला', other: 'अन्य' }

function label(category, value) {
  if (!value) return null
  const opts = props.surveyOptions[category] ?? []
  const found = opts.find(o => o.value === String(value))
  return found ? found.label : value
}

function str(v) { return v !== null && v !== undefined && v !== '' ? String(v) : null }

// ── Ethnicity / Religion / Mother-tongue fallback from household Step 2 ───
// Returns { value, inherited } — inherited=true means pulled from household social data
function inherit(category, memberVal, hhVal) {
  if (memberVal) return { value: label(category, memberVal) || memberVal, inherited: false }
  if (hhVal)     return { value: label(category, hhVal) || hhVal,         inherited: true  }
  return { value: null, inherited: false }
}

function formatDate(d) {
  if (!d) return null
  return new Date(d).toLocaleDateString('ne-NP')
}

// ── Inline components (render fn) ─────────────────────────────────────────
const DetailRow = {
  props: ['label', 'value'],
  render() {
    return h('div', [
      h('p', { class: 'text-xs text-gray-400 mb-0.5' }, this.label),
      h('p', { class: 'text-sm font-medium text-gray-800' }, this.value ?? '—'),
    ])
  },
}

// FallbackRow: shows value + a subtle "परिवार" badge when data is inherited from household
const FallbackRow = {
  props: ['label', 'entry'],
  render() {
    const entry = this.entry ?? { value: null, inherited: false }
    const valueNode = entry.value
      ? h('span', { class: 'text-sm font-medium text-gray-800' }, entry.value)
      : h('span', { class: 'text-sm text-gray-400' }, '—')
    const badge = entry.inherited
      ? h('span', {
          class: 'ml-1.5 text-[10px] bg-indigo-100 text-indigo-500 font-semibold px-1.5 py-0.5 rounded align-middle',
          title: 'घरधुरी स्तरको विवरणबाट लिइएको',
        }, 'परिवार')
      : null
    return h('div', [
      h('p', { class: 'text-xs text-gray-400 mb-0.5' }, this.label),
      h('p', { class: 'flex items-baseline gap-0' }, [valueNode, badge]),
    ])
  },
}

const colorMap = {
  indigo: ['bg-indigo-100', 'text-indigo-600'], blue:   ['bg-blue-100',   'text-blue-600'],
  green:  ['bg-green-100',  'text-green-600'],  teal:   ['bg-teal-100',   'text-teal-600'],
  rose:   ['bg-rose-100',   'text-rose-600'],   cyan:   ['bg-cyan-100',   'text-cyan-600'],
  amber:  ['bg-amber-100',  'text-amber-600'],  violet: ['bg-violet-100', 'text-violet-600'],
}

const SectionHead = {
  props: ['icon', 'color', 'title'],
  render() {
    const [bg, text] = colorMap[this.color] ?? ['bg-gray-100', 'text-gray-600']
    return h('h2', { class: 'text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2' }, [
      h('span', { class: `w-6 h-6 rounded ${bg} flex items-center justify-center` }, [
        h('i', { class: `fa-solid ${this.icon} ${text} text-xs` }),
      ]),
      this.title,
    ])
  },
}

// Document chip: green=has, red=hasn't, or note-only
const DocChip = {
  props: ['label', 'has', 'note'],
  render() {
    if (this.note !== undefined) {
      return h('div', { class: 'flex items-center gap-2 text-[12px] text-amber-600' }, [
        h('i', { class: 'fa-solid fa-circle-info text-amber-400 text-[10px] shrink-0 w-4' }),
        h('span', this.label + ': ' + this.note),
      ])
    }
    const has = this.has
    return h('div', { class: 'flex items-center justify-between text-[12px]' }, [
      h('span', { class: 'text-gray-600' }, this.label),
      h('span', {
        class: ['font-semibold px-2 py-0.5 rounded-full text-[11px]',
          has ? 'bg-green-100 text-green-700' : 'bg-red-50 text-red-400'],
      }, has ? '✓ छ' : '✗ छैन'),
    ])
  },
}

// ── Business status helpers ────────────────────────────────────────────────
function bizStatusBadge(s) {
  const map = {
    draft:        'bg-gray-100 text-gray-600',
    submitted:    'bg-yellow-100 text-yellow-700',
    ward_approved:'bg-blue-100 text-blue-700',
    rejected:     'bg-red-100 text-red-700',
    published:    'bg-green-100 text-green-700',
  }
  return map[s] ?? 'bg-gray-100 text-gray-600'
}

function bizStatusLabel(s) {
  const map = {
    draft:        'मस्यौदा',
    submitted:    'पेस भएको',
    ward_approved:'वडा अनुमोदित',
    rejected:     'अस्वीकृत',
    published:    'प्रकाशित',
  }
  return map[s] ?? s
}

// ── Print / PDF ────────────────────────────────────────────────────────────
function printPage() {
  window.print()
}

function exportPDF() {
  const original = document.title
  document.title = `${props.member.full_name || 'सदस्य'} — ${props.household.unique_code}`
  window.print()
  setTimeout(() => { document.title = original }, 3000)
}
</script>

<style>
/* ── Print Stylesheet ─────────────────────────────────────────────────────── */
@media print {
  @page {
    size: A4 portrait;
    margin: 12mm 16mm;
  }

  /* Force background colors / images to print */
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  /* Hide app chrome */
  aside,
  header,
  .no-print {
    display: none !important;
  }

  /* Show print-only elements */
  .print-only {
    display: block !important;
  }

  /* Reset body & scroll containers */
  html, body {
    background: white !important;
    overflow: visible !important;
    height: auto !important;
  }

  /* Unwrap the Inertia layout flex containers */
  body > div,
  .flex.h-screen,
  .flex.flex-col.flex-1 {
    display: block !important;
    height: auto !important;
    overflow: visible !important;
  }

  /* Content area — no padding clipping */
  main {
    overflow: visible !important;
    padding: 0 !important;
    height: auto !important;
  }

  /* Our print wrapper */
  #member-print-area {
    max-width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  /* Hero: keep gradient background */
  #member-print-area .relative.rounded-xl {
    border-radius: 8pt !important;
    margin-bottom: 12pt !important;
    page-break-inside: avoid;
  }

  /* Two-column grid → single column */
  #member-print-area .grid {
    display: block !important;
  }

  /* Each section: avoid page breaks inside, margin between */
  #member-print-area section {
    break-inside: avoid;
    page-break-inside: avoid;
    margin-bottom: 10pt !important;
    border-radius: 6pt !important;
    box-shadow: none !important;
  }

  /* Section header strips — keep their bg colors */
  #member-print-area section > div:first-child {
    border-radius: 6pt 6pt 0 0 !important;
    padding: 8pt 14pt !important;
  }

  /* Section content padding */
  #member-print-area section > div:last-child {
    padding: 10pt 14pt !important;
  }

  /* Grid inside sections */
  #member-print-area .grid.grid-cols-2,
  #member-print-area .grid.grid-cols-3 {
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 8pt !important;
  }

  /* DetailRow / FallbackRow text sizes */
  #member-print-area p { font-size: 9pt !important; line-height: 1.4 !important; }
  #member-print-area .text-xs  { font-size: 8pt !important; }
  #member-print-area .text-sm  { font-size: 9pt !important; }

  /* Hero text */
  #member-print-area h1 { font-size: 16pt !important; }
  #member-print-area .text-xl { font-size: 14pt !important; }

  /* Tags / badges inside hero */
  #member-print-area .rounded-full { border-radius: 100pt !important; }

  /* Remove hover effects */
  a { text-decoration: none !important; }
}
</style>
