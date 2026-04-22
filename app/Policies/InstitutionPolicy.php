<?php

namespace App\Policies;

use App\Models\Institution;
use App\Models\User;

class InstitutionPolicy
{
    public function viewAny(User $user): bool  { return true; }

    public function view(User $user, Institution $institution): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isReviewer()) return in_array($institution->ward_id, $user->getAssignedWardIds());
        if ($user->isEnumerator()) return $institution->enumerator_id === $user->id;
        return false;
    }

    public function create(User $user): bool
    {
        return $user->isEnumerator() || $user->isAdmin();
    }

    public function update(User $user, Institution $institution): bool
    {
        if ($institution->isFrozen()) return false;
        if ($user->isAdmin()) return true;
        if ($user->isEnumerator()) {
            return $institution->enumerator_id === $user->id
                && in_array($institution->status, ['draft', 'rejected']);
        }
        return false;
    }

    public function delete(User $user, Institution $institution): bool
    {
        return $user->isAdmin() && ! $institution->isFrozen();
    }

    public function approve(User $user, Institution $institution): bool
    {
        if ($user->isAdmin()) return $institution->status === 'ward_approved';
        if ($user->isReviewer()) {
            return $institution->status === 'submitted'
                && in_array($institution->ward_id, $user->getAssignedWardIds());
        }
        return false;
    }

    public function publish(User $user, Institution $institution): bool
    {
        return $user->isAdmin() && in_array($institution->status, ['ward_approved', 'escalated']);
    }
}
