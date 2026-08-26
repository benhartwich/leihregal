<!DOCTYPE html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body { font-family: Arial, sans-serif; font-size: 15px; color: #374151; background: #f9fafb; margin: 0; padding: 24px; }
  .card { background: #fff; border-radius: 12px; padding: 32px; max-width: 560px; margin: 0 auto; border: 1px solid #e5e7eb; }
  .badge { display: inline-block; background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; }
  h1 { font-size: 20px; color: #111827; margin: 16px 0 8px; }
  .meta { color: #6b7280; font-size: 14px; margin-bottom: 20px; }
  .highlight { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; font-size: 14px; color: #991b1b; margin: 16px 0; }
  .btn { display: inline-block; background: #dc2626; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; }
  .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
</style>
</head>
<body>
<div class="card">
    @include('mail._marke')

    <span class="badge">Rückgabe überfällig</span>
    <h1>{{ $loan->media->title }}</h1>
    <p class="meta">
        @if($loan->media->author){{ $loan->media->author }} &middot; @endif
        {{ $loan->media->type->label() }}
    </p>

    <p>Hallo {{ $loan->user->name }},</p>
    <p>
        das Medium <strong>{{ $loan->media->title }}</strong> ist seit
        <strong>{{ $loan->due_at->format('d.m.Y') }}</strong> zur Rückgabe fällig.
    </p>

    <div class="highlight">
        Überfällig seit {{ $loan->daysOverdue() }} {{ $loan->daysOverdue() === 1 ? 'Tag' : 'Tagen' }}
        (Ausleihe seit {{ $loan->borrowed_at->format('d.m.Y') }})
    </div>

    <p>Bitte geben Sie das Medium baldmöglichst zurück, damit andere Nutzer:innen es ausleihen können.</p>

    <p style="margin-top: 24px;">
        <a href="{{ config('app.url') }}/ausleihen" class="btn">Jetzt zurückgeben</a>
    </p>

    <div class="footer">
        {{ config('app.name') }} &mdash; {{ config('app.url') }}<br>
        Diese Erinnerung wird automatisch täglich verschickt, solange die Rückgabe aussteht.
    </div>
</div>
</body>
</html>
