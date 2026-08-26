<!DOCTYPE html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body { font-family: Arial, sans-serif; font-size: 15px; color: #374151; background: #f9fafb; margin: 0; padding: 24px; }
  .card { background: #fff; border-radius: 12px; padding: 32px; max-width: 560px; margin: 0 auto; border: 1px solid #e5e7eb; }
  .badge { display: inline-block; background: #dbeafe; color: #1e40af; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; }
  h1 { font-size: 20px; color: #111827; margin: 16px 0 8px; }
  table { width: 100%; border-collapse: collapse; margin: 16px 0; }
  td { padding: 6px 0; font-size: 14px; border-bottom: 1px solid #f3f4f6; }
  td.zahl { text-align: right; font-weight: 600; color: #111827; }
  .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
</style>
</head>
<body>
<div class="card">
    @include('mail._marke')

    <span class="badge">Quartalsbericht</span>
    <h1>{{ $bericht['bezeichnung'] }}</h1>
    <p style="color:#6b7280;font-size:14px;">
        {{ $bericht['von']->format('d.m.Y') }} bis {{ $bericht['bis']->format('d.m.Y') }}
    </p>

    <p>Der vollständige Bericht liegt als PDF bei. Die wichtigsten Zahlen vorab:</p>

    <table>
        <tr><td>Ausleihen</td><td class="zahl">{{ $bericht['ausleihen']['gesamt'] }}</td></tr>
        <tr><td>Aktive Personen</td><td class="zahl">{{ $bericht['ausleihen']['nutzer'] }}</td></tr>
        <tr><td>Neu im Bestand</td><td class="zahl">{{ $bericht['bestand']['neu'] }}</td></tr>
        <tr><td>Medien im Bestand</td><td class="zahl">{{ $bericht['bestand']['gesamt'] }}</td></tr>
        <tr><td>Neue Wünsche</td><td class="zahl">{{ $bericht['wuensche']['neu'] }}</td></tr>
        <tr><td>Unbearbeitete Wünsche</td><td class="zahl">{{ $bericht['wuensche']['offen'] }}</td></tr>
    </table>

    @if($bericht['wuensche']['offen'] > 0)
        <p style="font-size:14px;">
            {{ $bericht['wuensche']['offen'] }} Wunsch/Wünsche warten auf eine Entscheidung.
        </p>
    @endif

    <div class="footer">
        {{ config('app.name') }} &mdash; {{ config('app.url') }}<br>
        Dieser Bericht wird automatisch zu Quartalsbeginn an Kuratorinnen, Kuratoren und die Administration verschickt.
    </div>
</div>
</body>
</html>
