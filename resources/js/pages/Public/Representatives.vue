<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">

    <!-- ── Page header ──────────────────────────────────────────────────── -->
    <div class="mb-5 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <p class="text-xs font-semibold uppercase tracking-widest text-rose-400 mb-1">
          {{ lg?.name_np ?? 'स्थानीय तह' }}
        </p>
        <h1 class="text-2xl font-bold text-[#1d273b]">जनप्रतिनिधि विवरण</h1>
        <p class="text-sm text-gray-400 mt-0.5">
          {{ locale === 'ne' ? 'निर्वाचित जनप्रतिनिधिहरूको सम्पूर्ण परिचय' : 'Profiles of elected local government representatives' }}
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
    <div v-if="props.representatives.length"
      class="mb-5 flex flex-col sm:flex-row gap-3">

      <!-- Search -->
      <div class="relative flex-1 max-w-sm">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[12px]"></i>
        <input v-model="search" type="text" placeholder="नाम खोज्नुहोस्..."
          class="w-full pl-9 pr-8 py-2 text-[13px] border border-[#e6e7e9] rounded-lg bg-white
                 focus:outline-none focus:border-rose-400 focus:ring-1 focus:ring-rose-100 transition" />
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
                       ? 'bg-rose-600 border-rose-600 text-white'
                       : 'bg-white border-[#e6e7e9] text-gray-600 hover:border-rose-300 hover:text-rose-600']">
            {{ opt.label }}
          </button>
        </div>
      </div>

      <!-- Result count -->
      <div v-if="search || sortBy !== 'default'"
        class="flex items-center shrink-0 text-[12px] text-gray-400">
        <span class="bg-rose-50 text-rose-600 font-semibold px-2.5 py-1 rounded-full border border-rose-100">
          {{ filtered.length }} / {{ props.representatives.length }}
        </span>
      </div>
    </div>

    <!-- ── Empty state (no data at all) ─────────────────────────────────── -->
    <div v-if="!props.representatives.length"
      class="flex flex-col items-center justify-center py-24 text-gray-300 gap-4">
      <i class="fa-solid fa-people-group text-5xl"></i>
      <p class="text-base font-medium text-gray-400">जनप्रतिनिधि विवरण उपलब्ध छैन</p>
    </div>

    <!-- ── No search results ─────────────────────────────────────────────── -->
    <div v-else-if="!filtered.length"
      class="flex flex-col items-center justify-center py-20 gap-3 text-gray-400">
      <i class="fa-solid fa-magnifying-glass text-4xl text-gray-200"></i>
      <p class="text-sm">"{{ search }}" नामको जनप्रतिनिधि फेला परेन।</p>
      <button @click="search = ''" class="text-rose-500 text-sm underline">खोज हटाउनुहोस्</button>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- ── CARD VIEW ─────────────────────────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div v-else-if="viewMode === 'card'" class="grid grid-cols-1 xl:grid-cols-2 gap-5">

      <div v-for="rep in filtered" :key="rep.id"
        class="rep-card bg-white rounded-xl border border-[#eaedf1] shadow-sm flex overflow-hidden
               transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">

        <!-- ── Left: Profile panel ── -->
        <div class="profile-side relative flex flex-col items-center px-5 py-5 shrink-0
                    bg-gradient-to-b from-[#fdf2f6] to-white border-r border-[#eaedf1]">

          <!-- Position badge -->
          <span class="absolute top-3 right-3 bg-[#d81b60] text-white text-[10px] font-bold
                       px-2.5 py-0.5 rounded-full leading-5 whitespace-nowrap">
            {{ positionLabel(rep.position) }}
          </span>

          <!-- Photo -->
          <div class="mt-5 w-20 h-20 rounded-full border-[3px] border-white shadow-md overflow-hidden
                      bg-rose-100 flex items-center justify-center">
            <img v-if="rep.photo" :src="`/storage/${rep.photo}`" class="w-full h-full object-cover" />
            <i v-else class="fa-solid fa-user text-rose-300 text-3xl"></i>
          </div>

          <!-- Name with search highlight -->
          <p class="mt-3 text-[14px] font-bold text-[#1d273b] text-center leading-snug"
            v-html="highlight(rep.name)"></p>

          <!-- Tags -->
          <div class="mt-2 flex flex-wrap justify-center gap-1.5">
            <span v-if="rep.ward"
              class="inline-flex items-center gap-1 text-[11px] text-rose-600 font-medium bg-rose-50 px-2 py-0.5 rounded-full border border-rose-100">
              <i class="fa-solid fa-map-pin text-[9px]"></i>वडा {{ rep.ward.ward_number }}
            </span>
            <span v-if="rep.gender"
              class="text-[11px] text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">
              {{ genderLabel(rep.gender) }}
            </span>
            <span v-if="rep.elected_party"
              class="inline-flex items-center gap-1 text-[11px] text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full border border-blue-100 max-w-[130px] truncate">
              <i class="fa-solid fa-flag text-[9px]"></i>{{ rep.elected_party }}
            </span>
          </div>
        </div>

        <!-- ── Right: Details panel ── -->
        <div class="flex-1 px-5 py-4 min-w-0 flex flex-col gap-4">

          <!-- ① प्राथमिक विवरण -->
          <div>
            <p class="section-label text-rose-500">
              <i class="fa-solid fa-circle-user mr-1 text-[10px]"></i>प्राथमिक विवरण
            </p>
            <div class="grid grid-cols-2 gap-x-4 gap-y-2 mt-2">
              <DataRow v-if="rep.contact"       label="सम्पर्क"         :value="rep.contact" />
              <DataRow v-if="rep.email"         label="इमेल"            :value="rep.email" />
              <DataRow v-if="rep.ethnicity"     label="जातजाति"         :value="optLabel('ethnicity', rep.ethnicity)" />
              <DataRow v-if="rep.education"     label="शैक्षिक योग्यता" :value="optLabel('education_level', rep.education)" />
              <DataRow v-if="rep.date_of_birth" label="जन्म मिति"       :value="fmtDate(rep.date_of_birth)" />
              <DataRow v-if="rep.birth_place"   label="जन्म स्थान"      :value="rep.birth_place" />
              <div v-if="rep.address_ward || rep.address_tole" class="col-span-2 flex items-baseline gap-2 text-[13px]">
                <span class="data-label">ठेगाना:</span>
                <span class="data-value">
                  <span v-if="rep.address_ward">वडा {{ rep.address_ward.ward_number }}</span>
                  <span v-if="rep.address_ward && rep.address_tole"> — </span>
                  <span v-if="rep.address_tole">{{ rep.address_tole.name_np }}</span>
                </span>
              </div>
            </div>
          </div>

          <!-- ② थप विवरण -->
          <div v-if="rep.father_name || rep.mother_name || rep.spouse_name || rep.work_experience"
            class="border-t border-[#eaedf1] pt-3">
            <p class="section-label text-gray-400">
              <i class="fa-solid fa-circle-info mr-1 text-[10px]"></i>थप विवरण
            </p>
            <div class="grid grid-cols-2 gap-x-4 gap-y-2 mt-2">
              <DataRow v-if="rep.father_name" label="बाबुको नाम"      :value="rep.father_name" />
              <DataRow v-if="rep.mother_name" label="आमाको नाम"       :value="rep.mother_name" />
              <DataRow v-if="rep.spouse_name" label="श्रीमान/श्रीमती" :value="rep.spouse_name" />
            </div>
            <div v-if="rep.work_experience" class="mt-2">
              <span class="data-label">कार्य अनुभव:</span>
              <p class="data-value mt-0.5 line-clamp-2 !font-normal text-[#555]">{{ rep.work_experience }}</p>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- ── LIST VIEW ──────────────────────────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div v-else class="space-y-2">

      <div v-for="rep in filtered" :key="rep.id"
        class="bg-white border border-[#e6e7e9] rounded-xl shadow-sm overflow-hidden">

        <!-- Accordion header -->
        <button type="button" @click="toggleList(rep.id)"
          class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors">

          <!-- Photo -->
          <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center overflow-hidden shrink-0 border border-rose-200">
            <img v-if="rep.photo" :src="`/storage/${rep.photo}`" class="w-full h-full object-cover" />
            <i v-else class="fa-solid fa-user text-rose-400 text-sm"></i>
          </div>

          <!-- Name + meta -->
          <div class="flex-1 min-w-0">
            <p class="text-[14px] font-semibold text-[#1d273b] truncate" v-html="highlight(rep.name)"></p>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5">
              <span class="text-xs text-rose-600 font-medium">{{ positionLabel(rep.position) }}</span>
              <span v-if="rep.ward" class="text-xs text-gray-400">
                <i class="fa-solid fa-map-pin text-[9px] mr-0.5"></i>वडा {{ rep.ward.ward_number }}
              </span>
              <span v-if="rep.address_tole" class="text-xs text-gray-400 hidden sm:inline">
                {{ rep.address_tole.name_np }}
              </span>
              <span v-if="rep.contact" class="text-xs text-gray-500">
                <i class="fa-solid fa-phone text-[9px] mr-0.5"></i>{{ rep.contact }}
              </span>
              <span v-if="rep.elected_party" class="text-xs text-gray-400 hidden md:inline">
                <i class="fa-solid fa-flag text-[9px] mr-0.5"></i>{{ rep.elected_party }}
              </span>
            </div>
          </div>

          <!-- Chevron -->
          <i :class="['fa-solid text-gray-400 text-xs transition-transform shrink-0',
                      openList[rep.id] ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
        </button>

        <!-- Accordion body -->
        <div v-if="openList[rep.id]" class="border-t border-[#e6e7e9] px-5 py-4">
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 text-xs">
            <div v-if="rep.contact">       <p class="text-gray-400 mb-0.5">सम्पर्क</p>           <p class="font-medium text-gray-700">{{ rep.contact }}</p></div>
            <div v-if="rep.email">         <p class="text-gray-400 mb-0.5">इमेल</p>              <p class="font-medium text-gray-700">{{ rep.email }}</p></div>
            <div v-if="rep.gender">        <p class="text-gray-400 mb-0.5">लिङ्ग</p>             <p class="font-medium text-gray-700">{{ genderLabel(rep.gender) }}</p></div>
            <div v-if="rep.ethnicity">     <p class="text-gray-400 mb-0.5">जातजाति</p>           <p class="font-medium text-gray-700">{{ optLabel('ethnicity', rep.ethnicity) }}</p></div>
            <div v-if="rep.education">     <p class="text-gray-400 mb-0.5">शिक्षा</p>             <p class="font-medium text-gray-700">{{ optLabel('education_level', rep.education) }}</p></div>
            <div v-if="rep.date_of_birth"> <p class="text-gray-400 mb-0.5">जन्म मिति</p>         <p class="font-medium text-gray-700">{{ fmtDate(rep.date_of_birth) }}</p></div>
            <div v-if="rep.birth_place">   <p class="text-gray-400 mb-0.5">जन्म स्थान</p>        <p class="font-medium text-gray-700">{{ rep.birth_place }}</p></div>
            <div v-if="rep.elected_party"> <p class="text-gray-400 mb-0.5">निर्वाचित दल</p>      <p class="font-medium text-gray-700">{{ rep.elected_party }}</p></div>
            <div v-if="rep.father_name">   <p class="text-gray-400 mb-0.5">बाबुको नाम</p>        <p class="font-medium text-gray-700">{{ rep.father_name }}</p></div>
            <div v-if="rep.mother_name">   <p class="text-gray-400 mb-0.5">आमाको नाम</p>         <p class="font-medium text-gray-700">{{ rep.mother_name }}</p></div>
            <div v-if="rep.spouse_name">   <p class="text-gray-400 mb-0.5">श्रीमान/श्रीमती</p>  <p class="font-medium text-gray-700">{{ rep.spouse_name }}</p></div>
            <div v-if="rep.address_ward">  <p class="text-gray-400 mb-0.5">ठेगाना (वडा)</p>     <p class="font-medium text-gray-700">वडा {{ rep.address_ward.ward_number }}</p></div>
            <div v-if="rep.address_tole">  <p class="text-gray-400 mb-0.5">टोल/बस्ती</p>        <p class="font-medium text-gray-700">{{ rep.address_tole.name_np }}</p></div>
          </div>
          <div v-if="rep.work_experience" class="mt-3 pt-3 border-t border-[#e6e7e9]">
            <p class="text-xs text-gray-400 mb-1">कार्य अनुभव</p>
            <p class="text-xs text-gray-700 whitespace-pre-wrap">{{ rep.work_experience }}</p>
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
  representatives:  { type: Array,  default: () => [] },
  surveyOptsForRep: { type: Object, default: () => ({}) },
})

