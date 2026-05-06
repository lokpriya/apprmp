<?php

namespace App\Policies;

use App\Models\Institution;
use App\Models\User;

class InstitutionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('institutions.view');
    }

    public function view(User $user, Institution $institution): bool
    {
        if (!$user->hasPermission('institutions.view')) return false;
        if ($user->isAdmin()) return true;
        if ($user->isWardAdmin() || $user->isReviewer())
            return in_array($institution->ward_id, $user->getAssignedWardIds());
        if ($user->isEnumerator()) return $institution->enumerator_id === $user->id;
        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('institutions.create');
    }

    public function update(User $user, Institution $institution): bool
    {
        if ($institution->isFrozen()) return false;
        if (!$user->hasPermission('institutions.edit')) return false;
        if ($user->isAdmin()) return true;
        if ($user->isWardAdmin())
            return in_array($institution->ward_id, $user->getAssignedWardIds());
        return in_array($institution->status, ['draft', 'rejected', 'submitted'])
            && ($user->isReviewer() || $institution->enumerator_id === $user->id);
    }

    public function delete(User $user, Institution $institution): bool
    {
        if (!$user->hasPermission('institutions.delete')) return false;
        if ($user->isAdmin()) return !$institution->isFrozen();
        if ($user->isWardAdmin())
            return !$institution->isFrozen() && in_array($institution->ward_id, $user->getAssignedWardIds());
        return false;
    }

    public function approve(User $user, Institution $institution): bool
    {
        if (!$user->hasPermission('institutions.approve')) return false;
        if ($user->isAdmin()) return $institution->status === 'ward_approved';
        if ($user->isWardAdmin())
            return $institution->status === 'submitted'
                && in_array($institution->ward_id, $user->getAssignedWardIds());
        return $institution->status === 'submitted'
            && in_array($institution->ward_id, $user->getAssignedWardIds());
    }

    public function reject(User $user, Institution $institution): bool
    {
        if (!$user->hasPermission('institutions.reject')) return false;
        if ($user->isAdmin()) return in_array($institution->status, ['ward_approved', 'escalated', 'published']);
        if ($user->isWardAdmin())
            return in_array($institution->status, ['submitted', 'ward_approved'])
                && in_array($institution->ward_id, $user->getAssignedWardIds());
        return in_array($institution->status, ['submitted', 'ward_approved'])
            && in_array($institution->ward_id, $user->getAssignedWardIds());
    }

    public function publish(User $user, Institution $institution): bool
    {
        if (!$user->hasPermission('institutions.publish')) return false;
        if ($user->isAdmin()) return in_array($institution->status, ['ward_approved', 'escalated']);
        if ($user->isWardAdmin())
            return in_array($institution->status, ['ward_approved', 'escalated'])
                && in_array($institution->ward_id, $user->getAssignedWardIds());
        return false;
    }
}
