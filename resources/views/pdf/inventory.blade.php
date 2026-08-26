<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 9pt; color: #333; }
  h1 { font-size: 13pt; color: #1e40af; margin-bottom: 4px; }
  .meta { font-size: 8pt; color: #888; margin-bottom: 12px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #f1f5f9; text-align: left; padding: 5px 6px; font-size: 7.5pt;
       text-transform: uppercase; letter-spacing: 0.5pt; border-bottom: 2px solid #e2e8f0; }
  td { padding: 5px 6px; vertical-align: top; border-bottom: 1px solid #f1f5f9; font-size: 8.5pt; }
  tr:nth-child(even) td { background: #f8fafc; }
  .code { font-family: monospace; font-size: 7.5pt; color: #888; }
  .tag { display: inline-block; background: #f1f5f9; color: #475569;
         padding: 0 4px; border-radius: 3px; font-size: 7pt; margin: 1px; }
</style>
</head>
<body>
<h1>Bestandsliste {{ config('app.name') }}</h1>
<div class="meta">Stand {{ now()->format('d.m.Y') }} · {{ $media->count() }} Medien</div>

<table>
  <thead>
    <tr>
      <th>Code</th>
      <th>Titel</th>
      <th>Autor</th>
      <th>Typ</th>
      <th>Jahr</th>
      <th>Status</th>
      <th>Tags</th>
    </tr>
  </thead>
  <tbody>
    @foreach($media as $m)
    <tr>
      <td class="code">{{ $m->internal_code }}</td>
      <td><strong>{{ $m->title }}</strong></td>
      <td>{{ $m->author ?? '–' }}</td>
      <td>{{ $m->type->label() }}</td>
      <td>{{ $m->year ?? '–' }}</td>
      <td>{{ $m->status->label() }}</td>
      <td>
        @foreach($m->tags as $tag)
          <span class="tag">{{ $tag->tag }}</span>
        @endforeach
      </td>
    </tr>
    @endforeach
  </tbody>
</table>
</body>
</html>
