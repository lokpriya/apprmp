<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'local_government_id',
        'phone', 'is_active', 'last_login_at', 'login_attempts', 'locked_until',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected $casts = [
        'email_verified_at'       => 'datetime',
        'password'                => 'hashed',
        'is_active'               => 'boolean',
        'last_login_at'           => 'datetime',
        'locked_until'            => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
    ];

    public function localGovernment(): BelongsTo
    {
        return $this->belongsTo(LocalGovernment::class);
    }

    public function assignedWards(): BelongsToMany
    {
        return $this->belongsToMany(Ward::class, 'user_ward_assignments')
            ->withPivot('assigned_at', 'assigned_by');
    }

    /** Toles a tole coordinator looks after (श्रमदान). */
    public function assignedSettlements(): BelongsToMany
    {
        return $this->belongsToMany(Settlement::class, 'user_settlement_assignments')
            ->withPivot('assigned_at', 'assigned_by');
    }

    public function households(): HasMany
    {
        return $this->hasMany(Household::class, 'enumerator_id');
    }

    public function institutions(): HasMany
    {
        return $this->hasMany(Institution::class, 'enumerator_id');
    }

    // Role helpers
    public function isSuperAdmin(): bool  { return $this->role === 'super_admin'; }
    public function isAdmin(): bool       { return in_array($this->role, ['super_admin', 'municipal_admin']); }
    public function isWardAdmin(): bool   { return $this->role === 'ward_admin'; }
    public function isReviewer(): bool    { return $this->role === 'ward_reviewer'; }
    public function isEnumerator(): bool  { return $this->role === 'enumerator'; }
    public function isToleCoordinator(): bool { return $this->role === 'tole_coordinator'; }

    public function hasPermission(string $perm): bool
    {
        if ($this->isSuperAdmin()) return true;
        $lg = $this->localGovernment;
        $perms = \App\Support\RolePermissions::effective($this->role, $lg?->role_permissions);
        return in_array($perm, $perms);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return !is_null($this->two_factor_confirmed_at) && !is_null($this->two_factor_secret);
    }

    public function twoFactorRequiredForRole(): bool
    {
        return in_array($this->role, ['super_admin', 'municipal_admin']);
    }

    public function getRecoveryCodes(): array
    {
        if (!$this->two_factor_recovery_codes) return [];
        return json_decode(decrypt($this->two_factor_recovery_codes), true) ?? [];
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function getAssignedWardIds(): array
    {
        return $this->assignedWards()->pluck('ward_id')->toArray();
    }
}
