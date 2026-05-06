<template>
  <div>

    <!-- Heading -->
    <div class="mb-7">
      <div class="w-11 h-11 rounded-full bg-violet-100 flex items-center justify-center mb-4">
        <i class="fa-solid fa-shield-halved text-violet-600 text-lg"></i>
      </div>
      <h1 class="text-[1.65rem] font-extrabold text-gray-900 leading-tight">Two-Factor Auth</h1>
      <p class="text-gray-500 text-[13.5px] mt-1">
        <template v-if="!showRecovery">
          Enter the <strong class="text-gray-700 font-semibold">6-digit code</strong> from Google Authenticator
        </template>
        <template v-else>
          Enter one of your <strong class="text-gray-700 font-semibold">recovery codes</strong>
        </template>
      </p>
    </div>

    <form @submit.prevent="submit" class="space-y-5">

      <!-- Code input -->
      <div>
        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">
          {{ showRecovery ? 'Recovery code' : 'Authentication code' }}
        </label>
        <input
          ref="codeInput"
          v-model="form.code"
          type="text"
          :placeholder="showRecovery ? 'XXXXX-XXXXX' : '000000'"
          :maxlength="showRecovery ? 11 : 6"
          autocomplete="one-time-code"
          autofocus
          class="w-full h-11 px-3.5 rounded-lg border text-center font-mono text-[18px] tracking-[0.25em]
                 transition outline-none focus:ring-2 focus:ring-violet-500/25 focus:border-violet-500"
          :class="form.errors.code
            ? 'border-red-400 bg-red-50'
            : 'border-gray-200 bg-white'"
        />
        <p v-if="form.errors.code" class="mt-1.5 text-[11.5px] text-red-500 text-center">
          <i class="fa-solid fa-circle-xmark mr-1"></i>{{ form.errors.code }}
        </p>
      </div>

      <!-- Submit -->
      <button
        type="submit"
        :disabled="form.processing"
        class="w-full h-11 rounded-lg bg-violet-600 hover:bg-violet-700 active:bg-violet-800
               text-white font-semibold text-[14px] transition-colors disabled:opacity-60
               flex items-center justify-center gap-2 shadow-sm shadow-violet-500/25"
      >
        <i v-if="form.processing" class="fa-solid fa-spinner fa-spin text-[13px]"></i>
        {{ form.processing ? 'Verifying…' : 'Verify & Sign In' }}
      </button>

    </form>

    <!-- Toggle TOTP ↔ Recovery -->
    <div class="mt-5 text-center">
      <button type="button" @click="toggleMode"
        class="text-[13px] text-violet-600 hover:text-violet-800 font-medium transition-colors">
        <template v-if="!showRecovery">
          <i class="fa-solid fa-key mr-1.5"></i>Use a recovery code instead
        </template>
        <template v-else>
          <i class="fa-solid fa-mobile-screen-button mr-1.5"></i>Use authenticator app instead
        </template>
      </button>
    </div>

    <!-- Back to login -->
    <p class="text-center text-[13px] text-gray-400 mt-6">
      <a href="/login" class="text-[#206bc4] font-semibold hover:underline">
        <i class="fa-solid fa-arrow-left text-[11px] mr-1"></i>Back to login
      </a>
    </p>

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
