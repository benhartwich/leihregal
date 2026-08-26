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
#      landet auf der PRODUKTIONSDATENBANK.
#   3. Bindung nur an 127.0.0.1 – der Testserver gehört nicht ins Netz.
#
# Vor dem Start wird geprüft, auf welche Datenbank der Server tatsächlich
# zeigen würde. Stimmt sie nicht, startet er gar nicht erst.
#
set -euo pipefail

cd "$(dirname "$0")"

# Auf Servern mit mehreren PHP-Versionen ausdrücklich setzen, etwa PHP=php8.3
PHP="${PHP:-php}"
# Datenbank, auf die der Testserver zeigen muss. Muss auf _test enden.
TEST_DB="${TEST_DB:-leihregal_test}"

export APP_ENV=dusk
export APP_CONFIG_CACHE=bootstrap/cache/config.dusk-niemals-anlegen.php
export APP_ROUTES_CACHE=bootstrap/cache/routes.dusk-niemals-anlegen.php

DB=$($PHP -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo config("database.connections." . config("database.default") . ".database");
')

if [ "$DB" != "$TEST_DB" ]; then
    echo "ABBRUCH: Der Testserver würde auf die Datenbank '$DB' zeigen." >&2
    echo "Erwartet wird '$TEST_DB'. Bitte .env.dusk prüfen." >&2
    exit 1
fi

echo "Testserver auf http://127.0.0.1:8123 – Datenbank: $DB"
exec $PHP artisan serve --host=127.0.0.1 --port=8123 --no-reload
