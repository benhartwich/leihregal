<?php

namespace Tests\Feature;

use App\Mail\ReservationReadyMail;
use App\Models\Media;
use App\Models\User;
use App\Notifications\LoanOverdueNotification;
use App\Notifications\ReservationReadyNotification;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Benachrichtigungs-Center (Spec 4.6, Phase 6).
 *
 * Bisher liefen Hinweise ausschliesslich per E-Mail. Der Umbau auf
 * Laravel-Notifications ergänzt den Datenbank-Kanal – der E-Mail-Versand muss
 * dabei unverändert bleiben.
 */
class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Beide Kanäle werden angesprochen: E-Mail wie bisher, zusätzlich die
     * Datenbank für das Center.
     */
    public function test_benachrichtigung_geht_ueber_beide_kanaele(): void
    {
        Notification::fake();

        $media    = Media::factory()->create();
        $service  = app(LoanService::class);
        $loan     = $service->borrow($media, User::factory()->create());
        $wartende = User::factory()->create();

        $service->reserve($media->fresh(), $wartende);
        $service->returnMedia($loan);

        Notification::assertSentTo(
            $wartende,
            ReservationReadyNotification::class,
            function ($notification, array $kanaele) {
                return in_array('mail', $kanaele, true)
                    && in_array('database', $kanaele, true);
            }
        );
    }

    public function test_benachrichtigung_landet_im_center(): void
    {
        Mail::fake();

        $media    = Media::factory()->create();
        $service  = app(LoanService::class);
        $loan     = $service->borrow($media, User::factory()->create());
        $wartende = User::factory()->create();

        $service->reserve($media->fresh(), $wartende);
        $service->returnMedia($loan);

        $this->assertDatabaseCount('notifications', 1);

        $eintrag = $wartende->notifications()->firstOrFail();
        $this->assertSame('Reservierung abholbereit', $eintrag->data['titel']);
        $this->assertNull($eintrag->read_at);
    }

    /**
     * Kern des Umbaus: Der E-Mail-Teil wurde nicht neu geschrieben, sondern
     * gibt weiterhin das bestehende Mailable zurück. Betreff und Vorlage
     * bleiben dadurch unverändert.
     *
     * (Auf Mail::assertSent lässt sich das nicht prüfen: Gibt eine
     * Notification ein Mailable zurück, versendet Laravel es über
     * Mailable::send() – MailFake verbucht das nicht als Mailable.)
     */
    public function test_mailinhalt_bleibt_unveraendert(): void
    {
        $media       = Media::factory()->create(['title' => 'Wut-Karten']);
        $service     = app(LoanService::class);
        $leiher      = User::factory()->create();
        $loan        = $service->borrow($media, $leiher);
        $wartende    = User::factory()->create();

        Mail::fake();
        $reservierung = $service->reserve($media->fresh(), $wartende);

        $mailable = (new ReservationReadyNotification($reservierung))->toMail($wartende);

        $this->assertInstanceOf(ReservationReadyMail::class, $mailable);
        $this->assertStringContainsString('Wut-Karten', $mailable->envelope()->subject);
        $this->assertStringContainsString('abholbereit', $mailable->envelope()->subject);
    }

    public function test_center_zeigt_eigene_benachrichtigungen(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['title' => 'Trauerbuch']);
        $loan  = app(LoanService::class)->borrow($media, $user);

        Mail::fake();
        $user->notify(new LoanOverdueNotification($loan));

        $this->actingAs($user)
            ->get(route('notifications'))
            ->assertOk()
            ->assertSee('Rückgabe überfällig')
            ->assertSee('Trauerbuch', escape: false);
    }

    public function test_fremde_benachrichtigungen_sind_nicht_sichtbar(): void
    {
        Notification::fake();

        $fremder = User::factory()->create();
        $media   = Media::factory()->create(['title' => 'Geheimes Buch']);
        $loan    = app(LoanService::class)->borrow($media, $fremder);

        Notification::fake(); // zurücksetzen
        Mail::fake();
        $fremder->notify(new LoanOverdueNotification($loan));

        $this->actingAs(User::factory()->create())
            ->get(route('notifications'))
            ->assertOk()
            ->assertDontSee('Geheimes Buch', escape: false);
    }

    public function test_einzelne_benachrichtigung_kann_gelesen_werden(): void
    {
        Mail::fake();

        $user  = User::factory()->create();
        $media = Media::factory()->create();
        $loan  = app(LoanService::class)->borrow($media, $user);
        $user->notify(new LoanOverdueNotification($loan));

        $eintrag = $user->notifications()->firstOrFail();
        $this->assertNull($eintrag->read_at);

        Volt::actingAs($user)
            ->test('pages.notifications')
            ->call('alsGelesenMarkieren', $eintrag->id);

        $this->assertNotNull($eintrag->fresh()->read_at);
    }

    public function test_alle_koennen_auf_einmal_gelesen_werden(): void
    {
        Mail::fake();

        $user  = User::factory()->create();
        $media = Media::factory()->create();
        $loan  = app(LoanService::class)->borrow($media, $user);

        $user->notify(new LoanOverdueNotification($loan));
        $user->notify(new LoanOverdueNotification($loan));

        $this->assertSame(2, $user->unreadNotifications()->count());

        Volt::actingAs($user)
            ->test('pages.notifications')
            ->call('alleAlsGelesenMarkieren');

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_benachrichtigung_kann_entfernt_werden(): void
    {
        Mail::fake();

        $user  = User::factory()->create();
        $media = Media::factory()->create();
        $loan  = app(LoanService::class)->borrow($media, $user);
        $user->notify(new LoanOverdueNotification($loan));

        $eintrag = $user->notifications()->firstOrFail();

        Volt::actingAs($user)
            ->test('pages.notifications')
            ->call('loeschen', $eintrag->id);

        $this->assertDatabaseCount('notifications', 0);
    }

    /**
     * Wer eine fremde ID untergeschoben bekommt, darf damit nichts anfangen –
     * die Abfrage geht immer über die Beziehung des angemeldeten Kontos.
     */
    public function test_fremde_benachrichtigung_kann_nicht_geloescht_werden(): void
    {
        Mail::fake();

        $fremder = User::factory()->create();
        $media   = Media::factory()->create();
        $loan    = app(LoanService::class)->borrow($media, $fremder);
        $fremder->notify(new LoanOverdueNotification($loan));

        $eintrag = $fremder->notifications()->firstOrFail();

        Volt::actingAs(User::factory()->create())
            ->test('pages.notifications')
            ->call('loeschen', $eintrag->id);

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_glocke_zeigt_anzahl_ungelesener(): void
    {
        Mail::fake();

        $user  = User::factory()->create();
        $media = Media::factory()->create();
        $loan  = app(LoanService::class)->borrow($media, $user);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->notify(new LoanOverdueNotification($loan));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Benachrichtigungen');

        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
    }

    public function test_center_ist_fuer_gaeste_gesperrt(): void
    {
        $this->get(route('notifications'))->assertRedirect(route('login'));
    }

    public function test_ueberfaelligkeits_befehl_erzeugt_beide_kanaele(): void
    {
        Mail::fake();

        $user  = User::factory()->create();
        $media = Media::factory()->create();
        $loan  = app(LoanService::class)->borrow($media, $user);
        $loan->update(['due_at' => now()->subDays(3)]);

        $this->artisan('loans:remind-overdue')->assertSuccessful();

        $this->assertSame(1, $user->unreadNotifications()->count());
        $this->assertNotNull($loan->fresh()->last_reminded_at);
    }
}
