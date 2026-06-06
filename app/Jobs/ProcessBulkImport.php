<?php

namespace App\Jobs;

use App\Imports\BusinessImport;
use App\Imports\HouseholdImport;
use App\Imports\InstitutionImport;
use App\Models\BulkImportBatch;
use App\Models\User;
use App\Services\SurveyValueValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProcessBulkImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    public function __construct(
        public readonly int     $batchId,
        public readonly string  $storedPath,
        public readonly string  $recordType,
        public readonly int     $uploaderId,
        public readonly ?int    $wardId,
    ) {}

    public function handle(): void
    {
        ini_set('memory_limit', '512M');

        $batch    = BulkImportBatch::findOrFail($this->batchId);
        $uploader = User::findOrFail($this->uploaderId);
        $fullPath = Storage::disk('local')->path($this->storedPath);

        $batch->update(['status' => 'processing']);

        try {
            $lgId   = $uploader->local_government_id;
            $wardId = $this->wardId;

            if ($this->recordType === 'household') {
                $import = new HouseholdImport($this->uploaderId, $wardId, new SurveyValueValidator($lgId));
                Excel::import($import, $fullPath);
                $errors = $import->getErrors();
                $batch->update([
                    'total_rows'     => $import->getTotalHouseholds() + count($errors),
                    'processed_rows' => $import->getTotalHouseholds(),
                    'error_rows'     => count($errors),
                    'status'         => 'completed',
                    'error_log'      => $errors ? array_values(array_slice($errors, 0, 200)) : null,
                    'completed_at'   => now(),
                ]);

            } elseif ($this->recordType === 'institution') {
                $import = new InstitutionImport($this->uploaderId, $wardId);
                Excel::import($import, $fullPath);
                $errors = $import->getErrors();
                $batch->update([
                    'total_rows'     => $import->getTotalInstitutions() + count($errors),
                    'processed_rows' => $import->getTotalInstitutions(),
                    'error_rows'     => count($errors),
                    'status'         => 'completed',
                    'error_log'      => $errors ? array_values(array_slice($errors, 0, 200)) : null,
                    'completed_at'   => now(),
                ]);

            } elseif ($this->recordType === 'business') {
                $import = new BusinessImport($this->uploaderId, $wardId, new SurveyValueValidator($lgId));
                Excel::import($import, $fullPath);
                $errors = $import->getErrors();
                $batch->update([
                    'total_rows'     => $import->getTotalBusinesses() + count($errors),
                    'processed_rows' => $import->getTotalBusinesses(),
                    'error_rows'     => count($errors),
                    'status'         => 'completed',
                    'error_log'      => $errors ? array_values(array_slice($errors, 0, 200)) : null,
                    'completed_at'   => now(),
                ]);
            }
        } catch (\Throwable $e) {
            $batch->update([
                'status'       => 'failed',
                'error_log'    => [['error' => $e->getMessage()]],
                'completed_at' => now(),
            ]);
        } finally {
            Storage::disk('local')->delete($this->storedPath);
        }
    }

    public function failed(\Throwable $e): void
    {
        Storage::disk('local')->delete($this->storedPath);

        $batch = BulkImportBatch::find($this->batchId);
        if ($batch && in_array($batch->status, ['queued', 'processing'])) {
            $batch->update([
                'status'       => 'failed',
                'error_log'    => [['error' => 'Job failed: ' . $e->getMessage()]],
                'completed_at' => now(),
            ]);
        }
    }
}
