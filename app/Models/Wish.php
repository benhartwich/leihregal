<?php

namespace App\Models;

use App\Enums\WishStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Wish extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id', 'title', 'isbn', 'topic_freetext',
        'status', 'cluster_id', 'curator_note',
    ];

    protected function casts(): array
    {
        return ['status' => WishStatus::class];
    }

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function suggestion(): HasOne { return $this->hasOne(AcquisitionSuggestion::class); }
}
