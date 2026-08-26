#!/bin/bash
#
# Erzeugt alle abgeleiteten Bilddateien aus den SVG-Vorlagen unter
# public/brand/. Aufruf aus dem Projektverzeichnis:
#
#   ./ops/marke/icons-erzeugen.sh
#
# Voraussetzungen (Debian/Ubuntu):
#   apt install librsvg2-bin imagemagick
#
# Warum rsvg-convert und nicht ImageMagick allein: Der interne SVG-Renderer
# von ImageMagick löst weder Farbverläufe noch `transform` korrekt auf – die
# Marke kommt schwarz und verschoben heraus.
#
# Wer die Anwendung unter eigenem Namen betreibt, ändert die SVG-Vorlagen
# (Farben, Wortmarke im Vorschaubild) und lässt dieses Skript neu laufen.
# Einzelheiten in docs/marke.md.
#
set -euo pipefail

cd "$(dirname "$0")/../../public"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# Grundfarbe für das Apple-Icon. Muss zu BRAND_FARBE passen: iOS legt hinter
# transparente Bereiche sonst Schwarz.
FARBE="${BRAND_FARBE:-#2563EB}"

echo "App-Icons …"
rsvg-convert -w 192  -h 192 brand/leihregal-mark.svg          -o icon-192.png
rsvg-convert -w 512  -h 512 brand/leihregal-mark.svg          -o icon-512.png
rsvg-convert -w 512  -h 512 brand/leihregal-mark-maskable.svg -o icon-maskable-512.png

echo "Vorschaubild für geteilte Links …"
rsvg-convert -w 1200 -h 630 brand/leihregal-vorschau.svg      -o og-bild.png

echo "Favicon …"
cp brand/leihregal-mark-kompakt.svg favicon.svg
for g in 16 32 48; do
    rsvg-convert -w "$g" -h "$g" brand/leihregal-mark-kompakt.svg -o "$TMP/f$g.png"
done
magick "$TMP/f16.png" "$TMP/f32.png" "$TMP/f48.png" favicon.ico

echo "Apple-Touch-Icon …"
# Bewusst ohne Alphakanal, die abgerundeten Ecken setzt iOS selbst.
rsvg-convert -w 180 -h 180 brand/leihregal-mark.svg -o "$TMP/apple.png"
magick "$TMP/apple.png" -background "$FARBE" -alpha remove -alpha off apple-touch-icon.png

echo "Fertig. Erzeugt in $(pwd):"
ls -1 icon-192.png icon-512.png icon-maskable-512.png og-bild.png favicon.svg favicon.ico apple-touch-icon.png
