#!/bin/bash
#
# Deployment auf einem einzelnen Server.
#
# Aufruf als root im Projektverzeichnis:
#   ./deploy.sh                regulärer Deploy
#   ./deploy.sh --skip-build   Assets nicht neu bauen (nur Backend-Änderungen)
#   ./deploy.sh --no-tests     Testlauf überspringen (nicht empfohlen)
#   ./deploy.sh --prod-deps    vendor/ ohne dev-Abhängigkeiten aufbauen
#
# Zu --prod-deps: Laufen Entwicklung und Betrieb auf derselben Maschine,
# braucht die Testsuite die dev-Abhängigkeiten (PHPUnit). Deshalb bleiben sie
# standardmässig installiert. Wer ein schlankes vendor/ will, nutzt
# --prod-deps – dann sind bis zum nächsten vollen `composer install` aber
# keine Tests mehr möglich.
#
# Alle standortabhängigen Werte stehen in deploy.conf. Diese Datei ist von der
# Versionsverwaltung ausgenommen; als Vorlage dient deploy.conf.example.
#
set -euo pipefail

PROJEKT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ── Vorgaben, in deploy.conf überschreibbar ─────────────────────────────────
BESITZER="www-data:www-data"
# Composer ausdrücklich über $PHP starten: Sein Shebang ist /usr/bin/env php.
# Zeigt `php` auf eine ältere Version als das Projekt verlangt, muss $PHP
# ausdrücklich gesetzt werden – etwa auf php8.3.
PHP="php"
COMPOSER="/usr/local/bin/composer"
FPM_DIENST="php8.3-fpm"
BASIS_URL="$(grep -E '^APP_URL=' "$PROJEKT/.env" 2>/dev/null | cut -d= -f2- | tr -d '"' || true)"
# Optionaler systemd-Dienst, der vor dem Deploy die Datenbank sichert.
# Leer lassen, um den Schritt zu überspringen (siehe docs/backup.md).
BACKUP_DIENST=""
BACKUP_VERZEICHNIS="/var/backups/leihregal/daily"

# shellcheck source=/dev/null
[ -f "$PROJEKT/deploy.conf" ] && . "$PROJEKT/deploy.conf"

BUILD=1
TESTS=1
PROD_DEPS=0

for arg in "$@"; do
    case "$arg" in
        --skip-build) BUILD=0 ;;
        --no-tests)   TESTS=0 ;;
        --prod-deps)  PROD_DEPS=1 ;;
        *) echo "Unbekannte Option: $arg" >&2; exit 2 ;;
    esac
done

if [ "$(id -u)" -ne 0 ]; then
    echo "FEHLER: Bitte als root ausführen (Dateirechte und FPM-Reload)." >&2
    exit 1
fi

cd "$PROJEKT"

schritt() { echo; echo "── $1 ──"; }

trap 'echo; echo "ABGEBROCHEN vor dem Deploy - es wurde nichts verändert." >&2' ERR

# ── 1. Vorbedingungen ────────────────────────────────────────────────────────

schritt "Vorbedingungen"
[ -f .env ] || { echo "FEHLER: .env fehlt." >&2; exit 1; }
command -v "$PHP" >/dev/null || { echo "FEHLER: $PHP nicht gefunden." >&2; exit 1; }
command -v npm  >/dev/null || { echo "FEHLER: npm nicht gefunden." >&2; exit 1; }
echo "OK ($($PHP -r 'echo PHP_VERSION;'))"

# ── 2. Tests als Freigabe ────────────────────────────────────────────────────
# Bewusst VOR allen Änderungen: Ein Deploy, den die Tests nicht tragen, soll
# gar nicht erst beginnen. Nach --prod-deps wären sie ohnehin nicht lauffähig.

if [ "$TESTS" -eq 1 ]; then
    schritt "Tests"
    if [ ! -f vendor/bin/phpunit ]; then
        echo "FEHLER: PHPUnit fehlt - vermutlich wurde zuletzt mit --prod-deps deployt." >&2
        echo "Erst nachinstallieren: COMPOSER_ALLOW_SUPERUSER=1 $PHP $COMPOSER install" >&2
        echo "Oder bewusst überspringen: ./deploy.sh --no-tests" >&2
        exit 1
    fi
    $PHP artisan test --compact
    echo "Tests bestanden."
else
    schritt "Tests"
    echo "übersprungen (--no-tests)"
fi

# ── 3. Sicherung ─────────────────────────────────────────────────────────────
# Migrationen sind nicht immer rückwärts anwendbar. Nutzt denselben Weg wie
# der nächtliche Timer, inklusive Vollständigkeitsprüfung.

schritt "Datenbank sichern"
if [ -z "$BACKUP_DIENST" ]; then
    echo "übersprungen (BACKUP_DIENST nicht gesetzt)"
    echo "  → Ohne Sicherung ist eine fehlgeschlagene Migration nicht" >&2
    echo "    zurücknehmbar. Einrichtung siehe docs/backup.md." >&2
elif systemctl start "$BACKUP_DIENST"; then
    echo "Sicherung: $(ls -1t "$BACKUP_VERZEICHNIS"/*.sql.gz 2>/dev/null | head -1)"
