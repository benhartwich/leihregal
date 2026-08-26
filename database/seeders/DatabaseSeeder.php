<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Grundausstattung für eine frische Installation.
 *
 * Legt ein Administrationskonto mit Zufallspasswort an und füllt die
 * Whitelist mit Verlagen, die im sozialpädagogischen Feld verbreitet sind.
 * Beides ist mehrfach ausführbar: Bestehendes wird nicht überschrieben.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            WhitelistSeeder::class,
        ]);
    }
}
