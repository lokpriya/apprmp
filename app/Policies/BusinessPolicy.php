<?php

namespace App\Policies;

use App\Models\MemberBusiness;
use App\Models\User;

class BusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MemberBusiness $business): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isReviewer()) return in_array($business->ward_id, $user->getAssignedWardIds());
        if ($user->isEnumerator()) return $business->enumerator_id === $user->id;
        return false;
    }

    public function create(User $user): bool
    {
        return $user->isEnumerator() || $user->isAdmin();
    }

    public function update(User $user, MemberBusiness $business): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isEnumerator()) {
            return $business->enumerator_id === $user->id
                && in_array($business->status, ['draft', 'rejected']);
        }
        return false;
    }

    public function delete(User $user, MemberBusiness $business): bool
    {
        return $user->isAdmin();
    }

    public function approve(User $user, MemberBusiness $business): bool
    {
        if ($user->isAdmin()) return $business->status === 'ward_approved';
        if ($user->isReviewer()) {
            return $business->status === 'submitted'
                && in_array($business->ward_id, $user->getAssignedWardIds());
        }
        return false;
    }

    public function reject(User $user, MemberBusiness $business): bool
    {
        if ($user->isAdmin()) return in_array($business->status, ['ward_approved', 'escalated', 'published']);
        if ($user->isReviewer()) {
            return in_array($business->status, ['submitted', 'ward_approved'])
                && in_array($business->ward_id, $user->getAssignedWardIds());
        }
        return false;
    }

    public function publish(User $user, MemberBusiness $business): bool
    {
        return $user->isAdmin() && in_array($business->status, ['ward_approved', 'escalated']);
    }
}
