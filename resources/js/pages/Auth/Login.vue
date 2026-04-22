<template>
  <div class="bg-white rounded-md border border-[#e6e7e9] overflow-hidden w-full max-w-sm">

    <!-- Card header -->
    <div class="bg-[#182433] px-6 py-6 text-center">
      <div class="w-16 h-16 rounded bg-[#206bc4]/80 flex items-center justify-center mx-auto mb-3">
        <i class="fa-solid fa-map-location-dot text-white text-3xl"></i>
      </div>
      <h1 class="text-white font-semibold text-[15px] leading-tight">{{ props.lg?.name_np ?? 'APPRMP' }}</h1>
      <p v-if="props.lg?.name_en" class="text-white/70 text-[12px] mt-1">{{ props.lg.name_en }}</p>
      <span class="inline-flex items-center gap-1.5 mt-2 px-3 py-1 rounded bg-white/10 text-white/80 text-[11px] font-medium">
        <i class="fa-solid fa-shield-halved text-[10px]"></i>
        Staff Portal — Sign In
      </span>
    </div>

    <!-- Card body -->
    <div class="px-6 py-6">

      <!-- Status message -->
      <div v-if="props.status" class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-md text-[13px] flex items-center gap-2">
        <i class="fa-solid fa-circle-check shrink-0"></i>
        {{ props.status }}
      </div>

      <form @submit.prevent="submit" class="space-y-4">

        <!-- Email -->
        <div>
          <label class="block text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.12em] mb-1">
            इमेल ठेगाना
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-[#9aa0ac] pointer-events-none">
              <i class="fa-solid fa-envelope text-sm"></i>
            </span>
            <input
              v-model="form.email"
              type="email"
              required
              autofocus
              autocomplete="username"
              placeholder="staff@municipality.gov.np"
              class="w-full pl-9 pr-4 h-9 rounded border border-[#e6e7e9] text-[13px] focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] transition outline-none"
              :class="{ 'border-red-400 focus:ring-red-400 focus:border-red-400': form.errors.email }"
            />
          </div>
          <p v-if="form.errors.email" class="text-[11px] text-red-500 mt-1">{{ form.errors.email }}</p>
        </div>

        <!-- Password -->
        <div>
          <label class="block text-[11px] font-semibold text-[#9aa0ac] uppercase tracking-[0.12em] mb-1">
            पासवर्ड
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-[#9aa0ac] pointer-events-none">
              <i class="fa-solid fa-lock text-sm"></i>
            </span>
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              required
              autocomplete="current-password"
              placeholder="••••••••"
              class="w-full pl-9 pr-10 h-9 rounded border border-[#e6e7e9] text-[13px] focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4] transition outline-none"
              :class="{ 'border-red-400 focus:ring-red-400 focus:border-red-400': form.errors.password }"
            />
            <button type="button" @click="showPassword = !showPassword"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#9aa0ac] hover:text-[#626976] transition-colors">
              <i :class="showPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'" class="text-sm"></i>
            </button>
          </div>
          <p v-if="form.errors.password" class="text-[11px] text-red-500 mt-1">{{ form.errors.password }}</p>
        </div>

        <!-- Remember me -->
        <div class="flex items-center justify-between">
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input v-model="form.remember" type="checkbox" class="rounded text-[#206bc4] focus:ring-[#206bc4] w-4 h-4" />
            <span class="text-[13px] text-[#626976]">याद राख्नुहोस्</span>
          </label>
        </div>

        <!-- Submit -->
        <button
          type="submit"
          :disabled="form.processing"
          class="w-full h-9 px-4 bg-[#206bc4] hover:bg-[#1a5ba8] text-white font-semibold rounded text-[13px] disabled:opacity-60 transition flex items-center justify-center gap-2"
        >
          <i v-if="form.processing" class="fa-solid fa-spinner fa-spin text-sm"></i>
          <i v-else class="fa-solid fa-right-to-bracket text-sm"></i>
          {{ form.processing ? 'साइन इन हुँदैछ...' : 'साइन इन' }}
        </button>

      </form>
    </div>

    <!-- Card footer -->
    <div class="px-6 py-3 bg-gray-50 border-t border-[#e6e7e9] text-center">
      <a href="/public" class="inline-flex items-center gap-1.5 text-[12px] text-[#206bc4] hover:text-[#1a5ba8] font-medium transition-colors">
        <i class="fa-solid fa-globe text-[10px]"></i>
        सार्वजनिक पोर्टल हेर्नुहोस्
      </a>
    </div>

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
