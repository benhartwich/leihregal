<?php

namespace Tests\Unit;

use App\Services\PiiFilterService;
use PHPUnit\Framework\TestCase;

/**
 * Der PII-Filter ist die letzte Instanz vor dem Sprachmodell (Spec 5).
 * Was er durchlässt, verlässt den Server.
 */
class PiiFilterServiceTest extends TestCase
{
    private PiiFilterService $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new PiiFilterService();
    }

    public function test_telefonnummern_werden_ersetzt(): void
    {
        foreach ([
            '0664 1234567',
            '+43 664 1234567',
            '0043 664 1234567',
            '01/234 56 78',
            '0664-123-4567',
        ] as $nummer) {
            $ergebnis = $this->filter->filter("Erreichbar unter {$nummer} bitte melden.");

            $this->assertStringNotContainsString(
                $nummer,
                $ergebnis['text'],
                "Telefonnummer '{$nummer}' blieb im Text stehen."
            );
            $this->assertStringContainsString('[TELEFON]', $ergebnis['text']);
            $this->assertContains('Telefonnummer', $ergebnis['types']);
        }
    }

    public function test_email_adressen_werden_ersetzt(): void
    {
        $ergebnis = $this->filter->filter('Schreiben Sie an maria.huber@example.com zurück.');

        $this->assertStringNotContainsString('maria.huber@example.com', $ergebnis['text']);
        $this->assertStringContainsString('[EMAIL]', $ergebnis['text']);
        $this->assertContains('E-Mail', $ergebnis['types']);
    }

    public function test_geburtsdaten_werden_ersetzt(): void
    {
        foreach (['12.03.2011', '1.7.1998', '12/03/2011', '12-03-2011'] as $datum) {
            $ergebnis = $this->filter->filter("Geboren am {$datum}.");

            $this->assertStringNotContainsString($datum, $ergebnis['text'], "Datum '{$datum}' blieb stehen.");
            $this->assertStringContainsString('[DATUM]', $ergebnis['text']);
        }
    }

    public function test_altersangaben_werden_ersetzt(): void
    {
        foreach (['14 Jahre alt', '8 Jahr alt', '16-jährig', '12 jähriger'] as $angabe) {
            $ergebnis = $this->filter->filter("Das Gegenüber ist {$angabe} und zurückgezogen.");

            $this->assertStringContainsString('[ALTER]', $ergebnis['text'], "'{$angabe}' nicht erkannt.");
            $this->assertContains('Alter', $ergebnis['types']);
        }
    }

    public function test_name_nach_einleitewort_wird_ersetzt(): void
    {
        $ergebnis = $this->filter->filter('Der Klient heißt Michael und zieht sich zurück.');

        $this->assertStringNotContainsString('Michael', $ergebnis['text']);
        $this->assertStringContainsString('heißt [NAME]', $ergebnis['text']);
        $this->assertContains('Name', $ergebnis['types']);
    }

    /**
     * Genau dieses Muster liess den Filter früher mit einem Fatal Error
     * abstürzen („Only variables should be passed by reference") – und damit
     * den gesamten KI-Assistenten. Die Formulierung ist im Arbeitsalltag
     * völlig üblich.
     */
    public function test_name_vor_verb_wird_ersetzt_ohne_absturz(): void
    {
        foreach ([
            'Markus hat gestern die Gruppe verlassen.',
            'Sabine ist sehr zurückgezogen.',
            'Tobias war lange nicht da.',
            'Jonas wurde wütend.',
            'Lena geht nicht mehr zur Schule.',
            'Elias sagte nichts dazu.',
        ] as $satz) {
            $ergebnis = $this->filter->filter($satz);

            $this->assertStringContainsString('[NAME]', $ergebnis['text'], "Kein Name erkannt in: {$satz}");
            $this->assertContains('Name', $ergebnis['types']);
        }
    }

    public function test_verb_bleibt_nach_namensersetzung_erhalten(): void
    {
        $ergebnis = $this->filter->filter('Markus hat gestern geweint.');

        $this->assertSame('[NAME] hat gestern geweint.', $ergebnis['text']);
    }

    /**
     * Ohne Ausschlussliste würde aus „Das Kind ist wütend" ein
     * „[NAME] ist wütend" – die Anfrage verlöre ihren Sinn.
     */
    public function test_haeufige_begriffe_werden_nicht_als_name_geschwaerzt(): void
    {
        foreach ([
            'Das Kind ist wütend und wirft Gegenstände.',
            'Die Mutter hat sich gemeldet.',
            'Der Jugendliche war sehr still.',
            'Die Gruppe ist unruhig.',
            'Das Verhalten war auffällig.',
        ] as $satz) {
            $ergebnis = $this->filter->filter($satz);

            $this->assertStringNotContainsString(
                '[NAME]',
                $ergebnis['text'],
                "Fälschlich als Name geschwärzt: {$satz}"
            );
        }
    }

    public function test_unauffaelliger_text_bleibt_unveraendert(): void
    {
        $text     = 'Ich suche Material zum Thema Wut für die Arbeit in der Kleingruppe.';
        $ergebnis = $this->filter->filter($text);

        $this->assertSame($text, $ergebnis['text']);
        $this->assertFalse($ergebnis['redacted']);
        $this->assertSame([], $ergebnis['types']);
    }

    public function test_mehrere_arten_gleichzeitig(): void
    {
        $ergebnis = $this->filter->filter(
            'Der Klient heißt Michael, geboren am 12.03.2011, erreichbar unter 0664 1234567 '
            . 'oder michael@example.com. Er ist 14 Jahre alt.'
        );

        foreach (['Michael', '12.03.2011', '0664 1234567', 'michael@example.com'] as $geheim) {
            $this->assertStringNotContainsString($geheim, $ergebnis['text']);
        }

        foreach (['Name', 'Datum', 'Telefonnummer', 'E-Mail', 'Alter'] as $typ) {
            $this->assertContains($typ, $ergebnis['types'], "Typ '{$typ}' nicht gemeldet.");
        }

        $this->assertTrue($ergebnis['redacted']);
    }

    public function test_typen_sind_eine_liste_ohne_luecken(): void
    {
        $ergebnis = $this->filter->filter('Mail an a@b.de und c@d.de bitte.');

        // array_unique erhält Schlüssel – ohne array_values würde daraus im
        // JSON ein Objekt statt einer Liste.
        $this->assertSame(['E-Mail'], $ergebnis['types']);
        $this->assertSame([0], array_keys($ergebnis['types']));
    }

    public function test_clean_liefert_nur_den_text(): void
    {
        $this->assertSame(
            'Kontakt: [EMAIL]',
            $this->filter->clean('Kontakt: max@example.com')
        );
    }

    public function test_leerer_text_wird_verarbeitet(): void
    {
        $ergebnis = $this->filter->filter('');

        $this->assertSame('', $ergebnis['text']);
        $this->assertFalse($ergebnis['redacted']);
    }
}
