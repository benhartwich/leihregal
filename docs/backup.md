# Datensicherung

Zwei Ebenen, die unterschiedliche Fehler auffangen. Wer nur eine davon hat,
hat kein Backup.

| Ebene | Fängt ab | Deckt ab |
|---|---|---|
| **Serverweite Sicherung** auf ein fremdes Medium | Festplattendefekt, Totalverlust des Servers | Anwendungscode, hochgeladene Cover unter `storage/`, die `.env` |
| **Täglicher Datenbank-Snapshot** mit Historie | fehlgeschlagene Migration, versehentliches Löschen, Fehleingabe von vorgestern | nur die Datenbank, dafür 14 Tage rückwirkend |

Die zweite Ebene liefert dieses Projekt fertig mit: `ops/backup/`. Die erste
hängt von Ihrem Hoster ab und ist hier nicht abgebildet – wichtig ist nur,
dass sie existiert und `storage/` sowie die `.env` einschließt.

---

## Datenbank-Snapshot einrichten

Vorausgesetzt wird ein Debian-artiges System mit systemd und MariaDB.

**1. Skript und Einheiten ablegen**

```bash
install -m 0700 ops/backup/leihregal-db-backup.sh /usr/local/sbin/
install -m 0644 ops/backup/leihregal-db-backup.service \
                ops/backup/leihregal-db-backup.timer \
                ops/backup/leihregal-backup-alert.service \
                /etc/systemd/system/
```

**2. Anpassen, falls Ihre Datenbank anders heißt**

```bash
cat > /etc/default/leihregal-backup <<'EOF'
DB="leihregal"
ZIEL="/var/backups/leihregal/daily"
AUFBEWAHRUNG_TAGE=14
EOF
```

Heißt die Datenbank anders, muss auch `ReadWritePaths=` in
`leihregal-db-backup.service` auf das passende Zielverzeichnis zeigen –
`ProtectSystem=strict` verhindert sonst das Schreiben.

**3. Starten**

```bash
systemctl daemon-reload
systemctl enable --now leihregal-db-backup.timer
systemctl start leihregal-db-backup.service   # erster Lauf zur Kontrolle
```

**4. Alarm zustellbar machen**

Schlägt die Sicherung fehl, verschickt `leihregal-backup-alert.service` eine
Mail an `root`. Damit die jemand liest, muss ein lokaler MTA laufen und
`/etc/aliases` `root` auf eine echte Adresse weiterleiten.

> Ein Backup, dessen Scheitern niemand bemerkt, ist kein Backup. Prüfen Sie
> die Zustellung einmal mit einem absichtlich herbeigeführten Fehlschlag –
> etwa, indem Sie `DB` vorübergehend auf einen nicht existierenden Namen
> setzen.

---

## Was das Skript tut

| Was | Wert |
|---|---|
| Zeitpunkt | täglich 03:30 Uhr (+ bis zu 5 min Streuung) |
| Ablageort | `/var/backups/leihregal/daily/` |
| Dateiname | `leihregal-JJJJMMTT-HHMMSS.sql.gz` |
| Aufbewahrung | 14 Tage, danach automatisch gelöscht |
| Rechte | `0600 root:root`, Verzeichnis `0700` |

Der Zeitpunkt liegt bewusst abseits des Laravel-Schedulers (Fristerinnerungen
um 08:00, Wunsch-Bündelung sonntags um 02:00). Der Timer holt versäumte Läufe
nach (`Persistent=true`), falls der Server aus war.

Gesichert wird mit `--single-transaction`: ein konsistenter Snapshot ohne
Schreibsperre. Routinen, Trigger und Events kommen mit, damit der Dump allein
wiederherstellbar ist.

**Rotiert wird erst, wenn der neue Stand nachweislich vollständig ist.**
`mariadb-dump` kann mit Exit-Code 0 abbrechen, wenn die Verbindung mittendrin
wegfällt – ein abgeschnittener Dump sieht dann aus wie ein gelungener.
Geprüft werden deshalb drei Dinge: die Abschlusszeile `-- Dump completed`,
mindestens eine Tabelle und die Integrität des gzip-Archivs. Fällt eine davon
durch, bleiben alle alten Sicherungen liegen und der Lauf schlägt fehl.

---

## Status prüfen

```bash
systemctl list-timers leihregal-db-backup.timer
journalctl -u leihregal-db-backup.service -n 20
ls -lh /var/backups/leihregal/daily/
```

Sofort auslösen:

```bash
systemctl start leihregal-db-backup.service
```

---

## Wiederherstellung

> **Achtung:** Der Dump enthält `DROP TABLE`-Anweisungen. Ein Restore
> überschreibt den kompletten aktuellen Datenbestand.

**1. Ist-Zustand sichern**, falls die Datenbank noch steht:

```bash
mariadb-dump -u root --single-transaction leihregal \
  > /var/backups/leihregal/vor-restore-$(date +%Y%m%d-%H%M%S).sql
```

**2. Erst gegen die Testdatenbank prüfen**, ob der Stand brauchbar ist:

```bash
gunzip -c /var/backups/leihregal/daily/leihregal-JJJJMMTT-HHMMSS.sql.gz \
  | mariadb -u root leihregal_test
mariadb -u root leihregal_test -e "SELECT COUNT(*) FROM media; SELECT COUNT(*) FROM users;"
```

**3. Zurückspielen:**

```bash
gunzip -c /var/backups/leihregal/daily/leihregal-JJJJMMTT-HHMMSS.sql.gz \
  | mariadb -u root leihregal
```

**4. Caches leeren und FPM neu laden:**

```bash
cd /pfad/zum/projekt
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R leihregal:www-data bootstrap/cache storage
systemctl reload php8.3-fpm
```

Der Dump stellt auch den `VECTOR`-Index (`DISTANCE=cosine`) auf
`media_embeddings` und den `FULLTEXT`-Index `ft_media_search` auf `media`
wieder her – beide sind für Suche und Empfehlungen nötig.

---

## Hinweise zur serverweiten Ebene

- Die `.env` enthält API-Schlüssel und den Datenbankzugang. Sie muss
  mitgesichert werden **und** das Sicherungsziel muss entsprechend
  geschützt sein.
- Die Cover liegen unter `storage/app/public/`. Sie stehen in keinem Dump.
- Sichert die serverweite Ebene mit `rsync --delete`, hält sie nur den
  jeweils letzten Stand vor. Nehmen Sie deshalb `/var/backups/leihregal/`
  in die Spiegelung mit auf – sonst liegt die 14-Tage-Historie ausschließlich
  auf derselben Festplatte wie die Produktivdatenbank.
- Datenbankpasswörter gehören nicht in Kommandozeilen (`mysqldump -p…`): auf
  einem Server mit mehreren Nutzern sind sie dort über `ps` sichtbar. Nutzen
  Sie `/root/.my.cnf` mit Rechten `0600`. Dieses Backup braucht die Datei
  nicht – es authentifiziert sich als root über den Unix-Socket.
