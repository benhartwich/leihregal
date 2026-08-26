<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatService
{
    private string $apiKey;

    public function __construct(private SimilarMediaService $similar)
    {
        $this->apiKey = (string) config('services.anthropic.key');
    }

    /**
     * Run a single-turn situations-assistant query.
     *
     * @param  string     $situation  Already PII-filtered situation description
     * @param  Collection $inventory  Top-N relevant media (pre-fetched via semantic search)
     * @return array{text: string, media_ids: int[]}
     */
    public function ask(string $situation, Collection $inventory): array
    {
        if (! $this->apiKey) {
            return [
                'text'      => 'Der KI-Assistent ist aktuell nicht verfügbar (API-Key fehlt).',
                'media_ids' => [],
            ];
        }

        $inventoryText = $this->similar->buildInventoryContext($inventory);

        $fachkraefte = config('branding.kontext.fachkraefte');
        $einrichtung = config('branding.kontext.einrichtung');

        $systemPrompt = <<<SYSTEM
Du bist ein Medienberater für {$fachkraefte} in folgendem Umfeld: {$einrichtung}.
Deine Aufgabe: Passende Bücher, Spiele oder Materialien aus dem vorhandenen Bestand empfehlen.

WICHTIG:
- Empfiehl NUR Medien aus der unten angegebenen Bestandsliste
- Wenn kein Medium wirklich passt, sage das offen ("Kein passendes Medium im Bestand")
- Erwähne in diesem Fall die Möglichkeit, einen Wunsch einzureichen
- Antworte auf Deutsch, in "Sie"-Form
- Halte die Antwort kurz: max. 3 Empfehlungen, je 2-3 Sätze Begründung
- Verwende die Medientitel exakt so wie sie in der Bestandsliste stehen (für automatische Erkennung)

BESTAND:
{$inventoryText}
SYSTEM;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model'      => config('services.anthropic.model'),
                'max_tokens' => 1024,
                'system'     => $systemPrompt,
                'messages'   => [
                    ['role' => 'user', 'content' => "Situation: {$situation}"],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('ChatService API error', ['status' => $response->status(), 'body' => $response->body()]);
                return ['text' => 'Fehler beim KI-Aufruf. Bitte versuchen Sie es erneut.', 'media_ids' => []];
            }

            $text = $response->json('content.0.text', '');

            // Extract recommended media IDs by matching titles from inventory
            $mediaIds = $this->extractMediaIds($text, $inventory);

            // Strip trailing JSON block (```json [...] ``` or bare [...]) Claude appends per system prompt
            $displayText = preg_replace('/\n*```(?:json)?\s*\[[\s\S]*?\]\s*```\s*$/u', '', $text);
            $displayText = preg_replace('/\n*\[[\s\S]*?\]\s*$/u', '', $displayText);
            $displayText = trim($displayText);

            return ['text' => $displayText, 'media_ids' => $mediaIds];
        } catch (\Throwable $e) {
            Log::warning('ChatService ask failed', ['error' => $e->getMessage()]);
            return ['text' => 'Der KI-Assistent ist momentan nicht erreichbar.', 'media_ids' => []];
        }
    }

    private function extractMediaIds(string $text, Collection $inventory): array
    {
        $ids = [];
        foreach ($inventory as $media) {
            if (str_contains($text, $media->title)) {
                $ids[] = $media->id;
            }
        }
        return array_slice(array_unique($ids), 0, 3);
    }
}
