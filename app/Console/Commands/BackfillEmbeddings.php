<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\MediaAiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Zieht fehlende Embeddings nach.
 *
 * Medien ohne Embedding tauchen weder in der semantischen Suche noch im
 * Situations-Assistenten auf – sie sind faktisch unsichtbar. Da die Erzeugung
 * beim Anlegen still scheitern kann (Rate-Limit, Netzwerk), braucht es einen
 * Weg, die Lücken nachträglich zu schliessen.
 */
class BackfillEmbeddings extends Command
{
    protected $signature = 'media:backfill-embeddings
                            {--force : Auch vorhandene Embeddings neu erzeugen}
                            {--limit=0 : Höchstzahl zu verarbeitender Medien (0 = alle)}';

    protected $description = 'Erzeugt fehlende Embeddings für Medien nach';

    public function handle(MediaAiService $ai): int
    {
        $abfrage = Media::query()
            ->whereNotIn('status', ['ausgemustert', 'verloren'])
            ->orderBy('id');

        if (! $this->option('force')) {
            $abfrage->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('media_embeddings')
                  ->whereColumn('media_embeddings.media_id', 'media.id');
            });
        }

        if ($limit = (int) $this->option('limit')) {
            $abfrage->limit($limit);
        }

        $medien = $abfrage->get();

        if ($medien->isEmpty()) {
            $this->info('Alle Medien haben ein Embedding – nichts zu tun.');
            return self::SUCCESS;
        }

        $this->info("{$medien->count()} Medium/Medien werden verarbeitet …");

        $erfolg = 0;
        $fehler = 0;

        foreach ($medien as $medium) {
            $text = $ai->buildEmbeddingText([
                'title'         => $medium->title,
                'author'        => $medium->author,
                'summary'       => $medium->summary,
                'target_group'  => $medium->target_group,
                'practical_use' => $medium->practical_use,
                'tags'          => $medium->tags()->pluck('tag')->all(),
            ]);

            if (trim($text) === '') {
                $this->warn("  #{$medium->id} {$medium->title}: kein Text vorhanden, übersprungen.");
                continue;
            }

            $embedding = $ai->generateEmbedding($text);

            if (! $embedding) {
                $this->error("  #{$medium->id} {$medium->title}: fehlgeschlagen.");
                $fehler++;
                continue;
            }

            DB::statement(
                'INSERT INTO media_embeddings (media_id, embedding, updated_at)
                 VALUES (?, UNHEX(?), NOW())
                 ON DUPLICATE KEY UPDATE embedding = VALUES(embedding), updated_at = NOW()',
                [$medium->id, bin2hex($embedding)]
            );

            $this->line("  #{$medium->id} {$medium->title}: ok");
            $erfolg++;
        }

        $this->newLine();
        $this->info("Fertig: {$erfolg} erzeugt, {$fehler} fehlgeschlagen.");

        // Fehlschläge als Exit-Code melden, damit der Scheduler sie bemerkt.
        return $fehler > 0 ? self::FAILURE : self::SUCCESS;
    }
}
