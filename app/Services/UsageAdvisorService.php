<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Einsatz-Assistent für ein einzelnes Medium (Phase 8).
 *
 * Unterschied zum Situations-Assistenten: Dort wird gefragt „welches Medium
 * passt?", hier steht das Medium fest und gefragt wird „wie setze ich es in
 * dieser Situation konkret ein?".
 */
class UsageAdvisorService
{
    private const VERSUCHE = 3;

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.anthropic.key');
    }

    /**
     * @param  string  $situation  Bereits PII-gefilterte Situationsbeschreibung
     * @return array{text: ?string, fehler: ?string}
     */
    public function beraten(Media $media, string $situation): array
    {
        if (! $this->apiKey) {
            return ['text' => null, 'fehler' => 'Der Einsatz-Assistent ist nicht verfügbar (API-Schlüssel fehlt).'];
        }

        $tags = $media->tags->pluck('tag')->implode(', ');

        $fachkraefte = config('branding.kontext.fachkraefte');
        $einrichtung = config('branding.kontext.einrichtung');

        $system = <<<SYSTEM
        Du berätst {$fachkraefte} dabei, ein bestimmtes Medium in folgendem
        Umfeld konkret einzusetzen: {$einrichtung}.

        MEDIUM
        Titel: {$media->title}
        Art: {$media->type->label()}
        Autor/in: {$media->author}
        Zielgruppe: {$media->target_group}
        Altersempfehlung: {$media->age_recommendation}
        Themen: {$tags}
        Inhalt: {$media->summary}
        Bisherige Praxishinweise: {$media->practical_use}

        AUFGABE
        Beschreibe, wie sich genau dieses Medium in der geschilderten Situation
        einsetzen lässt.

        VORGABEN
        - Antworte auf Deutsch, in der Sie-Form.
        - Nenne 3 bis 4 konkrete Schritte oder Gesprächsanlässe, keine Theorie.
        - Gib einen Hinweis, worauf zu achten ist oder wann das Medium
          ungeeignet wäre.
        - Passt das Medium erkennbar nicht zur Situation, sage das offen,
          statt etwas zu konstruieren.
        - Höchstens 250 Wörter. Keine Überschrift, keine Aufzählung mit
          Nummern in Klammern.
        SYSTEM;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
                ->timeout(60)
                ->retry(
                    times: self::VERSUCHE,
                    sleepMilliseconds: fn (int $versuch) => min(1000 * (2 ** ($versuch - 1)), 4000),
                    when: fn (Throwable $f) => $this->istWiederholbar($f),
                    throw: false,
                )
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => config('services.anthropic.model'),
                    'max_tokens' => 900,
                    'system'     => $system,
                    'messages'   => [
                        ['role' => 'user', 'content' => "Situation: {$situation}"],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('UsageAdvisorService API-Fehler', [
                    'status'   => $response->status(),
                    'media_id' => $media->id,
                ]);

                return ['text' => null, 'fehler' => 'Der Einsatz-Assistent ist momentan nicht erreichbar. Bitte später erneut versuchen.'];
            }

            $text = trim($response->json('content.0.text', ''));

            if ($text === '') {
                return ['text' => null, 'fehler' => 'Es kam keine verwertbare Antwort zurück.'];
            }

            return ['text' => $text, 'fehler' => null];
        } catch (Throwable $e) {
            Log::warning('UsageAdvisorService fehlgeschlagen', ['error' => $e->getMessage()]);

            return ['text' => null, 'fehler' => 'Der Einsatz-Assistent ist momentan nicht erreichbar.'];
        }
    }

    private function istWiederholbar(Throwable $fehler): bool
    {
        if ($fehler instanceof ConnectionException) {
            return true;
        }

        if ($fehler instanceof RequestException && $fehler->response !== null) {
            $status = $fehler->response->status();

            return $status === 429 || $status >= 500;
        }

        return false;
    }
}
