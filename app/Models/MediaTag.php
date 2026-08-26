<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaTag extends Model
{
    public $timestamps = false;

    protected $fillable = ['media_id', 'tag'];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
