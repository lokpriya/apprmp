<template>
  <div class="max-w-3xl mx-auto space-y-3 pb-10">

    <!-- Back + title -->
    <div>
      <Link :href="`/shramdaan/toles/${household.tole_id}`" class="text-[12px] text-[#206bc4] no-underline">
        <i class="fa-solid fa-chevron-left text-[10px]"></i> {{ household.tole }}
      </Link>
      <h1 class="text-[15px] font-semibold text-[#1d273b] mt-1">{{ household.head_name }}</h1>
      <p class="text-[12px] text-[#626976] font-mono">{{ passbook?.passbook_no ?? household.unique_code }}</p>
    </div>

    <!-- Family (from the survey, read-only) -->
    <section :class="card">
      <h2 :class="h2"><i class="fa-solid fa-id-card text-emerald-600"></i> घरमुली तथा परिवार</h2>
      <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-[13px]">
        <div><dt :class="dt">घर नं.</dt><dd class="font-mono">{{ household.unique_code }}</dd></div>
        <div><dt :class="dt">वडा / टोल</dt><dd>{{ household.ward_number }} · {{ household.tole }}</dd></div>
        <div><dt :class="dt">मोबाइल</dt><dd>
          <a v-if="household.phone" :href="`tel:${household.phone}`" class="font-mono text-[#1d273b] no-underline">{{ household.phone }}</a>
          <span v-else>—</span></dd></div>
        <div><dt :class="dt">परिवार संख्या</dt><dd>{{ household.family_size }} जना</dd></div>
        <div class="col-span-2"><dt :class="dt">मुख्य पेशा</dt><dd>{{ household.occupation || '—' }}</dd></div>
        <div v-if="passbook"><dt :class="dt">दर्ता मिति</dt><dd>{{ passbook.registered_on_bs }}</dd></div>
      </dl>
      <p class="text-[11px] text-[#9aa0ac] mt-2">यो विवरण सर्वेक्षणबाट आएको हो — यहाँबाट परिवर्तन हुँदैन।</p>
    </section>

    <!-- Not opened yet -->
    <section v-if="!passbook" :class="card" class="text-center py-6">
      <i class="fa-solid fa-book-open text-3xl text-emerald-600 mb-2"></i>
      <p class="text-[13px] text-[#1d273b]">यस परिवारको श्रमदान पासबुक अहिलेसम्म खोलिएको छैन।</p>
      <p class="text-[11px] text-[#9aa0ac] mt-1">पासबुक नं.: SD-{{ household.unique_code }}</p>
      <button v-if="canEdit" type="button" @click="openPassbook" :disabled="opening"
        class="mt-3 h-11 px-6 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-md text-[14px] font-semibold">
        <i class="fa-solid fa-plus mr-1"></i> पासबुक खोल्नुहोस्
      </button>
    </section>

    <template v-else>
      <!-- Fiscal year summary -->
      <section :class="card">
        <div class="flex items-center gap-2 mb-3">
          <h2 :class="h2" class="!mb-0 flex-1"><i class="fa-solid fa-chart-simple text-emerald-600"></i> आर्थिक वर्ष</h2>
          <select :value="fiscalYear" @change="changeFy($event.target.value)"
            class="h-9 px-2 border border-[#dadcde] rounded-md text-[13px] bg-white">
            <option v-for="y in fiscalYears" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center">
          <div class="rounded-md bg-emerald-50 p-2">
            <p class="text-[20px] font-bold text-emerald-700 leading-tight">{{ totals.verified_days }}</p>
            <p class="text-[10px] text-emerald-800">प्रमाणित दिन</p>
          </div>
          <div class="rounded-md bg-amber-50 p-2">
            <p class="text-[20px] font-bold text-amber-700 leading-tight">{{ totals.pending_days }}</p>
            <p class="text-[10px] text-amber-800">प्रमाणित हुन बाँकी</p>
          </div>
          <div class="rounded-md p-2" :class="tierStyle[totals.tier].bg">
            <p class="text-[18px] leading-tight">{{ tierStyle[totals.tier].icon }}</p>
            <p class="text-[10px] font-semibold" :class="tierStyle[totals.tier].text">{{ tierStyle[totals.tier].label }}</p>
          </div>
        </div>
        <p class="text-[11px] text-[#9aa0ac] mt-2">
          {{ settings.hours_per_day }} घण्टा = १ दिन · श्रेणी प्रमाणित दिनबाट मात्र
          (🥇 {{ settings.tier_gold }} · 🥈 {{ settings.tier_silver }} · 🥉 {{ settings.tier_bronze }} दिन)
        </p>
      </section>

      <!-- Members + passbook details -->
      <section :class="card">
        <h2 :class="h2"><i class="fa-solid fa-users text-emerald-600"></i> श्रमदानमा सहभागी हुने सदस्य
          <span class="text-[11px] font-normal text-[#9aa0ac]">({{ settings.min_age }}+ वर्ष)</span></h2>

        <div v-for="(m, i) in details.members" :key="m.household_member_id"
          class="border border-[#eef0f2] rounded-md p-3 mb-2" :class="m.excluded ? 'bg-gray-50' : ''">
          <div class="flex items-start gap-2">
            <div class="flex-1 min-w-0">
              <p class="text-[14px] font-semibold text-[#1d273b]" :class="m.excluded ? 'line-through text-[#9aa0ac]' : ''">
                {{ members[i].name }}
                <span v-if="members[i].is_head" class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 align-middle">घरमुली</span>
              </p>
              <p class="text-[11px] text-[#626976]">
                {{ members[i].relation || '—' }}
                <template v-if="members[i].age != null"> · {{ members[i].age }} वर्ष</template>
                <template v-if="members[i].gender"> · {{ genderLabel[members[i].gender] ?? members[i].gender }}</template>
              </p>
            </div>
            <label v-if="canEdit" class="flex items-center gap-1.5 text-[11px] text-[#626976] shrink-0">
              <input type="checkbox" v-model="m.excluded" class="w-4 h-4 rounded" /> असक्षम
            </label>
          </div>
          <div v-if="canEdit" class="grid grid-cols-2 gap-2 mt-2">
            <input v-model="m.citizenship" maxlength="60" placeholder="नागरिकता नं. (ऐच्छिक)" :class="input" />
            <input v-model="m.skill" maxlength="150" placeholder="दक्षता / सीप" :class="input" />
          </div>
          <p v-else class="text-[12px] text-[#626976] mt-1">{{ m.skill || '' }}</p>
        </div>

        <div v-if="canEdit" class="space-y-2 mt-3 pt-3 border-t border-[#eef0f2]">
          <input v-model="details.head_citizenship" maxlength="60" placeholder="घरमुलीको नागरिकता नं. र जिल्ला (ऐच्छिक)" :class="input" />
          <input v-model="details.certifying_officer" maxlength="150" placeholder="प्रमाणीकरण गर्ने अधिकारी (संयोजक/प्राविधिक)" :class="input" />
          <input v-model="details.remarks" maxlength="500" placeholder="विशेष कैफियत" :class="input" />
          <p class="text-[11px] text-[#9aa0ac]"><i class="fa-solid fa-lock"></i> नागरिकता नं. गोप्य राखिन्छ र पासबुकमा मात्र छापिन्छ।</p>
          <p v-for="(msg, k) in details.errors" :key="k" class="text-xs text-red-500">{{ msg }}</p>
          <button type="button" @click="saveDetails" :disabled="details.processing || !details.isDirty"
            class="w-full h-11 bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-50 text-white rounded-md text-[14px] font-semibold">
            <i class="fa-solid fa-floppy-disk mr-1"></i> विवरण सुरक्षित गर्नुहोस्
          </button>
        </div>
      </section>

      <!-- Work entries -->
      <section :class="card">
        <div class="flex items-center justify-between mb-3">
          <h2 :class="h2" class="!mb-0"><i class="fa-solid fa-clipboard-user text-emerald-600"></i> श्रमदान विवरण · {{ fiscalYear }}</h2>
          <button v-if="canEdit && !editing" type="button" @click="startNew"
            class="h-9 px-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-[13px] font-semibold">
            <i class="fa-solid fa-plus"></i> थप्नुहोस्
          </button>
        </div>

        <!-- Entry form -->
        <form v-if="editing" @submit.prevent="saveEntry" class="border-2 border-emerald-200 bg-emerald-50/40 rounded-md p-3 mb-3 space-y-2">
          <p class="text-[12px] font-semibold text-emerald-800">{{ entry.id ? 'श्रमदान सच्याउनुहोस्' : 'नयाँ श्रमदान' }}</p>
          <label class="block">
            <span :class="lbl">मिति (बि.सं.)</span>
            <NepaliDatePicker v-model="entry.work_date" placeholder="मिति छान्नुहोस्" />
          </label>
          <label class="block">
            <span :class="lbl">सहभागी सदस्य</span>
            <select v-model="entry.household_member_id" required :class="input">
              <option :value="null" disabled>छान्नुहोस्</option>
              <option v-for="m in workers" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </label>
          <label class="block">
            <span :class="lbl">योजना / कार्य</span>
            <select v-model="entry.project_id" :class="input">
              <option :value="null">— सूचीमा छैन (तल लेख्नुहोस्) —</option>
              <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <input v-if="!entry.project_id" v-model="entry.project_name" maxlength="250" placeholder="योजनाको नाम" :class="input" class="mt-2" />
          </label>
          <div class="grid grid-cols-2 gap-2">
            <label class="block">
              <span :class="lbl">समय (घण्टा)</span>
              <input type="number" step="0.5" min="0.5" max="24" v-model.number="entry.hours" required :class="input" />
            </label>
            <div>
              <span :class="lbl">दिन बराबर</span>
              <p class="h-10 flex items-center px-3 rounded-md bg-white border border-[#eef0f2] font-semibold text-emerald-700">
                {{ daysPreview }}
              </p>
            </div>
          </div>
          <input v-model="entry.work_nature" maxlength="250" placeholder="कामको प्रकृति (उदा: बाटो खन्ने, सरसफाइ)" :class="input" />
          <input v-model="entry.supervisor" maxlength="150" placeholder="सुपरिवेक्षक / उपभोक्ता समिति" :class="input" />
          <p v-for="(msg, k) in entry.errors" :key="k" class="text-xs text-red-500">{{ msg }}</p>
          <div class="flex gap-2 pt-1">
            <button type="button" @click="editing = false" class="h-11 flex-1 border border-[#dadcde] bg-white rounded-md text-[14px]">रद्द</button>
            <button type="submit" :disabled="entry.processing || !entry.work_date"
              class="h-11 flex-[2] bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-md text-[14px] font-semibold">
              <i class="fa-solid fa-floppy-disk mr-1"></i> सुरक्षित गर्नुहोस्
            </button>
          </div>
        </form>

        <p v-if="!entries.length && !editing" class="text-center text-[13px] text-[#9aa0ac] py-6">
          यस आर्थिक वर्षमा कुनै श्रमदान दर्ता भएको छैन।
        </p>

        <div v-for="e in entries" :key="e.id" class="border border-[#eef0f2] rounded-md p-3 mb-2">
          <div class="flex items-start gap-2">
            <div class="flex-1 min-w-0">
              <p class="text-[13px] font-semibold text-[#1d273b]">{{ e.project_name }}</p>
              <p class="text-[12px] text-[#626976]">
                <span class="font-mono">{{ e.work_date_bs }}</span> · {{ e.member_name }}
              </p>
              <p v-if="e.work_nature" class="text-[12px] text-[#626976]">{{ e.work_nature }}</p>
              <p v-if="e.supervisor" class="text-[11px] text-[#9aa0ac]">सुपरिवेक्षक: {{ e.supervisor }}</p>
            </div>
            <div class="text-right shrink-0">
              <p class="text-[15px] font-bold text-[#1d273b] leading-none">{{ e.days }} <span class="text-[10px] font-normal">दिन</span></p>
              <p class="text-[10px] text-[#9aa0ac]">{{ e.hours }} घण्टा</p>
            </div>
          </div>
          <div class="flex items-center gap-2 mt-2">
            <span v-if="e.verified" class="text-[11px] px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 font-semibold">✓ प्रमाणित</span>
            <span v-else class="text-[11px] px-2 py-0.5 rounded bg-amber-100 text-amber-700">प्रमाणित हुन बाँकी</span>
            <template v-if="canEdit && !e.verified">
              <button type="button" @click="startEdit(e)" class="ml-auto h-8 px-3 border border-[#dadcde] rounded text-[12px]">
                <i class="fa-solid fa-pen"></i> सच्याउनुहोस्
              </button>
              <button type="button" @click="removeEntry(e)" class="h-8 px-3 border border-red-200 text-red-600 rounded text-[12px]">
                <i class="fa-solid fa-trash-can"></i>
              </button>
            </template>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import NepaliDatePicker from '@/components/NepaliDatePicker.vue'

