<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 translate-y-2"
      leave-active-class="transition duration-200 ease-in"
      leave-to-class="opacity-0 translate-y-2">
    <div v-if="visible"
      class="notice-bar fixed bottom-0 left-0 right-0 z-[999] flex items-center"
      :style="`background:${bar.bg ?? '#1a2d45'};height:38px`">

      <!-- Left label -->
      <div class="shrink-0 flex items-center gap-1.5 px-3 py-1 border-r text-[11px] font-bold uppercase tracking-wider"
        style="border-color:rgba(255,255,255,0.15);color:rgba(255,255,255,0.6);white-space:nowrap">
        <i class="fa-solid fa-bullhorn text-[10px]"></i>
        <span>सूचना</span>
      </div>

      <!-- Scrolling track -->
      <div class="flex-1 overflow-hidden relative" style="height:38px">
        <div class="ticker-track flex items-center h-full" :style="`animation-duration:${speed}s`">
          <!-- Duplicate text for seamless loop -->
          <span v-for="n in 4" :key="n"
            class="text-[13px] font-medium px-12 whitespace-nowrap"
            style="color:rgba(255,255,255,0.88)">
            {{ bar.text }}
          </span>
        </div>
      </div>

      <!-- Close button -->
      <button @click="dismiss"
        class="shrink-0 w-8 h-full flex items-center justify-center transition-colors border-l ml-auto"
        style="border-color:rgba(255,255,255,0.15);color:rgba(255,255,255,0.55)"
        title="बन्द गर्नुहोस्"
        @mouseover="$event.currentTarget.style.background='rgba(255,255,255,0.08)'"
        @mouseleave="$event.currentTarget.style.background='transparent'">
        <i class="fa-solid fa-xmark text-[12px]"></i>
      </button>
    </div>
    </Transition>
    <!-- Body padding so content isn't hidden behind bar -->
    <div v-if="visible" style="height:38px;pointer-events:none;display:block" aria-hidden="true"></div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const bar  = computed(() => page.props.noticeBar ?? null)

const dismissed = ref(false)
const visible   = computed(() => bar.value && !dismissed.value)

// Speed based on text length: longer text scrolls faster to cover distance
const speed = computed(() => {
  const len = (bar.value?.text ?? '').length
  return Math.max(20, Math.min(60, Math.round(len * 0.35)))
})

onMounted(() => {
  dismissed.value = sessionStorage.getItem('notice_bar_dismissed') === '1'
})

function dismiss() {
  sessionStorage.setItem('notice_bar_dismissed', '1')
  dismissed.value = true
}
</script>

<style scoped>
@keyframes ticker {
  0%   { transform: translateX(0); }
  100% { transform: translateX(-25%); }
}
.ticker-track {
  animation: ticker linear infinite;
  will-change: transform;
}
.ticker-track:hover {
  animation-play-state: paused;
}
</style>
