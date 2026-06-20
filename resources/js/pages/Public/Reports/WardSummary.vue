<template>
  <div class="w-full px-4 sm:px-6 py-6 space-y-5">

    <!-- ── Print-only header ──────────────────────────────────────────────── -->
    <div class="print-only hidden">
      <div class="flex items-center justify-between border-b-2 border-gray-800 pb-3 mb-4">
        <div>
          <p class="text-xl font-bold text-gray-900">{{ props.lg?.name_np }}</p>
          <p class="text-sm text-gray-500">{{ props.lg?.name_en }}</p>
        </div>
        <div class="text-right">
          <p class="text-base font-bold text-gray-800">वडागत सारांश प्रतिवेदन</p>
          <p class="text-xs text-gray-500 mt-0.5">Ward-wise Summary Report</p>
          <p class="text-xs text-gray-400 mt-0.5">मिति: {{ printDate }}</p>
        </div>
      </div>
    </div>

    <!-- ── Screen header ──────────────────────────────────────────────────── -->
    <div class="no-print flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-[18px] font-bold text-[#1d273b]">वडागत सारांश</h1>
        <p class="text-[12px] text-[#9aa0ac] mt-0.5">Ward-wise Population & Household Summary</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="printReport"
          class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#1d273b] hover:bg-[#2d3a4f] text-white text-[12px] font-medium rounded-md transition-colors">
          <i class="fa-solid fa-print text-[11px]"></i> PDF / प्रिन्ट
        </button>
        <a href="/public/reports" class="inline-flex items-center gap-1.5 text-[12px] text-[#206bc4] hover:underline">
          <i class="fa-solid fa-arrow-left text-[10px]"></i> प्रतिवेदन सूची
        </a>
      </div>
    </div>

    <!-- ── Summary stat strip ─────────────────────────────────────────────── -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <div v-for="card in statCards" :key="card.key"
        class="bg-white border border-[#e6e7e9] rounded-xl p-4 relative overflow-hidden group hover:border-[#206bc4]/30 transition-colors">
        <!-- Accent bar -->
        <div class="absolute top-0 left-0 right-0 h-[3px] rounded-t-xl" :class="card.bar"></div>
        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1 mt-1">{{ card.label }}</p>
        <p class="text-[26px] font-extrabold leading-none" :class="card.color">{{ fmt(card.value) }}</p>
        <!-- Mini relative bar -->
        <div class="mt-2.5 h-1 w-full bg-gray-100 rounded-full overflow-hidden">
          <div class="h-full rounded-full transition-all duration-700" :class="card.bar" :style="`width:${card.bar_pct}%`"></div>
        </div>
        <p class="text-[10px] text-[#9aa0ac] mt-1">{{ card.sub }}</p>
      </div>
    </div>

    <!-- ── Ward summary table ─────────────────────────────────────────────── -->
    <div class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden shadow-sm">
      <div class="flex items-center justify-between px-5 py-3.5 border-b border-[#e6e7e9] bg-gray-50/70">
        <div>
          <p class="text-[13px] font-semibold text-[#1d273b]">वडागत विवरण</p>
          <p class="text-[11px] text-[#9aa0ac] mt-0.5">घरधुरी, जनसंख्या, संस्था, व्यवसाय र साक्षरता — पङ्क्ति थिचेर विस्तृत हेर्नुस्</p>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-[11px] text-[#9aa0ac] bg-white border border-[#e6e7e9] px-2.5 py-1 rounded-full">
            {{ props.wardSummary.length }} वडा
          </span>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="border-b border-[#e6e7e9] bg-gray-50">
            <tr>
              <th @click="setSort('ward_number')"
                class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] cursor-pointer hover:text-[#206bc4] select-none whitespace-nowrap">
                वडा <SortIcon :col="'ward_number'" :sortKey="sortKey" :sortDir="sortDir" />
              </th>
              <th @click="setSort('households')"
                class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] cursor-pointer hover:text-[#206bc4] select-none whitespace-nowrap">
                घरधुरी <SortIcon :col="'households'" :sortKey="sortKey" :sortDir="sortDir" />
              </th>
              <th @click="setSort('male')"
                class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-blue-400 cursor-pointer hover:text-blue-600 select-none whitespace-nowrap">
                पुरुष <SortIcon :col="'male'" :sortKey="sortKey" :sortDir="sortDir" />
              </th>
              <th @click="setSort('female')"
                class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-pink-400 cursor-pointer hover:text-pink-600 select-none whitespace-nowrap">
                महिला <SortIcon :col="'female'" :sortKey="sortKey" :sortDir="sortDir" />
              </th>
              <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-gray-400 whitespace-nowrap">
                अन्य
              </th>
              <th @click="setSort('total')"
                class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] cursor-pointer hover:text-[#206bc4] select-none whitespace-nowrap">
                जम्मा <SortIcon :col="'total'" :sortKey="sortKey" :sortDir="sortDir" />
              </th>
              <th class="px-4 py-2.5 text-center text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] whitespace-nowrap min-w-[130px]">
                लिंग अनुपात
              </th>
              <th @click="setSort('institutions')"
                class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-violet-400 cursor-pointer hover:text-violet-600 select-none whitespace-nowrap">
                संस्था <SortIcon :col="'institutions'" :sortKey="sortKey" :sortDir="sortDir" />
              </th>
              <th @click="setSort('businesses')"
                class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-emerald-500 cursor-pointer hover:text-emerald-700 select-none whitespace-nowrap">
                व्यवसाय <SortIcon :col="'businesses'" :sortKey="sortKey" :sortDir="sortDir" />
              </th>
              <th @click="setSort('litpct')"
                class="px-4 py-2.5 text-center text-[11px] font-semibold uppercase tracking-wide text-teal-500 cursor-pointer hover:text-teal-700 select-none whitespace-nowrap">
                साक्षरता <SortIcon :col="'litpct'" :sortKey="sortKey" :sortDir="sortDir" />
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(ws, idx) in sortedWards" :key="ws.ward_id"
              @click="openModal(idx)"
              class="border-b border-[#e6e7e9] hover:bg-blue-50/40 cursor-pointer transition-colors group">

              <!-- Ward cell -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-2.5">
                  <span class="w-7 h-7 rounded-lg flex items-center justify-center text-[12px] font-bold shrink-0
                               bg-[#206bc4]/10 text-[#206bc4] group-hover:bg-[#206bc4] group-hover:text-white transition-colors">
                    {{ ws.ward_number }}
                  </span>
                  <span class="text-[13px] font-medium text-[#1d273b] group-hover:text-[#206bc4] transition-colors">वडा {{ ws.ward_number }}</span>
                  <i class="fa-solid fa-chevron-right text-[9px] text-[#9aa0ac] group-hover:text-[#206bc4] opacity-0 group-hover:opacity-100 transition-all"></i>
                </div>
              </td>

              <td class="px-4 py-3 text-right text-[13px] text-[#1d273b] tabular-nums">{{ fmt(ws.households) }}</td>
              <td class="px-4 py-3 text-right text-[13px] text-blue-600 tabular-nums font-medium">{{ fmt(ws.male) }}</td>
              <td class="px-4 py-3 text-right text-[13px] text-pink-500 tabular-nums font-medium">{{ fmt(ws.female) }}</td>
              <td class="px-4 py-3 text-right text-[13px] text-gray-400 tabular-nums">{{ fmt(ws.other) }}</td>
              <td class="px-4 py-3 text-right text-[13px] font-semibold text-[#1d273b] tabular-nums">{{ fmt(ws.total) }}</td>

              <!-- Gender ratio bar -->
              <td class="px-4 py-3">
                <div v-if="ws.total > 0" class="flex items-center gap-1.5">
                  <span class="text-[11px] text-blue-500 tabular-nums w-8 text-right">{{ malePct(ws) }}%</span>
                  <div class="flex-1 h-2 rounded-full overflow-hidden bg-gray-100 relative min-w-[60px]">
                    <div class="absolute left-0 top-0 h-full bg-blue-400 rounded-l-full" :style="`width:${malePct(ws)}%`"></div>
                    <div class="absolute right-0 top-0 h-full bg-pink-300 rounded-r-full" :style="`width:${femalePct(ws)}%`"></div>
                  </div>
                  <span class="text-[11px] text-pink-400 tabular-nums w-8">{{ femalePct(ws) }}%</span>
                </div>
                <span v-else class="text-[11px] text-gray-300">—</span>
              </td>

              <td class="px-4 py-3 text-right text-[13px] text-violet-600 tabular-nums">{{ fmt(ws.institutions) }}</td>
              <td class="px-4 py-3 text-right text-[13px] text-emerald-600 tabular-nums">{{ fmt(ws.businesses) }}</td>

              <!-- Literacy badge -->
              <td class="px-4 py-3 text-center">
                <span class="inline-block px-2 py-0.5 rounded-full text-[12px] font-semibold" :class="litBadge(ws)">
                  {{ litPct(ws) }}%
                </span>
              </td>
            </tr>
          </tbody>

          <tfoot class="bg-gray-50/80 border-t-2 border-[#e6e7e9]">
            <tr>
              <td class="px-4 py-2.5 text-[13px] font-bold text-[#1d273b]">जम्मा</td>
              <td class="px-4 py-2.5 text-right text-[13px] font-bold text-[#1d273b] tabular-nums">{{ fmt(totalHH) }}</td>
              <td class="px-4 py-2.5 text-right text-[13px] font-bold text-blue-600 tabular-nums">{{ fmt(totalMale) }}</td>
              <td class="px-4 py-2.5 text-right text-[13px] font-bold text-pink-500 tabular-nums">{{ fmt(totalFemale) }}</td>
              <td class="px-4 py-2.5 text-right text-[13px] font-bold text-gray-400 tabular-nums">{{ fmt(totalOther) }}</td>
              <td class="px-4 py-2.5 text-right text-[13px] font-bold text-[#1d273b] tabular-nums">{{ fmt(totalPop) }}</td>
              <td></td>
              <td class="px-4 py-2.5 text-right text-[13px] font-bold text-violet-600 tabular-nums">{{ fmt(totalInstitutions) }}</td>
              <td class="px-4 py-2.5 text-right text-[13px] font-bold text-emerald-600 tabular-nums">{{ fmt(totalBusinesses) }}</td>
              <td class="px-4 py-2.5 text-center">
                <span class="inline-block px-2 py-0.5 rounded-full text-[12px] font-bold"
                  :class="litBadge({ literate: totalLiterate, literate_total: totalLiterateBase })">
                  {{ totalLiteracyPct }}%
                </span>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- ── Bar chart ──────────────────────────────────────────────────────── -->
    <div v-if="props.wardSummary.length" class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden shadow-sm">
      <div class="px-5 py-3.5 border-b border-[#e6e7e9] bg-gray-50/70">
        <p class="text-[13px] font-semibold text-[#1d273b]">वडा अनुसार घरधुरी र जनसंख्या</p>
        <p class="text-[11px] text-[#9aa0ac] mt-0.5">Ward-wise comparison — click a bar to open details</p>
      </div>
      <div class="p-4 chart-container" style="height:280px;">
        <canvas ref="barEl"></canvas>
      </div>
    </div>

    <!-- ── Print footer ───────────────────────────────────────────────────── -->
    <div class="print-only hidden text-center text-[10px] text-gray-400 pt-4 border-t border-gray-200">
      यो प्रतिवेदन {{ props.lg?.name_np }} को डेटा प्रणालीबाट स्वचालित रूपमा तयार गरिएको हो।
    </div>

  </div>

  <!-- ── Ward detail modal ──────────────────────────────────────────────── -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="activeWard"
        class="fixed inset-0 z-[200] flex items-end sm:items-center justify-center p-0 sm:p-4 no-print"
        @click.self="closeModal">

        <!-- Backdrop -->
        <div class="absolute inset-0 bg-[#0b1e35]/70 backdrop-blur-[2px]" @click="closeModal"></div>

        <!-- Panel -->
        <Transition name="modal-slide">
          <div v-if="activeWard"
            class="relative bg-white w-full sm:max-w-3xl rounded-t-2xl sm:rounded-2xl shadow-2xl z-10 max-h-[92vh] flex flex-col overflow-hidden">

            <!-- Modal header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-[#e6e7e9] shrink-0">
              <!-- Prev arrow -->
              <button @click="prevWard"
                :disabled="activeIdx === 0"
                class="w-8 h-8 rounded-full flex items-center justify-center text-[#626976] hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                <i class="fa-solid fa-chevron-left text-[12px]"></i>
              </button>

              <!-- Ward title -->
              <div class="text-center">
                <div class="flex items-center justify-center gap-2">
                  <span class="w-8 h-8 rounded-lg bg-[#206bc4] flex items-center justify-center text-white font-bold text-[14px] shrink-0">
                    {{ activeWard.ward_number }}
                  </span>
                  <span class="text-[15px] font-bold text-[#1d273b]">वडा {{ activeWard.ward_number }}</span>
                </div>
                <p class="text-[11px] text-[#9aa0ac] mt-0.5">{{ activeIdx + 1 }} / {{ sortedWards.length }}</p>
              </div>

              <!-- Next + close -->
              <div class="flex items-center gap-1">
                <button @click="nextWard"
                  :disabled="activeIdx === sortedWards.length - 1"
                  class="w-8 h-8 rounded-full flex items-center justify-center text-[#626976] hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                  <i class="fa-solid fa-chevron-right text-[12px]"></i>
                </button>
                <button @click="closeModal"
                  class="w-8 h-8 rounded-full flex items-center justify-center text-[#626976] hover:bg-red-50 hover:text-red-500 transition-colors ml-1">
                  <i class="fa-solid fa-xmark text-[13px]"></i>
                </button>
              </div>
            </div>

            <!-- Modal body — scrollable -->
            <div class="overflow-y-auto flex-1 p-5 space-y-5">

              <!-- Stat cards row -->
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-xl border border-[#e6e7e9] p-3.5 bg-amber-50/60">
                  <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-600 mb-1">घरधुरी</p>
                  <p class="text-[22px] font-extrabold text-amber-700 leading-none">{{ fmt(activeWard.households) }}</p>
                  <p class="text-[10px] text-amber-500 mt-1">
                    जम्माको {{ totalHH > 0 ? Math.round(activeWard.households / totalHH * 100) : 0 }}%
                  </p>
                </div>
                <div class="rounded-xl border border-[#e6e7e9] p-3.5 bg-blue-50/60">
                  <p class="text-[10px] font-semibold uppercase tracking-wide text-blue-600 mb-1">जनसंख्या</p>
                  <p class="text-[22px] font-extrabold text-blue-700 leading-none">{{ fmt(activeWard.total) }}</p>
                  <p class="text-[10px] text-blue-500 mt-1">
                    औसत {{ activeWard.households > 0 ? (activeWard.total / activeWard.households).toFixed(1) : '—' }} प्रति घर
                  </p>
                </div>
                <div class="rounded-xl border border-[#e6e7e9] p-3.5 bg-teal-50/60">
                  <p class="text-[10px] font-semibold uppercase tracking-wide text-teal-600 mb-1">साक्षरता</p>
                  <p class="text-[22px] font-extrabold leading-none" :class="litColor(activeWard)">{{ litPct(activeWard) }}%</p>
                  <p class="text-[10px] text-teal-500 mt-1">{{ fmt(activeWard.literate) }} / {{ fmt(activeWard.literate_total) }}</p>
                </div>
                <div class="rounded-xl border border-[#e6e7e9] p-3.5 bg-violet-50/60">
                  <p class="text-[10px] font-semibold uppercase tracking-wide text-violet-600 mb-1">संस्था + व्यवसाय</p>
                  <p class="text-[22px] font-extrabold text-violet-700 leading-none">
                    {{ fmt(activeWard.institutions + activeWard.businesses) }}
                  </p>
                  <p class="text-[10px] text-violet-500 mt-1">
                    {{ fmt(activeWard.institutions) }} संस्था · {{ fmt(activeWard.businesses) }} व्यवसाय
                  </p>
                </div>
              </div>

              <!-- Gender split -->
              <div class="rounded-xl border border-[#e6e7e9] p-4">
                <p class="text-[12px] font-semibold text-[#1d273b] mb-3">लिंग वितरण</p>
                <div class="flex items-center gap-5">
                  <!-- SVG donut -->
                  <div class="shrink-0 relative w-[72px] h-[72px]">
                    <svg viewBox="0 0 36 36" class="w-full h-full -rotate-90">
                      <!-- Background -->
                      <circle cx="18" cy="18" r="15.9" fill="none" stroke="#f3f4f6" stroke-width="4"/>
                      <!-- Male (blue) -->
                      <circle cx="18" cy="18" r="15.9" fill="none" stroke="#3B82F6" stroke-width="4"
                        :stroke-dasharray="`${malePctModal} ${100 - malePctModal}`"
                        stroke-dashoffset="0"/>
                      <!-- Female (pink) — starts after male -->
                      <circle cx="18" cy="18" r="15.9" fill="none" stroke="#EC4899" stroke-width="4"
                        :stroke-dasharray="`${femalePctModal} ${100 - femalePctModal}`"
                        :stroke-dashoffset="`${-(malePctModal)}`"/>
                      <!-- Other (gray) -->
                      <circle v-if="otherPctModal > 0" cx="18" cy="18" r="15.9" fill="none" stroke="#d1d5db" stroke-width="4"
                        :stroke-dasharray="`${otherPctModal} ${100 - otherPctModal}`"
                        :stroke-dashoffset="`${-(malePctModal + femalePctModal)}`"/>
                    </svg>
                    <!-- Center label -->
                    <div class="absolute inset-0 flex items-center justify-center">
                      <span class="text-[10px] font-bold text-[#1d273b]">{{ fmt(activeWard.total) }}</span>
                    </div>
                  </div>

                  <!-- Gender breakdown bars -->
                  <div class="flex-1 space-y-2.5">
                    <div>
                      <div class="flex items-center justify-between mb-1">
                        <span class="text-[11px] text-[#626976] flex items-center gap-1.5">
                          <span class="w-2 h-2 rounded-full bg-blue-400 shrink-0"></span>पुरुष
                        </span>
                        <span class="text-[12px] font-semibold text-blue-600 tabular-nums">{{ fmt(activeWard.male) }} ({{ malePctModal }}%)</span>
                      </div>
                      <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-400 rounded-full transition-all duration-500" :style="`width:${malePctModal}%`"></div>
                      </div>
                    </div>
                    <div>
                      <div class="flex items-center justify-between mb-1">
                        <span class="text-[11px] text-[#626976] flex items-center gap-1.5">
                          <span class="w-2 h-2 rounded-full bg-pink-400 shrink-0"></span>महिला
                        </span>
                        <span class="text-[12px] font-semibold text-pink-500 tabular-nums">{{ fmt(activeWard.female) }} ({{ femalePctModal }}%)</span>
                      </div>
                      <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-pink-400 rounded-full transition-all duration-500" :style="`width:${femalePctModal}%`"></div>
                      </div>
                    </div>
                    <div v-if="activeWard.other > 0">
                      <div class="flex items-center justify-between mb-1">
                        <span class="text-[11px] text-[#626976] flex items-center gap-1.5">
                          <span class="w-2 h-2 rounded-full bg-gray-300 shrink-0"></span>अन्य
                        </span>
                        <span class="text-[12px] font-semibold text-gray-500 tabular-nums">{{ fmt(activeWard.other) }} ({{ otherPctModal }}%)</span>
                      </div>
                      <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gray-300 rounded-full transition-all duration-500" :style="`width:${otherPctModal}%`"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Comparison bars — this ward vs all wards (by max) -->
              <div class="rounded-xl border border-[#e6e7e9] p-4">
                <p class="text-[12px] font-semibold text-[#1d273b] mb-3">
                  तुलनात्मक विश्लेषण
                  <span class="text-[10px] font-normal text-[#9aa0ac] ml-1">नगरपालिका जम्माको तुलनामा</span>
                </p>
                <div class="space-y-3">
                  <div v-for="metric in comparisonMetrics" :key="metric.key">
                    <div class="flex items-center justify-between mb-1">
                      <span class="text-[11px] text-[#626976]">{{ metric.label }}</span>
                      <div class="flex items-center gap-2">
                        <span class="text-[12px] font-semibold text-[#1d273b] tabular-nums">{{ fmt(metric.value) }}</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium"
                          :class="metric.rank <= Math.ceil(sortedWards.length / 3) ? 'bg-green-100 text-green-700' : metric.rank <= Math.ceil(sortedWards.length * 2 / 3) ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-600'">
                          #{{ metric.rank }}
                        </span>
                      </div>
                    </div>
                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                      <div class="h-full rounded-full transition-all duration-700" :class="metric.barClass"
                        :style="`width:${metric.pct}%`"></div>
                    </div>
                  </div>
                </div>
              </div>

            </div>

            <!-- Modal footer -->
            <div class="px-5 py-3.5 border-t border-[#e6e7e9] bg-gray-50/70 shrink-0 flex items-center justify-between">
              <p class="text-[11px] text-[#9aa0ac]">
                <i class="fa-regular fa-keyboard mr-1"></i>
                ← → बाण चाबी, Esc बन्द
              </p>
              <a :href="`/public/reports/ward/${activeWard.ward_number}`"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#206bc4] hover:bg-[#1a56a0] text-white text-[12px] font-medium rounded-lg transition-colors">
                विस्तृत प्रोफाइल
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
              </a>
            </div>

          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, defineComponent, h } from 'vue'
import Chart from 'chart.js/auto'
import PublicReportsLayout from '@/layouts/PublicReportsLayout.vue'

defineOptions({ layout: PublicReportsLayout })

// ── Sort icon inline component ────────────────────────────────────────────
const SortIcon = defineComponent({
  props: { col: String, sortKey: String, sortDir: String },
  setup(p) {
    return () => {
      if (p.sortKey !== p.col) return h('i', { class: 'fa-solid fa-sort text-[9px] opacity-30 ml-0.5' })
      return h('i', { class: `fa-solid fa-sort-${p.sortDir === 'asc' ? 'up' : 'down'} text-[9px] ml-0.5 text-[#206bc4]` })
    }
  },
})

const props = defineProps({
  lg:          { type: Object, default: null },
  wardSummary: { type: Array,  default: () => [] },
  wards:       { type: Array,  default: () => [] },
  settlements: { type: Array,  default: () => [] },
  filters:     { type: Object, default: () => ({}) },
})

// ── Sort state ────────────────────────────────────────────────────────────
const sortKey = ref('ward_number')
const sortDir = ref('asc')

function setSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = 'asc'
  }
}

const litPct  = ws => ws.literate_total > 0 ? Math.round(ws.literate / ws.literate_total * 100) : 0
const malePct = ws => ws.total > 0 ? Math.round(ws.male / ws.total * 100) : 0
const femalePct = ws => ws.total > 0 ? Math.round(ws.female / ws.total * 100) : 0

const sortedWards = computed(() => {
  const arr = [...props.wardSummary].map(w => ({ ...w, litpct: litPct(w) }))
  const dir = sortDir.value === 'asc' ? 1 : -1
  return arr.sort((a, b) => {
    const av = a[sortKey.value] ?? 0
    const bv = b[sortKey.value] ?? 0
    return (av - bv) * dir
  })
})

// ── Helpers ───────────────────────────────────────────────────────────────
const fmt = n => Number(n ?? 0).toLocaleString()

const litColor = ws => {
  const p = litPct(ws)
  if (p >= 80) return 'text-green-600'
  if (p >= 60) return 'text-teal-500'
  if (p >= 40) return 'text-amber-500'
  return 'text-red-500'
}

const litBadge = ws => {
  const p = litPct(ws)
  if (p >= 80) return 'bg-green-100 text-green-700'
  if (p >= 60) return 'bg-teal-100 text-teal-700'
  if (p >= 40) return 'bg-amber-100 text-amber-700'
  if (p > 0)   return 'bg-red-100 text-red-600'
  return 'bg-gray-100 text-gray-400'
}

const printDate = new Date().toLocaleDateString('ne-NP', { year: 'numeric', month: 'long', day: 'numeric' })

// ── Totals ────────────────────────────────────────────────────────────────
const totalHH           = computed(() => props.wardSummary.reduce((s, w) => s + w.households,    0))
const totalPop          = computed(() => props.wardSummary.reduce((s, w) => s + w.total,          0))
const totalMale         = computed(() => props.wardSummary.reduce((s, w) => s + w.male,           0))
const totalFemale       = computed(() => props.wardSummary.reduce((s, w) => s + w.female,         0))
const totalOther        = computed(() => props.wardSummary.reduce((s, w) => s + w.other,          0))
const totalInstitutions = computed(() => props.wardSummary.reduce((s, w) => s + w.institutions,   0))
const totalBusinesses   = computed(() => props.wardSummary.reduce((s, w) => s + w.businesses,     0))
const totalLiterate     = computed(() => props.wardSummary.reduce((s, w) => s + w.literate,       0))
const totalLiterateBase = computed(() => props.wardSummary.reduce((s, w) => s + w.literate_total, 0))
const totalLiteracyPct  = computed(() =>
  totalLiterateBase.value > 0 ? Math.round(totalLiterate.value / totalLiterateBase.value * 100) : 0
)

// ── Stat cards with mini progress bars ───────────────────────────────────
const statCards = computed(() => [
  { key: 'hh',    label: 'जम्मा घरधुरी',  value: totalHH.value,          color: 'text-amber-600',  bar: 'bg-amber-400',  bar_pct: 100,  sub: `${props.wardSummary.length} वडामा` },
  { key: 'pop',   label: 'जम्मा जनसंख्या', value: totalPop.value,         color: 'text-[#1d273b]',  bar: 'bg-[#206bc4]',  bar_pct: 100,  sub: `${totalHH.value > 0 ? (totalPop.value / totalHH.value).toFixed(1) : '—'} औसत/घर` },
  { key: 'male',  label: 'पुरुष',          value: totalMale.value,        color: 'text-blue-600',   bar: 'bg-blue-400',   bar_pct: totalPop.value > 0 ? Math.round(totalMale.value / totalPop.value * 100) : 50,  sub: `${totalPop.value > 0 ? Math.round(totalMale.value / totalPop.value * 100) : 0}% जनसंख्या` },
  { key: 'fem',   label: 'महिला',          value: totalFemale.value,      color: 'text-pink-500',   bar: 'bg-pink-400',   bar_pct: totalPop.value > 0 ? Math.round(totalFemale.value / totalPop.value * 100) : 50, sub: `${totalPop.value > 0 ? Math.round(totalFemale.value / totalPop.value * 100) : 0}% जनसंख्या` },
  { key: 'inst',  label: 'संस्थाहरू',     value: totalInstitutions.value, color: 'text-violet-600', bar: 'bg-violet-400', bar_pct: 100,  sub: 'प्रकाशित संस्था' },
  { key: 'biz',   label: 'व्यवसायहरू',    value: totalBusinesses.value,   color: 'text-emerald-600', bar: 'bg-emerald-400', bar_pct: 100, sub: 'दर्ता व्यवसाय' },
])

// ── Modal state ───────────────────────────────────────────────────────────
const activeIdx  = ref(-1)
const activeWard = computed(() => activeIdx.value >= 0 ? sortedWards.value[activeIdx.value] : null)

function openModal(idx) {
  activeIdx.value = idx
}
function closeModal() {
  activeIdx.value = -1
}
function prevWard() {
  if (activeIdx.value > 0) activeIdx.value--
}
function nextWard() {
  if (activeIdx.value < sortedWards.value.length - 1) activeIdx.value++
}

// Keyboard navigation
function onKeydown(e) {
  if (!activeWard.value) return
  if (e.key === 'Escape')     { closeModal(); return }
  if (e.key === 'ArrowLeft')  { prevWard();   return }
  if (e.key === 'ArrowRight') { nextWard();   return }
}

// ── Modal computed values ─────────────────────────────────────────────────
const malePctModal   = computed(() => activeWard.value ? malePct(activeWard.value)   : 0)
const femalePctModal = computed(() => activeWard.value ? femalePct(activeWard.value) : 0)
const otherPctModal  = computed(() => activeWard.value && activeWard.value.total > 0
  ? Math.round(activeWard.value.other / activeWard.value.total * 100) : 0)

// Max values across all wards (for comparison bars)
const wardMax = computed(() => ({
  households:   Math.max(...props.wardSummary.map(w => w.households), 1),
  total:        Math.max(...props.wardSummary.map(w => w.total), 1),
  institutions: Math.max(...props.wardSummary.map(w => w.institutions), 1),
  businesses:   Math.max(...props.wardSummary.map(w => w.businesses), 1),
  litpct:       Math.max(...props.wardSummary.map(w => litPct(w)), 1),
}))

// Rank by metric (1 = best/highest)
function rankOf(ward, _key, accessor) {
  const sorted = [...props.wardSummary].sort((a, b) => accessor(b) - accessor(a))
  return sorted.findIndex(w => w.ward_id === ward.ward_id) + 1
}

const comparisonMetrics = computed(() => {
  const w = activeWard.value
  if (!w) return []
  const mx = wardMax.value
  return [
    { key: 'hh',    label: 'घरधुरी',      value: w.households,   pct: Math.round(w.households   / mx.households   * 100), barClass: 'bg-amber-400',   rank: rankOf(w, 'hh', x => x.households) },
    { key: 'pop',   label: 'जनसंख्या',    value: w.total,        pct: Math.round(w.total        / mx.total        * 100), barClass: 'bg-blue-400',    rank: rankOf(w, 'pop', x => x.total) },
    { key: 'lit',   label: 'साक्षरता %',  value: litPct(w),      pct: Math.round(litPct(w)      / mx.litpct       * 100), barClass: 'bg-teal-400',    rank: rankOf(w, 'lit', x => litPct(x)) },
    { key: 'inst',  label: 'संस्था',      value: w.institutions, pct: Math.round(w.institutions / mx.institutions * 100), barClass: 'bg-violet-400',  rank: rankOf(w, 'inst', x => x.institutions) },
    { key: 'biz',   label: 'व्यवसाय',    value: w.businesses,   pct: Math.round(w.businesses   / mx.businesses   * 100), barClass: 'bg-emerald-400', rank: rankOf(w, 'biz', x => x.businesses) },
  ]
})

// ── Main bar chart ────────────────────────────────────────────────────────
const barEl = ref(null)
let chart   = null

// Lock/unlock page scroll when modal open
watch(activeWard, (val) => {
  if (typeof document !== 'undefined') {
    document.body.style.overflow = val ? 'hidden' : ''
  }
})

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
  if (!barEl.value || !props.wardSummary.length) return
  chart = new Chart(barEl.value, {
    type: 'bar',
    data: {
      labels: props.wardSummary.map(w => `वडा ${w.ward_number}`),
      datasets: [
        { label: 'घरधुरी',  data: props.wardSummary.map(w => w.households),  backgroundColor: '#F59E0B', borderRadius: 4 },
        { label: 'पुरुष',   data: props.wardSummary.map(w => w.male),         backgroundColor: '#3B82F6', borderRadius: 4 },
        { label: 'महिला',   data: props.wardSummary.map(w => w.female),       backgroundColor: '#EC4899', borderRadius: 4 },
        { label: 'व्यवसाय', data: props.wardSummary.map(w => w.businesses),   backgroundColor: '#10B981', borderRadius: 4 },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      onClick: (_, elements) => {
        if (elements.length) {
          const i = elements[0].index
          const ward = props.wardSummary[i]
          const sortedIdx = sortedWards.value.findIndex(w => w.ward_id === ward.ward_id)
          if (sortedIdx >= 0) openModal(sortedIdx)
        }
      },
      plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
        tooltip: { callbacks: { title: t => t[0].label } },
      },
      scales: {
        y: { beginAtZero: true, grid: { color: '#f3f4f6' } },
        x: { grid: { display: false } },
      },
    },
  })
})

