<?php

namespace App\Support;

use App\Models\Household;
use App\Models\Settlement;
use App\Models\User;
use App\Models\Ward;

/**
 * The one answer to "which toles' श्रमदान may this user see?".
 *
 * Fails closed like the survey list queries: a role not named here gets no
 * toles at all, so a role added later sees nothing until someone decides
 * otherwise.
 */
final class ShramdaanAccess
{
    /** May this user open the module at all? Module on + permission. */
    public static function canUse(?User $user): bool
    {
        return $user
            && Modules::enabled(Modules::SHRAMDAAN, $user->local_government_id)
            && $user->hasPermission('shramdaan.view');
    }

    /** @return int[] settlement ids, always inside the user's municipality */
    public static function settlementIds(User $user): array
    {
        $inLg = Settlement::query()
            ->join('wards', 'wards.id', '=', 'settlements.ward_id')
            ->where('wards.local_government_id', $user->local_government_id);

        if ($user->isAdmin()) {
            // all toles of the municipality
        } elseif ($user->isWardAdmin() || $user->isReviewer()) {
            $inLg->whereIn('settlements.ward_id', $user->assignedWards()->pluck('wards.id'));
        } elseif ($user->isToleCoordinator()) {
            $inLg->whereIn('settlements.id', $user->assignedSettlements()->pluck('settlements.id'));
        } else {
            return [];
        }

        return $inLg->pluck('settlements.id')->map(fn($id) => (int) $id)->all();
    }

    /**
     * Wards whose coordinators and योजना list this user manages
     * (shramdaan.manage): the whole municipality for admins, the assigned wards
     * for ward staff, nothing for anyone else — a coordinator manages nothing.
     *
     * @return int[]
     */
    public static function managedWardIds(User $user): array
    {
        if (!$user->hasPermission('shramdaan.manage')) return [];

        if ($user->isAdmin()) {
            return Ward::where('local_government_id', $user->local_government_id)
                ->pluck('id')->map(fn($id) => (int) $id)->all();
        }
        if ($user->isWardAdmin() || $user->isReviewer()) {
            return $user->assignedWards()->pluck('wards.id')->map(fn($id) => (int) $id)->all();
        }

        return [];
    }

    /**
     * May this user open this family's passbook? Published, not deleted, and in
     * one of the user's toles. Returning 404 for the rest keeps other toles'
     * household ids from being probed.
     */
    public static function authorizeHousehold(User $user, Household $household): void
    {
        abort_unless(
            $household->status === 'published'
            && !$household->trashed()
            && $household->settlement_id
            && in_array((int) $household->settlement_id, self::settlementIds($user), true),
            404
        );
    }
}
