<?php

namespace Tests\Feature;

use App\Enums\MediaStatus;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\User;
use App\Models\WhitelistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Protokoll der Kurations- und Admin-Aktionen (Spec 5).
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_anlegen_wird_protokolliert(): void
    {
        $kurator = User::factory()->kurator()->create();
        $this->actingAs($kurator);

        $media = Media::factory()->create(['title' => 'Gefühlskarten Basis']);

        $eintrag = AuditLog::where('entity', 'Media')
            ->where('entity_id', $media->id)
            ->where('action', 'erstellt')
            ->firstOrFail();

        $this->assertSame($kurator->id, $eintrag->user_id);
        $this->assertSame($kurator->name, $eintrag->user_name);
        $this->assertSame('kurator', $eintrag->user_role);
        $this->assertSame('Gefühlskarten Basis', $eintrag->entity_label);
    }

    public function test_aenderung_protokolliert_alten_und_neuen_wert(): void
    {
        $kurator = User::factory()->kurator()->create();
        $media   = Media::factory()->create(['title' => 'Alter Titel']);

        $this->actingAs($kurator);
        $media->update(['title' => 'Neuer Titel', 'status' => MediaStatus::Ausgemustert]);

        $eintrag = AuditLog::where('action', 'geaendert')->latest('id')->firstOrFail();

        $this->assertSame('Alter Titel', $eintrag->diff['title']['alt']);
        $this->assertSame('Neuer Titel', $eintrag->diff['title']['neu']);
        $this->assertSame('verfuegbar', $eintrag->diff['status']['alt']);
        $this->assertSame('ausgemustert', $eintrag->diff['status']['neu']);
    }

    public function test_loeschen_wird_protokolliert(): void
    {
        $admin = User::factory()->admin()->create();
        $eintragObjekt = WhitelistEntry::create([
            'type'     => 'verlag',
            'name'     => 'Beltz Juventa',
            'added_by' => $admin->id,
        ]);

        $this->actingAs($admin);
        $eintragObjekt->delete();

        $eintrag = AuditLog::where('entity', 'WhitelistEntry')
            ->where('action', 'geloescht')
            ->firstOrFail();

        $this->assertSame('Beltz Juventa', $eintrag->entity_label);
    }

    /**
     * Der wichtigste Test: Passwort-Hashes dürfen unter keinen Umständen
     * im Protokoll landen – es ist für Kuratoren und Admins einsehbar.
     */
    public function test_passwoerter_landen_nicht_im_protokoll(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $nutzer = User::factory()->create();
        $nutzer->update(['password' => 'ein-neues-geheimes-passwort']);

        $hash = $nutzer->fresh()->password;

        foreach (AuditLog::all() as $eintrag) {
            $roh = json_encode($eintrag->diff, JSON_UNESCAPED_UNICODE) ?: '';

            $this->assertStringNotContainsString(
                'ein-neues-geheimes-passwort',
                $roh,
                'Klartext-Passwort im Protokoll gefunden.'
            );
            $this->assertStringNotContainsString(
                $hash,
                $roh,
                'Passwort-Hash im Protokoll gefunden.'
            );
        }

        // Die Tatsache der Änderung bleibt sichtbar, nur der Wert nicht.
        $aenderung = AuditLog::where('entity', 'User')
            ->where('action', 'geaendert')
            ->latest('id')
            ->firstOrFail();

        $this->assertArrayHasKey('password', $aenderung->diff);
        $this->assertSame('(geändert, nicht protokolliert)', $aenderung->diff['password']['neu']);
    }

    /**
     * `remember_token` ändert sich bei jedem „Angemeldet bleiben" und ist ein
     * Sitzungsgeheimnis. Es steht auf der Ignorierliste – eine Änderung nur
     * daran erzeugt deshalb gar keinen Eintrag.
     */
    public function test_remember_token_erzeugt_keinen_eintrag(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $nutzer = User::factory()->create();
        $vorher = AuditLog::where('entity', 'User')->where('action', 'geaendert')->count();

        $nutzer->update(['remember_token' => 'geheimes-token-xyz']);

        $this->assertSame(
            $vorher,
            AuditLog::where('entity', 'User')->where('action', 'geaendert')->count(),
            'Eine reine remember_token-Änderung darf keinen Protokolleintrag erzeugen.'
        );

        $this->assertStringNotContainsString(
            'geheimes-token-xyz',
            AuditLog::all()->pluck('diff')->toJson(),
            'Sitzungs-Token im Protokoll gefunden.'
        );
    }

    public function test_ohne_echte_aenderung_entsteht_kein_eintrag(): void
    {
        $kurator = User::factory()->kurator()->create();
        $media   = Media::factory()->create(['title' => 'Unverändert']);

        $this->actingAs($kurator);
        $vorher = AuditLog::where('action', 'geaendert')->count();

        $media->update(['title' => 'Unverändert']);

        $this->assertSame($vorher, AuditLog::where('action', 'geaendert')->count());
    }

    public function test_systemaktionen_ohne_anmeldung_werden_als_system_gefuehrt(): void
    {
        // Kein actingAs – entspricht Konsolenbefehlen und Scheduler-Läufen.
        Media::factory()->create();

        $eintrag = AuditLog::where('entity', 'Media')->firstOrFail();

        $this->assertNull($eintrag->user_id);
        $this->assertSame('System', $eintrag->user_name);
    }

    public function test_lange_texte_werden_gekuerzt(): void
    {
        $kurator = User::factory()->kurator()->create();
        $this->actingAs($kurator);

        $media = Media::factory()->create();
        $media->update(['summary' => str_repeat('a', 500)]);

        $eintrag = AuditLog::where('action', 'geaendert')->latest('id')->firstOrFail();

        $this->assertStringEndsWith('… (gekürzt)', $eintrag->diff['summary']['neu']);
        $this->assertLessThan(400, mb_strlen($eintrag->diff['summary']['neu']));
    }

    /**
     * `settings` nutzt einen Textschlüssel als Primärschlüssel – deshalb ist
     * audit_logs.entity_id ein String und kein Integer.
     */
    public function test_einstellungen_mit_textschluessel_werden_protokolliert(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        \App\Models\Setting::set('standard_leihdauer', '14');
        \App\Models\Setting::set('standard_leihdauer', '21');

        $anlegen = AuditLog::where('entity', 'Setting')->where('action', 'erstellt')->firstOrFail();
        $this->assertSame('standard_leihdauer', $anlegen->entity_id);

        $aendern = AuditLog::where('entity', 'Setting')->where('action', 'geaendert')->firstOrFail();
        $this->assertSame('14', $aendern->diff['value']['alt']);
        $this->assertSame('21', $aendern->diff['value']['neu']);
    }

    /**
     * Beim `deleted`-Event ist die users-Zeile bereits entfernt. Ein
     * Protokolleintrag, der auf sie verweist, verletzt den Fremdschlüssel –
     * das Löschen bräche dann mit einer QueryException ab.
     */
    public function test_konto_kann_sich_selbst_loeschen(): void
    {
        $user = User::factory()->create(['name' => 'Selbstlöscher']);
        $this->actingAs($user);

        $user->delete();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        $eintrag = AuditLog::where('entity', 'User')
            ->where('action', 'geloescht')
            ->firstOrFail();

        $this->assertNull($eintrag->user_id, 'Verweis auf das gelöschte Konto muss leer sein.');
        $this->assertSame('Selbstlöscher', $eintrag->user_name, 'Der Name muss lesbar bleiben.');
    }

    public function test_fremdes_konto_loeschen_behaelt_den_verweis_auf_den_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $opfer = User::factory()->create(['name' => 'Gelöschtes Konto']);
        $opfer->delete();

        $eintrag = AuditLog::where('entity', 'User')
            ->where('action', 'geloescht')
            ->firstOrFail();

        $this->assertSame($admin->id, $eintrag->user_id);
        $this->assertSame('Gelöschtes Konto', $eintrag->entity_label);
    }

    public function test_protokollseite_ist_nur_fuer_admins(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.audit'))
            ->assertForbidden();

        $this->actingAs(User::factory()->kurator()->create())
            ->get(route('admin.audit'))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.audit'))
            ->assertOk()
            ->assertSee('Protokoll');
    }

    public function test_protokollseite_zeigt_eintraege(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Media::factory()->create(['title' => 'Wut-Buch für Kinder']);

        $this->get(route('admin.audit'))
            ->assertOk()
            ->assertSee('Wut-Buch für Kinder', escape: false);
    }
}
