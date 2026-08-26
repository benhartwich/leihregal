<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SimilarMediaService
{
    public function __construct(private MediaAiService $ai) {}

    /**
     * Find up to $limit media items similar to $media using cosine distance on embeddings.
     * Falls back to tag-based similarity if no embedding exists.
     */
    public function findSimilar(Media $media, int $limit = 4): Collection
    {
        // Try vector search first
        if ($media->embedding) {
            $results = DB::select("
                SELECT me.media_id,
                       VEC_DISTANCE_COSINE(me.embedding, UNHEX(?)) AS dist
                FROM   media_embeddings me
                JOIN   media m ON m.id = me.media_id
                WHERE  me.media_id != ?
                  AND  m.status NOT IN ('verloren', 'ausgemustert')
                ORDER  BY dist
                LIMIT  ?
            ", [bin2hex($media->embedding->getRawOriginal('embedding')), $media->id, $limit]);

            if (! empty($results)) {
                $ids = collect($results)->pluck('media_id');
                return Media::whereIn('id', $ids)
                    ->with('tags')
                    ->get()
                    ->sortBy(fn($m) => array_search($m->id, $ids->toArray()))
                    ->values();
            }
        }

        // Fallback: tag overlap
        return $this->findByTagOverlap($media, $limit);
    }

    private function findByTagOverlap(Media $media, int $limit): Collection
    {
        $tags = $media->tags->pluck('tag')->toArray();

        if (empty($tags)) {
            // Last resort: same type
            return Media::where('type', $media->type)
                ->where('id', '!=', $media->id)
                ->whereNotIn('status', ['verloren', 'ausgemustert'])
                ->with('tags')
                ->limit($limit)
                ->get();
        }

        return Media::whereHas('tags', fn($q) => $q->whereIn('tag', $tags))
            ->where('id', '!=', $media->id)
            ->whereNotIn('status', ['verloren', 'ausgemustert'])
            ->with('tags')
            ->limit($limit)
            ->get();
    }

    /**
     * Semantic search: embed query text, then find nearest neighbors.
     * Falls back to FULLTEXT if embedding fails.
     */
    public function semanticSearch(string $query, int $limit = 6): Collection
    {
        $embedding = $this->ai->generateEmbedding($query);

        if ($embedding) {
            $results = DB::select("
                SELECT me.media_id,
                       VEC_DISTANCE_COSINE(me.embedding, UNHEX(?)) AS dist
                FROM   media_embeddings me
                JOIN   media m ON m.id = me.media_id
                WHERE  m.status NOT IN ('verloren', 'ausgemustert')
                ORDER  BY dist
                LIMIT  ?
            ", [bin2hex($embedding), $limit]);

            if (! empty($results)) {
                $ids = collect($results)->pluck('media_id');
                return Media::whereIn('id', $ids)
                    ->with('tags')
                    ->get()
                    ->sortBy(fn($m) => array_search($m->id, $ids->toArray()))
                    ->values();
            }
        }

        // Fallback: FULLTEXT
        if (strlen($query) >= 3) {
            return Media::whereRaw(
                'MATCH(title, author, summary) AGAINST(? IN BOOLEAN MODE)',
                [$query . '*']
            )
            ->whereNotIn('status', ['verloren', 'ausgemustert'])
            ->with('tags')
            ->limit($limit)
            ->get();
        }

        return collect();
    }

    /**
     * Build a compact media inventory string for use as Claude context.
     * Returns a summarised list (title, type, tags, status) for top N results.
     */
    public function buildInventoryContext(Collection $media): string
    {
        if ($media->isEmpty()) {
            return 'Keine passenden Medien gefunden.';
        }

        return $media->map(function ($m) {
            $tags   = $m->tags->pluck('tag')->implode(', ');
            $status = $m->status->label();
            return "- {$m->title} [{$m->type->label()}]"
                . ($tags ? ", Tags: {$tags}" : '')
                . ", Status: {$status}"
                . ($m->age_recommendation ? ", Alter: {$m->age_recommendation}" : '');
        })->implode("\n");
    }
}
