<template>
  <div>
    <!-- Print-only header (hidden on screen) -->
    <div class="print-only hidden mb-6 pb-4 border-b-2 border-gray-800">
      <p class="text-xs text-gray-500 mb-1">{{ page.props.auth?.lg?.name_np ?? 'स्थानीय तह' }}</p>
      <h1 class="text-xl font-bold text-gray-900">व्यवसाय विवरण</h1>
      <div class="flex items-center gap-4 mt-2 text-sm text-gray-600">
        <span>कोड: <strong class="font-mono">{{ business.unique_code }}</strong></span>
        <span>व्यवसाय: <strong>{{ business.business_name }}</strong></span>
        <span>वडा: <strong>{{ business.ward?.ward_number ?? '—' }}</strong></span>
        <span>अवस्था: <strong>{{ statusLabel(business.status) }}</strong></span>
        <span class="ml-auto text-xs text-gray-400">मुद्रण मिति: {{ new Date().toLocaleDateString('ne-NP') }}</span>
      </div>
    </div>

    <!-- Page header (screen only) -->
    <div class="no-print flex items-center gap-3 mb-4">
      <a
        href="/businesses"
        class="w-8 h-8 rounded border border-[#e6e7e9] flex items-center justify-center text-[#626976] hover:bg-gray-50 transition-colors"
      >
        <i class="fa-solid fa-arrow-left text-[11px]"></i>
      </a>
      <h1 class="text-[15px] font-semibold text-[#1d273b]">व्यवसाय विवरण</h1>
      <span class="font-mono text-[11px] bg-orange-50 text-orange-700 px-2 py-0.5 rounded">{{ business.unique_code }}</span>
      <span :class="['text-[11px] px-2 py-0.5 rounded font-medium', statusBadge(business.status)]">
        {{ statusLabel(business.status) }}
      </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

      <!-- ── Main content (2/3) ─────────────────────────────────────── -->
      <div class="lg:col-span-2 space-y-5">

        <!-- 1. घरधुरी / सदस्य सम्पर्क -->
        <section class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <span class="w-6 h-6 rounded bg-blue-100 flex items-center justify-center">
              <i class="fa-solid fa-users text-blue-600 text-xs"></i>
            </span>
            व्यवसाय स्वामी
          </h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <DetailRow label="सदस्यको नाम" :value="member?.full_name" />
            <DetailRow label="उमेर" :value="member?.age ? `${member.age} वर्ष` : null" />
            <DetailRow label="लिङ्ग" :value="member?.gender" />
            <DetailRow label="बाबुको नाम" :value="business.owner_father_name" />
            <DetailRow label="हजुरबाबुको नाम" :value="business.owner_grand_father_name" />
            <div v-if="household">
              <p class="text-xs text-gray-400 mb-0.5">घरधुरी कोड</p>
              <a :href="`/households/${household.id}`" class="text-sm font-mono text-blue-600 hover:underline font-medium">
                {{ household.unique_code }}
              </a>
            </div>
          </div>
        </section>

        <!-- 2. व्यवसाय पहिचान -->
        <section class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <span class="w-6 h-6 rounded bg-orange-100 flex items-center justify-center">
              <i class="fa-solid fa-store text-orange-600 text-xs"></i>
            </span>
            व्यवसाय पहिचान
          </h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <DetailRow label="व्यवसायको नाम" :value="business.business_name" />
            <DetailRow label="व्यवसाय स्थान" :value="business.business_location" />
            <DetailRow label="व्यवसायको किसिम" :value="business.business_type === 'other' ? business.business_type_other : business.business_type" />
          </div>
        </section>

        <!-- 3. व्यवसाय क्षेत्र -->
        <section class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <span class="w-6 h-6 rounded bg-teal-100 flex items-center justify-center">
              <i class="fa-solid fa-layer-group text-teal-600 text-xs"></i>
            </span>
            व्यवसाय क्षेत्र
          </h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <DetailRow
              label="व्यवसाय क्षेत्र"
              :value="business.business_field === 'other' ? business.business_field_other : business.business_field"
            />
            <DetailRow label="उप-वर्ग" :value="business.business_sub_category" />
          </div>
        </section>

        <!-- 4. दर्ता तथा कर -->
        <section class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <span class="w-6 h-6 rounded bg-purple-100 flex items-center justify-center">
              <i class="fa-solid fa-file-contract text-purple-600 text-xs"></i>
            </span>
            दर्ता तथा कर विवरण
          </h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
            <DetailRow label="व्यवसाय दर्ता भएको?" :value="boolLabel(business.is_registered)" />
            <DetailRow label="PAN/VAT छ?" :value="boolLabel(business.has_pan_vat)" />
            <DetailRow v-if="business.has_pan_vat && business.pan_vat_number" label="PAN / VAT नं." :value="business.pan_vat_number" />
            <DetailRow label="व्यवसाय कर तिरेको?" :value="boolLabel(business.paid_business_tax)" />
            <DetailRow v-if="business.paid_business_tax" label="कर रकम" :value="business.tax_amount ? `रु. ${business.tax_amount}` : null" />
            <DetailRow label="सम्पर्क नं." :value="business.contact_number" />
          </div>

          <!-- Registration places -->
          <div v-if="regPlaces.length" class="mb-4">
            <p class="text-xs text-gray-400 mb-2">दर्ता स्थान</p>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="place in regPlaces"
                :key="place"
                class="bg-purple-50 text-purple-700 text-xs px-3 py-1 rounded-full border border-purple-100 font-medium"
              >{{ place }}</span>
            </div>
          </div>

          <!-- Registration numbers grid -->
          <div v-if="hasRegNumbers" class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-4 border-t border-gray-100">
            <DetailRow v-if="business.reg_no_municipality" label="नगर/गाउँपालिका दर्ता नं." :value="business.reg_no_municipality" />
            <DetailRow v-if="business.reg_no_home_cottage" label="घरेलु/कुटीर उद्योग नं." :value="business.reg_no_home_cottage" />
            <DetailRow v-if="business.reg_no_company_registrar" label="कम्पनी रजिस्ट्रार नं." :value="business.reg_no_company_registrar" />
            <DetailRow v-if="business.reg_no_fcci" label="FCCI/FNCII नं." :value="business.reg_no_fcci" />
            <DetailRow v-if="business.reg_no_govt_office" label="सरकारी कार्यालय दर्ता नं." :value="business.reg_no_govt_office" />
            <DetailRow v-if="business.place_of_registration_other" label="अन्य दर्ता स्थान" :value="business.place_of_registration_other" />
          </div>
        </section>

        <!-- Rejection note -->
        <div v-if="business.status === 'rejected' && business.ward_review_comment"
          class="bg-red-50 border border-red-200 rounded-md p-4 flex gap-3">
          <i class="fa-solid fa-circle-exclamation text-red-400 mt-0.5 flex-shrink-0"></i>
          <div>
            <p class="text-sm font-semibold text-red-700 mb-1">अस्वीकृति कारण</p>
            <p class="text-sm text-red-600">{{ business.ward_review_comment }}</p>
          </div>
        </div>

      </div>

      <!-- ── Sidebar (1/3) ──────────────────────────────────────────── -->
      <div class="space-y-4">

        <!-- Status + Actions -->
        <div class="no-print bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">अवस्था</h3>
          <div class="flex items-center gap-2 mb-4">
            <span :class="['text-sm px-3 py-1.5 rounded-full font-semibold', statusBadge(business.status)]">
              {{ statusLabel(business.status) }}
            </span>
          </div>

          <div class="space-y-2">
            <!-- Edit -->
            <a
              v-if="canEdit"
              :href="`/businesses/${business.id}/edit`"
              class="flex items-center justify-center gap-2 w-full rounded-md border border-[#e6e7e9] px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
            >
              <i class="fa-solid fa-pen text-gray-400"></i>
              सम्पादन गर्नुस्
              <span v-if="business.status === 'published'" class="ml-auto text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-semibold">प्रकाशित</span>
            </a>

            <!-- Submit -->
            <button
              v-if="canSubmit"
              @click="submitBusiness"
              :disabled="actionLoading"
              class="flex items-center justify-center gap-2 w-full rounded-md bg-orange-600 hover:bg-orange-700 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60"
            >
              <i :class="actionLoading ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-paper-plane'"></i>
              पेस गर्नुस्
            </button>

            <!-- Approve -->
            <button v-if="canApprove" @click="approveBusiness" :disabled="actionLoading"
              class="flex items-center justify-center gap-2 w-full rounded-md bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60">
              <i :class="actionLoading ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-circle-check'"></i>
              स्वीकृत गर्नुस्
            </button>

            <!-- Reject -->
            <button v-if="canReject" @click="showBizRejectModal = true" :disabled="actionLoading"
              class="flex items-center justify-center gap-2 w-full rounded-md bg-red-600 hover:bg-red-700 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60">
              <i class="fa-solid fa-circle-xmark"></i>
              अस्वीकार गर्नुस्
            </button>

            <!-- Publish (admin) -->
            <button
              v-if="canPublish"
              @click="publishBusiness"
              :disabled="actionLoading"
              class="flex items-center justify-center gap-2 w-full rounded-md bg-green-600 hover:bg-green-700 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60"
            >
              <i :class="actionLoading ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-globe'"></i>
              प्रकाशित गर्नुस्
            </button>

            <!-- Ward reviewer: request edit -->
            <button v-if="canRequestEdit" @click="showRequestModal = true"
              class="flex items-center justify-center gap-2 w-full rounded-md border border-orange-300 bg-orange-50 hover:bg-orange-100 px-4 py-2 text-sm font-medium text-orange-700 transition-colors">
              <i class="fa-solid fa-pen-to-square text-orange-400"></i> सम्पादन अनुरोध
            </button>
            <div v-if="hasPendingEditRequest"
              class="flex items-center gap-2 w-full rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-700">
              <i class="fa-solid fa-clock text-amber-400"></i> अनुरोध पेस गरिएको — समीक्षा बाँकी
            </div>

            <!-- Print -->
            <button @click="printPage"
              class="flex items-center justify-center gap-2 w-full rounded-md border border-orange-200 bg-orange-50 hover:bg-orange-100 px-4 py-2 text-sm font-medium text-orange-700 transition-colors">
              <i class="fa-solid fa-print text-orange-400"></i> प्रिन्ट
            </button>
            <a :href="`/businesses/${business.id}/pdf`" target="_blank"
              class="flex items-center justify-center gap-2 w-full rounded-md border border-rose-200 bg-rose-50 hover:bg-rose-100 px-4 py-2 text-sm font-medium text-rose-700 transition-colors">
              <i class="fa-solid fa-file-pdf text-rose-400"></i> PDF डाउनलोड
            </a>
          </div>

          <div class="mt-4 pt-4 border-t border-gray-50">
            <a
              href="/businesses"
              class="flex items-center gap-2 text-xs text-gray-400 hover:text-gray-600 transition-colors"
            >
              <i class="fa-solid fa-arrow-left"></i> सूचीमा फर्कनुस्
            </a>
          </div>
        </div>

        <!-- Quick Info -->
        <div class="bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">छोटो जानकारी</h3>
          <div class="space-y-3">
            <div>
              <p class="text-xs text-gray-400">अद्वितीय कोड</p>
              <p class="text-sm font-mono font-semibold text-orange-700">{{ business.unique_code }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400">वडा</p>
              <p class="text-sm font-medium text-gray-700">
                {{ business.ward ? `वडा ${business.ward.ward_number}` : '—' }}
              </p>
            </div>
            <div v-if="business.settlement">
              <p class="text-xs text-gray-400">बस्ती / टोल</p>
              <p class="text-sm font-medium text-gray-700">{{ business.settlement.name_np }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400">गणक</p>
              <p class="text-sm font-medium text-gray-700">{{ business.enumerator?.name || '—' }}</p>
            </div>
            <div v-if="business.ward_reviewer">
              <p class="text-xs text-gray-400">वडा समीक्षक</p>
              <p class="text-sm font-medium text-gray-700">{{ business.ward_reviewer?.name || '—' }}</p>
            </div>
          </div>
        </div>

        <!-- Field Verification -->
        <FieldVerificationCard
          :verification="fieldVerification"
          :can-verify="canVerify"
          :save-url="`/businesses/${business.id}/field-verify`"
        />

        <!-- Workflow timeline -->
        <div class="no-print bg-white rounded-md border border-[#e6e7e9] shadow-sm p-5">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
            <i class="fa-solid fa-timeline text-orange-400"></i> कार्यक्रम लग
          </h3>
          <div class="relative">
            <div class="absolute left-3 top-0 bottom-0 w-px bg-gray-100"></div>
            <div class="space-y-4">
              <!-- Created -->
              <div class="relative pl-8">
                <div class="absolute left-0 top-1 w-6 h-6 rounded-full bg-white border-2 border-gray-200 flex items-center justify-center">
                  <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                </div>
                <div>
                  <div class="text-xs font-semibold text-gray-700">सुरु भयो</div>
                  <p class="text-xs text-gray-400 mt-0.5">{{ business.enumerator?.name || '—' }}</p>
                </div>
              </div>

              <!-- Submitted -->
              <div v-if="business.submitted_at" class="relative pl-8">
                <div class="absolute left-0 top-1 w-6 h-6 rounded-full bg-white border-2 border-yellow-300 flex items-center justify-center">
                  <div class="w-2 h-2 rounded-full bg-yellow-400"></div>
                </div>
                <div>
                  <div class="text-xs font-semibold text-gray-700">पेस भयो</div>
                  <p class="text-xs text-gray-400 mt-0.5">{{ formatDateTime(business.submitted_at) }}</p>
                </div>
              </div>

              <!-- Ward reviewed -->
              <div v-if="business.ward_reviewed_at" class="relative pl-8">
                <div class="absolute left-0 top-1 w-6 h-6 rounded-full bg-white border-2 flex items-center justify-center"
                  :class="business.status === 'rejected' ? 'border-red-300' : 'border-blue-300'">
                  <div class="w-2 h-2 rounded-full" :class="business.status === 'rejected' ? 'bg-red-400' : 'bg-blue-400'"></div>
                </div>
                <div>
                  <div class="text-xs font-semibold text-gray-700">
                    {{ business.status === 'rejected' ? 'अस्वीकृत' : 'वडा अनुमोदित' }}
                  </div>
                  <p class="text-xs text-gray-400 mt-0.5">{{ business.ward_reviewer?.name || '—' }}</p>
                  <p v-if="business.ward_review_comment" class="text-xs text-red-500 mt-0.5">{{ business.ward_review_comment }}</p>
                </div>
              </div>

              <!-- Published -->
              <div v-if="business.published_at" class="relative pl-8">
                <div class="absolute left-0 top-1 w-6 h-6 rounded-full bg-white border-2 border-green-300 flex items-center justify-center">
                  <div class="w-2 h-2 rounded-full bg-green-500"></div>
                </div>
                <div>
                  <div class="text-xs font-semibold text-gray-700">प्रकाशित</div>
                  <p class="text-xs text-gray-400 mt-0.5">{{ formatDateTime(business.published_at) }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- ── Edit Request Modal ──────────────────────────────────────── -->
  <Teleport to="body">
    <div v-if="showRequestModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
      @click.self="showRequestModal = false">
      <div class="bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-md mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-pen-to-square text-orange-500"></i>
          </div>
          <div>
            <h3 class="text-[15px] font-semibold text-gray-900">सम्पादन अनुरोध</h3>
            <p class="text-[12px] text-gray-400">व्यवसाय: <span class="font-mono">{{ business.unique_code }}</span></p>
          </div>
        </div>
        <p class="text-sm text-gray-600 mb-3">यो प्रकाशित रेकर्डमा सम्पादन आवश्यक पर्नुको कारण उल्लेख गर्नुहोस्:</p>
        <textarea
          v-model="editRequestReason"
          rows="4"
          placeholder="उदाहरण: व्यवसाय बन्द भएको छ / ठेगाना परिवर्तन भएको छ / नयाँ जानकारी थप्नु छ..."
          class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-orange-300 resize-none"
        ></textarea>
        <div class="flex gap-3 mt-4">
          <button @click="showRequestModal = false"
            class="flex-1 rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
            रद्द गर्नुस्
          </button>
          <button @click="submitEditRequest" :disabled="requestLoading || !editRequestReason.trim()"
            class="flex-1 rounded-md bg-orange-500 hover:bg-orange-600 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60">
            <i :class="requestLoading ? 'fa-solid fa-spinner fa-spin mr-1' : 'fa-solid fa-paper-plane mr-1'"></i>
            अनुरोध पठाउनुस्
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Reject modal -->
  <Teleport to="body">
    <div v-if="showBizRejectModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
      @click.self="showBizRejectModal = false">
      <div class="bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-md mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-circle-xmark text-red-500"></i>
          </div>
          <div>
            <h3 class="text-[15px] font-semibold text-gray-900">अस्वीकार गर्नुस्</h3>
            <p class="text-[12px] text-gray-400">व्यवसाय: <span class="font-mono">{{ business.unique_code }}</span></p>
          </div>
        </div>
        <p class="text-sm text-gray-600 mb-3">अस्वीकार गर्नुको कारण उल्लेख गर्नुहोस् (अनिवार्य):</p>
        <textarea
          v-model="bizRejectComment"
          rows="3"
          placeholder="उदाहरण: विवरण अपूर्ण छ / व्यवसाय दर्ता नम्बर गलत छ..."
          class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-300 resize-none"
        ></textarea>
        <div class="flex gap-3 mt-4">
          <button @click="showBizRejectModal = false"
            class="flex-1 rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
            रद्द गर्नुस्
          </button>
          <button @click="submitBizReject" :disabled="bizRejectLoading || !bizRejectComment.trim()"
            class="flex-1 rounded-md bg-red-600 hover:bg-red-700 px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60">
            <i :class="bizRejectLoading ? 'fa-solid fa-spinner fa-spin mr-1' : 'fa-solid fa-circle-xmark mr-1'"></i>
            अस्वीकार गर्नुस्
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, h } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import FieldVerificationCard from '@/components/FieldVerificationCard.vue'

const page = usePage()
function printPage() { window.print() }

const props = defineProps({
  business:               { type: Object,  required: true },
  member:                 { type: Object,  default: null },
  household:              { type: Object,  default: null },
  canEdit:                { type: Boolean, default: false },
  canSubmit:              { type: Boolean, default: false },
  canApprove:             { type: Boolean, default: false },
  canReject:              { type: Boolean, default: false },
  canPublish:             { type: Boolean, default: false },
  canRequestEdit:         { type: Boolean, default: false },
  hasPendingEditRequest:  { type: Boolean, default: false },
  canVerify:              { type: Boolean, default: false },
  fieldVerification:      { type: Object,  default: null },
  surveyOptions:          { type: Object,  default: () => ({}) },
})

const actionLoading = ref(false)

// ── Computed helpers ───────────────────────────────────────────────────────────

const regPlaces = computed(() => {
  const p = props.business.place_of_registration
  if (!p) return []
  if (Array.isArray(p)) return p
  try { return JSON.parse(p) } catch { return [] }
})

const hasRegNumbers = computed(() =>
  props.business.reg_no_municipality
  || props.business.reg_no_home_cottage
  || props.business.reg_no_company_registrar
  || props.business.reg_no_fcci
  || props.business.reg_no_govt_office
  || props.business.place_of_registration_other
)

// ── Inline sub-components ─────────────────────────────────────────────────────

const DetailRow = {
  props: ['label', 'value'],
  render() {
    return h('div', [
      h('p', { class: 'text-xs text-gray-400 mb-0.5' }, this.label),
      h('p', { class: 'text-sm font-medium text-gray-800' }, this.value ?? '—'),
    ])
  },
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function statusBadge(s) {
  const map = {
    draft:        'bg-gray-100 text-gray-600',
    submitted:    'bg-yellow-100 text-yellow-700',
    ward_approved:'bg-blue-100 text-blue-700',
    rejected:     'bg-red-100 text-red-700',
    published:    'bg-green-100 text-green-700',
  }
  return map[s] ?? 'bg-gray-100 text-gray-600'
}

function statusLabel(s) {
  const map = {
    draft:        'मस्यौदा',
    submitted:    'पेस भएको',
    ward_approved:'वडा अनुमोदित',
    rejected:     'अस्वीकृत',
    published:    'प्रकाशित',
  }
  return map[s] ?? s
}

function boolLabel(v) {
  if (v === true  || v === 1  || v === '1') return 'छ'
  if (v === false || v === 0  || v === '0') return 'छैन'
  return '—'
}

function formatDateTime(d) {
  if (!d) return ''
  return new Date(d).toLocaleString('ne-NP')
}

// ── Actions ───────────────────────────────────────────────────────────────────

const showBizRejectModal = ref(false)
const bizRejectComment   = ref('')
const bizRejectLoading   = ref(false)

function submitBusiness() {
  if (!confirm('के तपाईं यो व्यवसाय पेस गर्न निश्चित हुनुहुन्छ?')) return
  actionLoading.value = true
  router.post(`/businesses/${props.business.id}/submit`, {}, {
    onFinish: () => { actionLoading.value = false },
  })
}

function publishBusiness() {
  if (!confirm('के तपाईं यो व्यवसाय प्रकाशित गर्न निश्चित हुनुहुन्छ?')) return
  actionLoading.value = true
  router.patch(`/admin/businesses/${props.business.id}/publish`, {}, {
    onFinish: () => { actionLoading.value = false },
  })
}

function approveBusiness() {
  if (!confirm('के तपाईं यो व्यवसाय स्वीकृत गर्न निश्चित हुनुहुन्छ?')) return
  actionLoading.value = true
  router.patch(`/review/businesses/${props.business.id}/approve`, {}, {
    onFinish: () => { actionLoading.value = false },
  })
}

function submitBizReject() {
  if (!bizRejectComment.value.trim()) return
  bizRejectLoading.value = true
  router.patch(`/review/businesses/${props.business.id}/reject`, { comment: bizRejectComment.value }, {
    onSuccess: () => { showBizRejectModal.value = false; bizRejectComment.value = '' },
    onFinish:  () => { bizRejectLoading.value = false },
  })
}

// ── Edit Request ──────────────────────────────────────────────────────────────
const showRequestModal  = ref(false)
const editRequestReason = ref('')
const requestLoading    = ref(false)

function submitEditRequest() {
  if (!editRequestReason.value.trim()) return
  requestLoading.value = true
  router.post('/edit-requests', {
    requestable_type: 'business',
    requestable_id:   props.business.id,
    reason:           editRequestReason.value,
  }, {
    onSuccess: () => { showRequestModal.value = false; editRequestReason.value = '' },
    onFinish:  () => { requestLoading.value = false },
  })
}
</script>

<style>
@media print {
  .grid.grid-cols-1.lg\:grid-cols-3 {
    display: block !important;
  }
  .lg\:col-span-2,
  .space-y-4 > *,
  .space-y-5 > * {
    width: 100% !important;
    max-width: 100% !important;
    margin-bottom: 12pt !important;
  }
  .p-5 { padding: 10pt !important; }
  .grid.grid-cols-2,
  .grid.sm\:grid-cols-3 {
    grid-template-columns: repeat(3, 1fr) !important;
  }
}
</style>
