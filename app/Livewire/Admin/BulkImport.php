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
            $batch = app(BulkImportService::class)->importHouseholds(
                $this->file,
                auth()->user(),
                $this->wardId ?: null
            );

            $this->batchId = $batch->id;
            $this->file    = null;
            $this->loadRecentBatches();

        } catch (\Throwable $e) {
            $this->status   = 'error';
            $this->errorMsg = $e->getMessage();
        }
    }

    public function pollStatus(): void
    {
        if (!$this->batchId || $this->status !== 'uploading') return;

        $batch = BulkImportBatch::find($this->batchId);
        if (!$batch) return;

        if ($batch->status === 'completed') {
            $this->importedCount = $batch->processed_rows;
            $this->errorCount    = $batch->error_rows;
            $this->errorLog      = $batch->error_log ?? [];
            $this->status        = 'done';
            $this->loadRecentBatches();
        } elseif ($batch->status === 'failed') {
            $this->errorLog = $batch->error_log ?? [];
            $this->errorMsg = $this->errorLog[0]['error'] ?? 'Import failed.';
            $this->status   = 'error';
            $this->loadRecentBatches();
        }
    }

    public function downloadErrorCsv()
    {
        if (empty($this->errorLog)) return;

        $rows   = [['Type', 'Sheet', 'Row', 'Field', 'Invalid Value / Error']];
        foreach ($this->errorLog as $err) {
            if (is_array($err) && ($err['type'] ?? '') === 'invalid_option') {
                $rows[] = ['Invalid Option', $err['sheet'] ?? '', $err['row'] ?? '', $err['field'] ?? '', $err['value'] ?? ''];
            } else {
                $msg    = is_array($err) ? ($err['error'] ?? json_encode($err)) : $err;
                $rows[] = ['Error', '', '', '', $msg];
            }
        }

        $filename = 'import_errors_' . date('Ymd_His') . '.csv';
        return response()->streamDownload(function () use ($rows) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            foreach ($rows as $row) fputcsv($f, $row);
            fclose($f);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
