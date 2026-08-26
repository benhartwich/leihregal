<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MediaAiService
{
    /** Gesamtzahl der Versuche je Aufruf (1 regulärer + 3 Wiederholungen). */
    private const VERSUCHE = 4;

    private string $anthropicKey;
    private string $openAiKey;

    public function __construct()
    {
        $this->anthropicKey = (string) config('services.anthropic.key');
        $this->openAiKey    = (string) config('services.openai.key');
    }

    // ── Wiederholungslogik (Spec 7.5) ────────────────────────────────────────

    /**
     * Entscheidet, ob ein Fehlschlag eine Wiederholung rechtfertigt.
     *
     * Wiederholt wird bei Rate-Limits (429), Serverfehlern (5xx) und
     * Verbindungsproblemen. Alle übrigen 4xx – etwa ein ungültiger Schlüssel
     * oder eine fehlerhafte Anfrage – werden durch Wiederholen nicht besser
     * und würden nur Kontingent verbrennen.
     */
    private function istWiederholbar(Throwable $fehler): bool
    {
        if ($fehler instanceof ConnectionException) {
            return true;
        }

        if (! $fehler instanceof RequestException || $fehler->response === null) {
            return false;
        }

        $status = $fehler->response->status();

        // Aufgebrauchtes Guthaben meldet OpenAI als 429 – also mit demselben
        // Statuscode wie ein echtes Rate-Limit. Der Unterschied ist
        // entscheidend: Ein Rate-Limit vergeht, ein leeres Konto nicht.
        // Wiederholen würde hier nur Zeit kosten und die Ursache verschleiern.
        if ($status === 429 && $this->istKontingentErschoepft($fehler->response->json())) {
            return false;
        }

        return $status === 429 || $status >= 500;
    }

    /**
     * Erkennt ein dauerhaft erschöpftes Kontingent (Abrechnungsproblem)
     * im Gegensatz zu einer vorübergehenden Drosselung.
     */
    private function istKontingentErschoepft(mixed $koerper): bool
    {
        if (! is_array($koerper)) {
            return false;
        }

        $typ = $koerper['error']['type'] ?? '';

        return in_array($typ, ['insufficient_quota', 'billing_not_active'], true);
    }

    /**
     * Wartezeit vor dem nächsten Versuch in Millisekunden.
     *
     * Bevorzugt den `Retry-After`-Header, den OpenAI und Anthropic bei 429
     * mitschicken – der Anbieter weiss besser als wir, wann er wieder bereit
     * ist. Sonst exponentiell (1s, 2s, 4s) mit etwas Streuung, damit mehrere
     * gleichzeitig laufende Aufträge nicht im Gleichtakt erneut anklopfen.
     */
    private function wartezeit(int $versuch, ?Throwable $fehler = null): int
    {
        if ($fehler instanceof RequestException && $fehler->response?->hasHeader('Retry-After')) {
            $sekunden = (int) $fehler->response->header('Retry-After');

            if ($sekunden > 0 && $sekunden <= 60) {
                return $sekunden * 1000;
            }
        }

        return min(1000 * (2 ** max(0, $versuch - 1)), 8000) + random_int(0, 250);
    }

    // ── Claude: structured enrichment ────────────────────────────────────────

    /**
     * Generate AI-enriched metadata for a media item.
     * Returns array with keys: summary, target_group, age_recommendation, practical_use, tags
     */
    public function enrichMedia(array $mediaData): ?array
    {
        if (! $this->anthropicKey) {
            return null;
        }

        $title  = $mediaData['title'] ?? 'Unbekannt';
        $author = $mediaData['author'] ?? '';
        $type   = $mediaData['type'] ?? 'buch';
        $description = $mediaData['description'] ?? '';

        $einrichtung = config('branding.kontext.einrichtung');
        $zielgruppe  = config('branding.kontext.zielgruppe');
        $arbeitsfeld = config('branding.kontext.arbeitsfeld');

        $prompt = <<<EOT
Du analysierst ein Medium für eine {$einrichtung}.

Medientyp: {$type}
Titel: {$title}
Autor/in: {$author}
Beschreibung: {$description}

Erstelle eine strukturierte Analyse mit folgendem JSON-Format (antworte NUR mit dem JSON-Objekt, ohne Erklärungen):

{
  "summary": "Kurze, prägnante Zusammenfassung in 2-3 Sätzen (für {$zielgruppe})",
  "target_group": "Für wen ist dieses Medium geeignet (z.B. Jugendliche ab 12, Kinder 6-10, {$zielgruppe})",
  "age_recommendation": "Empfohlenes Alter (z.B. '8-12 Jahre', 'ab 14', 'Erwachsene')",
  "practical_use": "Praktische Einsatzmöglichkeiten in der {$arbeitsfeld} (2-4 Sätze)",
  "tags": ["tag1", "tag2", "tag3"]
}

Tags sollen kurze, relevante Schlagwörter sein (max. 8 Stück), z.B. Themen, Methoden, Zielgruppen.
EOT;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->anthropicKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(30)
              ->retry(
                  times: self::VERSUCHE,
                  sleepMilliseconds: fn (int $versuch, Throwable $fehler) => $this->wartezeit($versuch, $fehler),
                  when: fn (Throwable $fehler) => $this->istWiederholbar($fehler),
                  throw: false,
              )
              ->post('https://api.anthropic.com/v1/messages', [
                'model'      => config('services.anthropic.model'),
                'max_tokens' => 800,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('Anthropic API error', [
                    'status'   => $response->status(),
                    'body'     => $response->body(),
                    'versuche' => self::VERSUCHE,
                ]);
                return null;
            }

            $content = $response->json('content.0.text', '');
            // Extract JSON from response (handle potential markdown code blocks)
            if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $data = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
            }

            Log::warning('MediaAiService: invalid JSON from Claude', ['content' => $content]);
            return null;
        } catch (\Throwable $e) {
            Log::warning('MediaAiService enrichMedia failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ── OpenAI: embedding generation ─────────────────────────────────────────

    /**
     * Generate text embedding for semantic search.
     * Returns a packed binary string (VECTOR format) or null.
     */
    public function generateEmbedding(string $text): ?string
    {
        if (! $this->openAiKey) {
            return null;
        }

        // Truncate to ~8000 chars to stay within token limits
        $text = mb_substr($text, 0, 8000);

        try {
            $response = Http::withToken($this->openAiKey)
                ->timeout(15)
                ->retry(
                    times: self::VERSUCHE,
                    sleepMilliseconds: fn (int $versuch, Throwable $fehler) => $this->wartezeit($versuch, $fehler),
                    when: fn (Throwable $fehler) => $this->istWiederholbar($fehler),
                    // Nach erschöpften Versuchen die fehlgeschlagene Antwort
                    // zurückgeben statt zu werfen – der Aufrufer erwartet null.
                    throw: false,
                )
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => config('services.openai.embedding_model'),
                    'input' => $text,
                ]);

            if (! $response->successful()) {
                if ($this->istKontingentErschoepft($response->json())) {
                    // Als Fehler statt Warnung: Das behebt sich nicht von
                    // selbst und macht die semantische Suche für jedes neu
                    // angelegte Medium blind.
                    Log::error('OpenAI-Guthaben aufgebraucht – es werden keine Embeddings mehr erzeugt.', [
                        'hinweis' => 'Guthaben aufladen, danach: php8.3 artisan media:backfill-embeddings',
                    ]);
                } else {
                    Log::warning('OpenAI embeddings error', [
                        'status'   => $response->status(),
                        'versuche' => self::VERSUCHE,
                    ]);
                }

                return null;
            }

            $vector = $response->json('data.0.embedding');

            if (! is_array($vector) || count($vector) !== 1536) {
                return null;
            }

            // Pack floats as binary string for MariaDB VECTOR type
            return pack('f*', ...$vector);
        } catch (\Throwable $e) {
            Log::warning('MediaAiService generateEmbedding failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Build the plain text used for embedding from media fields.
     */
    public function buildEmbeddingText(array $fields): string
    {
        return implode("\n", array_filter([
            $fields['title'] ?? '',
            $fields['author'] ?? '',
            $fields['summary'] ?? '',
            $fields['target_group'] ?? '',
            $fields['practical_use'] ?? '',
            isset($fields['tags']) ? implode(' ', (array) $fields['tags']) : '',
        ]));
    }
}
