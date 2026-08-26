<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push-Abonnements je Gerät (Phase 8).
 *
 * Ein Konto kann mehrere Einträge haben – Handy, Tablet, Arbeitsplatzrechner
 * sind je ein eigenes Abo mit eigenem Endpunkt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Der Endpunkt ist die Adresse beim Push-Dienst des Browsers.
            // Er kann sehr lang werden, deshalb Text statt String – und der
            // Eindeutigkeitsindex greift nur auf die ersten Zeichen.
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique();

            $table->string('public_key');
            $table->string('auth_token');
            $table->string('geraet')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
