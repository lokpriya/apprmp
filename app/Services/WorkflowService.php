<?php

namespace App\Services;

use App\Enums\RecordStatus;
use App\Models\Household;
use App\Models\Institution;
use App\Models\MemberBusiness;
use App\Models\SubmissionLog;
use App\Models\User;
use App\Notifications\SubmissionStatusNotification;
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

            if ($newStatus === RecordStatus::Submitted) {
                $updates['submitted_at'] = now();
            } elseif ($newStatus === RecordStatus::WardApproved) {
                $updates['ward_reviewed_at'] = now();
                $updates['ward_reviewer_id'] = $actor->id;
            } elseif ($newStatus === RecordStatus::Rejected) {
                $updates['ward_reviewed_at']    = now();
                $updates['ward_reviewer_id']    = $actor->id;
                $updates['ward_review_comment'] = $comment;
            } elseif ($newStatus === RecordStatus::Published) {
                $updates['published_at'] = now();
                $updates['published_by'] = $actor->id;
            }

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

        // Notify the enumerator (skip submitted status and when actor is the enumerator)
        $notifyStatuses = [
            RecordStatus::WardApproved->value,
            RecordStatus::Rejected->value,
            RecordStatus::Escalated->value,
            RecordStatus::Published->value,
        ];
        if (in_array($newStatus->value, $notifyStatuses) && isset($record->enumerator_id)) {
            $enumerator = User::find($record->enumerator_id);
            if ($enumerator && $enumerator->id !== $actor->id) {
                $recordType = match(true) {
                    $record instanceof Institution    => 'institution',
                    $record instanceof MemberBusiness => 'business',
                    default                           => 'household',
                };
                $enumerator->notify(new SubmissionStatusNotification(
                    $recordType, $record->id, $record->unique_code, $newStatus->value, $comment
                ));
            }
        }
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
