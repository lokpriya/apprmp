<template>
  <div>
    <!-- Print-only header -->
    <div class="print-only hidden mb-6 pb-4 border-b-2 border-gray-800">
      <p class="text-xs text-gray-500 mb-1">{{ page.props.auth?.lg?.name_np ?? 'स्थानीय तह' }}</p>
      <h1 class="text-xl font-bold text-gray-900">संस्था विवरण</h1>
      <div class="flex items-center gap-4 mt-2 text-sm text-gray-600">
        <span>कोड: <strong class="font-mono">{{ institution.unique_code }}</strong></span>
        <span>प्रकार: <strong>{{ institution.institutionType?.name_np ?? '—' }}</strong></span>
        <span>वडा: <strong>{{ institution.ward?.ward_number ?? '—' }}</strong></span>
        <span>अवस्था: <strong>{{ statusLabel(institution.status) }}</strong></span>
        <span class="ml-auto text-xs text-gray-400">मुद्रण मिति: {{ new Date().toLocaleDateString('ne-NP') }}</span>
      </div>
    </div>

    <!-- Page header -->
    <div class="no-print flex items-center gap-3 mb-4">
      <a href="/institutions"
        class="w-8 h-8 rounded border border-[#e6e7e9] flex items-center justify-center text-[#626976] hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left text-[11px]"></i>
      </a>
      <h1 class="text-[15px] font-semibold text-[#1d273b]">संस्था विवरण</h1>
      <span class="font-mono text-[11px] bg-teal-50 text-teal-700 px-2 py-0.5 rounded">{{ institution.unique_code }}</span>
      <span :class="['text-[11px] px-2 py-0.5 rounded font-medium', statusBadge(institution.status)]">
        {{ statusLabel(institution.status) }}
      </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

      <!-- ── Main content (2/3) ──────────────────────────────────────── -->
      <div class="lg:col-span-2 space-y-5">

        <!-- ① संस्था विवरण ──────────────────────────────────────────── -->
        <section class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="flex items-center gap-2 px-5 py-3 border-b border-[#e6e7e9] bg-teal-50">
            <span class="w-6 h-6 rounded bg-teal-100 flex items-center justify-center">
              <i class="fa-solid fa-building text-teal-600 text-xs"></i>
            </span>
            <h2 class="text-sm font-semibold text-teal-800">संस्था विवरण</h2>
          </div>
          <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-4">
            <DetailRow label="संस्थाको नाम (नेपाली)" :value="institution.name_np" />
            <DetailRow label="संस्थाको नाम (अंग्रेजी)" :value="institution.name_en" />
            <DetailRow label="प्रकार" :value="institution.institutionType?.name_np" />
            <DetailRow label="वडा नं." :value="institution.ward ? `वडा ${institution.ward.ward_number}` : null" />
            <DetailRow label="बस्ती" :value="institution.settlement?.name_np" />
            <DetailRow label="स्वामित्व" :value="lbl('ownership_type', institution.ownership_type)" />
            <DetailRow label="दर्ता नं." :value="institution.registration_number" />
            <DetailRow label="स्थापना मिति" :value="formatDate(institution.established_date)" />
            <DetailRow label="सर्वेक्षण मिति" :value="formatDate(institution.survey_date)" />
            <DetailRow label="गणक" :value="institution.enumerator?.name" />
          </div>
          <div v-if="institution.remarks" class="mx-5 mb-5 p-3 bg-yellow-50 rounded-md border border-yellow-100">
            <p class="text-xs text-yellow-600 font-medium mb-1"><i class="fa-solid fa-note-sticky mr-1"></i>कैफियत</p>
            <p class="text-sm text-gray-700">{{ institution.remarks }}</p>
          </div>
        </section>

        <!-- ② सम्पर्क विवरण ─────────────────────────────────────────── -->
        <section
          v-if="institution.respondent_name || institution.respondent_contact || institution.gps_lat"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="flex items-center gap-2 px-5 py-3 border-b border-[#e6e7e9] bg-sky-50">
            <span class="w-6 h-6 rounded bg-sky-100 flex items-center justify-center">
              <i class="fa-solid fa-address-card text-sky-600 text-xs"></i>
            </span>
            <h2 class="text-sm font-semibold text-sky-800">सम्पर्क विवरण</h2>
          </div>
          <div class="p-5">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
              <DetailRow label="उत्तरदाताको नाम" :value="institution.respondent_name" />
              <DetailRow label="सम्पर्क नं." :value="institution.respondent_contact" />
            </div>
            <div v-if="institution.gps_lat" class="border-t border-[#e6e7e9] pt-4">
              <p class="text-xs font-semibold text-gray-500 mb-3"><i class="fa-solid fa-location-dot text-sky-400 mr-1"></i>GPS स्थान</p>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <DetailRow label="Latitude" :value="institution.gps_lat ? String(institution.gps_lat) : null" mono />
                <DetailRow label="Longitude" :value="institution.gps_lng ? String(institution.gps_lng) : null" mono />
                <DetailRow label="Accuracy (m)" :value="institution.gps_accuracy ? String(institution.gps_accuracy) : null" mono />
                <DetailRow label="Altitude (m)" :value="institution.gps_altitude ? String(institution.gps_altitude) : null" mono />
              </div>
            </div>
          </div>
        </section>

        <!-- ③ भौतिक पूर्वाधार ──────────────────────────────────────── -->
        <section v-if="institution.infrastructure" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="flex items-center gap-2 px-5 py-3 border-b border-[#e6e7e9] bg-orange-50">
            <span class="w-6 h-6 rounded bg-orange-100 flex items-center justify-center">
              <i class="fa-solid fa-hammer text-orange-600 text-xs"></i>
            </span>
            <h2 class="text-sm font-semibold text-orange-800">भौतिक पूर्वाधार</h2>
          </div>
          <div class="p-5 space-y-4">
            <!-- Buildings table -->
            <div v-if="institution.infrastructure.buildings?.length">
              <p class="text-xs font-semibold text-gray-500 mb-2"><i class="fa-solid fa-building text-orange-400 mr-1"></i>भवन विवरण</p>
              <div class="overflow-x-auto">
                <table class="w-full text-xs">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="text-left px-3 py-2 text-gray-500 font-semibold rounded-l-lg">भवनको प्रकार</th>
                      <th class="text-right px-3 py-2 text-gray-500 font-semibold">भवन संख्या</th>
                      <th class="text-right px-3 py-2 text-gray-500 font-semibold rounded-r-lg">कोठा संख्या</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-[#e6e7e9]">
                    <tr v-for="(b, i) in institution.infrastructure.buildings" :key="i" class="hover:bg-gray-50">
                      <td class="px-3 py-2 font-medium text-gray-800">{{ optLabel('inst_building_type', b.building_type) }}</td>
                      <td class="px-3 py-2 text-right text-gray-600">{{ b.building_count ?? '—' }}</td>
                      <td class="px-3 py-2 text-right text-gray-600">{{ b.room_count ?? '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Summary stats -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
              <DetailRow label="कुल क्षेत्रफल (वर्गमि.)" :value="institution.infrastructure.total_area ? String(institution.infrastructure.total_area) : null" />
              <DetailRow label="भवनको अवस्था" :value="lbl('condition', institution.infrastructure.condition)" />
              <DetailRow v-if="institution.infrastructure.total_classrooms" label="कक्षाकोठा संख्या" :value="String(institution.infrastructure.total_classrooms)" />
            </div>

            <!-- Facilities chips -->
            <div class="border-t border-[#e6e7e9] pt-3">
              <p class="text-xs font-semibold text-gray-500 mb-2">उपलब्ध सुविधाहरू</p>
              <div class="flex flex-wrap gap-2">
                <FacilityChip label="विद्युत"      :has="institution.infrastructure.has_electricity" />
                <FacilityChip label="खानेपानी"    :has="institution.infrastructure.has_water" />
                <FacilityChip label="इन्टरनेट"    :has="institution.infrastructure.has_internet" />
                <FacilityChip label="शौचालय"      :has="institution.infrastructure.has_toilet" />
                <FacilityChip label="Ramp/ढलान"   :has="institution.infrastructure.has_ramp" />
                <FacilityChip v-if="institutionSlug === 'school'" label="पुस्तकालय" :has="institution.infrastructure.has_library" />
                <FacilityChip v-if="institutionSlug === 'school'" label="प्रयोगशाला" :has="institution.infrastructure.has_lab" />
              </div>
            </div>

            <!-- Toilet detail -->
            <div v-if="institution.infrastructure.has_toilet && (institution.infrastructure.male_toilet || institution.infrastructure.female_toilet)"
              class="grid grid-cols-2 gap-4 border-t border-[#e6e7e9] pt-3">
              <DetailRow label="पुरुष शौचालय" :value="institution.infrastructure.male_toilet ? String(institution.infrastructure.male_toilet) : null" />
              <DetailRow label="महिला शौचालय" :value="institution.infrastructure.female_toilet ? String(institution.infrastructure.female_toilet) : null" />
            </div>
          </div>
        </section>

        <!-- ④ कार्यकारी समिति ──────────────────────────────────────── -->
        <section v-if="ec.has_committee && ec.members?.length" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="flex items-center justify-between px-5 py-3 border-b border-[#e6e7e9] bg-blue-50">
            <div class="flex items-center gap-2">
              <span class="w-6 h-6 rounded bg-blue-100 flex items-center justify-center">
                <i class="fa-solid fa-users text-blue-600 text-xs"></i>
              </span>
              <h2 class="text-sm font-semibold text-blue-800">कार्यकारी समिति</h2>
            </div>
            <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full font-semibold">
              {{ ec.members.length }} जना
            </span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead class="bg-gray-50">
                <tr>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold rounded-l-lg">#</th>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold">पूरा नाम</th>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold">पद / ओहदा</th>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold">लिङ्ग</th>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold">जाति / समूह</th>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold">उमेर</th>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold rounded-r-lg">पेशा</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#e6e7e9]">
                <tr v-for="(m, idx) in ec.members" :key="idx" class="hover:bg-gray-50 transition-colors">
                  <td class="px-3 py-2.5 text-gray-400 font-mono">{{ idx + 1 }}</td>
                  <td class="px-3 py-2.5 font-medium text-gray-800">
                    {{ m.full_name || '—' }}
                    <span v-if="m.disability" class="ml-1 text-[10px] bg-purple-100 text-purple-600 px-1 rounded">अपाङ्गता</span>
                  </td>
                  <td class="px-3 py-2.5 text-gray-600">{{ optLabel('inst_committee_position', m.position) }}</td>
                  <td class="px-3 py-2.5 text-gray-600">{{ lbl('gender', m.gender) }}</td>
                  <td class="px-3 py-2.5 text-gray-600">{{ optLabel('ethnicity', m.ethnicity) }}</td>
                  <td class="px-3 py-2.5 text-gray-600">{{ m.age ? `${m.age} वर्ष` : '—' }}</td>
                  <td class="px-3 py-2.5 text-gray-600">{{ optLabel('major_occupation', m.occupation) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <!-- Gender summary -->
          <div class="px-5 py-3 bg-blue-50/50 border-t border-[#e6e7e9] flex flex-wrap gap-4 text-xs">
            <span class="text-gray-500">पुरुष: <strong class="text-gray-800">{{ ec.members.filter(m => m.gender === 'male').length }}</strong></span>
            <span class="text-gray-500">महिला: <strong class="text-gray-800">{{ ec.members.filter(m => m.gender === 'female').length }}</strong></span>
            <span class="text-gray-500">अपाङ्गता: <strong class="text-gray-800">{{ ec.members.filter(m => m.disability).length }}</strong></span>
          </div>
        </section>

        <!-- ⑤ कर्मचारी ────────────────────────────────────────────── -->
        <section v-if="institution.staff?.length" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="flex items-center justify-between px-5 py-3 border-b border-[#e6e7e9] bg-indigo-50">
            <div class="flex items-center gap-2">
              <span class="w-6 h-6 rounded bg-indigo-100 flex items-center justify-center">
                <i class="fa-solid fa-id-badge text-indigo-600 text-xs"></i>
              </span>
              <h2 class="text-sm font-semibold text-indigo-800">कर्मचारी</h2>
            </div>
            <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full font-semibold">
              {{ institution.staff.length }} जना
            </span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead class="bg-gray-50">
                <tr>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold rounded-l-lg">नाम</th>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold">पद</th>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold">लिङ्ग</th>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold">योग्यता</th>
                  <th class="text-left px-3 py-2 text-gray-500 font-semibold rounded-r-lg">किसिम</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#e6e7e9]">
                <tr v-for="(s, idx) in institution.staff" :key="idx" class="hover:bg-gray-50 transition-colors">
                  <td class="px-3 py-2.5 font-medium text-gray-800">{{ s.name || '—' }}</td>
                  <td class="px-3 py-2.5 text-gray-600">{{ optLabel('inst_staff_category', s.position) }}</td>
                  <td class="px-3 py-2.5 text-gray-600">{{ lbl('gender', s.gender) }}</td>
                  <td class="px-3 py-2.5 text-gray-600">{{ optLabel('education_level', s.qualification) }}</td>
                  <td class="px-3 py-2.5">
                    <span v-if="s.type" class="bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full">{{ optLabel('inst_staff_type', s.type) }}</span>
                    <span v-else>—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- ⑥ सेवाहरू ─────────────────────────────────────────────── -->
        <section v-if="institution.services?.length" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="flex items-center gap-2 px-5 py-3 border-b border-[#e6e7e9] bg-teal-50">
            <span class="w-6 h-6 rounded bg-teal-100 flex items-center justify-center">
              <i class="fa-solid fa-list-check text-teal-600 text-xs"></i>
            </span>
            <h2 class="text-sm font-semibold text-teal-800">प्रदान गरिने सेवाहरू</h2>
            <span class="ml-auto bg-teal-100 text-teal-700 text-xs px-2 py-0.5 rounded-full font-semibold">{{ institution.services.length }}</span>
          </div>
          <div class="p-5 flex flex-wrap gap-2">
            <span v-for="(svc, idx) in institution.services" :key="idx"
              class="bg-teal-50 text-teal-700 text-xs px-3 py-1.5 rounded-full border border-teal-100 font-medium">
              {{ optLabel('inst_service_type', svc.service_name) }}
            </span>
          </div>
        </section>

        <!-- ⑦ शिक्षा विवरण (School type-specific) ──────────────────── -->
        <template v-if="institutionSlug === 'school'">
          <!-- School info card -->
          <section class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
            <div class="flex items-center gap-2 px-5 py-3 border-b border-[#e6e7e9] bg-indigo-50">
              <span class="w-6 h-6 rounded bg-indigo-100 flex items-center justify-center">
                <i class="fa-solid fa-school text-indigo-600 text-xs"></i>
              </span>
              <h2 class="text-sm font-semibold text-indigo-800">शिक्षा विवरण</h2>
            </div>
            <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-4">
              <DetailRow label="विद्यालयको तह" :value="optLabel('inst_school_level', ts.school_level)" />
              <DetailRow label="व्यवस्थापन प्रकार" :value="optLabel('inst_management_type', ts.management_type)" />
              <DetailRow label="शिक्षण माध्यम" :value="optLabel('inst_medium', ts.medium)" />
              <DetailRow label="कक्षाकोठा संख्या" :value="institution.infrastructure?.total_classrooms ? String(institution.infrastructure.total_classrooms) : null" />
              <DetailRow label="पुस्तकालय" :value="institution.infrastructure?.has_library ? 'छ' : 'छैन'" />
              <DetailRow label="प्रयोगशाला" :value="institution.infrastructure?.has_lab ? 'छ' : 'छैन'" />
            </div>
          </section>

          <!-- Grade-wise enrollment -->
          <section v-if="institution.education?.length" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
            <div class="flex items-center gap-2 px-5 py-3 border-b border-[#e6e7e9] bg-violet-50">
              <span class="w-6 h-6 rounded bg-violet-100 flex items-center justify-center">
                <i class="fa-solid fa-graduation-cap text-violet-600 text-xs"></i>
              </span>
              <h2 class="text-sm font-semibold text-violet-800">कक्षागत विद्यार्थी विवरण</h2>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-xs">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="text-left px-3 py-2 text-gray-500 font-semibold rounded-l-lg">कक्षा / तह</th>
                    <th class="text-right px-3 py-2 text-gray-500 font-semibold">छात्र</th>
                    <th class="text-right px-3 py-2 text-gray-500 font-semibold">छात्रा</th>
                    <th class="text-right px-3 py-2 text-gray-500 font-semibold rounded-r-lg">जम्मा</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#e6e7e9]">
                  <tr v-for="(edu, idx) in institution.education" :key="idx" class="hover:bg-gray-50 transition-colors">
                    <td class="px-3 py-2.5 font-medium text-gray-800">{{ optLabel('inst_grade', edu.grade) }}</td>
                    <td class="px-3 py-2.5 text-right text-gray-600">{{ edu.male_students ?? '—' }}</td>
                    <td class="px-3 py-2.5 text-right text-gray-600">{{ edu.female_students ?? '—' }}</td>
                    <td class="px-3 py-2.5 text-right font-semibold text-gray-700">
                      {{ (Number(edu.male_students) || 0) + (Number(edu.female_students) || 0) }}
                    </td>
                  </tr>
                  <tr class="bg-indigo-50 font-semibold">
                    <td class="px-3 py-2 text-indigo-700">जम्मा</td>
                    <td class="px-3 py-2 text-right text-indigo-700">{{ sumField('male_students') }}</td>
                    <td class="px-3 py-2 text-right text-indigo-700">{{ sumField('female_students') }}</td>
                    <td class="px-3 py-2 text-right text-indigo-700">{{ sumField('male_students') + sumField('female_students') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <!-- Teachers -->
          <section v-if="sd.teachers?.length" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b border-[#e6e7e9] bg-amber-50">
              <div class="flex items-center gap-2">
                <span class="w-6 h-6 rounded bg-amber-100 flex items-center justify-center">
                  <i class="fa-solid fa-chalkboard-user text-amber-600 text-xs"></i>
                </span>
                <h2 class="text-sm font-semibold text-amber-800">शिक्षक विवरण</h2>
              </div>
              <span class="bg-amber-100 text-amber-700 text-xs px-2 py-0.5 rounded-full font-semibold">{{ sd.teachers.length }} जना</span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-xs">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="text-left px-3 py-2 text-gray-500 font-semibold rounded-l-lg">नाम</th>
                    <th class="text-left px-3 py-2 text-gray-500 font-semibold">श्रेणी</th>
                    <th class="text-left px-3 py-2 text-gray-500 font-semibold">लिङ्ग</th>
                    <th class="text-left px-3 py-2 text-gray-500 font-semibold">योग्यता</th>
                    <th class="text-left px-3 py-2 text-gray-500 font-semibold">नियुक्ति तह</th>
                    <th class="text-left px-3 py-2 text-gray-500 font-semibold">विशिष्टीकरण</th>
                    <th class="text-left px-3 py-2 text-gray-500 font-semibold">पढाउने विषयहरू</th>
                    <th class="text-left px-3 py-2 text-gray-500 font-semibold rounded-r-lg">प्रवेश मिति</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#e6e7e9]">
                  <tr v-for="(t, idx) in sd.teachers" :key="idx" class="hover:bg-gray-50">
                    <td class="px-3 py-2.5 font-medium text-gray-800">{{ t.full_name || '—' }}</td>
                    <td class="px-3 py-2.5 text-gray-600">{{ optLabel('inst_teacher_category', t.category) }}</td>
                    <td class="px-3 py-2.5 text-gray-600">{{ lbl('gender', t.gender) }}</td>
                    <td class="px-3 py-2.5 text-gray-600">{{ optLabel('inst_teacher_qualification', t.qualification) }}</td>
                    <td class="px-3 py-2.5 text-gray-600">{{ optLabel('inst_teacher_level', t.appointment_level) }}</td>
                    <td class="px-3 py-2.5 text-gray-600">{{ optLabel('inst_teacher_subject', t.specialization) }}</td>
                    <td class="px-3 py-2.5">
                      <span v-if="t.subjects?.length" class="flex flex-wrap gap-1">
                        <span v-for="s in t.subjects" :key="s"
                          class="bg-indigo-50 text-indigo-700 text-[10px] px-1.5 py-0.5 rounded-full border border-indigo-100">{{ optLabel('inst_teacher_subject', s) }}</span>
                      </span>
                      <span v-else class="text-gray-400">—</span>
                    </td>
                    <td class="px-3 py-2.5 text-gray-600">{{ t.enrollment_date || '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </template>

        <!-- ⑧ स्वास्थ्य विवरण (Health Post type-specific) ──────────── -->
        <template v-else-if="institutionSlug === 'health_post'">
          <!-- Basic health info -->
          <section class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
            <div class="flex items-center gap-2 px-5 py-3 border-b border-[#e6e7e9] bg-red-50">
              <span class="w-6 h-6 rounded bg-red-100 flex items-center justify-center">
                <i class="fa-solid fa-heart-pulse text-red-500 text-xs"></i>
              </span>
              <h2 class="text-sm font-semibold text-red-800">स्वास्थ्य संस्था विवरण</h2>
            </div>
            <div class="p-5 space-y-4">
              <!-- Health type -->
              <DetailRow v-if="institution.healthDetail?.health_type" label="स्वास्थ्य संस्थाको प्रकार" :value="optLabel('inst_health_type', institution.healthDetail.health_type)" />

              <!-- Bed breakdown -->
              <div v-if="institution.healthDetail?.bed_count" class="border-t border-[#e6e7e9] pt-4">
                <p class="text-xs font-semibold text-gray-500 mb-3"><i class="fa-solid fa-bed text-red-400 mr-1"></i>शय्या विवरण</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                  <DetailRow label="कुल शय्या" :value="String(institution.healthDetail.bed_count)" />
                  <DetailRow label="पुरुष वार्ड" :value="institution.healthDetail.male_ward_beds ? String(institution.healthDetail.male_ward_beds) : null" />
                  <DetailRow label="महिला वार्ड" :value="institution.healthDetail.female_ward_beds ? String(institution.healthDetail.female_ward_beds) : null" />
                  <DetailRow label="बाल वार्ड" :value="institution.healthDetail.children_ward_beds ? String(institution.healthDetail.children_ward_beds) : null" />
                  <DetailRow label="प्रसूति वार्ड" :value="institution.healthDetail.maternity_beds ? String(institution.healthDetail.maternity_beds) : null" />
                  <DetailRow label="ICU शय्या" :value="institution.healthDetail.icu_beds ? String(institution.healthDetail.icu_beds) : null" />
                  <DetailRow label="आपतकालीन शय्या" :value="institution.healthDetail.emergency_beds ? String(institution.healthDetail.emergency_beds) : null" />
                </div>
              </div>

              <!-- Patient stats -->
              <div class="border-t border-[#e6e7e9] pt-4">
                <p class="text-xs font-semibold text-gray-500 mb-3"><i class="fa-solid fa-chart-line text-red-400 mr-1"></i>बिरामी तथ्याङ्क</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                  <DetailRow label="दैनिक OPD" :value="institution.healthDetail?.opd_daily ? String(institution.healthDetail.opd_daily) : null" />
                  <DetailRow label="मासिक OPD" :value="institution.healthDetail?.opd_monthly ? String(institution.healthDetail.opd_monthly) : null" />
                  <DetailRow label="मासिक IPD" :value="institution.healthDetail?.ipd_monthly ? String(institution.healthDetail.ipd_monthly) : null" />
                  <DetailRow label="वार्षिक प्रसव" :value="institution.healthDetail?.annual_delivery_count ? String(institution.healthDetail.annual_delivery_count) : null" />
                </div>
              </div>

              <!-- Facilities -->
              <div class="border-t border-[#e6e7e9] pt-4">
                <p class="text-xs font-semibold text-gray-500 mb-3">उपकरण तथा सुविधाहरू</p>
                <div class="flex flex-wrap gap-2">
                  <FacilityChip label="एम्बुलेन्स"       :has="institution.healthDetail?.has_ambulance" />
                  <FacilityChip label="X-Ray"              :has="institution.healthDetail?.has_xray" />
                  <FacilityChip label="अल्ट्रासाउन्ड"    :has="institution.healthDetail?.has_ultrasound" />
                  <FacilityChip label="ECG"                :has="institution.healthDetail?.has_ecg" />
                  <FacilityChip label="ICU"                :has="institution.healthDetail?.has_icu" />
                  <FacilityChip label="अपरेशन थिएटर"      :has="institution.healthDetail?.has_operation_theatre" />
                  <FacilityChip label="प्रयोगशाला"        :has="institution.healthDetail?.has_lab" />
                  <FacilityChip label="रक्त बैंक"         :has="institution.healthDetail?.has_blood_bank" />
                  <FacilityChip label="डायलाइसिस"         :has="institution.healthDetail?.has_dialysis" />
                  <FacilityChip label="दन्त विभाग"        :has="institution.healthDetail?.has_dental" />
                  <FacilityChip label="फिजियोथेरापी"      :has="institution.healthDetail?.has_physiotherapy" />
                  <FacilityChip label="अक्सिजन प्लान्ट"   :has="institution.healthDetail?.has_oxygen_plant" />
                </div>
              </div>

              <!-- Health services tags -->
              <div v-if="institution.healthDetail?.health_services?.length" class="border-t border-[#e6e7e9] pt-4">
                <p class="text-xs font-semibold text-gray-500 mb-2">प्रदान गरिने स्वास्थ्य सेवाहरू</p>
                <div class="flex flex-wrap gap-2">
                  <span v-for="svc in institution.healthDetail.health_services" :key="svc"
                    class="bg-red-50 text-red-700 text-xs px-2.5 py-1 rounded-full border border-red-100 font-medium">{{ optLabel('inst_health_service', svc) }}</span>
                </div>
              </div>

              <!-- Equipment tags -->
              <div v-if="institution.healthDetail?.equipment?.length" class="border-t border-[#e6e7e9] pt-4">
                <p class="text-xs font-semibold text-gray-500 mb-2">उपकरणहरू</p>
                <div class="flex flex-wrap gap-2">
                  <span v-for="eq in institution.healthDetail.equipment" :key="eq"
                    class="bg-pink-50 text-pink-700 text-xs px-2.5 py-1 rounded-full border border-pink-100 font-medium">{{ optLabel('inst_health_equipment', eq) }}</span>
                </div>
              </div>

              <!-- Lab services -->
              <div v-if="institution.healthDetail?.lab_services?.length" class="border-t border-[#e6e7e9] pt-4">
                <p class="text-xs font-semibold text-gray-500 mb-2">प्रयोगशाला सेवाहरू</p>
                <div class="flex flex-wrap gap-2">
                  <span v-for="lab in institution.healthDetail.lab_services" :key="lab"
                    class="bg-purple-50 text-purple-700 text-xs px-2.5 py-1 rounded-full border border-purple-100 font-medium">{{ optLabel('inst_lab_service', lab) }}</span>
                </div>
              </div>
            </div>
          </section>

          <!-- Health staff -->
          <section v-if="sd.health_staff?.length" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b border-[#e6e7e9] bg-rose-50">
              <div class="flex items-center gap-2">
                <span class="w-6 h-6 rounded bg-rose-100 flex items-center justify-center">
                  <i class="fa-solid fa-user-doctor text-rose-600 text-xs"></i>
                </span>
                <h2 class="text-sm font-semibold text-rose-800">स्वास्थ्यकर्मी विवरण</h2>
              </div>
              <span class="bg-rose-100 text-rose-700 text-xs px-2 py-0.5 rounded-full font-semibold">{{ sd.health_staff.length }} जना</span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-xs">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="text-left px-3 py-2 text-gray-500 font-semibold rounded-l-lg">नाम</th>
                    <th class="text-left px-3 py-2 text-gray-500 font-semibold">पद / श्रेणी</th>
                    <th class="text-left px-3 py-2 text-gray-500 font-semibold">लिङ्ग</th>
                    <th class="text-left px-3 py-2 text-gray-500 font-semibold rounded-r-lg">योग्यता</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#e6e7e9]">
                  <tr v-for="(hs, idx) in sd.health_staff" :key="idx" class="hover:bg-gray-50">
                    <td class="px-3 py-2.5 font-medium text-gray-800">{{ hs.full_name || '—' }}</td>
                    <td class="px-3 py-2.5 text-gray-600">{{ optLabel('inst_health_staff_type', hs.staff_type) }}</td>
                    <td class="px-3 py-2.5 text-gray-600">{{ lbl('gender', hs.gender) }}</td>
                    <td class="px-3 py-2.5 text-gray-600">{{ optLabel('education_level', hs.qualification) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </template>

        <!-- ⑨ सहकारी विवरण ────────────────────────────────────────── -->
        <section v-else-if="institutionSlug === 'cooperative' && hasTypespecific"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <SectionHeader icon="fa-solid fa-handshake" color="green" title="सहकारी विवरण" />
          <div class="p-5 space-y-4">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
              <DetailRow label="सहकारीको प्रकार" :value="optLabel('inst_coop_type', ts.coop_type)" />
              <DetailRow label="शेयर पुँजी (रु.)" :value="ts.share_capital ? ts.share_capital.toLocaleString() : null" />
              <DetailRow label="कुल ऋण लगानी (रु.)" :value="ts.loan_outstanding ? ts.loan_outstanding.toLocaleString() : null" />
            </div>
            <div v-if="ts.male_members || ts.female_members" class="border-t border-[#e6e7e9] pt-4">
              <p class="text-xs font-semibold text-gray-500 mb-3">सदस्य संख्या</p>
              <div class="grid grid-cols-3 gap-4">
                <StatCard label="पुरुष सदस्य" :value="ts.male_members" color="blue" />
                <StatCard label="महिला सदस्य" :value="ts.female_members" color="pink" />
                <StatCard label="अन्य सदस्य" :value="ts.other_members" color="gray" />
              </div>
            </div>
          </div>
        </section>

        <!-- ⑩ वित्तीय संस्था (Bank) ──────────────────────────────── -->
        <section v-else-if="institutionSlug === 'bank' && hasTypespecific"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <SectionHeader icon="fa-solid fa-landmark" color="emerald" title="वित्तीय संस्था विवरण" />
          <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-4">
            <DetailRow label="वित्तीय संस्थाको प्रकार" :value="optLabel('inst_bank_type', ts.bank_type)" />
            <DetailRow label="शाखा संख्या" :value="ts.branch_count ? String(ts.branch_count) : null" />
            <DetailRow label="कुल निक्षेप (रु. लाखमा)" :value="ts.total_deposits ? ts.total_deposits.toLocaleString() : null" />
            <DetailRow label="कुल ऋण लगानी (रु. लाखमा)" :value="ts.total_loans ? ts.total_loans.toLocaleString() : null" />
          </div>
        </section>

        <!-- ⑪ सरकारी कार्यालय ──────────────────────────────────── -->
        <section v-else-if="institutionSlug === 'govt_office' && hasTypespecific"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <SectionHeader icon="fa-solid fa-flag" color="slate" title="कार्यालय विवरण" />
          <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-4">
            <DetailRow label="कार्यालयको तह" :value="optLabel('inst_office_level', ts.office_level)" />
            <DetailRow label="विभाग / मन्त्रालय" :value="ts.department" />
          </div>
        </section>

        <!-- ⑫ NGO/CBO ─────────────────────────────────────────────── -->
        <section v-else-if="institutionSlug === 'ngo_cbo' && hasTypespecific"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <SectionHeader icon="fa-solid fa-globe" color="cyan" title="गैसस / सामुदायिक संगठन विवरण" />
          <div class="p-5 space-y-4">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
              <DetailRow label="दर्ताको प्रकार" :value="optLabel('inst_registration_type', ts.registration_type)" />
              <DetailRow label="प्रमुख दाता / साझेदार" :value="ts.donor" />
              <DetailRow label="लाभग्राही (पुरुष)" :value="ts.beneficiaries_m ? String(ts.beneficiaries_m) : null" />
              <DetailRow label="लाभग्राही (महिला)" :value="ts.beneficiaries_f ? String(ts.beneficiaries_f) : null" />
            </div>
            <div v-if="ts.working_areas?.length" class="border-t border-[#e6e7e9] pt-3">
              <p class="text-xs font-semibold text-gray-500 mb-2">कार्य क्षेत्रहरू</p>
              <div class="flex flex-wrap gap-2">
                <span v-for="area in ts.working_areas" :key="area"
                  class="bg-cyan-50 text-cyan-700 text-xs px-2.5 py-1 rounded-full border border-cyan-100 font-medium">{{ optLabel('inst_working_area', area) }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- ⑬ धार्मिक संस्था ─────────────────────────────────────── -->
        <section v-else-if="institutionSlug === 'religious' && hasTypespecific"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <SectionHeader icon="fa-solid fa-place-of-worship" color="amber" title="धार्मिक संस्था विवरण" />
          <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-4">
            <DetailRow label="धर्म / संस्थाको प्रकार" :value="optLabel('inst_religion_type', ts.religion_type)" />
            <DetailRow label="दैनिक भक्तजन/दर्शनार्थी" :value="ts.daily_visitors ? String(ts.daily_visitors) : null" />
            <div>
              <p class="text-xs text-gray-400 mb-0.5">गुठी / ट्रस्ट</p>
              <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', ts.has_trust ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                {{ ts.has_trust ? 'दर्ता छ' : 'दर्ता छैन' }}
              </span>
            </div>
          </div>
        </section>

        <!-- ⑭ बजार ───────────────────────────────────────────────── -->
        <section v-else-if="institutionSlug === 'market' && hasTypespecific"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <SectionHeader icon="fa-solid fa-store" color="orange" title="बजार विवरण" />
          <div class="p-5 space-y-4">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
              <DetailRow label="पसल/प्रतिष्ठान संख्या" :value="ts.shop_count ? String(ts.shop_count) : null" />
              <DetailRow label="प्रमुख वस्तु / व्यापार" :value="ts.goods_types" />
            </div>
            <div v-if="ts.market_days?.length" class="border-t border-[#e6e7e9] pt-3">
              <p class="text-xs font-semibold text-gray-500 mb-2">बजार बस्ने दिन</p>
              <div class="flex flex-wrap gap-2">
                <span v-for="d in ts.market_days" :key="d"
                  class="bg-orange-50 text-orange-700 text-xs px-2.5 py-1 rounded-full border border-orange-100 font-medium">{{ d }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- ⑮ उद्योग ──────────────────────────────────────────────── -->
        <section v-else-if="institutionSlug === 'industry' && hasTypespecific"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <SectionHeader icon="fa-solid fa-industry" color="zinc" title="उद्योग विवरण" />
          <div class="p-5 space-y-4">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
              <DetailRow label="उद्योगको प्रकार" :value="optLabel('inst_industry_type', ts.industry_type)" />
              <DetailRow label="वार्षिक उत्पादन (रु. लाखमा)" :value="ts.annual_production ? ts.annual_production.toLocaleString() : null" />
            </div>
            <div v-if="ts.male_workers || ts.female_workers" class="border-t border-[#e6e7e9] pt-4">
              <p class="text-xs font-semibold text-gray-500 mb-3">कामदार संख्या</p>
              <div class="grid grid-cols-3 gap-4">
                <StatCard label="पुरुष कामदार" :value="ts.male_workers" color="blue" />
                <StatCard label="महिला कामदार" :value="ts.female_workers" color="pink" />
                <StatCard label="जम्मा" :value="(Number(ts.male_workers)||0) + (Number(ts.female_workers)||0)" color="gray" />
              </div>
            </div>
          </div>
        </section>

        <!-- ⑯ मिडिया ──────────────────────────────────────────────── -->
        <section v-else-if="institutionSlug === 'media' && hasTypespecific"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <SectionHeader icon="fa-solid fa-newspaper" color="purple" title="मिडिया विवरण" />
          <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-4">
            <DetailRow label="मिडियाको प्रकार" :value="optLabel('inst_media_type', ts.media_type)" />
            <DetailRow label="प्रसार क्षेत्र" :value="ts.coverage_area" />
            <DetailRow label="प्रकाशन आवृत्ति" :value="lbl('frequency', ts.frequency) || ts.frequency" />
          </div>
        </section>

        <!-- ⑰ वन/सामुदायिक वन ────────────────────────────────────── -->
        <template v-else-if="institutionSlug === 'forest_group' && hasTypespecific">
          <!-- §A Forest info -->
          <section class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
            <SectionHeader icon="fa-solid fa-tree" color="green" title="वन विवरण" />
            <div class="p-5 space-y-4">
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <DetailRow label="वनको किसिम" :value="optLabel('inst_forest_type', ts.forest_type)" />
                <DetailRow label="वनको क्षेत्रफल (हे.)" :value="ts.forest_area_hectare ? String(ts.forest_area_hectare) : null" />
                <DetailRow label="समेटेका वडाहरु" :value="ts.forest_covered_wards" />
                <DetailRow label="उपभोक्ता घरधुरी" :value="ts.consumer_households ? String(ts.consumer_households) : null" />
                <DetailRow label="जम्मा रुखको संख्या" :value="ts.total_trees ? String(ts.total_trees) : null" />
                <DetailRow label="वार्षिक बजेट (रु.)" :value="ts.annual_budget ? Number(ts.annual_budget).toLocaleString() : null" />
                <DetailRow label="वार्षिक आम्दानी (रु.)" :value="ts.annual_income ? Number(ts.annual_income).toLocaleString() : null" />
                <DetailRow label="समिति अध्यक्ष" :value="ts.committee_chairperson" />
              </div>
              <!-- Members -->
              <div v-if="ts.member_count_m || ts.member_count_f" class="border-t border-[#e6e7e9] pt-4">
                <p class="text-xs font-semibold text-gray-500 mb-3">सदस्यता</p>
                <div class="grid grid-cols-3 gap-4">
                  <StatCard label="पुरुष सदस्य" :value="ts.member_count_m" color="blue" />
                  <StatCard label="महिला सदस्य" :value="ts.member_count_f" color="pink" />
                  <StatCard label="जम्मा" :value="(Number(ts.member_count_m)||0) + (Number(ts.member_count_f)||0)" color="gray" />
                </div>
              </div>
              <!-- Conservation flags -->
              <div class="border-t border-[#e6e7e9] pt-4 flex flex-wrap gap-3">
                <span :class="['text-xs font-medium px-2.5 py-1 rounded-full border', ts.has_conservation_plan ? 'bg-green-50 text-green-700 border-green-200' : 'bg-gray-100 text-gray-500 border-gray-200']">
                  <i :class="['fa-solid mr-1 text-[10px]', ts.has_conservation_plan ? 'fa-check' : 'fa-xmark']"></i>संरक्षण कार्ययोजना
                </span>
                <span :class="['text-xs font-medium px-2.5 py-1 rounded-full border', ts.encroachment_issue ? 'bg-red-50 text-red-600 border-red-200' : 'bg-green-50 text-green-700 border-green-200']">
                  <i :class="['fa-solid mr-1 text-[10px]', ts.encroachment_issue ? 'fa-triangle-exclamation' : 'fa-check']"></i>अतिक्रमण समस्या {{ ts.encroachment_issue ? 'छ' : 'छैन' }}
                </span>
                <span v-if="ts.fire_incidents_yearly" class="text-xs font-medium px-2.5 py-1 rounded-full border bg-orange-50 text-orange-700 border-orange-200">
                  <i class="fa-solid fa-fire mr-1 text-[10px]"></i>वार्षिक डढेलो: {{ ts.fire_incidents_yearly }}
                </span>
              </div>
            </div>
          </section>
          <!-- Flora/Fauna tags -->
          <section v-if="ts.tree_types?.length || ts.herb_types?.length || ts.wildlife?.length || ts.forest_products?.length"
            class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
            <SectionHeader icon="fa-solid fa-seedling" color="emerald" title="वनस्पति तथा वन्यजन्तु" />
            <div class="p-5 space-y-4">
              <div v-if="ts.tree_types?.length">
                <p class="text-xs font-semibold text-gray-500 mb-2">रुखका प्रजाति</p>
                <div class="flex flex-wrap gap-2">
                  <span v-for="t in ts.tree_types" :key="t" class="bg-green-50 text-green-700 text-xs px-2.5 py-1 rounded-full border border-green-100">{{ t }}</span>
                </div>
              </div>
              <div v-if="ts.herb_types?.length" class="border-t border-[#e6e7e9] pt-3">
                <p class="text-xs font-semibold text-gray-500 mb-2">जडिबुटी / औषधीय वनस्पति</p>
                <div class="flex flex-wrap gap-2">
                  <span v-for="h in ts.herb_types" :key="h" class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-1 rounded-full border border-emerald-100">{{ h }}</span>
                </div>
              </div>
              <div v-if="ts.wildlife?.length" class="border-t border-[#e6e7e9] pt-3">
                <p class="text-xs font-semibold text-gray-500 mb-2">वन्यजन्तु</p>
                <div class="flex flex-wrap gap-2">
                  <span v-for="w in ts.wildlife" :key="w" class="bg-amber-50 text-amber-700 text-xs px-2.5 py-1 rounded-full border border-amber-100">{{ w }}</span>
                </div>
              </div>
              <div v-if="ts.forest_products?.length" class="border-t border-[#e6e7e9] pt-3">
                <p class="text-xs font-semibold text-gray-500 mb-2">वन उत्पादन</p>
                <div class="flex flex-wrap gap-2">
                  <span v-for="p in ts.forest_products" :key="p" class="bg-orange-50 text-orange-700 text-xs px-2.5 py-1 rounded-full border border-orange-100">{{ p }}</span>
                </div>
              </div>
            </div>
          </section>
        </template>

        <!-- ⑱ खेल क्लब ────────────────────────────────────────────── -->
        <section v-else-if="institutionSlug === 'sports_club' && hasTypespecific"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <SectionHeader icon="fa-solid fa-futbol" color="sky" title="खेल क्लब विवरण" />
          <div class="p-5 space-y-4">
            <div class="grid grid-cols-3 gap-4">
              <StatCard label="पुरुष खेलाडी" :value="ts.sports_member_m" color="blue" />
              <StatCard label="महिला खेलाडी" :value="ts.sports_member_f" color="pink" />
              <StatCard label="जम्मा खेलाडी" :value="(Number(ts.sports_member_m)||0) + (Number(ts.sports_member_f)||0)" color="gray" />
            </div>
            <div v-if="ts.game_types?.length" class="border-t border-[#e6e7e9] pt-3">
              <p class="text-xs font-semibold text-gray-500 mb-2">खेलकुदका प्रकार</p>
              <div class="flex flex-wrap gap-2">
                <span v-for="g in ts.game_types" :key="g" class="bg-sky-50 text-sky-700 text-xs px-2.5 py-1 rounded-full border border-sky-100 font-medium">{{ g }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- ⑲ सामुदायिक पूर्वाधार ─────────────────────────────────── -->
        <section v-else-if="institutionSlug === 'community_infra' && hasTypespecific"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <SectionHeader icon="fa-solid fa-city" color="violet" title="सामुदायिक पूर्वाधार विवरण" />
          <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-4">
            <DetailRow label="क्षमता (व्यक्ति)" :value="ts.capacity ? String(ts.capacity) : null" />
            <DetailRow label="प्रयोगको आवृत्ति" :value="lbl('frequency', ts.frequency)" />
          </div>
        </section>

        <!-- ⑳ पर्यटन ──────────────────────────────────────────────── -->
        <section v-else-if="institutionSlug === 'park_tourism' && hasTypespecific"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <SectionHeader icon="fa-solid fa-mountain-sun" color="teal" title="पर्यटन विवरण" />
          <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-4">
            <DetailRow label="पर्यटन स्थलको प्रकार" :value="lbl('park_type', ts.capacity) || ts.capacity" />
            <DetailRow label="वार्षिक पर्यटक संख्या" :value="ts.annual_visitors ? Number(ts.annual_visitors).toLocaleString() : null" />
            <DetailRow label="प्रवेश शुल्क (रु.)" :value="ts.entry_fee ? Number(ts.entry_fee).toLocaleString() : null" />
          </div>
        </section>

        <!-- Other institution types — fallback health section only for types without a dedicated section -->
        <section v-if="institution.healthDetail && !['school','health_post','cooperative','bank','govt_office','ngo_cbo','religious','market','industry','media','forest_group','sports_club','community_infra','park_tourism'].includes(institutionSlug)"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="flex items-center gap-2 px-5 py-3 border-b border-[#e6e7e9] bg-red-50">
            <span class="w-6 h-6 rounded bg-red-100 flex items-center justify-center">
              <i class="fa-solid fa-heart-pulse text-red-500 text-xs"></i>
            </span>
            <h2 class="text-sm font-semibold text-red-800">स्वास्थ्य विवरण</h2>
          </div>
          <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
            <DetailRow label="शय्या संख्या" :value="institution.healthDetail.bed_count ? String(institution.healthDetail.bed_count) : null" />
            <DetailRow label="दैनिक OPD" :value="institution.healthDetail.opd_daily ? String(institution.healthDetail.opd_daily) : null" />
          </div>
          <div class="px-5 pb-5 flex flex-wrap gap-2">
            <FacilityChip label="एम्बुलेन्स" :has="institution.healthDetail.has_ambulance" />
            <FacilityChip label="X-Ray"      :has="institution.healthDetail.has_xray" />
            <FacilityChip label="प्रयोगशाला" :has="institution.healthDetail.has_lab" />
          </div>
        </section>

        <!-- Generic additional_info fallback -->
        <section v-if="ts.additional_info" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <SectionHeader icon="fa-solid fa-circle-info" color="gray" title="थप जानकारी" />
          <div class="p-5">
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ ts.additional_info }}</p>
          </div>
        </section>

      </div>

      <!-- ── Sidebar (1/3) ──────────────────────────────────────────── -->
      <div class="no-print space-y-4">

        <!-- Status + Actions -->
        <div class="no-print bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">अवस्था</h3>
          <div class="flex items-center gap-2 mb-4">
            <span :class="['text-sm px-3 py-1.5 rounded-full font-semibold', statusBadge(institution.status)]">
              {{ statusLabel(institution.status) }}
            </span>
          </div>
          <div class="space-y-2">
            <a v-if="canEdit" :href="`/institutions/${institution.id}/edit`"
              class="flex items-center justify-center gap-2 w-full rounded-md border border-[#e6e7e9] px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
              <i class="fa-solid fa-pen text-gray-400"></i>
              सम्पादन गर्नुस्
              <span v-if="institution.status === 'published'" class="ml-auto text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-semibold">प्रकाशित</span>
            </a>
            <button v-if="canSubmit" @click="submitInstitution" :disabled="actionLoading"
              class="flex items-center justify-center gap-2 w-full rounded-md bg-teal-600 hover:bg-teal-700 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60">
              <i :class="actionLoading ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-paper-plane'"></i>
              पेस गर्नुस्
            </button>
            <button v-if="canApprove" @click="approveInstitution" :disabled="actionLoading"
              class="flex items-center justify-center gap-2 w-full rounded-md bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60">
              <i :class="actionLoading ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-circle-check'"></i>
              स्वीकृत गर्नुस्
            </button>
            <button v-if="canReject" @click="showInstRejectModal = true" :disabled="actionLoading"
              class="flex items-center justify-center gap-2 w-full rounded-md bg-red-600 hover:bg-red-700 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60">
              <i class="fa-solid fa-circle-xmark"></i>
              अस्वीकार गर्नुस्
            </button>
            <button v-if="canPublish" @click="publishInstitution" :disabled="actionLoading"
              class="flex items-center justify-center gap-2 w-full rounded-md bg-green-600 hover:bg-green-700 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60">
              <i :class="actionLoading ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-globe'"></i>
              प्रकाशित गर्नुस्
            </button>
            <button v-if="canRequestEdit" @click="showRequestModal = true"
              class="flex items-center justify-center gap-2 w-full rounded-md border border-orange-300 bg-orange-50 hover:bg-orange-100 px-4 py-2 text-sm font-medium text-orange-700 transition-colors">
              <i class="fa-solid fa-pen-to-square text-orange-400"></i> सम्पादन अनुरोध
            </button>
            <div v-if="hasPendingEditRequest"
              class="flex items-center gap-2 w-full rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-700">
              <i class="fa-solid fa-clock text-amber-400"></i> अनुरोध पेस गरिएको — समीक्षा बाँकी
            </div>
            <button @click="printPage"
              class="flex items-center justify-center gap-2 w-full rounded-md border border-teal-200 bg-teal-50 hover:bg-teal-100 px-4 py-2 text-sm font-medium text-teal-700 transition-colors">
              <i class="fa-solid fa-print text-teal-400"></i> प्रिन्ट
            </button>
            <a :href="`/institutions/${institution.id}/pdf`" target="_blank"
              class="flex items-center justify-center gap-2 w-full rounded-md border border-rose-200 bg-rose-50 hover:bg-rose-100 px-4 py-2 text-sm font-medium text-rose-700 transition-colors">
              <i class="fa-solid fa-file-pdf text-rose-400"></i> PDF डाउनलोड
            </a>
          </div>
          <div class="mt-4 pt-4 border-t border-gray-50">
            <a href="/institutions" class="flex items-center gap-2 text-xs text-gray-400 hover:text-gray-600 transition-colors">
              <i class="fa-solid fa-arrow-left"></i> सूचीमा फर्कनुस्
            </a>
          </div>
        </div>

        <!-- Photo Card -->
        <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
          <div class="flex items-center gap-2 px-4 py-2.5 border-b border-[#e6e7e9] bg-gray-50">
            <i class="fa-solid fa-camera text-teal-500 text-xs"></i>
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">फोटो</h3>
          </div>
          <div class="p-3 space-y-2">
            <div v-if="photoUrl" class="rounded-md overflow-hidden border border-[#e6e7e9] bg-gray-100">
              <img :src="photoUrl" :alt="`Photo ${institution.photo_number}`"
                class="w-full object-cover" style="max-height:200px;"
                @error="e => { if (photoUrlFallback && e.target.src !== photoUrlFallback) { e.target.src = photoUrlFallback } else { e.target.closest('div').style.display='none' } }" />
            </div>
            <div v-else-if="institution.photo_number" class="rounded-md border border-dashed border-amber-200 bg-amber-50 flex flex-col items-center justify-center py-5 gap-1.5">
              <i class="fa-solid fa-link-slash text-amber-300 text-xl"></i>
              <p class="text-xs text-amber-600 text-center leading-snug">फोटो Base URL<br>सेटिङ गरिएको छैन</p>
            </div>
            <div v-else class="rounded-md border border-dashed border-gray-200 bg-gray-50 flex flex-col items-center justify-center py-6 gap-1.5">
              <i class="fa-solid fa-camera text-gray-300 text-2xl"></i>
              <p class="text-xs text-gray-400">फोटो नम्बर राखिएको छैन</p>
            </div>
            <div class="flex items-center justify-between pt-0.5">
              <div>
                <p class="text-[10px] text-gray-400">फोटो नम्बर</p>
                <p class="text-xs font-mono font-semibold text-gray-700">{{ institution.photo_number || '—' }}</p>
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
            <div>
              <p class="text-xs text-gray-400">अद्वितीय कोड</p>
              <p class="text-sm font-mono font-semibold text-teal-700">{{ institution.unique_code }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400">प्रकार</p>
              <p class="text-sm font-medium text-gray-700">{{ institution.institutionType?.name_np || '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400">गणक</p>
              <p class="text-sm font-medium text-gray-700">{{ institution.enumerator?.name || '—' }}</p>
            </div>
            <div v-if="institution.gps_lat && institution.gps_lng">
              <p class="text-xs text-gray-400">GPS</p>
              <p class="text-xs font-mono text-gray-600">{{ institution.gps_lat }}, {{ institution.gps_lng }}</p>
            </div>
          </div>
        </div>

        <!-- Field Verification -->
        <FieldVerificationCard
          :verification="fieldVerification"
          :can-verify="canVerify"
          :save-url="`/institutions/${institution.id}/field-verify`"
        />

        <!-- Submission Log -->
        <div v-if="institution.submissionLogs?.length" class="no-print bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
            <i class="fa-solid fa-timeline text-teal-400"></i> कार्यक्रम लग
          </h3>
          <div class="relative">
            <div class="absolute left-3 top-0 bottom-0 w-px bg-gray-100"></div>
            <div class="space-y-4">
              <div v-for="log in institution.submissionLogs" :key="log.id" class="relative pl-8">
                <div class="absolute left-0 top-1 w-6 h-6 rounded-full bg-white border-2 border-teal-200 flex items-center justify-center">
                  <div class="w-2 h-2 rounded-full bg-teal-400"></div>
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

  <!-- Edit Request Modal -->
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
            <p class="text-[12px] text-gray-400">संस्था: <span class="font-mono">{{ institution.unique_code }}</span></p>
          </div>
        </div>
        <p class="text-sm text-gray-600 mb-3">यो प्रकाशित रेकर्डमा सम्पादन आवश्यक पर्नुको कारण उल्लेख गर्नुहोस्:</p>
        <textarea v-model="editRequestReason" rows="4"
          placeholder="उदाहरण: संस्थाको नाम परिवर्तन भएको छ / नयाँ शाखा थपिएको छ..."
          class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-300 resize-none"></textarea>
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

  <!-- Reject modal -->
  <Teleport to="body">
    <div v-if="showInstRejectModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
      @click.self="showInstRejectModal = false">
      <div class="bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-md mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-circle-xmark text-red-500"></i>
          </div>
          <div>
            <h3 class="text-[15px] font-semibold text-gray-900">अस्वीकार गर्नुस्</h3>
            <p class="text-[12px] text-gray-400">संस्था: <span class="font-mono">{{ institution.unique_code }}</span></p>
          </div>
        </div>
        <p class="text-sm text-gray-600 mb-3">अस्वीकार गर्नुको कारण उल्लेख गर्नुहोस् (अनिवार्य):</p>
        <textarea
          v-model="instRejectComment"
          rows="3"
          placeholder="उदाहरण: विवरण अपूर्ण छ / कर्मचारी संख्या गलत छ..."
          class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-300 resize-none"
        ></textarea>
        <div class="flex gap-3 mt-4">
          <button @click="showInstRejectModal = false"
            class="flex-1 rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
            रद्द गर्नुस्
          </button>
          <button @click="submitInstReject" :disabled="instRejectLoading || !instRejectComment.trim()"
            class="flex-1 rounded-md bg-red-600 hover:bg-red-700 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60">
            <i :class="instRejectLoading ? 'fa-solid fa-spinner fa-spin mr-1' : 'fa-solid fa-circle-xmark mr-1'"></i>
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

const page = usePage()
function printPage() { window.print() }

const props = defineProps({
  institution:            { type: Object,  required: true },
  surveyOpts:             { type: Object,  default: () => ({}) },
  canEdit:                { type: Boolean, default: false },
  canSubmit:              { type: Boolean, default: false },
  canApprove:             { type: Boolean, default: false },
  canReject:              { type: Boolean, default: false },
  canPublish:             { type: Boolean, default: false },
  canRequestEdit:         { type: Boolean, default: false },
  hasPendingEditRequest:  { type: Boolean, default: false },
  canVerify:              { type: Boolean, default: false },
  fieldVerification:      { type: Object,  default: null },
})

// ── Data accessors ─────────────────────────────────────────────────────────

const sd               = computed(() => props.institution.survey_data ?? {})
const ts               = computed(() => sd.value.typespecific ?? {})
const ec               = computed(() => sd.value.executive_committee ?? {})
const institutionSlug  = computed(() => props.institution.institutionType?.slug ?? '')
const hasTypespecific  = computed(() => Object.values(ts.value).some(v => v !== '' && v !== null && v !== undefined && !(Array.isArray(v) && !v.length)))

// ── Label maps ─────────────────────────────────────────────────────────────

const staticLabels = {
  ownership_type: { govt:'सरकारी', private:'निजी', community:'सामुदायिक', ngo:'गैसस/NGO', cooperative:'सहकारी', other:'अन्य' },
  gender:         { male:'पुरुष', female:'महिला', other:'अन्य' },
  condition:      { good:'राम्रो', average:'मध्यम', poor:'जीर्ण', damaged:'क्षतिग्रस्त', under_const:'निर्माणाधीन' },
  frequency:      { daily:'दैनिक', weekly:'साप्ताहिक', monthly:'मासिक', occasional:'अवसरमा मात्र' },
  park_type:      { natural_park:'प्राकृतिक उद्यान', heritage_site:'सांस्कृतिक सम्पदा', eco_tourism:'ईको–पर्यटन', adventure:'साहसिक पर्यटन', picnic_spot:'पिकनिक स्थल' },
}
function lbl(map, value) {
  if (!value && value !== false) return null
  return staticLabels[map]?.[value] ?? value
}
// Resolve label from dynamic survey options (DB-backed)
function optLabel(category, value) {
  if (!value) return '—'
  const found = (props.surveyOpts[category] ?? []).find(o => o.value === value)
  return found?.label_ne ?? value
}

// ── Photo ──────────────────────────────────────────────────────────────────

const photoUrl = computed(() => {
  const base = usePage().props.auth?.lg?.photo_dir?.institution_base_url
  const num  = props.institution.photo_number
  if (!base || !num) return null
  return `${base}/${num}.png?v=${encodeURIComponent(num)}`
})
const photoUrlFallback = computed(() => {
  const base = usePage().props.auth?.lg?.photo_dir?.institution_base_url
  const num  = props.institution.photo_number
  if (!base || !num) return null
  return `${base}/${num}.jpeg?v=${encodeURIComponent(num)}`
})

// ── Inline sub-components ─────────────────────────────────────────────────

const DetailRow = {
  props: ['label', 'value', 'mono'],
  render() {
    return h('div', [
      h('p', { class: 'text-xs text-gray-400 mb-0.5' }, this.label),
      h('p', { class: ['text-sm font-medium text-gray-800', this.mono ? 'font-mono' : ''].join(' ') }, this.value ?? '—'),
    ])
  },
}

const FacilityChip = {
  props: ['label', 'has'],
  render() {
    return h('span', {
      class: [
        'inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full font-medium border',
        this.has ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-500 border-red-200',
      ],
    }, [
      h('i', { class: ['fa-solid text-xs', this.has ? 'fa-check' : 'fa-xmark'] }),
      ' ' + this.label,
    ])
  },
}

const colorMap = {
  blue:    { bg: 'bg-blue-50',    text: 'text-blue-700',    label: 'text-blue-400' },
  pink:    { bg: 'bg-pink-50',    text: 'text-pink-700',    label: 'text-pink-400' },
  gray:    { bg: 'bg-gray-50',    text: 'text-gray-700',    label: 'text-gray-400' },
  green:   { bg: 'bg-green-50',   text: 'text-green-700',   label: 'text-green-400' },
  amber:   { bg: 'bg-amber-50',   text: 'text-amber-700',   label: 'text-amber-400' },
  teal:    { bg: 'bg-teal-50',    text: 'text-teal-700',    label: 'text-teal-400' },
}

const StatCard = {
  props: ['label', 'value', 'color'],
  render() {
    const c = colorMap[this.color] ?? colorMap.gray
    return h('div', { class: `rounded-md border border-[#e6e7e9] p-3 text-center ${c.bg}` }, [
      h('p', { class: `text-xl font-bold ${c.text}` }, this.value ?? '—'),
      h('p', { class: `text-[11px] ${c.label} mt-0.5` }, this.label),
    ])
  },
}

const headerColorMap = {
  green:   { bg: 'bg-green-50',   text: 'text-green-800',   icon: 'text-green-600',   badge: 'bg-green-100' },
  emerald: { bg: 'bg-emerald-50', text: 'text-emerald-800', icon: 'text-emerald-600', badge: 'bg-emerald-100' },
  slate:   { bg: 'bg-slate-50',   text: 'text-slate-800',   icon: 'text-slate-600',   badge: 'bg-slate-100' },
  cyan:    { bg: 'bg-cyan-50',    text: 'text-cyan-800',    icon: 'text-cyan-600',    badge: 'bg-cyan-100' },
  amber:   { bg: 'bg-amber-50',   text: 'text-amber-800',   icon: 'text-amber-600',   badge: 'bg-amber-100' },
  orange:  { bg: 'bg-orange-50',  text: 'text-orange-800',  icon: 'text-orange-600',  badge: 'bg-orange-100' },
  zinc:    { bg: 'bg-zinc-50',    text: 'text-zinc-800',    icon: 'text-zinc-600',    badge: 'bg-zinc-100' },
  purple:  { bg: 'bg-purple-50',  text: 'text-purple-800',  icon: 'text-purple-600',  badge: 'bg-purple-100' },
  sky:     { bg: 'bg-sky-50',     text: 'text-sky-800',     icon: 'text-sky-600',     badge: 'bg-sky-100' },
  violet:  { bg: 'bg-violet-50',  text: 'text-violet-800',  icon: 'text-violet-600',  badge: 'bg-violet-100' },
  teal:    { bg: 'bg-teal-50',    text: 'text-teal-800',    icon: 'text-teal-600',    badge: 'bg-teal-100' },
  gray:    { bg: 'bg-gray-50',    text: 'text-gray-700',    icon: 'text-gray-500',    badge: 'bg-gray-100' },
}

const SectionHeader = {
  props: ['icon', 'color', 'title'],
  render() {
    const c = headerColorMap[this.color] ?? headerColorMap.gray
    return h('div', { class: `flex items-center gap-2 px-5 py-3 border-b border-[#e6e7e9] ${c.bg}` }, [
      h('span', { class: `w-6 h-6 rounded ${c.badge} flex items-center justify-center` }, [
        h('i', { class: `${this.icon} ${c.icon} text-xs` }),
      ]),
      h('h2', { class: `text-sm font-semibold ${c.text}` }, this.title),
    ])
  },
}

// ── Helpers ───────────────────────────────────────────────────────────────

function statusBadge(s) {
  const map = { draft:'bg-gray-100 text-gray-600', submitted:'bg-yellow-100 text-yellow-700', ward_approved:'bg-blue-100 text-blue-700', rejected:'bg-red-100 text-red-700', published:'bg-green-100 text-green-700' }
  return map[s] ?? 'bg-gray-100 text-gray-600'
}
function statusLabel(s) {
  const map = { draft:'मस्यौदा', submitted:'पेस भएको', ward_approved:'वडा अनुमोदित', rejected:'अस्वीकृत', published:'प्रकाशित' }
  return map[s] ?? s
}
function actionBadge(a) {
  const map = { submitted:'bg-yellow-100 text-yellow-700', approved:'bg-blue-100 text-blue-700', rejected:'bg-red-100 text-red-600', published:'bg-green-100 text-green-700' }
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
function sumField(field) {
  return (props.institution.education || []).reduce((sum, row) => sum + (Number(row[field]) || 0), 0)
}

// ── Actions ───────────────────────────────────────────────────────────────

const actionLoading      = ref(false)
const showInstRejectModal = ref(false)
const instRejectComment   = ref('')
const instRejectLoading   = ref(false)

function submitInstitution() {
  if (!confirm('के तपाईं यो संस्था पेस गर्न निश्चित हुनुहुन्छ?')) return
  actionLoading.value = true
  router.post(`/institutions/${props.institution.id}/submit`, {}, { onFinish: () => { actionLoading.value = false } })
}

function approveInstitution() {
  if (!confirm('के तपाईं यो संस्था स्वीकृत गर्न निश्चित हुनुहुन्छ?')) return
  actionLoading.value = true
  router.patch(`/review/institutions/${props.institution.id}/approve`, {}, { onFinish: () => { actionLoading.value = false } })
}

function submitInstReject() {
  if (!instRejectComment.value.trim()) return
  instRejectLoading.value = true
  router.patch(`/review/institutions/${props.institution.id}/reject`, { comment: instRejectComment.value }, {
    onSuccess: () => { showInstRejectModal.value = false; instRejectComment.value = '' },
    onFinish:  () => { instRejectLoading.value = false },
  })
}
function publishInstitution() {
  if (!confirm('के तपाईं यो संस्था प्रकाशित गर्न निश्चित हुनुहुन्छ?')) return
  actionLoading.value = true
  router.patch(`/admin/institutions/${props.institution.id}/publish`, {}, { onFinish: () => { actionLoading.value = false } })
}

// ── Edit Request ──────────────────────────────────────────────────────────

const showRequestModal  = ref(false)
const editRequestReason = ref('')
const requestLoading    = ref(false)
function submitEditRequest() {
  if (!editRequestReason.value.trim()) return
  requestLoading.value = true
  router.post('/edit-requests', {
    requestable_type: 'institution',
    requestable_id:   props.institution.id,
    reason:           editRequestReason.value,
  }, {
    onSuccess: () => { showRequestModal.value = false; editRequestReason.value = '' },
    onFinish:  () => { requestLoading.value = false },
  })
}
</script>

<style>
@media print {
  @page { size: A4; margin: 10mm 12mm; }

  /* ── CRITICAL: un-clip layout so all pages render ── */
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

  /* Force colour printing */
  * { print-color-adjust: exact !important; -webkit-print-color-adjust: exact !important; }

  /* ── Hide UI chrome ── */
  .no-print { display: none !important; }

  /* ── Collapse sidebar: show only main 2/3 column ── */
  .grid.grid-cols-1.lg\:grid-cols-3 { display: block !important; }
  .lg\:col-span-2 { width: 100% !important; max-width: 100% !important; }
  /* Hide the sidebar column entirely */
  .grid.grid-cols-1.lg\:grid-cols-3 > div:last-child { display: none !important; }

  /* ── Sections: un-clip, avoid page breaks inside ── */
  section {
    overflow: visible !important;
    break-inside: avoid;
    page-break-inside: avoid;
    margin-bottom: 6pt !important;
    box-shadow: none !important;
    border: 0.5pt solid #d1d5db !important;
    border-radius: 4pt !important;
  }

  /* ── Un-clip table scroll wrappers ── */
  .overflow-x-auto,
  .overflow-hidden {
    overflow: visible !important;
  }

  /* ── Detail grids: 4 columns ── */
  .grid.grid-cols-2,
  .grid.sm\:grid-cols-3,
  .grid.sm\:grid-cols-4,
  .grid.lg\:grid-cols-4,
  .grid.grid-cols-3 {
    grid-template-columns: repeat(4, 1fr) !important;
    gap: 4pt 8pt !important;
  }

  /* ── Stat cards: inline row for print ── */
  .grid.grid-cols-3 {
    grid-template-columns: repeat(3, 1fr) !important;
  }

  /* ── Text: compact ── */
  p, span, td, th, div { font-size: 8pt !important; line-height: 1.35 !important; }
  .text-xs   { font-size: 7pt  !important; }
  .text-sm   { font-size: 8pt  !important; }
  .text-xl, .text-2xl { font-size: 11pt !important; }
  .text-\[11px\], .text-\[12px\], .text-\[13px\] { font-size: 7pt !important; }

  /* ── Tables ── */
  table { font-size: 7pt !important; width: 100% !important; table-layout: fixed !important; }
  td, th { padding: 2pt 4pt !important; word-break: break-word !important; }

  /* ── Spacing ── */
  .space-y-2 > * + *, .space-y-3 > * + *,
  .space-y-4 > * + *, .space-y-5 > * + * { margin-top: 5pt !important; }
  .mb-4 { margin-bottom: 4pt !important; }
  .mt-4 { margin-top: 4pt !important; }
  .p-5  { padding: 8pt !important; }
  .px-5 { padding-left: 8pt !important; padding-right: 8pt !important; }
  .py-3, .py-2 { padding-top: 3pt !important; padding-bottom: 3pt !important; }

  /* ── Section header bars ── */
  .flex.items-center.gap-2.px-5.py-3.border-b {
    padding: 4pt 8pt !important;
  }

  /* ── Facility chips: compact ── */
  .rounded-full { padding: 1pt 5pt !important; }

  /* ── Borders ── */
  .border, .border-t, .border-b { border-color: #e5e7eb !important; }
  .shadow-sm, .shadow { box-shadow: none !important; }

  /* ── Show print-only header, hide screen header ── */
  .print-only { display: block !important; }
}
</style>
