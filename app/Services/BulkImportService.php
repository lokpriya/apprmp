<?php

namespace App\Services;

use App\Imports\BusinessImport;
use App\Imports\HouseholdImport;
use App\Imports\InstitutionImport;
use App\Models\BulkImportBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class BulkImportService
{
    /**
     * Create a batch record and run the import synchronously.
     * Returns the completed BulkImportBatch.
     */
    /**
     * Mark any "processing" batches for the given user+type that are older
     * than 10 minutes as failed. Called on page mount to clean up after OOM crashes.
     */
    public static function recoverStuckBatches(string $recordType, int $userId): int
    {
        return BulkImportBatch::where('record_type', $recordType)
            ->where('user_id', $userId)
            ->where('status', 'processing')
            ->where('created_at', '<', now()->subMinutes(10))
            ->update([
                'status'       => 'failed',
                'error_log'    => json_encode([[
                    'error' => 'Import terminated unexpectedly (possibly out of memory). '
                             . 'Rows that were committed before the crash are preserved.',
                ]]),
                'completed_at' => now(),
            ]);
    }

    public function importHouseholds(
        UploadedFile $file,
        User $uploader,
        ?int $wardId = null
    ): BulkImportBatch {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        // Store file
        $path = $file->store('bulk-imports', 'local');

        $batch = BulkImportBatch::create([
            'user_id'     => $uploader->id,
            'filename'    => $file->getClientOriginalName(),
            'record_type' => 'household',
            'total_rows'  => 0,
            'processed_rows' => 0,
            'error_rows'  => 0,
            'status'      => 'processing',
        ]);

        try {
            $import = new HouseholdImport($uploader->id, $wardId);

            Excel::import($import, Storage::disk('local')->path($path));

            $errors = $import->getErrors();

            $batch->update([
                'total_rows'     => $import->getTotalHouseholds() + count($errors),
                'processed_rows' => $import->getTotalHouseholds(),
                'error_rows'     => count($errors),
                'status'         => 'completed',
                'error_log'      => $errors ? array_values(array_slice($errors, 0, 200)) : null,
                'completed_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            $batch->update([
                'status'    => 'failed',
                'error_log' => [['error' => $e->getMessage()]],
                'completed_at' => now(),
            ]);
        } finally {
            Storage::disk('local')->delete($path);
        }

        return $batch->fresh();
    }

    public function importInstitutions(
        UploadedFile $file,
        User $uploader,
        ?int $wardId = null
    ): BulkImportBatch {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $path = $file->store('bulk-imports', 'local');

        $batch = BulkImportBatch::create([
            'user_id'        => $uploader->id,
            'filename'       => $file->getClientOriginalName(),
            'record_type'    => 'institution',
            'total_rows'     => 0,
            'processed_rows' => 0,
            'error_rows'     => 0,
            'status'         => 'processing',
        ]);

        try {
            $import = new InstitutionImport($uploader->id, $wardId);

            Excel::import($import, Storage::disk('local')->path($path));

            $errors = $import->getErrors();

            $batch->update([
                'total_rows'     => $import->getTotalInstitutions() + count($errors),
                'processed_rows' => $import->getTotalInstitutions(),
                'error_rows'     => count($errors),
                'status'         => 'completed',
                'error_log'      => $errors ? array_values(array_slice($errors, 0, 200)) : null,
                'completed_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            $batch->update([
                'status'       => 'failed',
                'error_log'    => [['error' => $e->getMessage()]],
                'completed_at' => now(),
            ]);
        } finally {
            Storage::disk('local')->delete($path);
        }

        return $batch->fresh();
    }

    public function importBusinesses(
        UploadedFile $file,
        User $uploader,
        ?int $wardId = null
    ): BulkImportBatch {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $path = $file->store('bulk-imports', 'local');

        $batch = BulkImportBatch::create([
            'user_id'        => $uploader->id,
            'filename'       => $file->getClientOriginalName(),
            'record_type'    => 'business',
            'total_rows'     => 0,
            'processed_rows' => 0,
            'error_rows'     => 0,
            'status'         => 'processing',
        ]);

        try {
            $import = new BusinessImport($uploader->id, $wardId);

            Excel::import($import, Storage::disk('local')->path($path));

            $errors = $import->getErrors();

            $batch->update([
                'total_rows'     => $import->getTotalBusinesses() + count($errors),
                'processed_rows' => $import->getTotalBusinesses(),
                'error_rows'     => count($errors),
                'status'         => 'completed',
                'error_log'      => $errors ? array_values(array_slice($errors, 0, 200)) : null,
                'completed_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            $batch->update([
                'status'       => 'failed',
                'error_log'    => [['error' => $e->getMessage()]],
                'completed_at' => now(),
            ]);
        } finally {
            Storage::disk('local')->delete($path);
        }

        return $batch->fresh();
    }

    /**
     * Return a summary array for displaying results.
     */
    public function summary(BulkImportBatch $batch): array
    {
        return [
            'filename'   => $batch->filename,
            'status'     => $batch->status,
            'imported'   => $batch->processed_rows,
            'errors'     => $batch->error_rows,
            'error_log'  => $batch->error_log ?? [],
            'completed'  => $batch->completed_at?->format('Y-m-d H:i:s'),
        ];
    }
}
