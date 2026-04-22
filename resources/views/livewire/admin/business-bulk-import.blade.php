<div class="min-h-screen bg-gray-50 py-8">
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

    {{-- ─── Stuck-batch recovery notice ───────────────────────────────────── --}}
    @if($recoveredCount > 0)
    <div class="flex items-start gap-3 bg-amber-50 border border-amber-300 rounded-xl px-4 py-3 text-sm text-amber-800">
        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <div>
            <span class="font-semibold">{{ $recoveredCount }} stuck import{{ $recoveredCount > 1 ? 's' : '' }} recovered.</span>
            A previous import was interrupted (likely due to insufficient memory). Any rows that were saved before the crash are preserved in the database. Please review the history below and re-import if needed.
        </div>
    </div>
    @endif

    {{-- ─── Page Header ─────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Bulk Business Data Upload</h1>
            <p class="text-sm text-gray-500 mt-1">व्यवसायको तथ्याङ्क एक्सेल फाइलबाट थोकमा अपलोड गर्नुहोस्</p>
        </div>
        <button wire:click="downloadTemplate"
                class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
            </svg>
            Download Template (.xlsx)
        </button>
    </div>

    {{-- ─── Template Info Card ──────────────────────────────────────────── --}}
    <div class="bg-orange-50 border border-orange-200 rounded-xl p-5">
        <div class="flex gap-3">
            <svg class="w-5 h-5 text-orange-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-sm text-orange-800">
                <p class="font-semibold mb-2">Excel Template Structure — 2 Sheets</p>
                <div class="grid grid-cols-2 gap-2">
                    <div class="flex items-start gap-1.5">
                        <span class="inline-block w-2.5 h-2.5 rounded-sm mt-0.5 flex-shrink-0 bg-orange-600"></span>
                        <div>
                            <p class="font-medium">Businesses</p>
                            <p class="text-xs text-orange-600">All business data — one row per business (required)</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-1.5">
                        <span class="inline-block w-2.5 h-2.5 rounded-sm mt-0.5 flex-shrink-0 bg-gray-500"></span>
                        <div>
                            <p class="font-medium">Valid Values</p>
                            <p class="text-xs text-orange-600">Reference sheet — allowed values for dropdown fields</p>
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-xs text-orange-600">
                    ⓘ Each row is linked to an existing household member using the
                    <code class="bg-orange-100 px-1 rounded">household_code</code> +
                    <code class="bg-orange-100 px-1 rounded">member_name</code> combination.
                    The <code class="bg-orange-100 px-1 rounded">business_code</code> is auto-generated if left blank.
                    Template values update in real-time from your survey options configuration.
                </p>
            </div>
        </div>
    </div>

    {{-- ─── Upload Form (idle state) ───────────────────────────────────── --}}
    @if($status === 'idle')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">
        <h2 class="text-lg font-semibold text-gray-800">Upload Excel File</h2>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Ward Filter <span class="text-gray-400">(optional)</span>
            </label>
            <select wire:model="wardId"
                    class="w-full max-w-xs border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                <option value="">— All wards (resolved from household) —</option>
                @foreach($wards as $ward)
                <option value="{{ $ward->id }}">Ward {{ $ward->ward_number }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">
                If selected, all imported businesses are assigned to this ward regardless of the household's ward.
            </p>
        </div>

        {{-- File dropzone --}}
        <div x-data="{ dragging: false }"
             x-on:dragover.prevent="dragging = true"
             x-on:dragleave.prevent="dragging = false"
             x-on:drop.prevent="dragging = false"
             :class="dragging ? 'border-orange-400 bg-orange-50' : 'border-gray-300 bg-gray-50'"
             class="border-2 border-dashed rounded-xl p-8 text-center transition-colors">

            <input type="file" id="biz-bulk-file"
                   wire:model="file"
                   accept=".xlsx,.xls"
                   class="hidden">

            <label for="biz-bulk-file" class="cursor-pointer block">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <p class="mt-3 text-sm text-gray-600">
                    <span class="font-medium text-orange-600 hover:underline">Click to browse</span>
                    or drag & drop your Excel file here
                </p>
                <p class="text-xs text-gray-400 mt-1">Accepts .xlsx / .xls — max 10 MB</p>
            </label>

            @if($file)
            <div class="mt-4 inline-flex items-center gap-2 bg-orange-100 text-orange-800 px-4 py-2 rounded-lg text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                {{ $file->getClientOriginalName() }}
                <button type="button" wire:click="$set('file', null)"
                        class="ml-1 text-orange-500 hover:text-orange-700">✕</button>
            </div>
            @endif
        </div>

        @error('file')
        <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror

        <div class="flex items-center gap-4 pt-2">
            <button wire:click="runImport"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-60 cursor-not-allowed"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-orange-600 text-white text-sm font-semibold rounded-lg hover:bg-orange-700 focus:ring-2 focus:ring-orange-500 transition disabled:opacity-60">
                <span wire:loading.remove wire:target="runImport">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                </span>
                <span wire:loading wire:target="runImport">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </span>
                <span wire:loading.remove wire:target="runImport">Start Import</span>
                <span wire:loading wire:target="runImport">Importing...</span>
            </button>
            <p class="text-xs text-gray-400">
                Data is saved as draft status — ward reviewers can approve after import.
            </p>
        </div>
    </div>
    @endif

    {{-- ─── Processing ──────────────────────────────────────────────────── --}}
    @if($status === 'uploading')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 flex flex-col items-center gap-4">
        <svg class="animate-spin h-10 w-10 text-orange-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <p class="text-lg font-semibold text-gray-700">Importing business data, please wait…</p>
        <p class="text-sm text-gray-400">Processing the Businesses sheet. This may take a moment for large files.</p>
    </div>
    @endif

    {{-- ─── Success Result ──────────────────────────────────────────────── --}}
    @if($status === 'done')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-emerald-600 px-6 py-5 flex items-center gap-4">
            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-emerald-500 flex items-center justify-center">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div class="text-white">
                <p class="text-xl font-bold">Import Complete</p>
                <p class="text-emerald-100 text-sm">Business data has been imported successfully.</p>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-0 divide-x divide-gray-100 border-b border-gray-100">
            <div class="px-6 py-4 text-center">
                <p class="text-3xl font-bold text-gray-900">{{ $importedCount }}</p>
                <p class="text-sm text-gray-500 mt-0.5">Businesses Imported</p>
            </div>
            <div class="px-6 py-4 text-center">
                <p class="text-3xl font-bold {{ $errorCount > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $errorCount }}</p>
                <p class="text-sm text-gray-500 mt-0.5">Rows with Errors</p>
            </div>
            <div class="px-6 py-4 text-center">
                <p class="text-sm font-medium text-amber-700 bg-amber-100 inline-block px-3 py-1 rounded-full mt-2">Draft Status</p>
                <p class="text-xs text-gray-400 mt-1">Pending ward approval</p>
            </div>
        </div>

        @if(!empty($errorLog))
        <div class="px-6 py-4">
            <h3 class="text-sm font-semibold text-red-700 mb-3 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Import Errors ({{ count($errorLog) }})
            </h3>
            <div class="max-h-60 overflow-y-auto rounded-lg border border-red-100 bg-red-50">
                <table class="w-full text-xs">
                    <thead class="bg-red-100 text-red-700 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">#</th>
                            <th class="px-4 py-2 text-left font-medium">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-red-100">
                        @foreach($errorLog as $i => $err)
                        <tr>
                            <td class="px-4 py-1.5 text-red-500 font-mono">{{ $i + 1 }}</td>
                            <td class="px-4 py-1.5 text-red-700">{{ is_array($err) ? ($err['error'] ?? json_encode($err)) : $err }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="px-6 pb-5 pt-2 flex gap-3">
            <button wire:click="resetImport"
                    class="px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-700 transition">
                Import Another File
            </button>
            <a href="{{ route('businesses.index') }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                View Businesses
            </a>
        </div>
    </div>
    @endif

    {{-- ─── Error State ─────────────────────────────────────────────────── --}}
    @if($status === 'error')
    <div class="bg-white rounded-xl shadow-sm border border-red-200 p-6">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <div>
                <h3 class="font-semibold text-red-800">Import Failed</h3>
                <p class="text-sm text-red-600 mt-1">{{ $errorMsg ?: 'An unexpected error occurred during import.' }}</p>
                <button wire:click="resetImport"
                        class="mt-4 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                    Try Again
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ─── Import History ──────────────────────────────────────────────── --}}
    @if(!empty($recentBatches))
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Recent Import History</h2>
            <span class="text-xs text-gray-400">Last 10 business imports by you</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">File</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Imported</th>
                        <th class="px-4 py-3 text-center">Errors</th>
                        <th class="px-4 py-3 text-left">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($recentBatches as $batch)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800 max-w-xs truncate">{{ $batch['filename'] }}</td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $statusColor = match($batch['status']) {
                                    'completed' => 'bg-emerald-100 text-emerald-700',
                                    'failed'    => 'bg-red-100 text-red-700',
                                    'processing'=> 'bg-blue-100 text-blue-700',
                                    default     => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ $statusColor }}">
                                {{ ucfirst($batch['status']) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-700">{{ $batch['processed_rows'] }}</td>
                        <td class="px-4 py-3 text-center {{ $batch['error_rows'] > 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                            {{ $batch['error_rows'] }}
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">
                            {{ $batch['completed_at'] ? \Carbon\Carbon::parse($batch['completed_at'])->format('Y-m-d H:i') : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ─── Usage Guide ─────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">How to use the Excel template</h2>
        <ol class="space-y-3 text-sm text-gray-600">
            <li class="flex gap-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-orange-100 text-orange-700 font-bold text-xs flex items-center justify-center">1</span>
                <span>Click <strong>Download Template</strong> above to get the pre-formatted Excel file. The template is generated in real-time from your current survey option configuration.</span>
            </li>
            <li class="flex gap-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-orange-100 text-orange-700 font-bold text-xs flex items-center justify-center">2</span>
                <span>Fill the <strong>Businesses</strong> sheet — one business per row starting from row 3. Row 1 has column names and row 2 has hints (do not delete either row).</span>
            </li>
            <li class="flex gap-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-orange-100 text-orange-700 font-bold text-xs flex items-center justify-center">3</span>
                <span>Each row requires a valid <strong>household_code</strong> (e.g. <code class="bg-gray-100 px-1 rounded text-xs">HH-0001-W01-00001</code>) and the exact <strong>member_name</strong> as recorded in the system. The member must not already have a business record.</span>
            </li>
            <li class="flex gap-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-orange-100 text-orange-700 font-bold text-xs flex items-center justify-center">4</span>
                <span>Leave <strong>business_code</strong> blank for auto-generation (format: BZ-XXXX-WXX-XXXXX). For <strong>place_of_registration</strong>, use comma-separated values (e.g. <code class="bg-gray-100 px-1 rounded text-xs">municipality,fcci</code>).</span>
            </li>
            <li class="flex gap-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-orange-100 text-orange-700 font-bold text-xs flex items-center justify-center">5</span>
                <span>Check the <strong>Valid Values</strong> sheet for all allowed codes in dropdown fields (business_type, business_field, business_location, registration places, sub-categories, etc.).</span>
            </li>
            <li class="flex gap-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-orange-100 text-orange-700 font-bold text-xs flex items-center justify-center">6</span>
                <span>Upload the completed file here. All records will be saved as <strong>Draft</strong> status for ward reviewer approval. The ward is auto-resolved from the linked household unless overridden above.</span>
            </li>
        </ol>
    </div>

</div>
</div>
