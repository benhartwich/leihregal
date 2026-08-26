<?php

namespace Database\Seeders;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Enums\UserRole;
use App\Models\Location;
use App\Models\Media;
use App\Models\MediaTag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Beispielbestand zum Ausprobieren.
 *
 * Eine frisch installierte Bibliothek ist leer, und an einer leeren Liste
 * lässt sich weder etwas vorführen noch beurteilen, ob die Einrichtung
 * passt. Dieser Seeder legt einen kleinen, fachlich plausiblen Bestand an:
 * Standorte, drei Konten für die drei Rollen und rund zwanzig Medien quer
 * durch alle Medienarten, mit Themen-Tags und Praxishinweisen.
 *
 * Aufruf:
 *
 *     php artisan db:seed --class=BeispieldatenSeeder
 *
 * Rückgängig machen: Medien in der Oberfläche löschen oder die Datenbank
 * frisch migrieren (`php artisan migrate:fresh`).
 *
 * Die Beispielkonten haben ein bekanntes Passwort. Deshalb legt der Seeder
 * sie nur an, wenn APP_ENV nicht `production` ist – für eine Vorführung auf
 * einem Produktivsystem lässt sich das mit SEED_DEMO_USERS=1 aufheben.
 * Die Konten heißen dann trotzdem sichtbar nach ihrer Rolle und sollten
 * hinterher gelöscht werden.
 */
class BeispieldatenSeeder extends Seeder
{
    private const PASSWORT = 'beispiel1234';

    public function run(): void
    {
        $this->standorte();
        $ersteller = $this->konten();
        $this->medien($ersteller);

        $this->command->newLine();
        $this->command->info('✓ Beispieldaten angelegt.');
        $this->command->newLine();
    }

    private function standorte(): void
    {
        $standorte = [
            ['name' => 'Regal A – Fachliteratur', 'place' => 'Büro',       'sort_order' => 1],
            ['name' => 'Regal B – Kinder & Jugend', 'place' => 'Büro',     'sort_order' => 2],
            ['name' => 'Materialschrank',         'place' => 'Gruppenraum', 'sort_order' => 3],
            ['name' => 'Handapparat',             'place' => 'unterwegs',   'sort_order' => 4],
        ];

        foreach ($standorte as $standort) {
            Location::firstOrCreate(
                ['name' => $standort['name'], 'place' => $standort['place']],
                ['sort_order' => $standort['sort_order']],
            );
        }

        $this->command->info('  Standorte: ' . count($standorte));
    }

    /**
     * Legt je ein Konto für Betreuung, Kuration und Administration an und
     * gibt das Kuratorenkonto zurück – ihm werden die Medien zugeschrieben.
     */
    private function konten(): ?User
    {
        $erlaubt = ! app()->isProduction() || env('SEED_DEMO_USERS') === '1';

        if (! $erlaubt) {
            $this->command->warn('  Konten: übersprungen (APP_ENV=production).');
            $this->command->line('    Für eine Vorführung: SEED_DEMO_USERS=1 setzen.');

            return User::where('role', UserRole::Kurator)->first()
                ?? User::where('role', UserRole::Admin)->first();
        }

        $konten = [
            ['name' => 'Beispiel Betreuung',      'email' => 'betreuung@example.org', 'role' => UserRole::Betreuer],
            ['name' => 'Beispiel Kuration',       'email' => 'kuration@example.org',  'role' => UserRole::Kurator],
            ['name' => 'Beispiel Administration', 'email' => 'admin@example.org',     'role' => UserRole::Admin],
        ];

        $angelegt = [];

        foreach ($konten as $konto) {
            $user = User::firstOrCreate(
                ['email' => $konto['email']],
                [
                    'name'     => $konto['name'],
                    'password' => Hash::make(self::PASSWORT),
                    'role'     => $konto['role'],
                    'active'   => true,
                ],
            );

            $angelegt[] = [$konto['email'], $konto['role']->label()];
        }

        $this->command->info('  Konten:');
        $this->command->table(['E-Mail', 'Rolle'], $angelegt);
        $this->command->line('  Passwort für alle drei: ' . self::PASSWORT);

        return User::where('email', 'kuration@example.org')->first();
    }

