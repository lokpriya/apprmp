<template>
  <div class="max-w-4xl mx-auto space-y-4">

    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Link href="/review" class="w-8 h-8 rounded bg-white border border-[#e6e7e9] flex items-center justify-center text-gray-500 hover:bg-gray-50 transition-colors">
          <i class="fa-solid fa-arrow-left text-xs"></i>
        </Link>
        <div>
          <h2 class="text-base font-bold text-gray-800">घरधुरी समीक्षा अवस्था</h2>
          <p class="text-xs text-gray-500">{{ households.meta?.total ?? 0 }} रेकर्ड पेस भएका</p>
        </div>
      </div>
      <span class="px-3 py-1.5 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">
        <i class="fa-solid fa-clock mr-1"></i>
        {{ households.meta?.total ?? 0 }} बाँकी
      </span>
    </div>

    <!-- List -->
    <div v-if="households.data?.length > 0" class="space-y-3">
      <div v-for="hh in households.data" :key="hh.id"
        class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden">

        <!-- Main row -->
        <div class="p-5">
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                <span class="font-mono text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded">{{ hh.unique_code }}</span>
                <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">पेस भएको</span>
              </div>
              <p class="font-semibold text-gray-900 text-sm">{{ hh.respondent_name }}</p>
              <div class="flex flex-wrap items-center gap-3 mt-1 text-xs text-gray-500">
                <span><i class="fa-solid fa-location-dot text-gray-300 mr-1"></i>वडा {{ hh.ward?.ward_number ?? '—' }}</span>
                <span v-if="hh.settlement"><i class="fa-solid fa-house-flag text-gray-300 mr-1"></i>{{ hh.settlement.name_np }}</span>
                <span><i class="fa-solid fa-user text-gray-300 mr-1"></i>{{ hh.enumerator?.name ?? '—' }}</span>
                <span><i class="fa-regular fa-clock text-gray-300 mr-1"></i>{{ hh.submitted_at }}</span>
              </div>
            </div>

            <!-- Action buttons -->
            <div class="flex items-center gap-2 shrink-0">
              <a :href="`/households/${hh.id}`" target="_blank"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-[#e6e7e9] text-gray-600 rounded-md text-xs font-medium hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-eye"></i>
                <span class="hidden sm:inline">हेर्नुहोस्</span>
              </a>
              <button @click="approve(hh)"
                :disabled="processing[hh.id]"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white rounded-md text-xs font-semibold hover:bg-green-700 disabled:opacity-50 transition-colors">
                <i class="fa-solid fa-check"></i>
                <span class="hidden sm:inline">अनुमोदन</span>
              </button>
              <button @click="togglePanel(hh.id, 'escalate')"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 text-white rounded-md text-xs font-semibold hover:bg-orange-600 transition-colors">
                <i class="fa-solid fa-arrow-up"></i>
              </button>
              <button @click="togglePanel(hh.id, 'reject')"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-red-600 text-white rounded-md text-xs font-semibold hover:bg-red-700 transition-colors">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Reject panel -->
        <Transition
          enter-active-class="transition ease-out duration-200"
          enter-from-class="opacity-0 -translate-y-2"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition ease-in duration-150"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 -translate-y-2"
        >
          <div v-if="openPanel[hh.id] === 'reject'" class="border-t border-red-100 bg-red-50 px-5 py-4">
            <p class="text-xs font-semibold text-red-700 mb-2">
              <i class="fa-solid fa-circle-exclamation mr-1"></i>अस्वीकृतिको कारण <span class="text-red-500">*</span>
            </p>
            <textarea v-model="comments[hh.id]" rows="2" placeholder="अस्वीकृतिको विस्तृत कारण लेख्नुहोस्..."
              class="w-full rounded-md border border-red-200 px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 focus:border-red-400 bg-white resize-none mb-2">
            </textarea>
            <div class="flex items-center gap-2">
              <button @click="reject(hh)" :disabled="!comments[hh.id] || processing[hh.id]"
                class="px-4 py-1.5 bg-red-600 text-white rounded-md text-xs font-semibold hover:bg-red-700 disabled:opacity-50 transition-colors">
                <i class="fa-solid fa-xmark mr-1"></i>अस्वीकृत गर्नुहोस्
              </button>
              <button @click="closePanel(hh.id)" class="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700 transition-colors">
                रद्द गर्नुहोस्
              </button>
            </div>
          </div>
        </Transition>

        <!-- Escalate panel -->
        <Transition
          enter-active-class="transition ease-out duration-200"
          enter-from-class="opacity-0 -translate-y-2"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition ease-in duration-150"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 -translate-y-2"
        >
          <div v-if="openPanel[hh.id] === 'escalate'" class="border-t border-orange-100 bg-orange-50 px-5 py-4">
            <p class="text-xs font-semibold text-orange-700 mb-2">
              <i class="fa-solid fa-arrow-up mr-1"></i>नगर प्रशासकलाई escalate गर्नुहोस्
            </p>
            <textarea v-model="comments[hh.id]" rows="2" placeholder="escalation को कारण (वैकल्पिक)..."
              class="w-full rounded-md border border-orange-200 px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 focus:border-orange-400 bg-white resize-none mb-2">
            </textarea>
            <div class="flex items-center gap-2">
              <button @click="escalate(hh)" :disabled="processing[hh.id]"
                class="px-4 py-1.5 bg-orange-500 text-white rounded-md text-xs font-semibold hover:bg-orange-600 disabled:opacity-50 transition-colors">
                <i class="fa-solid fa-arrow-up mr-1"></i>Escalate गर्नुहोस्
              </button>
              <button @click="closePanel(hh.id)" class="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700 transition-colors">
                रद्द गर्नुहोस्
              </button>
            </div>
          </div>
        </Transition>

      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-16 text-center">
      <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-circle-check text-green-500 text-2xl"></i>
      </div>
      <p class="font-semibold text-gray-700">सबै घरधुरी समीक्षा भइसकेको छ!</p>
      <p class="text-sm text-gray-400 mt-1">कुनै पेस गरिएको घरधुरी बाँकी छैन।</p>
    </div>

    <!-- Pagination -->
    <div v-if="households.meta?.last_page > 1" class="flex items-center justify-between bg-white rounded-md border border-[#e6e7e9] shadow-sm px-5 py-3">
      <span class="text-xs text-gray-500">
        {{ households.meta.from }}–{{ households.meta.to }} / {{ households.meta.total }}
      </span>
      <div class="flex items-center gap-1">
        <Link v-if="households.links?.prev" :href="households.links.prev"
          class="px-3 py-1.5 rounded border border-[#e6e7e9] text-xs text-gray-600 hover:bg-gray-50 transition-colors">
          <i class="fa-solid fa-chevron-left"></i>
        </Link>
        <span class="px-3 py-1.5 text-xs text-gray-500">{{ households.meta.current_page }} / {{ households.meta.last_page }}</span>
        <Link v-if="households.links?.next" :href="households.links.next"
          class="px-3 py-1.5 rounded border border-[#e6e7e9] text-xs text-gray-600 hover:bg-gray-50 transition-colors">
          <i class="fa-solid fa-chevron-right"></i>
        </Link>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  households: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
})

