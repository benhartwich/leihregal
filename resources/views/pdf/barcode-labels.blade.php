<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
  /*
    3 Spalten × 10 Zeilen = 30 Etiketten je A4-Bogen (Spec 4.1).

    Als Tabelle mit festem Layout, nicht als Float-Raster: dompdf setzt
    Floats und Flexbox nur näherungsweise um – dabei rutschte je Bogen eine
    Zeile auf eine Folgeseite. Tabellen mit `table-layout: fixed` hält es
    dagegen masshaltig ein.
  */
  @page { margin: 0; }
  * { margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; }

  table.bogen {
    width: 210mm;
    table-layout: fixed;
    border-collapse: collapse;
    page-break-after: always;
  }
  table.bogen:last-of-type { page-break-after: auto; }

  table.bogen td {
    width: 70mm;
    height: 29.4mm;
    text-align: center;
    vertical-align: middle;
    overflow: hidden;
  }

  .barcode {
    width: 54mm;
    height: 12mm;
  }

  .code {
    font-family: 'Courier New', monospace;
    font-size: 7.5pt;
    letter-spacing: 0.4pt;
    color: #000;
  }

  .titel {
    font-size: 6pt;
    color: #555;
    line-height: 1.1;
  }
</style>
</head>
<body>
@foreach($labels->chunk(30) as $bogen)
  <table class="bogen">
    @foreach($bogen->values()->chunk(3) as $zeile)
      <tr>
        @foreach($zeile as $label)
          <td>
            <img class="barcode" src="{{ $label['barcode'] }}" alt="{{ $label['code'] }}">
            <div class="code">{{ $label['code'] }}</div>
            <div class="titel">{{ $label['title'] }}</div>
          </td>
        @endforeach

        {{-- Angebrochene letzte Zeile auffüllen, damit die Zellbreiten
             im festen Tabellenlayout erhalten bleiben. --}}
        @for($i = $zeile->count(); $i < 3; $i++)
          <td></td>
        @endfor
      </tr>
    @endforeach
  </table>
@endforeach
</body>
</html>
