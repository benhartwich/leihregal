# Mitwirken

Danke für Ihr Interesse. Dieses Projekt ist aus einem konkreten Bedarf
entstanden – einem Betreuungsdienst, dessen Materialbestand niemand mehr
überblickte. Beiträge, die aus einem ähnlich konkreten Bedarf kommen, sind
besonders willkommen.

## Wobei Hilfe am meisten nützt

- **Erfahrungsberichte aus dem Einsatz.** Was fehlt im Alltag? Was ist im Weg?
  Das ist wertvoller als Code.
- **Fehlerberichte** mit einem Weg zum Nachstellen.
- **Übersetzungen.** Die Anwendung ist derzeit einsprachig deutsch.
- **Barrierefreiheit.** Getestet wurde bislang nur oberflächlich.

## Entwicklungsumgebung

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Eine Datenbank für die Entwicklung und **eine getrennte für die Tests**
anlegen. Der Name der Testdatenbank muss auf `_test` enden – die Testsuite
weigert sich sonst zu starten:

```sql
CREATE DATABASE leihregal;
CREATE DATABASE leihregal_test;
```

Dann:

```bash
php artisan migrate
php artisan db:seed
php artisan db:seed --class=BeispieldatenSeeder   # Beispielbestand
npm run dev
```

## Vor einem Pull Request

```bash
php artisan test        # muss grün sein
vendor/bin/pint         # Formatierung nach Laravel-Konvention
```

Neue Funktionen brauchen Tests. Bei Fehlerkorrekturen ist ein Test, der den
Fehler vorher zeigt, die beste Beschreibung des Problems.

## Konventionen im Code

Ein paar Dinge weichen von dem ab, was in Laravel-Projekten üblich ist. Das ist
gewollt:

- **Deutsche Bezeichner** in neuerem Code – Variablen, Methoden, Kommentare.
  Die Anwendung wird auf Deutsch bedient und auf Deutsch dokumentiert; ein
  Sprachwechsel an der Codegrenze kostet mehr, als er einbringt. Älterer Code
  ist teils englisch, das wird nicht flächendeckend umgestellt.
- **Kommentare erklären das Warum**, nicht das Was. Besonders dort, wo eine
  naheliegende Lösung verworfen wurde.
- **Kein Markenname im Code.** Alles, was den Namen der Anwendung braucht,
  liest `config('app.name')`; alles Weitere steht in `config/branding.php`.
  Ein hart verdrahtetes „Leihregal" ist ein Fehler.
- **Neue Kurations- oder Verwaltungsmodelle** bekommen das Trait
  `App\Models\Concerns\Auditable`, sonst fehlen sie im Änderungsprotokoll.

## Den Datenbank-Schutzwall nicht entfernen

`tests/Concerns/PrueftTestDatenbank.php` prüft vor jedem Testlauf, dass nicht
die Datenbank des laufenden Betriebs getroffen wird. `RefreshDatabase` leert
die Datenbank, gegen die es läuft. Wenn diese Prüfung im Weg ist, zeigt sie
auf ein falsch konfiguriertes Testsetup – nicht auf ein Problem in der Prüfung.

## Lizenz

Mit dem Einreichen eines Beitrags stimmen Sie zu, dass er unter der
[MIT-Lizenz](LICENSE) veröffentlicht wird.
