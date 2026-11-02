<template>
  <div class="max-w-3xl mx-auto space-y-3">
    <div>
      <Link href="/shramdaan" class="text-[12px] text-[#206bc4] no-underline"><i class="fa-solid fa-chevron-left text-[10px]"></i> श्रमदान</Link>
      <div class="flex items-center gap-2 mt-1">
        <h1 class="text-[15px] font-semibold text-[#1d273b] flex-1">श्रमदान योजना सूची</h1>
        <select :value="fiscalYear" @change="router.get('/shramdaan/projects', { fy: $event.target.value })"
          class="h-9 px-2 border border-[#dadcde] rounded-md text-[13px] bg-white">
          <option v-for="y in fiscalYears" :key="y" :value="y">आ.व. {{ y }}</option>
        </select>
      </div>
      <p class="text-[11px] text-[#9aa0ac]">टोल संयोजकले श्रमदान दर्ता गर्दा यही सूचीबाट योजना छान्छन्।</p>
    </div>

    <!-- Add -->
    <form @submit.prevent="add" class="bg-white border border-[#e6e7e9] rounded-md p-4 space-y-2">
      <p class="text-[13px] font-semibold text-[#1d273b]">नयाँ योजना</p>
      <input v-model="form.name" required maxlength="250" placeholder="योजनाको नाम" :class="input" />
      <div class="grid grid-cols-2 gap-2">
        <select v-model="form.ward_id" required :class="input" @change="form.settlement_id = null">
          <option :value="null" disabled>वडा छान्नुहोस्</option>
          <option v-for="w in wards" :key="w.id" :value="w.id">वडा {{ w.ward_number }}</option>
        </select>
        <select v-model="form.settlement_id" :class="input" :disabled="!form.ward_id">
          <option :value="null">सम्पूर्ण वडा</option>
          <option v-for="t in wardToles" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
      </div>
      <p v-for="(msg, k) in form.errors" :key="k" class="text-xs text-red-500">{{ msg }}</p>
      <button type="submit" :disabled="form.processing"
        class="w-full h-11 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-md text-[14px] font-semibold">
        <i class="fa-solid fa-plus mr-1"></i> आ.व. {{ fiscalYear }} मा थप्नुहोस्
      </button>
    </form>

    <div v-if="!projects.length" class="text-center py-8 bg-white border border-[#e6e7e9] rounded-md text-[#9aa0ac] text-[13px]">
      यस आर्थिक वर्षमा कुनै योजना थपिएको छैन।
    </div>

    <div v-for="p in projects" :key="p.id" class="bg-white border border-[#e6e7e9] rounded-md p-3 flex items-center gap-3"
      :class="p.is_active ? '' : 'opacity-60'">
      <div class="flex-1 min-w-0">
        <p class="text-[14px] font-semibold text-[#1d273b]">{{ p.name }}</p>
        <p class="text-[12px] text-[#626976]">वडा {{ p.ward_number }}{{ p.tole ? ' · ' + p.tole : ' · सम्पूर्ण वडा' }} · {{ p.entries }} प्रविष्टि</p>
      </div>
      <button type="button" @click="toggle(p)"
        class="h-8 px-3 rounded text-[12px] border"
        :class="p.is_active ? 'border-[#dadcde] text-[#626976]' : 'border-emerald-300 text-emerald-700'">
        {{ p.is_active ? 'बन्द गर्नुहोस्' : 'सक्रिय गर्नुहोस्' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'

const props = defineProps({
  projects:    { type: Array,  default: () => [] },
  wards:       { type: Array,  default: () => [] },
  toles:       { type: Array,  default: () => [] },
  fiscalYear:  { type: String, required: true },
  fiscalYears: { type: Array,  default: () => [] },
})

const input = 'w-full h-10 px-3 border border-[#dadcde] rounded-md text-[14px] bg-white focus:outline-none focus:border-[#206bc4]'

const form = useForm({
  name: '', ward_id: props.wards.length === 1 ? props.wards[0].id : null, settlement_id: null, fiscal_year: props.fiscalYear,
})
const wardToles = computed(() => props.toles.filter(t => Number(t.ward_id) === Number(form.ward_id)))

function add() {
  form.fiscal_year = props.fiscalYear
  form.post('/shramdaan/projects', { preserveScroll: true, onSuccess: () => form.reset('name', 'settlement_id') })
}
function toggle(p) {
  router.patch(`/shramdaan/projects/${p.id}`, { is_active: !p.is_active }, { preserveScroll: true })
}
</script>
