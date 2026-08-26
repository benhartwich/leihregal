<?php

namespace App\Models;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Media extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'type',
        'title',
        'author',
        'publisher',
        'year',
        'isbn',
        'language',
        'status',
        'internal_code',
        'summary',
        'target_group',
        'age_recommendation',
        'practical_use',
        'cover_path',
        'location',
        'loan_days',
        'copy_number',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type'   => MediaType::class,
            'status' => MediaStatus::class,
            'year'   => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(MediaTag::class);
    }

    public function embedding(): HasOne
    {
        return $this->hasOne(MediaEmbedding::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(\App\Models\MediaReview::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(\App\Models\MediaBookmark::class);
    }

    /** All copies sharing the same ISBN (including self). */
    public function copies(): \Illuminate\Database\Eloquent\Builder
    {
        return static::where('isbn', $this->isbn)->where('isbn', '!=', '')->orderBy('copy_number');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoans(): HasMany
    {
        return $this->hasMany(Loan::class)->whereNull('returned_at');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(\App\Models\DamageReport::class);
    }

    public function activeReservations(): HasMany
    {
        return $this->hasMany(Reservation::class)
            ->whereIn('status', ['wartend', 'bereit'])
            ->orderBy('position');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->status === MediaStatus::Verfuegbar;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? asset('storage/' . $this->cover_path) : null;
    }

    public function tagList(): array
    {
        return $this->tags->pluck('tag')->toArray();
    }

    // ── Internal code generator ───────────────────────────────────────────────

    public static function generateInternalCode(): string
    {
        do {
            $code = 'LIB-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        } while (self::where('internal_code', $code)->exists());

        return $code;
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeSearch($query, string $term)
    {
        return $query->whereRaw(
            'MATCH(title, author, summary) AGAINST(? IN BOOLEAN MODE)',
            [$term . '*']
        );
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', MediaStatus::Verfuegbar);
    }
}
