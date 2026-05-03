<template>
  <div class="max-w-xl mx-auto py-8 px-4 space-y-5">

      <!-- Header -->
      <div>
        <h1 class="text-lg font-bold text-gray-900">दुई-चरण प्रमाणीकरण (2FA)</h1>
        <p class="text-xs text-gray-500 mt-0.5">Two-Factor Authentication</p>
      </div>

      <!-- Forced-setup warning for admins -->
      <div v-if="props.twoFactorRequired && !props.twoFactorEnabled"
        class="flex items-start gap-3 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg">
        <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5 shrink-0"></i>
        <div>
          <p class="text-[13px] font-semibold text-amber-800">2FA अनिवार्य छ</p>
          <p class="text-[12px] text-amber-700 mt-0.5">
            तपाईंको भूमिकाका लागि 2FA सक्रिय गर्नु अनिवार्य छ। सेटअप नगरी अगाडि बढ्न सकिँदैन।
          </p>
        </div>
      </div>

      <!-- Already enabled — management card -->
      <template v-if="props.twoFactorEnabled && !setupMode">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-shield-check text-emerald-600"></i>
            </div>
            <div>
              <p class="text-[14px] font-bold text-gray-900">2FA सक्रिय छ</p>
              <p class="text-[12px] text-gray-500">Google Authenticator मार्फत सुरक्षित</p>
            </div>
          </div>

          <hr class="border-gray-100" />

          <!-- Regenerate recovery codes -->
          <div>
            <p class="text-[13px] font-semibold text-gray-700 mb-1">रिकभरी कोडहरू</p>
            <p class="text-[12px] text-gray-500 mb-3">
              रिकभरी कोडहरू फोन हराएमा प्रयोग गर्न सकिन्छ। प्रत्येक कोड एकपटक मात्र प्रयोग हुन्छ।
            </p>
            <div v-if="recoveryCodes.length" class="grid grid-cols-2 gap-2 mb-3">
              <code v-for="rc in recoveryCodes" :key="rc"
                class="block bg-gray-50 border border-gray-200 rounded px-3 py-1.5 font-mono text-[12px] text-gray-700 text-center">
                {{ rc }}
              </code>
            </div>
            <div class="flex gap-2">
              <input v-model="regenPassword" type="password" placeholder="हालको पासवर्ड"
                class="field-input flex-1" />
              <button type="button" @click="regenerateCodes" :disabled="regenLoading"
                class="inline-flex items-center gap-2 h-10 px-4 bg-[#1d273b] hover:bg-[#2d3a4f] text-white text-[12px] font-medium rounded-md transition-colors disabled:opacity-50 shrink-0">
                <i v-if="regenLoading" class="fa-solid fa-spinner fa-spin text-[10px]"></i>
                <i v-else class="fa-solid fa-rotate text-[10px]"></i>
                नयाँ कोडहरू
              </button>
            </div>
            <p v-if="regenError" class="text-xs text-red-500 mt-1.5">{{ regenError }}</p>
          </div>

          <hr class="border-gray-100" />

          <!-- Disable -->
          <div>
            <p class="text-[13px] font-semibold text-red-700 mb-1">2FA निष्क्रिय गर्नुहोस्</p>
            <p class="text-[12px] text-gray-500 mb-3">
              निष्क्रिय गरेपछि लगइनमा कोड माग्दैन। सुरक्षाका लागि सक्रिय राख्नुस्।
            </p>
            <div class="flex gap-2">
              <input v-model="disablePassword" type="password" placeholder="हालको पासवर्ड"
                class="field-input flex-1" />
              <button type="button" @click="disable2fa" :disabled="disableLoading"
                class="inline-flex items-center gap-2 h-10 px-4 bg-red-600 hover:bg-red-700 text-white text-[12px] font-medium rounded-md transition-colors disabled:opacity-50 shrink-0">
                <i v-if="disableLoading" class="fa-solid fa-spinner fa-spin text-[10px]"></i>
                <i v-else class="fa-solid fa-shield-xmark text-[10px]"></i>
                निष्क्रिय गर्नुहोस्
              </button>
            </div>
            <p v-if="disableError" class="text-xs text-red-500 mt-1.5">{{ disableError }}</p>
          </div>
        </div>
      </template>

      <!-- Setup wizard -->
      <template v-else>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">

          <!-- Step 1: QR code -->
          <template v-if="step === 1">
            <h2 class="text-[14px] font-bold text-gray-900 mb-1">चरण १ — QR कोड स्क्यान गर्नुहोस्</h2>
            <p class="text-[12px] text-gray-500 mb-4">
              Google Authenticator एप खोल्नुहोस् → + थिच्नुहोस् → QR कोड स्क्यान गर्नुहोस्।
            </p>

            <div v-if="!qrSvg" class="flex flex-col items-center py-8 gap-3">
              <button type="button" @click="startSetup" :disabled="setupLoading"
                class="inline-flex items-center gap-2 h-10 px-6 bg-violet-600 hover:bg-violet-700 text-white text-[13px] font-semibold rounded-md transition-colors disabled:opacity-50">
                <i v-if="setupLoading" class="fa-solid fa-spinner fa-spin text-[11px]"></i>
                <i v-else class="fa-solid fa-qrcode text-[11px]"></i>
                {{ setupLoading ? 'तयार हुँदैछ...' : 'सुरु गर्नुहोस्' }}
              </button>
              <p v-if="setupError" class="text-xs text-red-500 text-center">
                <i class="fa-solid fa-circle-xmark mr-1"></i>{{ setupError }}
              </p>
            </div>

            <template v-else>
              <!-- QR image -->
              <div class="flex justify-center mb-4">
                <div class="p-3 bg-white border-2 border-gray-200 rounded-xl inline-block">
                  <img :src="'data:image/svg+xml;base64,' + qrSvg" alt="QR Code" class="w-48 h-48" />
                </div>
              </div>

              <!-- Manual entry -->
              <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 mb-5">
                <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">म्यानुअल प्रविष्टि कोड</p>
                <div class="flex items-center gap-2">
                  <code class="font-mono text-[13px] text-gray-800 tracking-widest flex-1">{{ secretFormatted }}</code>
                  <button type="button" @click="copySecret"
                    class="px-2 py-1 border border-gray-200 rounded text-[10px] transition-colors"
                    :class="secretCopied ? 'text-emerald-600 border-emerald-300 bg-emerald-50' : 'text-gray-500 hover:bg-gray-100'">
                    <i :class="secretCopied ? 'fa-solid fa-check' : 'fa-solid fa-copy'"></i>
                  </button>
                </div>
              </div>

              <button type="button" @click="step = 2"
                class="w-full h-10 bg-violet-600 hover:bg-violet-700 text-white text-[13px] font-semibold rounded-md transition-colors flex items-center justify-center gap-2">
                <i class="fa-solid fa-arrow-right text-[11px]"></i>
                स्क्यान भयो — अगाडि बढ्नुहोस्
              </button>
            </template>
          </template>

          <!-- Step 2: Confirm code -->
          <template v-else-if="step === 2">
            <h2 class="text-[14px] font-bold text-gray-900 mb-1">चरण २ — कोड प्रमाणित गर्नुहोस्</h2>
            <p class="text-[12px] text-gray-500 mb-4">
              Google Authenticator एपमा देखिएको 6-अङ्कीय कोड प्रविष्ट गर्नुहोस्।
            </p>
            <div class="space-y-3">
              <input v-model="confirmCode" type="text" maxlength="6" placeholder="000000"
                autocomplete="one-time-code" autofocus
                class="w-full px-4 py-3 border rounded-md text-center font-mono text-xl tracking-widest focus:ring-2 focus:ring-violet-500/30 focus:border-violet-500 transition-colors"
                :class="confirmError ? 'border-red-400 bg-red-50' : 'border-gray-200'"
                @keyup.enter="confirmSetup" />
              <p v-if="confirmError" class="text-xs text-red-500 text-center">{{ confirmError }}</p>
              <button type="button" @click="confirmSetup" :disabled="confirmLoading"
                class="w-full h-10 bg-violet-600 hover:bg-violet-700 text-white text-[13px] font-semibold rounded-md transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
                <i v-if="confirmLoading" class="fa-solid fa-spinner fa-spin text-[11px]"></i>
                <i v-else class="fa-solid fa-shield-check text-[11px]"></i>
                {{ confirmLoading ? 'प्रमाणित हुँदैछ...' : '2FA सक्रिय गर्नुहोस्' }}
              </button>
              <button type="button" @click="step = 1"
                class="w-full text-[12px] text-gray-400 hover:text-gray-600 transition-colors">
                ← पछाडि जानुहोस्
              </button>
            </div>
          </template>

          <!-- Step 3: Recovery codes -->
          <template v-else-if="step === 3">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-shield-check text-emerald-600"></i>
              </div>
              <div>
                <p class="text-[14px] font-bold text-emerald-700">2FA सफलतापूर्वक सक्रिय भयो!</p>
              </div>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-4">
              <p class="text-[12px] font-semibold text-amber-800 mb-1">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                रिकभरी कोडहरू सुरक्षित ठाउँमा राख्नुहोस्!
              </p>
              <p class="text-[11px] text-amber-700">
                फोन हराएमा यी कोडहरू प्रयोग गर्न सकिन्छ। प्रत्येक कोड एकपटक मात्र काम गर्छ।
              </p>
            </div>

            <div class="grid grid-cols-2 gap-2 mb-4">
              <code v-for="rc in recoveryCodes" :key="rc"
                class="block bg-gray-50 border border-gray-200 rounded px-3 py-1.5 font-mono text-[12px] text-gray-700 text-center">
                {{ rc }}
              </code>
            </div>

            <button type="button" @click="copyCodes"
              class="w-full h-9 border border-gray-200 hover:bg-gray-50 text-gray-600 text-[12px] font-medium rounded-md transition-colors flex items-center justify-center gap-2 mb-3">
              <i :class="codesCopied ? 'fa-solid fa-check text-emerald-600' : 'fa-solid fa-copy'"></i>
              {{ codesCopied ? 'कपि भयो!' : 'सबै कोड कपि गर्नुहोस्' }}
            </button>

            <a :href="dashboardUrl"
              class="w-full h-10 bg-violet-600 hover:bg-violet-700 text-white text-[13px] font-semibold rounded-md transition-colors flex items-center justify-center gap-2">
              <i class="fa-solid fa-arrow-right-to-bracket text-[11px]"></i>
              डास्बोर्डमा जानुहोस्
            </a>
          </template>

        </div>
      </template>

    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
  twoFactorEnabled:  { type: Boolean, default: false },
  twoFactorRequired: { type: Boolean, default: false },
  lg:                { type: Object,  default: null },
})

