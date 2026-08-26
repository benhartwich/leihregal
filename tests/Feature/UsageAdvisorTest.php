<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Einsatz-Assistent pro Medium (Phase 8).
 */
class UsageAdvisorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.anthropic.key', 'test-schluessel');
        Http::preventStrayRequests();
        Sleep::fake();
        RateLimiter::clear('einsatz-assistent:1');
    }

    private function antwort(string $text = 'Schritt eins. Schritt zwei. Schritt drei.'): array
    {
        return ['content' => [['type' => 'text', 'text' => $text]]];
    }

    public function test_liefert_vorschlaege_zur_situation(): void
    {
        Http::fake(fn () => Http::response($this->antwort('Setzen Sie die Karten zunächst ohne Worte ein.'), 200));

        $user  = User::factory()->create();
        $media = Media::factory()->create(['title' => 'Gefühlskarten']);

        Volt::actingAs($user)
            ->test('pages.media.show', ['media' => $media])
            ->set('advisorSituation', 'Ein Kind zieht sich nach Streit stundenlang zurück.')
            ->call('askAdvisor')
            ->assertSet('advisorError', null)
            ->assertSet('advisorAnswer', 'Setzen Sie die Karten zunächst ohne Worte ein.');
    }

    public function test_zu_kurze_beschreibung_wird_abgelehnt(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create();

        Volt::actingAs($user)
            ->test('pages.media.show', ['media' => $media])
            ->set('advisorSituation', 'zu kurz')
            ->call('askAdvisor')
            ->assertSet('advisorAnswer', null);

        // Keine Anfrage darf hinausgegangen sein.
        Http::assertNothingSent();
    }

    /**
     * Spec 5: Vor jedem Modellaufruf muss der PII-Filter greifen. Der
     * Einsatz-Assistent darf da keine Ausnahme sein.
     */
    public function test_personenbezogene_daten_verlassen_den_server_nicht(): void
    {
        Http::fake(fn () => Http::response($this->antwort(), 200));

        $user  = User::factory()->create();
        $media = Media::factory()->create();

        Volt::actingAs($user)
            ->test('pages.media.show', ['media' => $media])
            ->set('advisorSituation', 'Der Klient heißt Michael, geboren am 12.03.2011, erreichbar unter 0664 1234567.')
            ->call('askAdvisor')
            ->assertSet('advisorRedacted', true);

        Http::assertSent(function ($request) {
            $inhalt = json_encode($request->data());

            foreach (['Michael', '12.03.2011', '0664 1234567'] as $geheim) {
                if (str_contains($inhalt, $geheim)) {
                    return false;
                }
            }

            return str_contains($inhalt, '[NAME]')
                && str_contains($inhalt, '[DATUM]')
                && str_contains($inhalt, '[TELEFON]');
        });
    }

    public function test_das_medium_wird_dem_modell_mitgegeben(): void
    {
        Http::fake(fn () => Http::response($this->antwort(), 200));

        $user  = User::factory()->create();
        $media = Media::factory()->create([
            'title'   => 'Wut-Werkstatt',
            'summary' => 'Übungen zum Umgang mit Wut.',
        ]);

        Volt::actingAs($user)
            ->test('pages.media.show', ['media' => $media])
            ->set('advisorSituation', 'Ein Kind wirft bei Frust regelmässig Gegenstände.')
            ->call('askAdvisor');

        Http::assertSent(function ($request) {
            $system = $request->data()['system'] ?? '';

            return str_contains($system, 'Wut-Werkstatt')
                && str_contains($system, 'Übungen zum Umgang mit Wut.');
        });
    }

    public function test_fehler_der_schnittstelle_wird_gemeldet(): void
    {
        Http::fake(fn () => Http::response(['error' => 'kaputt'], 500));

        $user  = User::factory()->create();
        $media = Media::factory()->create();

        $component = Volt::actingAs($user)
            ->test('pages.media.show', ['media' => $media])
            ->set('advisorSituation', 'Eine ausreichend lange Situationsbeschreibung.')
            ->call('askAdvisor');

        $component->assertSet('advisorAnswer', null);
        $this->assertNotNull($component->get('advisorError'));
    }

    public function test_ohne_api_schluessel_kommt_ein_hinweis(): void
    {
        config()->set('services.anthropic.key', '');

        $user  = User::factory()->create();
        $media = Media::factory()->create();

        $component = Volt::actingAs($user)
            ->test('pages.media.show', ['media' => $media])
            ->set('advisorSituation', 'Eine ausreichend lange Situationsbeschreibung.')
            ->call('askAdvisor');

        $this->assertStringContainsString('nicht verfügbar', $component->get('advisorError'));
        Http::assertNothingSent();
    }

    /**
     * Der Aufruf kostet Kontingent wie der Situations-Assistent, hängt aber
     * nicht an dessen throttle:ai-Route – die Bremse muss hier selbst greifen.
     */
    public function test_zu_viele_anfragen_werden_gebremst(): void
    {
        Http::fake(fn () => Http::response($this->antwort(), 200));

        $user  = User::factory()->create();
        $media = Media::factory()->create();

        $component = Volt::actingAs($user)->test('pages.media.show', ['media' => $media]);

        for ($i = 0; $i < 10; $i++) {
            $component
                ->set('advisorSituation', "Eine ausreichend lange Situationsbeschreibung Nummer {$i}.")
                ->call('askAdvisor');
        }

        $component
            ->set('advisorSituation', 'Noch eine ausreichend lange Situationsbeschreibung.')
            ->call('askAdvisor');

        $this->assertStringContainsString('Zu viele Anfragen', $component->get('advisorError'));
        Http::assertSentCount(10);
    }
}
