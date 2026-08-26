<!DOCTYPE html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body { font-family: Arial, sans-serif; font-size: 15px; color: #374151; background: #f9fafb; margin: 0; padding: 24px; }
  .card { background: #fff; border-radius: 12px; padding: 32px; max-width: 560px; margin: 0 auto; border: 1px solid #e5e7eb; }
  .badge { display: inline-block; background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; }
  h1 { font-size: 20px; color: #111827; margin: 16px 0 8px; }
  .meta { color: #6b7280; font-size: 14px; margin-bottom: 20px; }
  .highlight { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; font-size: 14px; color: #92400e; margin: 16px 0; }
  .btn { display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; }
  .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
</style>
</head>
<body>
<div class="card">
    @include('mail._marke')

    <span class="badge">
        @if($verbleibendeTage === 0) Heute fällig
        @elseif($verbleibendeTage === 1) Morgen fällig
        @else Rückgabe steht an
        @endif
    </span>
    <h1>{{ $loan->media->title }}</h1>
    <p class="meta">
        @if($loan->media->author){{ $loan->media->author }} &middot; @endif
        {{ $loan->media->type->label() }}
    </p>

    <p>Hallo {{ $loan->user->name }},</p>
    <p>
        eine kurze Erinnerung: <strong>{{ $loan->media->title }}</strong> ist
        @if($verbleibendeTage === 0)
            <strong>heute</strong> zur Rückgabe fällig.
        @elseif($verbleibendeTage === 1)
            <strong>morgen</strong> zur Rückgabe fällig.
        @else
            in <strong>{{ $verbleibendeTage }} Tagen</strong> zur Rückgabe fällig.
        @endif
    </p>

    <div class="highlight">
        Rückgabe bis {{ $loan->due_at->format('d.m.Y') }}
        (Ausleihe seit {{ $loan->borrowed_at->format('d.m.Y') }})
    </div>

    <p>Sie brauchen länger? Solange niemand auf das Medium wartet, können Sie die Ausleihe verlängern.</p>

    <p style="margin-top: 24px;">
        <a href="{{ config('app.url') }}/ausleihen" class="btn">Zu meinen Ausleihen</a>
    </p>

    <div class="footer">
        {{ config('app.name') }} &mdash; {{ config('app.url') }}<br>
        Diese Erinnerung erhalten Sie 3 Tage und 1 Tag vor Fristende sowie am Fälligkeitstag.
    </div>
</div>
</body>
</html>
