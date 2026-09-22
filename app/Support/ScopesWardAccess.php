<?php

namespace App\Support;

use App\Models\User;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

/**
 * One definition of "which wards may this user touch", for the forms that file
 * records against a ward.
 *
 * The list pages already scope their queries and fail closed on an unknown role.
 * The create/edit forms did not: they offered every ward in the municipality to
 * everyone, and store()/update() validated `ward_id` with nothing but
 * `exists:wards,id`. A ward admin assigned to ward 1 who picked ward 2 by
 * mistake therefore *succeeded* — the record was created in ward 2 with a ward 2
 * unique_code, and the redirect to the show page then failed the view policy.
 * The user saw an error and assumed nothing had been saved, while in fact a
 * record existed in a ward they could no longer open, edit or delete.
 *
 * So the ward is constrained in three places, and all three matter:
 *   - the dropdown only offers permitted wards (prevents the mistake)
 *   - wardRule() rejects anything else (catches a tampered or stale request)
 *   - the pre-selection removes the choice entirely for single-ward users
 */
trait ScopesWardAccess
{
    /**
     * Ward ids this user may file records under.
     *
     * Fails closed, like the list queries: an unrecognised role gets an empty
     * array, which makes wardRule() reject every ward rather than accept any.
     */
    protected function scopedWardIds(?User $user = null): array
    {
        $user ??= auth()->user();
        if (!$user) return [];

        if ($user->isAdmin()) {
            return Ward::where('local_government_id', $user->local_government_id)
                ->orderBy('ward_number')
                ->pluck('id')
                ->all();
        }

        if ($user->isWardAdmin() || $user->isReviewer() || $user->isEnumerator()) {
            return $user->assignedWards()
                ->orderBy('ward_number')
                ->pluck('wards.id')
                ->all();
        }

        return [];
    }

    /**
     * The wards to offer in a form's dropdown.
     *
     * $currentWardId is the ward the record being edited already sits in. It is
     * always included even when it is outside the user's assignments, because
     * dropping it would blank the select and silently move the record to
     * whatever the user picked next. That can happen for real: an admin may
     * reassign wards while someone has a draft open.
     */
    protected function selectableWards(?int $currentWardId = null): Collection
    {
        $ids = $this->scopedWardIds();

        if ($currentWardId && !in_array($currentWardId, $ids, true)) {
            $ids[] = $currentWardId;
        }

        return Ward::whereIn('id', $ids)
            ->orderBy('ward_number')
            ->get(['id', 'ward_number']);
    }

    /**
     * The ward to pre-select on the form: the record's own ward when editing,
     * otherwise the user's only ward if they have exactly one. Users with
     * several wards still choose for themselves.
     */
    protected function defaultWardId(?int $currentWardId = null): ?int
    {
        if ($currentWardId) return $currentWardId;

        $ids = $this->scopedWardIds();

        return count($ids) === 1 ? (int) $ids[0] : null;
    }

    /**
     * Validation rule for a submitted ward_id.
     *
     * Rule::in() over ids read from the database also proves the ward exists, so
     * this replaces `exists:wards,id` rather than sitting beside it.
     */
    protected function wardRule(?int $currentWardId = null): array
    {
        $ids = $this->scopedWardIds();

        if ($currentWardId && !in_array($currentWardId, $ids, true)) {
            $ids[] = $currentWardId;
        }

        return ['required', 'integer', Rule::in($ids)];
    }

    /** Shown when someone submits a ward they are not assigned to. */
    protected function wardRuleMessages(): array
    {
        return [
            'ward_id.in'       => 'तपाईंलाई यो वडामा विवरण दर्ता गर्ने अनुमति छैन।',
            'ward_id.required' => 'वडा नं. आवश्यक छ।',
        ];
    }
}
