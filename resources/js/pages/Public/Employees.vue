<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">

    <!-- Page header -->
    <div class="mb-5 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <p class="text-xs font-semibold uppercase tracking-widest text-cyan-500 mb-1">
          {{ lg?.name_np ?? 'स्थानीय तह' }}
        </p>
        <h1 class="text-2xl font-bold text-[#1d273b]">कर्मचारी विवरण</h1>
        <p class="text-sm text-gray-400 mt-0.5">
          {{ locale === 'ne' ? 'पालिकाका कर्मचारीहरूको सम्पूर्ण परिचय' : 'Staff profiles of the local government' }}
        </p>
      </div>

      <!-- View toggle -->
      <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1 self-start sm:self-auto shrink-0">
        <button @click="viewMode = 'card'"
          :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-all',
                   viewMode === 'card' ? 'bg-white text-[#1d273b] shadow-sm' : 'text-gray-500 hover:text-gray-700']">
          <i class="fa-solid fa-table-cells-large text-[11px]"></i> कार्ड
        </button>
        <button @click="viewMode = 'list'"
          :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-all',
                   viewMode === 'list' ? 'bg-white text-[#1d273b] shadow-sm' : 'text-gray-500 hover:text-gray-700']">
          <i class="fa-solid fa-list text-[11px]"></i> सूची
        </button>
      </div>
    </div>

    <!-- ── Toolbar: Search + Sort ────────────────────────────────────────── -->
    <div v-if="props.employees.length"
      class="mb-5 flex flex-col sm:flex-row gap-3">

      <!-- Search -->
      <div class="relative flex-1 max-w-sm">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[12px]"></i>
        <input v-model="search" type="text" placeholder="नाम खोज्नुहोस्..."
          class="w-full pl-9 pr-8 py-2 text-[13px] border border-[#e6e7e9] rounded-lg bg-white
                 focus:outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-100 transition" />
        <button v-if="search" @click="search = ''"
          class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
          <i class="fa-solid fa-xmark text-[11px]"></i>
        </button>
      </div>

      <!-- Sort -->
      <div class="flex items-center gap-2 shrink-0">
        <span class="text-[12px] text-gray-400 whitespace-nowrap">क्रमबद्ध:</span>
        <div class="flex gap-1">
          <button v-for="opt in sortOptions" :key="opt.key"
            @click="sortBy = opt.key"
            :class="['px-3 py-2 text-[12px] font-medium rounded-lg border transition-all',
                     sortBy === opt.key
                       ? 'bg-cyan-600 border-cyan-600 text-white'
                       : 'bg-white border-[#e6e7e9] text-gray-600 hover:border-cyan-300 hover:text-cyan-600']">
            {{ opt.label }}
          </button>
        </div>
      </div>

      <!-- Result count -->
      <div v-if="search || sortBy !== 'default'"
        class="flex items-center shrink-0 text-[12px] text-gray-400">
        <span class="bg-cyan-50 text-cyan-600 font-semibold px-2.5 py-1 rounded-full border border-cyan-100">
          {{ filtered.length }} / {{ props.employees.length }}
        </span>
      </div>
    </div>

    <!-- Empty state (no data) -->
    <div v-if="!props.employees.length"
      class="flex flex-col items-center justify-center py-24 text-gray-300 gap-4">
      <i class="fa-solid fa-id-badge text-5xl"></i>
      <p class="text-base font-medium text-gray-400">कर्मचारी विवरण उपलब्ध छैन</p>
    </div>

    <!-- No search results -->
    <div v-else-if="!filtered.length"
      class="flex flex-col items-center justify-center py-20 gap-3 text-gray-400">
      <i class="fa-solid fa-magnifying-glass text-4xl text-gray-200"></i>
      <p class="text-sm">"{{ search }}" नामको कर्मचारी फेला परेन।</p>
      <button @click="search = ''" class="text-cyan-500 text-sm underline">खोज हटाउनुहोस्</button>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- CARD VIEW -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div v-else-if="viewMode === 'card'" class="grid grid-cols-1 xl:grid-cols-2 gap-5">

      <div v-for="emp in filtered" :key="emp.id"
        class="emp-card bg-white rounded-xl border border-[#eaedf1] shadow-sm flex overflow-hidden
               transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">

        <!-- Left: Profile panel -->
        <div class="profile-side relative flex flex-col items-center px-5 py-5 shrink-0
                    bg-gradient-to-b from-[#ecfeff] to-white border-r border-[#eaedf1]">

          <!-- Photo -->
          <div class="mt-2 w-20 h-20 rounded-full border-[3px] border-white shadow-md overflow-hidden
                      bg-cyan-100 flex items-center justify-center">
            <img v-if="emp.photo" :src="`/storage/${emp.photo}`" class="w-full h-full object-cover" />
            <i v-else class="fa-solid fa-user text-cyan-300 text-3xl"></i>
          </div>

          <!-- Name with highlight -->
          <p class="mt-3 text-[14px] font-bold text-[#1d273b] text-center leading-snug"
            v-html="highlight(emp.name)"></p>

          <!-- Position/Department tags -->
          <div class="mt-2 flex flex-wrap justify-center gap-1.5">
            <span v-if="emp.position"
              class="inline-block text-[11px] text-cyan-700 font-semibold bg-cyan-50 border border-cyan-100 px-2 py-0.5 rounded-full">
              {{ emp.position }}
            </span>
            <span v-if="emp.department"
              class="inline-flex items-center gap-1 text-[11px] text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">
              <i class="fa-solid fa-building text-[9px]"></i>{{ emp.department }}
            </span>
            <span v-if="emp.gender"
              class="text-[11px] text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">
              {{ genderLabel(emp.gender) }}
            </span>
          </div>

          <!-- Social links -->
          <div v-if="emp.facebook || emp.instagram || emp.tiktok || emp.twitter_x"
            class="mt-3 flex items-center justify-center gap-3">
            <a v-if="emp.facebook"  :href="emp.facebook.startsWith('http') ? emp.facebook : `https://facebook.com/${emp.facebook}`"
              target="_blank" class="text-blue-500 hover:text-blue-700 transition text-[15px]">
              <i class="fa-brands fa-facebook"></i>
            </a>
            <a v-if="emp.instagram" :href="emp.instagram.startsWith('http') ? emp.instagram : `https://instagram.com/${emp.instagram}`"
              target="_blank" class="text-pink-500 hover:text-pink-700 transition text-[15px]">
              <i class="fa-brands fa-instagram"></i>
            </a>
            <a v-if="emp.tiktok"    :href="emp.tiktok.startsWith('http') ? emp.tiktok : `https://tiktok.com/@${emp.tiktok}`"
              target="_blank" class="text-gray-700 hover:text-black transition text-[15px]">
              <i class="fa-brands fa-tiktok"></i>
            </a>
            <a v-if="emp.twitter_x" :href="emp.twitter_x.startsWith('http') ? emp.twitter_x : `https://x.com/${emp.twitter_x}`"
              target="_blank" class="text-gray-700 hover:text-black transition text-[15px]">
              <i class="fa-brands fa-x-twitter"></i>
            </a>
          </div>
        </div>

        <!-- Right: Details panel -->
        <div class="flex-1 px-5 py-4 min-w-0 flex flex-col gap-4">

          <!-- ① प्राथमिक विवरण -->
          <div>
            <p class="section-label text-cyan-500">
              <i class="fa-solid fa-circle-user mr-1 text-[10px]"></i>प्राथमिक विवरण
            </p>
            <div class="grid grid-cols-2 gap-x-4 gap-y-2 mt-2">
              <DataRow v-if="emp.entry_date"        label="पदबहाल मिति"       :value="fmtDate(emp.entry_date)" />
              <DataRow v-if="emp.contact"           label="सम्पर्क"           :value="emp.contact" />
              <DataRow v-if="emp.email"             label="इमेल"              :value="emp.email" />
              <DataRow v-if="emp.ethnicity"         label="जातजाति"           :value="optLabel('ethnicity', emp.ethnicity)" />
              <DataRow v-if="emp.education"         label="शैक्षिक योग्यता"   :value="optLabel('education_level', emp.education)" />
              <DataRow v-if="emp.date_of_birth"     label="जन्म मिति"         :value="fmtDate(emp.date_of_birth)" />
              <DataRow v-if="emp.birth_place"       label="जन्म स्थान"        :value="emp.birth_place" />
              <div v-if="emp.permanent_address" class="col-span-2 flex items-baseline gap-2 text-[13px]">
                <span class="data-label">स्थायी ठेगाना:</span>
                <span class="data-value">{{ emp.permanent_address }}</span>
              </div>
            </div>
          </div>

          <!-- ② थप विवरण -->
          <div v-if="emp.father_name || emp.mother_name || emp.spouse_name || emp.work_experience"
            class="border-t border-[#eaedf1] pt-3">
            <p class="section-label text-gray-400">
              <i class="fa-solid fa-circle-info mr-1 text-[10px]"></i>थप विवरण
            </p>
            <div class="grid grid-cols-2 gap-x-4 gap-y-2 mt-2">
              <DataRow v-if="emp.father_name" label="बाबुको नाम"      :value="emp.father_name" />
              <DataRow v-if="emp.mother_name" label="आमाको नाम"       :value="emp.mother_name" />
              <DataRow v-if="emp.spouse_name" label="श्रीमान/श्रीमती" :value="emp.spouse_name" />
            </div>
            <div v-if="emp.work_experience" class="mt-2">
              <span class="data-label">कार्य अनुभव:</span>
              <p class="data-value mt-0.5 line-clamp-2 !font-normal text-[#555]">{{ emp.work_experience }}</p>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- LIST VIEW -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div v-else class="space-y-2">

      <div v-for="emp in filtered" :key="emp.id"
        class="bg-white border border-[#e6e7e9] rounded-xl shadow-sm overflow-hidden">

        <!-- Accordion header -->
        <button type="button" @click="toggleList(emp.id)"
          class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors">

          <div class="w-10 h-10 rounded-full bg-cyan-100 flex items-center justify-center overflow-hidden shrink-0 border border-cyan-200">
            <img v-if="emp.photo" :src="`/storage/${emp.photo}`" class="w-full h-full object-cover" />
            <i v-else class="fa-solid fa-user text-cyan-400 text-sm"></i>
          </div>

          <div class="flex-1 min-w-0">
            <p class="text-[14px] font-semibold text-[#1d273b] truncate" v-html="highlight(emp.name)"></p>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5">
              <span v-if="emp.position"   class="text-xs text-cyan-600 font-medium">{{ emp.position }}</span>
              <span v-if="emp.department" class="text-xs text-gray-400"><i class="fa-solid fa-building text-[9px] mr-0.5"></i>{{ emp.department }}</span>
              <span v-if="emp.contact"    class="text-xs text-gray-500"><i class="fa-solid fa-phone text-[9px] mr-0.5"></i>{{ emp.contact }}</span>
              <span v-if="emp.email"      class="text-xs text-gray-400 hidden md:inline"><i class="fa-solid fa-envelope text-[9px] mr-0.5"></i>{{ emp.email }}</span>
            </div>
          </div>

          <i :class="['fa-solid text-gray-400 text-xs transition-transform shrink-0',
                      openList[emp.id] ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
        </button>

        <!-- Accordion body -->
        <div v-if="openList[emp.id]" class="border-t border-[#e6e7e9] px-5 py-4">
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 text-xs">
            <div v-if="emp.position">          <p class="text-gray-400 mb-0.5">पद</p>                <p class="font-medium text-gray-700">{{ emp.position }}</p></div>
            <div v-if="emp.department">        <p class="text-gray-400 mb-0.5">शाखा</p>              <p class="font-medium text-gray-700">{{ emp.department }}</p></div>
            <div v-if="emp.entry_date">        <p class="text-gray-400 mb-0.5">पदबहाल मिति</p>      <p class="font-medium text-gray-700">{{ fmtDate(emp.entry_date) }}</p></div>
            <div v-if="emp.contact">           <p class="text-gray-400 mb-0.5">सम्पर्क</p>          <p class="font-medium text-gray-700">{{ emp.contact }}</p></div>
            <div v-if="emp.email">             <p class="text-gray-400 mb-0.5">इमेल</p>             <p class="font-medium text-gray-700">{{ emp.email }}</p></div>
            <div v-if="emp.gender">            <p class="text-gray-400 mb-0.5">लिङ्ग</p>            <p class="font-medium text-gray-700">{{ genderLabel(emp.gender) }}</p></div>
            <div v-if="emp.ethnicity">         <p class="text-gray-400 mb-0.5">जातजाति</p>          <p class="font-medium text-gray-700">{{ optLabel('ethnicity', emp.ethnicity) }}</p></div>
            <div v-if="emp.education">         <p class="text-gray-400 mb-0.5">शिक्षा</p>           <p class="font-medium text-gray-700">{{ optLabel('education_level', emp.education) }}</p></div>
            <div v-if="emp.date_of_birth">     <p class="text-gray-400 mb-0.5">जन्म मिति</p>        <p class="font-medium text-gray-700">{{ fmtDate(emp.date_of_birth) }}</p></div>
            <div v-if="emp.birth_place">       <p class="text-gray-400 mb-0.5">जन्म स्थान</p>       <p class="font-medium text-gray-700">{{ emp.birth_place }}</p></div>
            <div v-if="emp.permanent_address" class="sm:col-span-2">
                                               <p class="text-gray-400 mb-0.5">स्थायी ठेगाना</p>    <p class="font-medium text-gray-700">{{ emp.permanent_address }}</p></div>
            <div v-if="emp.father_name">       <p class="text-gray-400 mb-0.5">बाबुको नाम</p>       <p class="font-medium text-gray-700">{{ emp.father_name }}</p></div>
            <div v-if="emp.mother_name">       <p class="text-gray-400 mb-0.5">आमाको नाम</p>        <p class="font-medium text-gray-700">{{ emp.mother_name }}</p></div>
            <div v-if="emp.spouse_name">       <p class="text-gray-400 mb-0.5">श्रीमान/श्रीमती</p> <p class="font-medium text-gray-700">{{ emp.spouse_name }}</p></div>
          </div>
          <!-- Social IDs -->
          <div v-if="emp.facebook || emp.instagram || emp.tiktok || emp.twitter_x"
            class="mt-3 pt-3 border-t border-[#e6e7e9] flex flex-wrap gap-x-5 gap-y-1 text-xs">
            <span v-if="emp.facebook">  <i class="fa-brands fa-facebook text-blue-500 mr-1"></i>{{ emp.facebook }}</span>
            <span v-if="emp.instagram"> <i class="fa-brands fa-instagram text-pink-500 mr-1"></i>{{ emp.instagram }}</span>
            <span v-if="emp.tiktok">    <i class="fa-brands fa-tiktok text-gray-700 mr-1"></i>{{ emp.tiktok }}</span>
            <span v-if="emp.twitter_x"> <i class="fa-brands fa-x-twitter text-gray-700 mr-1"></i>{{ emp.twitter_x }}</span>
          </div>
          <div v-if="emp.work_experience" class="mt-3 pt-3 border-t border-[#e6e7e9]">
            <p class="text-xs text-gray-400 mb-1">कार्य अनुभव</p>
            <p class="text-xs text-gray-700 whitespace-pre-wrap">{{ emp.work_experience }}</p>
          </div>
        </div>

      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed, h } from 'vue'
