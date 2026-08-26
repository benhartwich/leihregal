# Änderungen

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung an [Semantic Versioning](https://semver.org/lang/de/).

## [Unveröffentlicht]

## [1.0.0] – 2026-08-26

Erste öffentliche Veröffentlichung. Die Anwendung war zuvor als
Einzelinstallation für einen sozialpädagogischen Betreuungsdienst in Betrieb
und wurde für die Veröffentlichung vom Namen und vom Standort dieser
Einrichtung gelöst.

### Bestand

- Medienerfassung per ISBN-Scan mit der Handykamera, Titeldaten und Cover über
  Google Books, mit manueller Erfassung als Rückfall
- Sechs Medienarten, Standorte, mehrere Exemplare je Titel
- QR-Etiketten einzeln und als Bogen mit 30 Stück auf A4
- Volltextsuche über `FULLTEXT`-Index, Filter nach Art, Status und Themen

### Ausleihe

- Ausleihe und Rückgabe per Barcode-Scan, Verlängerung, Bewertung
- Reservierung mit Warteliste in der Reihenfolge der Anmeldung
- Fristerinnerungen 3 Tage vorher, 1 Tag vorher, am Fälligkeitstag und danach
- Merkliste und Schadensmeldungen

### KI-gestützte Funktionen

Alle optional; ohne API-Schlüssel bleibt die übrige Anwendung voll nutzbar.

- Situations-Assistent mit vorgeschaltetem PII-Filter
- Ähnlichkeitssuche über Embeddings, gespeichert als `VECTOR(1536)`
- Persönliche Empfehlungen auf dem Dashboard
- Einsatz-Assistent für ein einzelnes Medium
- Bestandslücken-Analyse und Veraltungs-Check, beschränkt auf eine selbst
  gepflegte Whitelist von Verlagen und Autor:innen
- Automatische Bündelung ähnlicher Anschaffungswünsche

### Verwaltung

- Drei Rollen mit getrennten Rechten, keine Selbstregistrierung
- Änderungsprotokoll über `Auditable`, mit Schwärzung sensibler Felder
- Anschaffungs- und Bestandsliste als PDF und CSV, Quartalsbericht
- Benachrichtigungs-Center, E-Mail und optional Web-Push

### Betrieb

- Progressive Web App mit Service Worker und eigenen Icons
- Deploy-Skript, das ohne grüne Testsuite nichts verändert
- Datenbanksicherung mit Vollständigkeitsprüfung als systemd-Einheiten
  unter `ops/backup/`
- 172 Feature- und Unit-Tests, 11 Browsertests

### Whitelabel

- Kein Markenname im Code. Name, Untertitel, Beschreibung, Farben und der
  fachliche Zuschnitt der KI-Prompts kommen aus `config/branding.php`
  beziehungsweise der `.env`
- Web-App-Manifest wird zur Laufzeit aus der Konfiguration erzeugt
- Skript zum Erzeugen aller Icons aus den SVG-Vorlagen unter `ops/marke/`

### Sicherheit

- Schutzwall gegen Datenverlust in der Testsuite: Ein Testlauf gegen die
  Datenbank aus der `.env` oder gegen einen Namen ohne `_test`-Endung wird
  abgelehnt, bevor `RefreshDatabase` etwas anfassen kann
- Passwortwechsel verlangt das alte Passwort, ist ratenbegrenzt und beendet
  andere Sitzungen
- Ratenbegrenzung auf allen Routen, die ein Sprachmodell aufrufen

[Unveröffentlicht]: https://github.com/benhartwich/leihregal/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/benhartwich/leihregal/releases/tag/v1.0.0
