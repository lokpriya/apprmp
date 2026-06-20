<template>
  <Head :title="`वडा ${props.ward.ward_number} — वडा प्रोफाइल`" />
  <div class="w-full px-4 sm:px-6 py-6 space-y-6">

    <!-- Print-only header -->
    <div class="print-only hidden border-b-2 border-gray-800 pb-3 mb-2">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-lg font-bold text-gray-900">{{ props.lg?.name_np }}</p>
          <p class="text-sm text-gray-500">{{ props.lg?.name_en }}</p>
        </div>
        <div class="text-right">
          <p class="text-base font-bold text-gray-800">वडा {{ props.ward.ward_number }} — वडा प्रोफाइल</p>
          <p class="text-xs text-gray-400 mt-0.5">मिति: {{ printDate }}</p>
        </div>
      </div>
    </div>

    <!-- Screen header -->
    <div class="no-print flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-[#206bc4] flex items-center justify-center shrink-0">
          <span class="text-white font-bold text-[15px]">{{ props.ward.ward_number }}</span>
        </div>
        <div>
          <h1 class="text-[18px] font-bold text-[#1d273b]">वडा {{ props.ward.ward_number }} — वडा प्रोफाइल</h1>
          <p class="text-[12px] text-[#9aa0ac] mt-0.5">Ward {{ props.ward.ward_number }} Comprehensive Profile Report</p>
        </div>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <select @change="goToWard"
          class="rounded border border-[#e6e7e9] bg-white px-2.5 py-1.5 text-[12px] text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4] outline-none">
          <option v-for="w in props.allWards" :key="w.id" :value="w.id" :selected="w.id === props.ward.id">
            वडा {{ w.ward_number }}
          </option>
        </select>
        <button @click="printPage" aria-label="यो प्रोफाइल PDF मा प्रिन्ट गर्नुहोस्"
          class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#1d273b] hover:bg-[#2d3a4f] text-white text-[12px] font-medium rounded-md transition-colors">
          <i class="fa-solid fa-print text-[11px]" aria-hidden="true"></i> PDF / प्रिन्ट
        </button>
        <a href="/public/reports/ward-summary" class="inline-flex items-center gap-1.5 text-[12px] text-[#206bc4] hover:underline">
          <i class="fa-solid fa-arrow-left text-[10px]"></i> वडागत सारांश
        </a>
      </div>
    </div>

    <!-- ── 1. Overview ─────────────────────────────────────────────────────── -->
    <div>
      <div class="flex items-center gap-2.5 border-b border-[#e6e7e9] pb-2">
        <div class="w-7 h-7 rounded bg-[#206bc4]/10 flex items-center justify-center shrink-0">
          <i class="fa-solid fa-house-chimney text-[#206bc4] text-[12px]"></i>
        </div>
        <div>
          <p class="text-[14px] font-bold text-[#1d273b]">सिंहावलोकन</p>
          <p class="text-[11px] text-[#9aa0ac]">Ward Overview</p>
        </div>
      </div>

      <!-- Empty state — no published data for this ward -->
      <div v-if="!o.households && !o.population"
        class="mt-4 rounded-xl bg-amber-50 border border-amber-100 px-5 py-4 flex items-start gap-3">
        <i class="fa-solid fa-circle-info text-amber-400 mt-0.5 shrink-0"></i>
        <div>
          <p class="text-sm font-semibold text-amber-800">यस वडामा प्रकाशित तथ्याङ्क उपलब्ध छैन</p>
          <p class="text-xs text-amber-600 mt-0.5">वडा नं. {{ props.ward.ward_number }} का घरधुरी सर्वेक्षण डेटा अझै प्रकाशित भएको छैन।</p>
        </div>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mt-3">
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">घरधुरी</p>
          <p class="text-2xl font-bold text-amber-600">{{ fmt(o.households) }}</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">घर</p>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">जनसंख्या</p>
          <p class="text-2xl font-bold text-[#1d273b]">{{ fmt(o.population) }}</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">व्यक्ति</p>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">पुरुष</p>
          <p class="text-2xl font-bold text-blue-600">{{ fmt(o.male) }}</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">व्यक्ति</p>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">महिला</p>
          <p class="text-2xl font-bold text-pink-500">{{ fmt(o.female) }}</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">व्यक्ति</p>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">औसत घरधुरी साइज</p>
          <p class="text-2xl font-bold text-[#1d273b]">{{ o.avg_hh_size }}</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">सदस्य / घर</p>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">साक्षरता दर</p>
          <p class="text-2xl font-bold" :class="litColor(o.literacy_pct)">{{ o.literacy_pct }}%</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">१५+ उमेरका</p>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">संस्थाहरू</p>
          <p class="text-2xl font-bold text-violet-600">{{ fmt(o.institutions) }}</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">GPS दर्ता</p>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">व्यवसायहरू</p>
          <p class="text-2xl font-bold text-emerald-600">{{ fmt(o.businesses) }}</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">दर्ता</p>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">ज्येष्ठ नागरिक (६०+)</p>
          <p class="text-2xl font-bold text-orange-500">{{ fmt(o.elderly) }}</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">व्यक्ति</p>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">अपाङ्गता भएका</p>
          <p class="text-2xl font-bold text-red-500">{{ fmt(o.disabled) }}</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">व्यक्ति</p>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">विदेशमा</p>
          <p class="text-2xl font-bold text-sky-600">{{ fmt(o.abroad) }}</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">व्यक्ति</p>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1 flex items-center gap-1">
            लिंग अनुपात
            <span title="प्रति १०० महिलामा पुरुषको संख्या। १०० भन्दा बढी = पुरुष बढी; कम = महिला बढी।"
              class="cursor-help text-[#9aa0ac] hover:text-[#206bc4] transition" aria-label="लिंग अनुपात बारे जानकारी">
              <i class="fa-solid fa-circle-info text-[9px]"></i>
            </span>
          </p>
          <p class="text-2xl font-bold text-[#626976]">{{ o.sex_ratio }}</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">प्रति १०० महिला पुरुष</p>
        </div>
      </div>
    </div>

    <!-- ── 2. Age Groups + Ethnicity ──────────────────────────────────────── -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Age groups -->
      <div class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
        <div class="px-4 py-3 border-b border-[#e6e7e9]">
          <p class="text-[13px] font-semibold text-[#1d273b]">उमेर समूह</p>
          <p class="text-[11px] text-[#9aa0ac]">Age Group Distribution</p>
        </div>
        <table class="w-full">
          <thead class="bg-gray-50 border-b border-[#e6e7e9]">
            <tr>
              <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">उमेर</th>
              <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">संख्या</th>
              <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">%</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="grp in AGE_ORDER" :key="grp" class="border-b border-[#e6e7e9] last:border-0">
              <td class="px-4 py-2 text-[13px] text-[#1d273b]">{{ grp }}</td>
              <td class="px-4 py-2 text-right text-[13px] tabular-nums text-[#1d273b]">{{ fmt(props.ageGroups[grp] ?? 0) }}</td>
              <td class="px-4 py-2 text-right text-[12px] tabular-nums text-[#626976]">{{ popPct(props.ageGroups[grp] ?? 0) }}%</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Ethnicity -->
      <div class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
        <div class="px-4 py-3 border-b border-[#e6e7e9]">
          <p class="text-[13px] font-semibold text-[#1d273b]">जातीय विवरण</p>
          <p class="text-[11px] text-[#9aa0ac]">Ethnicity Breakdown</p>
        </div>
        <table class="w-full">
          <thead class="bg-gray-50 border-b border-[#e6e7e9]">
            <tr>
              <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">जाति</th>
              <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">संख्या</th>
              <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">%</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in props.ethnicityData.slice(0,10)" :key="r.val" class="border-b border-[#e6e7e9] last:border-0">
              <td class="px-4 py-2 text-[12px] text-[#1d273b]">{{ r.label ?? r.val }}</td>
              <td class="px-4 py-2 text-right text-[12px] tabular-nums text-[#1d273b]">{{ fmt(r.cnt) }}</td>
              <td class="px-4 py-2 text-right text-[11px] tabular-nums text-[#626976]">{{ popPct(r.cnt) }}%</td>
            </tr>
            <tr v-if="!props.ethnicityData.length">
              <td colspan="3" class="px-4 py-3 text-center text-[12px] text-[#9aa0ac]">डेटा उपलब्ध छैन</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── 3. Religion + Mother tongue ────────────────────────────────────── -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
        <div class="px-4 py-3 border-b border-[#e6e7e9]">
          <p class="text-[13px] font-semibold text-[#1d273b]">धर्म अनुसार</p>
          <p class="text-[11px] text-[#9aa0ac]">Religion Distribution</p>
        </div>
        <table class="w-full">
          <thead class="bg-gray-50 border-b border-[#e6e7e9]">
            <tr>
              <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">धर्म</th>
              <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">संख्या</th>
              <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">%</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in props.religionData" :key="r.val" class="border-b border-[#e6e7e9] last:border-0">
              <td class="px-4 py-2 text-[12px] text-[#1d273b]">{{ r.label ?? r.val }}</td>
              <td class="px-4 py-2 text-right text-[12px] tabular-nums text-[#1d273b]">{{ fmt(r.cnt) }}</td>
              <td class="px-4 py-2 text-right text-[11px] tabular-nums text-[#626976]">{{ popPct(r.cnt) }}%</td>
            </tr>
            <tr v-if="!props.religionData.length">
              <td colspan="3" class="px-4 py-3 text-center text-[12px] text-[#9aa0ac]">डेटा उपलब्ध छैन</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
        <div class="px-4 py-3 border-b border-[#e6e7e9]">
          <p class="text-[13px] font-semibold text-[#1d273b]">मातृभाषा (शीर्ष १०)</p>
          <p class="text-[11px] text-[#9aa0ac]">Mother Tongue</p>
        </div>
        <table class="w-full">
          <thead class="bg-gray-50 border-b border-[#e6e7e9]">
            <tr>
              <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">भाषा</th>
              <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">संख्या</th>
              <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">%</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in props.motherTongue" :key="r.val" class="border-b border-[#e6e7e9] last:border-0">
              <td class="px-4 py-2 text-[12px] text-[#1d273b]">{{ r.label ?? r.val }}</td>
              <td class="px-4 py-2 text-right text-[12px] tabular-nums text-[#1d273b]">{{ fmt(r.cnt) }}</td>
              <td class="px-4 py-2 text-right text-[11px] tabular-nums text-[#626976]">{{ popPct(r.cnt) }}%</td>
            </tr>
            <tr v-if="!props.motherTongue.length">
              <td colspan="3" class="px-4 py-3 text-center text-[12px] text-[#9aa0ac]">डेटा उपलब्ध छैन</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── 4. Housing ─────────────────────────────────────────────────────── -->
    <div>
      <div class="flex items-center gap-2.5 border-b border-[#e6e7e9] pb-2">
        <div class="w-7 h-7 rounded bg-amber-500/10 flex items-center justify-center shrink-0">
          <i class="fa-solid fa-house text-amber-600 text-[12px]"></i>
        </div>
        <div>
          <p class="text-[14px] font-bold text-[#1d273b]">आवास विवरण</p>
          <p class="text-[11px] text-[#9aa0ac]">Housing Details</p>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
        <FreqSmallTable title="घरको स्वामित्व" sub="Ownership Type" :rows="hs.ownership" :total="hs.total_hh" />
        <FreqSmallTable title="छतको प्रकार" sub="Roof Type" :rows="hs.roof" :total="hs.total_hh" />
        <FreqSmallTable title="भित्ताको प्रकार" sub="Wall Type" :rows="hs.wall" :total="hs.total_hh" />
        <FreqSmallTable title="जगको प्रकार" sub="Foundation Type" :rows="hs.foundation" :total="hs.total_hh" />
      </div>
      <div class="mt-3 bg-white border border-[#e6e7e9] rounded-md p-4 flex items-center gap-4">
        <div class="w-14 h-14 rounded-full flex items-center justify-center shrink-0"
          :class="hs.permit_pct >= 50 ? 'bg-green-100' : 'bg-amber-100'">
          <span class="text-xl font-bold" :class="hs.permit_pct >= 50 ? 'text-green-600' : 'text-amber-600'">{{ hs.permit_pct }}%</span>
        </div>
        <div>
          <p class="text-[13px] font-semibold text-[#1d273b]">{{ fmt(hs.permit_count) }} घरधुरीसँग निर्माण अनुमति छ</p>
          <p class="text-[12px] text-[#9aa0ac]">{{ fmt(hs.total_hh) }} मध्ये {{ hs.permit_pct }}% — Building permit coverage</p>
        </div>
      </div>
    </div>

    <!-- ── 5. Utilities ───────────────────────────────────────────────────── -->
    <div>
      <div class="flex items-center gap-2.5 border-b border-[#e6e7e9] pb-2">
        <div class="w-7 h-7 rounded bg-blue-500/10 flex items-center justify-center shrink-0">
          <i class="fa-solid fa-bolt text-blue-600 text-[12px]"></i>
        </div>
        <div>
          <p class="text-[14px] font-bold text-[#1d273b]">आधारभूत सेवाहरू</p>
          <p class="text-[11px] text-[#9aa0ac]">Basic Utilities & Services</p>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
        <FreqSmallTable title="खानेपानीको स्रोत" sub="Water Source" :rows="u.water" :total="o.households" />
        <FreqSmallTable title="विद्युत आपूर्ति" sub="Electricity Source" :rows="u.electricity" :total="o.households" />
        <FreqSmallTable title="खाना पकाउने इन्धन" sub="Cooking Fuel" :rows="u.cooking" :total="o.households" />
        <FreqSmallTable title="शौचालयको प्रकार" sub="Toilet Type" :rows="u.toilet" :total="o.households" />
        <FreqSmallTable title="फोहर व्यवस्थापन" sub="Solid Waste Management" :rows="u.waste" :total="o.households" />
        <FreqSmallTable title="इन्टरनेट सेवा" sub="Internet Type" :rows="u.internet" :total="o.households" />
      </div>
      <div class="mt-3 bg-white border border-[#e6e7e9] rounded-md p-4 flex items-center gap-4">
        <div class="w-14 h-14 rounded-full flex items-center justify-center shrink-0"
          :class="u.handwash_pct >= 50 ? 'bg-green-100' : 'bg-amber-100'">
          <span class="text-xl font-bold" :class="u.handwash_pct >= 50 ? 'text-green-600' : 'text-amber-600'">{{ u.handwash_pct }}%</span>
        </div>
        <div>
          <p class="text-[13px] font-semibold text-[#1d273b]">हातधुने सुविधा उपलब्ध</p>
          <p class="text-[12px] text-[#9aa0ac]">{{ u.handwash_pct }}% घरधुरीमा — Handwashing facility availability</p>
        </div>
      </div>
    </div>

    <!-- ── 6. Education & Occupation ─────────────────────────────────────── -->
    <div>
      <div class="flex items-center gap-2.5 border-b border-[#e6e7e9] pb-2">
        <div class="w-7 h-7 rounded bg-indigo-500/10 flex items-center justify-center shrink-0">
          <i class="fa-solid fa-graduation-cap text-indigo-600 text-[12px]"></i>
        </div>
        <div>
          <p class="text-[14px] font-bold text-[#1d273b]">शिक्षा र पेशा</p>
          <p class="text-[11px] text-[#9aa0ac]">Education & Occupation</p>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
        <FreqSmallTable title="शिक्षाको स्तर" sub="Education Level" :rows="ed.edu_level" :total="o.population" />
        <FreqSmallTable title="पेशा" sub="Occupation Type" :rows="ed.occupation" :total="o.population" />
      </div>
      <div class="mt-3 bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
        <div class="px-4 py-3 border-b border-[#e6e7e9]">
          <p class="text-[13px] font-semibold text-[#1d273b]">लिंग अनुसार साक्षरता</p>
          <p class="text-[11px] text-[#9aa0ac]">Literacy by Gender</p>
        </div>
        <div class="p-4 grid grid-cols-3 gap-4">
          <div v-for="r in ed.lit_by_gender" :key="r.gender" class="text-center">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1 capitalize">{{ r.gender }}</p>
            <p class="text-2xl font-bold" :class="litColor(r.tot > 0 ? Math.round(r.lit/r.tot*100) : 0)">
              {{ r.tot > 0 ? Math.round(r.lit / r.tot * 100) : 0 }}%
            </p>
            <p class="text-[11px] text-[#9aa0ac] mt-0.5">{{ fmt(r.lit) }} / {{ fmt(r.tot) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ── 7. Economic ────────────────────────────────────────────────────── -->
    <div>
      <div class="flex items-center gap-2.5 border-b border-[#e6e7e9] pb-2">
        <div class="w-7 h-7 rounded bg-yellow-500/10 flex items-center justify-center shrink-0">
          <i class="fa-solid fa-coins text-yellow-600 text-[12px]"></i>
        </div>
        <div>
          <p class="text-[14px] font-bold text-[#1d273b]">आर्थिक विवरण</p>
          <p class="text-[11px] text-[#9aa0ac]">Economic Profile</p>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
        <FreqSmallTable title="मुख्य आय स्रोत" sub="Primary Income Source" :rows="eco.income_source" :total="eco.total_hh" />
        <FreqSmallTable title="वार्षिक आम्दानी" sub="Annual Income Range" :rows="incomeRangeRows" :total="eco.total_hh" />
      </div>
      <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3">
        <!-- Bank account -->
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <div class="flex items-start justify-between gap-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">बैंक खाता</p>
            <div class="w-7 h-7 rounded-full bg-blue-50 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-building-columns text-blue-500 text-[11px]"></i>
            </div>
          </div>
          <p class="text-2xl font-bold text-[#1d273b] mt-1">{{ eco.bank_pct }}%</p>
          <div class="mt-2 h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-blue-400 rounded-full" :style="`width:${Math.min(eco.bank_pct,100)}%`"></div>
          </div>
        </div>
        <!-- Insurance -->
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <div class="flex items-start justify-between gap-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]">बीमा</p>
            <div class="w-7 h-7 rounded-full bg-green-50 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-shield-halved text-green-500 text-[11px]"></i>
            </div>
          </div>
          <p class="text-2xl font-bold text-[#1d273b] mt-1">{{ eco.insurance_pct }}%</p>
          <div class="mt-2 h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-green-400 rounded-full" :style="`width:${Math.min(eco.insurance_pct,100)}%`"></div>
          </div>
        </div>
        <!-- Poverty card -->
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">गरिबी परिचयपत्र</p>
          <p class="text-2xl font-bold text-amber-600">{{ fmt(eco.poverty_count) }}</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">घरधुरी</p>
        </div>
        <!-- Social security -->
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">सामाजिक सुरक्षा</p>
          <p class="text-2xl font-bold text-violet-600">{{ fmt(eco.ssa_count) }}</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">भत्ता प्राप्तकर्ता</p>
        </div>
      </div>
    </div>

    <!-- ── 8. Agriculture ─────────────────────────────────────────────────── -->
    <div>
      <div class="flex items-center gap-2.5 border-b border-[#e6e7e9] pb-2">
        <div class="w-7 h-7 rounded bg-green-500/10 flex items-center justify-center shrink-0">
          <i class="fa-solid fa-seedling text-green-600 text-[12px]"></i>
        </div>
        <div>
          <p class="text-[14px] font-bold text-[#1d273b]">कृषि विवरण</p>
          <p class="text-[11px] text-[#9aa0ac]">Agriculture Profile</p>
        </div>
      </div>
      <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div v-for="item in agriCards" :key="item.label" class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <div class="flex items-start justify-between gap-1 mb-1">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] leading-tight">{{ item.label }}</p>
          </div>
          <p class="text-2xl font-bold text-[#1d273b]">{{ item.pct }}%</p>
          <div class="mt-2 h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full" :class="item.bar" :style="`width:${Math.min(item.pct,100)}%`"></div>
          </div>
          <p class="text-[10px] text-[#9aa0ac] mt-1">{{ item.sub }}</p>
        </div>
      </div>
    </div>

    <!-- ── 9. Migration ───────────────────────────────────────────────────── -->
    <div>
      <div class="flex items-center gap-2.5 border-b border-[#e6e7e9] pb-2">
        <div class="w-7 h-7 rounded bg-sky-500/10 flex items-center justify-center shrink-0">
          <i class="fa-solid fa-plane text-sky-600 text-[12px]"></i>
        </div>
        <div>
          <p class="text-[14px] font-bold text-[#1d273b]">बसाइसराइ</p>
          <p class="text-[11px] text-[#9aa0ac]">Migration Profile</p>
        </div>
      </div>
      <div class="mt-3 grid grid-cols-2 gap-3">
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">विदेशमा</p>
          <p class="text-2xl font-bold text-sky-600">{{ fmt(mig.abroad) }}</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">व्यक्ति</p>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">आन्तरिक बसाइसराइ</p>
          <p class="text-2xl font-bold text-orange-500">{{ fmt(mig.internal) }}</p>
          <p class="text-[10px] text-[#9aa0ac] mt-0.5">व्यक्ति</p>
        </div>
      </div>
      <div v-if="mig.dest_countries && mig.dest_countries.length" class="mt-3 bg-white border border-[#e6e7e9] rounded-md overflow-hidden">
        <div class="px-4 py-3 border-b border-[#e6e7e9]">
          <p class="text-[13px] font-semibold text-[#1d273b]">गन्तव्य देशहरू</p>
          <p class="text-[11px] text-[#9aa0ac]">Destination Countries</p>
        </div>
        <div class="p-4 flex flex-wrap gap-2">
          <div v-for="r in mig.dest_countries" :key="r.country"
            class="flex items-center gap-1.5 px-3 py-1.5 bg-sky-50 border border-sky-200 rounded-full text-[12px]">
            <span class="font-medium text-sky-700">{{ r.country }}</span>
            <span class="text-sky-500 tabular-nums">{{ fmt(r.cnt) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── 10. Health & Disability ───────────────────────────────────────── -->
    <div>
      <div class="flex items-center gap-2.5 border-b border-[#e6e7e9] pb-2">
        <div class="w-7 h-7 rounded bg-red-500/10 flex items-center justify-center shrink-0">
          <i class="fa-solid fa-heart-pulse text-red-500 text-[12px]"></i>
        </div>
        <div>
          <p class="text-[14px] font-bold text-[#1d273b]">स्वास्थ्य र अपाङ्गता</p>
          <p class="text-[11px] text-[#9aa0ac]">Health & Disability</p>
        </div>
      </div>
      <div class="mt-3 grid grid-cols-3 gap-3">
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">अपाङ्गता भएका</p>
          <p class="text-2xl font-bold text-red-500">{{ fmt(hlt.disabled_count) }}</p>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">दीर्घरोग भएका</p>
          <p class="text-2xl font-bold text-orange-500">{{ fmt(hlt.chronic_count) }}</p>
        </div>
        <div class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac] mb-1">ज्येष्ठ नागरिक (६०+)</p>
          <p class="text-2xl font-bold text-amber-600">{{ fmt(hlt.elderly_count) }}</p>
        </div>
      </div>
      <div v-if="hlt.disability_types && hlt.disability_types.length" class="mt-3">
        <FreqSmallTable title="अपाङ्गताको प्रकार" sub="Disability Type Breakdown" :rows="hlt.disability_types" :total="hlt.disabled_count" />
      </div>
    </div>

    <!-- ── 11. Disaster ───────────────────────────────────────────────────── -->
    <div>
      <div class="flex items-center gap-2.5 border-b border-[#e6e7e9] pb-2">
        <div class="w-7 h-7 rounded bg-orange-500/10 flex items-center justify-center shrink-0">
          <i class="fa-solid fa-triangle-exclamation text-orange-500 text-[12px]"></i>
        </div>
        <div>
          <p class="text-[14px] font-bold text-[#1d273b]">प्राकृतिक प्रकोप जोखिम</p>
          <p class="text-[11px] text-[#9aa0ac]">Disaster Risk Profile</p>
        </div>
      </div>
      <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div v-for="item in disasterCards" :key="item.label" class="bg-white border border-[#e6e7e9] rounded-md p-4">
          <div class="flex items-center gap-2 mb-2">
            <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0" :class="item.bg">
              <i :class="['fa-solid', item.icon, 'text-[11px]', item.iconColor]"></i>
            </div>
            <p class="text-[11px] font-semibold text-[#9aa0ac]">{{ item.label }}</p>
          </div>
          <p class="text-xl font-bold text-[#1d273b]">{{ fmt(item.count) }} घरधुरी</p>
          <div class="mt-2 h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full" :class="item.bar"
              :style="`width:${dis.total_hh > 0 ? Math.min(Math.round(item.count/dis.total_hh*100),100) : 0}%`"></div>
          </div>
          <p class="text-[10px] text-[#9aa0ac] mt-1">
            {{ dis.total_hh > 0 ? Math.round(item.count / dis.total_hh * 100) : 0 }}% — {{ fmt(dis.total_hh) }} मध्ये
          </p>
        </div>
      </div>
    </div>

    <!-- Print footer -->
    <div class="print-only hidden text-center text-[10px] text-gray-400 pt-4 border-t border-gray-200">
      वडा {{ props.ward.ward_number }} — {{ props.lg?.name_np }} को डेटा प्रणालीबाट स्वचालित रूपमा तयार गरिएको प्रतिवेदन।
    </div>

  </div>
</template>

<script setup>
import { computed, defineComponent, h } from 'vue'
import { Head } from '@inertiajs/vue3'
import PublicLayout from '@/layouts/PublicLayout.vue'

defineOptions({ layout: PublicLayout })

// ── Inline reusable component: frequency table ─────────────────────────────
const FreqSmallTable = defineComponent({
  name: 'FreqSmallTable',
  props: {
    title: String,
    sub: String,
    rows: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
  },
  setup(props) {
    return () => h('div', { class: 'bg-white border border-[#e6e7e9] rounded-md overflow-hidden' }, [
      h('div', { class: 'px-4 py-3 border-b border-[#e6e7e9]' }, [
        h('p', { class: 'text-[13px] font-semibold text-[#1d273b]' }, props.title),
        h('p', { class: 'text-[11px] text-[#9aa0ac]' }, props.sub),
      ]),
      h('table', { class: 'w-full' }, [
        h('thead', { class: 'bg-gray-50 border-b border-[#e6e7e9]' }, [
          h('tr', [
            h('th', { class: 'px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]' }, 'विवरण'),
            h('th', { class: 'px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]' }, 'संख्या'),
            h('th', { class: 'px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-[#9aa0ac]' }, '%'),
          ]),
        ]),
        h('tbody', props.rows && props.rows.length
          ? props.rows.map(r => h('tr', { key: r.val, class: 'border-b border-[#e6e7e9] last:border-0' }, [
              h('td', { class: 'px-4 py-2 text-[12px] text-[#1d273b]' }, String(r.label ?? r.val ?? '')),
              h('td', { class: 'px-4 py-2 text-right text-[12px] tabular-nums text-[#1d273b]' }, Number(r.cnt ?? 0).toLocaleString()),
              h('td', { class: 'px-4 py-2 text-right text-[11px] tabular-nums text-[#626976]' },
                props.total > 0 ? `${Math.round(r.cnt / props.total * 100)}%` : '0%'),
            ]))
          : [h('tr', [h('td', { colspan: 3, class: 'px-4 py-3 text-center text-[12px] text-[#9aa0ac]' }, 'डेटा उपलब्ध छैन')])]
        ),
      ]),
    ])
  },
})

const props = defineProps({
  lg:           { type: Object, default: null },
  ward:         { type: Object, required: true },
  allWards:     { type: Array,  default: () => [] },
  overview:     { type: Object, default: () => ({}) },
  ageGroups:    { type: Object, default: () => ({}) },
  ethnicityData:{ type: Array,  default: () => [] },
  religionData: { type: Array,  default: () => [] },
  motherTongue: { type: Array,  default: () => [] },
  housing:      { type: Object, default: () => ({}) },
  utilities:    { type: Object, default: () => ({}) },
  education:    { type: Object, default: () => ({}) },
  economic:     { type: Object, default: () => ({}) },
  agriculture:  { type: Object, default: () => ({}) },
  migration:    { type: Object, default: () => ({}) },
  health:       { type: Object, default: () => ({}) },
  disaster:     { type: Object, default: () => ({}) },
})

const o   = computed(() => props.overview)
const hs  = computed(() => props.housing)
const u   = computed(() => props.utilities)
const ed  = computed(() => props.education)
const eco = computed(() => props.economic)
const agr = computed(() => props.agriculture)
const mig = computed(() => props.migration)
const hlt = computed(() => props.health)
const dis = computed(() => props.disaster)

const fmt = n => Number(n ?? 0).toLocaleString()
const printDate = new Date().toLocaleDateString('ne-NP', { year: 'numeric', month: 'long', day: 'numeric' })
const litColor = pct => {
  if (pct >= 80) return 'text-green-600'
  if (pct >= 60) return 'text-teal-500'
  if (pct >= 40) return 'text-amber-500'
  return 'text-red-500'
}
const popPct = cnt => o.value.population > 0 ? Math.round(cnt / o.value.population * 100) : 0

const AGE_ORDER = ['0-4','5-14','15-24','25-39','40-59','60+']

const INCOME_LABELS = {
  'below_50k':  'रु. ५०,००० भन्दा कम',
  '50k_100k':   'रु. ५०,०००–१,००,०००',
  '100k_200k':  'रु. १,००,०००–२,००,०००',
  '200k_500k':  'रु. २,००,०००–५,००,०००',
  '500k_1m':    'रु. ५,००,०००–१०,००,०००',
  'above_1m':   'रु. १०,००,००० भन्दा बढी',
}
const incomeRangeRows = computed(() =>
  (eco.value.income_range ?? []).map(r => ({ val: INCOME_LABELS[r.val] ?? r.val, cnt: r.cnt }))
)

const agriCards = computed(() => [
  { label: 'जग्गा स्वामित्व', pct: agr.value.owns_land_pct ?? 0, bar: 'bg-green-400', sub: `${fmt(agr.value.owns_count)} / ${fmt(agr.value.total_hh)} घरधुरी` },
  { label: 'रासायनिक मल', pct: agr.value.chem_fert_pct ?? 0, bar: 'bg-orange-400', sub: 'जग्गावाला मध्ये' },
  { label: 'जैविक मल', pct: agr.value.org_fert_pct ?? 0, bar: 'bg-lime-400', sub: 'जग्गावाला मध्ये' },
  { label: 'उत्पादन बिक्री', pct: agr.value.sells_pct ?? 0, bar: 'bg-blue-400', sub: 'जग्गावाला मध्ये' },
  { label: 'कृषि ऋण', pct: agr.value.agri_loan_pct ?? 0, bar: 'bg-red-400', sub: 'जग्गावाला मध्ये' },
])

const disasterCards = computed(() => [
  { label: 'बाढी जोखिम', count: dis.value.flood_count ?? 0, icon: 'fa-water', bg: 'bg-blue-100', iconColor: 'text-blue-500', bar: 'bg-blue-400' },
  { label: 'पहिरो जोखिम', count: dis.value.landslide_count ?? 0, icon: 'fa-hill-rockslide', bg: 'bg-amber-100', iconColor: 'text-amber-500', bar: 'bg-amber-400' },
  { label: 'खडेरी जोखिम', count: dis.value.drought_count ?? 0, icon: 'fa-sun', bg: 'bg-orange-100', iconColor: 'text-orange-500', bar: 'bg-orange-400' },
  { label: 'निकासी योजना', count: dis.value.evac_count ?? 0, icon: 'fa-person-running', bg: 'bg-green-100', iconColor: 'text-green-500', bar: 'bg-green-400' },
  { label: 'विपद् तालिम', count: dis.value.training_count ?? 0, icon: 'fa-chalkboard-user', bg: 'bg-violet-100', iconColor: 'text-violet-500', bar: 'bg-violet-400' },
])

function printPage() {
  window.print()
}

function goToWard(e) {
  window.location.href = `/public/reports/ward/${e.target.value}`
}
</script>

<style scoped>
@media print {
  @page { size: A4 portrait; margin: 10mm 12mm; }

  :deep(html), :deep(body), :deep(#app), :deep([data-page]) {
    overflow: visible !important;
    height: auto !important;
  }

  * { print-color-adjust: exact !important; -webkit-print-color-adjust: exact !important; }

  .no-print   { display: none !important; }
  .print-only { display: block !important; }

  .max-w-screen-lg { max-width: 100% !important; padding: 0 !important; }

  .grid > div, table { break-inside: avoid; }
}
</style>
