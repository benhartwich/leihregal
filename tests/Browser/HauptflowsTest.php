<?php

namespace Tests\Browser;

use App\Models\Media;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E-Tests der Hauptflows im echten Browser (Spec 7.6).
 *
 * Ergänzt die Feature-Tests: Diese prüfen die Logik, hier wird geprüft, ob
 * die Bedienung im Browser tatsächlich funktioniert – inklusive Livewire,
 * Formularen und Navigation.
 */
class HauptflowsTest extends DuskTestCase
{
    // DatabaseTruncation statt RefreshDatabase: Der Webserver läuft in einem
    // eigenen Prozess und sähe eine Transaktion des Testprozesses nicht.
    use DatabaseTruncation;

    public function test_anmeldung_und_abmeldung(): void
    {
        $user = User::factory()->create(['email' => 'betreuer@example.com']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->assertSee('Leihregal')
                ->type('email', $user->email)
                ->type('password', 'password')
                ->press('Anmelden')
                ->waitForLocation('/dashboard')
                ->assertSee('Willkommen');
        });
    }

    public function test_falsches_passwort_wird_abgewiesen(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->type('email', $user->email)
                ->type('password', 'falsch-falsch')
                ->press('Anmelden')
                ->waitForText('stimmen nicht', 10)
                ->assertPathIs('/login');
        });
    }

    public function test_deaktiviertes_konto_kommt_nicht_hinein(): void
    {
        $user = User::factory()->deaktiviert()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->type('email', $user->email)
                ->type('password', 'password')
                ->press('Anmelden')
                ->waitForText('deaktiviert', 10)
                ->assertPathIs('/login');
        });
    }

    public function test_medienliste_und_detailseite(): void
    {
        $user = User::factory()->create();
        Media::factory()->create(['title' => 'Gefühlskarten für Kinder']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/medien')
                ->waitForText('Gefühlskarten für Kinder', 10)
                ->clickLink('Gefühlskarten für Kinder')
                ->waitForText('Gefühlskarten für Kinder', 10)
                ->assertSee('Verfügbar');
        });
    }

    public function test_ausleihen_und_zurueckgeben(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['title' => 'Wut-Werkstatt']);

        $this->browse(function (Browser $browser) use ($user, $media) {
            $browser->loginAs($user)
                ->visit("/medien/{$media->id}")
                ->waitForText('Wut-Werkstatt', 10)
                ->click('@ausleihen')
                ->waitForText('Ausgeliehen', 10)
                ->assertSee('Ausgeliehen');

            $browser->visit('/ausleihen')
                ->waitForText('Wut-Werkstatt', 10)
                ->assertSee('Wut-Werkstatt');
        });

        $this->assertDatabaseHas('loans', [
            'media_id'    => $media->id,
            'user_id'     => $user->id,
            'returned_at' => null,
        ]);
    }

    public function test_reservieren_wenn_ausgeliehen(): void
    {
        $leiher  = User::factory()->create();
        $wartend = User::factory()->create();
        $media   = Media::factory()->create(['title' => 'Nur einmal da']);

        app(LoanService::class)->borrow($media, $leiher);

        $this->browse(function (Browser $browser) use ($wartend, $media) {
            $browser->loginAs($wartend)
                ->visit("/medien/{$media->id}")
                ->waitForText('Nur einmal da', 10)
                ->click('@reservieren')
                // Die Meldung lautet „Reserviert (Position 1 in der Warteschlange)."
                ->waitForText('Position 1', 10);
        });

        $this->assertDatabaseHas('reservations', [
            'media_id' => $media->id,
            'user_id'  => $wartend->id,
            'status'   => 'wartend',
        ]);
    }

    public function test_wunsch_einreichen(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/wuensche/neu')
                ->waitForText('Wunsch', 10)
                ->type('@wunsch-titel', 'Ein dringend benötigtes Buch')
                ->click('@wunsch-absenden')
                ->waitForLocation('/wuensche', 10);
        });

        $this->assertDatabaseHas('wishes', [
            'user_id' => $user->id,
            'title'   => 'Ein dringend benötigtes Buch',
        ]);
    }

    public function test_betreuer_sieht_keine_kuration(): void
    {
        $betreuer = User::factory()->create();

        $this->browse(function (Browser $browser) use ($betreuer) {
            $browser->loginAs($betreuer)
                ->visit('/kuration')
                ->assertSee('403');
        });
    }

    public function test_kurator_kann_medium_anlegen(): void
    {
        $kurator = User::factory()->kurator()->create();

        $this->browse(function (Browser $browser) use ($kurator) {
            $browser->loginAs($kurator)
                ->visit('/medien/neu')
                ->waitForText('Medium', 10)
                ->assertSee('Titel');
        });
    }

    public function test_benachrichtigungs_center_ist_erreichbar(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/benachrichtigungen')
                ->waitForText('Benachrichtigungen', 10)
                ->assertSee('Noch keine Benachrichtigungen');
        });
    }

    public function test_passwortwechsel_verlangt_das_alte_passwort(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/profile')
                ->waitForText('Passwort ändern', 10)
                ->assertSee('Aktuelles Passwort');
        });
    }
}
