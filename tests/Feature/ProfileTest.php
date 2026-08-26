<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Testet die tatsächlich geroutete Profilseite (Volt-Seite `pages.profile`).
 *
 * Die früheren Tests prüften die Breeze-Unterkomponenten unter
 * livewire/profile/. Diese waren seit Phase 1 nicht mehr eingebunden –
 * /profile rendert die eigene Volt-Seite – und wurden am 2026-08-06
 * zusammen mit den zugehörigen Tests entfernt.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profilseite_wird_angezeigt(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Mein Profil')
            ->assertSee('Persönliche Daten')
            ->assertSee('Passwort ändern');
    }

    public function test_profildaten_koennen_geaendert_werden(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('pages.profile')
            ->set('name', 'Test Nutzer')
            ->set('email', 'test@example.com')
            ->call('updateProfile')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('Test Nutzer', $user->name);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_bereits_vergebene_email_wird_abgelehnt(): void
    {
        $user   = User::factory()->create();
        $andere = User::factory()->create(['email' => 'belegt@example.com']);

        Volt::actingAs($user)
            ->test('pages.profile')
            ->set('name', $user->name)
            ->set('email', 'belegt@example.com')
            ->call('updateProfile')
            ->assertHasErrors('email');

        $this->assertNotSame('belegt@example.com', $user->refresh()->email);
    }

    public function test_passwort_kann_mit_aktuellem_passwort_geaendert_werden(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('pages.profile')
            ->set('current_password', 'password')
            ->set('password', 'neues-passwort')
            ->set('password_confirmation', 'neues-passwort')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('neues-passwort', $user->refresh()->password));
    }

    /**
     * Kern der Absicherung: Eine übernommene Sitzung allein darf nicht
     * genügen, um das Konto dauerhaft zu kapern.
     */
    public function test_passwortwechsel_ohne_aktuelles_passwort_wird_abgelehnt(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('pages.profile')
            ->set('password', 'neues-passwort')
            ->set('password_confirmation', 'neues-passwort')
            ->call('updatePassword')
            ->assertHasErrors('current_password');

        $this->assertTrue(
            Hash::check('password', $user->refresh()->password),
            'Passwort wurde ohne Angabe des alten Passworts geändert.'
        );
    }

    public function test_falsches_aktuelles_passwort_wird_abgelehnt(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('pages.profile')
            ->set('current_password', 'falsches-passwort')
            ->set('password', 'neues-passwort')
            ->set('password_confirmation', 'neues-passwort')
            ->call('updatePassword')
            ->assertHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_neues_passwort_muss_sich_vom_alten_unterscheiden(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('pages.profile')
            ->set('current_password', 'password')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('updatePassword')
            ->assertHasErrors('password');
    }

    public function test_zu_kurzes_passwort_wird_abgelehnt(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('pages.profile')
            ->set('current_password', 'password')
            ->set('password', 'kurz')
            ->set('password_confirmation', 'kurz')
            ->call('updatePassword')
            ->assertHasErrors('password');
    }

    /**
     * Die Prüfung des aktuellen Passworts wäre sonst selbst ein Orakel,
     * über das sich das Passwort erraten liesse.
     */
    public function test_zu_viele_fehlversuche_werden_gebremst(): void
    {
        $user = User::factory()->create();

        $component = Volt::actingAs($user)->test('pages.profile');

        for ($i = 0; $i < 5; $i++) {
            $component
                ->set('current_password', 'falsch-' . $i)
                ->set('password', 'neues-passwort')
                ->set('password_confirmation', 'neues-passwort')
                ->call('updatePassword');
        }

        // Sechster Versuch – diesmal mit dem RICHTIGEN Passwort. Die Bremse
        // muss trotzdem greifen.
        $component
            ->set('current_password', 'password')
            ->set('password', 'neues-passwort')
            ->set('password_confirmation', 'neues-passwort')
            ->call('updatePassword')
            ->assertHasErrors('current_password');

        $this->assertTrue(
            Hash::check('password', $user->refresh()->password),
            'Passwortwechsel trotz aktiver Sperre durchgelassen.'
        );
    }

    public function test_themen_abo_kann_an_und_abgeschaltet_werden(): void
    {
        $user = User::factory()->create();

        $component = Volt::actingAs($user)->test('pages.profile');

        $component->call('toggleSubscription', 'Trauer');
        $this->assertDatabaseHas('tag_subscriptions', [
            'user_id' => $user->id,
            'tag'     => 'Trauer',
        ]);

        $component->call('toggleSubscription', 'Trauer');
        $this->assertDatabaseMissing('tag_subscriptions', [
            'user_id' => $user->id,
            'tag'     => 'Trauer',
        ]);
    }

    /**
     * Belegt, dass die AuthenticateSession-Middleware aktiv ist und Sitzungen
     * mit veraltetem Passwort-Hash abmeldet. Genau darauf beruht die Zusage
     * „Sie werden auf allen anderen Geräten abgemeldet".
     *
     * Eine Sitzung, die vor dem Passwortwechsel begonnen hat, trägt noch den
     * alten Hash – das wird hier nachgestellt.
     */
    public function test_sitzung_mit_altem_passwort_hash_wird_abgemeldet(): void
    {
        $user      = User::factory()->create();
        $alterHash = $user->password;

        $user->update(['password' => 'ein-ganz-neues-passwort']);

        $this->actingAs($user)
            ->withSession(['password_hash_web' => $alterHash])
            ->get(route('profile'))
            ->assertRedirect(route('login'));
    }

    public function test_sitzung_mit_aktuellem_passwort_hash_bleibt_bestehen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['password_hash_web' => $user->password])
            ->get(route('profile'))
            ->assertOk();
    }

    public function test_profil_ist_fuer_gaeste_gesperrt(): void
    {
        $this->get('/profile')->assertRedirect(route('login'));
    }
}
