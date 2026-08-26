<?php

namespace Tests\Feature;

use App\Enums\MediaStatus;
use App\Enums\ReservationStatus;
use App\Models\Media;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ReservationReadyNotification;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Ausleihe-Statemachine und Warteliste (Spec 4.3, 7.6).
 */
class LoanServiceTest extends TestCase
{
    use RefreshDatabase;

    private LoanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->service = app(LoanService::class);
    }

    // ── Ausleihe ─────────────────────────────────────────────────────────────

    public function test_verfuegbares_medium_kann_ausgeliehen_werden(): void
    {
        $media = Media::factory()->create();
        $user  = User::factory()->create();

        $loan = $this->service->borrow($media, $user);

        $this->assertNull($loan->returned_at);
        $this->assertSame($user->id, $loan->user_id);
        $this->assertSame(MediaStatus::Ausgeliehen, $media->fresh()->status);
    }

    public function test_leihdauer_folgt_der_einstellung(): void
    {
        Setting::set('loan_default_days', '7');
        $media = Media::factory()->create();

        $loan = $this->service->borrow($media, User::factory()->create());

        $this->assertSame(7, (int) $loan->borrowed_at->diffInDays($loan->due_at));
    }

    public function test_leihdauer_am_medium_schlaegt_die_einstellung(): void
    {
        Setting::set('loan_default_days', '7');
        $media = Media::factory()->create(['loan_days' => 21]);

        $loan = $this->service->borrow($media, User::factory()->create());

        $this->assertSame(21, (int) $loan->borrowed_at->diffInDays($loan->due_at));
    }

    public static function nichtVerleihbareStatus(): array
    {
        return [
            'ausgeliehen'     => [MediaStatus::Ausgeliehen],
            'reserviert'      => [MediaStatus::Reserviert],
            'in Aufbereitung' => [MediaStatus::InAufbereitung],
            'verloren'        => [MediaStatus::Verloren],
            'ausgemustert'    => [MediaStatus::Ausgemustert],
        ];
    }

    // PHPUnit 12 wertet @dataProvider-Annotationen nicht mehr aus – Attribut nötig.
    #[DataProvider('nichtVerleihbareStatus')]
    public function test_nicht_verfuegbares_medium_kann_nicht_ausgeliehen_werden(MediaStatus $status): void
    {
        $media = Media::factory()->create(['status' => $status]);

        $this->expectException(RuntimeException::class);
        $this->service->borrow($media, User::factory()->create());
    }

    public function test_dasselbe_medium_kann_nicht_doppelt_ausgeliehen_werden(): void
    {
        $media = Media::factory()->create();
        $user  = User::factory()->create();

        $this->service->borrow($media, $user);

        // Zurück auf verfügbar setzen, um gezielt die Doppelausleihe-Prüfung
        // zu treffen und nicht schon an der Statusprüfung zu scheitern.
        //
        // refresh() ist hier zwingend: borrow() hat den Status auf einer eigenen
        // Model-Instanz geändert, diese hier trägt noch „verfuegbar". Ohne
        // refresh() sähe Eloquent keine Änderung und setzte gar kein UPDATE ab.
        $media->refresh();
        $media->update(['status' => MediaStatus::Verfuegbar]);

        $this->expectExceptionMessage('Sie haben dieses Medium bereits ausgeliehen.');
        $this->service->borrow($media->fresh(), $user);
    }

    // ── Verlängerung ─────────────────────────────────────────────────────────

    public function test_ausleihe_kann_verlaengert_werden(): void
    {
        Setting::set('max_extensions', '1');
        $media = Media::factory()->create(['loan_days' => 14]);
        $user  = User::factory()->create();
        $loan  = $this->service->borrow($media, $user);

        $alt = $loan->due_at->copy();
        $neu = $this->service->extendLoan($loan, $user);

        $this->assertSame(14, (int) $alt->diffInDays($neu->due_at));
        $this->assertSame(1, $neu->extension_count);
    }

    public function test_verlaengerung_ist_begrenzt(): void
    {
        Setting::set('max_extensions', '1');
        $media = Media::factory()->create();
        $user  = User::factory()->create();
        $loan  = $this->service->borrow($media, $user);

        $this->service->extendLoan($loan, $user);

        $this->expectExceptionMessage('Maximale Anzahl Verlängerungen (1) erreicht.');
        $this->service->extendLoan($loan->fresh(), $user);
    }

    public function test_keine_verlaengerung_wenn_jemand_wartet(): void
    {
        $media   = Media::factory()->create();
        $leiher  = User::factory()->create();
        $loan    = $this->service->borrow($media, $leiher);

        $this->service->reserve($media->fresh(), User::factory()->create());

        $this->expectExceptionMessage('Verlängerung nicht möglich – jemand wartet auf dieses Medium.');
        $this->service->extendLoan($loan, $leiher);
    }

    // ── Rückgabe ─────────────────────────────────────────────────────────────

    public function test_rueckgabe_ohne_warteliste_macht_medium_verfuegbar(): void
    {
        $media = Media::factory()->create();
        $loan  = $this->service->borrow($media, User::factory()->create());

        $this->service->returnMedia($loan, rating: 1, comment: 'Hat gut funktioniert.');

        $loan->refresh();
        $this->assertNotNull($loan->returned_at);
        $this->assertSame(1, $loan->rating);
        $this->assertSame('Hat gut funktioniert.', $loan->rating_comment);
        $this->assertSame(MediaStatus::Verfuegbar, $media->fresh()->status);
    }

    public function test_rueckgabe_mit_warteliste_setzt_medium_auf_reserviert(): void
    {
        $media    = Media::factory()->create();
        $loan     = $this->service->borrow($media, User::factory()->create());
        $wartende = User::factory()->create();

        $reservierung = $this->service->reserve($media->fresh(), $wartende);
        $this->service->returnMedia($loan);

        $this->assertSame(ReservationStatus::Bereit, $reservierung->fresh()->status);
        $this->assertNotNull($reservierung->fresh()->notified_at);
        $this->assertSame(MediaStatus::Reserviert, $media->fresh()->status);

        // Seit dem Umbau auf Laravel-Notifications wird nicht mehr direkt
        // gemailt – die Benachrichtigung geht über beide Kanäle.
        Notification::assertSentTo($wartende, ReservationReadyNotification::class);
    }

    /**
     * Kern der Warteliste: Wer zuerst reserviert hat, wird zuerst bedient.
     */
    public function test_warteliste_wird_in_reihenfolge_bedient(): void
    {
        $media = Media::factory()->create();
        $loan  = $this->service->borrow($media, User::factory()->create());

        $erste  = User::factory()->create();
        $zweite = User::factory()->create();
        $dritte = User::factory()->create();

        $r1 = $this->service->reserve($media->fresh(), $erste);
        $r2 = $this->service->reserve($media->fresh(), $zweite);
        $r3 = $this->service->reserve($media->fresh(), $dritte);

        $this->assertSame([1, 2, 3], [$r1->position, $r2->position, $r3->position]);

        $this->service->returnMedia($loan);

        $this->assertSame(ReservationStatus::Bereit, $r1->fresh()->status, 'Nicht die erste Person bedient.');
        $this->assertSame(ReservationStatus::Wartend, $r2->fresh()->status);
        $this->assertSame(ReservationStatus::Wartend, $r3->fresh()->status);
    }

    // ── Reservierung ─────────────────────────────────────────────────────────

    public function test_verfuegbares_medium_kann_nicht_reserviert_werden(): void
    {
        $media = Media::factory()->create();

        $this->expectExceptionMessage('Dieses Medium ist verfügbar – bitte direkt ausleihen.');
        $this->service->reserve($media, User::factory()->create());
    }

    public function test_ausgemustertes_medium_kann_nicht_reserviert_werden(): void
    {
        $media = Media::factory()->ausgemustert()->create();

        $this->expectExceptionMessage('Eine Reservierung ist für dieses Medium nicht möglich.');
        $this->service->reserve($media, User::factory()->create());
    }

    public function test_doppelte_reservierung_wird_abgelehnt(): void
    {
        $media = Media::factory()->create();
        $this->service->borrow($media, User::factory()->create());

        $user = User::factory()->create();
        $this->service->reserve($media->fresh(), $user);

        $this->expectExceptionMessage('Sie haben dieses Medium bereits reserviert.');
        $this->service->reserve($media->fresh(), $user);
    }

    public function test_wer_das_medium_hat_kann_es_nicht_reservieren(): void
    {
        $media  = Media::factory()->create();
        $leiher = User::factory()->create();
        $this->service->borrow($media, $leiher);

        $this->expectExceptionMessage('Sie haben dieses Medium bereits ausgeliehen.');
        $this->service->reserve($media->fresh(), $leiher);
    }

    // ── Stornierung ──────────────────────────────────────────────────────────

    public function test_stornierung_schliesst_luecke_in_der_warteliste(): void
    {
        $media = Media::factory()->create();
        $this->service->borrow($media, User::factory()->create());

        $r1 = $this->service->reserve($media->fresh(), $u1 = User::factory()->create());
        $r2 = $this->service->reserve($media->fresh(), User::factory()->create());
        $r3 = $this->service->reserve($media->fresh(), User::factory()->create());

        $this->service->cancelReservation($r1, $u1);

        $this->assertSame(ReservationStatus::Storniert, $r1->fresh()->status);
        $this->assertSame(1, $r2->fresh()->position, 'Warteliste wurde nicht neu durchnummeriert.');
        $this->assertSame(2, $r3->fresh()->position);
    }

    public function test_fremde_reservierung_darf_nicht_storniert_werden(): void
    {
        $media = Media::factory()->create();
        $this->service->borrow($media, User::factory()->create());
        $reservierung = $this->service->reserve($media->fresh(), User::factory()->create());

        $this->expectExceptionMessage('Keine Berechtigung zum Stornieren dieser Reservierung.');
        $this->service->cancelReservation($reservierung, User::factory()->create());
    }

    public function test_kurator_darf_fremde_reservierung_stornieren(): void
    {
        $media = Media::factory()->create();
        $this->service->borrow($media, User::factory()->create());
        $reservierung = $this->service->reserve($media->fresh(), User::factory()->create());

        $this->service->cancelReservation($reservierung, User::factory()->kurator()->create());

        $this->assertSame(ReservationStatus::Storniert, $reservierung->fresh()->status);
    }

    // ── Abholung ─────────────────────────────────────────────────────────────

    public function test_bereite_reservierung_kann_abgeholt_werden(): void
    {
        $media = Media::factory()->create();
        $loan  = $this->service->borrow($media, User::factory()->create());
        $abholer = User::factory()->create();

        $reservierung = $this->service->reserve($media->fresh(), $abholer);
        $this->service->returnMedia($loan);

        $neueAusleihe = $this->service->pickupReservation($reservierung->fresh());

        $this->assertSame($abholer->id, $neueAusleihe->user_id);
        $this->assertSame(ReservationStatus::Abgeholt, $reservierung->fresh()->status);
        $this->assertSame(MediaStatus::Ausgeliehen, $media->fresh()->status);
    }

    public function test_wartende_reservierung_kann_nicht_abgeholt_werden(): void
    {
        $media = Media::factory()->create();
        $this->service->borrow($media, User::factory()->create());
        $reservierung = $this->service->reserve($media->fresh(), User::factory()->create());

        $this->expectExceptionMessage('Diese Reservierung ist noch nicht zur Abholung bereit.');
        $this->service->pickupReservation($reservierung);
    }

    // ── Barcode-Suche ────────────────────────────────────────────────────────

    public function test_medium_wird_ueber_internen_code_gefunden(): void
    {
        $media = Media::factory()->create(['internal_code' => 'LIB-100200']);

        $this->assertSame($media->id, $this->service->findMediaByCode('LIB-100200')?->id);
        $this->assertSame($media->id, $this->service->findMediaByCode('lib-100200')?->id, 'Kleinschreibung nicht behandelt.');
        $this->assertSame($media->id, $this->service->findMediaByCode(' LIB-100200 ')?->id, 'Leerzeichen nicht behandelt.');
    }

    public function test_medium_wird_ueber_isbn_gefunden(): void
    {
        $media = Media::factory()->create(['isbn' => '978-3-407-86543-2']);

        $this->assertSame($media->id, $this->service->findMediaByCode('9783407865432')?->id);
    }

    public function test_unbekannter_code_liefert_null(): void
    {
        Media::factory()->create(['internal_code' => 'LIB-999999']);

        $this->assertNull($this->service->findMediaByCode('LIB-000000'));
    }

    public function test_aktive_ausleihe_wird_ueber_code_gefunden(): void
    {
        $media = Media::factory()->create(['internal_code' => 'LIB-333444']);
        $loan  = $this->service->borrow($media, User::factory()->create());

        $this->assertSame($loan->id, $this->service->findActiveLoanByCode('LIB-333444')?->id);

        $this->service->returnMedia($loan);
        $this->assertNull(
            $this->service->findActiveLoanByCode('LIB-333444'),
            'Zurückgegebene Ausleihe wird noch als aktiv gemeldet.'
        );
    }
}