const dashboardUrl = '/dashboard'

// Setup wizard
const setupMode     = ref(false)
const step          = ref(1)
const qrSvg         = ref('')
const secret        = ref('')
const setupLoading  = ref(false)
const setupError    = ref('')
const confirmCode   = ref('')
const confirmError  = ref('')
const confirmLoading = ref(false)
const recoveryCodes  = ref([])
const secretCopied  = ref(false)
const codesCopied   = ref(false)

const secretFormatted = computed(() =>
  secret.value.match(/.{1,4}/g)?.join(' ') ?? ''
)

async function startSetup() {
  setupLoading.value = true
  setupError.value   = ''
  try {
    const { data } = await axios.post('/two-factor/setup')
    qrSvg.value  = data.qr_svg
    secret.value = data.secret
  } catch (e) {
    setupError.value = e.response?.data?.message ?? 'सर्भर त्रुटि भयो। पुनः प्रयास गर्नुहोस्।'
  } finally {
    setupLoading.value = false
  }
}

function copySecret() {
  navigator.clipboard.writeText(secret.value).then(() => {
    secretCopied.value = true
    setTimeout(() => { secretCopied.value = false }, 2000)
  })
}

async function confirmSetup() {
  if (!confirmCode.value || confirmCode.value.length !== 6) {
    confirmError.value = '6-अङ्कीय कोड प्रविष्ट गर्नुहोस्।'
    return
  }
  confirmLoading.value = true
  confirmError.value   = ''
  try {
    const { data } = await axios.post('/two-factor/confirm', { code: confirmCode.value })
    recoveryCodes.value = data.recovery_codes
    step.value = 3
  } catch (e) {
    confirmError.value = e.response?.data?.errors?.code ?? e.response?.data?.message ?? 'त्रुटि भयो।'
  } finally {
    confirmLoading.value = false
  }
}

