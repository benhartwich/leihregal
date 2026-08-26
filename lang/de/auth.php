<?php

/*
 * Anmeldungs-Meldungen auf Deutsch.
 *
 * Ohne diese Datei gibt trans('auth.failed') den Schlüssel selbst zurück –
 * Nutzenden stand bei falschem Passwort wörtlich „auth.failed" auf dem
 * Bildschirm. Aufgefallen im Browsertest.
 */
return [
    'failed'   => 'Diese Zugangsdaten stimmen nicht mit unseren Aufzeichnungen überein.',
    'password' => 'Das angegebene Passwort ist nicht korrekt.',
    'throttle' => 'Zu viele Anmeldeversuche. Bitte versuchen Sie es in :seconds Sekunden erneut.',
];
