<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Services\ChatService;
use App\Services\CurationService;
use App\Services\GoogleBooksService;
use App\Services\MediaAiService;
use App\Services\UsageAdvisorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Die Bibliothek muss ohne jeden API-Schlüssel vollständig benutzbar sein.
 *
 * Das ist ein zugesagtes Verhalten und keine Nebensache: Eine Einrichtung soll
 * die Anwendung aufsetzen und benutzen können, bevor sie über KI-Dienste
 * überhaupt entscheidet.
 *
 * Anlass für diese Tests war ein konkreter Fehler: In den Diensten stand
 * `config('services.anthropic.key', '')`. Der zweite Parameter greift aber nur,
 * wenn der Schlüssel im Konfigurations-Array *fehlt* – ein vorhandener Eintrag
 * mit dem Wert null kommt als null durch und lief gegen eine typisierte
 * String-Eigenschaft. Ergebnis war ein HTTP 500 auf jeder Mediendetailseite,
 * und zwar ausschließlich in einer frischen Installation ohne Schlüssel.
 */
class OhneApiSchluesselTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Kein leerer String, sondern null – so sieht eine .env aus, in der
        // die Zeile gar nicht vorkommt.
        config([
            'services.anthropic.key'    => null,
            'services.openai.key'       => null,
            'services.google_books.key' => null,
        ]);
    }

    public function test_dienste_lassen_sich_ohne_schluessel_erzeugen(): void
    {
        foreach ([
            MediaAiService::class,
            ChatService::class,
            CurationService::class,
            UsageAdvisorService::class,
            GoogleBooksService::class,
        ] as $dienst) {
            $this->assertInstanceOf($dienst, app($dienst));
        }
    }

    public function test_mediendetailseite_bleibt_erreichbar(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['title' => 'Ohne Schlüssel']);

        $this->actingAs($user)
            ->get("/medien/{$media->id}")
            ->assertOk()
            ->assertSee('Ohne Schlüssel');
    }

    public function test_medienliste_und_uebersicht_bleiben_erreichbar(): void
    {
        $user = User::factory()->create();
        Media::factory()->create();

        $this->actingAs($user)->get('/medien')->assertOk();
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_assistent_meldet_sich_ab_statt_zu_scheitern(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/assistent')->assertOk();

        $antwort = app(ChatService::class)->ask('Beliebige Situation', collect());

        $this->assertSame([], $antwort['media_ids']);
        $this->assertStringContainsString('nicht verfügbar', $antwort['text']);
    }
}
