<?php

namespace App\Models;

use App\Enums\AcquisitionStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcquisitionSuggestion extends Model
{
    use Auditable;

    protected $fillable = [
        'source', 'title', 'isbn', 'publisher', 'author',
        'price_estimate', 'reason', 'shop_urls', 'status', 'wish_id',
    ];

    protected function casts(): array
    {
        return [
            'status'    => AcquisitionStatus::class,
            'shop_urls' => 'array',
            'price_estimate' => 'decimal:2',
        ];
    }

    public function wish(): BelongsTo { return $this->belongsTo(Wish::class); }
}
