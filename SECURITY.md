# Sicherheit

## Ein Sicherheitsproblem melden

**Bitte kein öffentliches Issue anlegen.** Melden Sie die Lücke über
[GitHub Security Advisories](https://github.com/benhartwich/leihregal/security/advisories/new)
– das ist ein privater Kanal zwischen Ihnen und der Projektbetreuung.

Hilfreich sind: eine Beschreibung des Problems, die betroffene Version oder
der Commit, und wenn möglich ein Weg zum Nachstellen.

Sie bekommen innerhalb von sieben Tagen eine Rückmeldung. Bis eine Korrektur
verfügbar ist, bitten wir darum, die Lücke nicht zu veröffentlichen.

## Was diese Anwendung besonders schützenswert macht

Leihregal wird in Einrichtungen eingesetzt, die mit Kindern, Jugendlichen und
Familien in belastenden Lebenslagen arbeiten. Auch wenn keine Klientendaten
verwaltet werden, sind zwei Bereiche besonders heikel:

- **Der Situations-Chat.** Nutzende beschreiben dort reale Situationen. Ein
  PII-Filter (`App\Services\PiiFilterService`) entfernt personenbezogene
  Angaben, bevor der Text an das Sprachmodell geht. Lücken in diesem Filter
  sind Sicherheitsprobleme, keine bloßen Fehler.
- **Die `.env`.** Sie enthält API-Schlüssel und den Datenbankzugang. Sie darf
  weder im Repository landen noch über den Webserver erreichbar sein.

## Für Betreiber

- `APP_DEBUG=false` im Betrieb. Andernfalls zeigen Fehlerseiten Konfiguration
  und Umgebungsvariablen.
- Als Dokumentenwurzel ausschließlich `public/` einrichten – nie das
  Projektverzeichnis selbst.
- TLS erzwingen. Ohne HTTPS reisen Sitzungsschlüssel im Klartext.
- Datensicherung nach [`docs/backup.md`](docs/backup.md), und das
  Sicherungsziel ebenso schützen wie den Server: Dumps enthalten alles.
- Aktualisierungen einspielen. `composer audit` meldet bekannte Lücken in den
  Abhängigkeiten.

## Unterstützte Versionen

Sicherheitskorrekturen erscheinen für den `main`-Zweig. Ein Rückportieren in
ältere Stände findet nicht statt.
