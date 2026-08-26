<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Erzeugt ein VAPID-Schlüsselpaar für Web-Push.
 */
class GenerateVapidKeys extends Command
{
    protected $signature   = 'push:vapid-keys';
    protected $description = 'Erzeugt ein VAPID-Schlüsselpaar für Web-Push';

    public function handle(): int
    {
        if (config('webpush.public_key')) {
            $this->warn('Es sind bereits VAPID-Schlüssel hinterlegt.');
            $this->line('Ein Wechsel macht ALLE bestehenden Push-Abos ungültig –');
            $this->line('die Geräte müssten sich erneut anmelden.');

            if (! $this->confirm('Trotzdem ein neues Paar erzeugen?', false)) {
                return self::SUCCESS;
            }
        }

        $schluessel = VAPID::createVapidKeys();

        $this->newLine();
        $this->info('Folgende Zeilen in die .env eintragen:');
        $this->newLine();
        $this->line('VAPID_SUBJECT=mailto:' . config('mail.from.address'));
        $this->line('VAPID_PUBLIC_KEY=' . $schluessel['publicKey']);
        $this->line('VAPID_PRIVATE_KEY=' . $schluessel['privateKey']);
        $this->newLine();
        $this->warn('Der private Schlüssel gehört nicht in die Versionsverwaltung.');

        return self::SUCCESS;
    }
}
