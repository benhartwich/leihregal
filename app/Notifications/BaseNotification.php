<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Basis für alle Benachrichtigungen der Anwendung.
 *
 * Jede Benachrichtigung geht per E-Mail wie bisher und zusätzlich in die
 * Datenbank für das Benachrichtigungs-Center. Hat das Konto mindestens ein
 * Push-Abo, kommt Web-Push als dritter Kanal dazu (Phase 8).
 *
 * Die `toMail()`-Methoden der Unterklassen geben die bereits vorhandenen
 * Mailables zurück. Dadurch bleiben Betreff und Text unverändert – der Umbau
 * fügt nur Kanäle hinzu, statt den Mailversand neu zu schreiben.
 */
abstract class BaseNotification extends Notification
{
    public function via(object $notifiable): array
    {
        $kanaele = ['mail', 'database'];

        // Nur senden, wenn tatsächlich ein Gerät angemeldet ist – sonst
        // liefe für jede Benachrichtigung ein Push-Versand ins Leere.
        if (method_exists($notifiable, 'pushSubscriptions')
            && $notifiable->pushSubscriptions()->exists()) {
            $kanaele[] = 'webpush';
        }

        return $kanaele;
    }

    /**
     * Inhalt für das Benachrichtigungs-Center.
     *
     * @return array{titel: string, text: string, url: string, symbol: string}
     */
    abstract public function toDatabase(object $notifiable): array;

    /**
     * Nutzlast für Web-Push.
     *
     * Leitet sich aus `toDatabase()` ab: Was im Center steht, ist auch das,
     * was auf dem Sperrbildschirm sinnvoll ist. Unterklassen können das
     * überschreiben, müssen aber nicht.
     */
    public function toWebPush(object $notifiable): array
    {
        $daten = $this->toDatabase($notifiable);

        return [
            'title' => $daten['titel'] ?? config('app.name'),
            'body'  => $daten['text'] ?? '',
            'url'   => $daten['url'] ?? url('/dashboard'),
            'icon'  => '/icon-192.png',
            'badge' => '/icon-192.png',
            'tag'   => $daten['symbol'] ?? 'allgemein',
        ];
    }
}
