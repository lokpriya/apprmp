<template>
  <div class="space-y-4">

    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-md bg-green-600 flex items-center justify-center text-white shrink-0">
          <i class="fa-solid fa-paper-plane text-sm"></i>
        </div>
        <div>
          <h2 class="text-lg font-bold text-gray-800">प्रकाशन अवस्था</h2>
          <p class="text-xs text-gray-500">वडाबाट अनुमोदित र escalate भएका रेकर्ड प्रकाशित गर्नुहोस्</p>
        </div>
      </div>
      <!-- Total badge -->
      <span
        v-if="counts.households + counts.institutions + counts.businesses > 0"
        class="px-3 py-1.5 bg-green-100 text-green-700 rounded-full text-xs font-semibold"
      >
        <i class="fa-solid fa-clock mr-1"></i>
        {{ counts.households + counts.institutions + counts.businesses }} बाँकी
      </span>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 bg-white border border-[#e6e7e9] rounded-md p-1 w-fit shadow-sm">
      <button
        @click="activeTab = 'households'"
        :class="[
          'flex items-center gap-2 px-4 py-2 rounded text-sm font-medium transition-colors',
          activeTab === 'households'
            ? 'bg-green-600 text-white shadow-sm'
            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
        ]"
      >
        <i class="fa-solid fa-house text-xs"></i>
        घरधुरी
        <span :class="[
          'text-[11px] font-bold px-1.5 py-0.5 rounded-full',
          activeTab === 'households' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600'
        ]">{{ counts.households }}</span>
      </button>
      <button
        @click="activeTab = 'institutions'"
        :class="[
          'flex items-center gap-2 px-4 py-2 rounded text-sm font-medium transition-colors',
          activeTab === 'institutions'
            ? 'bg-teal-600 text-white shadow-sm'
            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
        ]"
      >
        <i class="fa-solid fa-building text-xs"></i>
        संस्था
        <span :class="[
          'text-[11px] font-bold px-1.5 py-0.5 rounded-full',
          activeTab === 'institutions' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600'
        ]">{{ counts.institutions }}</span>
      </button>
      <button
        @click="activeTab = 'businesses'"
        :class="[
          'flex items-center gap-2 px-4 py-2 rounded text-sm font-medium transition-colors',
          activeTab === 'businesses'
            ? 'bg-amber-600 text-white shadow-sm'
            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
        ]"
      >
        <i class="fa-solid fa-briefcase text-xs"></i>
        व्यवसाय
        <span :class="[
          'text-[11px] font-bold px-1.5 py-0.5 rounded-full',
          activeTab === 'businesses' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600'
        ]">{{ counts.businesses }}</span>
      </button>
    </div>

    <!-- ── HOUSEHOLDS TAB ── -->
    <template v-if="activeTab === 'households'">
      <div v-if="households.data?.length > 0" class="space-y-3">
        <div
          v-for="hh in households.data"
          :key="hh.id"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden"
        >
          <div class="p-5">
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                  <span class="font-mono text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded">{{ hh.unique_code }}</span>
                  <!-- Status badge -->
                  <span :class="[
                    'px-2 py-0.5 rounded-full text-xs font-medium',
                    hh.status === 'escalated'
                      ? 'bg-orange-100 text-orange-700'
                      : 'bg-blue-100 text-blue-700'
                  ]">
                    <i :class="hh.status === 'escalated' ? 'fa-solid fa-arrow-up mr-1' : 'fa-solid fa-check mr-1'"></i>
                    {{ hh.status === 'escalated' ? 'Escalated' : 'वडा अनुमोदित' }}
                  </span>
                </div>
                <p class="font-semibold text-gray-900 text-sm">{{ hh.respondent_name }}</p>
                <div class="flex flex-wrap items-center gap-3 mt-1 text-xs text-gray-500">
                  <span><i class="fa-solid fa-location-dot text-gray-300 mr-1"></i>वडा {{ hh.ward?.ward_number ?? '—' }}</span>
                  <span v-if="hh.settlement"><i class="fa-solid fa-house-flag text-gray-300 mr-1"></i>{{ hh.settlement.name_np }}</span>
                  <span><i class="fa-solid fa-user text-gray-300 mr-1"></i>{{ hh.enumerator?.name ?? '—' }}</span>
                  <span v-if="hh.ward_reviewer">
                    <i class="fa-solid fa-shield-halved text-gray-300 mr-1"></i>{{ hh.ward_reviewer.name }}
                  </span>
                  <span><i class="fa-regular fa-clock text-gray-300 mr-1"></i>{{ hh.ward_reviewed_at }}</span>
                </div>
                <!-- Escalation comment -->
                <div v-if="hh.ward_review_comment" class="mt-2 text-xs text-orange-700 bg-orange-50 border border-orange-100 rounded px-3 py-1.5">
                  <i class="fa-solid fa-comment mr-1"></i>{{ hh.ward_review_comment }}
                </div>
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-2 shrink-0">
                <a :href="`/households/${hh.id}`" target="_blank"
                  class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-[#e6e7e9] text-gray-600 rounded-md text-xs font-medium hover:bg-gray-50 transition-colors">
                  <i class="fa-solid fa-eye"></i>
                  <span class="hidden sm:inline">हेर्नुहोस्</span>
                </a>
                <button @click="publishHH(hh)" :disabled="hhProcessing[hh.id]"
                  class="flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white rounded-md text-xs font-semibold hover:bg-green-700 disabled:opacity-50 transition-colors">
                  <i class="fa-solid fa-paper-plane text-[10px]"></i>
                  <span class="hidden sm:inline">प्रकाशन</span>
                </button>
                <button
                  v-if="hh.status === 'escalated'"
                  @click="toggleHHPanel(hh.id)"
                  :class="[
                    'flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-colors',
                    hhOpenPanel[hh.id]
                      ? 'bg-red-100 text-red-700 border border-red-200'
                      : 'bg-red-600 text-white hover:bg-red-700'
                  ]">
                  <i class="fa-solid fa-xmark"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Reject panel (escalated only) -->
          <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
          >
            <div v-if="hhOpenPanel[hh.id]" class="border-t border-red-100 bg-red-50 px-5 py-4">
              <p class="text-xs font-semibold text-red-700 mb-2">
                <i class="fa-solid fa-circle-exclamation mr-1"></i>अस्वीकृतिको कारण <span class="text-red-500">*</span>
              </p>
              <textarea v-model="hhComments[hh.id]" rows="2"
                placeholder="कारण लेख्नुहोस्..."
                class="w-full rounded-md border border-red-200 px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 bg-white resize-none mb-2">
              </textarea>
              <div class="flex items-center gap-2">
                <button @click="rejectHH(hh)" :disabled="!hhComments[hh.id] || hhProcessing[hh.id]"
                  class="px-4 py-1.5 bg-red-600 text-white rounded-md text-xs font-semibold hover:bg-red-700 disabled:opacity-50 transition-colors">
                  <i class="fa-solid fa-xmark mr-1"></i>अस्वीकृत गर्नुहोस्
                </button>
                <button @click="toggleHHPanel(hh.id)" class="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700">
                  रद्द
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
        <p class="font-semibold text-gray-700">सबै घरधुरी प्रकाशित भइसकेको छ!</p>
        <p class="text-sm text-gray-400 mt-1">वडाबाट अनुमोदित कुनै घरधुरी बाँकी छैन।</p>
      </div>

      <!-- HH Pagination -->
      <div v-if="households.meta?.last_page > 1"
        class="flex items-center justify-between bg-white rounded-md border border-[#e6e7e9] shadow-sm px-5 py-3">
        <span class="text-xs text-gray-500">{{ households.meta.from }}–{{ households.meta.to }} / {{ households.meta.total }}</span>
        <div class="flex items-center gap-1">
          <Link v-if="households.links?.prev" :href="households.links.prev + '&tab=households'"
            class="px-3 py-1.5 rounded border border-[#e6e7e9] text-xs text-gray-600 hover:bg-gray-50">
            <i class="fa-solid fa-chevron-left"></i>
          </Link>
          <span class="px-3 py-1.5 text-xs text-gray-500">{{ households.meta.current_page }} / {{ households.meta.last_page }}</span>
          <Link v-if="households.links?.next" :href="households.links.next + '&tab=households'"
            class="px-3 py-1.5 rounded border border-[#e6e7e9] text-xs text-gray-600 hover:bg-gray-50">
            <i class="fa-solid fa-chevron-right"></i>
          </Link>
        </div>
      </div>
    </template>

    <!-- ── INSTITUTIONS TAB ── -->
    <template v-if="activeTab === 'institutions'">
      <div v-if="institutions.data?.length > 0" class="space-y-3">
        <div
          v-for="inst in institutions.data"
          :key="inst.id"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden"
        >
          <div class="p-5">
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                  <span class="font-mono text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded">{{ inst.unique_code }}</span>
                  <span v-if="inst.institutionType" class="px-2 py-0.5 bg-teal-100 text-teal-700 rounded-full text-xs font-medium">
                    {{ inst.institutionType.name_np }}
                  </span>
                  <span :class="[
                    'px-2 py-0.5 rounded-full text-xs font-medium',
                    inst.status === 'escalated' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700'
                  ]">
                    <i :class="inst.status === 'escalated' ? 'fa-solid fa-arrow-up mr-1' : 'fa-solid fa-check mr-1'"></i>
                    {{ inst.status === 'escalated' ? 'Escalated' : 'वडा अनुमोदित' }}
                  </span>
                </div>
                <p class="font-semibold text-gray-900 text-sm">{{ inst.name_np || inst.name_en }}</p>
                <div class="flex flex-wrap items-center gap-3 mt-1 text-xs text-gray-500">
                  <span><i class="fa-solid fa-location-dot text-gray-300 mr-1"></i>वडा {{ inst.ward?.ward_number ?? '—' }}</span>
                  <span><i class="fa-solid fa-user text-gray-300 mr-1"></i>{{ inst.enumerator?.name ?? '—' }}</span>
                  <span v-if="inst.ward_reviewer">
                    <i class="fa-solid fa-shield-halved text-gray-300 mr-1"></i>{{ inst.ward_reviewer.name }}
                  </span>
                  <span><i class="fa-regular fa-clock text-gray-300 mr-1"></i>{{ inst.ward_reviewed_at }}</span>
                </div>
                <div v-if="inst.ward_review_comment" class="mt-2 text-xs text-orange-700 bg-orange-50 border border-orange-100 rounded px-3 py-1.5">
                  <i class="fa-solid fa-comment mr-1"></i>{{ inst.ward_review_comment }}
                </div>
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-2 shrink-0">
                <a :href="`/institutions/${inst.id}`" target="_blank"
                  class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-[#e6e7e9] text-gray-600 rounded-md text-xs font-medium hover:bg-gray-50 transition-colors">
                  <i class="fa-solid fa-eye"></i>
                  <span class="hidden sm:inline">हेर्नुहोस्</span>
                </a>
                <button @click="publishInst(inst)" :disabled="instProcessing[inst.id]"
                  class="flex items-center gap-1.5 px-3 py-1.5 bg-teal-600 text-white rounded-md text-xs font-semibold hover:bg-teal-700 disabled:opacity-50 transition-colors">
                  <i class="fa-solid fa-paper-plane text-[10px]"></i>
                  <span class="hidden sm:inline">प्रकाशन</span>
                </button>
                <button
                  v-if="inst.status === 'escalated'"
                  @click="toggleInstPanel(inst.id)"
                  :class="[
                    'flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-colors',
                    instOpenPanel[inst.id]
                      ? 'bg-red-100 text-red-700 border border-red-200'
                      : 'bg-red-600 text-white hover:bg-red-700'
                  ]">
                  <i class="fa-solid fa-xmark"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Reject panel (escalated only) -->
          <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
          >
            <div v-if="instOpenPanel[inst.id]" class="border-t border-red-100 bg-red-50 px-5 py-4">
              <p class="text-xs font-semibold text-red-700 mb-2">
                <i class="fa-solid fa-circle-exclamation mr-1"></i>अस्वीकृतिको कारण <span class="text-red-500">*</span>
              </p>
              <textarea v-model="instComments[inst.id]" rows="2"
                placeholder="कारण लेख्नुहोस्..."
                class="w-full rounded-md border border-red-200 px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 bg-white resize-none mb-2">
              </textarea>
              <div class="flex items-center gap-2">
                <button @click="rejectInst(inst)" :disabled="!instComments[inst.id] || instProcessing[inst.id]"
                  class="px-4 py-1.5 bg-red-600 text-white rounded-md text-xs font-semibold hover:bg-red-700 disabled:opacity-50">
                  <i class="fa-solid fa-xmark mr-1"></i>अस्वीकृत गर्नुहोस्
                </button>
                <button @click="toggleInstPanel(inst.id)" class="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700">
                  रद्द
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
        <p class="font-semibold text-gray-700">सबै संस्था प्रकाशित भइसकेको छ!</p>
        <p class="text-sm text-gray-400 mt-1">वडाबाट अनुमोदित कुनै संस्था बाँकी छैन।</p>
      </div>

      <!-- Inst Pagination -->
      <div v-if="institutions.meta?.last_page > 1"
        class="flex items-center justify-between bg-white rounded-md border border-[#e6e7e9] shadow-sm px-5 py-3">
        <span class="text-xs text-gray-500">{{ institutions.meta.from }}–{{ institutions.meta.to }} / {{ institutions.meta.total }}</span>
        <div class="flex items-center gap-1">
          <Link v-if="institutions.links?.prev" :href="institutions.links.prev + '&tab=institutions'"
            class="px-3 py-1.5 rounded border border-[#e6e7e9] text-xs text-gray-600 hover:bg-gray-50">
            <i class="fa-solid fa-chevron-left"></i>
          </Link>
          <span class="px-3 py-1.5 text-xs text-gray-500">{{ institutions.meta.current_page }} / {{ institutions.meta.last_page }}</span>
          <Link v-if="institutions.links?.next" :href="institutions.links.next + '&tab=institutions'"
            class="px-3 py-1.5 rounded border border-[#e6e7e9] text-xs text-gray-600 hover:bg-gray-50">
            <i class="fa-solid fa-chevron-right"></i>
          </Link>
        </div>
      </div>
    </template>

    <!-- ── BUSINESSES TAB ── -->
    <template v-if="activeTab === 'businesses'">
      <div v-if="businesses.data?.length > 0" class="space-y-3">
        <div
          v-for="biz in businesses.data"
          :key="biz.id"
          class="bg-white rounded-md border border-[#e6e7e9] shadow-sm overflow-hidden"
        >
          <div class="p-5">
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1 min-w-0">
                <!-- Code + badges -->
                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                  <span class="font-mono text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded">{{ biz.unique_code }}</span>
                  <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">
                    <i class="fa-solid fa-check mr-1 text-[10px]"></i>वडा अनुमोदित
                  </span>
                  <span v-if="biz.is_registered" class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">
                    दर्ता भएको
                  </span>
                </div>

                <!-- Business name -->
                <p class="font-semibold text-gray-900 text-sm">{{ biz.business_name }}</p>

                <!-- Details grid -->
                <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-0.5 text-xs text-gray-500">
                  <span v-if="biz.member">
                    <i class="fa-solid fa-user text-gray-300 mr-1"></i>{{ biz.member.full_name }}
                  </span>
                  <span v-if="biz.household">
                    <i class="fa-solid fa-house text-gray-300 mr-1"></i>{{ biz.household.unique_code }}
                  </span>
                  <span>
                    <i class="fa-solid fa-location-dot text-gray-300 mr-1"></i>वडा {{ biz.ward?.ward_number ?? '—' }}
                  </span>
                  <span v-if="biz.settlement">
                    <i class="fa-solid fa-house-flag text-gray-300 mr-1"></i>{{ biz.settlement.name_np }}
                  </span>
                  <span v-if="biz.business_type">
                    <i class="fa-solid fa-tag text-gray-300 mr-1"></i>{{ biz.business_type }}
                  </span>
                  <span v-if="biz.business_field">
                    <i class="fa-solid fa-briefcase text-gray-300 mr-1"></i>{{ biz.business_field }}
                  </span>
                  <span v-if="biz.business_location">
                    <i class="fa-solid fa-map-pin text-gray-300 mr-1"></i>{{ biz.business_location }}
                  </span>
                  <span v-if="biz.contact_number">
                    <i class="fa-solid fa-phone text-gray-300 mr-1"></i>{{ biz.contact_number }}
                  </span>
                  <span v-if="biz.enumerator">
                    <i class="fa-solid fa-user-pen text-gray-300 mr-1"></i>{{ biz.enumerator.name }}
                  </span>
                  <span v-if="biz.ward_reviewer">
                    <i class="fa-solid fa-shield-halved text-gray-300 mr-1"></i>{{ biz.ward_reviewer.name }}
                  </span>
                  <span v-if="biz.ward_reviewed_at">
                    <i class="fa-regular fa-clock text-gray-300 mr-1"></i>{{ biz.ward_reviewed_at }}
                  </span>
                </div>

                <!-- Reviewer comment -->
                <div v-if="biz.ward_review_comment" class="mt-2 text-xs text-blue-700 bg-blue-50 border border-blue-100 rounded px-3 py-1.5">
                  <i class="fa-solid fa-comment mr-1"></i>{{ biz.ward_review_comment }}
                </div>
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-2 shrink-0">
                <a :href="`/businesses/${biz.id}`" target="_blank"
                  class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-[#e6e7e9] text-gray-600 rounded-md text-xs font-medium hover:bg-gray-50 transition-colors">
                  <i class="fa-solid fa-eye"></i>
                  <span class="hidden sm:inline">हेर्नुहोस्</span>
                </a>
                <button @click="publishBiz(biz)" :disabled="bizProcessing[biz.id]"
                  class="flex items-center gap-1.5 px-3 py-1.5 bg-amber-600 text-white rounded-md text-xs font-semibold hover:bg-amber-700 disabled:opacity-50 transition-colors">
                  <i class="fa-solid fa-paper-plane text-[10px]"></i>
                  <span class="hidden sm:inline">प्रकाशन</span>
                </button>
                <button
                  @click="toggleBizPanel(biz.id)"
                  :class="[
                    'flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-colors',
                    bizOpenPanel[biz.id]
                      ? 'bg-red-100 text-red-700 border border-red-200'
                      : 'bg-red-600 text-white hover:bg-red-700'
                  ]">
                  <i class="fa-solid fa-xmark"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Reject panel -->
          <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
          >
            <div v-if="bizOpenPanel[biz.id]" class="border-t border-red-100 bg-red-50 px-5 py-4">
              <p class="text-xs font-semibold text-red-700 mb-2">
                <i class="fa-solid fa-circle-exclamation mr-1"></i>अस्वीकृतिको कारण <span class="text-red-500">*</span>
              </p>
              <textarea v-model="bizComments[biz.id]" rows="2"
                placeholder="कारण लेख्नुहोस्..."
                class="w-full rounded-md border border-red-200 px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 bg-white resize-none mb-2">
              </textarea>
              <div class="flex items-center gap-2">
                <button @click="rejectBiz(biz)" :disabled="!bizComments[biz.id] || bizProcessing[biz.id]"
                  class="px-4 py-1.5 bg-red-600 text-white rounded-md text-xs font-semibold hover:bg-red-700 disabled:opacity-50">
                  <i class="fa-solid fa-xmark mr-1"></i>अस्वीकृत गर्नुहोस्
                </button>
                <button @click="toggleBizPanel(biz.id)" class="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700">
                  रद्द
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
        <p class="font-semibold text-gray-700">सबै व्यवसाय प्रकाशित भइसकेको छ!</p>
        <p class="text-sm text-gray-400 mt-1">वडाबाट अनुमोदित कुनै व्यवसाय बाँकी छैन।</p>
      </div>

      <!-- Biz Pagination -->
      <div v-if="businesses.meta?.last_page > 1"
        class="flex items-center justify-between bg-white rounded-md border border-[#e6e7e9] shadow-sm px-5 py-3">
        <span class="text-xs text-gray-500">{{ businesses.meta.from }}–{{ businesses.meta.to }} / {{ businesses.meta.total }}</span>
        <div class="flex items-center gap-1">
          <Link v-if="businesses.links?.prev" :href="businesses.links.prev + '&tab=businesses'"
            class="px-3 py-1.5 rounded border border-[#e6e7e9] text-xs text-gray-600 hover:bg-gray-50">
            <i class="fa-solid fa-chevron-left"></i>
          </Link>
          <span class="px-3 py-1.5 text-xs text-gray-500">{{ businesses.meta.current_page }} / {{ businesses.meta.last_page }}</span>
          <Link v-if="businesses.links?.next" :href="businesses.links.next + '&tab=businesses'"
            class="px-3 py-1.5 rounded border border-[#e6e7e9] text-xs text-gray-600 hover:bg-gray-50">
            <i class="fa-solid fa-chevron-right"></i>
          </Link>
        </div>
      </div>
    </template>

  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  households:   { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  institutions: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  businesses:   { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  tab:          { type: String, default: 'households' },
  counts:       { type: Object, default: () => ({ households: 0, institutions: 0, businesses: 0 }) },
})

