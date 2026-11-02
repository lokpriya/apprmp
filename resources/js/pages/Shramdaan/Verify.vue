<template>
  <div class="max-w-3xl mx-auto space-y-3 pb-24">
    <div>
      <Link href="/shramdaan" class="text-[12px] text-[#206bc4] no-underline"><i class="fa-solid fa-chevron-left text-[10px]"></i> श्रमदान</Link>
      <h1 class="text-[15px] font-semibold text-[#1d273b] mt-1">श्रमदान प्रमाणीकरण</h1>
      <p class="text-[11px] text-[#9aa0ac]">
        प्रमाणित भएपछि मात्र दिन गणना हुन्छ र पासबुकमा ✓ प्रमाणित छापिन्छ। आफैंले दर्ता गरेको श्रमदान अर्को व्यक्तिले प्रमाणित गर्नुपर्छ।
      </p>
    </div>

    <!-- Filters -->
    <div class="flex gap-2">
      <select :value="filters.fy" @change="go({ fy: $event.target.value, tole: filters.tole })" :class="select">
        <option v-for="y in fiscalYears" :key="y" :value="y">आ.व. {{ y }}</option>
      </select>
      <select :value="filters.tole ?? ''" @change="go({ fy: filters.fy, tole: $event.target.value || undefined })" :class="select">
        <option value="">सबै टोल</option>
        <option v-for="t in toles" :key="t.id" :value="t.id">वडा {{ t.ward_number }} · {{ t.name }}</option>
      </select>
    </div>

    <div v-if="!entries.data.length" class="text-center py-12 bg-white border border-[#e6e7e9] rounded-md text-[#9aa0ac]">
      <i class="fa-solid fa-circle-check text-3xl text-emerald-500 mb-2"></i>
      <p class="text-[13px]">प्रमाणित गर्न बाँकी कुनै श्रमदान छैन।</p>
    </div>

    <template v-else>
      <label class="flex items-center gap-2 px-1 text-[12px] text-[#626976]">
        <input type="checkbox" class="w-4 h-4" :checked="allSelected" @change="toggleAll" />
        यो पृष्ठका सबै छान्नुहोस् ({{ selectable.length }})
        <span class="ml-auto">जम्मा {{ entries.meta.total }} बाँकी</span>
      </label>

      <template v-for="(e, i) in entries.data" :key="e.id">
        <p v-if="i === 0 || entries.data[i - 1].household_id !== e.household_id"
          class="text-[11px] font-semibold text-[#626976] px-1 pt-2">
          वडा {{ e.ward_number }} · {{ e.tole }} ·
          <Link :href="`/shramdaan/households/${e.household_id}`" class="text-[#206bc4] no-underline">{{ e.family }} ({{ e.unique_code }})</Link>
        </p>
        <label class="bg-white border rounded-md p-3 flex items-start gap-3"
          :class="selected.has(e.id) ? 'border-emerald-400 bg-emerald-50/40' : 'border-[#e6e7e9]'">
          <input type="checkbox" class="w-5 h-5 mt-0.5 shrink-0" :disabled="e.own"
            :checked="selected.has(e.id)" @change="toggle(e)" />
          <div class="flex-1 min-w-0">
            <p class="text-[13px] font-semibold text-[#1d273b]">{{ e.project_name }}</p>
            <p class="text-[12px] text-[#626976]"><span class="font-mono">{{ e.work_date_bs }}</span> · {{ e.member_name }}</p>
            <p v-if="e.work_nature" class="text-[12px] text-[#626976]">{{ e.work_nature }}</p>
            <p class="text-[11px] text-[#9aa0ac]">
              <template v-if="e.supervisor">सुपरिवेक्षक: {{ e.supervisor }} · </template>दर्ता: {{ e.entered_by_name || '—' }}
            </p>
            <p v-if="e.own" class="text-[11px] text-amber-600 mt-0.5">तपाईंले आफैं दर्ता गर्नुभएको — अर्को व्यक्तिले प्रमाणित गर्नुपर्छ।</p>
          </div>
          <div class="text-right shrink-0">
            <p class="text-[15px] font-bold text-[#1d273b] leading-none">{{ e.days }}</p>
            <p class="text-[10px] text-[#9aa0ac]">दिन · {{ e.hours }} घ.</p>
          </div>
        </label>
      </template>

      <div v-if="entries.meta.last_page > 1" class="flex gap-2">
        <Link :href="entries.links.prev || '#'" :class="pager(!entries.links.prev)">अघिल्लो</Link>
        <Link :href="entries.links.next || '#'" :class="pager(!entries.links.next)">अर्को</Link>
      </div>
    </template>

    <!-- Sticky action bar -->
    <div v-if="selected.size" class="fixed bottom-0 inset-x-0 bg-white border-t border-[#e6e7e9] p-3 z-20">
      <div class="max-w-3xl mx-auto flex items-center gap-3">
        <p class="text-[13px] text-[#1d273b] flex-1">{{ selected.size }} छानिएको · {{ selectedDays }} दिन</p>
        <button type="button" @click="verify" :disabled="busy"
          class="h-11 px-5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-md text-[14px] font-semibold">
          <i class="fa-solid fa-check mr-1"></i> प्रमाणित गर्नुहोस्
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  entries:     { type: Object, required: true },
  toles:       { type: Array,  default: () => [] },
  filters:     { type: Object, default: () => ({}) },
  fiscalYears: { type: Array,  default: () => [] },
})

const select = 'h-10 flex-1 min-w-0 px-2 border border-[#dadcde] rounded-md text-[13px] bg-white'
const selected = reactive(new Set())
const busy = ref(false)

const selectable = computed(() => props.entries.data.filter(e => !e.own))
const allSelected = computed(() => selectable.value.length > 0 && selectable.value.every(e => selected.has(e.id)))
const selectedDays = computed(() =>
  Math.round(props.entries.data.filter(e => selected.has(e.id)).reduce((s, e) => s + e.days, 0) * 100) / 100)

function toggle(e) { if (e.own) return; selected.has(e.id) ? selected.delete(e.id) : selected.add(e.id) }
function toggleAll() {
  if (allSelected.value) selectable.value.forEach(e => selected.delete(e.id))
  else selectable.value.forEach(e => selected.add(e.id))
}
function go(params) { router.get('/shramdaan/verify', params) }
function verify() {
  router.post('/shramdaan/verify', { ids: [...selected] }, {
    preserveScroll: true,
    onStart: () => busy.value = true,
    onFinish: () => busy.value = false,
    onSuccess: () => selected.clear(),
  })
}
function pager(disabled) {
  return ['h-11 flex-1 inline-flex items-center justify-center border border-[#dadcde] bg-white rounded-md text-[13px] no-underline text-[#1d273b]',
          disabled ? 'opacity-40 pointer-events-none' : '']
}
</script>
