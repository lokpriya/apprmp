<template>
  <div class="max-w-4xl mx-auto space-y-6">

    <!-- Heading -->
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-md bg-[#206bc4] flex items-center justify-center text-white shrink-0">
        <i class="fa-solid fa-magnifying-glass-chart text-sm"></i>
      </div>
      <div>
        <h2 class="text-lg font-bold text-gray-800">समीक्षा अवस्था</h2>
        <p class="text-xs text-gray-500">पेस गरिएका रेकर्डहरूको समीक्षा गर्नुहोस्</p>
      </div>
    </div>

    <!-- Queue cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

      <!-- Households -->
      <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-6">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-md bg-indigo-100 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-house text-indigo-600 text-xl"></i>
          </div>
          <div class="flex-1">
            <p class="text-3xl font-bold text-gray-800">{{ props.pendingHouseholds }}</p>
            <p class="text-sm text-gray-500 mt-0.5">घरधुरी समीक्षा बाँकी</p>
          </div>
        </div>
        <div class="mt-4 pt-4 border-t border-[#e6e7e9]">
          <Link href="/review/households"
            class="flex items-center justify-between px-4 py-2.5 bg-[#206bc4] text-white rounded-md text-sm font-medium hover:bg-[#1a5ba8] transition-colors">
            <span>समीक्षा गर्नुहोस्</span>
            <i class="fa-solid fa-arrow-right text-xs"></i>
          </Link>
        </div>
      </div>

      <!-- Institutions -->
      <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-6">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-md bg-teal-100 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-building text-teal-600 text-xl"></i>
          </div>
          <div class="flex-1">
            <p class="text-3xl font-bold text-gray-800">{{ props.pendingInstitutions }}</p>
            <p class="text-sm text-gray-500 mt-0.5">संस्था समीक्षा बाँकी</p>
          </div>
        </div>
        <div class="mt-4 pt-4 border-t border-[#e6e7e9]">
          <Link href="/review/institutions"
            class="flex items-center justify-between px-4 py-2.5 bg-teal-600 text-white rounded-md text-sm font-medium hover:bg-teal-700 transition-colors">
            <span>समीक्षा गर्नुहोस्</span>
            <i class="fa-solid fa-arrow-right text-xs"></i>
          </Link>
        </div>
      </div>

      <!-- Businesses -->
      <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-6">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-md bg-amber-100 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-briefcase text-amber-600 text-xl"></i>
          </div>
          <div class="flex-1">
            <p class="text-3xl font-bold text-gray-800">{{ props.pendingBusinesses }}</p>
            <p class="text-sm text-gray-500 mt-0.5">व्यवसाय समीक्षा बाँकी</p>
          </div>
        </div>
        <div class="mt-4 pt-4 border-t border-[#e6e7e9]">
          <Link href="/review/businesses"
            class="flex items-center justify-between px-4 py-2.5 bg-amber-600 text-white rounded-md text-sm font-medium hover:bg-amber-700 transition-colors">
            <span>समीक्षा गर्नुहोस्</span>
            <i class="fa-solid fa-arrow-right text-xs"></i>
          </Link>
        </div>
      </div>
    </div>

    <!-- Workflow diagram -->
    <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-6">
      <h3 class="text-sm font-semibold text-gray-700 mb-4">
        <i class="fa-solid fa-route text-indigo-400 mr-1.5"></i>
        समीक्षा प्रक्रिया
      </h3>
      <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-full font-medium">मस्यौदा</span>
        <i class="fa-solid fa-arrow-right text-gray-300 text-[10px]"></i>
        <span class="px-3 py-1.5 bg-yellow-100 text-yellow-700 rounded-full font-medium">पेस भएको</span>
        <i class="fa-solid fa-arrow-right text-gray-300 text-[10px]"></i>
        <span class="px-3 py-1.5 bg-blue-100 text-blue-700 rounded-full font-medium">वडा अनुमोदित</span>
        <i class="fa-solid fa-arrow-right text-gray-300 text-[10px]"></i>
        <span class="px-3 py-1.5 bg-green-100 text-green-700 rounded-full font-medium">प्रकाशित</span>
      </div>
      <div class="mt-3 flex flex-wrap gap-2 text-xs">
        <span class="px-3 py-1.5 bg-red-100 text-red-600 rounded-full font-medium">
          <i class="fa-solid fa-rotate-left mr-1"></i>अस्वीकृत → गणकलाई फिर्ता
        </span>
        <span class="px-3 py-1.5 bg-orange-100 text-orange-600 rounded-full font-medium">
          <i class="fa-solid fa-arrow-up mr-1"></i>escalated → नगर प्रशासकलाई
        </span>
      </div>
    </div>

  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  pendingHouseholds:   { type: Number, default: 0 },
  pendingInstitutions: { type: Number, default: 0 },
  pendingBusinesses:   { type: Number, default: 0 },
})
</script>
