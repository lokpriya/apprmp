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
            <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center shrink-0">
                <i class="fa-solid fa-file-arrow-up text-indigo-600 text-lg"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900">संस्था तथ्याङ्क थोक आयात</h1>
                <p class="text-xs text-gray-500 mt-0.5">Institution Bulk Data Import — Excel फाइलबाट डेटा अपलोड गर्नुहोस्</p>
            </div>
        </div>
        <button wire:click="downloadTemplate"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-xl hover:bg-emerald-700 transition shadow-sm">
            <i class="fa-solid fa-download"></i>
            Template Download (.xlsx)
        </button>
    </div>

    {{-- ─── Sheet Structure Info ─────────────────────────────────────────────── --}}
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
        <div class="flex gap-3">
            <i class="fa-solid fa-circle-info text-indigo-500 text-base mt-0.5 shrink-0"></i>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-indigo-800 mb-1">Excel Template — 7 Data Sheets + 1 Reference Sheet</p>
                <p class="text-xs text-indigo-600 mb-3">All data sheets share <code class="bg-indigo-100 px-1 rounded font-mono">institution_code</code> as the link key. Only the Institutions sheet is required.</p>

                {{-- 7 data sheets --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-2">
                    @foreach([
                        ['Institutions',   'bg-blue-600',   'fa-building',        '16 cols — name, type, ward, GPS, ownership, registration, respondent, photo', true],
                        ['Infrastructure', 'bg-purple-600', 'fa-hammer',          '20 cols — building type/condition, rooms, facilities, toilets, safety features', false],
                        ['Staff',          'bg-emerald-700','fa-users',           '9 cols — one row per staff category: male/female counts, Dalit, Janajati, permanent/contract', false],
                        ['Education',      'bg-amber-600',  'fa-graduation-cap',  '8 cols — one row per grade: student counts, Dalit, Janajati, scholarship, dropout (schools only)', false],
                        ['Health',         'bg-red-700',    'fa-kit-medical',     '11 cols — bed count, delivery, emergency, lab, X-ray, ambulance, outpatient/inpatient avg (health only)', false],
                        ['Services',       'bg-sky-600',    'fa-hand-holding-heart','7 cols — one row per service: name, type, capacity, beneficiaries, coverage, is_free', false],
                        ['Teachers',       'bg-indigo-700', 'fa-chalkboard-user', '9 cols — one row per teacher: name, gender, category, qualification, level, specialization, subjects (schools only)', false],
                    ] as [$sheet, $color, $icon, $desc, $required])
                    <div class="flex items-start gap-2 bg-white/60 rounded-lg p-2">
                        <span class="inline-flex w-6 h-6 rounded items-center justify-center {{ $color }} shrink-0 mt-0.5">
                            <i class="fa-solid {{ $icon }} text-white text-xs"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-indigo-800 leading-tight">
                                {{ $sheet }}
                                @if($required)<span class="text-red-500 ml-0.5">*</span>@endif
                            </p>
                            <p class="text-[11px] text-indigo-600 leading-tight">{{ $desc }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Valid Values reference note --}}
                <div class="flex items-start gap-2 bg-white/60 border border-indigo-200 rounded-lg p-2">
                    <span class="inline-flex w-6 h-6 rounded items-center justify-center bg-gray-500 shrink-0 mt-0.5">
                        <i class="fa-solid fa-list-check text-white text-xs"></i>
                    </span>
                    <p class="text-[11px] text-indigo-700">
                        <span class="font-semibold">Valid Values</span> — Reference-only sheet (do not edit).
                        Auto-generated from live survey options. Lists all accepted values for every dropdown field across all sheets.
                        The hint row (row 2) in each sheet shows a live preview.
                    </p>
                </div>

                <p class="mt-2.5 text-xs text-indigo-600">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i>
                    Row 1 = column headers &nbsp;|&nbsp; Row 2 = hints (do not delete) &nbsp;|&nbsp; Row 3 onwards = your data.
                </p>
            </div>
        </div>
    </div>

    {{-- ─── Update Notice ───────────────────────────────────────────────────── --}}
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 flex gap-2.5 items-start text-xs text-amber-800">
        <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5 shrink-0"></i>
        <div>
            <strong>Updated template (v2):</strong>
            Teachers sheet added for individual teacher records (name, gender, category, qualification, subjects — schools only).
            All hint rows now dynamically reflect live survey options.
            <strong class="ml-1">Re-download the template if using an older version.</strong>
        </div>
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
                    class="w-full max-w-xs border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">— All wards (use ward_number from file) —</option>
                @foreach($wards as $ward)
                <option value="{{ $ward->id }}">Ward {{ $ward->ward_number }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">
                If selected, all imported institutions are forced to this ward regardless of the file value.
            </p>
        </div>

        {{-- Dropzone --}}
        <div x-data="{ dragging: false }"
             x-on:dragover.prevent="dragging = true"
             x-on:dragleave.prevent="dragging = false"
             x-on:drop.prevent="dragging = false"
             :class="dragging ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300 bg-gray-50'"
             class="border-2 border-dashed rounded-xl p-8 text-center transition-colors cursor-pointer">

            <input type="file" id="inst-bulk-file"
                   wire:model="file"
                   accept=".xlsx,.xls"
                   class="hidden">

            <label for="inst-bulk-file" class="cursor-pointer block">
                <i class="fa-solid fa-cloud-arrow-up text-4xl text-gray-300 mb-3 block"></i>
                <p class="text-sm text-gray-600">
                    <span class="font-semibold text-indigo-600 hover:underline">Click to browse</span>
                    &nbsp;or drag & drop your Excel file here
                </p>
                <p class="text-xs text-gray-400 mt-1">Accepts .xlsx / .xls — max 10 MB</p>
            </label>

            @if($file)
            <div class="mt-4 inline-flex items-center gap-2 bg-indigo-100 text-indigo-800 px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fa-solid fa-file-excel text-green-600"></i>
                {{ $file->getClientOriginalName() }}
                <button type="button" wire:click="$set('file', null)"
                        class="ml-1 text-indigo-400 hover:text-indigo-700">
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
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 transition shadow-sm disabled:opacity-60">
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

    {{-- ─── Processing (polls every 3 s until job completes) ─────────────────── --}}
    @if($status === 'uploading')
    <div wire:poll.3s="pollStatus"
         class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 flex flex-col items-center gap-4">
        <div class="w-16 h-16 rounded-full bg-indigo-100 flex items-center justify-center">
            <i class="fa-solid fa-spinner fa-spin text-indigo-600 text-2xl"></i>
        </div>
        <div class="text-center">
            <p class="text-base font-semibold text-gray-700">डेटा आयात हुँदैछ…</p>
            <p class="text-sm text-gray-400 mt-1">Import queued — processing in the background. This page will update automatically.</p>
        </div>
        <div class="w-48 h-1.5 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-indigo-500 rounded-full animate-pulse w-3/4"></div>
        </div>
        <p class="text-xs text-gray-300">Batch #{{ $batchId }}</p>
    </div>
    @endif

    {{-- ─── Success Result ────────────────────────────────────────────────────── --}}
    @if($status === 'done')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        {{-- Banner --}}
        <div class="bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-circle-check text-white text-2xl"></i>
            </div>
            <div class="text-white">
                <p class="text-lg font-bold">Import Complete!</p>
                <p class="text-indigo-100 text-sm">संस्था तथ्याङ्क सफलतापूर्वक आयात गरियो।</p>
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-3 divide-x divide-gray-100 border-b border-gray-100">
            <div class="px-6 py-4 text-center">
                <p class="text-3xl font-bold text-gray-900">{{ $importedCount }}</p>
                <p class="text-xs text-gray-500 mt-1 flex items-center justify-center gap-1">
                    <i class="fa-solid fa-building text-indigo-400"></i> Institutions Imported
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

        {{-- Split errors: validation vs regular --}}
        @if(!empty($errorLog))
        @php
            $validationErrors = collect($errorLog)
                ->filter(fn($e) => is_array($e) && ($e['type'] ?? '') === 'invalid_option')
                ->values()->toArray();
            $regularErrors = collect($errorLog)
                ->reject(fn($e) => is_array($e) && ($e['type'] ?? '') === 'invalid_option')
                ->values()->toArray();
            $byField = collect($validationErrors)
                ->groupBy('field')
                ->map(fn($rows) => [
                    'sheet'    => $rows->first()['sheet'] ?? '',
                    'category' => $rows->first()['category'] ?? '',
                    'rows'     => $rows->pluck('row')->sort()->values()->toArray(),
                    'values'   => $rows->pluck('value')->unique()->sort()->values()->toArray(),
                ])->toArray();
        @endphp

        {{-- Validation errors --}}
        @if(!empty($validationErrors))
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-amber-700 mb-1 flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
                Invalid Option Values — {{ count($validationErrors) }} row(s) skipped
            </h3>
            <p class="text-xs text-amber-600 mb-3">
                These rows were skipped because the values below do not match any valid survey option.
                Fix the values in your source file and re-upload.
            </p>
            <div class="max-h-72 overflow-y-auto rounded-lg border border-amber-200 bg-amber-50">
                <table class="w-full text-xs">
                    <thead class="bg-amber-100 text-amber-800 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold">Sheet</th>
                            <th class="px-4 py-2 text-left font-semibold">Field</th>
                            <th class="px-4 py-2 text-left font-semibold">Invalid Value(s)</th>
                            <th class="px-4 py-2 text-left font-semibold">Row(s)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-amber-100">
                        @foreach($byField as $field => $info)
                        <tr class="hover:bg-amber-100/60">
                            <td class="px-4 py-1.5 text-amber-600 font-medium">{{ $info['sheet'] }}</td>
                            <td class="px-4 py-1.5 font-mono text-amber-800 font-semibold">{{ $field }}</td>
                            <td class="px-4 py-1.5">
                                @foreach($info['values'] as $v)
                                <span class="inline-flex items-center mr-1 mb-0.5 px-1.5 py-0.5 rounded bg-red-100 text-red-700 font-mono text-[11px]">{{ $v }}</span>
                                @endforeach
                            </td>
                            <td class="px-4 py-1.5 text-amber-600 font-mono text-[11px]">
                                {{ count($info['rows']) > 6 ? implode(', ', array_slice($info['rows'], 0, 6)) . ' …+' . (count($info['rows']) - 6) . ' more' : implode(', ', $info['rows']) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-amber-600 flex items-center gap-1.5">
                <i class="fa-solid fa-circle-info"></i>
                Check the <strong>Valid Values</strong> sheet in the template for the list of accepted values per field.
            </p>
        </div>
        @endif

        {{-- Regular errors --}}
        @if(!empty($regularErrors))
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-red-700 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation"></i>
                Import Errors ({{ count($regularErrors) }})
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
                        @foreach($regularErrors as $i => $err)
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
        @endif

        <div class="px-6 pb-5 pt-3 flex flex-wrap gap-3 items-center">
            <button wire:click="resetImport"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition">
                <i class="fa-solid fa-rotate"></i> Import Another File
            </button>
            <a href="{{ route('institutions.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                <i class="fa-solid fa-list"></i> View Institutions
            </a>
            @if(!empty($errorLog))
            <button wire:click="downloadErrorCsv"
                    class="inline-flex items-center gap-2 px-4 py-2 border border-red-300 text-red-700 text-sm font-medium rounded-lg hover:bg-red-50 transition ml-auto">
                <i class="fa-solid fa-file-arrow-down"></i>
                त्रुटि सूची डाउनलोड गर्नुहोस् (.csv)
            </button>
            @endif
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
                            <i class="fa-solid fa-building text-gray-400"></i> Imported
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
                        'queued'     => 'bg-amber-100 text-amber-700',
                        default      => 'bg-gray-100 text-gray-500',
                    };
                    $si = match($batch['status']) {
                        'completed'  => 'fa-circle-check',
                        'failed'     => 'fa-circle-xmark',
                        'processing' => 'fa-spinner fa-spin',
                        'queued'     => 'fa-hourglass-half',
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
                ['Download the template',
                 'Click <strong>Template Download</strong> above. The file has <strong>8 sheets</strong> — 7 data sheets and 1 <em>Valid Values</em> reference sheet. Do not rename or reorder the sheets.',
                 'fa-download', 'bg-indigo-100 text-indigo-700'],

                ['Fill Institutions sheet first (required)',
                 'Enter one institution per row starting from <strong>row 3</strong>. Row 1 is headers, row 2 is the hint row — <strong>do not delete or edit row 2</strong>. <code class="bg-gray-100 px-1 rounded text-xs font-mono">institution_code</code> is auto-generated if left blank (format: IN-XXXX-WXX-XXXXX).',
                 'fa-building', 'bg-indigo-100 text-indigo-700'],

                ['Fill Infrastructure sheet — one row per institution',
                 'Covers building type, condition, total rooms/floors, land area, boundary wall, toilet breakdown (total/female/male/disabled), and 7 facility flags: accessible ramp, drinking water, electricity, solar, generator, internet, fire extinguisher, earthquake safety, evacuation plan.',
                 'fa-hammer', 'bg-purple-100 text-purple-700'],

                ['Fill Staff sheet — one row per staff category',
                 'Enter one row per staff category per institution (e.g. teacher, health_worker, admin). Columns: male/female count, Dalit/Janajati/other count, permanent/contract count. Applies to all institution types.',
                 'fa-users', 'bg-emerald-100 text-emerald-700'],

                ['Fill Education sheet — one row per grade (schools only)',
                 'For schools: enter one row per grade (ecd, grade_1 … grade_12). Columns: male/female students, Dalit/Janajati/scholarship/dropout counts. Leave blank for non-school institutions.',
                 'fa-graduation-cap', 'bg-amber-100 text-amber-700'],

                ['Fill Health sheet — one row per institution (health only)',
                 'For health posts/hospitals: bed count, delivery/emergency/lab/X-ray/ambulance service flags, monthly outpatient/inpatient averages, vaccination and family planning availability. Leave blank for non-health institutions.',
                 'fa-kit-medical', 'bg-red-100 text-red-700'],

                ['Fill Services sheet — one row per service',
                 'List each service offered (e.g. "Primary Education", "OPD Consultation"). Columns: service_name, service_type, capacity, annual_beneficiaries, coverage_description, is_free. Applies to all types.',
                 'fa-hand-holding-heart', 'bg-sky-100 text-sky-700'],

                ['Fill Teachers sheet — one row per teacher (schools only)',
                 'For schools: enter one row per teacher. Columns: full_name, gender, category (permanent/contract/…), qualification, appointment_level, specialization, subjects (pipe-separated e.g. math|science), enrollment_date. Stored in survey_data.',
                 'fa-chalkboard-user', 'bg-indigo-100 text-indigo-700'],

                ['Check Valid Values sheet for dropdown values',
                 'The <strong>Valid Values</strong> sheet lists every accepted value for each dropdown field. Also check the hint row (row 2) in each sheet — it shows a live preview pulled directly from your survey options.',
                 'fa-list-check', 'bg-gray-100 text-gray-700'],

                ['Upload and import',
                 'Save the file (.xlsx) and upload it here. All imported records are saved as <strong>Draft</strong> status and require ward reviewer approval before becoming published.',
                 'fa-upload', 'bg-indigo-100 text-indigo-700'],
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
