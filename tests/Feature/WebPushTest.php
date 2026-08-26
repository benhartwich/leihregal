<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Media;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\LoanOverdueNotification;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Web-Push (Phase 8).
 *
 * Der tatsächliche Versand an die Push-Dienste der Browser lässt sich hier
 * nicht prüfen – getestet werden Abo-Verwaltung, Kanalauswahl und Nutzlast.
 */
class WebPushTest extends TestCase
{
    use RefreshDatabase;

    private function abodaten(string $endpunkt = 'https://fcm.googleapis.com/fcm/send/abc123'): array
    {
        return [
            'endpoint'  => $endpunkt,
            'publicKey' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM=',
            'authToken' => 'tBHItJI5svbpez7KI4CCXg==',
        ];
    }

    // ── Abo-Verwaltung ───────────────────────────────────────────────────────

    public function test_geraet_kann_sich_anmelden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('push.subscribe'), $this->abodaten())
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertSame($user->id, PushSubscription::first()->user_id);
    }

    public function test_wiederholte_anmeldung_erzeugt_keinen_zweiten_eintrag(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push.subscribe'), $this->abodaten())->assertOk();
        $this->actingAs($user)->postJson(route('push.subscribe'), $this->abodaten())->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    public function test_mehrere_geraete_je_konto_sind_moeglich(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push.subscribe'), $this->abodaten('https://push.example/a'))->assertOk();
        $this->actingAs($user)->postJson(route('push.subscribe'), $this->abodaten('https://push.example/b'))->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 2);
    }

    public function test_geraet_kann_sich_abmelden(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('push.subscribe'), $this->abodaten())->assertOk();

        $this->actingAs($user)
            ->deleteJson(route('push.unsubscribe'), ['endpoint' => $this->abodaten()['endpoint']])
            ->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    /**
     * Sonst könnte jemand mit einem erratenen Endpunkt fremde Geräte
     * stillschalten.
     */
    public function test_fremdes_abo_kann_nicht_abgemeldet_werden(): void
    {
        $fremder = User::factory()->create();
        $this->actingAs($fremder)->postJson(route('push.subscribe'), $this->abodaten())->assertOk();

        $this->actingAs(User::factory()->create())
            ->deleteJson(route('push.unsubscribe'), ['endpoint' => $this->abodaten()['endpoint']])
            ->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    public function test_unvollstaendige_anmeldung_wird_abgelehnt(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('push.subscribe'), ['endpoint' => 'https://push.example/a'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_gaeste_koennen_sich_nicht_anmelden(): void
    {
        $this->postJson(route('push.subscribe'), $this->abodaten())->assertUnauthorized();
    }

    public function test_geloeschtes_konto_nimmt_seine_abos_mit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('push.subscribe'), $this->abodaten())->assertOk();

        $user->delete();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    // ── Kanalauswahl ─────────────────────────────────────────────────────────

    private function ausleihe(User $user): Loan
    {
        Notification::fake();
        $loan = app(LoanService::class)->borrow(Media::factory()->create(), $user);
        Notification::fake(); // Aufzeichnung zurücksetzen

        return $loan;
    }

    public function test_ohne_abo_kein_push_kanal(): void
    {
        $user = User::factory()->create();
        $loan = $this->ausleihe($user);

        $user->notify(new LoanOverdueNotification($loan));

        Notification::assertSentTo($user, LoanOverdueNotification::class,
            fn ($n, array $kanaele) => ! in_array('webpush', $kanaele, true));
    }

    public function test_mit_abo_kommt_der_push_kanal_dazu(): void
    {
        $user = User::factory()->create();
        $loan = $this->ausleihe($user);

        PushSubscription::create([
            'user_id'       => $user->id,
            'endpoint'      => 'https://push.example/a',
            'endpoint_hash' => PushSubscription::hashFor('https://push.example/a'),
            'public_key'    => 'x',
            'auth_token'    => 'y',
        ]);

        $user->notify(new LoanOverdueNotification($loan->fresh()));

        Notification::assertSentTo($user, LoanOverdueNotification::class,
            fn ($n, array $kanaele) => in_array('webpush', $kanaele, true)
                                    && in_array('mail', $kanaele, true)
                                    && in_array('database', $kanaele, true));
    }

    // ── Nutzlast ─────────────────────────────────────────────────────────────

    public function test_nutzlast_enthaelt_titel_text_und_ziel(): void
    {
        Mail::fake();

        $user  = User::factory()->create();
        $media = Media::factory()->create(['title' => 'Wut-Karten']);
        $loan  = app(LoanService::class)->borrow($media, $user);

        $nutzlast = (new LoanOverdueNotification($loan->fresh()))->toWebPush($user);

        $this->assertSame('Rückgabe überfällig', $nutzlast['title']);
        $this->assertStringContainsString('Wut-Karten', $nutzlast['body']);
        $this->assertStringContainsString('/ausleihen', $nutzlast['url']);
        $this->assertArrayHasKey('icon', $nutzlast);
        $this->assertArrayHasKey('tag', $nutzlast);
    }

    public function test_endpunkt_hash_ist_stabil(): void
    {
        $this->assertSame(
            PushSubscription::hashFor('https://push.example/a'),
            PushSubscription::hashFor('https://push.example/a')
        );

        $this->assertNotSame(
            PushSubscription::hashFor('https://push.example/a'),
            PushSubscription::hashFor('https://push.example/b')
        );
    }

    public function test_schluessel_erscheinen_nicht_in_json_ausgaben(): void
    {
        $abo = PushSubscription::create([
            'user_id'       => User::factory()->create()->id,
            'endpoint'      => 'https://push.example/a',
            'endpoint_hash' => PushSubscription::hashFor('https://push.example/a'),
            'public_key'    => 'geheimer-public-key',
            'auth_token'    => 'geheimes-token',
        ]);

        $json = $abo->toJson();

        $this->assertStringNotContainsString('geheimer-public-key', $json);
        $this->assertStringNotContainsString('geheimes-token', $json);
    }
}
