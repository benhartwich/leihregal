<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravels Standard-Tabelle für Benachrichtigungen (Spec 4.6, Phase 6).
 *
 * Grundlage für das Benachrichtigungs-Center in der Oberfläche. Bisher liefen
 * Hinweise ausschliesslich per E-Mail – wer sie übersah oder löschte, hatte
 * keine Möglichkeit mehr, sie nachzulesen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Deckt die Hauptabfrage ab: ungelesene Hinweise eines Nutzers,
            // neueste zuerst.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