import { useLocale } from '@/i18n/index.js'
import PublicLayout from '@/layouts/PublicLayout.vue'

defineOptions({ layout: PublicLayout })

const { locale } = useLocale()

const props = defineProps({
  lg:               { type: Object, default: null },
  employees:        { type: Array,  default: () => [] },
  surveyOptsForEmp: { type: Object, default: () => ({}) },
})

// ── UI state ────────────────────────────────────────────────────────────────
const viewMode = ref('card')
const openList = reactive({})
const search   = ref('')
const sortBy   = ref('default')

function toggleList(id) { openList[id] = !openList[id] }

// ── Sort options ─────────────────────────────────────────────────────────────
const sortOptions = [
  { key: 'default',    label: 'पूर्वनिर्धारित' },
  { key: 'position',   label: 'पदअनुसार'        },
  { key: 'department', label: 'शाखाअनुसार'      },
  { key: 'name',       label: 'नामअनुसार'        },
]

// ── Filtered + sorted list ───────────────────────────────────────────────────
const filtered = computed(() => {
  let list = [...props.employees]

  // Search by name
  const q = search.value.trim().toLowerCase()
  if (q) {
    list = list.filter(e => e.name?.toLowerCase().includes(q))
  }

  // Sort
  if (sortBy.value === 'position') {
    list = list.sort((a, b) => (a.position ?? '').localeCompare(b.position ?? '', 'ne'))
  } else if (sortBy.value === 'department') {
    list = list.sort((a, b) => (a.department ?? '').localeCompare(b.department ?? '', 'ne'))
  } else if (sortBy.value === 'name') {
    list = list.sort((a, b) => (a.name ?? '').localeCompare(b.name ?? '', 'ne'))
  }

  return list
})

