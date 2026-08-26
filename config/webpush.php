<?php

return [
    /*
     * VAPID identifiziert diesen Server gegenüber den Push-Diensten der
     * Browser-Hersteller. Das Schlüsselpaar wird einmalig erzeugt
     * (php8.3 artisan push:vapid-keys) und darf danach nicht mehr wechseln –
     * sonst werden alle bestehenden Abos ungültig.
     */
    'subject'     => env('VAPID_SUBJECT') ?: 'mailto:' . env('MAIL_FROM_ADDRESS', 'admin@example.org'),
    'public_key'  => env('VAPID_PUBLIC_KEY', ''),
    'private_key' => env('VAPID_PRIVATE_KEY', ''),

    /* Gültigkeitsdauer einer Push-Nachricht beim Dienst, in Sekunden. */
    'ttl' => 86400,
];
