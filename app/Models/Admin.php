<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Dashboard operator. Lives in its own table/guard — NOT a player.
 */
class Admin extends Authenticatable implements FilamentUser
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'created_by',
        'permissions',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    // ── Role helpers ─────────────────────────────────────────────────
    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSubadmin(): bool
    {
        return $this->role === 'subadmin';
    }

    /** Superadmin and admin can manage other admins (admin only manages subadmins). */
    public function canManageAdmins(): bool
    {
        return $this->isSuperadmin() || $this->isAdmin();
    }

    /** Whether this operator may view a given resource (key). */
    public function canViewResource(string $key): bool
    {
        if ($this->isSuperadmin() || $this->isAdmin()) {
            return true;
        }

        return in_array($key, $this->permissions ?? [], true);
    }

    // ── Relationships ────────────────────────────────────────────────
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Admin::class, 'created_by');
    }

    /** Only active operators can access the dashboard. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === 'active';
    }
}
