<template>
  <div class="max-w-2xl space-y-5">

    <!-- Page header -->
    <div class="flex items-center gap-3">
      <Link href="/admin/users"
        class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-md transition-colors">
        <i class="fa-solid fa-arrow-left text-sm"></i>
      </Link>
      <div>
        <h1 class="text-lg font-bold text-gray-900">{{ isEdit ? 'प्रयोगकर्ता सम्पादन' : 'नयाँ प्रयोगकर्ता' }}</h1>
        <p class="text-xs text-gray-500 mt-0.5">{{ isEdit ? 'Edit User' : 'Create User' }}</p>
      </div>
    </div>

    <form @submit.prevent="submit" class="space-y-5">

      <!-- Basic info card -->
      <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5 space-y-4">
        <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
          <i class="fa-solid fa-user text-indigo-500 text-xs"></i>
          आधारभूत जानकारी
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <!-- Name -->
          <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
              पूरा नाम <span class="text-red-400">*</span>
            </label>
            <input v-model="form.name" type="text" required placeholder="प्रयोगकर्ताको नाम"
              class="w-full px-3 py-2.5 rounded-md border text-sm transition-colors focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4]"
              :class="form.errors.name ? 'border-red-400' : 'border-[#e6e7e9]'" />
            <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
          </div>

          <!-- Email -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
              इमेल <span class="text-red-400">*</span>
            </label>
            <input v-model="form.email" type="email" required placeholder="user@example.com"
              class="w-full px-3 py-2.5 rounded-md border text-sm transition-colors focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4]"
              :class="form.errors.email ? 'border-red-400' : 'border-[#e6e7e9]'" />
            <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>
          </div>

          <!-- Phone -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
              फोन नम्बर
            </label>
            <input v-model="form.phone" type="text" placeholder="98XXXXXXXX"
              class="w-full px-3 py-2.5 rounded-md border border-[#e6e7e9] text-sm focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4]" />
          </div>

          <!-- Password -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
              पासवर्ड {{ isEdit ? '(खाली छोड्नुहोस् यदि परिवर्तन नगर्ने)' : '' }}
              <span v-if="!isEdit" class="text-red-400">*</span>
            </label>
            <div class="relative">
              <input v-model="form.password" :type="showPw ? 'text' : 'password'"
                :required="!isEdit" placeholder="••••••••" minlength="8"
                class="w-full pr-10 px-3 py-2.5 rounded-md border text-sm transition-colors focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4]"
                :class="form.errors.password ? 'border-red-400' : 'border-[#e6e7e9]'" />
              <button type="button" @click="showPw = !showPw"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                <i :class="showPw ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'" class="text-sm"></i>
              </button>
            </div>
            <p v-if="form.errors.password" class="text-xs text-red-500 mt-1">{{ form.errors.password }}</p>
          </div>

          <!-- Role -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">
              भूमिका <span class="text-red-400">*</span>
            </label>
            <select v-model="form.role" required
              class="w-full px-3 py-2.5 rounded-md border text-sm transition-colors focus:ring-2 focus:ring-[#206bc4]/30 focus:border-[#206bc4]"
              :class="form.errors.role ? 'border-red-400' : 'border-[#e6e7e9]'">
              <option value="">भूमिका छान्नुहोस्</option>
              <option value="municipal_admin">Municipal Admin</option>
              <option value="ward_admin">Ward Admin</option>
              <option value="ward_reviewer">Ward Reviewer</option>
              <option value="enumerator">Enumerator</option>
            </select>
            <p v-if="form.errors.role" class="text-xs text-red-500 mt-1">{{ form.errors.role }}</p>
          </div>
        </div>

        <!-- Active toggle (edit only) -->
        <div v-if="isEdit" class="flex items-center justify-between p-3 bg-gray-50 rounded-md">
          <div>
            <p class="text-sm font-medium text-gray-700">सक्रिय खाता</p>
            <p class="text-xs text-gray-400">निष्क्रिय प्रयोगकर्ता लगइन गर्न सक्दैनन्</p>
          </div>
          <button type="button" @click="form.is_active = !form.is_active"
            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
            :class="form.is_active ? 'bg-[#206bc4]' : 'bg-gray-200'">
            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
              :class="form.is_active ? 'translate-x-6' : 'translate-x-1'"></span>
          </button>
        </div>
      </div>

      <!-- Ward assignments -->
      <div v-if="['enumerator', 'ward_reviewer', 'ward_admin'].includes(form.role)"
        class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
        <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2 mb-3">
          <i class="fa-solid fa-map-pin text-indigo-500 text-xs"></i>
          वडा असाइनमेन्ट
          <span class="ml-auto text-xs font-normal text-gray-400">{{ form.ward_ids.length }} वडा छनोट गरिएको</span>
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
          <label v-for="ward in props.wards" :key="ward.id"
            class="flex items-center gap-2.5 p-2.5 rounded-md border cursor-pointer transition-colors"
            :class="form.ward_ids.includes(ward.id)
              ? 'border-indigo-300 bg-indigo-50'
              : 'border-[#e6e7e9] hover:border-gray-300'">
            <input type="checkbox" :value="ward.id" v-model="form.ward_ids"
              class="rounded text-indigo-600 focus:ring-[#206bc4]/30 w-4 h-4 shrink-0" />
            <span class="text-sm text-gray-700">वडा {{ ward.ward_number }}</span>
          </label>
        </div>
        <p v-if="props.wards.length === 0" class="text-xs text-gray-400 text-center py-4">
          कुनै वडा छैन — पहिले Settings मा वडा थप्नुहोस्।
        </p>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-3 justify-end">
        <Link href="/admin/users"
          class="px-5 py-2.5 rounded-md border border-[#e6e7e9] text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
          रद्द गर्नुहोस्
        </Link>
        <button type="submit" :disabled="form.processing"
          class="px-6 py-2.5 bg-[#206bc4] hover:bg-[#1a5ba8] disabled:opacity-60 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-md transition-colors shadow-sm shadow-indigo-200 flex items-center gap-2">
          <i v-if="form.processing" class="fa-solid fa-spinner fa-spin text-xs"></i>
          <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
          {{ form.processing ? 'सुरक्षित हुँदैछ...' : 'सुरक्षित गर्नुहोस्' }}
        </button>
      </div>

    </form>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  user:            { type: Object,  default: null },
  wards:           { type: Array,   default: () => [] },
  assignedWardIds: { type: Array,   default: () => [] },
})

const isEdit = computed(() => !!props.user)
const showPw = ref(false)

const form = useForm({
  name:      props.user?.name      ?? '',
  email:     props.user?.email     ?? '',
  phone:     props.user?.phone     ?? '',
  password:  '',
  role:      props.user?.role      ?? '',
  is_active: props.user?.is_active ?? true,
  ward_ids:  [...(props.assignedWardIds ?? [])],
})

function submit() {
  if (isEdit.value) {
    form.put(`/admin/users/${props.user.id}`)
  } else {
    form.post('/admin/users')
  }
}
</script>
