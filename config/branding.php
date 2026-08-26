<?php

/*
|--------------------------------------------------------------------------
| Erscheinungsbild und fachlicher Zuschnitt
|--------------------------------------------------------------------------
|
| Alles, was eine Einrichtung an dieser Anwendung zu ihrem Eigenen macht,
| steht hier – und nur hier. Der Anwendungsname selbst kommt aus
| config('app.name') (also APP_NAME in der .env).
|
| Die Werte unter "kontext" landen wörtlich in den Prompts an das
| Sprachmodell. Sie beschreiben, für wen kuratiert wird. Wer die Anwendung
| in einem anderen Feld einsetzt – Schule, Jugendzentrum, Beratungsstelle –
| passt sie an und bekommt sofort passendere Empfehlungen. Die Vorgaben
| beschreiben eine sozialpädagogische Wohngruppe.
|
*/

/*
 * Farbwert aus der Umgebung lesen.
 *
 * In einer .env leitet `#` einen Kommentar ein: `BRAND_FARBE=#0F766E` kommt
 * als leerer Wert an. Statt die Anwendung dann farblos zu rendern, greift
 * hier die Vorgabe – und ein Wert ohne Rautenzeichen wird ergänzt. Damit ist
 * sowohl `BRAND_FARBE="#0F766E"` als auch `BRAND_FARBE=0F766E` richtig.
 *
 * Bewusst eine lokale Funktion und kein Eintrag im zurückgegebenen Array:
 * Closures im Ergebnis würden `php artisan config:cache` unmöglich machen.
 */
$farbe = static function (?string $wert, string $vorgabe): string {
    $wert = trim((string) $wert);

    return $wert === '' ? $vorgabe : '#' . ltrim($wert, '#');
};

return [

    /*
    | Untertitel neben der Wortmarke: im Browser-Tab, in der Web-App und
    | in den Kopfzeilen von E-Mails und PDF-Berichten.
    */
    'untertitel' => env('BRAND_UNTERTITEL', 'Medienbibliothek'),

    /*
    | Ein Satz zur Anwendung, für die Anmeldeseite und die Web-App.
    */
    'beschreibung' => env(
        'BRAND_BESCHREIBUNG',
        'Medienbibliothek für sozialpädagogische Einrichtungen',
    ),

    /*
    | Fachlicher Zuschnitt. Diese Formulierungen gehen in die KI-Prompts ein.
    | Sie sind bewusst als Textbausteine gehalten und keine Schlüsselwörter:
    | Formulieren Sie so, wie Sie es einer neuen Kollegin erklären würden.
    */
    'kontext' => [

        // Für welche Einrichtung wird kuratiert?
        // Steht im Satz: "Du analysierst ein Medium für eine …"
        'einrichtung' => env(
            'BRAND_EINRICHTUNG',
            'sozialpädagogische Wohngruppe mit Kindern und Jugendlichen',
        ),

        // Wer nutzt die Bibliothek fachlich?
        // Steht im Satz: "Du bist ein Medienberater für …"
        'fachkraefte' => env('BRAND_FACHKRAEFTE', 'sozialpädagogische Fachkräfte'),

        // Wie werden die Nutzenden in Texten angesprochen?
        // Steht im Satz: "Kurze Zusammenfassung (für …)"
        'zielgruppe' => env('BRAND_ZIELGRUPPE', 'Betreuer:innen'),

        // In welchem Arbeitsfeld werden die Medien eingesetzt?
        // Steht im Satz: "Praktische Einsatzmöglichkeiten in der …"
        'arbeitsfeld' => env('BRAND_ARBEITSFELD', 'sozialpädagogischen Arbeit'),
    ],

    /*
    | Markenfarben. Der Grundton färbt Logo, App-Icon, E-Mail- und
    | PDF-Kopfzeilen sowie die Themenfarbe der Web-App. Wer ihn ändert,
    | passt zusätzlich die Tokens --color-marke-* in resources/css/app.css
    | an und baut das Frontend neu (siehe docs/marke.md).
    */
    'farben' => [
        'marke'        => $farbe(env('BRAND_FARBE'), '#2563EB'),
        'marke_hell'   => $farbe(env('BRAND_FARBE_HELL'), '#3B82F6'),
        'marke_dunkel' => $farbe(env('BRAND_FARBE_DUNKEL'), '#1D4ED8'),
        'hintergrund'  => $farbe(env('BRAND_FARBE_HINTERGRUND'), '#F9FAFB'),
    ],
];
