# Administrationshandbuch

Betriebs- und Verwaltungsanleitung. Für die Bedienung durch Betreuer:innen
siehe `handbuch.md`.

---

## Überblick

Die Beispiele gehen von einer Installation nach `installation.md` aus:
Debian mit Nginx, PHP-FPM und MariaDB. Tragen Sie die Werte Ihrer eigenen
Installation ein – es lohnt sich, diese Tabelle beim Aufsetzen auszufüllen.

| | |
|---|---|
| Adresse | `APP_URL` aus der `.env` |
| Projektverzeichnis | z. B. `/var/www/leihregal` |
| Systembenutzer | z. B. `leihregal`, Gruppe `www-data` |
| PHP | mindestens 8.3. Auf Servern mit mehreren Versionen immer die Version ausdrücklich aufrufen, etwa `php8.3` |
| Datenbank | MariaDB, Schema aus `DB_DATABASE` (Tests: derselbe Name mit Endung `_test`) |
| Webserver | Nginx, Vhost unter `/etc/nginx/sites-available/` |
| FPM-Pool | `/etc/php/8.3/fpm/pool.d/` |
| Gesundheitsprüfung | `/healthz` – liefert JSON mit DB- und Speicherstatus |

---

## Webserver

Vorlagen: [nginx](../ops/nginx/leihregal.conf.example),
[Apache 2.4](../ops/apache/leihregal.conf.example).

Unter Apache genügt in aller Regel `AllowOverride All` – die mitgelieferte
`public/.htaccess` reicht alles Nichtvorhandene an `index.php` weiter und
trifft damit auch die dynamischen Pfade. Unter nginx gibt es kein Gegenstück
dazu, dort muss die Ausnahme ausdrücklich im Vhost stehen.

**Wenn die Oberfläche sichtbar ist, aber auf nichts reagiert**, ist fast immer
Livewires JavaScript nicht erreichbar. Prüfen:

```bash
PFAD=$(php artisan tinker --execute='echo app("livewire")->getUriPrefix();')
curl -o /dev/null -w '%{http_code}\n' "https://<ihre-domain>${PFAD}/livewire.min.js"
```

Kommt hier 404, fängt eine Webserver-Regel für `*.js` den Pfad ab. Livewire
erzeugt diese Datei zur Laufzeit, sie liegt nicht auf der Platte. Die Ausnahme
im Vhost muss `^~ /livewire` lauten – **ohne** abschliessenden Schrägstrich,
denn der Pfad heisst `/livewire-<hash>/` und nicht `/livewire/`.

Die Folgen eines 404 an dieser Stelle sind nicht offensichtlich: Formulare
fallen auf einen nativen Submit zurück, und ein `<form>` ohne `method` ist
GET – beim Anmeldeformular steht das Passwort danach in der Adresszeile.
Weder Feature- noch Browsertests bemerken das, weil erstere kein JavaScript
laden und letztere über `artisan serve` am Webserver vorbeilaufen.
`./deploy.sh` prüft es deshalb bei jedem Lauf mit.

---

## Rollen

| Rolle | Darf |
|---|---|
| **betreuer** | suchen, ausleihen, zurückgeben, reservieren, bewerten, Wünsche einreichen |
| **kurator** | zusätzlich Medien anlegen und bearbeiten, Whitelist, Wünsche verwalten, Anschaffungslisten, Etiketten |
| **admin** | zusätzlich Nutzerverwaltung, Einstellungen, Standorte, Protokoll |

Eine Selbstregistrierung gibt es nicht – Konten legt ausschliesslich die
Administration an. Ein Test in der Suite stellt sicher, dass keine
Registrierungsroute versehentlich wieder auftaucht.

---

## Nutzerverwaltung

**Admin → Nutzer**

- **Anlegen:** Name, E-Mail, Passwort, Rolle. Die Zugangsdaten müssen Sie der
  Person selbst mitteilen – es wird keine Einladung verschickt.
- **Deaktivieren:** Statt zu löschen. Deaktivierte Konten werden beim nächsten
  Seitenaufruf sofort abgemeldet. Ausleihhistorie bleibt erhalten.
- **Passwort zurücksetzen:** Beim Bearbeiten ein neues Passwort eintragen; das
  bisherige muss dafür nicht bekannt sein. Feld leer lassen heisst „unverändert".

