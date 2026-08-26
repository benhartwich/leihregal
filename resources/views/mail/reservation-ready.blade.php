<!DOCTYPE html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body { font-family: Arial, sans-serif; font-size: 15px; color: #374151; background: #f9fafb; margin: 0; padding: 24px; }
  .card { background: #fff; border-radius: 12px; padding: 32px; max-width: 560px; margin: 0 auto; border: 1px solid #e5e7eb; }
  .badge { display: inline-block; background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; }
  h1 { font-size: 20px; color: #111827; margin: 16px 0 8px; }
  .meta { color: #6b7280; font-size: 14px; margin-bottom: 20px; }
  .btn { display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; }
  .footer { font-size: 12px; color: #9ca3af; margin-top: 24px; border-top: 1px solid #f3f4f6; padding-top: 16px; }
</style>
</head>
<body>
<div class="card">
    @include('mail._marke')

    <span class="badge">Bereit zur Abholung</span>
    <h1>{{ $reservation->media->title }}</h1>
    <p class="meta">
        @if($reservation->media->author){{ $reservation->media->author }} &middot; @endif
        {{ $reservation->media->type->label() }}
    </p>

    <p>Hallo {{ $reservation->user->name }},</p>
    <p>Ihre Reservierung ist jetzt zur Abholung bereit. Bitte holen Sie das Medium zeitnah ab.</p>
    <p>
        Zur Abholung einfach den Barcode scannen oder den Code
        <strong>{{ $reservation->media->internal_code }}</strong> eingeben.
    </p>

    <p style="margin-top: 24px;">
        <a href="{{ config('app.url') }}/ausleihen" class="btn">Zu meinen Ausleihen</a>
    </p>

    <div class="footer">
        {{ config('app.name') }} &mdash; {{ config('app.url') }}<br>
        Sie erhalten diese E-Mail, weil Sie ein Medium reserviert haben.
    </div>
</div>
</body>
</html>
