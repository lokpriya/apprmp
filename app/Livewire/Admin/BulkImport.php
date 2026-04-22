<?php

namespace App\Livewire\Admin;

use App\Exports\HouseholdImportTemplate;
use App\Models\BulkImportBatch;
use App\Models\Ward;
use App\Services\BulkImportService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

class BulkImport extends Component
{
    use WithFileUploads;

    // Upload
    public $file      = null;
    public ?int $wardId = null;

    // State
    public string $status    = 'idle';   // idle | uploading | done | error
    public string $errorMsg  = '';

    // Result
    public int   $importedCount = 0;
    public int   $errorCount    = 0;
    public array $errorLog      = [];
    public ?int  $batchId       = null;

    // History
    public array $recentBatches  = [];
    public int   $recoveredCount = 0;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin(), 403);
        $this->recoveredCount = BulkImportService::recoverStuckBatches('household', auth()->id());
        $this->loadRecentBatches();
    }

    public function loadRecentBatches(): void
    {
        $this->recentBatches = BulkImportBatch::where('record_type', 'household')
            ->where('user_id', auth()->id())
            ->latest()
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function downloadTemplate()
    {
        $lgId = auth()->user()->local_government_id;
        return Excel::download(
            new HouseholdImportTemplate($lgId),
            'household_import_template_' . date('Ymd') . '.xlsx'
        );
    }

    public function runImport(): void
    {
        $this->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // 10 MB max
        ], [
            'file.required' => 'Please upload an Excel file.',
            'file.mimes'    => 'Only .xlsx or .xls files are accepted.',
            'file.max'      => 'File must be under 10 MB.',
        ]);

        $this->status   = 'uploading';
        $this->errorMsg = '';

        try {
            /** @var BulkImportService $service */
            $service = app(BulkImportService::class);

            $batch = $service->importHouseholds(
                $this->file,
                auth()->user(),
                $this->wardId ?: null
            );

            $this->batchId      = $batch->id;
            $this->importedCount = $batch->processed_rows;
            $this->errorCount    = $batch->error_rows;
            $this->errorLog      = $batch->error_log ?? [];
            $this->status        = $batch->status === 'failed' ? 'error' : 'done';

            if ($batch->status === 'failed' && !empty($this->errorLog)) {
                $this->errorMsg = $this->errorLog[0]['error'] ?? 'Import failed.';
            }

            $this->file = null;
            $this->loadRecentBatches();

        } catch (\Throwable $e) {
            $this->status   = 'error';
            $this->errorMsg = $e->getMessage();
        }
    }

    public function resetImport(): void
    {
        $this->status        = 'idle';
        $this->errorMsg      = '';
        $this->importedCount = 0;
        $this->errorCount    = 0;
        $this->errorLog      = [];
        $this->batchId       = null;
        $this->file          = null;
        $this->wardId        = null;
        $this->resetValidation();
    }

    public function render()
    {
        $wards = auth()->user()->isSuperAdmin()
            ? Ward::orderBy('ward_number')->get()
            : Ward::where('local_government_id', auth()->user()->local_government_id)
                  ->orderBy('ward_number')
                  ->get();

        return view('livewire.admin.bulk-import', [
            'wards' => $wards,
        ]);
    }
}
