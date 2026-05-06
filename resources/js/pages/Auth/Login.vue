<template>
  <div>

    <!-- Heading -->
    <div class="mb-7">
      <h1 class="text-[1.65rem] font-extrabold text-gray-900 leading-tight">Welcome back</h1>
      <p class="text-gray-500 text-[13.5px] mt-1">Sign in to your dashboard</p>
    </div>

    <!-- Status flash -->
    <div v-if="props.status"
         class="mb-5 flex items-center gap-2.5 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-[13px]">
      <i class="fa-solid fa-circle-check shrink-0"></i>
      {{ props.status }}
    </div>

    <form @submit.prevent="submit" class="space-y-5">

      <!-- Email -->
      <div>
        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Email address</label>
        <input
          v-model="form.email"
          type="email"
          required
          autofocus
          autocomplete="username"
          placeholder="you@example.com"
          class="w-full h-11 px-3.5 rounded-lg border text-[13.5px] transition outline-none
                 focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]"
          :class="form.errors.email
            ? 'border-red-400 bg-red-50 focus:ring-red-400/20 focus:border-red-400'
            : 'border-gray-200 bg-white'"
        />
        <p v-if="form.errors.email" class="mt-1.5 text-[11.5px] text-red-500">
          {{ form.errors.email }}
        </p>
      </div>

      <!-- Password -->
      <div>
        <label class="block text-[13px] font-medium text-gray-700 mb-1.5">Password</label>
        <div class="relative">
          <input
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            required
            autocomplete="current-password"
            placeholder="••••••••"
            class="w-full h-11 px-3.5 pr-11 rounded-lg border text-[13.5px] transition outline-none
                   focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]"
            :class="form.errors.password
              ? 'border-red-400 bg-red-50 focus:ring-red-400/20 focus:border-red-400'
              : 'border-gray-200 bg-white'"
          />
          <button
            type="button"
            @click="showPassword = !showPassword"
            class="absolute inset-y-0 right-0 px-3.5 flex items-center text-gray-400 hover:text-gray-600 transition-colors"
          >
            <i :class="showPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'" class="text-[14px]"></i>
          </button>
        </div>
        <p v-if="form.errors.password" class="mt-1.5 text-[11.5px] text-red-500">
          {{ form.errors.password }}
        </p>
      </div>

      <!-- Remember me -->
      <div class="flex items-center">
        <label class="flex items-center gap-2.5 cursor-pointer select-none">
          <input
            v-model="form.remember"
            type="checkbox"
            class="w-4 h-4 rounded border-gray-300 text-[#206bc4] focus:ring-[#206bc4]/30"
          />
          <span class="text-[13px] text-gray-600">Remember me</span>
        </label>
      </div>

      <!-- Submit -->
      <button
        type="submit"
        :disabled="form.processing"
        class="w-full h-11 rounded-lg bg-[#206bc4] hover:bg-[#1a5aa8] active:bg-[#15509a]
               text-white font-semibold text-[14px] transition-colors disabled:opacity-60
               flex items-center justify-center gap-2 shadow-sm shadow-[#206bc4]/30"
      >
        <i v-if="form.processing" class="fa-solid fa-spinner fa-spin text-[13px]"></i>
        {{ form.processing ? 'Signing in…' : 'Sign In' }}
      </button>

    </form>

    <!-- Footer link -->
    <p class="text-center text-[13px] text-gray-400 mt-7">
      Don't have an account?
      <a href="/public" class="text-[#206bc4] font-semibold hover:underline">
        Request Access
      </a>
    </p>

  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import GuestLayout from '@/layouts/GuestLayout.vue'

defineOptions({ layout: GuestLayout })

const props = defineProps({
  status: { type: String, default: null },
  lg:     { type: Object, default: null },
})

const showPassword = ref(false)

const form = useForm({
  email:    '',
  password: '',
  remember: false,
})

function submit() {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  })
}
</script>
