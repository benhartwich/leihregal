<?php

namespace Tests\Concerns;

use RuntimeException;

/**
 * Prüft vor jedem Testlauf, dass nicht die Produktionsdatenbank getroffen wird.
 *
 * Zwei Bedingungen müssen erfüllt sein, und beide sind bewusst unabhängig
 * voneinander gewählt:
 *
 *   1. Der Datenbankname darf nicht der aus der .env sein. Dieser Wert wird
 *      direkt aus der Datei gelesen, nicht über config() – sonst prüfte man
 *      genau den Wert, der im Fehlerfall bereits falsch ist.
 *   2. Der Name muss der Konvention für Testdatenbanken folgen: auf `_test`
 *      enden oder eine SQLite-Datenbank im Arbeitsspeicher sein.
 *
 * Wer andere Namen braucht, trägt sie in phpunit.xml unter TEST_DB_ERLAUBT
 * kommagetrennt ein.
 */
trait PrueftTestDatenbank
{
    protected function pruefeTestDatenbank(string $art): void
    {
        $verbindung = config('database.default');
        $datenbank  = (string) config("database.connections.{$verbindung}.database");

        $produktion = $this->datenbankAusEnvDatei();
        $erlaubt    = array_filter(array_map(
            'trim',
            explode(',', (string) env('TEST_DB_ERLAUBT', ''))
        ));

        $istTestname = in_array($datenbank, $erlaubt, true)
            || $datenbank === ':memory:'
            || str_ends_with($datenbank, '_test');

        $istProduktion = $produktion !== null && $datenbank === $produktion;

        if ($istTestname && ! $istProduktion) {
            return;
        }

        $grund = $istProduktion
            ? 'Das ist die Datenbank aus der .env, also die des laufenden Betriebs.'
            : 'Der Name folgt nicht der Konvention (Endung `_test` oder `:memory:`).';

        throw new RuntimeException(<<<TEXT

            ABBRUCH: {$art} laufen gegen eine nicht freigegebene Datenbank.

              Verbindung:  {$verbindung}
              Datenbank:   {$datenbank}

            {$grund}

            Der Testlauf würde diese Datenbank vollständig leeren.

            Häufigste Ursache ist eine gecachte Konfiguration: Liegt
            bootstrap/cache/config.php, ignoriert Laravel alle <env>-Einträge
            aus phpunit.xml. Dagegen biegt phpunit.xml APP_CONFIG_CACHE auf
            einen nicht existierenden Pfad um – fehlt diese Zeile, greift der
            Schutz nicht mehr. Zur Kontrolle:

              php artisan config:clear

            Testdatenbank anlegen, falls nicht vorhanden:

              CREATE DATABASE {$produktion}_test;
              GRANT ALL ON {$produktion}_test.* TO 'benutzer'@'localhost';

            TEXT);
    }

    /**
     * Datenbankname aus der .env-Datei, ohne den Umweg über config().
     *
     * Während eines Dusk-Laufs ist `.env` nicht die Datei des laufenden
     * Betriebs: `artisan dusk` sichert sie nach `.env.backup` und schiebt
     * `.env.dusk` an ihre Stelle. Ohne diese Unterscheidung verglichen die
     * Browsertests die Testdatenbank mit sich selbst und brächen immer ab.
     */
    private function datenbankAusEnvDatei(): ?string
    {
        $pfad = is_readable(base_path('.env.backup'))
            ? base_path('.env.backup')
            : base_path('.env');

        if (! is_readable($pfad)) {
            return null;
        }

        foreach (file($pfad, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $zeile) {
            if (preg_match('/^\s*DB_DATABASE\s*=\s*"?([^"#\s]+)"?/', $zeile, $treffer)) {
                return $treffer[1];
            }
        }

        return null;
    }
}