else
    echo "FEHLER: Sicherung fehlgeschlagen - Deploy abgebrochen." >&2
    exit 1
fi

# ── 4. Ab hier wird verändert ────────────────────────────────────────────────

schritt "Wartungsmodus"
$PHP artisan down --retry=15 || true

# Ab jetzt muss die Seite auch bei einem Fehler wieder hochkommen.
trap '$PHP artisan up >/dev/null 2>&1 || true; echo; echo "ABGEBROCHEN - Wartungsmodus aufgehoben, Stand prüfen: $PHP artisan about" >&2' ERR

schritt "PHP-Abhängigkeiten"
if [ "$PROD_DEPS" -eq 1 ]; then
    COMPOSER_ALLOW_SUPERUSER=1 $PHP "$COMPOSER" install \
        --no-dev --optimize-autoloader --no-interaction --prefer-dist
    echo "ohne dev-Abhängigkeiten installiert"
else
    COMPOSER_ALLOW_SUPERUSER=1 $PHP "$COMPOSER" install \
        --optimize-autoloader --no-interaction --prefer-dist
    echo "inklusive dev-Abhängigkeiten (Tests bleiben lauffähig)"
fi

schritt "Frontend"
if [ "$BUILD" -eq 1 ]; then
    npm ci --no-fund --no-audit
    npm run build
else
    echo "übersprungen (--skip-build)"
fi

schritt "Migrationen"
$PHP artisan migrate --force

schritt "Caches erneuern"
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

schritt "Dateirechte"
# composer, npm und artisan laufen hier als root und hinterlassen
# root-eigene Dateien - der FPM-Pool läuft aber als leihregal.
chown -R "$BESITZER" .
chmod -R 775 storage bootstrap/cache
echo "Besitzer auf $BESITZER gesetzt"

schritt "Dienste neu laden"
systemctl reload "$FPM_DIENST"
echo "$FPM_DIENST neu geladen"

# Ab hier ist die Seite wieder online; ein Fehler soll sie nicht erneut sperren.
trap - ERR
$PHP artisan up

# ── 5. Funktionsprüfung ──────────────────────────────────────────────────────

schritt "Funktionsprüfung"
GESUND=$(curl -sk -o /dev/null -w '%{http_code}' "$BASIS_URL/healthz" || echo "000")
LOGIN=$(curl  -sk -o /dev/null -w '%{http_code}' "$BASIS_URL/login"   || echo "000")

echo "/healthz  HTTP $GESUND"
echo "/login    HTTP $LOGIN"

# Livewires JavaScript, über denselben Weg wie ein Browser.
#
# Das ist kein Luxus: Ohne dieses Skript bleibt die Oberfläche zwar sichtbar,
# aber tot. Jedes `wire:submit`-Formular fällt dann auf einen nativen
# GET-Submit zurück – beim Anmeldeformular landet dabei das Passwort in der
# URL, und niemand kommt hinein. Zwei Eigenheiten machen das leicht
# übersehbar:
#
#   1. Der Pfad lautet /livewire-<hash>/ und leitet sich aus dem APP_KEY ab.
#      Er ist pro Installation verschieden. Eine nginx-Ausnahme, die auf
#      "/livewire/" endet, greift nicht (siehe docs/installation.md).
#   2. Die Browsertests laufen über `artisan serve` und sehen den Webserver
#      gar nicht. Diese Prüfung hier ist die einzige, die es merkt.
LW_PFAD=$($PHP artisan tinker --execute='echo app("livewire")->getUriPrefix();' 2>/dev/null | tr -d "\r\n")
if [ -n "$LW_PFAD" ]; then
    LW=$(curl -sk -o /dev/null -w '%{http_code}' "${BASIS_URL}${LW_PFAD}/livewire.min.js" || echo "000")
    echo "${LW_PFAD}/livewire.min.js  HTTP $LW"
else
    LW="übersprungen"
    echo "Livewire-Skript: Pfad nicht ermittelbar, Prüfung übersprungen"
fi

if [ "$GESUND" != "200" ] || [ "$LOGIN" != "200" ]; then
    echo >&2
    echo "FEHLER: Die Seite antwortet nicht wie erwartet." >&2
    echo "Protokoll ansehen:  tail -50 $PROJEKT/storage/logs/laravel.log" >&2
    echo "Notfall-Rückweg:    siehe docs/backup.md (Abschnitt Wiederherstellung)" >&2
    exit 1
fi

if [ "$LW" != "200" ] && [ "$LW" != "übersprungen" ]; then
    echo >&2
    echo "FEHLER: Livewires JavaScript ist nicht erreichbar (HTTP $LW)." >&2
    echo "Die Oberfläche wäre sichtbar, aber ohne Funktion – und das" >&2
    echo "Anmeldeformular würde das Passwort in die URL schreiben." >&2
    echo >&2
    echo "Fast immer der Webserver: Eine Regel, die *.js statisch ausliefert," >&2
    echo "fängt ${LW_PFAD}/livewire.min.js ab. Der Pfad wird von PHP erzeugt" >&2
    echo "und liegt nicht auf der Platte. Siehe docs/installation.md." >&2
    exit 1
fi

echo
echo "Deploy abgeschlossen."
