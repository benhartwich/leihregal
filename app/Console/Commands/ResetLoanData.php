<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetLoanData extends Command
{
    protected $signature   = 'db:reset-loans {--force : Skip confirmation prompt}';
    protected $description = 'Löscht alle Ausleihen, Reservierungen, Wünsche und Bewertungen. Media-Katalog und Nutzer bleiben erhalten.';

    public function handle(): int
    {
        $this->warn('Diese Aktion löscht unwiderruflich:');
        $this->line('  • Alle Ausleihen (loans)');
        $this->line('  • Alle Reservierungen (reservations)');
        $this->line('  • Alle Schadensmeldungen (damage_reports)');
        $this->line('  • Alle Wünsche (wishes + acquisition_suggestions)');
        $this->line('  • Alle Bewertungen und Kommentare (media_reviews)');
        $this->line('  • Alle Lesezeichen (media_bookmarks)');
        $this->line('  • Media-Status wird auf "verfügbar" zurückgesetzt');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Wirklich fortfahren?')) {
            $this->info('Abgebrochen.');
            return self::SUCCESS;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $counts = [
            'Ausleihen'              => DB::table('loans')->count(),
            'Reservierungen'         => DB::table('reservations')->count(),
            'Schadensmeldungen'      => DB::table('damage_reports')->count(),
            'Wünsche'                => DB::table('wishes')->count(),
            'Anschaffungsvorschläge' => DB::table('acquisition_suggestions')->count(),
            'Bewertungen'            => DB::table('media_reviews')->count(),
            'Lesezeichen'            => DB::table('media_bookmarks')->count(),
        ];

        DB::table('loans')->truncate();
        DB::table('reservations')->truncate();
        DB::table('damage_reports')->truncate();
        DB::table('wishes')->truncate();
        DB::table('acquisition_suggestions')->truncate();
        DB::table('media_reviews')->truncate();
        DB::table('media_bookmarks')->truncate();

        // Reset media status: ausgeliehen / reserviert / in_aufbereitung → verfuegbar
        $mediaReset = DB::table('media')
            ->whereIn('status', ['ausgeliehen', 'reserviert', 'in_aufbereitung'])
            ->update(['status' => 'verfuegbar']);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->info('Fertig. Folgendes wurde gelöscht:');
        foreach ($counts as $label => $count) {
            $this->line("  • {$count}× {$label}");
        }
        $this->line("  • {$mediaReset}× Media-Status zurückgesetzt");

        return self::SUCCESS;
    }
}
