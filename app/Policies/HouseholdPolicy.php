<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\User;

class HouseholdPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('households.view');
    }

    public function view(User $user, Household $household): bool
    {
        if (!$user->hasPermission('households.view')) return false;
        if ($user->isAdmin()) return true;
        if ($user->isWardAdmin() || $user->isReviewer())
            return in_array($household->ward_id, $user->getAssignedWardIds());
        if ($user->isEnumerator()) return $household->enumerator_id === $user->id;
        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('households.create');
    }

    public function update(User $user, Household $household): bool
    {
        if ($household->isFrozen()) return false;
        if (!$user->hasPermission('households.edit')) return false;
        if ($user->isAdmin()) return true;
        if ($user->isWardAdmin())
            return in_array($household->ward_id, $user->getAssignedWardIds());
        return in_array($household->status, ['draft', 'rejected', 'submitted'])
            && ($user->isReviewer() || $household->enumerator_id === $user->id);
    }

    public function delete(User $user, Household $household): bool
    {
        if (!$user->hasPermission('households.delete')) return false;
        if ($user->isAdmin()) return !$household->isFrozen();
        if ($user->isWardAdmin())
            return !$household->isFrozen() && in_array($household->ward_id, $user->getAssignedWardIds());
        return false;
    }

    public function approve(User $user, Household $household): bool
    {
        if (!$user->hasPermission('households.approve')) return false;
        if ($user->isAdmin()) return $household->status === 'ward_approved';
        if ($user->isWardAdmin())
            return $household->status === 'submitted'
                && in_array($household->ward_id, $user->getAssignedWardIds());
        return $household->status === 'submitted'
            && in_array($household->ward_id, $user->getAssignedWardIds());
    }

    public function reject(User $user, Household $household): bool
    {
        if (!$user->hasPermission('households.reject')) return false;
        if ($user->isAdmin()) return in_array($household->status, ['ward_approved', 'escalated', 'published']);
        if ($user->isWardAdmin())
            return in_array($household->status, ['submitted', 'ward_approved'])
                && in_array($household->ward_id, $user->getAssignedWardIds());
        return in_array($household->status, ['submitted', 'ward_approved'])
            && in_array($household->ward_id, $user->getAssignedWardIds());
    }

    public function publish(User $user, Household $household): bool
    {
        if (!$user->hasPermission('households.publish')) return false;
        if ($user->isAdmin()) return in_array($household->status, ['ward_approved', 'escalated']);
        if ($user->isWardAdmin())
            return in_array($household->status, ['ward_approved', 'escalated'])
                && in_array($household->ward_id, $user->getAssignedWardIds());
        return false;
    }
}
