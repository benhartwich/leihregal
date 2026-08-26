<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaEmbedding extends Model
{
    public $incrementing = false;
    protected $primaryKey = 'media_id';
    public $timestamps = false;

    protected $fillable = ['media_id', 'embedding'];

    protected $hidden = ['embedding'];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
