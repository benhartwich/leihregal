<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaBookmark extends Model
{
    protected $fillable = ['user_id', 'media_id'];

    public function media(): BelongsTo { return $this->belongsTo(Media::class); }
    public function user(): BelongsTo  { return $this->belongsTo(User::class); }
}
