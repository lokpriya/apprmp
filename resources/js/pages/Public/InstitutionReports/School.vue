<template>
  <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-5 space-y-6">

    <!-- ── Page header ── -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-xl shrink-0 bg-[#eff6ff]">
          🎓
        </div>
        <div>
          <h1 class="text-[18px] font-bold text-[#1d273b] leading-tight">
            शैक्षिक विवरण
          </h1>
          <p class="text-[12px] text-[#9aa0ac] mt-0.5">
            Education Report<span v-if="props.lg"> · {{ props.lg.name_en }}</span>
          </p>
        </div>
      </div>
      <div v-if="filters?.ward_id"
        class="inline-flex items-center gap-1 text-[12px] bg-blue-50 text-blue-600
               border border-blue-200 rounded px-2 py-0.5">
        <i class="fa-solid fa-map-pin text-[9px]"></i>
        {{ t('common.ward') }} {{ wardNumber }}
      </div>
    </div>

    <!-- ── KPI Cards — Schools & Students ── -->
    <div v-if="summary">
      <!-- Row 1: school + student numbers -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
        <div v-for="card in studentCards" :key="card.key"
          class="bg-white border border-[#e6e7e9] rounded-lg px-4 py-3.5 border-l-4"
          :style="`border-left-color:${card.color}`">
          <span class="text-[12px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac] leading-tight block">
            {{ card.label }}
          </span>
          <div class="text-[20px] font-bold mt-1" :style="`color:${card.color}`">
            {{ (summary[card.key] ?? 0).toLocaleString() }}
          </div>
        </div>
      </div>
      <!-- Row 2: academic + teachers -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div v-for="card in extraCards" :key="card.key"
          class="bg-white border border-[#e6e7e9] rounded-lg px-4 py-3.5 border-l-4"
          :style="`border-left-color:${card.color}`">
          <span class="text-[12px] font-semibold uppercase tracking-[0.1em] text-[#9aa0ac] leading-tight block">
            {{ card.label }}
          </span>
          <div class="text-[20px] font-bold mt-1" :style="`color:${card.color}`">
            {{ (summary[card.key] ?? 0).toLocaleString() }}
          </div>
        </div>
      </div>
    </div>

    <!-- ── Three breakdown bars ── -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

      <!-- By Level -->
      <div class="bg-white border border-[#e6e7e9] rounded-xl p-5">
        <h3 class="text-[13px] font-semibold text-[#1d273b] mb-4">{{ t('instReports.school.byLevel') }}</h3>
        <div v-if="byLevel.length" class="space-y-2.5">
          <div v-for="row in byLevel" :key="row.code" class="flex items-center gap-2">
            <div class="text-[12px] text-[#626976] w-32 shrink-0 truncate" :title="row.label">
              {{ row.label || row.code }}
            </div>
            <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
              <div class="h-2 rounded-full bg-blue-500 transition-all"
                :style="`width:${maxLevel > 0 ? Math.round(row.count / maxLevel * 100) : 0}%`"></div>
            </div>
            <span class="text-[12px] font-semibold text-[#1d273b] w-6 text-right shrink-0">{{ row.count }}</span>
          </div>
        </div>
        <p v-else class="text-[12px] text-[#9aa0ac]">{{ t('reports.noData') }}</p>
      </div>

      <!-- By Management -->
      <div class="bg-white border border-[#e6e7e9] rounded-xl p-5">
        <h3 class="text-[13px] font-semibold text-[#1d273b] mb-4">{{ t('instReports.school.byManagement') }}</h3>
        <div v-if="byManagement.length" class="space-y-2.5">
          <div v-for="(row, i) in byManagement" :key="row.code" class="flex items-center gap-2">
            <div class="w-2.5 h-2.5 rounded-full shrink-0"
              :style="`background:${palette[i % palette.length]}`"></div>
            <div class="text-[12px] text-[#626976] flex-1 truncate" :title="row.label">
              {{ row.label || row.code }}
            </div>
            <span class="text-[12px] font-semibold text-[#1d273b] shrink-0">{{ row.count }}</span>
          </div>
        </div>
        <p v-else class="text-[12px] text-[#9aa0ac]">{{ t('reports.noData') }}</p>
      </div>

      <!-- By Medium -->
      <div class="bg-white border border-[#e6e7e9] rounded-xl p-5">
        <h3 class="text-[13px] font-semibold text-[#1d273b] mb-4">{{ t('instReports.school.byMedium') }}</h3>
        <div v-if="byMedium.length" class="space-y-2.5">
          <div v-for="(row, i) in byMedium" :key="row.code" class="flex items-center gap-2">
            <div class="w-2.5 h-2.5 rounded-full shrink-0"
              :style="`background:${palette[(i + 3) % palette.length]}`"></div>
            <div class="text-[12px] text-[#626976] flex-1 truncate" :title="row.label">
              {{ row.label || row.code }}
            </div>
            <span class="text-[12px] font-semibold text-[#1d273b] shrink-0">{{ row.count }}</span>
          </div>
        </div>
        <p v-else class="text-[12px] text-[#9aa0ac]">{{ t('reports.noData') }}</p>
      </div>
    </div>

    <!-- ── Grade enrollment table ── -->
    <div v-if="gradeEnrollment.length" class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden">
      <div class="px-5 py-3.5 border-b border-[#e6e7e9] flex items-center justify-between">
        <h2 class="text-[14px] font-semibold text-[#1d273b]">{{ t('instReports.school.gradeEnrollment') }}</h2>
        <span class="text-[12px] text-[#9aa0ac]">
          {{ t('instReports.totalStudents') }}: <b class="text-[#1d273b]">{{ summary?.total_students?.toLocaleString() }}</b>
        </span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
          <thead>
            <tr class="bg-gray-50 border-b border-[#e6e7e9]">
              <th class="text-left px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide w-28">
                {{ locale === 'ne' ? 'कक्षा' : 'Grade' }}
              </th>
              <th class="text-right px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">{{ t('instReports.male') }}</th>
              <th class="text-right px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">{{ t('instReports.female') }}</th>
              <th class="text-right px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">{{ t('instReports.dalit') }}</th>
              <th class="text-right px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">{{ t('instReports.janajati') }}</th>
              <th class="text-right px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">{{ t('instReports.scholarship') }}</th>
              <th class="text-right px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">{{ t('instReports.dropout') }}</th>
              <th class="text-right px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">{{ t('reports.rowTotal') }}</th>
              <th class="px-4 py-2.5 w-32"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in gradeEnrollment" :key="row.grade"
              class="border-b border-[#f0f0f0] hover:bg-gray-50 transition">
              <td class="px-4 py-2.5 font-medium text-[#1d273b]">{{ row.grade_label || row.grade }}</td>
              <td class="px-4 py-2.5 text-right text-[#3b82f6]">{{ row.male.toLocaleString() }}</td>
              <td class="px-4 py-2.5 text-right text-[#ec4899]">{{ row.female.toLocaleString() }}</td>
              <td class="px-4 py-2.5 text-right text-[#626976]">{{ row.dalit.toLocaleString() }}</td>
              <td class="px-4 py-2.5 text-right text-[#626976]">{{ row.janajati.toLocaleString() }}</td>
              <td class="px-4 py-2.5 text-right text-[#0ca678]">{{ row.scholarship.toLocaleString() }}</td>
              <td class="px-4 py-2.5 text-right text-[#d63939]">{{ row.dropout.toLocaleString() }}</td>
              <td class="px-4 py-2.5 text-right font-bold text-[#1d273b]">{{ row.total.toLocaleString() }}</td>
              <!-- Mini bar -->
              <td class="px-4 py-2.5">
                <div class="flex gap-0.5 h-3 overflow-hidden rounded-sm">
                  <div class="bg-[#3b82f6] h-full transition-all"
                    :style="`width:${gradeTotal > 0 ? Math.round(row.male / gradeTotal * 100) : 0}%`"></div>
                  <div class="bg-[#ec4899] h-full transition-all"
                    :style="`width:${gradeTotal > 0 ? Math.round(row.female / gradeTotal * 100) : 0}%`"></div>
                </div>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-gray-50 font-bold">
              <td class="px-4 py-2.5 text-[#1d273b]">{{ t('reports.grandTotal') }}</td>
              <td class="px-4 py-2.5 text-right text-[#3b82f6]">{{ gradeTotal_male.toLocaleString() }}</td>
              <td class="px-4 py-2.5 text-right text-[#ec4899]">{{ gradeTotal_female.toLocaleString() }}</td>
              <td class="px-4 py-2.5 text-right text-[#6366f1]">{{ gradeTotal_dalit.toLocaleString() }}</td>
              <td class="px-4 py-2.5 text-right text-[#6366f1]">{{ gradeTotal_janajati.toLocaleString() }}</td>
              <td class="px-4 py-2.5 text-right text-[#0ca678]">{{ gradeTotal_scholarship.toLocaleString() }}</td>
              <td class="px-4 py-2.5 text-right text-[#d63939]">{{ gradeTotal_dropout.toLocaleString() }}</td>
              <td class="px-4 py-2.5 text-right text-[#6366f1]">{{ gradeTotal.toLocaleString() }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- ── School Directory ── -->
    <div class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden">
      <div class="px-5 py-3.5 border-b border-[#e6e7e9] flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-[14px] font-semibold text-[#1d273b]">{{ t('instReports.school.directory') }}</h2>
        <div class="flex items-center gap-2">
          <!-- Search -->
          <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-[#9aa0ac]"></i>
            <input v-model="search" type="text"
              :placeholder="locale === 'ne' ? 'विद्यालय खोज्नुहोस्…' : 'Search school…'"
              class="pl-7 pr-3 h-8 rounded border border-[#e6e7e9] text-[12px] text-[#1d273b]
                     focus:ring-2 focus:ring-blue-200 focus:border-blue-400 outline-none w-48">
          </div>
          <span class="text-[12px] text-[#9aa0ac]">{{ filteredSchools.length }} / {{ props.schools.length }}</span>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
          <thead>
            <tr class="bg-gray-50 border-b border-[#e6e7e9]">
              <th class="text-left px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide min-w-[200px]">
                {{ t('instReports.school.schoolName') }}
              </th>
              <th class="text-center px-3 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide w-16">
                {{ t('instReports.school.ward') }}
              </th>
              <th class="text-left px-3 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">
                {{ t('instReports.school.level') }}
              </th>
              <th class="text-left px-3 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">
                {{ t('instReports.school.management') }}
              </th>
              <th class="text-left px-3 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">
                {{ t('instReports.school.medium') }}
              </th>
              <th class="text-left px-3 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">
                {{ t('instReports.school.ownership') }}
              </th>
              <th class="text-right px-3 py-2.5 font-semibold text-[#3b82f6] text-[11px] uppercase tracking-wide">
                {{ t('instReports.male') }}
              </th>
              <th class="text-right px-3 py-2.5 font-semibold text-[#ec4899] text-[11px] uppercase tracking-wide">
                {{ t('instReports.female') }}
              </th>
              <th class="text-right px-3 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">
                {{ t('reports.rowTotal') }}
              </th>
              <th class="text-right px-3 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">
                {{ t('instReports.school.teachers') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!filteredSchools.length">
              <td colspan="10" class="px-4 py-8 text-center text-[#9aa0ac] text-[13px]">
                {{ t('reports.noData') }}
              </td>
            </tr>
            <tr v-for="(s, i) in pagedSchools" :key="s.id"
              class="border-b border-[#f0f0f0] hover:bg-blue-50/40 transition"
              :class="i % 2 === 0 ? '' : 'bg-gray-50/50'">
              <td class="px-4 py-2.5">
                <div class="font-medium text-[#1d273b] leading-tight">
                  {{ locale === 'ne' ? (s.name_np || s.name_en) : (s.name_en || s.name_np) }}
                </div>
                <div class="text-[11px] text-[#9aa0ac] mt-0.5">{{ s.unique_code }}</div>
              </td>
              <td class="px-3 py-2.5 text-center">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full
                             bg-blue-100 text-blue-700 text-[11px] font-bold">
                  {{ s.ward }}
                </span>
              </td>
              <td class="px-3 py-2.5">
                <span v-if="s.school_level_lbl"
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-indigo-50 text-indigo-700">
                  {{ s.school_level_lbl }}
                </span>
                <span v-else class="text-[#c8ccd4]">—</span>
              </td>
              <td class="px-3 py-2.5 text-[12px] text-[#626976]">
                {{ s.management_lbl || '—' }}
              </td>
              <td class="px-3 py-2.5 text-[12px] text-[#626976]">
                {{ s.medium_lbl || '—' }}
              </td>
              <td class="px-3 py-2.5">
                <span v-if="s.ownership_type"
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
                  :class="ownershipClass(s.ownership_type)">
                  {{ ownershipLabel(s.ownership_type) }}
                </span>
                <span v-else class="text-[#c8ccd4]">—</span>
              </td>
              <td class="px-3 py-2.5 text-right text-[#3b82f6] font-medium">
                {{ s.total_male.toLocaleString() }}
              </td>
              <td class="px-3 py-2.5 text-right text-[#ec4899] font-medium">
                {{ s.total_female.toLocaleString() }}
              </td>
              <td class="px-3 py-2.5 text-right font-bold text-[#1d273b]">
                {{ s.total_students.toLocaleString() }}
              </td>
              <td class="px-3 py-2.5 text-right">
                <span class="inline-flex items-center gap-1 text-[12px] font-medium text-[#626976]">
                  <i class="fa-solid fa-chalkboard-user text-[10px] text-[#6366f1]"></i>
                  {{ s.total_teachers }}
                </span>
              </td>
            </tr>
          </tbody>
          <tfoot v-if="filteredSchools.length">
            <tr class="bg-gray-50 font-bold border-t border-[#e6e7e9]">
              <td class="px-4 py-2.5 text-[#1d273b]" colspan="6">{{ t('reports.grandTotal') }}</td>
              <td class="px-3 py-2.5 text-right text-[#3b82f6]">{{ filteredMale.toLocaleString() }}</td>
              <td class="px-3 py-2.5 text-right text-[#ec4899]">{{ filteredFemale.toLocaleString() }}</td>
              <td class="px-3 py-2.5 text-right text-[#6366f1]">{{ filteredStudents.toLocaleString() }}</td>
              <td class="px-3 py-2.5 text-right text-[#6366f1]">{{ filteredTeachers.toLocaleString() }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1"
        class="px-5 py-3 border-t border-[#e6e7e9] flex flex-wrap items-center justify-between gap-3 text-[12px]">
        <span class="text-[#9aa0ac]">
          {{ (page - 1) * perPage + 1 }}–{{ Math.min(page * perPage, filteredSchools.length) }}
          {{ locale === 'ne' ? 'मध्ये' : 'of' }} {{ filteredSchools.length }}
        </span>
        <div class="flex items-center gap-1">
          <button @click="page = Math.max(1, page - 1)" :disabled="page === 1"
            class="px-2.5 py-1.5 rounded border border-[#e6e7e9] text-[#626976]
                   disabled:opacity-40 hover:bg-gray-50 transition">
            <i class="fa-solid fa-chevron-left text-[10px]"></i>
          </button>
          <button v-for="p in pageNumbers" :key="p" @click="page = p"
            class="px-2.5 py-1.5 rounded border text-[12px] font-medium transition"
            :class="p === page
              ? 'border-blue-400 bg-blue-50 text-blue-700'
              : 'border-[#e6e7e9] text-[#626976] hover:bg-gray-50'">
            {{ p }}
          </button>
          <button @click="page = Math.min(totalPages, page + 1)" :disabled="page === totalPages"
            class="px-2.5 py-1.5 rounded border border-[#e6e7e9] text-[#626976]
                   disabled:opacity-40 hover:bg-gray-50 transition">
            <i class="fa-solid fa-chevron-right text-[10px]"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- ── Teacher Report ── -->
    <div v-if="props.teachers.length || props.byTeacherCategory.length">

      <!-- Section heading -->
      <div class="flex items-center gap-3 mb-4">
        <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg bg-amber-50 shrink-0">
          👩‍🏫
        </div>
        <div>
          <h2 class="text-[15px] font-bold text-[#1d273b] leading-tight">शिक्षक/शिक्षिका विवरण</h2>
          <p class="text-[12px] text-[#9aa0ac]">Teacher Report</p>
        </div>
      </div>

      <!-- Category breakdown + gender summary -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

        <!-- By Category bars -->
        <div class="lg:col-span-2 bg-white border border-[#e6e7e9] rounded-xl p-5">
          <h3 class="text-[13px] font-semibold text-[#1d273b] mb-4">श्रेणी / Category</h3>
          <div v-if="byTeacherCategory.length" class="space-y-3">
            <div v-for="row in byTeacherCategory" :key="row.code">
              <div class="flex items-center justify-between mb-1">
                <span class="text-[12px] text-[#626976] truncate max-w-[200px]" :title="row.label">
                  {{ row.label || row.code }}
                </span>
                <span class="text-[12px] font-semibold text-[#1d273b] shrink-0 ml-2">{{ row.count }}</span>
              </div>
              <!-- Stacked bar: male + female -->
              <div class="flex h-2 rounded-full overflow-hidden bg-gray-100">
                <div class="bg-[#3b82f6] h-full transition-all"
                  :style="`width:${maxCategoryCount > 0 ? Math.round(row.male / maxCategoryCount * 100) : 0}%`"></div>
                <div class="bg-[#ec4899] h-full transition-all"
                  :style="`width:${maxCategoryCount > 0 ? Math.round(row.female / maxCategoryCount * 100) : 0}%`"></div>
              </div>
              <div class="flex gap-3 mt-1">
                <span class="text-[11px] text-[#3b82f6]">♂ {{ row.male }}</span>
                <span class="text-[11px] text-[#ec4899]">♀ {{ row.female }}</span>
              </div>
            </div>
          </div>
          <p v-else class="text-[12px] text-[#9aa0ac]">{{ t('reports.noData') }}</p>
        </div>

        <!-- Gender summary cards -->
        <div class="flex flex-col gap-3">
          <div class="bg-white border border-[#e6e7e9] rounded-xl p-4 border-l-4 border-l-[#1d273b]">
            <span class="text-[12px] font-semibold uppercase tracking-widest text-[#9aa0ac] block">जम्मा शिक्षक</span>
            <div class="text-[28px] font-bold text-[#1d273b] mt-1">{{ props.teachers.length.toLocaleString() }}</div>
          </div>
          <div class="bg-white border border-[#e6e7e9] rounded-xl p-4 border-l-4 border-l-[#3b82f6]">
            <span class="text-[12px] font-semibold uppercase tracking-widest text-[#9aa0ac] block">{{ t('instReports.male') }}</span>
            <div class="text-[28px] font-bold text-[#3b82f6] mt-1">
              {{ props.teachers.filter(t => t.gender === 'male').length.toLocaleString() }}
            </div>
          </div>
          <div class="bg-white border border-[#e6e7e9] rounded-xl p-4 border-l-4 border-l-[#ec4899]">
            <span class="text-[12px] font-semibold uppercase tracking-widest text-[#9aa0ac] block">{{ t('instReports.female') }}</span>
            <div class="text-[28px] font-bold text-[#ec4899] mt-1">
              {{ props.teachers.filter(t => t.gender === 'female').length.toLocaleString() }}
            </div>
          </div>
        </div>
      </div>

      <!-- Teacher directory table -->
      <div class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-[#e6e7e9] flex flex-wrap items-center justify-between gap-3">
          <h3 class="text-[14px] font-semibold text-[#1d273b]">शिक्षक निर्देशिका</h3>
          <div class="flex items-center gap-2">
            <div class="relative">
              <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-[#9aa0ac]"></i>
              <input v-model="teacherSearch" type="text"
                :placeholder="locale === 'ne' ? 'नाम वा विद्यालय खोज्नुहोस्…' : 'Search teacher or school…'"
                class="pl-7 pr-3 h-8 rounded border border-[#e6e7e9] text-[12px] text-[#1d273b]
                       focus:ring-2 focus:ring-amber-200 focus:border-amber-400 outline-none w-52">
            </div>
            <span class="text-[12px] text-[#9aa0ac]">{{ filteredTeacherRows.length }} / {{ props.teachers.length }}</span>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-[13px]">
            <thead>
              <tr class="bg-gray-50 border-b border-[#e6e7e9]">
                <th class="text-left px-4 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide min-w-[180px]">
                  {{ locale === 'ne' ? 'नाम' : 'Name' }}
                </th>
                <th class="text-left px-3 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide min-w-[160px]">
                  {{ locale === 'ne' ? 'विद्यालय' : 'School' }}
                </th>
                <th class="text-center px-3 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide w-14">
                  {{ t('instReports.school.ward') }}
                </th>
                <th class="text-center px-3 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide w-16">
                  {{ locale === 'ne' ? 'लिंग' : 'Gender' }}
                </th>
                <th class="text-left px-3 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">
                  {{ locale === 'ne' ? 'श्रेणी' : 'Category' }}
                </th>
                <th class="text-left px-3 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">
                  {{ locale === 'ne' ? 'योग्यता' : 'Qualification' }}
                </th>
                <th class="text-left px-3 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">
                  {{ locale === 'ne' ? 'नियुक्ति तह' : 'Level' }}
                </th>
                <th class="text-left px-3 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide">
                  {{ locale === 'ne' ? 'विषय' : 'Subjects' }}
                </th>
                <th class="text-left px-3 py-2.5 font-semibold text-[#626976] text-[11px] uppercase tracking-wide w-28">
                  {{ locale === 'ne' ? 'भर्ना मिति' : 'Joined' }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!filteredTeacherRows.length">
                <td colspan="9" class="px-4 py-8 text-center text-[#9aa0ac] text-[13px]">
                  {{ t('reports.noData') }}
                </td>
              </tr>
              <tr v-for="(t, i) in pagedTeachers" :key="i"
                class="border-b border-[#f0f0f0] hover:bg-amber-50/30 transition"
                :class="i % 2 === 0 ? '' : 'bg-gray-50/50'">
                <td class="px-4 py-2.5 font-medium text-[#1d273b]">
                  {{ t.full_name || '—' }}
                </td>
                <td class="px-3 py-2.5 text-[12px] text-[#626976]">
                  {{ locale === 'ne' ? (t.school_name || t.school_name_en) : (t.school_name_en || t.school_name) }}
                </td>
                <td class="px-3 py-2.5 text-center">
                  <span class="inline-flex items-center justify-center w-6 h-6 rounded-full
                               bg-amber-100 text-amber-700 text-[11px] font-bold">
                    {{ t.ward }}
                  </span>
                </td>
                <td class="px-3 py-2.5 text-center">
                  <span v-if="t.gender === 'male'"
                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-blue-50 text-blue-700">
                    ♂
                  </span>
                  <span v-else-if="t.gender === 'female'"
                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-pink-50 text-pink-700">
                    ♀
                  </span>
                  <span v-else class="text-[#c8ccd4]">—</span>
                </td>
                <td class="px-3 py-2.5 text-[12px] text-[#626976]">
                  <span v-if="t.category_lbl"
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700">
                    {{ t.category_lbl }}
                  </span>
                  <span v-else class="text-[#c8ccd4]">—</span>
                </td>
                <td class="px-3 py-2.5 text-[12px] text-[#626976]">{{ t.qualification_lbl || '—' }}</td>
                <td class="px-3 py-2.5 text-[12px] text-[#626976]">{{ t.level_lbl || '—' }}</td>
                <td class="px-3 py-2.5">
                  <div class="flex flex-wrap gap-1">
                    <span v-if="t.specialization_lbl"
                      class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-indigo-50 text-indigo-600">
                      {{ t.specialization_lbl }}
                    </span>
                    <span v-for="subj in t.subjects" :key="subj"
                      class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-[#626976]">
                      {{ subj }}
                    </span>
                    <span v-if="!t.specialization_lbl && !t.subjects?.length" class="text-[#c8ccd4]">—</span>
                  </div>
                </td>
                <td class="px-3 py-2.5 text-[12px] text-[#9aa0ac]">{{ t.enrollment_date || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="teacherTotalPages > 1"
          class="px-5 py-3 border-t border-[#e6e7e9] flex flex-wrap items-center justify-between gap-3 text-[12px]">
          <span class="text-[#9aa0ac]">
            {{ (teacherPage - 1) * teacherPerPage + 1 }}–{{ Math.min(teacherPage * teacherPerPage, filteredTeacherRows.length) }}
            {{ locale === 'ne' ? 'मध्ये' : 'of' }} {{ filteredTeacherRows.length }}
          </span>
          <div class="flex items-center gap-1">
            <button @click="teacherPage = Math.max(1, teacherPage - 1)" :disabled="teacherPage === 1"
              class="px-2.5 py-1.5 rounded border border-[#e6e7e9] text-[#626976]
                     disabled:opacity-40 hover:bg-gray-50 transition">
              <i class="fa-solid fa-chevron-left text-[10px]"></i>
            </button>
            <button v-for="p in teacherPageNumbers" :key="p" @click="teacherPage = p"
              class="px-2.5 py-1.5 rounded border text-[12px] font-medium transition"
              :class="p === teacherPage
                ? 'border-amber-400 bg-amber-50 text-amber-700'
                : 'border-[#e6e7e9] text-[#626976] hover:bg-gray-50'">
              {{ p }}
            </button>
            <button @click="teacherPage = Math.min(teacherTotalPages, teacherPage + 1)" :disabled="teacherPage === teacherTotalPages"
              class="px-2.5 py-1.5 rounded border border-[#e6e7e9] text-[#626976]
                     disabled:opacity-40 hover:bg-gray-50 transition">
              <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Data note ── -->
    <div class="bg-[#f0f4fa] rounded-lg px-5 py-4 flex items-start gap-3 text-[13px] text-[#626976]">
      <i class="fa-solid fa-circle-info text-[#6366f1] text-sm mt-0.5 shrink-0"></i>
      <span>{{ t('reports.indexNote') }}</span>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import PublicInstitutionReportsLayout from '@/layouts/PublicInstitutionReportsLayout.vue'
import { useLocale } from '@/i18n/index.js'

defineOptions({ layout: PublicInstitutionReportsLayout })

const { locale, t } = useLocale()

const props = defineProps({
  lg:                 { type: Object, default: null },
  wards:              { type: Array,  default: () => [] },
  filters:            { type: Object, default: () => ({}) },
  summary:            { type: Object, default: () => ({}) },
  schools:            { type: Array,  default: () => [] },
  byLevel:            { type: Array,  default: () => [] },
  byManagement:       { type: Array,  default: () => [] },
  byMedium:           { type: Array,  default: () => [] },
  gradeEnrollment:    { type: Array,  default: () => [] },
  teachers:           { type: Array,  default: () => [] },
  byTeacherCategory:  { type: Array,  default: () => [] },
})

// ── KPI cards ──────────────────────────────────────────────────────────────
const studentCards = [
  { key: 'total_schools',  label: t('instReports.school.totalSchools'),  color: '#6366f1' },
  { key: 'total_students', label: t('instReports.school.totalStudents'), color: '#1d273b' },
  { key: 'total_male',     label: t('instReports.male'),                  color: '#3b82f6' },
  { key: 'total_female',   label: t('instReports.female'),                color: '#ec4899' },
]
const extraCards = [
  { key: 'scholarship',    label: t('instReports.scholarship'),           color: '#0ca678' },
  { key: 'dropout',        label: t('instReports.dropout'),               color: '#d63939' },
  { key: 'total_teachers', label: t('instReports.school.totalTeachers'), color: '#1d273b' },
  { key: 'teacher_male',   label: t('instReports.school.teacherMale'),   color: '#3b82f6' },
  { key: 'teacher_female', label: t('instReports.school.teacherFemale'), color: '#ec4899' },
]

const palette = ['#6366f1','#0ca678','#f59f00','#d63939','#ae3ec9','#4299e1','#2fb344','#f76707']

// ── Ward number for filter chip ────────────────────────────────────────────
const wardNumber = computed(() =>
  props.wards.find(w => String(w.id) === String(props.filters?.ward_id))?.ward_number
  ?? props.filters?.ward_id
)

// ── Breakdown max ──────────────────────────────────────────────────────────
const maxLevel = computed(() => props.byLevel.reduce((m, r) => Math.max(m, r.count), 0))

// ── Grade totals ───────────────────────────────────────────────────────────
const gradeTotal        = computed(() => props.gradeEnrollment.reduce((s, r) => s + r.total, 0))
const gradeTotal_male   = computed(() => props.gradeEnrollment.reduce((s, r) => s + r.male, 0))
const gradeTotal_female = computed(() => props.gradeEnrollment.reduce((s, r) => s + r.female, 0))
const gradeTotal_dalit  = computed(() => props.gradeEnrollment.reduce((s, r) => s + r.dalit, 0))
const gradeTotal_janajati    = computed(() => props.gradeEnrollment.reduce((s, r) => s + r.janajati, 0))
const gradeTotal_scholarship = computed(() => props.gradeEnrollment.reduce((s, r) => s + r.scholarship, 0))
const gradeTotal_dropout     = computed(() => props.gradeEnrollment.reduce((s, r) => s + r.dropout, 0))

// ── Directory: search + pagination ────────────────────────────────────────
const search  = ref('')
const page    = ref(1)
const perPage = 20

watch(search, () => { page.value = 1 })

const filteredSchools = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.schools
  return props.schools.filter(s =>
    (s.name_np ?? '').toLowerCase().includes(q) ||
    (s.name_en ?? '').toLowerCase().includes(q) ||
    (s.unique_code ?? '').toLowerCase().includes(q)
  )
})

const totalPages  = computed(() => Math.ceil(filteredSchools.value.length / perPage))
const pagedSchools = computed(() =>
  filteredSchools.value.slice((page.value - 1) * perPage, page.value * perPage)
)
const pageNumbers = computed(() => {
  const total = totalPages.value
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  const p = page.value
  const pages = new Set([1, total, p])
  if (p > 1) pages.add(p - 1)
  if (p < total) pages.add(p + 1)
  return [...pages].sort((a, b) => a - b)
})

// ── Filtered totals ─────────────────────────────────────────────────────
const filteredMale     = computed(() => filteredSchools.value.reduce((s, r) => s + r.total_male, 0))
const filteredFemale   = computed(() => filteredSchools.value.reduce((s, r) => s + r.total_female, 0))
const filteredStudents = computed(() => filteredSchools.value.reduce((s, r) => s + r.total_students, 0))
const filteredTeachers = computed(() => filteredSchools.value.reduce((s, r) => s + r.total_teachers, 0))

// ── Teacher directory: search + pagination ─────────────────────────────────
const teacherSearch = ref('')
const teacherPage   = ref(1)
const teacherPerPage = 25

watch(teacherSearch, () => { teacherPage.value = 1 })

const filteredTeacherRows = computed(() => {
  const q = teacherSearch.value.trim().toLowerCase()
  if (!q) return props.teachers
  return props.teachers.filter(t =>
    (t.full_name       ?? '').toLowerCase().includes(q) ||
    (t.school_name     ?? '').toLowerCase().includes(q) ||
    (t.school_name_en  ?? '').toLowerCase().includes(q) ||
    (t.category_lbl    ?? '').toLowerCase().includes(q)
  )
})

const teacherTotalPages = computed(() => Math.ceil(filteredTeacherRows.value.length / teacherPerPage))
const pagedTeachers     = computed(() =>
  filteredTeacherRows.value.slice((teacherPage.value - 1) * teacherPerPage, teacherPage.value * teacherPerPage)
)
const teacherPageNumbers = computed(() => {
  const total = teacherTotalPages.value
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  const p = teacherPage.value
  const pages = new Set([1, total, p])
  if (p > 1) pages.add(p - 1)
  if (p < total) pages.add(p + 1)
  return [...pages].sort((a, b) => a - b)
})

const maxCategoryCount = computed(() => props.byTeacherCategory.reduce((m, r) => Math.max(m, r.count), 0))

// ── Ownership label/color ──────────────────────────────────────────────────
const ownershipMap = {
  govt:        { label: { ne: 'सरकारी',    en: 'Government'  }, cls: 'bg-blue-50 text-blue-700' },
  private:     { label: { ne: 'निजी',      en: 'Private'     }, cls: 'bg-orange-50 text-orange-700' },
  community:   { label: { ne: 'सामुदायिक', en: 'Community'   }, cls: 'bg-green-50 text-green-700' },
  cooperative: { label: { ne: 'सहकारी',    en: 'Cooperative' }, cls: 'bg-purple-50 text-purple-700' },
  ngo:         { label: { ne: 'गैसस',      en: 'NGO'         }, cls: 'bg-teal-50 text-teal-700' },
  joint:       { label: { ne: 'संयुक्त',   en: 'Joint'       }, cls: 'bg-amber-50 text-amber-700' },
  other:       { label: { ne: 'अन्य',      en: 'Other'       }, cls: 'bg-gray-100 text-gray-600' },
}
function ownershipLabel(val) {
  const m = ownershipMap[val]
  if (!m) return val
  return locale.value === 'ne' ? m.label.ne : m.label.en
}
function ownershipClass(val) {
  return ownershipMap[val]?.cls ?? 'bg-gray-100 text-gray-600'
}
</script>