const props = defineProps({
  household:   { type: Object, required: true },
  passbook:    { type: Object, default: null },
  members:     { type: Array,  default: () => [] },
  entries:     { type: Array,  default: () => [] },
  totals:      { type: Object, default: null },
  projects:    { type: Array,  default: () => [] },
  fiscalYear:  { type: String, required: true },
  fiscalYears: { type: Array,  default: () => [] },
  isCurrentFy: { type: Boolean, default: true },
  fyRange:     { type: Array,  default: () => [] },
  settings:    { type: Object, required: true },
  canEdit:     { type: Boolean, default: false },
})

const card  = 'bg-white border border-[#e6e7e9] rounded-md p-4'
const h2    = 'text-[13px] font-semibold text-[#1d273b] flex items-center gap-2 mb-3'
const dt    = 'text-[11px] text-[#9aa0ac]'
const lbl   = 'block text-[11px] font-semibold text-[#626976] mb-1'
const input = 'w-full h-10 px-3 border border-[#dadcde] rounded-md text-[14px] bg-white focus:outline-none focus:border-[#206bc4]'

const genderLabel = { male: 'पुरुष', female: 'महिला', other: 'अन्य' }
const tierStyle = {
  gold:        { icon: '🥇', label: 'स्वर्ण',       bg: 'bg-amber-100',   text: 'text-amber-800' },
  silver:      { icon: '🥈', label: 'रजत',         bg: 'bg-slate-100',   text: 'text-slate-700' },
  bronze:      { icon: '🥉', label: 'कांस्य',       bg: 'bg-orange-100',  text: 'text-orange-800' },
  participant: { icon: '🌱', label: 'प्रारम्भिक',   bg: 'bg-emerald-50',  text: 'text-emerald-800' },
}

