<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">

    <!-- ── Page header ──────────────────────────────────────────────────────── -->
    <div class="mb-5 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
      <div>
        <p class="text-xs font-semibold uppercase tracking-widest text-amber-500 mb-1">
          {{ lg?.name_np ?? 'स्थानीय तह' }}
        </p>
        <h1 class="text-2xl font-bold text-[#1d273b] flex items-center gap-2">
          <i class="fa-solid fa-images text-amber-400 text-xl"></i>
          फोटो ग्यालरी
        </h1>
        <p class="text-sm text-gray-400 mt-0.5">
          {{ locale === 'ne' ? 'पालिकाका महत्त्वपूर्ण क्षणहरूको फोटो संग्रह' : 'Photo collection of important moments' }}
        </p>
      </div>

      <!-- Stats pills -->
      <div class="flex items-center gap-2 self-start sm:self-auto shrink-0">
        <div class="flex items-center gap-1.5 bg-amber-50 border border-amber-100 rounded-lg px-3 py-1.5">
          <i class="fa-solid fa-images text-amber-400 text-xs"></i>
          <span class="text-[13px] font-bold text-amber-700">{{ photos.length }}</span>
          <span class="text-[11px] text-amber-500">फोटो</span>
        </div>
        <div class="flex items-center gap-1.5 bg-gray-50 border border-gray-100 rounded-lg px-3 py-1.5">
          <i class="fa-solid fa-layer-group text-gray-400 text-xs"></i>
          <span class="text-[13px] font-bold text-gray-700">{{ categories.length }}</span>
          <span class="text-[11px] text-gray-400">श्रेणी</span>
        </div>
      </div>
    </div>

    <!-- ── No gallery URL warning ──────────────────────────────────────────── -->
    <div v-if="!galleryUrl"
         class="mb-5 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 flex items-start gap-3">
      <i class="fa-solid fa-triangle-exclamation text-amber-400 mt-0.5 text-sm"></i>
      <div>
        <div class="font-semibold text-amber-800 text-sm">फोटो Gallery URL सेट गरिएको छैन</div>
        <div class="text-amber-600 text-xs mt-0.5">Admin › Settings › फोटो ट्याबमा Gallery URL थप्नुहोस्।</div>
      </div>
    </div>

    <!-- ── Empty state ────────────────────────────────────────────────────── -->
    <div v-if="photos.length === 0"
         class="flex flex-col items-center justify-center py-24 text-gray-300 gap-4">
      <i class="fa-solid fa-images text-5xl"></i>
      <p class="text-base font-medium text-gray-400">फोटो उपलब्ध छैन</p>
      <p class="text-sm text-gray-300">अहिलेसम्म कुनै फोटो प्रकाशित भएको छैन।</p>
    </div>

    <template v-else>
      <!-- ── Category filter chips ─────────────────────────────────────────── -->
      <div class="flex flex-wrap gap-2 mb-5">
        <button
          @click="activeCategory = null"
          class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-[13px] font-medium border transition-all"
          :class="activeCategory === null
            ? 'bg-[#1a2d45] text-white border-[#1a2d45] shadow-sm'
            : 'bg-white text-gray-600 border-gray-200 hover:border-[#1a2d45]/40 hover:text-[#1a2d45]'">
          <i class="fa-solid fa-layer-group text-[11px]"></i>
          सबै
          <span class="text-[11px] opacity-60">({{ photos.length }})</span>
        </button>

        <button
          v-for="cat in categories" :key="cat"
          @click="activeCategory = cat"
          class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-[13px] font-medium border transition-all"
          :class="activeCategory === cat
            ? 'bg-amber-500 text-white border-amber-500 shadow-sm'
            : 'bg-white text-gray-600 border-gray-200 hover:border-amber-300 hover:text-amber-700'">
          {{ cat }}
          <span class="text-[11px] opacity-60">({{ countForCat(cat) }})</span>
        </button>
      </div>

      <!-- ── Photo masonry grid ─────────────────────────────────────────────── -->
      <div class="columns-1 sm:columns-2 lg:columns-3 xl:columns-4 gap-4 space-y-4">
        <div
          v-for="ph in filteredPhotos" :key="ph.id"
          class="break-inside-avoid bg-white rounded-xl overflow-hidden shadow-sm
                 border border-gray-100 hover:shadow-md hover:-translate-y-0.5
                 transition-all duration-200 cursor-pointer group"
          @click="openLightbox(ph)">

          <!-- Image -->
          <div class="relative overflow-hidden bg-gray-100 aspect-[4/3]">
            <img
              v-if="galleryUrl && ph.photo_no"
              :src="`${galleryUrl}/${ph.photo_no}`"
              :alt="ph.title || ph.photo_no"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
              @error="e => { e.target.style.display='none'; e.target.nextElementSibling.classList.remove('hidden') }">
            <div class="absolute inset-0 flex flex-col items-center justify-center text-gray-300"
                 :class="(galleryUrl && ph.photo_no) ? 'hidden' : ''">
              <i class="fa-solid fa-image text-4xl mb-1.5"></i>
              <span class="text-xs">{{ ph.photo_no || '—' }}</span>
            </div>
            <!-- Badges -->
            <span v-if="ph.category"
              class="absolute top-2 left-2 bg-black/50 backdrop-blur-sm text-white
                     text-[10px] font-medium px-2 py-0.5 rounded-full leading-4">
              {{ ph.category }}
            </span>
            <span v-if="ph.photo_no"
              class="absolute top-2 right-2 bg-amber-500/90 text-white
                     text-[10px] font-bold px-2 py-0.5 rounded-full leading-4">
              #{{ ph.photo_no }}
            </span>
            <!-- Zoom overlay -->
            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/25 transition-colors
                        flex items-center justify-center pointer-events-none">
              <div class="w-9 h-9 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center
                          opacity-0 group-hover:opacity-100 transition-opacity">
                <i class="fa-solid fa-magnifying-glass-plus text-white text-sm"></i>
              </div>
            </div>
          </div>

          <!-- Caption -->
          <div class="px-3 py-2.5">
            <h3 v-if="ph.title" class="text-[13px] font-semibold text-gray-800 leading-snug line-clamp-2">
              {{ ph.title }}
            </h3>
            <p v-if="ph.subtitle" class="text-[11px] text-gray-500 mt-0.5 line-clamp-1">{{ ph.subtitle }}</p>
            <div class="flex items-center justify-between mt-1.5 text-[11px] text-gray-400">
              <span v-if="ph.photo_date">
                <i class="fa-regular fa-calendar mr-1"></i>{{ fmtDate(ph.photo_date) }}
              </span>
              <span v-if="ph.photo_credit" class="truncate max-w-[130px]">
                <i class="fa-solid fa-camera mr-1"></i>{{ ph.photo_credit }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Empty filter result ──────────────────────────────────────────── -->
      <div v-if="filteredPhotos.length === 0"
           class="flex flex-col items-center justify-center py-20 text-gray-300 gap-3">
        <i class="fa-solid fa-filter-circle-xmark text-4xl"></i>
        <p class="font-medium text-gray-400">यस श्रेणीमा फोटो उपलब्ध छैन</p>
      </div>
    </template>

  </div>

  <!-- ══════════════════════════════════════════════════════════════════════
       LIGHTBOX — Teleport outside layout
  ══════════════════════════════════════════════════════════════════════ -->
  <Teleport to="body">
    <Transition name="lb">
      <div v-if="lightbox.open"
           class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
           @click.self="closeLightbox">

        <!-- Backdrop -->
        <div class="absolute inset-0 backdrop-blur-sm" style="background:rgba(0,0,0,0.70)" @click="closeLightbox"></div>

        <!-- Panel -->
        <div class="relative z-10 w-full max-w-4xl flex flex-col max-h-[95vh]">

          <!-- Top controls -->
          <div class="flex items-center justify-between mb-2">
            <div class="flex gap-1.5">
              <button @click="prevPhoto"
                class="w-8 h-8 rounded-full flex items-center justify-center text-white transition
                       bg-white/12 hover:bg-white/22 disabled:opacity-30"
                :disabled="lightboxIndex === 0">
                <i class="fa-solid fa-chevron-left text-xs"></i>
              </button>
              <button @click="nextPhoto"
                class="w-8 h-8 rounded-full flex items-center justify-center text-white transition
                       bg-white/12 hover:bg-white/22 disabled:opacity-30"
                :disabled="lightboxIndex === filteredPhotos.length - 1">
                <i class="fa-solid fa-chevron-right text-xs"></i>
              </button>
            </div>
            <span class="text-white/50 text-xs tabular-nums">{{ lightboxIndex + 1 }} / {{ filteredPhotos.length }}</span>
            <button @click="closeLightbox"
              class="w-8 h-8 rounded-full flex items-center justify-center text-white
                     bg-white/12 hover:bg-white/22 transition">
              <i class="fa-solid fa-xmark text-sm"></i>
            </button>
          </div>

          <!-- Image -->
          <div class="bg-black/60 rounded-xl overflow-hidden flex items-center justify-center flex-1 min-h-0">
            <img
              v-if="galleryUrl && lightbox.photo?.photo_no"
              :src="`${galleryUrl}/${lightbox.photo.photo_no}`"
              :alt="lightbox.photo?.title"
              class="max-w-full max-h-[68vh] object-contain">
            <div v-else class="text-gray-500 py-20 text-center">
              <i class="fa-solid fa-image text-5xl mb-3 opacity-30"></i>
              <p class="text-sm">फोटो लोड हुन सकेन</p>
            </div>
          </div>

          <!-- Caption strip -->
          <div v-if="lightbox.photo?.title || lightbox.photo?.subtitle || lightbox.photo?.description || lightbox.photo?.category"
               class="mt-2 bg-white/8 backdrop-blur-md rounded-xl px-4 py-3 text-white">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <h3 v-if="lightbox.photo?.title" class="font-semibold text-[15px] leading-snug">
                  {{ lightbox.photo.title }}
                </h3>
                <p v-if="lightbox.photo?.subtitle" class="text-white/60 text-sm mt-0.5">
                  {{ lightbox.photo.subtitle }}
                </p>
                <p v-if="lightbox.photo?.description" class="text-white/50 text-xs mt-1.5 leading-relaxed">
                  {{ lightbox.photo.description }}
                </p>
              </div>
              <div class="shrink-0 text-right text-[12px] space-y-1 text-white/55">
                <div v-if="lightbox.photo?.category">
                  <i class="fa-solid fa-tag mr-1 text-amber-400"></i>{{ lightbox.photo.category }}
                </div>
                <div v-if="lightbox.photo?.photo_date">
                  <i class="fa-regular fa-calendar mr-1"></i>{{ fmtDate(lightbox.photo.photo_date) }}
                </div>
                <div v-if="lightbox.photo?.photo_credit">
                  <i class="fa-solid fa-camera mr-1"></i>{{ lightbox.photo.photo_credit }}
                </div>
                <div v-if="lightbox.photo?.gps_location">
                  <i class="fa-solid fa-location-dot mr-1 text-emerald-400"></i>{{ lightbox.photo.gps_location }}
                </div>
                <div v-if="lightbox.photo?.photo_no" class="font-semibold text-amber-300">
                  #{{ lightbox.photo.photo_no }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, reactive, onMounted, onBeforeUnmount } from 'vue'
import PublicLayout from '@/layouts/PublicLayout.vue'
import { useLocale } from '@/i18n/index.js'

defineOptions({ layout: PublicLayout })

const props = defineProps({
  lg:         { type: Object, default: null },
  photos:     { type: Array,  default: () => [] },
  galleryUrl: { type: String, default: null },
  categories: { type: Array,  default: () => [] },
})

const { locale } = useLocale()

// ── Category filter ────────────────────────────────────────────────────────
const activeCategory = ref(null)

const filteredPhotos = computed(() =>
  activeCategory.value
    ? props.photos.filter(p => p.category === activeCategory.value)
    : props.photos
)

function countForCat(cat) {
  return props.photos.filter(p => p.category === cat).length
}

// ── Date helper ────────────────────────────────────────────────────────────
function fmtDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('ne-NP', { year: 'numeric', month: 'short', day: 'numeric' })
}