onUnmounted(() => {
  chart?.destroy()
  document.removeEventListener('keydown', onKeydown)
  if (typeof document !== 'undefined') document.body.style.overflow = ''
})

function printReport() {
  window.print()
}
</script>

<style scoped>
/* Modal transitions */
.modal-fade-enter-active,
.modal-fade-leave-active { transition: opacity 0.25s ease; }
.modal-fade-enter-from,
.modal-fade-leave-to    { opacity: 0; }

/* Slide up on mobile, scale on desktop */
.modal-slide-enter-active { transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.25s ease; }
.modal-slide-leave-active { transition: transform 0.2s ease, opacity 0.2s ease; }
.modal-slide-enter-from   { transform: translateY(60px); opacity: 0; }
.modal-slide-leave-to     { transform: translateY(40px); opacity: 0; }

@media (min-width: 640px) {
  .modal-slide-enter-from { transform: scale(0.94) translateY(0); }
  .modal-slide-leave-to   { transform: scale(0.96) translateY(0); }
}

/* Print */
@media print {
  @page { size: A4 landscape; margin: 10mm 12mm; }

  :deep(html), :deep(body), :deep(#app), :deep([data-page]) {
    overflow: visible !important;
    height: auto !important;
  }

  * { print-color-adjust: exact !important; -webkit-print-color-adjust: exact !important; }

  .no-print   { display: none !important; }
  .print-only { display: block !important; }

  .chart-container { height: 220px !important; }
  .w-full { padding: 0 !important; }
}
</style>
