<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SubmissionStatusNotification extends Notification
{
    public function __construct(
        public readonly string $recordType,   // 'household' | 'institution' | 'business'
        public readonly int    $recordId,
        public readonly string $uniqueCode,
        public readonly string $newStatus,    // ward_approved | rejected | escalated | published
        public readonly ?string $comment = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $labels = [
            'ward_approved' => ['title' => 'अनुमोदित', 'icon' => 'check',      'color' => 'green'],
            'rejected'      => ['title' => 'अस्वीकृत', 'icon' => 'xmark',      'color' => 'red'],
            'escalated'     => ['title' => 'Escalate भयो', 'icon' => 'arrow-up', 'color' => 'orange'],
            'published'     => ['title' => 'प्रकाशित',   'icon' => 'globe',     'color' => 'blue'],
        ];

        $typeLabels = [
            'household'   => 'घरधुरी',
            'institution' => 'संस्था',
            'business'    => 'व्यवसाय',
        ];

        $meta = $labels[$this->newStatus] ?? ['title' => $this->newStatus, 'icon' => 'bell', 'color' => 'gray'];

        return [
            'record_type' => $this->recordType,
            'record_id'   => $this->recordId,
            'unique_code' => $this->uniqueCode,
            'status'      => $this->newStatus,
            'title'       => ($typeLabels[$this->recordType] ?? $this->recordType) . ' ' . $meta['title'],
            'message'     => $this->comment,
            'icon'        => $meta['icon'],
            'color'       => $meta['color'],
            'url'         => '/' . $this->routePrefix() . '/' . $this->recordId,
        ];
    }

    private function routePrefix(): string
    {
        return match ($this->recordType) {
            'institution' => 'institutions',
            'business'    => 'businesses',
            default       => 'households',
        };
    }
}
