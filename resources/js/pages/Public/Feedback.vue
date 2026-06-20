<template>
  <div class="max-w-2xl mx-auto px-4 py-8">

      <!-- Header -->
      <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl
                    bg-gradient-to-br from-amber-400 to-orange-500 shadow-lg shadow-amber-200 mb-4">
          <i class="fa-solid fa-lightbulb text-white text-2xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-[#1d273b]">नागरिक सुझाव</h1>
        <p class="text-gray-500 mt-1.5 text-sm leading-relaxed">
          तपाईंको सुझावले हाम्रो तथ्याङ्क थप सटीक र उपयोगी बनाउँछ।<br>
          कृपया आफ्नो विचार साझा गर्नुहोस्।
        </p>
      </div>

      <!-- ── Success Screen ─────────────────────────────────────────── -->
      <div v-if="success" class="py-6">

        <!-- Animated check circle -->
        <div class="flex flex-col items-center mb-8">
          <div class="success-ring relative w-28 h-28 flex items-center justify-center mb-5">
            <!-- Outer pulse ring -->
            <span class="absolute inset-0 rounded-full bg-green-200 opacity-40 animate-ping" style="animation-duration:1.8s"></span>
            <!-- Circle -->
            <span class="relative w-24 h-24 rounded-full bg-gradient-to-br from-green-400 to-emerald-500
                         flex items-center justify-center shadow-lg shadow-green-200">
              <i class="fa-solid fa-check text-white text-[2rem] success-check"></i>
            </span>
            <!-- Sparkle dots -->
            <span class="absolute top-1 right-3 w-2.5 h-2.5 rounded-full bg-yellow-400 sparkle-1"></span>
            <span class="absolute bottom-2 left-1 w-2 h-2 rounded-full bg-blue-400 sparkle-2"></span>
            <span class="absolute top-4 left-0 w-1.5 h-1.5 rounded-full bg-pink-400 sparkle-3"></span>
          </div>

          <h2 class="text-2xl font-bold text-[#1d273b] mb-1">धन्यवाद! 🙏</h2>
          <p class="text-gray-400 text-sm text-center max-w-xs leading-relaxed">
            तपाईंको सुझाव सफलतापूर्वक प्राप्त भयो
          </p>
        </div>

        <!-- Submission summary card -->
        <div v-if="submitted.category" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-5">
          <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-3">तपाईंको सुझाव सारांश</p>
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div v-if="submitted.name">
              <p class="text-[11px] text-gray-400 mb-0.5">नाम</p>
              <p class="font-medium text-[#1d273b]">{{ submitted.name }}</p>
            </div>
            <div>
              <p class="text-[11px] text-gray-400 mb-0.5">विषय</p>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                {{ categories[submitted.category] ?? submitted.category }}
              </span>
            </div>
            <div v-if="submitted.ward_number">
              <p class="text-[11px] text-gray-400 mb-0.5">वडा</p>
              <p class="font-medium text-[#1d273b]">वडा नं. {{ submitted.ward_number }}</p>
            </div>
            <div>
              <p class="text-[11px] text-gray-400 mb-0.5">स्थिति</p>
              <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600">
                <i class="fa-solid fa-circle-check text-[10px]"></i> प्राप्त भयो
              </span>
            </div>
          </div>
        </div>

        <!-- What happens next -->
        <div class="bg-[#f0f7ff] rounded-2xl p-5 mb-6">
          <p class="text-[11px] font-bold text-[#206bc4] uppercase tracking-wide mb-3.5">
            <i class="fa-solid fa-info-circle mr-1"></i> अब के हुनेछ?
          </p>
          <div class="space-y-3">
            <div v-for="(step, i) in nextSteps" :key="i" class="flex items-start gap-3">
              <div class="w-6 h-6 rounded-full bg-[#206bc4] flex items-center justify-center shrink-0 mt-0.5">
                <span class="text-[10px] font-bold text-white">{{ i + 1 }}</span>
              </div>
              <p class="text-[13px] text-[#1d273b] leading-snug pt-0.5">{{ step }}</p>
            </div>
          </div>
        </div>

        <!-- Action buttons -->
        <div class="flex flex-col sm:flex-row gap-3">
          <button @click="reset"
            class="flex-1 py-3 rounded-xl bg-[#206bc4] text-white text-sm font-semibold
                   hover:bg-[#1a5aab] transition-colors flex items-center justify-center gap-2 shadow-md shadow-blue-100">
            <i class="fa-solid fa-pen-to-square text-xs"></i>
            अर्को सुझाव दिनुहोस्
          </button>
          <a href="/public"
            class="flex-1 py-3 rounded-xl border border-gray-200 bg-white text-gray-600 text-sm font-medium
                   hover:bg-gray-50 transition-colors flex items-center justify-center gap-2 no-underline">
            <i class="fa-solid fa-house text-xs text-gray-400"></i>
            गृह पृष्ठमा जानुहोस्
          </a>
        </div>

      </div>

      <!-- Feedback Form -->
      <form v-else @submit.prevent="submit"
        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8 space-y-5">

        <!-- Honeypot (hidden, never shown to humans) -->
        <div style="display:none" aria-hidden="true">
          <input v-model="form.website" name="website" tabindex="-1" autocomplete="off" />
        </div>

        <!-- Validation error banner (scroll-to-top on failure) -->
        <div v-if="hasErrors" ref="errorBanner"
          class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 flex items-start gap-2"
          role="alert" aria-live="assertive">
          <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0"></i>
          <span>कृपया तलका त्रुटिहरू सच्याउनुहोस् र पुनः पठाउनुहोस्।</span>
        </div>

        <!-- Name -->
        <div>
          <label for="fb-name" class="block text-sm font-medium text-gray-700 mb-1.5">
            पूरा नाम <span class="text-gray-400 font-normal text-xs">(वैकल्पिक)</span>
          </label>
          <input id="fb-name" v-model="form.name" type="text" maxlength="100" placeholder="तपाईंको नाम"
            class="w-full rounded-lg border border-gray-200 px-3.5 py-2.5 text-sm
                   focus:outline-none focus:ring-2 focus:ring-amber-300 focus:border-amber-400 transition" />
        </div>

        <!-- Phone + Email row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="fb-phone" class="block text-sm font-medium text-gray-700 mb-1.5">
              फोन नम्बर <span class="text-red-400">*</span>
              <span class="text-gray-400 font-normal text-xs">(वा इमेल)</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">
                <i class="fa-solid fa-phone"></i>
              </span>
              <input id="fb-phone" v-model="form.phone" type="tel" maxlength="15" placeholder="०१-XXXXXXXX"
                :class="['w-full rounded-lg border px-3.5 py-2.5 text-sm pl-9 transition',
                         'focus:outline-none focus:ring-2 focus:ring-amber-300 focus:border-amber-400',
                         errors.phone ? 'border-red-300 bg-red-50' : 'border-gray-200']" />
            </div>
            <p v-if="errors.phone" class="text-xs text-red-500 mt-1" role="alert">{{ errors.phone }}</p>
          </div>
          <div>
            <label for="fb-email" class="block text-sm font-medium text-gray-700 mb-1.5">
              इमेल ठेगाना <span class="text-gray-400 font-normal text-xs">(वैकल्पिक)</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">
                <i class="fa-solid fa-envelope"></i>
              </span>
              <input id="fb-email" v-model="form.email" type="email" maxlength="150" placeholder="example@email.com"
                class="w-full rounded-lg border border-gray-200 px-3.5 py-2.5 text-sm pl-9
                       focus:outline-none focus:ring-2 focus:ring-amber-300 focus:border-amber-400 transition" />
            </div>
          </div>
        </div>

        <!-- Ward + Category row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="fb-ward" class="block text-sm font-medium text-gray-700 mb-1.5">
              कुन वडाको बारेमा? <span class="text-gray-400 font-normal text-xs">(वैकल्पिक)</span>
            </label>
            <select id="fb-ward" v-model="form.ward_number"
              class="w-full rounded-lg border border-gray-200 px-3.5 py-2.5 text-sm
                     focus:outline-none focus:ring-2 focus:ring-amber-300 focus:border-amber-400 transition bg-white">
              <option value="">— वडा छान्नुहोस् —</option>
              <option v-for="w in wards" :key="w" :value="w">वडा नं. {{ w }}</option>
            </select>
          </div>
          <div>
            <label for="fb-category" class="block text-sm font-medium text-gray-700 mb-1.5">
              सुझावको विषय <span class="text-red-400">*</span>
            </label>
            <select id="fb-category" v-model="form.category"
              :class="['w-full rounded-lg border px-3.5 py-2.5 text-sm bg-white transition',
                       'focus:outline-none focus:ring-2 focus:ring-amber-300 focus:border-amber-400',
                       errors.category ? 'border-red-300 bg-red-50' : 'border-gray-200']">
              <option value="">— विषय छान्नुहोस् —</option>
              <option v-for="(label, key) in categories" :key="key" :value="key">
                {{ label }}
              </option>
            </select>
            <p v-if="errors.category" class="text-xs text-red-500 mt-1" role="alert">{{ errors.category }}</p>
          </div>
        </div>

        <!-- Message -->
        <div>
          <label for="fb-message" class="block text-sm font-medium text-gray-700 mb-1.5">
            तपाईंको सुझाव <span class="text-red-400">*</span>
          </label>
          <textarea id="fb-message" v-model="form.message" rows="5" maxlength="2000"
            placeholder="कृपया तपाईंको सुझाव विस्तारमा लेख्नुहोस्..."
            :class="['w-full rounded-lg border px-3.5 py-2.5 text-sm resize-none transition',
                     'focus:outline-none focus:ring-2 focus:ring-amber-300 focus:border-amber-400',
                     errors.message ? 'border-red-300 bg-red-50' : 'border-gray-200']"></textarea>
          <div class="flex justify-between mt-1">
            <p v-if="errors.message" class="text-xs text-red-500" role="alert">{{ errors.message }}</p>
            <p class="text-xs text-gray-400 ml-auto">{{ form.message.length }} / 2000</p>
          </div>
        </div>

        <!-- form_token / bot rejection error -->
        <div v-if="errors.form_token" class="rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-600"
          role="alert">
          <i class="fa-solid fa-circle-exclamation mr-1.5"></i>{{ errors.form_token }}
        </div>

        <!-- Privacy note -->
        <div class="rounded-lg bg-blue-50 border border-blue-100 px-4 py-3 flex gap-3">
          <i class="fa-solid fa-shield-halved text-blue-400 mt-0.5 shrink-0"></i>
          <p class="text-xs text-blue-600 leading-relaxed">
            तपाईंको व्यक्तिगत जानकारी सुरक्षित राखिनेछ र केवल प्रशासनिक उद्देश्यका लागि प्रयोग गरिनेछ।
          </p>
        </div>

        <!-- Submit -->
        <div class="pt-2">
          <button type="submit" :disabled="processing || cooldown > 0"
            class="w-full py-3 rounded-xl font-semibold text-sm transition-all duration-200
                   flex items-center justify-center gap-2"
            :class="processing || cooldown > 0
              ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
              : 'bg-gradient-to-r from-amber-500 to-orange-500 text-white hover:from-amber-400 hover:to-orange-400 shadow-md shadow-amber-200'">
            <i v-if="processing" class="fa-solid fa-spinner fa-spin"></i>
            <i v-else-if="cooldown > 0" class="fa-solid fa-clock"></i>
            <i v-else class="fa-solid fa-paper-plane"></i>
            <span v-if="cooldown > 0">{{ cooldown }} सेकेण्डमा सक्रिय हुनेछ...</span>
            <span v-else-if="processing">पठाउँदै...</span>
            <span v-else>सुझाव पठाउनुहोस्</span>
          </button>
        </div>

      </form>
    </div>