const activeTab = ref(props.tab)

// Households
const hhProcessing = ref({})
const hhOpenPanel  = ref({})
const hhComments   = ref({})

function toggleHHPanel(id) {
  hhOpenPanel.value[id] = !hhOpenPanel.value[id]
  if (hhOpenPanel.value[id]) hhComments.value[id] = ''
}

function publishHH(hh) {
  if (!confirm(`घरधुरी "${hh.respondent_name}" प्रकाशित गर्ने?`)) return
  hhProcessing.value[hh.id] = true
  router.patch(`/admin/publish-queue/households/${hh.id}/publish`, {}, {
    onFinish: () => { hhProcessing.value[hh.id] = false },
  })
}

function rejectHH(hh) {
  hhProcessing.value[hh.id] = true
  router.patch(`/admin/publish-queue/households/${hh.id}/reject`, { comment: hhComments.value[hh.id] }, {
    onSuccess: () => { hhOpenPanel.value[hh.id] = false },
    onFinish:  () => { hhProcessing.value[hh.id] = false },
  })
}

// Institutions
const instProcessing = ref({})
const instOpenPanel  = ref({})
const instComments   = ref({})

function toggleInstPanel(id) {
  instOpenPanel.value[id] = !instOpenPanel.value[id]
  if (instOpenPanel.value[id]) instComments.value[id] = ''
}

