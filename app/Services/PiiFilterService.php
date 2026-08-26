<?php

namespace App\Services;

/**
 * Entfernt personenbezogene Daten aus Freitext, bevor er an ein Sprachmodell
 * geht (Spec 5).
 *
 * Bewusst regexbasiert: Die Spec sieht für saubere Eigennamen-Erkennung einen
 * späteren NER-Dienst vor. Die Namensheuristik arbeitet deshalb absichtlich
 * grosszügig – lieber ein Begriff zu viel geschwärzt als ein Klientenname zu
 * wenig. Die Oberfläche weist zusätzlich darauf hin, keine echten
 * Klientendaten einzugeben.
 */
class PiiFilterService
{
    /**
     * Grossgeschriebene Wörter, die in diesem Arbeitsfeld häufig vorkommen und
     * keine Eigennamen sind. Ohne diese Liste würde aus „Das Kind ist wütend"
     * ein „[NAME] ist wütend" – die Anfrage verlöre ihren Sinn.
     */
    private const KEINE_NAMEN = [
        // Artikel, Pronomen, Füllwörter
        'der', 'die', 'das', 'dem', 'den', 'dieser', 'diese', 'dieses',
        'er', 'sie', 'es', 'ich', 'wir', 'ihr', 'man', 'jemand', 'niemand',
        'alle', 'alles', 'nichts', 'etwas', 'jeder', 'jede', 'jedes',
        'heute', 'gestern', 'morgen', 'dann', 'danach', 'seitdem', 'oft',
        'manchmal', 'immer', 'nie', 'dort', 'hier', 'dabei', 'deshalb',
        'wenn', 'weil', 'aber', 'auch', 'noch', 'schon', 'nur', 'sehr',
        // Rollen und Personenbezeichnungen
        'klient', 'klientin', 'kind', 'kinder', 'junge', 'mädchen', 'bub',
        'mutter', 'vater', 'eltern', 'familie', 'geschwister', 'bruder',
        'schwester', 'oma', 'opa', 'grossmutter', 'grossvater',
        'betreuer', 'betreuerin', 'bewohner', 'bewohnerin', 'jugendliche',
        'jugendlicher', 'person', 'gruppe', 'team', 'kollege', 'kollegin',
        'lehrer', 'lehrerin', 'schule', 'kindergarten', 'wohngruppe',
        'gegenüber', 'teilnehmer', 'teilnehmerin', 'schüler', 'schülerin',
        // Häufige Substantive im Fallkontext
        'situation', 'problem', 'verhalten', 'gespräch', 'termin', 'thema',
        'wut', 'angst', 'trauer', 'streit', 'krise', 'konflikt', 'gefühl',
        'medium', 'buch', 'material', 'karten',
    ];

    /**
     * Ersetzt erkannte personenbezogene Daten durch neutrale Platzhalter.
     *
     * @return array{text: string, redacted: bool, types: string[]}
     */
    public function filter(string $input): array
    {
        $text  = $input;
        $types = [];

        $merke = function (string $typ) use (&$types): void {
            $types[] = $typ;
        };

        // Telefonnummern (AT/DE/CH und international)
        $text = preg_replace_callback(
            '/(?:\+\d{1,3}|00\d{1,3}|0)\s?[\d\s\-\/]{7,14}\d/u',
            function () use ($merke) { $merke('Telefonnummer'); return '[TELEFON]'; },
            $text
        );

        // E-Mail-Adressen
        $text = preg_replace_callback(
            '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/u',
            function () use ($merke) { $merke('E-Mail'); return '[EMAIL]'; },
            $text
        );

        // Datumsangaben – tt.mm.jjjj / tt/mm/jjjj / tt-mm-jjjj
        $text = preg_replace_callback(
            '/\b(0?[1-9]|[12]\d|3[01])[.\-\/](0?[1-9]|1[0-2])[.\-\/](19|20)\d{2}\b/',
            function () use ($merke) { $merke('Datum'); return '[DATUM]'; },
            $text
        );

        // Konkrete Altersangaben („12 Jahre alt", „16-jährig", „12 jähriger")
        // Die Endung ist zweistufig: „jähr" + optional „ig" + optionale
        // Beugung. „jähriger" scheiterte sonst, weil nach „ig" noch „er" folgt.
        $text = preg_replace_callback(
            '/\b\d{1,2}[- ]?[Jj]ähr(?:ig)?(?:e|en|em|er|es)?\b|\b\d{1,2} [Jj]ahre? alt\b/u',
            function () use ($merke) { $merke('Alter'); return '[ALTER]'; },
            $text
        );

        // Namensheuristik 1: Einleitewort + Name → Einleitewort bleibt stehen.
        //   „Der Klient heisst Michael" → „Der Klient heisst [NAME]"
        $text = preg_replace_callback(
            '/\b(heißt|heisst|namens|genannt|Name:)\s+([A-ZÄÖÜ][a-zäöüß]{2,})\b/u',
            function ($treffer) use ($merke) {
                $merke('Name');
                return $treffer[1] . ' [NAME]';
            },
            $text
        );

        // Namensheuristik 2: Name + Verb → Verb bleibt stehen.
        //   „Markus hat geweint" → „[NAME] hat geweint"
        //
        // Das Verb wird mitgefangen und wieder eingesetzt. Früher stand hier
        // `end(explode(' ', $m[0]))` – der Aufruf übergab ein Funktionsergebnis
        // an einen Referenzparameter und liess den Filter mit einem Fatal Error
        // abstürzen, sobald dieses Muster griff.
        $text = preg_replace_callback(
            '/\b([A-ZÄÖÜ][a-zäöüß]{2,})\s+(hat|ist|war|wurde|geht|lebt|kam|sagt|sagte|braucht)\b/u',
            function ($treffer) use ($merke) {
                if (in_array(mb_strtolower($treffer[1]), self::KEINE_NAMEN, true)) {
                    return $treffer[0];
                }

                $merke('Name');
                return '[NAME] ' . $treffer[2];
            },
            $text
        );

        $types = array_values(array_unique($types));

        return [
            'text'     => $text,
            'redacted' => $types !== [],
            'types'    => $types,
        ];
    }

    /**
     * Nur den gefilterten Text zurückgeben.
     */
    public function clean(string $input): string
    {
        return $this->filter($input)['text'];
    }
}
