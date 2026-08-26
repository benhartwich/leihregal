<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhitelistEntry extends Model
{
    use Auditable;

    protected $fillable = ['type', 'name', 'notes', 'added_by'];

    public function addedBy(): BelongsTo { return $this->belongsTo(User::class, 'added_by'); }
}
