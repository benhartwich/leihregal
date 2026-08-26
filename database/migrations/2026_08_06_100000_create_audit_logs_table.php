<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // nullOnDelete statt cascade: Ein Protokoll, das beim Löschen des
            // Nutzers mitverschwindet, ist als Protokoll wertlos.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Name und Rolle werden im Klartext mitgeschrieben. Bewusst
            // denormalisiert, damit der Eintrag lesbar bleibt, wenn das Konto
            // später gelöscht oder umbenannt wird.
            $table->string('user_name')->nullable();
            $table->string('user_role', 20)->nullable();

            $table->string('action', 40);
            $table->string('entity', 60);

            // String statt Integer: `settings` hat einen Textschlüssel als
            // Primärschlüssel, andere Tabellen numerische IDs.
            $table->string('entity_id', 100)->nullable();

            // Titel bzw. Bezeichnung zum Zeitpunkt der Aktion – ebenfalls
            // denormalisiert, damit „Medium X ausgemustert" nachvollziehbar
            // bleibt, auch wenn der Datensatz später verschwindet.
            $table->string('entity_label')->nullable();

            $table->json('diff')->nullable();

            // Nur created_at: Protokolleinträge werden nie geändert.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity', 'entity_id']);
            $table->index('created_at');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
