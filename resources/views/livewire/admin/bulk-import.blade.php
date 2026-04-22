<div class="space-y-5">

    {{-- ─── Stuck-batch recovery notice ───────────────────────────────────────── --}}
    @if($recoveredCount > 0)
    <div class="flex items-start gap-3 bg-amber-50 border border-amber-300 rounded-xl px-4 py-3 text-sm text-amber-800">
        <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5 shrink-0"></i>
        <div>
            <span class="font-semibold">{{ $recoveredCount }} stuck import{{ $recoveredCount > 1 ? 's' : '' }} recovered.</span>
            A previous import was interrupted (likely due to insufficient memory). Any rows that were saved before the crash are preserved in the database. Please review the history below and re-import if needed.
        </div>
    </div>
    @endif

    {{-- ─── Page Header ──────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center shrink-0">
                <i class="fa-solid fa-file-arrow-up text-blue-600 text-lg"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900">घरधुरी तथ्याङ्क थोक आयात</h1>
                <p class="text-xs text-gray-500 mt-0.5">Household Bulk Data Import — Excel फाइलबाट डेटा अपलोड गर्नुहोस्</p>
            </div>
        </div>
        <button wire:click="downloadTemplate"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-xl hover:bg-emerald-700 transition shadow-sm">
            <i class="fa-solid fa-download"></i>
            Template Download (.xlsx)
        </button>
    </div>

    {{-- ─── Sheet Structure Info ─────────────────────────────────────────────── --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <div class="flex gap-3">
            <i class="fa-solid fa-circle-info text-blue-500 text-base mt-0.5 shrink-0"></i>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-blue-800 mb-3">Excel Template — 8 Sheets</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach([
                        ['Households',   'bg-blue-600',    'fa-house',           'Core household info',       true],
                        ['Members',      'bg-emerald-600', 'fa-people-group',    'Family members',            false],
                        ['Housing',      'bg-purple-600',  'fa-building',        'House + land unit',         false],
                        ['Utilities',    'bg-sky-600',     'fa-bolt',            'Water, power, sanitation',  false],
                        ['Economic',     'bg-amber-600',   'fa-coins',           'Income, loans, bank',       false],
                        ['Agriculture',  'bg-green-700',   'fa-seedling',        'Land, crops (6 categories)',false],
                        ['Livestock',    'bg-orange-700',  'fa-cow',             'Animals + bee/fish/silk',   false],
                        ['Valid Values', 'bg-gray-600',    'fa-list-check',      'Reference sheet',           false],
                    ] as [$sheet, $color, $icon, $desc, $required])
                    <div class="flex items-start gap-2 bg-white/60 rounded-lg p-2">
                        <span class="inline-flex w-6 h-6 rounded items-center justify-center {{ $color }} shrink-0 mt-0.5">
                            <i class="fa-solid {{ $icon }} text-white text-xs"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-blue-800 leading-tight">
                                {{ $sheet }}
                                @if($required)<span class="text-red-500 ml-0.5">*</span>@endif
                            </p>
                            <p class="text-xs text-blue-600 leading-tight">{{ $desc }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
                <p class="mt-2.5 text-xs text-blue-600">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i>
                    Only <strong>Households</strong> sheet is required. All others are optional.
                    Use <code class="bg-blue-100 px-1 rounded font-mono">household_code</code> to link rows across sheets.
                </p>
            </div>
        </div>
    </div>

    {{-- ─── New Fields Notice ────────────────────────────────────────────────── --}}
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 flex gap-2.5 items-start text-xs text-amber-800">
        <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5 shrink-0"></i>
        <span>
            <strong>Updated template (v2):</strong>
            Now includes GPS altitude & accuracy, land measurement unit, 6 crop categories with sub-crops,
            bee/fish/silk data, and decision-making fields. Re-download the template if using an older version.
        </span>
    </div>

    {{-- ─── Upload Form ──────────────────────────────────────────────────────── --}}
    @if($status === 'idle')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 space-y-5">

        {{-- Ward selector --}}
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5 flex items-center gap-1.5">
                <i class="fa-solid fa-map-pin text-indigo-400"></i>
                Ward Filter
                <span class="text-gray-400 font-normal text-xs">(optional)</span>
            </label>
            <select wire:model="wardId"
                    class="w-full max-w-xs border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">— All wards (use ward_number from file) —</option>
                @foreach($wards as $ward)
                <option value="{{ $ward->id }}">Ward {{ $ward->ward_number }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">
                If selected, all imported households are forced to this ward regardless of the file value.
            </p>
        </div>

        {{-- Dropzone --}}
        <div x-data="{ dragging: false }"
             x-on:dragover.prevent="dragging = true"
             x-on:dragleave.prevent="dragging = false"
             x-on:drop.prevent="dragging = false"
             :class="dragging ? 'border-blue-400 bg-blue-50' : 'border-gray-300 bg-gray-50'"
             class="border-2 border-dashed rounded-xl p-8 text-center transition-colors cursor-pointer">

            <input type="file" id="bulk-file"
                   wire:model="file"
                   accept=".xlsx,.xls"
                   class="hidden">

            <label for="bulk-file" class="cursor-pointer block">
                <i class="fa-solid fa-cloud-arrow-up text-4xl text-gray-300 mb-3 block"></i>
                <p class="text-sm text-gray-600">
                    <span class="font-semibold text-blue-600 hover:underline">Click to browse</span>
                    &nbsp;or drag & drop your Excel file here
                </p>
                <p class="text-xs text-gray-400 mt-1">Accepts .xlsx / .xls — max 10 MB</p>
            </label>

            @if($file)
            <div class="mt-4 inline-flex items-center gap-2 bg-blue-100 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fa-solid fa-file-excel text-green-600"></i>
                {{ $file->getClientOriginalName() }}
                <button type="button" wire:click="$set('file', null)"
                        class="ml-1 text-blue-400 hover:text-blue-700">
                    <i class="fa-solid fa-circle-xmark"></i>
                </button>
            </div>
            @endif
        </div>

        @error('file')
        <p class="text-sm text-red-600 flex items-center gap-1.5">
            <i class="fa-solid fa-circle-exclamation"></i> {{ $message }}
        </p>
        @enderror

        <div class="flex items-center gap-4 pt-1">
            <button wire:click="runImport"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-60 cursor-not-allowed"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 transition shadow-sm disabled:opacity-60">
                <span wire:loading.remove wire:target="runImport">
                    <i class="fa-solid fa-upload"></i>
                </span>
                <span wire:loading wire:target="runImport">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                </span>
                <span wire:loading.remove wire:target="runImport">Start Import</span>
                <span wire:loading wire:target="runImport">Importing…</span>
            </button>
            <p class="text-xs text-gray-400 flex items-center gap-1">
                <i class="fa-solid fa-shield-halved text-amber-400"></i>
                Imported records are saved as <strong class="mx-0.5">Draft</strong> — pending ward reviewer approval.
            </p>
        </div>
    </div>
    @endif

    {{-- ─── Processing ───────────────────────────────────────────────────────── --}}
    @if($status === 'uploading')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 flex flex-col items-center gap-4">
        <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center">
            <i class="fa-solid fa-spinner fa-spin text-blue-600 text-2xl"></i>
        </div>
        <div class="text-center">
            <p class="text-base font-semibold text-gray-700">डेटा आयात हुँदैछ…</p>
            <p class="text-sm text-gray-400 mt-1">Processing all sheets. This may take a moment for large files.</p>
        </div>
        <div class="w-48 h-1.5 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-blue-500 rounded-full animate-pulse w-3/4"></div>
        </div>
    </div>
    @endif

    {{-- ─── Success Result ────────────────────────────────────────────────────── --}}
    @if($status === 'done')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        {{-- Banner --}}
        <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-circle-check text-white text-2xl"></i>
            </div>
            <div class="text-white">
                <p class="text-lg font-bold">Import Complete!</p>
                <p class="text-emerald-100 text-sm">घरधुरी तथ्याङ्क सफलतापूर्वक आयात गरियो।</p>
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-3 divide-x divide-gray-100 border-b border-gray-100">
            <div class="px-6 py-4 text-center">
                <p class="text-3xl font-bold text-gray-900">{{ $importedCount }}</p>
                <p class="text-xs text-gray-500 mt-1 flex items-center justify-center gap-1">
                    <i class="fa-solid fa-house text-blue-400"></i> Households Imported
                </p>
            </div>
            <div class="px-6 py-4 text-center">
                <p class="text-3xl font-bold {{ $errorCount > 0 ? 'text-red-600' : 'text-gray-300' }}">{{ $errorCount }}</p>
                <p class="text-xs text-gray-500 mt-1 flex items-center justify-center gap-1">
                    <i class="fa-solid fa-triangle-exclamation {{ $errorCount > 0 ? 'text-red-400' : 'text-gray-300' }}"></i>
                    Rows with Errors
                </p>
            </div>
            <div class="px-6 py-4 flex flex-col items-center justify-center">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-amber-100 text-amber-700">
                    <i class="fa-solid fa-clock"></i> Draft — Pending Approval
                </span>
                <p class="text-xs text-gray-400 mt-1.5">Ward reviewer approval required</p>
            </div>
        </div>

        {{-- Error log --}}
        @if(!empty($errorLog))
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-red-700 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation"></i>
                Import Errors ({{ count($errorLog) }})
            </h3>
            <div class="max-h-56 overflow-y-auto rounded-lg border border-red-100 bg-red-50">
                <table class="w-full text-xs">
                    <thead class="bg-red-100 text-red-700 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold w-10">#</th>
                            <th class="px-4 py-2 text-left font-semibold">Error Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-red-100">
                        @foreach($errorLog as $i => $err)
                        <tr class="hover:bg-red-100/50">
                            <td class="px-4 py-1.5 text-red-400 font-mono">{{ $i + 1 }}</td>
                            <td class="px-4 py-1.5 text-red-700">{{ is_array($err) ? ($err['error'] ?? json_encode($err)) : $err }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="px-6 pb-5 pt-3 flex gap-3">
            <button wire:click="resetImport"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition">
                <i class="fa-solid fa-rotate"></i> Import Another File
            </button>
            <a href="{{ route('households.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                <i class="fa-solid fa-list"></i> View Households
            </a>
        </div>
    </div>
    @endif

    {{-- ─── Error State ───────────────────────────────────────────────────────── --}}
    @if($status === 'error')
    <div class="bg-white rounded-xl shadow-sm border border-red-200 p-6">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-circle-xmark text-red-600 text-xl"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-semibold text-red-800">Import Failed</h3>
                <p class="text-sm text-red-600 mt-1">{{ $errorMsg ?: 'An unexpected error occurred during import.' }}</p>
                <button wire:click="resetImport"
                        class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition">
                    <i class="fa-solid fa-rotate"></i> Try Again
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ─── Import History ────────────────────────────────────────────────────── --}}
    @if(!empty($recentBatches))
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-gray-400"></i>
                Recent Import History
            </h2>
            <span class="text-xs text-gray-400">Last 10 imports by you</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left">File</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">
                            <i class="fa-solid fa-house text-gray-400"></i> Imported
                        </th>
                        <th class="px-4 py-3 text-center">
                            <i class="fa-solid fa-triangle-exclamation text-gray-400"></i> Errors
                        </th>
                        <th class="px-4 py-3 text-left">
                            <i class="fa-solid fa-calendar text-gray-400"></i> Date
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($recentBatches as $batch)
                    @php
                    $sc = match($batch['status']) {
                        'completed'  => 'bg-emerald-100 text-emerald-700',
                        'failed'     => 'bg-red-100 text-red-600',
                        'processing' => 'bg-blue-100 text-blue-700',
                        default      => 'bg-gray-100 text-gray-500',
                    };
                    $si = match($batch['status']) {
                        'completed'  => 'fa-circle-check',
                        'failed'     => 'fa-circle-xmark',
                        'processing' => 'fa-spinner fa-spin',
                        default      => 'fa-clock',
                    };
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800 max-w-xs truncate">
                            <i class="fa-solid fa-file-excel text-green-500 mr-1.5"></i>
                            {{ $batch['filename'] }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $sc }}">
                                <i class="fa-solid {{ $si }} text-xs"></i>
                                {{ ucfirst($batch['status']) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center font-semibold text-gray-700">{{ $batch['processed_rows'] }}</td>
                        <td class="px-4 py-3 text-center font-semibold {{ $batch['error_rows'] > 0 ? 'text-red-600' : 'text-gray-300' }}">
                            {{ $batch['error_rows'] ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">
                            {{ $batch['completed_at'] ? \Carbon\Carbon::parse($batch['completed_at'])->format('d M Y, H:i') : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ─── Usage Guide ───────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-circle-question text-indigo-400"></i>
            How to use the Excel template
        </h2>
        <ol class="space-y-3">
            @foreach([
                ['Download the template', 'Click <strong>Template Download</strong> above. The file has 8 sheets — 7 data sheets and 1 Valid Values reference.', 'fa-download', 'bg-blue-100 text-blue-700'],
                ['Fill Households sheet first', 'Enter one household per row starting from row 3. Row 2 is the hint row — <strong>do not delete it</strong>. Only this sheet is required.', 'fa-house', 'bg-blue-100 text-blue-700'],
                ['Link other sheets with household_code', 'If filling Members, Housing, Agriculture etc., use the same <code class="bg-gray-100 px-1 rounded text-xs font-mono">household_code</code> to connect related rows. Leave blank for auto-generation.', 'fa-link', 'bg-blue-100 text-blue-700'],
                ['Fill Agriculture sheet for crop data', 'The Agriculture sheet now supports 6 crop categories (food, pulses, oil, vegetables, cash crops, fruits). Enter sub-crops as comma-separated values.', 'fa-seedling', 'bg-green-100 text-green-700'],
                ['Check Valid Values sheet', 'Refer to the <strong>Valid Values</strong> sheet for allowed dropdown values (roof_type, ethnicity, land_measurement_unit, crop types, etc.).', 'fa-list-check', 'bg-gray-100 text-gray-700'],
                ['Upload and import', 'Save the file and upload it here. All records will be set to <strong>Draft</strong> status pending ward reviewer approval.', 'fa-upload', 'bg-blue-100 text-blue-700'],
            ] as [$title, $desc, $icon, $badgeCls])
            <li class="flex gap-3 text-sm text-gray-600">
                <span class="flex-shrink-0 w-7 h-7 rounded-full {{ $badgeCls }} font-bold text-xs flex items-center justify-center mt-0.5">
                    <i class="fa-solid {{ $icon }}"></i>
                </span>
                <div>
                    <p class="font-semibold text-gray-700 mb-0.5">{{ $title }}</p>
                    <p class="text-xs text-gray-500 leading-relaxed">{!! $desc !!}</p>
                </div>
            </li>
            @endforeach
        </ol>
    </div>

</div>
