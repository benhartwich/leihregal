<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Media;
use App\Models\MediaReview;
use App\Models\Wish;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Kennzahlen für den Quartalsbericht (Phase 8).
 *
 * Beantwortet die Fragen, die im Kuratorium tatsächlich gestellt werden:
 * Was wurde genutzt? Was liegt brach? Wo fehlt etwas?
 */
class QuarterlyReportService
{
    /**
     * Zeitraum des Quartals, in dem das übergebene Datum liegt.
     *
     * @return array{von: CarbonImmutable, bis: CarbonImmutable, bezeichnung: string}
     */
    public function zeitraum(?CarbonImmutable $stichtag = null): array
    {
        $stichtag = $stichtag ?? CarbonImmutable::now();
        $von      = $stichtag->startOfQuarter();
        $bis      = $stichtag->endOfQuarter();

        return [
            'von'         => $von,
            'bis'         => $bis,
            'bezeichnung' => 'Q' . $von->quarter . ' ' . $von->year,
        ];
    }

    /**
     * Voriges Quartal – das ist der Zeitraum, über den berichtet wird.
     */
    public function vorigesQuartal(?CarbonImmutable $stichtag = null): array
    {
        return $this->zeitraum(($stichtag ?? CarbonImmutable::now())->subQuarter());
    }

    public function erstellen(CarbonImmutable $von, CarbonImmutable $bis): array
    {
        return [
            'von'              => $von,
            'bis'              => $bis,
            'bezeichnung'      => 'Q' . $von->quarter . ' ' . $von->year,
            'ausleihen'        => $this->ausleihkennzahlen($von, $bis),
            'beliebteste'      => $this->beliebtesteMedien($von, $bis),
            'ungenutzt'        => $this->ungenutzteMedien($von),
            'bestand'          => $this->bestandskennzahlen($von, $bis),
            'themen'           => $this->themenverteilung($von, $bis),
            'wuensche'         => $this->wunschkennzahlen($von, $bis),
            'bewertungen'      => $this->bewertungskennzahlen($von, $bis),
        ];
    }

    private function ausleihkennzahlen(CarbonImmutable $von, CarbonImmutable $bis): array
    {
        $imZeitraum = Loan::whereBetween('borrowed_at', [$von, $bis]);

        $rueckgaben = Loan::whereBetween('returned_at', [$von, $bis])->get();

        // Leihdauer nur aus abgeschlossenen Ausleihen – laufende würden den
        // Schnitt künstlich drücken.
        $schnitt = $rueckgaben->isEmpty()
            ? null
            : round($rueckgaben->avg(fn ($l) => $l->borrowed_at->diffInDays($l->returned_at)), 1);

        return [
            'gesamt'          => (clone $imZeitraum)->count(),
            'nutzer'          => (clone $imZeitraum)->distinct('user_id')->count('user_id'),
            'rueckgaben'      => $rueckgaben->count(),
            'schnittTage'     => $schnitt,
            'ueberfaellig'    => Loan::whereNull('returned_at')->where('due_at', '<', $bis)->count(),
            'verlaengerungen' => (clone $imZeitraum)->where('extension_count', '>', 0)->count(),
        ];
    }

    private function beliebtesteMedien(CarbonImmutable $von, CarbonImmutable $bis, int $limit = 10): array
    {
        return Media::query()
            ->select('media.id', 'media.title', 'media.type')
            ->join('loans', 'loans.media_id', '=', 'media.id')
            ->whereBetween('loans.borrowed_at', [$von, $bis])
            ->groupBy('media.id', 'media.title', 'media.type')
            ->selectRaw('COUNT(loans.id) AS anzahl')
            ->orderByDesc('anzahl')
            ->orderBy('media.title')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Medien ohne Ausleihe seit Quartalsbeginn – Kandidaten für Aussortierung
     * oder gezielte Bewerbung.
     */
    private function ungenutzteMedien(CarbonImmutable $von, int $limit = 10): array
    {
        return Media::query()
            ->whereNotIn('status', ['ausgemustert', 'verloren'])
            ->whereDoesntHave('loans', fn ($q) => $q->where('borrowed_at', '>=', $von))
            ->orderBy('title')
            ->limit($limit)
            ->get(['id', 'title', 'type', 'created_at'])
            ->toArray();
    }

    private function bestandskennzahlen(CarbonImmutable $von, CarbonImmutable $bis): array
    {
        return [
            'gesamt'       => Media::whereNotIn('status', ['ausgemustert', 'verloren'])->count(),
            'neu'          => Media::whereBetween('created_at', [$von, $bis])->count(),
            'ausgemustert' => Media::where('status', 'ausgemustert')
                                   ->whereBetween('updated_at', [$von, $bis])->count(),
            'verloren'     => Media::where('status', 'verloren')
                                   ->whereBetween('updated_at', [$von, $bis])->count(),
            'ohneEmbedding' => Media::whereNotIn('status', ['ausgemustert', 'verloren'])
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('media_embeddings')
                    ->whereColumn('media_embeddings.media_id', 'media.id'))
                ->count(),
        ];
    }

    private function themenverteilung(CarbonImmutable $von, CarbonImmutable $bis, int $limit = 8): array
    {
        return DB::table('media_tags')
            ->join('loans', 'loans.media_id', '=', 'media_tags.media_id')
            ->whereBetween('loans.borrowed_at', [$von, $bis])
            ->groupBy('media_tags.tag')
            ->selectRaw('media_tags.tag, COUNT(*) AS anzahl')
            ->orderByDesc('anzahl')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function wunschkennzahlen(CarbonImmutable $von, CarbonImmutable $bis): array
    {
        $imZeitraum = Wish::whereBetween('created_at', [$von, $bis]);

        return [
            'neu'       => (clone $imZeitraum)->count(),
            'offen'     => Wish::where('status', 'eingereicht')->count(),
            'angenommen' => Wish::where('status', 'angenommen')
                                ->whereBetween('updated_at', [$von, $bis])->count(),
            'abgelehnt' => Wish::where('status', 'abgelehnt')
                               ->whereBetween('updated_at', [$von, $bis])->count(),
        ];
    }

    private function bewertungskennzahlen(CarbonImmutable $von, CarbonImmutable $bis): array
    {
        $bewertungen = MediaReview::whereBetween('created_at', [$von, $bis])->get();
        $mitNote     = $bewertungen->whereNotNull('rating');

        return [
            'gesamt'   => $bewertungen->count(),
            'positiv'  => $mitNote->where('rating', 1)->count(),
            'negativ'  => $mitNote->where('rating', 0)->count(),
        ];
    }
}
