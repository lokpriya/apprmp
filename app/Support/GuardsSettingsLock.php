<?php

namespace App\Support;

use App\Models\LocalGovernment;

/**
 * The settings lock, for controllers outside SettingsController.
 *
 * Locking is meant to freeze configuration for every role until a super admin
 * unlocks it. SettingsController checked it action by action, and the actions
 * that lived elsewhere — survey options, survey rounds — never checked it at
 * all, so a "locked" site could still have its answer lists rewritten.
 *
 * Read fresh from the database rather than through the LG cache: a lock that
 * has just been switched on must take effect on the very next request.
 */
trait GuardsSettingsLock
{
    protected function abortIfSettingsLocked(): void
    {
        $locked = LocalGovernment::whereKey(auth()->user()?->local_government_id)
            ->whereNotNull('settings_locked_at')
            ->exists();

        abort_if($locked, 403, 'Settings are locked. Unlock first to make changes.');
    }
}
