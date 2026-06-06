<?php

namespace App\Services;

use App\Jobs\ProcessBulkImport;
use App\Models\BulkImportBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class BulkImportService
{
    /**
     * Mark stuck batches (queued > 30 min, processing > 10 min) as failed.
     * Called on page mount to clean up after crashes or lost queue workers.
     */
    public static function recoverStuckBatches(string $recordType, int $userId): int
    {
        $count = 0;

        $count += BulkImportBatch::where('record_type', $recordType)
            ->where('user_id', $userId)
            ->where('status', 'queued')
            ->where('created_at', '<', now()->subMinutes(30))
            ->update([
                'status'       => 'failed',
                'error_log'    => json_encode([['error' => 'Import was queued but never processed. Check that the queue worker is running.']]),
                'completed_at' => now(),
            ]);

        $count += BulkImportBatch::where('record_type', $recordType)
            ->where('user_id', $userId)
            ->where('status', 'processing')
            ->where('created_at', '<', now()->subMinutes(10))
            ->update([
                'status'       => 'failed',
                'error_log'    => json_encode([['error' => 'Import terminated unexpectedly (possibly out of memory). Rows committed before the crash are preserved.']]),
                'completed_at' => now(),
            ]);

        return $count;
    }

    public function importHouseholds(UploadedFile $file, User $uploader, ?int $wardId = null): BulkImportBatch
    {
        return $this->dispatchImport($file, $uploader, $wardId, 'household');
    }

    public function importInstitutions(UploadedFile $file, User $uploader, ?int $wardId = null): BulkImportBatch
    {
        return $this->dispatchImport($file, $uploader, $wardId, 'institution');
    }

    public function importBusinesses(UploadedFile $file, User $uploader, ?int $wardId = null): BulkImportBatch
    {
        return $this->dispatchImport($file, $uploader, $wardId, 'business');
    }

    private function dispatchImport(UploadedFile $file, User $uploader, ?int $wardId, string $recordType): BulkImportBatch
    {
        $path = $file->store('bulk-imports', 'local');

        $batch = BulkImportBatch::create([
            'user_id'        => $uploader->id,
            'filename'       => $file->getClientOriginalName(),
            'record_type'    => $recordType,
            'total_rows'     => 0,
            'processed_rows' => 0,
            'error_rows'     => 0,
            'status'         => 'queued',
        ]);

        ProcessBulkImport::dispatch($batch->id, $path, $recordType, $uploader->id, $wardId);

        return $batch;
    }

    public function summary(BulkImportBatch $batch): array
    {
        return [
            'filename'  => $batch->filename,
            'status'    => $batch->status,
            'imported'  => $batch->processed_rows,
            'errors'    => $batch->error_rows,
            'error_log' => $batch->error_log ?? [],
            'completed' => $batch->completed_at?->format('Y-m-d H:i:s'),
        ];
    }
}
