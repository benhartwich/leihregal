<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Push-Abonnement eines Geräts (Phase 8).
 */
class PushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
        'geraet',
        'last_used_at',
    ];

    protected $hidden = ['public_key', 'auth_token'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Endpunkte sind zu lang für einen normalen Unique-Index – gespeichert
     * wird deshalb zusätzlich ihr Hash.
     */
    public static function hashFor(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