Ihr eigenes Konto können Sie weder deaktivieren noch sich selbst die
Admin-Rolle entziehen.

---

## Einstellungen

**Admin → Einstellungen**

| Einstellung | Bedeutung |
|---|---|
| Standard-Leihdauer | Tage, sofern am Medium nichts Abweichendes hinterlegt ist |
| Erinnerungsabstand | Tage zwischen zwei Erinnerungen an eine **überfällige** Ausleihe. Die Vorab-Erinnerungen (3/1/0 Tage) sind davon unabhängig und fest |
| Verlängerungen | Wie oft eine Ausleihe verlängert werden darf |
| ISBNdb-API-Key | Optional, zusätzliche Quelle beim ISBN-Import |

Eine am einzelnen Medium hinterlegte Leihdauer hat immer Vorrang.

---

## Standorte

**Admin → Standorte** – Aufbewahrungsorte, die beim Medium hinterlegt werden
können. Praktisch, sobald der Bestand über mehrere Räume verteilt ist.

---

## Protokoll

**Admin → Protokoll** zeichnet Kurations- und Admin-Aktionen auf: wer wann was
angelegt, geändert oder gelöscht hat, samt Vorher-/Nachher-Werten.

- Erfasst werden Medien, Nutzer, Whitelist, Anschaffungen, Wünsche, Standorte
  und Einstellungen.
- Ausleihen und Reservierungen werden **nicht** erfasst – das ist normaler
  Betrieb und würde das Protokoll fluten.
- Passwörter und Sitzungs-Token erscheinen nie im Klartext; dass eine Änderung
  stattfand, ist sichtbar, der Wert nicht.
- Einträge sind unveränderlich und lassen sich nicht löschen.
- Wird ein Konto gelöscht, bleibt der Name im Eintrag lesbar.

Nur für Admins einsehbar, da auch Änderungen an Nutzerkonten enthalten sind.

---

## Benachrichtigungen

Jeder Hinweis geht über zwei Kanäle: per E-Mail wie bisher und zusätzlich in
das **Benachrichtigungs-Center** (Glocke oben rechts, `/benachrichtigungen`).
Nutzende sehen dort auch ältere Hinweise, können sie als gelesen markieren
oder entfernen.

Technisch sind es Laravel-Notifications; die `toMail()`-Methoden geben die
bereits vorhandenen Mailables zurück, damit Betreff und Vorlagen unverändert
bleiben. Tabelle: `notifications`.

| Anlass | Auslöser |
|---|---|
| Frist läuft ab (3 Tage, 1 Tag, heute) | `loans:remind-due-soon` |
| Frist überschritten | `loans:remind-overdue` |
| Reservierung abholbereit | Rückgabe durch die Vorgängerin |
| Warteliste aktualisiert | Rückgabe durch die Vorgängerin |
| Terminreservierung verfügbar | Rückgabe |
| Neues Medium zu abonniertem Thema | Anlegen eines Mediums |
| Wunsch-Status geändert / Wunsch erfüllt | Kuration bzw. Anlegen |

Themen-Abos verwalten Nutzende selbst unter **Mein Profil**.

Schlägt der Versand fehl, wird das protokolliert, bricht aber den
auslösenden Vorgang nicht ab – eine Rückgabe soll nicht daran scheitern, dass
der Mailserver klemmt.

---

## Etiketten

**Medien → einzelnes Medium → Barcode**, oder für den Gesamtbestand
**Kuration → Bestandsliste** bzw. `/barcode/alle`.

Ausgegeben wird ein PDF mit **Code128-Barcodes, 30 Etiketten je A4-Bogen**
(70 × 29,4 mm, passend zu handelsüblicher 3×10-Bogenware). Der Kamera-Scanner
liest weiterhin auch QR-Codes, damit früher gedruckte Etiketten gültig bleiben.

Ausgemusterte und verlorene Medien erscheinen nicht im Sammelbogen.

---

## Betrieb

### Datensicherung

Zwei Ebenen, Einzelheiten in `backup.md`:

1. **Eine Sicherung des ganzen Servers** auf ein fremdes Medium – etwa per
   `rsync` auf einen Speicherplatz beim Hoster. Nur sie deckt auch die
   Cover-Dateien unter `storage/` und die `.env` ab.
