<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WhitelistEntry;
use Illuminate\Database\Seeder;

class WhitelistSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (! $admin) {
            $this->command->warn('Kein Admin-User gefunden – Whitelist-Seeder übersprungen.');
            return;
        }

        // Ausgangspunkt, kein Dogma: verbreitete Fachverlage im
        // sozialpädagogischen Feld. Die Liste wird in der Anwendung unter
        // Kuration → Whitelist gepflegt und kann komplett ersetzt werden.
        $verlage = [
            'Beltz Juventa',
            'Ernst Reinhardt Verlag',
            'Lambertus',
            'Carl-Auer',
            'Klett-Cotta',
            'Kösel',
            'Vandenhoeck & Ruprecht',
            'Balance Buch+Medien',
            'Herder',
            'Juventa',
        ];

        foreach ($verlage as $name) {
            WhitelistEntry::firstOrCreate(
                ['type' => 'verlag', 'name' => $name],
                ['added_by' => $admin->id]
            );
        }

        $this->command->info('Whitelist mit ' . count($verlage) . ' Verlagen angelegt.');
    }
}
