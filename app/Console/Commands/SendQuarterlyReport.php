<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Mail\QuarterlyReportMail;
use App\Models\User;
use App\Services\QuarterlyReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Verschickt den Quartalsbericht an Kuratorium und Administration (Phase 8).
 */
class SendQuarterlyReport extends Command
{
    protected $signature = 'reports:quarterly
                            {--quartal= : Berichtsquartal als JJJJ-Q, z. B. 2026-2. Ohne Angabe das zuletzt abgeschlossene.}
                            {--speichern= : Statt zu versenden das PDF unter diesem Pfad ablegen}
                            {--trocken : Nur die Kennzahlen ausgeben, nichts verschicken}';

    protected $description = 'Erstellt den Quartalsbericht und verschickt ihn an Kuratoren und Admins';

    public function handle(QuarterlyReportService $service): int
    {
        [$von, $bis] = $this->zeitraumBestimmen($service);

        if ($von === null) {
            $this->error('Ungültiges Quartal. Erwartet wird JJJJ-Q, z. B. 2026-2.');
            return self::FAILURE;
        }

        $bericht = $service->erstellen($von, $bis);

        $this->info("Bericht {$bericht['bezeichnung']} ({$von->format('d.m.Y')} – {$bis->format('d.m.Y')})");
        $this->line("  Ausleihen:        {$bericht['ausleihen']['gesamt']}");
        $this->line("  Aktive Personen:  {$bericht['ausleihen']['nutzer']}");
        $this->line("  Neu im Bestand:   {$bericht['bestand']['neu']}");
        $this->line("  Neue Wünsche:     {$bericht['wuensche']['neu']}");

        if ($this->option('trocken')) {
            return self::SUCCESS;
        }

        $pdf = Pdf::loadView('pdf.quarterly-report', ['bericht' => $bericht])
            ->setPaper('a4')
            ->output();

        if ($pfad = $this->option('speichern')) {
            file_put_contents($pfad, $pdf);
            $this->info("PDF abgelegt: {$pfad}");
            return self::SUCCESS;
        }

        $empfaenger = User::where('active', true)
            ->whereIn('role', [UserRole::Kurator->value, UserRole::Admin->value])
            ->get();

        if ($empfaenger->isEmpty()) {
            $this->warn('Keine aktiven Kuratoren oder Admins – nichts verschickt.');
            return self::SUCCESS;
        }

        $verschickt = 0;

        foreach ($empfaenger as $person) {
            try {
                Mail::to($person->email)->send(new QuarterlyReportMail($bericht, $pdf));
                $verschickt++;
            } catch (\Throwable $e) {
                $this->warn("Versand an {$person->email} fehlgeschlagen: {$e->getMessage()}");
            }
        }

        $this->info("Bericht an {$verschickt} von {$empfaenger->count()} Empfänger(n) verschickt.");

        return $verschickt === $empfaenger->count() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function zeitraumBestimmen(QuarterlyReportService $service): array
    {
        $angabe = $this->option('quartal');

        if (! $angabe) {
            // Ohne Angabe wird über das zuletzt abgeschlossene Quartal
            // berichtet – der Lauf startet ja am ersten Tag des neuen.
            $zeitraum = $service->vorigesQuartal();

            return [$zeitraum['von'], $zeitraum['bis']];
        }

        if (! preg_match('/^(\d{4})-([1-4])$/', $angabe, $treffer)) {
            return [null, null];
        }

        $von = CarbonImmutable::create((int) $treffer[1], ((int) $treffer[2] - 1) * 3 + 1, 1)
            ->startOfQuarter();

        return [$von, $von->endOfQuarter()];
    }
}