2. **`leihregal-db-backup.timer`** (täglich 03:30) – konsistenter Datenbank-Snapshot
   mit 14 Tagen Historie unter `/var/backups/leihregal/daily/`. Schlägt er fehl,
   geht eine E-Mail an `root`.

Prüfen:

```bash
systemctl list-timers leihregal-db-backup.timer
journalctl -u leihregal-db-backup.service -n 20
```

**Wiederherstellung:** siehe `backup.md`. Erst gegen `leihregal_test` prüfen,
dann einspielen.

### Geplante Aufgaben

Ausgelöst über `schedule:run` (minütlich per root-crontab):

| Zeit | Aufgabe |
|---|---|
| täglich 08:00 | Erinnerungen zu **überfälligen** Ausleihen |
| täglich 08:05 | Erinnerungen **vor** Fristende (3 Tage, 1 Tag, am Fälligkeitstag) |
| täglich 04:00 | fehlende Embeddings nachziehen |
| 1. Tag jedes Quartals, 06:00 | Quartalsbericht an Kuratoren und Admins |
| sonntags 02:00 | ähnliche Wünsche bündeln |

Anzeigen: `php artisan schedule:list`

### Deployment

```bash
cd /pfad/zum/projekt
./deploy.sh
```

Standortabhängige Werte – PHP-Aufruf, Dateibesitzer, FPM-Dienst – stehen in
`deploy.conf`; als Vorlage dient `deploy.conf.example`.

Das Skript prüft Vorbedingungen, lässt **zuerst die Tests laufen** und bricht
bei Fehlschlag ab, ohne etwas zu verändern. Danach sichert es die Datenbank,
schaltet in den Wartungsmodus, installiert Abhängigkeiten, baut die Assets,
migriert, erneuert die Caches, setzt Dateirechte, lädt FPM neu und prüft
abschliessend, ob die Seite antwortet. Bricht ein Schritt ab, wird der
Wartungsmodus automatisch wieder aufgehoben.

Optionen: `--skip-build`, `--no-tests`, `--prod-deps`.

> `--prod-deps` entfernt die dev-Abhängigkeiten. Danach sind auf diesem Server
> keine Tests mehr möglich, bis `composer install` ohne
> `--no-dev` gelaufen ist. Standardmässig bleiben sie deshalb installiert.

### Web-Push

Push läuft über ein VAPID-Schlüsselpaar in der `.env`
(`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`). Es wurde einmalig
erzeugt und darf **nicht gewechselt** werden – sonst werden alle bestehenden
Abos ungültig und jedes Gerät müsste sich neu anmelden.

Neues Paar erzeugen (nur bei Erstinstallation):

```bash
php artisan push:vapid-keys
```

Jedes Gerät meldet sich selbst unter **Mein Profil → Push auf dieses Gerät** an;
ein Konto kann mehrere Geräte haben. Abos, die der Browser verworfen hat,
werden beim nächsten Versand automatisch entfernt.

### Empfehlungen und Einsatz-Assistent

- **Persönliche Empfehlungen** auf dem Dashboard entstehen aus den Embeddings
  der eigenen Leihhistorie; Bewertungen gewichten mit. Ohne Historie werden die
  meistgenutzten Medien gezeigt. Fehlen Embeddings (siehe OpenAI-Guthaben),
  fällt die Empfehlung ebenfalls auf die Beliebtheitsliste zurück.
- **Einsatz-Assistent** auf der Mediendetailseite: Claude erhält das konkrete
  Medium und die Situationsbeschreibung. Auch hier greift der PII-Filter, dazu
  eine eigene Bremse von 10 Anfragen pro Minute und Konto.

### Quartalsbericht

```bash
php artisan reports:quarterly                      # letztes abgeschlossenes Quartal
php artisan reports:quarterly --quartal=2026-2     # bestimmtes Quartal
php artisan reports:quarterly --trocken            # nur Kennzahlen anzeigen
php artisan reports:quarterly --speichern=/tmp/q.pdf
```

### Protokolldateien

```bash
tail -50 storage/logs/laravel.log
tail -50 /var/log/nginx/<ihr-vhost>-error.log
```

### Tests

```bash
php artisan test          # Feature- und Unit-Tests
```

