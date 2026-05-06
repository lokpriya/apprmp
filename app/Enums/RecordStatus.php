<?php
namespace App\Enums;

enum RecordStatus: string
{
    case Draft        = 'draft';
    case Submitted    = 'submitted';
    case WardApproved = 'ward_approved';
    case Rejected     = 'rejected';
    case Escalated    = 'escalated';
    case Published    = 'published';

    public function label(): string
    {
        return match($this) {
            self::Draft        => 'मस्यौदा',
            self::Submitted    => 'पेश भएको',
            self::WardApproved => 'वडाबाट स्वीकृत',
            self::Rejected     => 'अस्वीकृत',
            self::Escalated    => 'माथि पठाएको',
            self::Published    => 'प्रकाशित',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Draft        => 'bg-gray-100 text-gray-700',
            self::Submitted    => 'bg-blue-100 text-blue-700',
            self::WardApproved => 'bg-yellow-100 text-yellow-700',
            self::Rejected     => 'bg-red-100 text-red-700',
            self::Escalated    => 'bg-orange-100 text-orange-700',
            self::Published    => 'bg-green-100 text-green-700',
        };
    }

    public function canTransitionTo(self $next, string $role): bool
    {
        return match($role) {
            'enumerator' => match($this) {
                self::Draft     => $next === self::Submitted,
                self::Rejected  => $next === self::Submitted,
                default         => false,
            },
            'ward_reviewer' => match($this) {
                self::Submitted    => in_array($next, [self::WardApproved, self::Rejected, self::Escalated]),
                self::WardApproved => $next === self::Rejected,
                default            => false,
            },
            'ward_admin' => match($this) {
                self::Draft        => $next === self::Submitted,
                self::Submitted    => in_array($next, [self::WardApproved, self::Rejected, self::Escalated]),
                self::Rejected     => $next === self::Submitted,
                self::WardApproved => in_array($next, [self::Published, self::Rejected]),
                self::Escalated    => in_array($next, [self::Published, self::Rejected]),
                default            => false,
            },
            'municipal_admin', 'super_admin' => match($this) {
                self::Draft        => in_array($next, [self::Submitted, self::Published]),
                self::Submitted    => in_array($next, [self::WardApproved, self::Rejected, self::Escalated]),
                self::Rejected     => $next === self::Submitted,
                self::WardApproved => $next === self::Published,
                self::Escalated    => in_array($next, [self::Published, self::Rejected]),
                self::Published    => $next === self::Rejected,
                default            => false,
            },
            default => false,
        };
    }

    public static function options(): array
    {
        return array_map(fn($case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
