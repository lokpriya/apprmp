<template>
  <div class="no-print bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">
    <!-- Header -->
    <div class="flex items-center gap-2 px-4 py-2.5 border-b border-[#e6e7e9] bg-gray-50">
      <i class="fa-solid fa-clipboard-check text-emerald-500 text-xs"></i>
      <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide flex-1">क्षेत्र प्रमाणीकरण</h3>
      <!-- Status chip -->
      <span v-if="verification && verification.is_verified"
        class="text-[10px] px-2 py-0.5 rounded-full font-semibold bg-green-100 text-green-700">
        <i class="fa-solid fa-circle-check mr-0.5"></i> प्रमाणित
      </span>
      <span v-else-if="verification && !verification.is_verified"
        class="text-[10px] px-2 py-0.5 rounded-full font-semibold bg-amber-100 text-amber-700">
        <i class="fa-solid fa-triangle-exclamation mr-0.5"></i> समस्या फेला
      </span>
      <span v-else
        class="text-[10px] px-2 py-0.5 rounded-full font-semibold bg-gray-100 text-gray-500">
        अप्रमाणित
      </span>
    </div>

    <div class="p-4">
      <!-- Saved verification details -->
      <div v-if="verification && !editing" class="space-y-2">
        <div class="grid grid-cols-2 gap-2 text-xs">
          <div>
            <p class="text-gray-400">भ्रमण मिति</p>
            <p class="font-medium text-gray-700">{{ verification.visited_at }}</p>
          </div>
          <div>
            <p class="text-gray-400">प्रमाणकर्ता</p>
            <p class="font-medium text-gray-700">{{ verification.verifier?.name || '—' }}</p>
          </div>
        </div>
        <div v-if="verification.notes" class="mt-2 p-2.5 rounded bg-gray-50 border border-gray-100">
          <p class="text-[10px] text-gray-400 mb-0.5">टिप्पणी</p>
          <p class="text-xs text-gray-700 leading-relaxed">{{ verification.notes }}</p>
        </div>
        <!-- Update button (verifier/admin only) -->
        <button v-if="canVerify" @click="startEdit"
          class="mt-2 flex items-center gap-1.5 text-xs text-[#206bc4] hover:underline">
          <i class="fa-solid fa-pen text-[10px]"></i> अद्यावधिक गर्नुस्
        </button>
      </div>

      <!-- Empty state -->
      <div v-else-if="!verification && !editing" class="text-center py-4">
        <i class="fa-solid fa-clipboard text-gray-300 text-2xl mb-2"></i>
        <p class="text-xs text-gray-400">अझै क्षेत्र भ्रमण गरिएको छैन।</p>
        <button v-if="canVerify" @click="startEdit"
          class="mt-3 flex items-center gap-2 mx-auto rounded-md bg-emerald-600 hover:bg-emerald-700 px-3 py-1.5 text-xs font-medium text-white transition-colors">
          <i class="fa-solid fa-clipboard-check"></i> प्रमाणीकरण थप्नुस्
        </button>
      </div>

      <!-- Form -->
      <form v-if="editing" @submit.prevent="submit" class="space-y-3">
        <!-- Visited date -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">भ्रमण मिति <span class="text-red-400">*</span></label>
          <input
            v-model="form.visited_at"
            type="date"
            :max="today"
            required
            class="w-full rounded border border-gray-200 px-2.5 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-300"
          />
        </div>

        <!-- Verified toggle -->
        <div>
          <p class="text-xs font-medium text-gray-600 mb-1.5">प्रमाणीकरण अवस्था</p>
          <div class="flex gap-2">
            <button type="button"
              @click="form.is_verified = true"
              :class="['flex-1 flex items-center justify-center gap-1.5 rounded border px-2 py-2 text-xs font-medium transition-colors',
                form.is_verified === true
                  ? 'bg-green-50 border-green-400 text-green-700'
                  : 'border-gray-200 text-gray-500 hover:bg-gray-50']">
              <i class="fa-solid fa-circle-check"></i> प्रमाणित
            </button>
            <button type="button"
              @click="form.is_verified = false"
              :class="['flex-1 flex items-center justify-center gap-1.5 rounded border px-2 py-2 text-xs font-medium transition-colors',
                form.is_verified === false
                  ? 'bg-amber-50 border-amber-400 text-amber-700'
                  : 'border-gray-200 text-gray-500 hover:bg-gray-50']">
              <i class="fa-solid fa-triangle-exclamation"></i> समस्या फेला
            </button>
          </div>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">टिप्पणी</label>
          <textarea
            v-model="form.notes"
            rows="3"
            placeholder="क्षेत्र भ्रमणका टिप्पणी वा समस्याहरू..."
            class="w-full rounded border border-gray-200 px-2.5 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-300 resize-none"
          ></textarea>
        </div>

        <!-- Actions -->
        <div class="flex gap-2 pt-1">
          <button type="button" @click="cancelEdit"
            class="flex-1 rounded border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">
            रद्द
          </button>
          <button type="submit" :disabled="saving || form.is_verified === null"
            class="flex-1 rounded bg-emerald-600 hover:bg-emerald-700 px-3 py-1.5 text-xs font-medium text-white transition-colors disabled:opacity-60">
            <i :class="saving ? 'fa-solid fa-spinner fa-spin mr-1' : 'fa-solid fa-floppy-disk mr-1'"></i>
            सुरक्षित
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  verification: { type: Object, default: null },
  canVerify:    { type: Boolean, default: false },
  saveUrl:      { type: String, required: true },
})

const today   = new Date().toISOString().slice(0, 10)
const editing = ref(false)
const saving  = ref(false)

const form = reactive({
  visited_at:  '',
  is_verified: null,
  notes:       '',
})

function startEdit() {
  form.visited_at  = props.verification?.visited_at  ?? today
  form.is_verified = props.verification?.is_verified  ?? null
  form.notes       = props.verification?.notes        ?? ''
  editing.value    = true
}

function cancelEdit() {
  editing.value = false
}

function submit() {
  if (form.is_verified === null) return
  saving.value = true
  router.post(props.saveUrl, {
    visited_at:  form.visited_at,
    is_verified: form.is_verified ? 1 : 0,
    notes:       form.notes,
  }, {
    preserveScroll: true,
    onSuccess: () => { editing.value = false },
    onFinish:  () => { saving.value = false },
  })
}
</script>
