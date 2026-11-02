<template>
  <div class="max-w-3xl mx-auto space-y-3">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h1 class="text-[15px] font-semibold text-[#1d273b] flex items-center gap-2">
          <i class="fa-solid fa-hand-holding-heart text-emerald-600"></i> श्रमदान पासबुक
        </h1>
        <p class="text-[11px] text-[#9aa0ac] mt-0.5">
          {{ isCoordinator ? 'तपाईंलाई तोकिएका टोलहरू' : 'तपाईंको क्षेत्रका टोलहरू' }} · आ.व. {{ fiscalYear }} · प्रकाशित घरधुरी मात्र
        </p>
      </div>
      <div v-if="canManage" class="flex gap-2 shrink-0">
        <Link href="/shramdaan/projects" class="h-9 px-3 inline-flex items-center gap-1.5 border border-[#dadcde] bg-white rounded-md text-[12px] text-[#1d273b]">
          <i class="fa-solid fa-diagram-project text-emerald-600"></i> योजना
        </Link>
        <Link href="/shramdaan/coordinators" class="h-9 px-3 inline-flex items-center gap-1.5 border border-[#dadcde] bg-white rounded-md text-[12px] text-[#1d273b]">
          <i class="fa-solid fa-people-roof text-emerald-600"></i> संयोजक
        </Link>
      </div>
    </div>

    <div v-if="!toles.length" class="text-center py-12 bg-white border border-[#e6e7e9] rounded-md text-[#9aa0ac]">
      <i class="fa-solid fa-people-roof text-3xl mb-2"></i>
      <p class="text-[13px]">तपाईंलाई अहिलेसम्म कुनै टोल तोकिएको छैन।</p>
      <p class="text-[11px] mt-1">वडा कार्यालय वा पालिकाको प्रशासकसँग सम्पर्क गर्नुहोस्।</p>
    </div>

    <template v-for="(tole, i) in toles" :key="tole.id">
      <p v-if="i === 0 || toles[i - 1].ward_number !== tole.ward_number"
        class="text-[11px] font-semibold text-[#626976] uppercase tracking-wide px-1 pt-2">
        वडा {{ tole.ward_number }}
      </p>
      <Link :href="`/shramdaan/toles/${tole.id}`"
        class="bg-white border border-[#e6e7e9] rounded-md p-3 flex items-center gap-3 no-underline active:bg-gray-50">
        <div class="w-10 h-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
          <i class="fa-solid fa-people-roof"></i>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-[14px] font-semibold text-[#1d273b] truncate">{{ tole.name }}</p>
          <p class="text-[12px] text-[#626976]">
            {{ tole.households }} घरधुरी · {{ tole.passbooks }} पासबुक खोलिएको
          </p>
        </div>
        <i class="fa-solid fa-chevron-right text-[#9aa0ac] text-xs"></i>
      </Link>
    </template>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  toles:         { type: Array,   default: () => [] },
  isCoordinator: { type: Boolean, default: false },
  fiscalYear:    { type: String,  default: '' },
  canManage:     { type: Boolean, default: false },
})
</script>
