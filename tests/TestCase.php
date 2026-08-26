<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use Concerns\PrueftTestDatenbank;

    /**
     * Schutzwall gegen Datenverlust.
     *
     * `RefreshDatabase` migriert die konfigurierte Datenbank bei jedem Testlauf
     * von Null neu – zeigt die Verbindung auf die Produktionsdatenbank, sind
     * alle Daten weg. Das ist kein hypothetisches Risiko: Steht in phpunit.xml
     * die Test-Verbindung nicht (mehr) drin, erbt der Testlauf stillschweigend
     * die Verbindung aus der .env.
     *
     * Die Prüfung hängt sich in setUpTraits() ein: Die Applikation ist da
     * bereits gebootet (Config lesbar), RefreshDatabase hat aber noch nichts
     * angefasst. Sie greift damit unabhängig davon, was in phpunit.xml oder
     * .env steht – die Config allein ist als Schutz zu leicht zu verlieren.
     */
    protected function setUpTraits()
    {
        $this->pruefeTestDatenbank('Tests');

        return parent::setUpTraits();
    }
}