// ── Search highlight ─────────────────────────────────────────────────────────
function highlight(text) {
  if (!search.value.trim() || !text) return text
  const q = search.value.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  return text.replace(new RegExp(q, 'gi'), m => `<mark class="bg-cyan-100 text-cyan-700 rounded px-0.5">${m}</mark>`)
}

// ── Label helpers ─────────────────────────────────────────────────────────────
function genderLabel(v) { return { male: 'पुरुष', female: 'महिला', other: 'अन्य' }[v] ?? v }
function fmtDate(v)     { return v ? String(v).slice(0, 10) : '—' }
function optLabel(category, value) {
  if (!value) return '—'
  const opts = props.surveyOptsForEmp[category] ?? []
  const found = opts.find(o => o.value === value)
  return found ? found.label_ne : value
}

// Inline DataRow sub-component
const DataRow = {
  props: ['label', 'value'],
  render() {
    return h('div', { class: 'flex items-baseline gap-1.5 text-[13px]' }, [
      h('span', { class: 'data-label' }, this.label + ':'),
      h('span', { class: 'data-value' }, this.value ?? '—'),
    ])
  },
}
</script>

<style scoped>
.profile-side { width: 190px; }
@media (max-width: 480px) {
  .emp-card     { flex-direction: column; }
  .profile-side { width: 100%; border-right: none; border-bottom: 1px solid #eaedf1; }
}

.section-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  display: flex;
  align-items: center;
  border-bottom: 1px solid #eaedf1;
  padding-bottom: 6px;
}

.data-label {
  color: #6c757d;
  white-space: nowrap;
  font-size: 13px;
  font-weight: 500;
  flex-shrink: 0;
  min-width: 90px;
}
.data-value {
  color: #333;
  font-size: 13px;
  font-weight: 500;
  word-break: break-word;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