// ── UI state ────────────────────────────────────────────────────────────────
const viewMode = ref('card')
const openList = reactive({})
const search   = ref('')
const sortBy   = ref('default')

function toggleList(id) { openList[id] = !openList[id] }

// ── Sort options ─────────────────────────────────────────────────────────────
const POSITION_ORDER = [
  'pramukh', 'upa_pramukh', 'adhyaksha', 'upa_adhyaksha',
  'ward_adhyaksha', 'mahila_sadashya', 'dalit_mahila', 'sadashya',
]

const sortOptions = [
  { key: 'default',  label: 'पूर्वनिर्धारित' },
  { key: 'position', label: 'पदअनुसार' },
  { key: 'ward',     label: 'वडाअनुसार' },
  { key: 'name',     label: 'नामअनुसार' },
]

// ── Filtered + sorted list ───────────────────────────────────────────────────
const filtered = computed(() => {
  let list = [...props.representatives]

  // Search by name
  const q = search.value.trim().toLowerCase()
  if (q) {
    list = list.filter(r => r.name?.toLowerCase().includes(q))
  }

  // Sort
  if (sortBy.value === 'position') {
    list = list.sort((a, b) => {
      const ai = POSITION_ORDER.indexOf(a.position ?? '')
      const bi = POSITION_ORDER.indexOf(b.position ?? '')
      return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi)
    })
  } else if (sortBy.value === 'ward') {
    list = list.sort((a, b) => (a.ward?.ward_number ?? 99) - (b.ward?.ward_number ?? 99))
  } else if (sortBy.value === 'name') {
    list = list.sort((a, b) => (a.name ?? '').localeCompare(b.name ?? '', 'ne'))
  }

  return list
})

