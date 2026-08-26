<?php

use App\Console\Commands\ClusterWishes;
use Illuminate\Support\Facades\Schedule;

// Daily at 08:00: send overdue loan reminders
Schedule::command('loans:remind-overdue')->dailyAt('08:00');

// Täglich 08:05: Erinnerung VOR Fristablauf (3 Tage, 1 Tag, am Fälligkeitstag).
// Fünf Minuten versetzt, damit beide Läufe nicht gleichzeitig Mails erzeugen.
Schedule::command('loans:remind-due-soon')->dailyAt('08:05');

// Weekly on Sunday at 02:00: cluster similar wishes
Schedule::command(ClusterWishes::class)->weeklyOn(0, '02:00');

// Täglich 04:00: fehlende Embeddings nachziehen.
// Medien ohne Embedding fehlen in der semantischen Suche und im
// Situations-Assistenten. Schlägt die Erzeugung beim Anlegen fehl (Rate-Limit,
// Netzwerk, fehlendes Guthaben), schliesst dieser Lauf die Lücke, sobald die
// Ursache behoben ist. Ohne fehlende Embeddings beendet er sich sofort.
Schedule::command('media:backfill-embeddings')
    ->dailyAt('04:00')
    ->withoutOverlapping();

// Quartalsbericht am ersten Tag jedes Quartals um 06:00 – berichtet wird über
// das gerade abgeschlossene Quartal (Phase 8).
Schedule::command('reports:quarterly')
    ->quarterlyOn(1, '06:00')
    ->withoutOverlapping();
