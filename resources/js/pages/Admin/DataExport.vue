<template>
  <div class="space-y-5 max-w-4xl">

    <!-- Header -->
    <div>
      <h1 class="text-[15px] font-semibold text-[#1d273b]">तथ्याङ्क निर्यात</h1>
      <p class="text-[11px] text-[#9aa0ac] mt-0.5">Full Data Export — Excel (multi-sheet)</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      <!-- ── Export form ── -->
      <div class="lg:col-span-2 space-y-4">

        <!-- Ward scope -->
        <div class="bg-white rounded-md border border-[#e6e7e9] p-5">
          <h2 class="text-[13px] font-semibold text-[#1d273b] mb-3 flex items-center gap-2">
            <i class="fa-solid fa-map-pin text-[#206bc4] text-[11px]"></i>
            वडा दायरा
          </h2>
          <div class="flex flex-wrap gap-2 mb-3">
            <label :class="scopeClass('all')" @click="form.ward_scope = 'all'; form.ward_id = ''">
              <input type="radio" v-model="form.ward_scope" value="all" class="sr-only" />
              <i class="fa-solid fa-globe text-[11px]"></i>
              सम्पूर्ण नगर/गाउँपालिका
            </label>
            <label :class="scopeClass('specific')" @click="form.ward_scope = 'specific'">
              <input type="radio" v-model="form.ward_scope" value="specific" class="sr-only" />
              <i class="fa-solid fa-map-pin text-[11px]"></i>
              एउटा वडा
            </label>
          </div>
          <select v-if="form.ward_scope === 'specific'"
            v-model="form.ward_id"
            class="w-full h-9 rounded border border-[#e6e7e9] text-[13px] text-[#1d273b] px-3 bg-white">
            <option value="">— वडा छान्नुहोस् —</option>
            <option v-for="w in wards" :key="w.id" :value="w.id">वडा {{ w.ward_number }}</option>
          </select>
        </div>

        <!-- Status scope -->
        <div class="bg-white rounded-md border border-[#e6e7e9] p-5">
          <h2 class="text-[13px] font-semibold text-[#1d273b] mb-3 flex items-center gap-2">
            <i class="fa-solid fa-filter text-[#206bc4] text-[11px]"></i>
            स्थिति फिल्टर
          </h2>
          <div class="grid grid-cols-2 gap-2">
            <label v-for="opt in statusOptions" :key="opt.value"
              :class="[
                'flex items-start gap-2.5 rounded-md border px-3 py-2.5 cursor-pointer transition-colors',
                form.status_scope === opt.value
                  ? 'border-[#206bc4] bg-[#206bc4]/5'
                  : 'border-[#e6e7e9] hover:bg-gray-50',
              ]">
              <input type="radio" v-model="form.status_scope" :value="opt.value" class="mt-0.5 accent-[#206bc4]" />
              <div>
                <p class="text-[13px] font-medium" :class="form.status_scope === opt.value ? 'text-[#206bc4]' : 'text-[#1d273b]'">
                  {{ opt.label }}
                </p>
                <p class="text-[11px] text-[#9aa0ac]">{{ opt.hint }}</p>
              </div>
            </label>
          </div>
        </div>

        <!-- Sheet selection -->
        <div class="bg-white rounded-md border border-[#e6e7e9] p-5">
          <h2 class="text-[13px] font-semibold text-[#1d273b] mb-3 flex items-center gap-2">
            <i class="fa-solid fa-table text-[#206bc4] text-[11px]"></i>
            Excel पाना (Sheets)
          </h2>

          <!-- Summary sheet — always included -->
          <div class="flex items-center gap-3 px-3 py-2.5 rounded-md bg-gray-50 border border-[#e6e7e9] mb-2 opacity-60">
            <i class="fa-solid fa-check-square text-[#206bc4] text-[13px]"></i>
            <div>
              <p class="text-[13px] font-medium text-[#1d273b]">सारांश पाना</p>
              <p class="text-[11px] text-[#9aa0ac]">वडागत स्थिति विश्लेषण — सधैं समावेश</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <label v-for="sheet in sheetOptions" :key="sheet.value"
              :class="[
                'flex items-start gap-2.5 rounded-md border px-3 py-2.5 cursor-pointer transition-colors select-none',
                form.sheets.includes(sheet.value)
                  ? 'border-[#206bc4] bg-[#206bc4]/5'
                  : 'border-[#e6e7e9] hover:bg-gray-50',
              ]">
              <input type="checkbox" :value="sheet.value" v-model="form.sheets"
                class="mt-0.5 w-3.5 h-3.5 accent-[#206bc4]" />
              <div>
                <p class="text-[13px] font-medium"
                  :class="form.sheets.includes(sheet.value) ? 'text-[#206bc4]' : 'text-[#1d273b]'">
                  {{ sheet.label }}
                </p>
                <p class="text-[11px] text-[#9aa0ac]">{{ sheet.hint }}</p>
              </div>
            </label>
          </div>

          <p v-if="form.sheets.length === 0"
            class="mt-2 text-[12px] text-red-500 flex items-center gap-1">
            <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
            कम्तीमा एउटा पाना छान्नुहोस्
          </p>
        </div>

        <!-- Download button -->
        <button
          @click="download"
          :disabled="!canDownload || downloading"
          class="w-full h-11 rounded-md bg-[#206bc4] hover:bg-[#1a5ba8] text-white text-[14px] font-semibold transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
        >
          <i :class="downloading ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-file-excel'"></i>
          {{ downloading ? 'तयार हुँदैछ...' : 'Excel फाइल डाउनलोड' }}
        </button>
      </div>

      <!-- ── Ward stats preview ── -->
      <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden self-start">
        <div class="px-4 py-3 border-b border-[#e6e7e9]">
          <h2 class="text-[13px] font-semibold text-[#1d273b] flex items-center gap-2">
            <i class="fa-solid fa-chart-bar text-[#206bc4] text-[11px]"></i>
            वडागत सारांश
          </h2>
        </div>
        <div class="divide-y divide-[#f0f1f3]">
          <div v-for="w in displayedWardStats" :key="w.id"
            class="px-4 py-2.5"
            :class="form.ward_scope === 'specific' && form.ward_id && form.ward_id != w.id ? 'opacity-30' : ''"
          >
            <div class="flex items-center justify-between mb-1">
              <span class="text-[12px] font-semibold text-[#1d273b]">वडा {{ w.ward_number }}</span>
              <span class="text-[11px] text-[#9aa0ac]">{{ w.total }} घर</span>
            </div>
            <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden flex">
              <div class="h-full bg-green-500"
                :style="{ width: w.total > 0 ? Math.round(w.published / w.total * 100) + '%' : '0%' }"></div>
              <div class="h-full bg-gray-300"
                :style="{ width: w.total > 0 ? Math.round(w.draft / w.total * 100) + '%' : '0%' }"></div>
            </div>
            <div class="flex justify-between mt-0.5">
              <span class="text-[10px] text-green-600">{{ w.published }} प्रकाशित</span>
              <span class="text-[10px] text-[#9aa0ac]">{{ w.draft }} मस्यौदा</span>
            </div>
          </div>
        </div>
        <div class="px-4 py-2 border-t border-[#e6e7e9] bg-gray-50 flex justify-between">
          <span class="text-[11px] font-semibold text-[#1d273b]">जम्मा</span>
          <span class="text-[11px] text-[#9aa0ac]">{{ totalHH }} घरधुरी</span>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
  wards:     { type: Array, default: () => [] },
  wardStats: { type: Array, default: () => [] },
})

