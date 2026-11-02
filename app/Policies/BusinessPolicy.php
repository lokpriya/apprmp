<?php

namespace App\Policies;

use App\Models\MemberBusiness;
use App\Models\User;

class BusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('businesses.view');
    }

    public function view(User $user, MemberBusiness $business): bool
    {
        if (!$user->hasPermission('businesses.view')) return false;
        if ($user->isAdmin()) return true;
        if ($user->isWardAdmin() || $user->isReviewer())
            return in_array($business->ward_id, $user->getAssignedWardIds());
        if ($user->isEnumerator()) return $business->enumerator_id === $user->id;
        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('businesses.create');
    }

    public function update(User $user, MemberBusiness $business): bool
    {
        if (!$user->hasPermission('businesses.edit')) return false;
        if ($user->isAdmin()) return true;
        if ($user->isWardAdmin())
            return in_array($business->ward_id, $user->getAssignedWardIds());
        // A reviewer only within their own wards (see HouseholdPolicy::update).
        return in_array($business->status, ['draft', 'rejected', 'submitted'])
            && (($user->isReviewer() && in_array($business->ward_id, $user->getAssignedWardIds()))
                || $business->enumerator_id === $user->id);
    }

    public function delete(User $user, MemberBusiness $business): bool
    {
        if (!$user->hasPermission('businesses.delete')) return false;
        if ($user->isAdmin()) return true;
        if ($user->isWardAdmin())
            return in_array($business->ward_id, $user->getAssignedWardIds());
        return false;
    }

    public function approve(User $user, MemberBusiness $business): bool
    {
        if (!$user->hasPermission('businesses.approve')) return false;
        if ($user->isAdmin()) return $business->status === 'ward_approved';
        if ($user->isWardAdmin())
            return $business->status === 'submitted'
                && in_array($business->ward_id, $user->getAssignedWardIds());
        return $business->status === 'submitted'
            && in_array($business->ward_id, $user->getAssignedWardIds());
    }

    public function reject(User $user, MemberBusiness $business): bool
    {
        if (!$user->hasPermission('businesses.reject')) return false;
        if ($user->isAdmin()) return in_array($business->status, ['ward_approved', 'escalated', 'published']);
        if ($user->isWardAdmin())
            return in_array($business->status, ['submitted', 'ward_approved'])
                && in_array($business->ward_id, $user->getAssignedWardIds());
        return in_array($business->status, ['submitted', 'ward_approved'])
            && in_array($business->ward_id, $user->getAssignedWardIds());
    }

    public function publish(User $user, MemberBusiness $business): bool
    {
        if (!$user->hasPermission('businesses.publish')) return false;
        if ($user->isAdmin()) return in_array($business->status, ['ward_approved', 'escalated']);
        if ($user->isWardAdmin())
            return in_array($business->status, ['ward_approved', 'escalated'])
                && in_array($business->ward_id, $user->getAssignedWardIds());
        return false;
    }
}
