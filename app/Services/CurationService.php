<?php

namespace App\Services;

use App\Models\Media;
use App\Models\WhitelistEntry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurationService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.anthropic.key');
    }

    // ── Bestandslücken-Analyse ────────────────────────────────────────────────

    public function analyzeGaps(): array
    {
        if (! $this->apiKey) {
            return ['error' => 'Anthropic API-Key fehlt.'];
        }

        $tagDistribution  = $this->buildTagDistribution();
        $whitelistVerlage = WhitelistEntry::where('type', 'verlag')->pluck('name')->implode(', ');
        $whitelistAutoren = WhitelistEntry::where('type', 'autor')->pluck('name')->implode(', ');
        $mediaCount       = Media::whereNotIn('status', ['verloren', 'ausgemustert'])->count();

        $arbeitsfeld = config('branding.kontext.arbeitsfeld');
        $einrichtung = config('branding.kontext.einrichtung');

        $prompt = <<<EOT
Du bist ein Kurator für die Medienbibliothek folgender Einrichtung: {$einrichtung}.

BESTAND ({$mediaCount} Medien) – TAG-VERTEILUNG:
{$tagDistribution}

WHITELIST (nur diese Verlage/Autoren empfehlen):
Verlage: {$whitelistVerlage}
Autoren: {$whitelistAutoren}

Analysiere den Bestand und identifiziere bis zu 5 konkrete Anschaffungsempfehlungen.
Fokus auf Lücken: unterrepräsentierte Themen, fehlende Altersgruppen, wichtige Themen ohne Material.

Antworte NUR mit einem JSON-Array (kein weiterer Text):
[
  {
    "title": "Buchtitel",
    "author": "Autor",
    "publisher": "Verlag (nur aus Whitelist)",
    "isbn": "ISBN falls bekannt oder null",
    "price_estimate": 29.90,
    "reason": "Kurze Begründung warum diese Lücke",
    "shop_urls": {
      "amazon": "https://www.amazon.de/s?k=TITEL+AUTOR",
      "genialokal": "https://www.genialokal.de/suche/?q=TITEL"
    }
  }
]
EOT;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model'      => config('services.anthropic.model'),
                'max_tokens' => 2000,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

            if (! $response->successful()) {
                Log::warning('CurationService gap analysis failed', ['status' => $response->status()]);
                return ['error' => 'KI-Anfrage fehlgeschlagen.'];
            }

            $content = $response->json('content.0.text', '');
            if (preg_match('/\[[\s\S]*\]/u', $content, $m)) {
                $suggestions = json_decode($m[0], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($suggestions)) {
                    return ['suggestions' => $suggestions];
                }
            }

            Log::warning('CurationService: invalid JSON', ['content' => $content]);
            return ['error' => 'KI hat kein gültiges Format zurückgegeben.'];
        } catch (\Throwable $e) {
            Log::warning('CurationService analyzeGaps exception', ['error' => $e->getMessage()]);
            return ['error' => 'Verbindungsfehler: ' . $e->getMessage()];
        }
    }

    // ── Veraltungs-Check ──────────────────────────────────────────────────────

    public function checkOutdated(Media $media): array
    {
        if (! $this->apiKey) {
            return ['error' => 'Anthropic API-Key fehlt.'];
        }

        $arbeitsfeld = config('branding.kontext.arbeitsfeld');

        $prompt = <<<EOT
Beurteile, ob folgendes Medium für den Einsatz in der {$arbeitsfeld} veraltet
oder noch zeitgemäß ist.

Titel: {$media->title}
Autor: {$media->author}
Jahr: {$media->year}
Typ: {$media->type->label()}
Zusammenfassung: {$media->summary}

Antworte NUR mit diesem JSON:
{
  "outdated": true/false,
  "confidence": "hoch/mittel/niedrig",
  "reason": "Kurze Begründung (1-2 Sätze)",
  "alternative": "Empfohlene Alternative falls veraltet, sonst null"
}
EOT;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model'      => config('services.anthropic.model'),
                'max_tokens' => 300,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

            $content = $response->json('content.0.text', '');
            if (preg_match('/\{[\s\S]*\}/u', $content, $m)) {
                $result = json_decode($m[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $result;
                }
            }
            return ['error' => 'Kein gültiges Ergebnis.'];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ── Wunsch-Bündelung (KI-Clustering) ─────────────────────────────────────

    public function clusterWishes(): int
    {
        $wishes = \App\Models\Wish::where('status', 'eingereicht')
            ->whereNull('cluster_id')
            ->get();

        if ($wishes->count() < 2) return 0;

        // Simple embedding-based clustering: group by cosine similarity > 0.85
        $aiService = app(MediaAiService::class);
        $clustered = 0;
        $nextCluster = (\App\Models\Wish::max('cluster_id') ?? 0) + 1;

        $embeddings = [];
        foreach ($wishes as $wish) {
            $text = implode(' ', array_filter([
                $wish->title,
                $wish->topic_freetext,
                $wish->isbn,
            ]));
            $embeddings[$wish->id] = $aiService->generateEmbedding($text);
        }

        $assigned = [];
        foreach ($wishes as $wish) {
            if (isset($assigned[$wish->id])) continue;
            $emb = $embeddings[$wish->id];
            if (! $emb) continue;

            $clusterMembers = [$wish->id];
            foreach ($wishes as $other) {
                if ($other->id === $wish->id || isset($assigned[$other->id])) continue;
                $otherEmb = $embeddings[$other->id];
                if (! $otherEmb) continue;
                if ($this->cosineSimilarity($emb, $otherEmb) > 0.82) {
                    $clusterMembers[] = $other->id;
                }
            }

            if (count($clusterMembers) > 1) {
                \App\Models\Wish::whereIn('id', $clusterMembers)
                    ->update(['cluster_id' => $nextCluster++]);
                foreach ($clusterMembers as $id) {
                    $assigned[$id] = true;
                }
                $clustered += count($clusterMembers);
            }
        }

        return $clustered;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildTagDistribution(): string
    {
        $tags = \App\Models\MediaTag::select('tag')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('tag')
            ->orderByDesc('count')
            ->limit(30)
            ->get();

        if ($tags->isEmpty()) return '(Noch keine Tags erfasst)';

        return $tags->map(fn($t) => "{$t->tag}: {$t->count}x")->implode(', ');
    }

    private function cosineSimilarity(string $a, string $b): float
    {
        $vecA = array_values(unpack('f*', $a));
        $vecB = array_values(unpack('f*', $b));
        $dot  = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        foreach ($vecA as $i => $v) {
            $dot += $v * $vecB[$i];
            $normA += $v * $v;
            $normB += $vecB[$i] * $vecB[$i];
        }
        $denom = sqrt($normA) * sqrt($normB);
        return $denom > 0 ? $dot / $denom : 0.0;
    }
}
