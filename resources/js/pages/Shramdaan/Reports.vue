<template>
  <div class="max-w-4xl mx-auto space-y-3 pb-8">
    <div>
      <Link href="/shramdaan" class="text-[12px] text-[#206bc4] no-underline"><i class="fa-solid fa-chevron-left text-[10px]"></i> श्रमदान</Link>
      <div class="flex flex-wrap items-center gap-2 mt-1">
        <h1 class="text-[15px] font-semibold text-[#1d273b] flex-1">श्रमदान रिपोर्ट</h1>
        <select :value="fiscalYear" @change="router.get('/shramdaan/reports', { fy: $event.target.value })"
          class="h-9 px-2 border border-[#dadcde] rounded-md text-[13px] bg-white">
          <option v-for="y in fiscalYears" :key="y" :value="y">आ.व. {{ y }}</option>
        </select>
        <a :href="`/shramdaan/reports/export?fy=${encodeURIComponent(fiscalYear)}`"
          class="h-9 px-3 inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-[12px] font-semibold no-underline">
          <i class="fa-solid fa-file-excel"></i> Excel
        </a>
      </div>
      <p class="text-[11px] text-[#9aa0ac]">श्रेणी र दिन प्रमाणित श्रमदानबाट मात्र · प्रकाशित घरधुरी मात्र</p>
    </div>

    <!-- Totals -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
      <div v-for="c in cards" :key="c.label" class="bg-white border border-[#e6e7e9] rounded-md p-3">
        <p class="text-[20px] font-bold leading-tight" :class="c.color">{{ c.value }}</p>
        <p class="text-[11px] text-[#626976]">{{ c.label }}</p>
      </div>
    </div>
    <div class="bg-white border border-[#e6e7e9] rounded-md p-3 flex flex-wrap gap-4 text-[13px]">
      <span>🥇 स्वर्ण <b>{{ total.gold }}</b></span>
      <span>🥈 रजत <b>{{ total.silver }}</b></span>
      <span>🥉 कांस्य <b>{{ total.bronze }}</b></span>
      <span class="text-[#9aa0ac]">परिवार</span>
    </div>

    <!-- Per tole -->
    <div class="bg-white border border-[#e6e7e9] rounded-md overflow-x-auto">
      <table class="w-full text-[12px] min-w-[620px]">
        <thead class="bg-gray-50 text-[#626976]">
          <tr>
            <th class="text-left p-2">टोल</th>
            <th class="p-2">घरधुरी</th><th class="p-2">पासबुक</th><th class="p-2">सक्रिय परिवार</th>
            <th class="p-2">प्रमाणित दिन</th><th class="p-2">बाँकी दिन</th>
            <th class="p-2">🥇</th><th class="p-2">🥈</th><th class="p-2">🥉</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(r, i) in rows" :key="r.tole_id">
            <tr v-if="i === 0 || rows[i - 1].ward_number !== r.ward_number" class="bg-emerald-50/60">
              <td colspan="9" class="px-2 py-1 font-semibold text-emerald-800">वडा {{ r.ward_number }}</td>
            </tr>
            <tr class="border-t border-[#f0f1f3] text-center">
              <td class="text-left p-2">{{ r.tole }}</td>
              <td class="p-2">{{ r.households }}</td><td class="p-2">{{ r.passbooks }}</td><td class="p-2">{{ r.active }}</td>
              <td class="p-2 font-semibold text-emerald-700">{{ r.verified }}</td>
              <td class="p-2 text-amber-600">{{ r.pending || '' }}</td>
              <td class="p-2">{{ r.gold || '' }}</td><td class="p-2">{{ r.silver || '' }}</td><td class="p-2">{{ r.bronze || '' }}</td>
            </tr>
          </template>
          <tr v-if="!rows.length"><td colspan="9" class="p-6 text-center text-[#9aa0ac]">कुनै टोल छैन।</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Top families -->
    <div v-if="top.length" class="bg-white border border-[#e6e7e9] rounded-md p-3">
      <p class="text-[13px] font-semibold text-[#1d273b] mb-2"><i class="fa-solid fa-trophy text-amber-500"></i> सबैभन्दा बढी प्रमाणित श्रमदान</p>
      <Link v-for="(f, i) in top" :key="f.household_id" :href="`/shramdaan/households/${f.household_id}`"
        class="flex items-center gap-3 py-2 border-t border-[#f0f1f3] first:border-0 no-underline">
        <span class="w-6 text-center text-[12px] text-[#9aa0ac]">{{ i + 1 }}</span>
        <span class="flex-1 min-w-0">
          <span class="block text-[13px] text-[#1d273b] truncate">{{ f.family }}</span>
          <span class="block text-[11px] text-[#9aa0ac]">वडा {{ f.ward_number }} · {{ f.tole }} · {{ f.unique_code }}</span>
        </span>
        <span class="text-[14px] font-bold text-emerald-700">{{ f.days }} दिन</span>
      </Link>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  rows:        { type: Array,  default: () => [] },
  top:         { type: Array,  default: () => [] },
  fiscalYear:  { type: String, required: true },
  fiscalYears: { type: Array,  default: () => [] },
})

const sum = (k) => Math.round(props.rows.reduce((s, r) => s + (Number(r[k]) || 0), 0) * 100) / 100
const total = computed(() => ({
  households: sum('households'), passbooks: sum('passbooks'), active: sum('active'),
  verified: sum('verified'), pending: sum('pending'), gold: sum('gold'), silver: sum('silver'), bronze: sum('bronze'),
}))
const cards = computed(() => [
  { label: 'पासबुक खोलिएका परिवार', value: total.value.passbooks, color: 'text-[#1d273b]' },
  { label: 'प्रमाणित श्रमदान भएका परिवार', value: total.value.active, color: 'text-[#1d273b]' },
  { label: 'प्रमाणित दिन', value: total.value.verified, color: 'text-emerald-700' },
  { label: 'प्रमाणित हुन बाँकी दिन', value: total.value.pending, color: 'text-amber-600' },
])
</script>
