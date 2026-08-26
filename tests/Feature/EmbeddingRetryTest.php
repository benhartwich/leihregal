<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Services\MediaAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Wiederholung mit exponentiellem Backoff (Spec 7.5).
 *
 * Anlass: 10 Fehlschläge mit HTTP 429 im Produktionsprotokoll, dadurch ein
 * Medium ohne Embedding – für die semantische Suche unsichtbar.
 */
class EmbeddingRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.key', 'test-schluessel');
        config()->set('services.anthropic.key', 'test-schluessel');

        Http::preventStrayRequests();

        // Ohne das würde die Suite die echten Backoff-Pausen abwarten
        // (rund 12 Sekunden allein für diese Testklasse).
        Sleep::fake();
    }

    private function embedding(): array
    {
        return ['data' => [['embedding' => array_fill(0, 1536, 0.01)]]];
    }

    public function test_rate_limit_wird_wiederholt_und_gelingt_danach(): void
    {
        Http::fakeSequence()
            ->push(['error' => 'rate limit'], 429)
            ->push(['error' => 'rate limit'], 429)
            ->push($this->embedding(), 200);

        $ergebnis = app(MediaAiService::class)->generateEmbedding('Wut und Trauer bei Kindern');

        $this->assertNotNull($ergebnis, 'Nach zwei 429ern wurde nicht erfolgreich wiederholt.');
        $this->assertSame(1536 * 4, strlen($ergebnis), 'Vektor hat nicht die erwartete Binärlänge.');
        Http::assertSentCount(3);
    }

    public function test_serverfehler_wird_wiederholt(): void
    {
        Http::fakeSequence()
            ->push(['error' => 'server'], 503)
            ->push($this->embedding(), 200);

        $this->assertNotNull(app(MediaAiService::class)->generateEmbedding('Test'));
        Http::assertSentCount(2);
    }

    /**
     * Ein ungültiger Schlüssel wird durch Wiederholen nicht gültig – hier
     * darf nicht nachgefasst werden, sonst verbrennt jeder Aufruf Kontingent.
     */
    public function test_authentifizierungsfehler_wird_nicht_wiederholt(): void
    {
        Http::fake(fn () => Http::response(['error' => 'invalid key'], 401));

        $this->assertNull(app(MediaAiService::class)->generateEmbedding('Test'));
        Http::assertSentCount(1);
    }

    /**
     * OpenAI meldet ein aufgebrauchtes Guthaben mit HTTP 429 – demselben Code
     * wie eine vorübergehende Drosselung. Ein leeres Konto füllt sich durch
     * Warten aber nicht; hier darf nicht wiederholt werden.
     */
    public function test_erschoepftes_guthaben_wird_nicht_wiederholt(): void
    {
        Http::fake(fn () => Http::response([
            'error' => [
                'message' => 'You have no credits remaining.',
                'type'    => 'insufficient_quota',
            ],
        ], 429));

        $this->assertNull(app(MediaAiService::class)->generateEmbedding('Test'));

        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    public function test_echtes_rate_limit_wird_weiterhin_wiederholt(): void
    {
        Http::fake(fn () => Http::response([
            'error' => ['message' => 'Rate limit reached', 'type' => 'rate_limit_exceeded'],
        ], 429));

        $this->assertNull(app(MediaAiService::class)->generateEmbedding('Test'));

        Http::assertSentCount(4);
    }

    public function test_ungueltige_anfrage_wird_nicht_wiederholt(): void
    {
        Http::fake(fn () => Http::response(['error' => 'bad request'], 400));

        $this->assertNull(app(MediaAiService::class)->generateEmbedding('Test'));
        Http::assertSentCount(1);
    }

    public function test_nach_erschoepften_versuchen_wird_null_geliefert_statt_geworfen(): void
    {
        Http::fake(fn () => Http::response(['error' => 'rate limit'], 429));

        // Kein try/catch: Der Aufrufer erwartet null, keine Ausnahme.
        $this->assertNull(app(MediaAiService::class)->generateEmbedding('Test'));
        Http::assertSentCount(4);
    }

    /**
     * Die Pausen müssen wachsen – sonst hämmert der Client bei einem
     * Rate-Limit im gleichen Takt weiter und verschlimmert es.
     */
    public function test_wartezeit_waechst_exponentiell(): void
    {
        Http::fake(fn () => Http::response(['error' => 'rate limit'], 429));

        app(MediaAiService::class)->generateEmbedding('Test');

        // 4 Versuche ergeben 3 Pausen dazwischen.
        Sleep::assertSleptTimes(3);

        // Grundwerte 1s / 2s / 4s, jeweils plus bis zu 250 ms Streuung.
        foreach ([1000, 2000, 4000] as $stufe) {
            Sleep::assertSlept(
                fn ($dauer) => $dauer->totalMilliseconds >= $stufe
                            && $dauer->totalMilliseconds < $stufe + 250,
                times: 1
            );
        }
    }

    /**
     * Nennt der Anbieter per `Retry-After`, wann er wieder bereit ist, hat das
     * Vorrang vor unserer eigenen Schätzung.
     */
    public function test_retry_after_header_bestimmt_die_wartezeit(): void
    {
        Http::fakeSequence()
            ->push(['error' => 'rate limit'], 429, ['Retry-After' => '3'])
            ->push($this->embedding(), 200);

        $this->assertNotNull(app(MediaAiService::class)->generateEmbedding('Test'));

        // 3 Sekunden laut Header – nicht die sonst übliche erste Stufe von ~1s.
        Sleep::assertSlept(fn ($dauer) => $dauer->totalMilliseconds === 3000.0, times: 1);
    }

    public function test_backfill_befehl_fuellt_fehlende_embeddings(): void
    {
        Http::fake(fn () => Http::response($this->embedding(), 200));

        $medium = Media::factory()->create(['title' => 'Gefühlskarten']);

        $this->assertDatabaseMissing('media_embeddings', ['media_id' => $medium->id]);

        $this->artisan('media:backfill-embeddings')
            ->assertSuccessful();

        $this->assertDatabaseHas('media_embeddings', ['media_id' => $medium->id]);
    }

    public function test_backfill_ueberspringt_vorhandene_embeddings(): void
    {
        Http::fake(fn () => Http::response($this->embedding(), 200));

        $medium = Media::factory()->create();
        $this->artisan('media:backfill-embeddings')->assertSuccessful();

        Http::fake(fn () => Http::response($this->embedding(), 200));
        $this->artisan('media:backfill-embeddings')->assertSuccessful();

        // Zweiter Lauf darf nichts mehr anfragen.
        Http::assertSentCount(0);
    }

    public function test_backfill_laesst_ausgemusterte_medien_aus(): void
    {
        Http::fake(fn () => Http::response($this->embedding(), 200));

        $ausgemustert = Media::factory()->ausgemustert()->create();

        $this->artisan('media:backfill-embeddings')->assertSuccessful();

        $this->assertDatabaseMissing('media_embeddings', ['media_id' => $ausgemustert->id]);
    }

    public function test_backfill_meldet_fehlschlag_als_exit_code(): void
    {
        Http::fake(fn () => Http::response(['error' => 'invalid key'], 401));

        Media::factory()->create();

        $this->artisan('media:backfill-embeddings')->assertFailed();
    }
}
