<!DOCTYPE html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body { font-family: Arial, sans-serif; font-size: 15px; color: #374151; background: #f9fafb; margin: 0; padding: 24px; }
  .card { background: #fff; border-radius: 12px; padding: 32px; max-width: 560px; margin: 0 auto; border: 1px solid #e5e7eb; }
  .badge { display: inline-block; background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; }
  h1 { font-size: 20px; color: #111827; margin: 16px 0 8px; }
  .meta { color: #6b7280; font-size: 14px; margin-bottom: 20px; }
  .dates { background: #eff6ff; border-radius: 8px; padding: 14px 16px; margin: 20px 0; font-size: 14px; }
  .btn { display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; }
  .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
</style>
</head>
<body>
<div class="card">
    @include('mail._marke')

    <span class="badge">Urlaubsreservierung – bald verfügbar</span>
    <h1>{{ $reservation->media->title }}</h1>
    <p class="meta">
        @if($reservation->media->author){{ $reservation->media->author }} &middot; @endif
        {{ $reservation->media->type->label() }}
    </p>

    <p>Hallo {{ $reservation->user->name }},</p>
    <p>das Medium für deine Urlaubsreservierung ist jetzt verfügbar und kann abgeholt werden.</p>

    <div class="dates">
        <strong>Dein reservierter Zeitraum:</strong><br>
        {{ $reservation->reserved_from->format('d.m.Y') }} – {{ $reservation->reserved_until->format('d.m.Y') }}
    </div>

    <p>
        Beim Abholen einfach den Barcode scannen oder den Code
        <strong>{{ $reservation->media->internal_code }}</strong> eingeben.
    </p>

    <p style="margin-top: 24px;">
        <a href="{{ url('/medien/' . $reservation->media_id) }}" class="btn">Zum Medium</a>
    </p>

    <div class="footer">
        {{ config('app.name') }} &mdash; {{ config('app.url') }}<br>
        Du erhältst diese E-Mail, weil du eine Urlaubsreservierung vorgenommen hast.
    </div>
</div>
</body>
</html>