    private function medien(?User $ersteller): void
    {
        $angelegt = 0;

        foreach ($this->bestand() as $eintrag) {
            $tags = $eintrag['tags'];
            unset($eintrag['tags']);

            $medium = Media::firstOrCreate(
                ['title' => $eintrag['title'], 'author' => $eintrag['author'] ?? null],
                $eintrag + [
                    'status'        => MediaStatus::Verfuegbar,
                    'language'      => 'de',
                    'internal_code' => Media::generateInternalCode(),
                    'created_by'    => $ersteller?->id,
                ],
            );

            if ($medium->wasRecentlyCreated) {
                $angelegt++;

                foreach ($tags as $tag) {
                    MediaTag::firstOrCreate(['media_id' => $medium->id, 'tag' => $tag]);
                }
            }
        }

        $this->command->info("  Medien: {$angelegt} neu angelegt");

        if ($angelegt > 0) {
            $this->command->line('    Ohne OPENAI_API_KEY fehlen die Embeddings; die');
            $this->command->line('    Ähnlichkeitssuche bleibt dann leer. Nachziehen mit:');
            $this->command->line('      php artisan media:backfill-embeddings');
        }
    }

    /**
     * Beispielbestand. Bewusst gemischt: Fachliteratur für das Team,
     * Material für die direkte Arbeit, Bücher für unterschiedliche
     * Altersgruppen. Die Angaben sind Beispiele, keine Empfehlungen.
     *
     * @return list<array<string, mixed>>
     */
    private function bestand(): array
    {
        return [
            [
                'type'      => MediaType::Buch,
                'title'     => 'Gewaltfreie Kommunikation',
                'author'    => 'Marshall B. Rosenberg',
                'publisher' => 'Junfermann',
                'year'      => 2016,
                'summary'   => 'Grundlagenwerk zu einer Gesprächshaltung, die Beobachtung, Gefühl, Bedürfnis und Bitte trennt. Viele Beispieldialoge aus Alltagssituationen.',
                'target_group' => 'Fachkräfte',
                'age_recommendation' => 'Erwachsene',
                'practical_use' => 'Als gemeinsame Sprache im Team, etwa vor Konfliktgesprächen. Einzelne Kapitel eignen sich für eine Teamsitzung.',
                'tags'      => ['Kommunikation', 'Konflikt', 'Haltung', 'Fachliteratur'],
            ],
            [
                'type'      => MediaType::Buch,
                'title'     => 'Kinder verstehen',
                'author'    => 'Herbert Renz-Polster',
                'publisher' => 'Kösel',
                'year'      => 2019,
                'summary'   => 'Entwicklungspsychologie aus evolutionärer Sicht, allgemein verständlich geschrieben. Erklärt kindliches Verhalten aus seiner Funktion heraus.',
                'target_group' => 'Fachkräfte, Eltern',
                'age_recommendation' => 'Erwachsene',
                'practical_use' => 'Hilft bei Elterngesprächen, wenn Verhalten als „Problem" beschrieben wird, das entwicklungspsychologisch erwartbar ist.',
                'tags'      => ['Entwicklung', 'Bindung', 'Elternarbeit', 'Fachliteratur'],
            ],
            [
                'type'      => MediaType::Buch,
                'title'     => 'Trauma und die Folgen',
                'author'    => 'Michaela Huber',
                'publisher' => 'Junfermann',
                'year'      => 2020,
                'summary'   => 'Einführung in Traumafolgestörungen: was im Nervensystem geschieht, wie sich das im Verhalten zeigt und was Stabilisierung bedeutet.',
                'target_group' => 'Fachkräfte',
                'age_recommendation' => 'Erwachsene',
                'practical_use' => 'Nachschlagewerk bei Verhaltensweisen, die ohne Traumahintergrund unerklärlich wirken. Kapitel zur Stabilisierung als Einstieg.',
                'tags'      => ['Trauma', 'Psychoedukation', 'Stabilisierung', 'Fachliteratur'],
            ],
            [
                'type'      => MediaType::Buch,
                'title'     => 'Systemische Fragetechniken',
                'author'    => 'Andreas Patrzek',
                'publisher' => 'Springer Gabler',
                'year'      => 2021,
                'summary'   => 'Sammlung von Fragetypen mit Beispielen: zirkuläre, skalierende, hypothetische und lösungsorientierte Fragen.',
                'target_group' => 'Fachkräfte',
                'age_recommendation' => 'Erwachsene',
                'practical_use' => 'Vor Hilfeplangesprächen durchblättern. Gut geeignet für kollegiale Beratung im Team.',
                'tags'      => ['Systemisch', 'Gesprächsführung', 'Beratung', 'Fachliteratur'],
            ],
            [
                'type'      => MediaType::Gefuehlskarten,
                'title'     => 'Gefühlskarten für Kinder',
                'author'    => null,
                'publisher' => 'Beltz',
                'year'      => 2022,
                'summary'   => 'Kartenset mit gezeichneten Gesichtern und je einem Gefühlswort. Ohne Text auf der Rückseite, damit die Deutung beim Kind bleibt.',
                'target_group' => 'Kinder 4–10',
                'age_recommendation' => '4-10 Jahre',
                'practical_use' => 'Zum Einstieg in Einzelgespräche: „Such dir die Karte, die zu heute passt." Auch als Tagesabschluss in der Gruppe.',
                'tags'      => ['Emotionen', 'Gesprächseinstieg', 'Einzelarbeit', 'Kinder'],
            ],
            [
                'type'      => MediaType::Gefuehlskarten,
                'title'     => 'Ressourcenkarten',
                'author'    => null,
                'publisher' => 'Carl-Auer',
                'year'      => 2021,
                'summary'   => 'Bildkarten zu Stärken, Fähigkeiten und Unterstützungsquellen. Betont, was da ist, statt was fehlt.',
                'target_group' => 'Jugendliche, Erwachsene',
                'age_recommendation' => 'ab 12',
                'practical_use' => 'In der Hilfeplanung, wenn ein Gespräch sich um Defizite dreht. Drei Karten auswählen lassen und begründen.',
                'tags'      => ['Ressourcen', 'Systemisch', 'Hilfeplanung', 'Jugendliche'],
            ],
            [
                'type'      => MediaType::Spiel,
                'title'     => 'Das Spiel des Lebens – Gefühle',
                'author'    => null,
                'publisher' => 'Don Bosco',
                'year'      => 2020,
                'summary'   => 'Kooperatives Brettspiel, bei dem Situationskarten besprochen werden. Kein Gewinner, das Spiel endet gemeinsam.',
                'target_group' => 'Kinder 6–12',
                'age_recommendation' => '6-12 Jahre',
                'practical_use' => 'Für Gruppenstunden mit gemischtem Alter. Dauert etwa 40 Minuten, lässt sich jederzeit abbrechen.',
                'tags'      => ['Gruppenarbeit', 'Emotionen', 'Kooperation', 'Spiel'],
            ],
            [
                'type'      => MediaType::Spiel,
                'title'     => 'Tabu – Junior',
                'author'    => null,
                'publisher' => 'Hasbro',
                'year'      => 2018,
                'summary'   => 'Begriffe umschreiben, ohne bestimmte Wörter zu benutzen. In der Junior-Fassung mit Bildern statt Text auf den Karten.',
                'target_group' => 'Kinder ab 8',
                'age_recommendation' => 'ab 8',
                'practical_use' => 'Sprachförderung nebenbei, ohne dass es nach Übung aussieht. Funktioniert auch mit geringen Deutschkenntnissen.',
                'tags'      => ['Sprache', 'Gruppenarbeit', 'Spiel'],
            ],
            [
                'type'      => MediaType::Buch,
                'title'     => 'Der Grüffelo',
                'author'    => 'Julia Donaldson',
                'publisher' => 'Beltz & Gelberg',
                'year'      => 1999,
                'summary'   => 'Bilderbuch in Reimen: Eine Maus erfindet ein Ungeheuer und trifft es dann wirklich. Über Mut und Schlagfertigkeit.',
                'target_group' => 'Kinder 3–7',
                'age_recommendation' => '3-7 Jahre',
                'practical_use' => 'Vorlesen beim Zubettgehen. Der Reim trägt auch Kinder mit kurzer Aufmerksamkeitsspanne durch die Geschichte.',
                'tags'      => ['Bilderbuch', 'Vorlesen', 'Mut', 'Kinder'],
            ],
            [
                'type'      => MediaType::Buch,
                'title'     => 'Irgendwie Anders',
                'author'    => 'Kathryn Cave',
                'publisher' => 'Oetinger',
                'year'      => 1994,
                'summary'   => 'Bilderbuch über Ausgrenzung und Zugehörigkeit. Zwei Wesen, die nirgends dazugehören, finden einander.',
                'target_group' => 'Kinder 4–9',
                'age_recommendation' => '4-9 Jahre',
                'practical_use' => 'Bewährt als Einstieg, wenn in der Gruppe jemand ausgeschlossen wird. Anschließend nicht sofort auswerten.',
                'tags'      => ['Bilderbuch', 'Ausgrenzung', 'Zugehörigkeit', 'Gruppenarbeit'],
            ],
            [
                'type'      => MediaType::Buch,
                'title'     => 'Wenn meine Haare sprechen könnten',
                'author'    => 'Bea Davies',
                'publisher' => 'Carlsen',
                'year'      => 2022,
                'summary'   => 'Graphic Novel über Identität, Herkunft und Körperbild aus der Sicht einer Jugendlichen.',
                'target_group' => 'Jugendliche ab 12',
                'age_recommendation' => 'ab 12',
                'practical_use' => 'Für Jugendliche, die nicht gern lesen: Das Bildformat senkt die Hürde. Gesprächsanlass zu Zugehörigkeit.',
                'tags'      => ['Graphic Novel', 'Identität', 'Jugendliche', 'Diversität'],
            ],
            [
                'type'      => MediaType::Buch,
                'title'     => 'Tschick',
                'author'    => 'Wolfgang Herrndorf',
                'publisher' => 'Rowohlt',
                'year'      => 2010,
                'summary'   => 'Zwei Vierzehnjährige fahren mit einem geliehenen Auto durch Brandenburg. Roman über Freundschaft und Außenseitertum.',
                'target_group' => 'Jugendliche ab 13',
                'age_recommendation' => 'ab 13',
                'practical_use' => 'Kommt bei Jugendlichen an, die Schullektüre ablehnen. Auch als Hörbuch beliebt.',
                'tags'      => ['Roman', 'Freundschaft', 'Jugendliche', 'Außenseiter'],
            ],
            [
                'type'      => MediaType::Arbeitsmaterial,
                'title'     => 'Anti-Aggressivitäts-Training: Übungsmappe',
                'author'    => null,
                'publisher' => 'Lambertus',
                'year'      => 2019,
                'summary'   => 'Kopiervorlagen und Ablaufpläne für Einzel- und Gruppensitzungen zum Umgang mit Wut.',
                'target_group' => 'Fachkräfte, Jugendliche 12–18',
                'age_recommendation' => 'ab 12',
                'practical_use' => 'Einzelne Übungen lassen sich herauslösen; die Mappe muss nicht von vorn bis hinten durchgearbeitet werden.',
                'tags'      => ['Aggression', 'Gruppenarbeit', 'Arbeitsblätter', 'Methoden'],
            ],
            [
                'type'      => MediaType::Arbeitsmaterial,
                'title'     => 'Notfallkoffer: Skills bei Anspannung',
                'author'    => null,
                'publisher' => 'Balance Buch+Medien',
                'year'      => 2021,
                'summary'   => 'Materialbox mit Skills gegen hohe Anspannung: Igelball, Duftöl, Kältebeutel, Kärtchen mit Übungen.',
                'target_group' => 'Jugendliche',
                'age_recommendation' => 'ab 12',
                'practical_use' => 'Nicht in der Krise erklären, sondern vorher gemeinsam ausprobieren und einen persönlichen Koffer zusammenstellen.',
                'tags'      => ['Skills', 'Anspannung', 'Krise', 'Selbstregulation'],
            ],
            [
                'type'      => MediaType::Arbeitsmaterial,
                'title'     => 'Biografiearbeit mit Kindern: Lebensbuch',
                'author'    => null,
                'publisher' => 'Beltz Juventa',
                'year'      => 2020,
                'summary'   => 'Vorlagen für ein Lebensbuch: Zeitleisten, Genogramm-Blätter, Platz für Fotos und Erinnerungen.',
                'target_group' => 'Kinder 6–14',
                'age_recommendation' => '6-14 Jahre',
                'practical_use' => 'Über Monate hinweg gemeinsam füllen. Braucht Verlässlichkeit – nicht anfangen, wenn ein Wechsel absehbar ist.',
                'tags'      => ['Biografiearbeit', 'Einzelarbeit', 'Identität', 'Methoden'],
            ],
            [
                'type'      => MediaType::Zeitschrift,
                'title'     => 'Sozialmagazin – Ausgabe 3/2024',
                'author'    => null,
                'publisher' => 'Beltz Juventa',
                'year'      => 2024,
                'summary'   => 'Schwerpunkt Digitalisierung in der Jugendhilfe: Fachbeiträge und Praxisberichte.',
                'target_group' => 'Fachkräfte',
                'age_recommendation' => 'Erwachsene',
                'practical_use' => 'Einzelne Beiträge für die Teamsitzung. Liegt im Büro aus.',
                'tags'      => ['Fachzeitschrift', 'Digitalisierung', 'Jugendhilfe'],
            ],
            [
                'type'      => MediaType::Zeitschrift,
                'title'     => 'unsere jugend – Ausgabe 1/2025',
                'author'    => null,
                'publisher' => 'Ernst Reinhardt Verlag',
                'year'      => 2025,
                'summary'   => 'Fachzeitschrift für Studium und Praxis der Sozialpädagogik. Diese Ausgabe zu Partizipation in stationären Hilfen.',
                'target_group' => 'Fachkräfte',
                'age_recommendation' => 'Erwachsene',
                'practical_use' => 'Grundlage für die Überarbeitung des eigenen Beteiligungskonzepts.',
                'tags'      => ['Fachzeitschrift', 'Partizipation', 'Jugendhilfe'],
            ],
            [
                'type'      => MediaType::Digital,
                'title'     => 'Videoreihe: Deeskalation im Alltag',
                'author'    => null,
                'publisher' => 'Carl-Auer',
                'year'      => 2023,
                'summary'   => 'Acht kurze Filme mit nachgestellten Situationen und Kommentar. Zugang über einen Lizenzcode.',
                'target_group' => 'Fachkräfte',
                'age_recommendation' => 'Erwachsene',
                'practical_use' => 'Je ein Film pro Teamsitzung, danach zehn Minuten Austausch. Zugangsdaten bei der Kuration.',
                'tags'      => ['Deeskalation', 'Video', 'Teamfortbildung', 'Digital'],
            ],
            [
                'type'      => MediaType::Digital,
                'title'     => 'Hörbuch: Die unendliche Geschichte',
                'author'    => 'Michael Ende',
                'publisher' => 'Silberfisch',
                'year'      => 2015,
                'summary'   => 'Ungekürzte Lesung, rund 13 Stunden. Als Download für den Gruppen-Player.',
                'target_group' => 'Kinder ab 9',
                'age_recommendation' => 'ab 9',
                'practical_use' => 'Für lange Fahrten und für Kinder, die abends schwer zur Ruhe kommen.',
                'tags'      => ['Hörbuch', 'Vorlesen', 'Digital', 'Kinder'],
            ],
            [
                'type'      => MediaType::Buch,
                'title'     => 'Das kleine Ich bin ich',
                'author'    => 'Mira Lobe',
                'publisher' => 'Jungbrunnen',
                'year'      => 1972,
                'summary'   => 'Ein buntes Tier sucht, wozu es gehört, und findet am Ende sich selbst. Klassiker in Reimform.',
                'target_group' => 'Kinder 3–8',
                'age_recommendation' => '3-8 Jahre',
                'practical_use' => 'Beim Ankommen in einer neuen Gruppe. Der Schluss trägt: „Ich bin ich."',
                'tags'      => ['Bilderbuch', 'Identität', 'Selbstwert', 'Kinder'],
            ],
        ];
    }
}