const openPanel  = ref({})
const comments   = ref({})
const processing = ref({})

function togglePanel(id, type) {
  if (openPanel.value[id] === type) {
    openPanel.value[id] = null
  } else {
    openPanel.value[id] = type
    comments.value[id]  = ''
  }
}

function closePanel(id) {
  openPanel.value[id] = null
  comments.value[id]  = ''
}

function approve(hh) {
  if (!confirm(`वडा ${hh.ward?.ward_number} को घरधुरी "${hh.respondent_name}" अनुमोदन गर्ने?`)) return
  processing.value[hh.id] = true
  router.patch(`/review/households/${hh.id}/approve`, {}, {
    onFinish: () => { processing.value[hh.id] = false },
  })
}

function reject(hh) {
  processing.value[hh.id] = true
  router.patch(`/review/households/${hh.id}/reject`, { comment: comments.value[hh.id] }, {
    onSuccess: () => closePanel(hh.id),
    onFinish:  () => { processing.value[hh.id] = false },
  })
}

function escalate(hh) {
  processing.value[hh.id] = true
  router.patch(`/review/households/${hh.id}/escalate`, { comment: comments.value[hh.id] ?? '' }, {
    onSuccess: () => closePanel(hh.id),
    onFinish:  () => { processing.value[hh.id] = false },
  })
}
</script>
