<template>
  <div class="max-w-3xl space-y-4">
    <div>
      <h1 class="text-[15px] font-semibold text-[#1d273b]">मोड्युलहरू</h1>
      <p class="text-[11px] text-[#9aa0ac] mt-0.5">
        Optional modules — switch on only what this municipality uses. Every module starts off.
      </p>
    </div>

    <div v-for="m in modules" :key="m.key" class="bg-white rounded-md border border-[#e6e7e9] shadow-sm">
      <!-- Header with on/off -->
      <div class="flex items-start gap-3 p-4 border-b border-[#e6e7e9]">
        <div class="w-10 h-10 rounded-md flex items-center justify-center shrink-0"
          :class="forms[m.key].enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-400'">
          <i :class="m.icon"></i>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-[14px] font-semibold text-[#1d273b]">{{ m.label }}</p>
          <p class="text-[12px] text-[#626976] mt-0.5">{{ m.description }}</p>
        </div>
        <button type="button" @click="forms[m.key].enabled = !forms[m.key].enabled"
          class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors"
          :class="forms[m.key].enabled ? 'bg-emerald-600' : 'bg-gray-300'"
          :title="forms[m.key].enabled ? 'सक्रिय' : 'बन्द'">
          <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
            :class="forms[m.key].enabled ? 'translate-x-6' : 'translate-x-1'"></span>
        </button>
      </div>

      <!-- श्रमदान settings -->
      <div v-if="m.key === 'shramdaan'" class="p-4 space-y-4">
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <label class="block">
            <span class="text-[11px] font-semibold text-gray-600">१ दिन = कति घण्टा</span>
            <input type="number" step="0.5" min="1" max="24" v-model.number="forms[m.key].settings.hours_per_day" :class="input" />
          </label>
          <label class="block">
            <span class="text-[11px] font-semibold text-gray-600">पासबुकमा सदस्य (न्यूनतम उमेर)</span>
            <input type="number" min="0" max="100" v-model.number="forms[m.key].settings.min_age" :class="input" />
          </label>
        </div>

        <div>
          <p class="text-[11px] font-semibold text-gray-600 mb-1">श्रेणी — एक आर्थिक वर्षमा प्रमाणित श्रमदान दिन</p>
          <div class="grid grid-cols-3 gap-3">
            <label class="block">
              <span class="text-[11px] text-amber-600">🥇 स्वर्ण (कम्तीमा)</span>
              <input type="number" step="0.5" min="0" v-model.number="forms[m.key].settings.tier_gold" :class="input" />
            </label>
            <label class="block">
              <span class="text-[11px] text-slate-500">🥈 रजत</span>
              <input type="number" step="0.5" min="0" v-model.number="forms[m.key].settings.tier_silver" :class="input" />
            </label>
            <label class="block">
              <span class="text-[11px] text-amber-800">🥉 कांस्य</span>
              <input type="number" step="0.5" min="0" v-model.number="forms[m.key].settings.tier_bronze" :class="input" />
            </label>
          </div>
        </div>

        <label class="block">
          <span class="text-[11px] font-semibold text-gray-600">प्रोत्साहन सिफारिस पाठ (श्रेणी पुगेमा मात्र पासबुकमा छापिन्छ)</span>
          <textarea rows="3" maxlength="600" v-model="forms[m.key].settings.incentive_text" :class="input"
            placeholder="उदा: पालिकाको श्रमदान कार्यविधि बमोजिम वडा सिफारिस शुल्कमा छुटका लागि योग्य। खाली छोडेमा छापिँदैन।"></textarea>
        </label>

        <p v-for="(msg, field) in forms[m.key].errors" :key="field" class="text-xs text-red-500">{{ msg }}</p>
      </div>

      <div class="flex items-center justify-end gap-3 px-4 py-3 bg-gray-50/60 border-t border-[#e6e7e9]">
        <span v-if="forms[m.key].enabled !== m.enabled" class="text-[11px] text-amber-600 mr-auto">
          {{ forms[m.key].enabled ? 'सुरक्षित गरेपछि सक्रिय हुनेछ।' : 'सुरक्षित गरेपछि बन्द हुनेछ — टोल संयोजकहरूले पहुँच गुमाउनेछन्।' }}
        </span>
        <button type="button" @click="save(m.key)" :disabled="forms[m.key].processing"
          class="inline-flex items-center gap-2 h-9 px-4 bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-50 text-white text-[13px] font-medium rounded">
          <i :class="forms[m.key].processing ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-floppy-disk'" class="text-[11px]"></i>
          सुरक्षित गर्नुहोस्
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  modules: { type: Array, default: () => [] },
})

const input = 'mt-1 w-full px-3 py-2 rounded-md border border-[#e6e7e9] text-sm focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4]'

const forms = reactive(Object.fromEntries(
  props.modules.map(m => [m.key, useForm({ enabled: m.enabled, settings: { ...m.settings } })])
))

function save(key) {
  forms[key].patch(`/admin/modules/${key}`, { preserveScroll: true })
}
</script>
