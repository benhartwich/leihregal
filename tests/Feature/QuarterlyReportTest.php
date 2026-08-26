<?php

namespace Tests\Feature;

use App\Mail\QuarterlyReportMail;
use App\Models\Loan;
use App\Models\Media;
use App\Models\User;
use App\Models\Wish;
use App\Services\QuarterlyReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Automatisierter Quartalsbericht (Phase 8).
 */
class QuarterlyReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function quartal(int $jahr, int $q): array
    {
        $von = CarbonImmutable::create($jahr, ($q - 1) * 3 + 1, 1)->startOfQuarter();

        return [$von, $von->endOfQuarter()];
    }

    private function ausleiheAm(Media $media, User $user, CarbonImmutable $zeitpunkt, ?CarbonImmutable $rueckgabe = null): Loan
    {
        return Loan::create([
            'media_id'    => $media->id,
            'user_id'     => $user->id,
            'borrowed_at' => $zeitpunkt,
            'due_at'      => $zeitpunkt->addDays(14),
            'returned_at' => $rueckgabe,
        ]);
    }

    public function test_zaehlt_nur_ausleihen_im_zeitraum(): void
    {
        [$von, $bis] = $this->quartal(2026, 2);

        $media = Media::factory()->create();
        $user  = User::factory()->create();

        $this->ausleiheAm($media, $user, $von->addDays(5));
        $this->ausleiheAm($media, $user, $von->addDays(40));
        $this->ausleiheAm($media, $user, $von->subDays(10));   // Vorquartal
        $this->ausleiheAm($media, $user, $bis->addDays(10));   // Folgequartal

        $bericht = app(QuarterlyReportService::class)->erstellen($von, $bis);

        $this->assertSame(2, $bericht['ausleihen']['gesamt']);
    }

    public function test_beliebteste_medien_sind_nach_anzahl_sortiert(): void
    {
        [$von, $bis] = $this->quartal(2026, 2);

        $oft   = Media::factory()->create(['title' => 'Oft geliehen']);
        $selten = Media::factory()->create(['title' => 'Selten geliehen']);
        $user  = User::factory()->create();

        foreach (range(1, 3) as $i) {
            $this->ausleiheAm($oft, $user, $von->addDays($i));
        }
        $this->ausleiheAm($selten, $user, $von->addDays(9));

        $bericht = app(QuarterlyReportService::class)->erstellen($von, $bis);

        $this->assertSame('Oft geliehen', $bericht['beliebteste'][0]['title']);
        $this->assertSame(3, (int) $bericht['beliebteste'][0]['anzahl']);
    }

    public function test_ungenutzte_medien_werden_erkannt(): void
    {
        [$von, $bis] = $this->quartal(2026, 2);

        $genutzt   = Media::factory()->create(['title' => 'Wurde geliehen']);
        $ungenutzt = Media::factory()->create(['title' => 'Lag im Regal']);

        $this->ausleiheAm($genutzt, User::factory()->create(), $von->addDays(3));

        $bericht = app(QuarterlyReportService::class)->erstellen($von, $bis);
        $titel   = array_column($bericht['ungenutzt'], 'title');

        $this->assertContains('Lag im Regal', $titel);
        $this->assertNotContains('Wurde geliehen', $titel);
    }

    public function test_durchschnittliche_leihdauer_nutzt_nur_rueckgaben(): void
    {
        [$von, $bis] = $this->quartal(2026, 2);

        $media = Media::factory()->create();
        $user  = User::factory()->create();

        $this->ausleiheAm($media, $user, $von->addDays(1), $von->addDays(11));  // 10 Tage
        $this->ausleiheAm($media, $user, $von->addDays(20), $von->addDays(24)); // 4 Tage
        $this->ausleiheAm($media, $user, $von->addDays(30));                    // läuft noch

        $bericht = app(QuarterlyReportService::class)->erstellen($von, $bis);

        $this->assertSame(7.0, $bericht['ausleihen']['schnittTage']);
        $this->assertSame(2, $bericht['ausleihen']['rueckgaben']);
    }

    public function test_bericht_wird_als_pdf_erzeugt(): void
    {
        [$von] = $this->quartal(2026, 2);
        Media::factory()->create();

        $pfad = sys_get_temp_dir() . '/quartalsbericht-test.pdf';
        @unlink($pfad);

        $this->artisan('reports:quarterly', [
            '--quartal'   => '2026-2',
            '--speichern' => $pfad,
        ])->assertSuccessful();

        $this->assertFileExists($pfad);
        $this->assertStringStartsWith('%PDF', file_get_contents($pfad));

        @unlink($pfad);
    }

    public function test_bericht_geht_an_kuratoren_und_admins(): void
    {
        $kurator  = User::factory()->kurator()->create();
        $admin    = User::factory()->admin()->create();
        $betreuer = User::factory()->create();
        $inaktiv  = User::factory()->kurator()->deaktiviert()->create();

        $this->artisan('reports:quarterly', ['--quartal' => '2026-2'])->assertSuccessful();

        Mail::assertSent(QuarterlyReportMail::class, fn ($m) => $m->hasTo($kurator->email));
        Mail::assertSent(QuarterlyReportMail::class, fn ($m) => $m->hasTo($admin->email));
        Mail::assertNotSent(QuarterlyReportMail::class, fn ($m) => $m->hasTo($betreuer->email));
        Mail::assertNotSent(QuarterlyReportMail::class, fn ($m) => $m->hasTo($inaktiv->email));
    }

    public function test_ungueltiges_quartal_wird_abgelehnt(): void
    {
        $this->artisan('reports:quarterly', ['--quartal' => 'Unsinn'])->assertFailed();
        Mail::assertNothingSent();
    }

    public function test_trockenlauf_verschickt_nichts(): void
    {
        User::factory()->admin()->create();

        $this->artisan('reports:quarterly', ['--quartal' => '2026-2', '--trocken' => true])
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_ohne_empfaenger_bricht_es_nicht_ab(): void
    {
        // Nur Betreuer vorhanden – niemand, der den Bericht bekommen müsste.
        User::factory()->count(2)->create();

        $this->artisan('reports:quarterly', ['--quartal' => '2026-2'])->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_voriges_quartal_wird_ohne_angabe_verwendet(): void
    {
        $service  = app(QuarterlyReportService::class);
        $zeitraum = $service->vorigesQuartal(CarbonImmutable::create(2026, 7, 5));

        $this->assertSame('Q2 2026', $zeitraum['bezeichnung']);
        $this->assertSame('2026-04-01', $zeitraum['von']->toDateString());
        $this->assertSame('2026-06-30', $zeitraum['bis']->toDateString());
    }

    public function test_wunschkennzahlen_stimmen(): void
    {
        [$von, $bis] = $this->quartal(2026, 2);
        $user = User::factory()->create();

        // Zeitstempel per Query setzen: create() füllt created_at immer mit
        // now(), da die Spalte nicht in $fillable steht.
        $imZeitraum = Wish::create(['user_id' => $user->id, 'title' => 'A', 'status' => 'eingereicht']);
        $davor      = Wish::create(['user_id' => $user->id, 'title' => 'B', 'status' => 'eingereicht']);

        Wish::whereKey($imZeitraum->id)->update(['created_at' => $von->addDay(), 'updated_at' => $von->addDay()]);
        Wish::whereKey($davor->id)->update(['created_at' => $von->subDays(5), 'updated_at' => $von->subDays(5)]);

        $bericht = app(QuarterlyReportService::class)->erstellen($von, $bis);

        $this->assertSame(1, $bericht['wuensche']['neu'], 'Nur Wünsche aus dem Zeitraum zählen als neu.');
        $this->assertSame(2, $bericht['wuensche']['offen'], 'Offene Wünsche werden stichtagsbezogen gezählt.');
    }
}
