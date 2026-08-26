<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 18mm 16mm; }
  * { margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 10pt; color: #1f2937; line-height: 1.45; }

  h1 { font-size: 18pt; margin-bottom: 2mm; }
  h2 { font-size: 11pt; margin: 7mm 0 2.5mm; padding-bottom: 1mm; border-bottom: 0.4mm solid #e5e7eb; }
  .kopf   { margin-bottom: 6mm; }
  .zeitraum { color: #6b7280; font-size: 9.5pt; }
  .marke      { margin-bottom: 4mm; }
  .marke img  { width: 9mm; height: 9mm; vertical-align: middle; }
  .marke span { font-size: 13pt; font-weight: bold; color: #111827; vertical-align: middle; padding-left: 2mm; }

  table { width: 100%; border-collapse: collapse; margin-top: 1.5mm; }
  th, td { text-align: left; padding: 1.6mm 2mm; font-size: 9.5pt; }
  thead th { background: #f3f4f6; font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.3pt; color: #4b5563; }
  tbody tr { border-bottom: 0.2mm solid #f3f4f6; }
  td.zahl, th.zahl { text-align: right; }

  .kacheln { width: 100%; border-collapse: separate; border-spacing: 2mm 0; margin-left: -2mm; }
  .kachel { background: #f9fafb; border: 0.2mm solid #e5e7eb; padding: 3mm; width: 25%; }
  .kachel .wert  { font-size: 16pt; font-weight: bold; color: #111827; }
  .kachel .titel { font-size: 8pt; color: #6b7280; }

  .hinweis { background: #fffbeb; border: 0.2mm solid #fde68a; padding: 2.5mm 3mm; font-size: 9pt; color: #92400e; margin-top: 2mm; }
  .leer { color: #9ca3af; font-style: italic; font-size: 9.5pt; padding: 2mm 0; }
  .fuss { margin-top: 9mm; padding-top: 2.5mm; border-top: 0.2mm solid #e5e7eb; font-size: 8pt; color: #9ca3af; }
</style>
</head>
<body>

{{--
    Bildmarke als Daten-URI: dompdf müsste die Datei sonst vom Dateisystem
    lesen, was je nach Arbeitsverzeichnis fehlschlägt.
--}}
<div class="marke">
  <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('icon-192.png'))) }}" alt="">
  <span>{{ config('app.name') }}</span>
</div>

<div class="kopf">
  <h1>Quartalsbericht {{ $bericht['bezeichnung'] }}</h1>
  <p class="zeitraum">
    Medienbibliothek ·
    {{ $bericht['von']->format('d.m.Y') }} bis {{ $bericht['bis']->format('d.m.Y') }}
  </p>
</div>

<h2>Nutzung</h2>
<table class="kacheln">
  <tr>
    <td class="kachel">
      <div class="wert">{{ $bericht['ausleihen']['gesamt'] }}</div>
      <div class="titel">Ausleihen</div>
    </td>
    <td class="kachel">
      <div class="wert">{{ $bericht['ausleihen']['nutzer'] }}</div>
      <div class="titel">aktive Personen</div>
    </td>
    <td class="kachel">
      <div class="wert">{{ $bericht['ausleihen']['schnittTage'] ?? '–' }}</div>
      <div class="titel">Tage im Schnitt</div>
    </td>
    <td class="kachel">
      <div class="wert">{{ $bericht['ausleihen']['verlaengerungen'] }}</div>
      <div class="titel">verlängert</div>
    </td>
  </tr>
</table>

@if($bericht['ausleihen']['ueberfaellig'] > 0)
  <div class="hinweis">
    {{ $bericht['ausleihen']['ueberfaellig'] }} Ausleihe(n) waren zum Quartalsende überfällig.
  </div>
@endif

<h2>Meistgenutzte Medien</h2>
@if(empty($bericht['beliebteste']))
  <p class="leer">Im Berichtszeitraum wurde nichts ausgeliehen.</p>
@else
  <table>
    <thead>
      <tr><th>Titel</th><th>Art</th><th class="zahl">Ausleihen</th></tr>
    </thead>
    <tbody>
      @foreach($bericht['beliebteste'] as $eintrag)
        <tr>
          <td>{{ $eintrag['title'] }}</td>
          <td>{{ \App\Enums\MediaType::from($eintrag['type'])->label() }}</td>
          <td class="zahl">{{ $eintrag['anzahl'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif

<h2>Gefragte Themen</h2>
@if(empty($bericht['themen']))
  <p class="leer">Keine Daten im Berichtszeitraum.</p>
@else
  <table>
    <thead>
      <tr><th>Thema</th><th class="zahl">Ausleihen</th></tr>
    </thead>
    <tbody>
      @foreach($bericht['themen'] as $thema)
        <tr>
          <td>{{ $thema->tag }}</td>
          <td class="zahl">{{ $thema->anzahl }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif

<h2>Nicht genutzt</h2>
<p class="zeitraum">Seit Quartalsbeginn nicht ausgeliehen – Kandidaten für gezielte Bewerbung oder Aussortierung.</p>
@if(empty($bericht['ungenutzt']))
  <p class="leer">Jedes Medium wurde mindestens einmal ausgeliehen.</p>
@else
  <table>
    <thead>
      <tr><th>Titel</th><th>Art</th><th>Im Bestand seit</th></tr>
    </thead>
    <tbody>
      @foreach($bericht['ungenutzt'] as $eintrag)
        <tr>
          <td>{{ $eintrag['title'] }}</td>
          <td>{{ \App\Enums\MediaType::from($eintrag['type'])->label() }}</td>
          <td>{{ \Carbon\Carbon::parse($eintrag['created_at'])->format('m/Y') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif

<h2>Bestand</h2>
<table>
  <tbody>
    <tr><td>Medien im Bestand</td><td class="zahl">{{ $bericht['bestand']['gesamt'] }}</td></tr>
    <tr><td>im Quartal aufgenommen</td><td class="zahl">{{ $bericht['bestand']['neu'] }}</td></tr>
    <tr><td>ausgemustert</td><td class="zahl">{{ $bericht['bestand']['ausgemustert'] }}</td></tr>
    <tr><td>als verloren gemeldet</td><td class="zahl">{{ $bericht['bestand']['verloren'] }}</td></tr>
  </tbody>
</table>

@if($bericht['bestand']['ohneEmbedding'] > 0)
  <div class="hinweis">
    {{ $bericht['bestand']['ohneEmbedding'] }} Medium/Medien haben kein Embedding und
    erscheinen daher weder in der semantischen Suche noch beim Assistenten.
    Abhilfe: <em>php8.3 artisan media:backfill-embeddings</em>
  </div>
@endif

<h2>Wünsche und Rückmeldungen</h2>
<table>
  <tbody>
    <tr><td>neu eingereichte Wünsche</td><td class="zahl">{{ $bericht['wuensche']['neu'] }}</td></tr>
    <tr><td>angenommen</td><td class="zahl">{{ $bericht['wuensche']['angenommen'] }}</td></tr>
    <tr><td>abgelehnt</td><td class="zahl">{{ $bericht['wuensche']['abgelehnt'] }}</td></tr>
    <tr><td>derzeit unbearbeitet</td><td class="zahl">{{ $bericht['wuensche']['offen'] }}</td></tr>
    <tr><td>Bewertungen abgegeben</td><td class="zahl">{{ $bericht['bewertungen']['gesamt'] }}</td></tr>
    <tr><td>davon positiv / negativ</td><td class="zahl">{{ $bericht['bewertungen']['positiv'] }} / {{ $bericht['bewertungen']['negativ'] }}</td></tr>
  </tbody>
</table>

<div class="fuss">
  Automatisch erstellt am {{ now()->format('d.m.Y H:i') }} · {{ config('app.name') }}
</div>

</body>
</html>
