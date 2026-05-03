<template>
  <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden w-full max-w-sm">

    <!-- Card header -->
    <div class="bg-[#182433] px-6 py-6 text-center">
      <div class="w-14 h-14 rounded-full bg-violet-600/80 flex items-center justify-center mx-auto mb-3">
        <i class="fa-solid fa-shield-halved text-white text-2xl"></i>
      </div>
      <h1 class="text-white font-semibold text-[15px] leading-tight">{{ props.lg?.name_np ?? 'APPRMP' }}</h1>
      <p v-if="props.lg?.name_en" class="text-white/70 text-[12px] mt-1">{{ props.lg.name_en }}</p>
      <span class="inline-flex items-center gap-1.5 mt-2 px-3 py-1 rounded bg-white/10 text-white/80 text-[11px] font-medium">
        <i class="fa-solid fa-lock text-[10px]"></i>
        दुई-चरण प्रमाणीकरण
      </span>
    </div>

    <!-- Card body -->
    <div class="px-6 py-6">

      <p class="text-[13px] text-gray-500 text-center mb-5">
        <template v-if="!showRecovery">
          Google Authenticator एपबाट <strong class="text-gray-700">6-अङ्कीय कोड</strong> प्रविष्ट गर्नुहोस्।
        </template>
        <template v-else>
          आफ्नो <strong class="text-gray-700">रिकभरी कोड</strong> प्रविष्ट गर्नुहोस्।
        </template>
      </p>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <input
            ref="codeInput"
            v-model="form.code"
            :type="showRecovery ? 'text' : 'text'"
            :placeholder="showRecovery ? 'XXXXX-XXXXX' : '000000'"
            :maxlength="showRecovery ? 11 : 6"
            autocomplete="one-time-code"
            autofocus
            class="w-full px-4 py-3 border rounded-md text-center font-mono text-lg tracking-widest focus:ring-2 focus:ring-violet-500/30 focus:border-violet-500 transition-colors"
            :class="form.errors.code ? 'border-red-400 bg-red-50' : 'border-gray-200'"
          />
          <p v-if="form.errors.code" class="mt-1.5 text-xs text-red-500 text-center">
            <i class="fa-solid fa-circle-xmark mr-1"></i>{{ form.errors.code }}
          </p>
        </div>

        <button type="submit" :disabled="form.processing"
          class="w-full h-10 bg-violet-600 hover:bg-violet-700 text-white text-[13px] font-semibold rounded-md transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
          <i v-if="form.processing" class="fa-solid fa-spinner fa-spin text-[11px]"></i>
          <i v-else class="fa-solid fa-arrow-right-to-bracket text-[11px]"></i>
          {{ form.processing ? 'प्रमाणित हुँदैछ...' : 'प्रमाणित गर्नुहोस्' }}
        </button>
      </form>

      <div class="mt-5 text-center">
        <button type="button" @click="toggleMode"
          class="text-[12px] text-violet-600 hover:text-violet-800 font-medium transition-colors">
          <template v-if="!showRecovery">
            <i class="fa-solid fa-key mr-1"></i>रिकभरी कोड प्रयोग गर्नुहोस्
          </template>
          <template v-else>
            <i class="fa-solid fa-mobile-screen-button mr-1"></i>Authenticator कोड प्रयोग गर्नुहोस्
          </template>
        </button>
      </div>
    </div>

    <div class="px-6 py-3 bg-gray-50 border-t border-[#e6e7e9] text-center">
      <a href="/login" class="inline-flex items-center gap-1.5 text-[12px] text-[#206bc4] hover:text-[#1a5ba8] font-medium transition-colors">
        <i class="fa-solid fa-arrow-left text-[10px]"></i>
        लगइनमा फर्कनुहोस्
      </a>
    </div>

  </div>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import { useForm } from '@inertiajs/vue3'
import GuestLayout from '@/layouts/GuestLayout.vue'

defineOptions({ layout: GuestLayout })

const props = defineProps({
  lg: { type: Object, default: null },
})

const showRecovery = ref(false)
const codeInput    = ref(null)

const form = useForm({ code: '' })

function submit() {
  form.post('/two-factor-challenge', {
    onFinish: () => { if (form.errors.code) form.reset('code') },
  })
}

function toggleMode() {
  showRecovery.value = !showRecovery.value
  form.reset('code')
  form.clearErrors()
  nextTick(() => codeInput.value?.focus())
}
</script>