</template>

<script setup>
import { ref, computed, reactive, nextTick, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import PublicLayout from '@/layouts/PublicLayout.vue'

defineOptions({ layout: PublicLayout })

const props = defineProps({
  formToken:  { type: String, default: '' },
  categories: { type: Object, default: () => ({}) },
  wards:      { type: Array,  default: () => [] },
})

const processing  = ref(false)
const cooldown    = ref(0)
const success     = ref(false)
const errors      = ref({})
const submitted   = ref({})
const errorBanner = ref(null)

const hasErrors = computed(() => Object.keys(errors.value).length > 0)

const nextSteps = [
  'तपाईंको सुझाव हाम्रो प्रशासकीय टोलीलाई प्राप्त भएको छ।',
  'सम्बन्धित विभागले सुझावको समीक्षा गर्नेछ।',
  'आवश्यकताअनुसार तथ्याङ्क सुधार र अद्यावधिक गरिनेछ।',
]

const form = reactive({
  website:     '',    // honeypot
  name:        '',
  phone:       '',
  email:       '',
  ward_number: '',
  category:    '',
  message:     '',
  form_token:  props.formToken,
})

// 5-second cooldown so timing check (4 sec min) always passes
let timer = null
onMounted(() => {
  cooldown.value = 5
  timer = setInterval(() => {
    if (cooldown.value > 0) cooldown.value--
    else clearInterval(timer)
  }, 1000)
})


function reset() {
  success.value = false
  errors.value  = {}
  Object.assign(form, {
    website: '', name: '', phone: '', email: '',
    ward_number: '', category: '', message: '',
    form_token: props.formToken,
  })
  cooldown.value = 5
  timer = setInterval(() => {
    if (cooldown.value > 0) cooldown.value--
    else clearInterval(timer)
  }, 1000)
}

function submit() {
  if (processing.value || cooldown.value > 0) return
  errors.value = {}
  processing.value = true

  const snapshot = { ...form }

  router.post('/public/feedback', snapshot, {
    preserveScroll: true,
    onSuccess: () => {
      submitted.value = snapshot
      success.value   = true
    },
    onError: (errs) => {
      errors.value = errs
      nextTick(() => errorBanner.value?.scrollIntoView({ behavior: 'smooth', block: 'center' }))
    },
    onFinish: () => { processing.value = false },
  })
}
</script>

<style scoped>
@keyframes pop-in {
  0%   { transform: scale(0.4); opacity: 0; }
  70%  { transform: scale(1.12); }
  100% { transform: scale(1);   opacity: 1; }
}
@keyframes sparkle {
  0%,100% { transform: scale(1) translateY(0);   opacity: 1; }
  50%      { transform: scale(1.4) translateY(-4px); opacity: 0.7; }
}
.success-check  { animation: pop-in  0.5s cubic-bezier(0.36,0.07,0.19,0.97) 0.1s both; }
.sparkle-1      { animation: sparkle 1.4s ease-in-out 0.4s infinite; }
.sparkle-2      { animation: sparkle 1.6s ease-in-out 0.6s infinite; }
.sparkle-3      { animation: sparkle 1.8s ease-in-out 0.2s infinite; }
</style>
