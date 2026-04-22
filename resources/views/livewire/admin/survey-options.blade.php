<div class="flex gap-6" style="min-height:600px">

    {{-- ══════════════════════════════════════════════════════════
         LEFT SIDEBAR — Category list
    ══════════════════════════════════════════════════════════ --}}
    <div class="w-64 shrink-0">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden sticky top-4">
            <div class="bg-gradient-to-r from-blue-900 to-blue-700 px-4 py-3">
                <p class="text-xs font-semibold text-blue-200 uppercase tracking-wider">विकल्प श्रेणीहरु</p>
                <p class="text-xs text-blue-300 mt-0.5">Option Categories</p>
            </div>

            {{-- Tab toggle --}}
            <div class="flex border-b border-gray-200">
                <button wire:key="tab-household"
                    wire:click="switchTab('household')"
                    class="flex-1 py-2.5 text-xs font-semibold transition-colors
                        {{ $activeTab === 'household'
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-50 text-gray-600 hover:bg-gray-100' }}">
                    🏠 घरधुरी
                </button>
                <button wire:key="tab-institution"
                    wire:click="switchTab('institution')"
                    class="flex-1 py-2.5 text-xs font-semibold transition-colors
                        {{ $activeTab === 'institution'
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-50 text-gray-600 hover:bg-gray-100' }}">
                    🏢 संस्था
                </button>
            </div>

            <div class="overflow-y-auto" style="max-height:490px">
                @foreach($categories as $key => $label)
                    @php
                        [$labelNe, $labelEn] = str_contains($label, '(')
                            ? [trim(substr($label, 0, strpos($label, '('))), trim(substr($label, strpos($label, '(')+1, -1))]
                            : [$label, ''];
                    @endphp
                    <button wire:key="cat-{{ $key }}"
                        wire:click="selectCategory('{{ $key }}')"
                        class="w-full text-left px-3 py-2.5 border-b border-gray-100 transition-colors
                            {{ $activeCategory === $key
                                ? 'bg-blue-50 border-l-4 border-l-blue-600'
                                : 'hover:bg-gray-50 border-l-4 border-l-transparent' }}">
                        <p class="text-xs font-semibold {{ $activeCategory === $key ? 'text-blue-700' : 'text-gray-700' }}">
                            {{ $labelNe }}
                        </p>
                        @if($labelEn)
                        <p class="text-xs {{ $activeCategory === $key ? 'text-blue-500' : 'text-gray-400' }}">
                            {{ $labelEn }}
                        </p>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         RIGHT PANEL — Options table + form
    ══════════════════════════════════════════════════════════ --}}
    <div class="flex-1 space-y-4">

        {{-- Error --}}
        @if($errorMsg)
        <div class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
            ⚠ {{ $errorMsg }}
        </div>
        @endif

        {{-- Flash --}}
        @if($flash)
        <div wire:key="flash-{{ $flash }}"
             x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
            <svg class="w-4 h-4 shrink-0 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
            {{ $flash }}
        </div>
        @endif

        {{-- Panel header --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4
                {{ $activeTab === 'institution'
                    ? 'bg-gradient-to-r from-indigo-900 to-indigo-700'
                    : 'bg-gradient-to-r from-blue-900 to-blue-700' }}">
                <div>
                    @php
                        $catLabel = $categories[$activeCategory] ?? $activeCategory;
                        [$catNe, $catEn] = str_contains($catLabel, '(')
                            ? [trim(substr($catLabel, 0, strpos($catLabel, '('))), trim(substr($catLabel, strpos($catLabel, '(')+1, -1))]
                            : [$catLabel, ''];
                    @endphp
                    <h3 class="text-sm font-semibold text-white">{{ $catNe }}</h3>
                    @if($catEn)<p class="text-xs text-blue-300">{{ $catEn }}</p>@endif
                </div>
                @if(!$showForm)
                <button wire:click="openAdd"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-white text-blue-700 text-xs font-semibold rounded-lg hover:bg-blue-50 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    नयाँ विकल्प थप्नुहोस्
                </button>
                @else
                <button wire:click="cancelForm"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-white/20 text-white text-xs font-medium rounded-lg hover:bg-white/30 transition">
                    ✕ फाराम बन्द गर्नुहोस्
                </button>
                @endif
            </div>

            {{-- ── ADD / EDIT FORM ─────────────────────────────── --}}
            @if($showForm)
            <div class="border-b border-gray-200 bg-blue-50 px-5 py-4">
                <h4 class="text-sm font-semibold text-gray-800 mb-3">
                    {{ $editId ? 'विकल्प सम्पादन' : 'नयाँ विकल्प थप्नुहोस्' }}
                    @if($editId)
                        <span class="ml-2 text-xs font-normal text-gray-500">(ID: {{ $editId }})</span>
                    @endif
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Value (slug) --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Value (Code) <span class="text-red-500">*</span>
                            <span class="font-normal text-gray-400 ml-1">letters, numbers, _ or -</span>
                        </label>
                        <input type="text"
                            wire:model="formValue"
                            placeholder="e.g. brahmin, piped_water"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                        @if($editId)
                            <p class="text-xs text-amber-600 mt-1">⚠ Value बदल्दा पुरानो सर्वेक्षण डेटामा असर पर्न सक्छ।</p>
                        @endif
                        @error('formValue')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    {{-- Sort order --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            क्रम संख्या (Sort Order)
                        </label>
                        <input type="number" wire:model="formSort" min="0"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                        @error('formSort')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    {{-- Label Nepali --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            नेपाली लेबल <span class="text-red-500">*</span>
                        </label>
                        <input type="text" wire:model="formLabelNe"
                            placeholder="उदाहरण: ब्राह्मण, धारा/पाइप"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                        @error('formLabelNe')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    {{-- Label English --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            English Label <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <input type="text" wire:model="formLabelEn"
                            placeholder="e.g. Brahmin, Piped Water"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                        @error('formLabelEn')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                {{-- Active toggle --}}
                <div class="flex items-center gap-3 mt-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="formActive" class="sr-only peer">
                        <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer
                                    peer-checked:after:translate-x-full peer-checked:after:border-white
                                    after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white
                                    after:rounded-full after:h-4 after:w-4 after:transition-all
                                    peer-checked:bg-blue-600"></div>
                    </label>
                    <span class="text-sm text-gray-700">सक्रिय (Active)</span>
                </div>
                {{-- Buttons --}}
                <div class="flex items-center gap-2 mt-4">
                    <button wire:click="save" wire:loading.attr="disabled"
                        class="px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">
                            {{ $editId ? 'अपडेट गर्नुहोस्' : 'सुरक्षित गर्नुहोस्' }}
                        </span>
                        <span wire:loading wire:target="save">कृपया प्रतीक्षा गर्नुहोस्…</span>
                    </button>
                    <button wire:click="cancelForm"
                        class="px-5 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">
                        रद्द गर्नुहोस्
                    </button>
                </div>
            </div>
            @endif

            {{-- ── OPTIONS TABLE ──────────────────────────────── --}}
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-12">#</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-40">Value</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">नेपाली लेबल</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 uppercase">English Label</th>
                            <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase w-24">स्थिति</th>
                            <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase w-32">कार्य</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($options as $opt)
                        <tr wire:key="opt-{{ $opt['id'] }}"
                            class="hover:bg-gray-50 {{ !$opt['is_active'] ? 'opacity-50' : '' }}">
                            {{-- Sort order --}}
                            <td class="px-3 py-2.5 text-gray-400 text-xs">{{ $opt['sort_order'] }}</td>
                            {{-- Value --}}
                            <td class="px-3 py-2.5">
                                <code class="text-xs bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded font-mono">{{ $opt['value'] }}</code>
                                @if($opt['local_government_id'] === null)
                                    <span class="ml-1 inline-flex items-center text-xs text-blue-600 bg-blue-50 border border-blue-200 px-1.5 py-0.5 rounded">
                                        Global
                                    </span>
                                @else
                                    <span class="ml-1 inline-flex items-center text-xs text-purple-600 bg-purple-50 border border-purple-200 px-1.5 py-0.5 rounded">
                                        Local
                                    </span>
                                @endif
                            </td>
                            {{-- Label Nepali --}}
                            <td class="px-3 py-2.5 text-gray-800">{{ $opt['label_ne'] }}</td>
                            {{-- Label English --}}
                            <td class="px-3 py-2.5 text-gray-500">{{ $opt['label_en'] ?? '—' }}</td>
                            {{-- Active toggle --}}
                            <td class="px-3 py-2.5 text-center">
                                <button wire:click="toggleActive({{ $opt['id'] }})"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium transition
                                        {{ $opt['is_active']
                                            ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                            : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">
                                    {{ $opt['is_active'] ? 'सक्रिय' : 'निष्क्रिय' }}
                                </button>
                            </td>
                            {{-- Actions — super admin can edit & delete all --}}
                            <td class="px-3 py-2.5 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button wire:click="openEdit({{ $opt['id'] }})"
                                        class="text-xs px-2 py-1 rounded bg-blue-50 text-blue-700 hover:bg-blue-100 font-medium transition">
                                        सम्पादन
                                    </button>
                                    <button wire:click="delete({{ $opt['id'] }})"
                                        wire:confirm="'{{ $opt['label_ne'] }}' मेटाउने? यो कार्य फिर्ता हुँदैन।"
                                        class="text-xs px-2 py-1 rounded bg-red-50 text-red-600 hover:bg-red-100 font-medium transition">
                                        मेटाउनुहोस्
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <div class="text-gray-400 text-sm">
                                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                    यस श्रेणीमा कुनै विकल्प छैन।<br>
                                    <span class="text-xs text-gray-400">Click "+ नयाँ विकल्प थप्नुहोस्" to add options.</span>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Footer count --}}
            @if(count($options) > 0)
            <div class="px-5 py-2 bg-gray-50 border-t border-gray-200">
                <p class="text-xs text-gray-500">
                    जम्मा {{ count($options) }} विकल्प —
                    <span class="text-blue-600">{{ collect($options)->where('local_government_id', null)->count() }} Global</span>,
                    <span class="text-purple-600">{{ collect($options)->whereNotNull('local_government_id')->count() }} Local</span>
                </p>
            </div>
            @endif
        </div>

        {{-- Help note --}}
        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-xs text-blue-700 space-y-1">
            <p class="font-semibold">ℹ️ निर्देशन (Super Admin):</p>
            <ul class="list-disc pl-4 space-y-0.5 text-blue-600">
                <li><strong>Global</strong> — सबै स्थानीय तहमा देखिने प्रणाली विकल्प। सम्पादन र मेटाउन मिल्छ।</li>
                <li><strong>Local</strong> — कुनै खास स्थानीय तहले थपेका विकल्प।</li>
                <li>नयाँ थप्दा <code class="bg-blue-100 px-1 rounded">value</code> मा अंग्रेजी अक्षर, अंक, _ वा - मात्र।</li>
                <li>नयाँ विकल्पहरु Global रूपमा थपिन्छन् (सबै स्थानीय तहमा लागू हुन्छ)।</li>
                <li>⚠ Value बदल्दा पुरानो सर्वेक्षण डेटा प्रभावित हुन सक्छ।</li>
            </ul>
        </div>
    </div>
</div>
