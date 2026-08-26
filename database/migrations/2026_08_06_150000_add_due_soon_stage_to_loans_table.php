<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merkt sich, welche Vorab-Erinnerung zuletzt verschickt wurde (Spec 4.6).
 *
 * Ohne diese Spalte liefe bei jedem Scheduler-Durchlauf erneut eine Mail
 * hinaus, solange die Frist in Reichweite ist. Gespeichert wird die zuletzt
 * bediente Stufe in verbleibenden Tagen (3, 1 oder 0); verschickt wird nur,
 * wenn die aktuelle Stufe kleiner ist als die zuletzt gemerkte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedTinyInteger('due_soon_stage')->nullable()->after('last_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('due_soon_stage');
        });
    }
};
