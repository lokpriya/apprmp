<template>
  <div class="w-full px-4 sm:px-6 py-6 space-y-6">

    <!-- Header -->
    <div>
      <h1 class="text-[18px] font-bold text-[#1d273b]">खुला तथ्याङ्क</h1>
      <p class="text-[12px] text-[#9aa0ac] mt-0.5">Open Data — download published data or access via JSON API</p>
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div v-for="s in statCards" :key="s.label"
        class="bg-white border border-[#e6e7e9] rounded-xl px-4 py-3 shadow-sm text-center">
        <p class="text-[22px] font-bold" :class="s.color">{{ s.value.toLocaleString() }}</p>
        <p class="text-[11px] text-gray-400 mt-0.5">{{ s.label }}</p>
      </div>
    </div>

    <p v-if="props.stats.updated_at" class="text-[11px] text-gray-400">
      <i class="fa-solid fa-clock mr-1"></i>
      अन्तिम अद्यावधिक: {{ formatDate(props.stats.updated_at) }}
    </p>

    <!-- CSV Downloads -->
    <div class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden shadow-sm">
      <div class="px-5 py-3.5 border-b border-[#e6e7e9] bg-gray-50/70">
        <p class="text-[13px] font-semibold text-[#1d273b]">
          <i class="fa-solid fa-file-csv text-emerald-500 mr-1.5"></i>
          CSV डाउनलोड
        </p>
        <p class="text-[11px] text-[#9aa0ac] mt-0.5">UTF-8 BOM — Excel मा सिधै खुल्छ</p>
      </div>
      <div class="divide-y divide-[#f0f1f3]">
        <div v-for="d in csvDownloads" :key="d.href" class="flex items-center gap-4 px-5 py-4">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
            :class="d.iconBg">
            <i :class="[d.icon, 'text-[16px]', d.iconColor]"></i>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[13px] font-semibold text-[#1d273b]">{{ d.title }}</p>
            <p class="text-[11px] text-gray-400 mt-0.5">{{ d.desc }}</p>
          </div>
          <a :href="d.href"
            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-[12px] font-medium text-white transition-colors no-underline shrink-0"
            :class="d.btnClass">
            <i class="fa-solid fa-download text-[11px]"></i>
            डाउनलोड
          </a>
        </div>
      </div>
    </div>

    <!-- JSON API -->
    <div class="bg-white border border-[#e6e7e9] rounded-xl overflow-hidden shadow-sm">
      <div class="px-5 py-3.5 border-b border-[#e6e7e9] bg-gray-50/70">
        <p class="text-[13px] font-semibold text-[#1d273b]">
          <i class="fa-solid fa-code text-[#206bc4] mr-1.5"></i>
          JSON API
        </p>
        <p class="text-[11px] text-[#9aa0ac] mt-0.5">Public REST API — no authentication required · rate limit 60 req/min</p>
      </div>
      <div class="divide-y divide-[#f0f1f3]">
        <div v-for="ep in apiEndpoints" :key="ep.path" class="flex items-start gap-4 px-5 py-4">
          <span class="shrink-0 mt-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700">GET</span>
          <div class="flex-1 min-w-0">
            <code class="text-[12px] font-mono text-[#1d273b]">{{ ep.path }}</code>
            <p class="text-[11px] text-gray-400 mt-0.5">{{ ep.desc }}</p>
          </div>
          <a :href="ep.path" target="_blank"
            class="shrink-0 inline-flex items-center gap-1 text-[11px] text-[#206bc4] hover:underline no-underline mt-0.5">
            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i> खोल्नुहोस्
          </a>
        </div>
      </div>
    </div>

    <!-- Usage note -->
    <div class="flex items-start gap-3 px-4 py-3 bg-blue-50 border border-blue-200 rounded-xl text-[12px] text-blue-700">
      <i class="fa-solid fa-circle-info shrink-0 mt-0.5"></i>
      <div>
        यस तथ्याङ्कको श्रेयमा <strong>{{ props.lg.name_np }}</strong> उल्लेख गर्नुहोस्।
        API प्रयोग गरी बनाएको कुनै अनुप्रयोग वा प्रतिवेदनको बारे कार्यालयलाई जानकारी दिनुभएमा हामीलाई खुसी लाग्नेछ।
      </div>
    </div>

  </div>
</template>

<script setup>
import PublicReportsLayout from '@/layouts/PublicReportsLayout.vue'

defineOptions({ layout: PublicReportsLayout })

const props = defineProps({
  lg:    { type: Object, default: null },
  stats: { type: Object, default: () => ({}) },
})

const statCards = [
  { label: 'घरधुरी',   value: props.stats.households   ?? 0, color: 'text-[#206bc4]' },
  { label: 'जनसंख्या', value: props.stats.members       ?? 0, color: 'text-emerald-600' },
  { label: 'संस्था',   value: props.stats.institutions  ?? 0, color: 'text-violet-600' },
  { label: 'व्यवसाय',  value: props.stats.businesses    ?? 0, color: 'text-orange-500' },
]

const csvDownloads = [
  {
    href:      '/public/opendata/wards',
    title:     'वडागत सारांश',
    desc:      'वडा नं., घरधुरी, जनसंख्या, साक्षरता, खानेपानी, शौचालय, इन्टरनेट, बैंक खाता',
    icon:      'fa-solid fa-map',
    iconBg:    'bg-blue-50',
    iconColor: 'text-[#206bc4]',
    btnClass:  'bg-[#206bc4] hover:bg-[#1a5ba8]',
  },
  {
    href:      '/public/opendata/institutions',
    title:     'संस्था सूची',
    desc:      'वडा नं., संस्थाको नाम, प्रकार, स्वामित्व, स्थापना मिति',
    icon:      'fa-solid fa-building-columns',
    iconBg:    'bg-violet-50',
    iconColor: 'text-violet-600',
    btnClass:  'bg-violet-600 hover:bg-violet-700',
  },
  {
    href:      '/public/opendata/businesses',
    title:     'व्यवसाय सूची',
    desc:      'वडा नं., व्यवसायको नाम, प्रकार, दर्ता नम्बर',
    icon:      'fa-solid fa-store',
    iconBg:    'bg-orange-50',
    iconColor: 'text-orange-500',
    btnClass:  'bg-orange-500 hover:bg-orange-600',
  },
]

const apiEndpoints = [
  { path: '/api/v1/summary',      desc: 'घरधुरी, जनसंख्या, संस्था, व्यवसाय — समग्र सारांश' },
  { path: '/api/v1/population',   desc: 'लिङ्ग, उमेर समूह, जाति, धर्म, साक्षरता विश्लेषण' },
  { path: '/api/v1/wards',        desc: 'वडागत सूचकहरू — घरधुरी, जनसंख्या, % सूचकहरू' },
  { path: '/api/v1/institutions', desc: 'संस्था प्रकार र वडागत वितरण' },
  { path: '/api/v1/economics',    desc: 'आम्दानी वितरण, बैंक खाता, बिमा, पेशा विश्लेषण' },
]

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('ne-NP', { year: 'numeric', month: 'long', day: 'numeric' })
}
</script>
