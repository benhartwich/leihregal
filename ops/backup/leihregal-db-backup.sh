#!/bin/bash
#
# Tägliches Datenbank-Backup.
#
# Läuft als root über leihregal-db-backup.timer und authentifiziert sich per
# Unix-Socket – so liegt kein Datenbankpasswort auf der Platte.
#
# Einstellungen kommen aus /etc/default/leihregal-backup, falls vorhanden.
# Einrichtung: siehe docs/backup.md.
#
set -euo pipefail

DB="leihregal"
ZIEL="/var/backups/leihregal/daily"
AUFBEWAHRUNG_TAGE=14

# shellcheck source=/dev/null
[ -f /etc/default/leihregal-backup ] && . /etc/default/leihregal-backup

STAMP="$(date +%Y%m%d-%H%M%S)"
DATEI="${ZIEL}/${DB}-${STAMP}.sql.gz"

mkdir -p "$ZIEL"
chmod 700 "$ZIEL"

TMP="$(mktemp "${ZIEL}/.${DB}-${STAMP}.XXXXXX.sql")"
trap 'rm -f "$TMP"' EXIT

echo "Sichere Datenbank '${DB}' …"

# --single-transaction: konsistenter Snapshot ohne Schreibsperre auf InnoDB.
# Routinen/Trigger/Events mitnehmen, damit der Dump allein wiederherstellbar ist.
mariadb-dump \
    --user=root \
    --single-transaction \
    --quick \
    --routines \
    --events \
    --triggers \
    --default-character-set=utf8mb4 \
    "$DB" > "$TMP"

# mariadb-dump kann mit Exit-Code 0 abbrechen, wenn die Verbindung mittendrin
# wegfällt. Die Abschlusszeile ist der verlässliche Vollständigkeitsbeweis –
# ohne sie wird nichts rotiert.
if ! tail -5 "$TMP" | grep -q '^-- Dump completed'; then
    echo "FEHLER: Dump ist unvollständig, Abschlusszeile fehlt. Breche ab." >&2
    exit 1
fi

TABELLEN="$(grep -c '^CREATE TABLE' "$TMP" || true)"
if [ "$TABELLEN" -lt 1 ]; then
    echo "FEHLER: Dump enthält keine Tabellen. Breche ab." >&2
    exit 1
fi

gzip -c "$TMP" > "$DATEI"
chmod 600 "$DATEI"

# Archiv gegenlesen, bevor alte Stände gelöscht werden.
if ! gzip -t "$DATEI"; then
    echo "FEHLER: Archiv ${DATEI} ist defekt. Breche ab." >&2
    rm -f "$DATEI"
    exit 1
fi

echo "Backup geschrieben: ${DATEI} ($(du -h "$DATEI" | cut -f1), ${TABELLEN} Tabellen)"

# Rotation – bewusst auf ${ZIEL} begrenzt, damit manuell angelegte
# Sicherungen in /var/backups/leihregal/ unangetastet bleiben.
GELOESCHT="$(find "$ZIEL" -maxdepth 1 -type f -name "${DB}-*.sql.gz" -mtime "+${AUFBEWAHRUNG_TAGE}" -print -delete | wc -l)"
VERBLEIBEND="$(find "$ZIEL" -maxdepth 1 -type f -name "${DB}-*.sql.gz" | wc -l)"

echo "Rotation: ${GELOESCHT} Sicherung(en) älter als ${AUFBEWAHRUNG_TAGE} Tage entfernt, ${VERBLEIBEND} vorhanden."