function copyCodes() {
  navigator.clipboard.writeText(recoveryCodes.value.join('\n')).then(() => {
    codesCopied.value = true
    setTimeout(() => { codesCopied.value = false }, 2000)
  })
}

// Disable 2FA
const disablePassword = ref('')
const disableError    = ref('')
const disableLoading  = ref(false)

async function disable2fa() {
  if (!disablePassword.value) { disableError.value = 'पासवर्ड आवश्यक छ।'; return }
  disableLoading.value = true
  disableError.value   = ''
  try {
    await axios.post('/two-factor/disable', { password: disablePassword.value })
    window.location.reload()
  } catch (e) {
    disableError.value = e.response?.data?.errors?.password ?? e.response?.data?.message ?? 'त्रुटि भयो।'
  } finally {
    disableLoading.value = false
    disablePassword.value = ''
  }
}

// Regenerate recovery codes
const regenPassword = ref('')
const regenError    = ref('')
const regenLoading  = ref(false)

async function regenerateCodes() {
  if (!regenPassword.value) { regenError.value = 'पासवर्ड आवश्यक छ।'; return }
  regenLoading.value = true
  regenError.value   = ''
  try {
    const { data } = await axios.post('/two-factor/recovery-codes', { password: regenPassword.value })
    recoveryCodes.value = data.recovery_codes
    regenPassword.value = ''
  } catch (e) {
    regenError.value = e.response?.data?.errors?.password ?? e.response?.data?.message ?? 'त्रुटि भयो।'
  } finally {
    regenLoading.value = false
  }
}
</script>

<style scoped>
.field-input { @apply w-full px-3 py-2 rounded-md border border-gray-200 text-sm focus:ring-2 focus:ring-violet-500/30 focus:border-violet-500 transition-colors; }
</style>