**Browsertests (E2E):** Laufen mit Chromium gegen einen eigenen Testserver.

```bash
# Terminal 1 – Testserver auf der Testdatenbank
./dusk-server.sh

# Terminal 2
php artisan dusk
```

> Die Einstellungen dafür stehen in `.env.dusk` und zeigen auf `leihregal_test`.
> `artisan dusk` tauscht die `.env` während des Laufs aus und spielt sie danach
> zurück. `tests/DuskTestCase.php` trägt denselben Datenbank-Schutzwall wie die
> übrige Suite – Dusk erbt nicht von `Tests\TestCase`.

Läuft ausschliesslich gegen `leihregal_test`. Ein Schutz in
`tests/TestCase.php` bricht ab, falls die Tests auf eine andere Datenbank
zeigen – die Testsuite leert die Zieldatenbank vollständig.

> `phpunit.xml` biegt zusätzlich `APP_CONFIG_CACHE` auf einen nicht
> existierenden Pfad um. Ohne das würde Laravel bei gecachter Konfiguration
> alle Testeinstellungen ignorieren und auf der Produktionsdatenbank landen.
> Beides nicht entfernen.

---

## Externe Dienste

| Dienst | Wofür | Schlüssel |
|---|---|---|
| Anthropic (Claude) | Medienbeschreibungen, Situations-Assistent, Kuration | `ANTHROPIC_API_KEY` |
| OpenAI | Embeddings für die semantische Suche | `OPENAI_API_KEY` |
| Google Books | Metadaten und Cover beim ISBN-Import | `GOOGLE_BOOKS_API_KEY` (optional) |

Beide KI-Aufrufe wiederholen sich bei Rate-Limits und Serverfehlern
automatisch mit wachsender Wartezeit.

**Wenn das OpenAI-Guthaben aufgebraucht ist**, erscheint im Protokoll:

```
production.ERROR: OpenAI-Guthaben aufgebraucht – es werden keine Embeddings mehr erzeugt.
```

Neu angelegte Medien haben dann kein Embedding und tauchen weder in der
semantischen Suche noch beim Assistenten auf. Nach dem Aufladen:

```bash
php artisan media:backfill-embeddings
```

Der nächtliche Lauf um 04:00 erledigt das ebenfalls von selbst.

Lücken prüfen:

```sql
SELECT COUNT(*) FROM media
WHERE status NOT IN ('ausgemustert','verloren')
  AND id NOT IN (SELECT media_id FROM media_embeddings);
```

---

## Störungssuche

| Beobachtung | Ursache und Abhilfe |
|---|---|
| Seite meldet 500 | `storage/logs/laravel.log` ansehen; oft Dateirechte nach manuellen Eingriffen als root – `chown -R leihregal:www-data .` |
| Änderungen wirken nicht | Caches erneuern: `php artisan optimize:clear`, danach `config:cache`, `route:cache`, `view:cache` |
| „Wartungsmodus" bleibt stehen | `php artisan up` |
| Keine E-Mails | `systemctl status postfix`, dann `mailq` |
| Assistent liefert keine Vorschläge | Embeddings prüfen (siehe oben) und Guthaben beider Anbieter kontrollieren |
| Scanner startet nicht | Nur über HTTPS möglich; Kameraberechtigung im Browser prüfen |
| Tests brechen mit „nicht freigegebene Datenbank" ab | Gewollt. `leihregal_test` muss existieren, `phpunit.xml` muss darauf zeigen |

---

## Bekannte Abweichungen und offene Punkte


- **Web-Push nicht auf einem echten Gerät geprüft.** Abo-Verwaltung und
  Nutzlast sind getestet, der Versand an die Push-Dienste der Browser nicht.
  Auf iOS funktioniert Push erst, wenn Leihregal über „Zum Home-Bildschirm"
  hinzugefügt wurde.
- **Kein Branding/Logo.** Bewusst offen – braucht eine gestalterische Vorgabe.
- **Kamera-Scan nicht automatisiert geprüft** – Browsertests decken die
  Hauptflows ab, eine echte Kamera lässt sich darin nicht ansteuern.
- **`@zxing/library` 0.23** liegt über dem npm-`latest`-Tag und verlangt
  Node ≥ 24 (installiert: 22). Der Build läuft, der Scan ist ungeprüft.