function publishInst(inst) {
  if (!confirm(`"${inst.name_np || inst.name_en}" प्रकाशित गर्ने?`)) return
  instProcessing.value[inst.id] = true
  router.patch(`/admin/publish-queue/institutions/${inst.id}/publish`, {}, {
    onFinish: () => { instProcessing.value[inst.id] = false },
  })
}

function rejectInst(inst) {
  instProcessing.value[inst.id] = true
  router.patch(`/admin/publish-queue/institutions/${inst.id}/reject`, { comment: instComments.value[inst.id] }, {
    onSuccess: () => { instOpenPanel.value[inst.id] = false },
    onFinish:  () => { instProcessing.value[inst.id] = false },
  })
}

// Businesses
const bizProcessing = ref({})
const bizOpenPanel  = ref({})
const bizComments   = ref({})

function toggleBizPanel(id) {
  bizOpenPanel.value[id] = !bizOpenPanel.value[id]
  if (bizOpenPanel.value[id]) bizComments.value[id] = ''
}

function publishBiz(biz) {
  if (!confirm(`व्यवसाय "${biz.business_name}" प्रकाशित गर्ने?`)) return
  bizProcessing.value[biz.id] = true
  router.patch(`/admin/publish-queue/businesses/${biz.id}/publish`, {}, {
    onFinish: () => { bizProcessing.value[biz.id] = false },
  })
}

function rejectBiz(biz) {
  bizProcessing.value[biz.id] = true
  router.patch(`/admin/publish-queue/businesses/${biz.id}/reject`, { comment: bizComments.value[biz.id] }, {
    onSuccess: () => { bizOpenPanel.value[biz.id] = false },
    onFinish:  () => { bizProcessing.value[biz.id] = false },
  })
}
</script>
