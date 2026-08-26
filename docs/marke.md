# Erscheinungsbild

Die Farben sind keine Neuerfindung: Sie wurden aus dem gewachsenen Bestand der
Oberfläche abgelesen und lediglich benannt, damit Logo, Icons, E-Mails und
PDFs nicht auseinanderlaufen.

Wenn Sie die Anwendung unter eigenem Namen betreiben wollen, lesen Sie zuerst
den Abschnitt [Eigener Name, eigene Farben](#eigener-name-eigene-farben).

---

## Eigener Name, eigene Farben

Die Anwendung ist so gebaut, dass eine Einrichtung sie ohne Eingriff in den
Code umbenennen kann. Drei Ebenen, von oben nach unten aufwendiger:

**1. Name und Texte – nur `.env`**

```dotenv
APP_NAME="Bücherei Musterstadt"
BRAND_UNTERTITEL="Medienausleihe"
BRAND_BESCHREIBUNG="Medien der Bücherei Musterstadt"
```

`APP_NAME` ist zugleich die Wortmarke. Sie erscheint im Logo, im Browser-Tab,
in E-Mails, in PDF-Berichten und auf dem Home-Bildschirm der Web-App. Das
Manifest wird zur Laufzeit erzeugt und zieht diese Werte automatisch mit.

**2. Farben – `.env` plus ein Handgriff im CSS**

```dotenv
BRAND_FARBE=#0F766E
BRAND_FARBE_HELL=#14B8A6
BRAND_FARBE_DUNKEL=#0F5F59
```

Damit ändern sich Logo-Verlauf, App-Icon-Hintergrund und die Themenfarbe der
Web-App. Die Oberfläche selbst nutzt an über 400 Stellen Tailwind-Klassen wie
`blue-600`; für einen vollständigen Wechsel passen Sie zusätzlich die Tokens
`--color-marke-*` in `resources/css/app.css` an und bauen mit `npm run build`
neu. Ohne diesen zweiten Schritt bleibt die Oberfläche blau.

**3. Eigenes Zeichen – die SVG-Vorlagen ersetzen**

Die vier Dateien unter `public/brand/` ersetzen, danach:

```bash
./ops/marke/icons-erzeugen.sh
```

Wer nur die Farben getauscht hat, ersetzt in den SVG-Vorlagen die beiden
Verlaufsfarben und lässt dasselbe Skript laufen. Die inline eingebettete
Bildmarke in `resources/views/components/brand-logo.blade.php` zieht ihre
Farben bereits aus der Konfiguration, ihre Form aber nicht – ein neues Motiv
gehört dort ebenfalls hinein.

Die Bildmarke unten beschreibt die mitgelieferte Vorgabe.

---

## Bildmarke

**Motiv:** Buchrücken im Regal, der rechte herausgezogen – das Medium, das
gerade jemand braucht. Genau darum geht die Anwendung: aus dem Bestand das
Passende herausziehen.

Rein geometrisch, keine Feinheiten, keine Schrift im Zeichen. Das ist Absicht:
Die Marke muss auch als 16-Pixel-Favicon lesbar bleiben.

| Datei | Verwendung |
|---|---|
| `public/brand/leihregal-mark.svg` | Vollversion (vier Rücken). Vorlage für App-Icons, ab ca. 48 px |
| `public/brand/leihregal-mark-kompakt.svg` | Drei breitere Rücken für kleine Größen. Vorlage für das Favicon |
| `public/brand/leihregal-mark-maskable.svg` | Android-Startbildschirm, Marke auf 66 % mit gefülltem Rand |
| `public/brand/leihregal-vorschau.svg` | Vorlage für das Vorschaubild geteilter Links |

**Warum zwei Varianten:** Unter 32 Pixel verschmelzen bei vier Rücken die
Zwischenräume zu einem grauen Block. Die kompakte Fassung hat drei deutlich
breitere Formen und lässt den Regalboden weg – eine 26 Pixel hohe Linie wäre
bei 16 Pixel Kantenlänge weniger als ein Bildpunkt.

### Abgeleitete Dateien

Alle aus den SVG-Vorlagen erzeugt, nicht von Hand gezeichnet:

```bash
./ops/marke/icons-erzeugen.sh          # braucht librsvg2-bin und imagemagick
```

Das Skript erzeugt `icon-192.png`, `icon-512.png`, `icon-maskable-512.png`,
`og-bild.png`, `favicon.svg`, `favicon.ico` und `apple-touch-icon.png`.

> `rsvg-convert` (Paket `librsvg2-bin`) statt ImageMagick allein: Der interne
> SVG-Renderer von ImageMagick löst weder Farbverläufe noch `transform`
> korrekt auf – die Marke kam schwarz und verschoben heraus.

> Das Apple-Icon bekommt bewusst **keinen** Alphakanal: iOS legt sonst Schwarz
> hinter transparente Bereiche. Die abgerundeten Ecken setzt das System selbst.

> `icon-maskable-512.png` ist als `purpose: maskable` eingetragen, die übrigen
> als `purpose: any`. Vorher stand bei beiden `any maskable` – Android hätte
> die Ecken der randlosen Marke abgeschnitten.

---

## Logo in der Anwendung

Bildmarke plus Wortmarke, als Blade-Komponente:

```blade
<x-brand-logo />                        {{-- Navigation --}}
<x-brand-logo groesse="gross" />        {{-- Anmeldeseite --}}
<x-brand-logo :nurMarke="true" />       {{-- nur das Zeichen --}}
```

Die Bildmarke steht dort inline im Markup statt als `<img>`: keine zusätzliche
Anfrage, verlustfreie Skalierung.

---

## Farben

**Marke** – benannt in `resources/css/app.css` unter `@theme`:

| Token | Wert | Tailwind | Verwendung |
|---|---|---|---|
| `--color-marke` | `#2563EB` | `blue-600` | Grundton, Schaltflächen, `theme_color` |
| `--color-marke-hell` | `#3B82F6` | `blue-500` | Verlauf hell, Hover |
| `--color-marke-dunkel` | `#1D4ED8` | `blue-700` | Verlauf dunkel, aktive Zustände |
| `--color-marke-zart` | `#EFF6FF` | `blue-50` | Hinterlegungen |

**Semantische Akzente** – unverändert in Gebrauch, bewusst nicht vereinheitlicht:

| Farbe | Bedeutung |
|---|---|
| Violett | KI-Funktionen: Situations-Assistent, Einsatz-Assistent |
| Smaragd | Kuration |
| Bernstein | Achtung, Fristen, Schadensmeldung |
| Grün | Erfolg, abholbereit, verfügbar |
| Rot | Fehler, überfällig, Zähler ungelesener Hinweise |
| Grau | Struktur, Fließtext, Ruhezustände |

Diese Zuordnung war schon vor der Markenarbeit da und trägt Bedeutung – wer
sie ändert, ändert die Bedienlogik mit.

---

## Schrift

**Figtree**, im Projekt unter `public/fonts/` (Variable Font, Schnitte 400–800,
Untermengen `latin` und `latin-ext`).

Eingebunden per `@font-face` in `resources/css/app.css`, **nicht** über Google
Fonts. Bei einer sozialen Einrichtung ist das kein Detail: Eine Einbindung
über den CDN überträgt bei jedem Seitenaufruf die IP-Adresse der Nutzenden an
einen Dritten. Die Schriftdateien liegen deshalb im Repository unter
`public/fonts/`.

Schrift aktualisieren:

```bash
curl -s -A 'Mozilla/5.0' 'https://fonts.googleapis.com/css2?family=Figtree:wght@400..800&display=swap'
# die beiden woff2-URLs (latin, latin-ext) daraus nach public/fonts/ laden
```

---

## Wo die Marke sonst auftaucht

| Ort | Umsetzung |
|---|---|
| Navigation, Anmeldeseite | `<x-brand-logo>` |
| Browser-Tab | `favicon.svg` (modern), `favicon.ico` (Rückfall) |
| Startbildschirm | Manifest unter `/manifest.webmanifest` (zur Laufzeit aus der Konfiguration erzeugt, siehe `routes/web.php`), App-Icons, `apple-touch-icon.png` |
| Benachrichtigungs-Mails | `resources/views/mail/_marke.blade.php` – PNG plus Text-Wortmarke, damit der Absender auch bei blockierten Bildern erkennbar bleibt |
| Quartalsbericht (PDF) | Bildmarke als Daten-URI im Kopf |
| Geteilte Links | `og-bild.png` samt Open-Graph-Angaben in beiden Layouts |

---

## Was bewusst nicht gemacht wurde

- **Keine Umstellung der bestehenden Farbklassen.** Die Oberfläche nutzt an
  über 400 Stellen `blue-500`/`blue-600`. Ein Austausch gegen Marken-Tokens
  wäre ein reines Umbenennen mit hohem Risiko und ohne sichtbaren Gewinn.
- **Keine Wortmarke als Pfad-SVG.** Der Schriftzug bleibt echter Text – gut
  für Vorlesewerkzeuge, Suche und Übersetzung.
- **Kein Hell-/Dunkel-Umschalter.** Die Anwendung ist durchgehend hell
  angelegt; ein zweites Farbschema wäre ein eigenes Vorhaben.
