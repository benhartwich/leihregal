<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loan extends Model
{
    protected $fillable = [
        'media_id',
        'user_id',
        'borrowed_at',
        'due_at',
        'returned_at',
        'rating',
        'rating_comment',
        'extension_count',
        'last_reminded_at',
        'due_soon_stage',
    ];

    protected function casts(): array
    {
        return [
            'borrowed_at'      => 'datetime',
            'due_at'           => 'datetime',
            'returned_at'      => 'datetime',
            'last_reminded_at' => 'datetime',
            'extension_count'  => 'integer',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function damageReports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DamageReport::class);
    }

    public function isReturned(): bool
    {
        return $this->returned_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isReturned() && $this->due_at->isPast();
    }

    public function daysOverdue(): int
    {
        if (! $this->isOverdue()) return 0;
        return (int) now()->diffInDays($this->due_at);
    }
}
