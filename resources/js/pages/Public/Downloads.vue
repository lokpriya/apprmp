<template>
  <div class="space-y-6">

    <!-- ── Page header ─────────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl border border-[#e8eaf0] shadow-sm overflow-hidden">
      <div class="bg-gradient-to-r from-teal-700 via-teal-600 to-cyan-600 px-6 py-5 relative overflow-hidden">
        <div class="absolute -top-6 -right-6 w-28 h-28 rounded-full bg-white/5"></div>
        <div class="absolute -bottom-4 right-20 w-16 h-16 rounded-full bg-white/5"></div>
        <div class="relative flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-white/15 ring-1 ring-white/20 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-file-arrow-down text-white text-xl"></i>
          </div>
          <div>
            <h1 class="text-[18px] font-bold text-white leading-tight">डाउनलोड सामग्री</h1>
            <p class="text-teal-100/80 text-[12px] mt-0.5">{{ props.lg?.name_np ?? props.lg?.name_en }} — सार्वजनिक कागजपत्र तथा स्रोत सामग्री</p>
          </div>
          <div class="ml-auto shrink-0">
            <span class="px-3 py-1.5 rounded-full bg-white/15 text-white text-xs font-semibold ring-1 ring-white/20">
              <i class="fa-solid fa-layer-group mr-1 text-[10px]"></i>{{ props.links.length }} सामग्री
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Empty state ──────────────────────────────────────────────────── -->
    <div v-if="!props.links.length"
      class="bg-white rounded-xl border border-dashed border-[#e8eaf0] py-20 flex flex-col items-center gap-4 text-gray-400">
      <div class="w-16 h-16 rounded-full bg-teal-50 flex items-center justify-center">
        <i class="fa-solid fa-folder-open text-teal-300 text-2xl"></i>
      </div>
      <div class="text-center">
        <p class="font-semibold text-gray-500">अहिलेसम्म कुनै सामग्री उपलब्ध छैन</p>
        <p class="text-sm text-gray-400 mt-1">नयाँ सामग्री थपिएपछि यहाँ देखिनेछ।</p>
      </div>
    </div>

    <!-- ── Cards grid ───────────────────────────────────────────────────── -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <div v-for="link in props.links" :key="link.id"
        class="group bg-white rounded-xl border border-[#e8eaf0] shadow-sm overflow-hidden
               hover:shadow-md hover:border-teal-200 transition-all duration-200 flex flex-col">

        <!-- Thumbnail — click opens viewer for PDF, else opens link -->
        <div @click="isPdf(link.url) ? openViewer(link) : openUrl(link.url)"
          class="relative h-40 bg-gradient-to-br from-teal-50 to-cyan-50 overflow-hidden shrink-0 cursor-pointer">

          <img v-if="link.thumbnail" :src="link.thumbnail" :alt="link.title"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />

          <div v-else class="w-full h-full flex flex-col items-center justify-center gap-2">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center transition-colors"
              :class="isPdf(link.url) ? 'bg-red-100 group-hover:bg-red-200' : 'bg-teal-100 group-hover:bg-teal-200'">
              <i class="text-2xl"
                :class="isPdf(link.url) ? 'fa-solid fa-file-pdf text-red-500' : 'fa-solid fa-file-arrow-down text-teal-500'"></i>
            </div>
            <span class="text-[11px] font-bold uppercase tracking-wider"
              :class="isPdf(link.url) ? 'text-red-400' : 'text-teal-400'">
              {{ isPdf(link.url) ? 'PDF' : 'Download' }}
            </span>
          </div>

          <!-- PDF badge on thumbnail -->
          <div v-if="isPdf(link.url)"
            class="absolute top-2.5 right-2.5 px-2 py-0.5 rounded bg-red-500 text-white text-[10px] font-bold tracking-wide shadow-sm">
            PDF
          </div>

          <!-- Date badge -->
          <div v-if="link.created_date"
            class="absolute top-2.5 left-2.5 px-2 py-1 rounded-md bg-white/90 backdrop-blur-sm text-[11px] font-semibold text-gray-600 shadow-sm">
            <i class="fa-solid fa-calendar-days text-teal-500 mr-1 text-[10px]"></i>
            {{ formatDate(link.created_date) }}
          </div>

          <!-- Hover overlay -->
          <div class="absolute inset-0 flex items-center justify-center
                      bg-teal-900/0 group-hover:bg-teal-900/10 transition-colors">
            <div class="w-10 h-10 rounded-full bg-white shadow-lg flex items-center justify-center
                        opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition-all duration-200">
              <i class="text-sm"
                :class="isPdf(link.url) ? 'fa-solid fa-eye text-red-500' : 'fa-solid fa-arrow-down text-teal-600'"></i>
            </div>
          </div>
        </div>

        <!-- Card body -->
        <div class="p-4 flex flex-col flex-1">
          <h3 class="font-bold text-[14px] text-[#1d273b] leading-snug line-clamp-2 group-hover:text-teal-700 transition-colors">
            {{ link.title }}
          </h3>
          <p v-if="link.description" class="text-[12px] text-gray-500 mt-1.5 line-clamp-2 flex-1 leading-relaxed">
            {{ link.description }}
          </p>
          <div v-else class="flex-1"></div>

          <!-- Action buttons -->
          <div class="mt-3 pt-3 border-t border-[#f0f1f3] flex items-center gap-2">

            <!-- View button — PDF only -->
            <button v-if="isPdf(link.url)" @click="openViewer(link)"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-800
                     text-white text-[12px] font-semibold transition-colors shrink-0">
              <i class="fa-solid fa-eye text-[10px]"></i>
              हेर्नुहोस्
            </button>

            <!-- Spacer for non-PDF -->
            <span v-else class="text-[11px] text-gray-400 font-mono truncate flex-1">
              <i class="fa-solid fa-link text-[9px] mr-1"></i>{{ shortUrl(link.url) }}
            </span>

            <!-- Hostname for PDF (fills space) -->
            <span v-if="isPdf(link.url)" class="text-[11px] text-gray-400 font-mono truncate flex-1">
              <i class="fa-solid fa-link text-[9px] mr-1"></i>{{ shortUrl(link.url) }}
            </span>

            <!-- Download / Open button -->
            <a :href="link.url" target="_blank" rel="noopener noreferrer"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg shrink-0
                     bg-teal-600 hover:bg-teal-700 text-white text-[12px] font-semibold transition-colors no-underline">
              <i class="fa-solid fa-download text-[10px]"></i>
              डाउनलोड
            </a>

          </div>
        </div>

      </div>
    </div>

    <!-- ── PDF Viewer Modal ─────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="viewer">
        <div v-if="viewer.open"
          class="fixed inset-0 z-[9999] flex flex-col bg-black/80 backdrop-blur-sm"
          @keydown.esc="closeViewer">

          <!-- Modal header -->
          <div class="bg-[#1a2535] px-4 py-3 flex items-center gap-3 shrink-0 border-b border-white/10">

            <!-- PDF icon + title -->
            <div class="w-8 h-8 rounded bg-red-500/20 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-file-pdf text-red-400 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-white font-semibold text-[13px] truncate">{{ viewer.link?.title }}</p>
              <p class="text-white/40 text-[11px] font-mono truncate">{{ viewer.link?.url }}</p>
            </div>

            <!-- Viewer toggle -->
            <div class="flex items-center gap-1 bg-white/8 rounded-lg p-1 shrink-0">
              <button @click="viewer.mode = 'direct'"
                class="px-2.5 py-1 rounded text-[11px] font-semibold transition-colors"
                :class="viewer.mode === 'direct'
                  ? 'bg-white text-gray-800 shadow-sm'
                  : 'text-white/50 hover:text-white'">
                <i class="fa-solid fa-file-pdf mr-1 text-[10px]"></i>Direct
              </button>
              <button @click="viewer.mode = 'gdocs'"
                class="px-2.5 py-1 rounded text-[11px] font-semibold transition-colors"
                :class="viewer.mode === 'gdocs'
                  ? 'bg-white text-gray-800 shadow-sm'
                  : 'text-white/50 hover:text-white'">
                <i class="fa-brands fa-google mr-1 text-[10px]"></i>Google
              </button>
            </div>

            <!-- Actions -->
            <a :href="viewer.link?.url" target="_blank" rel="noopener"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-teal-600 hover:bg-teal-700
                     text-white text-[12px] font-semibold transition-colors no-underline shrink-0">
              <i class="fa-solid fa-download text-[10px]"></i>
              <span class="hidden sm:inline">डाउनलोड</span>
            </a>

            <button @click="closeViewer"
              class="w-8 h-8 rounded-lg bg-white/8 hover:bg-white/15 flex items-center justify-center text-white/70 hover:text-white transition-colors shrink-0">
              <i class="fa-solid fa-xmark text-sm"></i>
            </button>
          </div>

          <!-- Viewer hint bar -->
          <div v-if="viewer.mode === 'direct'"
            class="bg-amber-900/40 border-b border-amber-700/30 px-4 py-1.5 flex items-center gap-2 shrink-0">
            <i class="fa-solid fa-circle-info text-amber-400 text-[11px]"></i>
            <p class="text-amber-200/80 text-[11px]">
              PDF लोड नभएमा <button @click="viewer.mode = 'gdocs'" class="underline font-semibold hover:text-amber-100">Google Viewer</button> प्रयोग गर्नुहोस्।
            </p>
          </div>
          <div v-if="viewer.mode === 'gdocs'"
            class="bg-blue-900/40 border-b border-blue-700/30 px-4 py-1.5 flex items-center gap-2 shrink-0">
            <i class="fa-brands fa-google text-blue-300 text-[11px]"></i>
            <p class="text-blue-200/80 text-[11px]">Google Docs Viewer — इन्टरनेट जडान आवश्यक छ।</p>
          </div>

          <!-- iframe area -->
          <div class="flex-1 relative bg-gray-900 overflow-hidden">

            <!-- Loading spinner -->
            <div v-if="viewer.loading"
              class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-gray-900 z-10">
              <div class="w-12 h-12 rounded-full border-4 border-teal-500/30 border-t-teal-500 animate-spin"></div>
              <p class="text-white/50 text-sm">PDF लोड हुँदैछ...</p>
            </div>

            <!-- Direct PDF iframe -->
            <iframe v-if="viewer.mode === 'direct'"
              :key="'direct-' + viewer.link?.id"
              :src="viewer.link?.url"
              class="w-full h-full border-0"
              @load="viewer.loading = false"
              @error="viewer.loading = false"
              allowfullscreen>
            </iframe>

            <!-- Google Docs Viewer iframe -->
            <iframe v-if="viewer.mode === 'gdocs'"
              :key="'gdocs-' + viewer.link?.id"
              :src="`https://docs.google.com/viewer?url=${encodeURIComponent(viewer.link?.url ?? '')}&embedded=true`"
              class="w-full h-full border-0"
              @load="viewer.loading = false"
              @error="viewer.loading = false"
              allowfullscreen>
            </iframe>

          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>

<script setup>
import { defineOptions, reactive, onMounted, onBeforeUnmount } from 'vue'
import PublicLayout from '@/layouts/PublicLayout.vue'

defineOptions({ layout: PublicLayout })

const props = defineProps({
  lg:    { type: Object, default: null },
  links: { type: Array,  default: () => [] },
})

// ── PDF viewer ─────────────────────────────────────────────────────────────

const viewer = reactive({
  open:    false,
  link:    null,
  mode:    'direct',   // 'direct' | 'gdocs'
  loading: true,
})

function isPdf(url) {
  return /\.pdf(\?|#|$)/i.test(url)
}

function openViewer(link) {
  viewer.link    = link
  viewer.mode    = 'direct'
  viewer.loading = true
  viewer.open    = true
}

function closeViewer() {
  viewer.open = false
  viewer.link = null
}

function openUrl(url) {
  window.open(url, '_blank', 'noopener,noreferrer')
}

// Reset loading spinner when switching viewer mode
import { watch } from 'vue'
watch(() => viewer.mode, () => { viewer.loading = true })

// Keyboard ESC to close
function onKeydown(e) { if (e.key === 'Escape') closeViewer() }
onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

// ── Helpers ────────────────────────────────────────────────────────────────

function formatDate(d) {
  if (!d) return ''
  const parts = String(d).slice(0, 10).split('-')
  if (parts.length < 3) return d
  const months = ['जनवरी','फेब्रुअरी','मार्च','अप्रिल','मे','जुन','जुलाई','अगस्त','सेप्टेम्बर','अक्टोबर','नोभेम्बर','डिसेम्बर']
  return `${parts[2]} ${months[parseInt(parts[1]) - 1]} ${parts[0]}`
}

function shortUrl(url) {
  try { return new URL(url).hostname.replace(/^www\./, '') }
  catch { return url.slice(0, 30) }
}
</script>

<style scoped>
.viewer-enter-active, .viewer-leave-active { transition: opacity 0.2s ease; }
.viewer-enter-from, .viewer-leave-to       { opacity: 0; }
</style>
