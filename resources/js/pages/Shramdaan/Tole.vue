<template>
  <div class="max-w-3xl mx-auto space-y-3">
    <div>
      <Link href="/shramdaan" class="text-[12px] text-[#206bc4] no-underline">
        <i class="fa-solid fa-chevron-left text-[10px]"></i> सबै टोल
      </Link>
      <h1 class="text-[15px] font-semibold text-[#1d273b] mt-1">
        {{ tole.name }} <span class="text-[12px] font-normal text-[#626976]">· वडा {{ tole.ward_number }}</span>
      </h1>
      <p class="text-[11px] text-[#9aa0ac]">{{ households.meta.total }} प्रकाशित घरधुरी · आ.व. {{ fiscalYear }}</p>
    </div>

    <form @submit.prevent="search" class="relative">
      <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[#9aa0ac] text-sm"></i>
      <input v-model="q" type="search" enterkeyhint="search" placeholder="घर नं., घरमुली वा सदस्यको नाम"
        class="w-full h-11 pl-10 pr-3 border border-[#dadcde] rounded-md text-[14px] bg-white focus:outline-none focus:border-[#206bc4]" />
    </form>

    <div v-if="!households.data.length" class="text-center py-10 bg-white border border-[#e6e7e9] rounded-md text-[#9aa0ac] text-[13px]">
      कुनै प्रकाशित घरधुरी भेटिएन।
    </div>

    <Link v-for="hh in households.data" :key="hh.id" :href="`/shramdaan/households/${hh.id}`"
      class="bg-white border border-[#e6e7e9] rounded-md p-3 flex items-center gap-3 no-underline active:bg-gray-50">
      <div class="flex-1 min-w-0">
        <p class="text-[14px] font-semibold text-[#1d273b] truncate">{{ hh.head_name || '—' }}</p>
        <p class="text-[12px] text-[#626976] font-mono">{{ hh.unique_code }}</p>
        <p class="text-[11px] text-[#9aa0ac]">{{ hh.members }} सदस्य</p>
      </div>
      <div class="text-right shrink-0">
        <template v-if="hh.has_passbook">
          <p class="text-[15px] font-bold text-emerald-700 leading-none">{{ hh.verified_days }}</p>
          <p class="text-[10px] text-[#9aa0ac]">प्रमाणित दिन</p>
          <p v-if="hh.pending_days" class="text-[10px] text-amber-600 mt-0.5">+{{ hh.pending_days }} बाँकी</p>
        </template>
        <span v-else class="text-[11px] px-2 py-0.5 rounded bg-gray-100 text-gray-500">पासबुक छैन</span>
      </div>
      <i class="fa-solid fa-chevron-right text-[#9aa0ac] text-xs"></i>
    </Link>

    <div v-if="households.meta.last_page > 1" class="flex gap-2 pb-6">
      <Link :href="households.links.prev || '#'" :class="pager(!households.links.prev)">
        <i class="fa-solid fa-chevron-left mr-1"></i> अघिल्लो
      </Link>
      <Link :href="households.links.next || '#'" :class="pager(!households.links.next)">
        अर्को <i class="fa-solid fa-chevron-right ml-1"></i>
      </Link>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  tole:       { type: Object, required: true },
  households: { type: Object, required: true },
  filters:    { type: Object, default: () => ({ q: '' }) },
  fiscalYear: { type: String, default: '' },
})

const q = ref(props.filters.q || '')

function search() {
  router.get(`/shramdaan/toles/${props.tole.id}`, q.value.trim() ? { q: q.value.trim() } : {}, { preserveState: true })
}

function pager(disabled) {
  return ['h-11 flex-1 inline-flex items-center justify-center border border-[#dadcde] bg-white rounded-md text-[13px] no-underline text-[#1d273b]',
          disabled ? 'opacity-40 pointer-events-none' : '']
}
</script>
