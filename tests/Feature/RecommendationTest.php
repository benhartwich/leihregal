<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Media;
use App\Models\MediaReview;
use App\Models\User;
use App\Services\LoanService;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Persönliche Empfehlungen und Feedback-Lernen (Phase 8).
 */
class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /**
     * Legt ein Embedding an, das in eine bestimmte Richtung zeigt.
     * `$richtung` bestimmt, welche Dimension dominiert – dadurch lassen sich
     * gezielt „ähnliche" und „unähnliche" Medien bauen.
     */
    private function embedding(Media $media, int $richtung): void
    {
        $vektor = array_fill(0, 1536, 0.0);
        $vektor[$richtung] = 1.0;

        DB::statement(
            'INSERT INTO media_embeddings (media_id, embedding, updated_at)
             VALUES (?, UNHEX(?), NOW())
             ON DUPLICATE KEY UPDATE embedding = VALUES(embedding)',
            [$media->id, bin2hex(pack('f*', ...$vektor))]
        );
    }

    private function abgeschlosseneAusleihe(User $user, Media $media): Loan
    {
        $loan = app(LoanService::class)->borrow($media, $user);
        app(LoanService::class)->returnMedia($loan);

        return $loan->fresh();
    }

    public function test_ohne_historie_kommen_die_beliebtesten_medien(): void
    {
        $user = User::factory()->create();

        $selten = Media::factory()->create(['title' => 'Selten geliehen']);
        $oft    = Media::factory()->create(['title' => 'Oft geliehen']);

        foreach (range(1, 3) as $i) {
            $this->abgeschlosseneAusleihe(User::factory()->create(), $oft);
        }
        $this->abgeschlosseneAusleihe(User::factory()->create(), $selten);

        $empfehlungen = app(RecommendationService::class)->fuerNutzer($user, 2);

        $this->assertSame('Oft geliehen', $empfehlungen->first()->title);
    }

    public function test_empfehlung_folgt_der_leihhistorie(): void
    {
        $user = User::factory()->create();

        $gelesen  = Media::factory()->create(['title' => 'Wut bei Kindern']);
        $aehnlich = Media::factory()->create(['title' => 'Wut verstehen']);
        $anders   = Media::factory()->create(['title' => 'Etwas ganz anderes']);

        $this->embedding($gelesen, 0);
        $this->embedding($aehnlich, 0);   // gleiche Richtung
        $this->embedding($anders, 900);   // andere Richtung

        $this->abgeschlosseneAusleihe($user, $gelesen);

        $empfehlungen = app(RecommendationService::class)->fuerNutzer($user, 2);

        $this->assertSame('Wut verstehen', $empfehlungen->first()->title);
    }

    public function test_bereits_geliehene_medien_werden_nicht_empfohlen(): void
    {
        $user    = User::factory()->create();
        $gelesen = Media::factory()->create(['title' => 'Schon gehabt']);
        $anderes = Media::factory()->create(['title' => 'Noch nicht gehabt']);

        $this->embedding($gelesen, 0);
        $this->embedding($anderes, 0);

        $this->abgeschlosseneAusleihe($user, $gelesen);

        $empfehlungen = app(RecommendationService::class)->fuerNutzer($user, 5);

        $this->assertFalse(
            $empfehlungen->contains('id', $gelesen->id),
            'Bereits entliehenes Medium wurde erneut empfohlen.'
        );
    }

    /**
     * Feedback-Lernen: Ein mit Daumen runter bewertetes Medium soll das
     * Profil von seiner Richtung wegziehen, nicht hin.
     */
    public function test_schlechte_bewertung_kehrt_die_richtung_um(): void
    {
        $user = User::factory()->create();

        $abgelehnt = Media::factory()->create(['title' => 'Fand ich schlecht']);
        $gleiche   = Media::factory()->create(['title' => 'Mehr davon']);

        $this->embedding($abgelehnt, 0);
        $this->embedding($gleiche, 0);

        $this->abgeschlosseneAusleihe($user, $abgelehnt);

        MediaReview::create([
            'media_id' => $abgelehnt->id,
            'user_id'  => $user->id,
            'rating'   => 0,
        ]);

        $profil = app(RecommendationService::class)->interessenprofil($user);
        $werte  = unpack('f*', $profil);

        // Bei Daumen runter zeigt die dominante Dimension in die Gegenrichtung.
        $this->assertLessThan(0, $werte[1], 'Negative Bewertung hat das Profil nicht umgekehrt.');
    }

    public function test_gute_bewertung_verstaerkt_die_richtung(): void
    {
        $user = User::factory()->create();

        $gemocht  = Media::factory()->create();
        $neutral  = Media::factory()->create();

        $this->embedding($gemocht, 0);
        $this->embedding($neutral, 1);

        $this->abgeschlosseneAusleihe($user, $gemocht);
        $this->abgeschlosseneAusleihe($user, $neutral);

        MediaReview::create(['media_id' => $gemocht->id, 'user_id' => $user->id, 'rating' => 1]);

        $werte = unpack('f*', app(RecommendationService::class)->interessenprofil($user));

        // Dimension 0 (bewertet, Gewicht 2) muss stärker wiegen als
        // Dimension 1 (unbewertet, Gewicht 1).
        $this->assertGreaterThan($werte[2], $werte[1]);
    }

    public function test_profil_ist_normalisiert(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create();
        $this->embedding($media, 5);
        $this->abgeschlosseneAusleihe($user, $media);

        $werte = unpack('f*', app(RecommendationService::class)->interessenprofil($user));

        $laenge = 0.0;
        foreach ($werte as $w) {
            $laenge += $w * $w;
        }

        $this->assertEqualsWithDelta(1.0, sqrt($laenge), 0.001, 'Profilvektor ist nicht auf Länge 1 normalisiert.');
    }

    public function test_ohne_embeddings_kein_profil(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create();
        $this->abgeschlosseneAusleihe($user, $media);

        $this->assertNull(app(RecommendationService::class)->interessenprofil($user));
    }

    public function test_ausgemusterte_medien_werden_nicht_empfohlen(): void
    {
        $user    = User::factory()->create();
        $gelesen = Media::factory()->create();
        $alt     = Media::factory()->ausgemustert()->create(['title' => 'Ausgemustert']);

        $this->embedding($gelesen, 0);
        $this->embedding($alt, 0);

        $this->abgeschlosseneAusleihe($user, $gelesen);

        $empfehlungen = app(RecommendationService::class)->fuerNutzer($user, 5);

        $this->assertFalse($empfehlungen->contains('id', $alt->id));
    }

    public function test_dashboard_zeigt_empfehlungen(): void
    {
        $user     = User::factory()->create();
        $gelesen  = Media::factory()->create();
        $vorschlag = Media::factory()->create(['title' => 'Empfohlener Titel']);

        $this->embedding($gelesen, 0);
        $this->embedding($vorschlag, 0);

        $this->abgeschlosseneAusleihe($user, $gelesen);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Für Sie ausgewählt')
            ->assertSee('Empfohlener Titel', escape: false);
    }
}
