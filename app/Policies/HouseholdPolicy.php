<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\User;

class HouseholdPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Household $household): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isReviewer()) return in_array($household->ward_id, $user->getAssignedWardIds());
        if ($user->isEnumerator()) return $household->enumerator_id === $user->id;
        return false;
    }

    public function create(User $user): bool
    {
        return $user->isEnumerator() || $user->isAdmin();
    }

    public function update(User $user, Household $household): bool
    {
        if ($household->isFrozen()) return false;
        if ($user->isAdmin()) return true;
        if ($user->isEnumerator()) {
            return $household->enumerator_id === $user->id
                && in_array($household->status, ['draft', 'rejected']);
        }
        return false;
    }

    public function delete(User $user, Household $household): bool
    {
        return $user->isAdmin() && ! $household->isFrozen();
    }

    public function approve(User $user, Household $household): bool
    {
        if ($user->isAdmin()) return $household->status === 'ward_approved';
        if ($user->isReviewer()) {
            return $household->status === 'submitted'
                && in_array($household->ward_id, $user->getAssignedWardIds());
        }
        return false;
    }

    public function reject(User $user, Household $household): bool
    {
        if ($user->isAdmin()) return in_array($household->status, ['ward_approved', 'escalated', 'published']);
        if ($user->isReviewer()) {
            return in_array($household->status, ['submitted', 'ward_approved'])
                && in_array($household->ward_id, $user->getAssignedWardIds());
        }
        return false;
    }

    public function publish(User $user, Household $household): bool
    {
        return $user->isAdmin() && in_array($household->status, ['ward_approved', 'escalated']);
    }
}
