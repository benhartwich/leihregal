<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaReview extends Model
{
    protected $fillable = ['media_id', 'user_id', 'rating', 'review', 'takeaway'];

    public function media(): BelongsTo  { return $this->belongsTo(Media::class); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
}
