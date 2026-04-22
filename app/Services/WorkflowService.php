<?php

namespace App\Services;

use App\Enums\RecordStatus;
use App\Models\Household;
use App\Models\Institution;
use App\Models\SubmissionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    public function transition(Model $record, RecordStatus $newStatus, User $actor, ?string $comment = null): void
    {
        $currentStatus = RecordStatus::from($record->status);

        if (! $currentStatus->canTransitionTo($newStatus, $actor->role)) {
            throw new \RuntimeException("Cannot transition from {$currentStatus->value} to {$newStatus->value} as {$actor->role}.");
        }

        DB::transaction(function () use ($record, $newStatus, $actor, $comment, $currentStatus) {
            $updates = ['status' => $newStatus->value];

            match ($newStatus) {
                RecordStatus::Submitted    => $updates['submitted_at'] = now(),
                RecordStatus::WardApproved => array_merge($updates, ['ward_reviewed_at' => now(), 'ward_reviewer_id' => $actor->id]),
                RecordStatus::Rejected     => array_merge($updates, ['ward_reviewed_at' => now(), 'ward_reviewer_id' => $actor->id, 'ward_review_comment' => $comment]),
                RecordStatus::Published    => array_merge($updates, ['published_at' => now(), 'published_by' => $actor->id]),
                default => null,
            };

            $record->update($updates);

            SubmissionLog::create([
                'loggable_type' => get_class($record),
                'loggable_id'   => $record->id,
                'from_status'   => $currentStatus->value,
                'to_status'     => $newStatus->value,
                'actor_id'      => $actor->id,
                'comment'       => $comment,
            ]);
        });
    }

    public function submit(Model $record, User $actor): void
    {
        $this->transition($record, RecordStatus::Submitted, $actor);
    }

    public function wardApprove(Model $record, User $actor, ?string $comment = null): void
    {
        $this->transition($record, RecordStatus::WardApproved, $actor, $comment);
    }

    public function reject(Model $record, User $actor, string $comment): void
    {
        $this->transition($record, RecordStatus::Rejected, $actor, $comment);
    }

    public function escalate(Model $record, User $actor, ?string $comment = null): void
    {
        $this->transition($record, RecordStatus::Escalated, $actor, $comment);
    }

    public function publish(Model $record, User $actor): void
    {
        $this->transition($record, RecordStatus::Published, $actor);
    }
}