const url = `/shramdaan/households/${props.household.id}`

// ── Passbook ────────────────────────────────────────────────────────────────
const opening = ref(false)
function openPassbook() {
  router.post(`${url}/open`, {}, { preserveScroll: true, onStart: () => opening.value = true, onFinish: () => opening.value = false })
}

function changeFy(fy) {
  router.get(url, { fy }, { preserveScroll: true })
}

// ── Details ─────────────────────────────────────────────────────────────────
const details = useForm({
  head_citizenship:   props.passbook?.head_citizenship ?? '',
  certifying_officer: props.passbook?.certifying_officer ?? '',
  remarks:            props.passbook?.remarks ?? '',
  members: props.members.map(m => ({
    household_member_id: m.id, citizenship: m.citizenship ?? '', skill: m.skill ?? '', excluded: m.excluded,
  })),
})
function saveDetails() {
  details.patch(`${url}/details`, { preserveScroll: true, onSuccess: () => details.defaults() })
}

// ── Entries ─────────────────────────────────────────────────────────────────
const workers = computed(() => props.members.filter((m, i) => !details.members[i]?.excluded))
const editing = ref(false)
const entry = useForm({
  id: null, work_date: '', household_member_id: null, project_id: null, project_name: '',
  hours: props.settings.hours_per_day, work_nature: '', supervisor: '',
})
const daysPreview = computed(() => {
  const h = Number(entry.hours) || 0
  return Math.round((h / (Number(props.settings.hours_per_day) || 8)) * 100) / 100
})

function startNew() {
  entry.reset(); entry.clearErrors()
  entry.id = null
  entry.hours = props.settings.hours_per_day
  entry.household_member_id = workers.value.length === 1 ? workers.value[0].id : null
  editing.value = true
}
function startEdit(e) {
  entry.clearErrors()
  Object.assign(entry, {
    id: e.id, work_date: e.work_date, household_member_id: e.household_member_id,
    project_id: e.project_id, project_name: e.project_id ? '' : e.project_name,
    hours: e.hours, work_nature: e.work_nature ?? '', supervisor: e.supervisor ?? '',
  })
  editing.value = true
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
function saveEntry() {
  const opts = { preserveScroll: true, onSuccess: () => { editing.value = false } }
  if (entry.id) entry.patch(`/shramdaan/entries/${entry.id}`, opts)
  else entry.post(`${url}/entries`, opts)
}
function removeEntry(e) {
  if (!confirm(`"${e.project_name}" (${e.work_date_bs}) हटाउने?`)) return
  router.delete(`/shramdaan/entries/${e.id}`, { preserveScroll: true })
}
</script>
