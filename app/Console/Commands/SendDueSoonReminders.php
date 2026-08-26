<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Notifications\LoanDueSoonNotification;
use Illuminate\Console\Command;

/**
 * Erinnert VOR Ablauf der Leihfrist (Spec 4.6).
 *
 * Ergänzt `loans:remind-overdue`, das erst nach Fristablauf greift.
 */
class SendDueSoonReminders extends Command
{
    /** Verbleibende Tage, an denen erinnert wird – absteigend. */
    private const STUFEN = [3, 1, 0];

    protected $signature   = 'loans:remind-due-soon';
    protected $description = 'Erinnert an Ausleihen, deren Frist in 3 Tagen, 1 Tag oder heute abläuft';

    public function handle(): int
    {
        $ausleihen = Loan::with(['media', 'user'])
            ->whereNull('returned_at')
            ->whereDate('due_at', '>=', today())
            ->whereDate('due_at', '<=', today()->addDays(max(self::STUFEN)))
            ->get();

        $verschickt = 0;

        foreach ($ausleihen as $ausleihe) {
            if (! $ausleihe->user) {
                continue;
            }

            $verbleibend = (int) today()->diffInDays($ausleihe->due_at->copy()->startOfDay(), absolute: false);

            // Nächstliegende Stufe bestimmen, die bereits erreicht ist.
            $stufe = null;
            foreach (self::STUFEN as $kandidat) {
                if ($verbleibend <= $kandidat) {
                    $stufe = $kandidat;
                }
            }

            if ($stufe === null) {
                continue;
            }

            // Bereits für diese oder eine engere Stufe erinnert? Dann nichts tun.
            // Ohne diese Prüfung ginge bei jedem Lauf erneut eine Mail hinaus.
            if ($ausleihe->due_soon_stage !== null && $ausleihe->due_soon_stage <= $stufe) {
                continue;
            }

            try {
                $ausleihe->user->notify(new LoanDueSoonNotification($ausleihe, max(0, $verbleibend)));
                $ausleihe->update(['due_soon_stage' => $stufe]);
                $verschickt++;
            } catch (\Throwable $e) {
                $this->warn("Benachrichtigung an {$ausleihe->user->email} fehlgeschlagen: {$e->getMessage()}");
            }
        }

        $this->info("Vorab-Erinnerungen verschickt: {$verschickt}");

        return self::SUCCESS;
    }
}