// ── Lightbox ───────────────────────────────────────────────────────────────
const lightbox = reactive({ open: false, photo: null })
const lightboxIndex = ref(0)

function openLightbox(ph) {
  lightboxIndex.value = filteredPhotos.value.findIndex(p => p.id === ph.id)
  lightbox.photo = ph
  lightbox.open  = true
  document.body.style.overflow = 'hidden'
}

function closeLightbox() {
  lightbox.open = false
  lightbox.photo = null
  document.body.style.overflow = ''
}

function prevPhoto() {
  if (lightboxIndex.value > 0) {
    lightboxIndex.value--
    lightbox.photo = filteredPhotos.value[lightboxIndex.value]
  }
}

function nextPhoto() {
  if (lightboxIndex.value < filteredPhotos.value.length - 1) {
    lightboxIndex.value++
    lightbox.photo = filteredPhotos.value[lightboxIndex.value]
  }
}

function onKey(e) {
  if (!lightbox.open) return
  if (e.key === 'ArrowLeft')  prevPhoto()
  if (e.key === 'ArrowRight') nextPhoto()
  if (e.key === 'Escape')     closeLightbox()
}

onMounted(()    => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<style scoped>
.lb-enter-active, .lb-leave-active { transition: opacity 0.18s ease; }
.lb-enter-from, .lb-leave-to { opacity: 0; }
</style>
