#!/bin/bash
#
# Startet den Testserver für Browsertests (artisan dusk).
#
# Warum ein eigenes Skript: Der Server muss drei Dinge gleichzeitig richtig
# machen, und jedes einzelne davon ist hier schon schiefgegangen.
#
#   1. APP_ENV=dusk – damit .env.dusk geladen wird. Die Option `--env` von
#      `artisan serve` wird NICHT ausgewertet.
#   2. APP_CONFIG_CACHE auf einen nicht existierenden Pfad – sonst gewinnt
#      bootstrap/cache/config.php aus dem letzten Deploy und der Server
#      landet auf der Datenbank des laufenden Betriebs.
#   3. Bindung nur an 127.0.0.1 – der Testserver gehört nicht ins Netz.
#
# Vor dem Start wird geprüft, auf welche Datenbank der Server tatsächlich
# zeigen würde. Es gelten dieselben zwei Bedingungen wie in der Testsuite
# (tests/Concerns/PrueftTestDatenbank.php): Der Name muss auf `_test` enden
# und darf nicht der aus der .env sein. Stimmt etwas nicht, startet der
# Server gar nicht erst.
#
set -euo pipefail

cd "$(dirname "$0")"

# Auf Servern mit mehreren PHP-Versionen ausdrücklich setzen, etwa PHP=php8.3
PHP="${PHP:-php}"
PORT="${PORT:-8123}"

export APP_ENV=dusk
export APP_CONFIG_CACHE=bootstrap/cache/config.dusk-niemals-anlegen.php
export APP_ROUTES_CACHE=bootstrap/cache/routes.dusk-niemals-anlegen.php

DB=$($PHP -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo config("database.connections." . config("database.default") . ".database");
')

BETRIEB=$(sed -nE 's/^[[:space:]]*DB_DATABASE[[:space:]]*=[[:space:]]*"?([^"#[:space:]]+)"?.*/\1/p' .env 2>/dev/null | head -1)

if [ "${DB%_test}" = "$DB" ]; then
    echo "ABBRUCH: Der Testserver würde auf die Datenbank '$DB' zeigen." >&2
    echo "Der Name folgt nicht der Konvention – erwartet wird die Endung '_test'." >&2
    echo "Bitte .env.dusk prüfen." >&2
    exit 1
fi

if [ -n "$BETRIEB" ] && [ "$DB" = "$BETRIEB" ]; then
    echo "ABBRUCH: Der Testserver würde auf '$DB' zeigen." >&2
    echo "Das ist die Datenbank aus der .env, also die des laufenden Betriebs." >&2
    echo "Browsertests leeren sie. Bitte .env.dusk prüfen." >&2
    exit 1
fi

echo "Testserver auf http://127.0.0.1:${PORT} – Datenbank: $DB"
exec $PHP artisan serve --host=127.0.0.1 --port="$PORT" --no-reload