// ── Search highlight ─────────────────────────────────────────────────────────
function highlight(text) {
  if (!search.value.trim() || !text) return text
  const q = search.value.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  return text.replace(new RegExp(q, 'gi'), m => `<mark class="bg-rose-100 text-rose-700 rounded px-0.5">${m}</mark>`)
}

// ── Label helpers ────────────────────────────────────────────────────────────
const POSITIONS = {
  pramukh:         'प्रमुख',
  upa_pramukh:     'उप-प्रमुख',
  adhyaksha:       'अध्यक्ष',
  upa_adhyaksha:   'उपाध्यक्ष',
  ward_adhyaksha:  'वार्ड अध्यक्ष',
  mahila_sadashya: 'महिला सदश्य',
  dalit_mahila:    'दलित महिला सदश्य',
  sadashya:        'सदश्य',
}
function positionLabel(v) { return POSITIONS[v] ?? v }
function genderLabel(v)   { return { male: 'पुरुष', female: 'महिला', other: 'अन्य' }[v] ?? v }
function fmtDate(v)       { return v ? String(v).slice(0, 10) : '—' }
function optLabel(category, value) {
  if (!value) return '—'
  const opts = props.surveyOptsForRep[category] ?? []
  const found = opts.find(o => o.value === value)
  return found ? found.label_ne : value
}

// ── Inline DataRow sub-component ──────────────────────────────────────────
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
  .rep-card       { flex-direction: column; }
  .profile-side   { width: 100%; border-right: none; border-bottom: 1px solid #eaedf1; }
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
