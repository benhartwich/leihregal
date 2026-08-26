<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Media;
use App\Models\MediaReview;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Persönliche Empfehlungen aus der Leihhistorie (Phase 8).
 *
 * Grundgedanke: Aus den Embeddings der bereits entliehenen Medien wird ein
 * gemitteltes „Interessenprofil" gebildet und damit im Bestand gesucht.
 * Bewertungen gewichten dabei mit – ein Medium mit Daumen hoch zieht das
 * Profil stärker in seine Richtung, eines mit Daumen runter stösst es ab
 * (Feedback-Lernen).
 */
class RecommendationService
{
    /** Anzahl Bytes je Vektor: 1536 floats à 4 Byte. */
    private const DIMENSIONEN = 1536;

    /** Gewicht ohne Bewertung – zählt, aber schwächer als ein Daumen hoch. */
    private const GEWICHT_NEUTRAL = 1.0;
    private const GEWICHT_GUT     = 2.0;
    private const GEWICHT_SCHLECHT = -1.0;

    /**
     * Empfehlungen für einen Nutzer.
     *
     * @return Collection<int, Media>
     */
    public function fuerNutzer(User $user, int $limit = 6): Collection
    {
        $profil = $this->interessenprofil($user);

        if ($profil === null) {
            // Ohne Historie: die im Haus beliebtesten Medien vorschlagen.
            return $this->beliebteste($user, $limit);
        }

        $ausgeschlossen = $this->bereitsGeliehen($user);

        $treffer = DB::select(
            sprintf("
                SELECT me.media_id,
                       VEC_DISTANCE_COSINE(me.embedding, UNHEX(?)) AS dist
                FROM   media_embeddings me
                JOIN   media m ON m.id = me.media_id
                WHERE  m.status NOT IN ('verloren', 'ausgemustert')
                  %s
                ORDER  BY dist
                LIMIT  ?
            ", $ausgeschlossen->isEmpty() ? '' : 'AND me.media_id NOT IN (' . $ausgeschlossen->implode(',') . ')'),
            [bin2hex($profil), $limit]
        );

        if (empty($treffer)) {
            return $this->beliebteste($user, $limit);
        }

        $ids = collect($treffer)->pluck('media_id');

        return Media::whereIn('id', $ids)
            ->with('tags')
            ->get()
            ->sortBy(fn ($m) => array_search($m->id, $ids->toArray()))
            ->values();
    }

    /**
     * Gemittelter, bewertungsgewichteter Vektor über die Leihhistorie.
     * Gibt null zurück, wenn sich daraus kein sinnvolles Profil ergibt.
     */
    public function interessenprofil(User $user): ?string
    {
        $historie = Loan::where('user_id', $user->id)
            ->whereNotNull('returned_at')
            ->pluck('media_id')
            ->unique();

        if ($historie->isEmpty()) {
            return null;
        }

        $bewertungen = MediaReview::where('user_id', $user->id)
            ->whereIn('media_id', $historie)
            ->pluck('rating', 'media_id');

        $vektoren = DB::table('media_embeddings')
            ->whereIn('media_id', $historie)
            ->select('media_id', DB::raw('HEX(embedding) AS hex'))
            ->get();

        if ($vektoren->isEmpty()) {
            return null;
        }

        $summe        = array_fill(0, self::DIMENSIONEN, 0.0);
        $gewichtSumme = 0.0;

        foreach ($vektoren as $zeile) {
            $werte = unpack('f*', hex2bin($zeile->hex));

            if (count($werte) !== self::DIMENSIONEN) {
                continue;
            }

            $gewicht = match ($bewertungen[$zeile->media_id] ?? null) {
                1       => self::GEWICHT_GUT,
                0       => self::GEWICHT_SCHLECHT,
                default => self::GEWICHT_NEUTRAL,
            };

            for ($i = 0; $i < self::DIMENSIONEN; $i++) {
                $summe[$i] += $werte[$i + 1] * $gewicht;
            }

            $gewichtSumme += abs($gewicht);
        }

        if ($gewichtSumme <= 0.0) {
            return null;
        }

        // Auf Einheitslänge bringen – die Kosinus-Distanz erwartet einen
        // normalisierten Vektor, sonst verzerrt die Länge das Ergebnis.
        $laenge = 0.0;
        foreach ($summe as $wert) {
            $laenge += $wert * $wert;
        }
        $laenge = sqrt($laenge);

        if ($laenge <= 0.0) {
            return null;
        }

        foreach ($summe as $i => $wert) {
            $summe[$i] = $wert / $laenge;
        }

        return pack('f*', ...$summe);
    }

    /**
     * Medien-IDs, die der Nutzer schon hatte – die braucht er nicht empfohlen.
     *
     * @return Collection<int, int>
     */
    private function bereitsGeliehen(User $user): Collection
    {
        return Loan::where('user_id', $user->id)->pluck('media_id')->unique()->values();
    }

    /**
     * Rückfallebene ohne Historie: was am häufigsten entliehen wurde.
     *
     * @return Collection<int, Media>
     */
    private function beliebteste(User $user, int $limit): Collection
    {
        $eigene = $this->bereitsGeliehen($user);

        return Media::query()
            ->whereNotIn('status', ['verloren', 'ausgemustert'])
            ->when($eigene->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $eigene))
            ->withCount('loans')
            ->orderByDesc('loans_count')
            ->orderBy('title')
            ->with('tags')
            ->limit($limit)
            ->get();
    }
}
