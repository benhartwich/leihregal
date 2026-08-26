<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 10pt; color: #333; }
  h1 { font-size: 14pt; color: #1e40af; margin-bottom: 4px; }
  .meta { font-size: 8pt; color: #888; margin-bottom: 16px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th { background: #f1f5f9; text-align: left; padding: 6px 8px; font-size: 8pt;
       text-transform: uppercase; letter-spacing: 0.5pt; border-bottom: 2px solid #e2e8f0; }
  td { padding: 6px 8px; vertical-align: top; border-bottom: 1px solid #f1f5f9; font-size: 9pt; }
  tr:nth-child(even) td { background: #f8fafc; }
  .badge { display: inline-block; padding: 1px 6px; border-radius: 4px; font-size: 7.5pt; font-weight: bold; }
  .badge-offen { background: #dbeafe; color: #1d4ed8; }
  .badge-bestellt { background: #fef9c3; color: #a16207; }
  .source { font-size: 7.5pt; color: #888; }
  .reason { font-size: 8pt; color: #555; font-style: italic; }
</style>
</head>
<body>
<h1>Anschaffungsliste {{ config('app.name') }}</h1>
<div class="meta">Erstellt am {{ now()->format('d.m.Y') }} · {{ $items->count() }} Einträge</div>

<table>
  <thead>
    <tr>
      <th>Titel / Autor</th>
      <th>Verlag</th>
      <th>ISBN</th>
      <th>Preis</th>
      <th>Status</th>
      <th>Begründung</th>
    </tr>
  </thead>
  <tbody>
    @forelse($items as $item)
    <tr>
      <td>
        <strong>{{ $item->title }}</strong>
        @if($item->author)<br><span style="color:#555">{{ $item->author }}</span>@endif
        <br><span class="source">{{ $item->source === 'ki' ? 'KI-Analyse' : 'Wunsch' }}</span>
      </td>
      <td>{{ $item->publisher ?? '–' }}</td>
      <td style="font-family:monospace;font-size:8pt">{{ $item->isbn ?? '–' }}</td>
      <td>{{ $item->price_estimate ? number_format($item->price_estimate, 2, ',', '.') . ' €' : '–' }}</td>
      <td><span class="badge badge-{{ $item->status->value }}">{{ $item->status->label() }}</span></td>
      <td class="reason">{{ $item->reason }}</td>
    </tr>
    @empty
    <tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px">Keine Einträge</td></tr>
    @endforelse
  </tbody>
</table>
</body>
</html>
