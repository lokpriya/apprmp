<template>
  <div class="max-w-3xl mx-auto space-y-3 pb-8">
    <div>
      <Link href="/shramdaan" class="text-[12px] text-[#206bc4] no-underline"><i class="fa-solid fa-chevron-left text-[10px]"></i> श्रमदान</Link>
      <h1 class="text-[15px] font-semibold text-[#1d273b] mt-1">टोल संयोजकहरू</h1>
      <p class="text-[11px] text-[#9aa0ac]">
        तपाईंको वडाका टोलमा मात्र तोक्न सकिन्छ। खाता निष्क्रिय गर्ने वा पासवर्ड रिसेट गर्ने काम पालिकाको प्रशासकले गर्छन्।
      </p>
    </div>

    <!-- Existing coordinators -->
    <div v-for="c in coordinators" :key="c.id" class="bg-white border border-[#e6e7e9] rounded-md p-3">
      <div class="flex items-start gap-2">
        <div class="flex-1 min-w-0">
          <p class="text-[14px] font-semibold text-[#1d273b]">
            {{ c.name }}
            <span v-if="!c.is_active" class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">निष्क्रिय</span>
          </p>
          <p class="text-[12px] text-[#626976]">{{ c.email }}<template v-if="c.phone"> · {{ c.phone }}</template></p>
          <p v-if="c.other_toles.length" class="text-[11px] text-[#9aa0ac]">अन्य वडामा: {{ c.other_toles.join(', ') }}</p>
        </div>
        <button type="button" @click="openEdit(c)"
          class="h-8 px-3 border border-[#dadcde] rounded text-[12px] shrink-0">
          {{ editing === c.id ? 'बन्द' : 'टोल तोक्नुहोस्' }}
        </button>
      </div>
      <div class="flex flex-wrap gap-1 mt-2">
        <span v-for="id in c.my_tole_ids" :key="id" class="text-[11px] px-2 py-0.5 rounded bg-emerald-50 text-emerald-700">{{ toleName(id) }}</span>
        <span v-if="!c.my_tole_ids.length" class="text-[11px] text-amber-600">तपाईंको वडामा कुनै टोल तोकिएको छैन</span>
      </div>
      <div v-if="editing === c.id" class="mt-3 pt-3 border-t border-[#eef0f2]">
        <TolePicker v-model="toleEdits[c.id]" :toles="toles" />
        <button type="button" @click="saveToles(c)"
          class="mt-2 w-full h-10 bg-[#206bc4] hover:bg-[#1a5ba8] text-white rounded-md text-[13px] font-semibold">
          सुरक्षित गर्नुहोस्
        </button>
      </div>
    </div>
    <p v-if="!coordinators.length" class="text-center py-6 bg-white border border-[#e6e7e9] rounded-md text-[13px] text-[#9aa0ac]">
      तपाईंको वडामा अहिलेसम्म कुनै टोल संयोजक छैन।
    </p>

    <!-- New coordinator -->
    <form @submit.prevent="create" class="bg-white border border-[#e6e7e9] rounded-md p-4 space-y-2">
      <p class="text-[13px] font-semibold text-[#1d273b]"><i class="fa-solid fa-user-plus text-emerald-600"></i> नयाँ टोल संयोजक</p>
      <input v-model="form.name" required maxlength="200" placeholder="पूरा नाम" :class="input" />
      <div class="grid grid-cols-2 gap-2">
        <input v-model="form.email" required type="email" placeholder="इमेल (लगइनका लागि)" :class="input" />
        <input v-model="form.phone" maxlength="15" placeholder="मोबाइल" :class="input" />
      </div>
      <input v-model="form.password" required type="password" minlength="8" autocomplete="new-password" placeholder="पासवर्ड (कम्तीमा ८ अक्षर)" :class="input" />
      <TolePicker v-model="form.settlement_ids" :toles="toles" />
      <p v-for="(msg, k) in form.errors" :key="k" class="text-xs text-red-500">{{ msg }}</p>
      <button type="submit" :disabled="form.processing"
        class="w-full h-11 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-md text-[14px] font-semibold">
        संयोजक थप्नुहोस्
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, h } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'

const props = defineProps({
  coordinators: { type: Array, default: () => [] },
  toles:        { type: Array, default: () => [] },
})

const input = 'w-full h-10 px-3 border border-[#dadcde] rounded-md text-[14px] bg-white focus:outline-none focus:border-[#206bc4]'

/** Checkbox list of toles, grouped by ward. */
const TolePicker = {
  props: { modelValue: Array, toles: Array },
  emits: ['update:modelValue'],
  setup(p, { emit }) {
    const toggle = (id) => {
      const set = new Set(p.modelValue ?? [])
      set.has(id) ? set.delete(id) : set.add(id)
      emit('update:modelValue', [...set])
    }
    return () => {
      const wards = [...new Set(p.toles.map(t => t.ward_number))]
      return h('div', { class: 'space-y-2' }, wards.map(w => h('div', [
        h('p', { class: 'text-[11px] font-semibold text-[#626976] mb-1' }, `वडा ${w}`),
        h('div', { class: 'grid grid-cols-2 gap-1.5' }, p.toles.filter(t => t.ward_number === w).map(t =>
          h('label', {
            class: ['flex items-center gap-2 p-2 rounded border text-[13px] cursor-pointer',
              (p.modelValue ?? []).includes(t.id) ? 'border-emerald-300 bg-emerald-50' : 'border-[#e6e7e9]'],
          }, [
            h('input', { type: 'checkbox', class: 'w-4 h-4', checked: (p.modelValue ?? []).includes(t.id), onChange: () => toggle(t.id) }),
            h('span', { class: 'truncate' }, t.name),
          ]))),
      ])))
    }
  },
}

const toleName = (id) => props.toles.find(t => t.id === id)?.name ?? id

const editing   = ref(null)
const toleEdits = reactive({})

function openEdit(c) {
  if (editing.value === c.id) { editing.value = null; return }
  toleEdits[c.id] = [...c.my_tole_ids]   // fresh from the server each time it opens
  editing.value = c.id
}

function saveToles(c) {
  router.patch(`/shramdaan/coordinators/${c.id}/toles`, { settlement_ids: toleEdits[c.id] }, {
    preserveScroll: true, onSuccess: () => { editing.value = null },
  })
}

const form = useForm({ name: '', email: '', phone: '', password: '', settlement_ids: [] })
function create() {
  form.post('/shramdaan/coordinators', { preserveScroll: true, onSuccess: () => form.reset() })
}
</script>