const page        = usePage()
const downloading = ref(false)

const form = ref({
  ward_scope:   'all',
  ward_id:      '',
  status_scope: 'published',
  sheets:       ['households', 'members', 'institutions', 'businesses'],
})

const statusOptions = [
  { value: 'published',     label: 'प्रकाशित मात्र',        hint: 'अन्तिम प्रकाशित अभिलेख' },
  { value: 'approved_plus', label: 'वडा स्वीकृत र माथि',    hint: 'स्वीकृत + प्रकाशित' },
  { value: 'no_draft',      label: 'मस्यौदा बाहेक सबै',     hint: 'पेश गरिएका र माथिका' },
  { value: 'all',           label: 'सबै स्थिति',             hint: 'मस्यौदा सहित' },
]

const sheetOptions = [
  { value: 'households',   label: 'घरधुरी',       hint: 'मुख्य घरधुरी विवरण' },
  { value: 'members',      label: 'सदस्यहरू',     hint: 'परिवार सदस्य विवरण' },
  { value: 'institutions', label: 'संस्थाहरू',    hint: 'संस्थागत अभिलेख' },
  { value: 'businesses',   label: 'व्यवसायहरू',   hint: 'व्यवसाय अभिलेख' },
]

function scopeClass(val) {
  const base = 'flex items-center gap-2 px-3 py-2 rounded-md border text-[13px] font-medium cursor-pointer transition-colors select-none'
  return form.value.ward_scope === val
    ? base + ' border-[#206bc4] bg-[#206bc4]/5 text-[#206bc4]'
    : base + ' border-[#e6e7e9] text-[#626976] hover:bg-gray-50'
}

const canDownload = computed(() =>
  form.value.sheets.length > 0 &&
  (form.value.ward_scope === 'all' || !!form.value.ward_id)
)

const displayedWardStats = computed(() => props.wardStats)

const totalHH = computed(() => props.wardStats.reduce((s, w) => s + w.total, 0))

async function download() {
  if (!canDownload.value || downloading.value) return
  downloading.value = true

  const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? ''

  try {
    const params = new URLSearchParams({
      ward_scope:   form.value.ward_scope,
      ward_id:      form.value.ward_id ?? '',
      status_scope: form.value.status_scope,
    })
    form.value.sheets.forEach(s => params.append('sheets[]', s))
    params.append('_token', csrf)

    // POST via hidden form for file download (fetch can't trigger browser save dialog)
    const f = document.createElement('form')
    f.method = 'POST'
    f.action = '/admin/data-export/download'
    f.style.display = 'none'

    for (const [k, v] of params.entries()) {
      const input = document.createElement('input')
      input.name  = k
      input.value = v
      f.appendChild(input)
    }

    document.body.appendChild(f)
    f.submit()
    document.body.removeChild(f)

    // Give browser a moment to start the download before re-enabling button
    setTimeout(() => { downloading.value = false }, 3000)
  } catch {
    downloading.value = false
    alert('डाउनलोड गर्न सकिएन। कृपया पुनः प्रयास गर्नुहोस्।')
  }
}
</script>
