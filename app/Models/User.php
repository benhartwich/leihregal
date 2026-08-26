<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, Auditable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => UserRole::class,
            'active'            => 'boolean',
        ];
    }

    /**
     * Push-Abos dieses Kontos – ein Eintrag je Gerät (Phase 8).
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    // ── Role helpers ──────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isKurator(): bool
    {
        return in_array($this->role, [UserRole::Kurator, UserRole::Admin]);
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        $roles = array_map(
            fn($r) => $r instanceof UserRole ? $r : UserRole::from($r),
            $roles
        );
        return in_array($this->role, $roles);
    }
}
