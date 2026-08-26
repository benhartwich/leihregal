# Enthaltene Fremdbestandteile

Der eigene Quelltext dieses Projekts steht unter der [MIT-Lizenz](LICENSE).
Mitgeliefert werden zusätzlich:

## Figtree

`public/fonts/figtree-latin.woff2`, `public/fonts/figtree-latin-ext.woff2`

Copyright 2022 The Figtree Project Authors
(https://github.com/erikdkennedy/figtree)

SIL Open Font License 1.1 – vollständiger Text in
[`public/fonts/OFL.txt`](public/fonts/OFL.txt).

Die Schrift liegt bewusst im Projekt statt bei einem CDN: Eine Einbindung
über Google Fonts überträgt bei jedem Seitenaufruf die IP-Adresse der
Nutzenden an einen Dritten.

## Abhängigkeiten

Die PHP- und JavaScript-Abhängigkeiten sind nicht Teil dieses Repositories.
Sie werden über Composer und npm bezogen; ihre Lizenzen listen
`composer.lock` und `package-lock.json` beziehungsweise:

```bash
composer licenses
npm ls --all
```
